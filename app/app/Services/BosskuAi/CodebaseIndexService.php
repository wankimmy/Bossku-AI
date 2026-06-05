<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\CodeChunk;
use App\Services\Llm\OllamaClient;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Semantic codebase index: chunk project files, embed them with Ollama, and retrieve the
 * top-K most relevant chunks for a query. This replaces dumping a directory sample / whole
 * files into agent prompts — the planner and executor get only the code that matters, which
 * cuts tokens sharply and keeps prompts small enough to avoid large-context truncation.
 *
 * Storage mirrors MemoryService: native pgvector on Postgres, a JSON column + in-PHP cosine
 * fallback on sqlite (tests), and a keyword fallback when embeddings are disabled.
 */
class CodebaseIndexService
{
    private const EMBED_DIM = 1536;

    private const MAX_FILE_BYTES = 262144; // 256 KB — skip large/generated files

    private const CHUNK_LINES = 60;

    private const CHUNK_OVERLAP_LINES = 10;

    /** @var list<string> */
    private const CODE_EXTENSIONS = [
        'php', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'vue', 'py', 'go', 'rs', 'rb',
        'java', 'kt', 'c', 'h', 'cpp', 'hpp', 'cs', 'swift', 'sh', 'sql', 'json', 'yaml',
        'yml', 'md', 'css', 'scss', 'html', 'blade.php', 'env', 'toml',
    ];

    public function __construct(
        protected OllamaClient $ollama,
        protected RuntimeSettings $settings,
    ) {}

    public function embeddingsEnabled(): bool
    {
        return $this->settings->memoryOllamaEnabled();
    }

    /**
     * Index every supported file under $root. Unchanged files (same file hash) are skipped;
     * changed/removed files have their stale chunks deleted. Returns simple stats.
     *
     * @param  array{skip_dirs?: list<string>}  $options
     * @return array{files: int, chunks: int, skipped: int, embedded: int}
     */
    public function indexDirectory(string $root, ?string $projectId = null, array $options = []): array
    {
        $stats = ['files' => 0, 'chunks' => 0, 'skipped' => 0, 'embedded' => 0];
        $realRoot = is_dir($root) ? rtrim($root, '/\\') : '';
        if ($realRoot === '') {
            return $stats;
        }

        $skipDirs = $options['skip_dirs'] ?? (array) config('bossku.skip_dirs', []);
        $embeddingsOn = $this->embeddingsEnabled() && $this->supportsVectorEmbeddings();
        $seenPaths = [];

        foreach ($this->walk($realRoot, $skipDirs) as $file) {
            $abs = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($abs, strlen($realRoot))), '/');
            if (! $this->isIndexable($file)) {
                continue;
            }
            $content = @file_get_contents($abs);
            if ($content === false || $content === '' || ! mb_check_encoding($content, 'UTF-8')) {
                continue;
            }

            $fileHash = hash('sha256', $content);
            $seenPaths[] = $relative;

            $existing = CodeChunk::query()
                ->where('project_id', $projectId)
                ->where('path', $relative)
                ->value('file_hash');
            if ($existing === $fileHash) {
                $stats['skipped']++;

                continue; // unchanged
            }

            // Changed: drop old chunks for this path, then re-chunk.
            CodeChunk::query()->where('project_id', $projectId)->where('path', $relative)->delete();

            $stats['files']++;
            foreach ($this->chunk($content) as $i => $chunk) {
                /** @var CodeChunk $row */
                $row = CodeChunk::query()->create([
                    'project_id' => $projectId,
                    'path' => $relative,
                    'language' => $this->extension($file),
                    'chunk_index' => $i,
                    'start_line' => $chunk['start_line'],
                    'end_line' => $chunk['end_line'],
                    'content' => $chunk['content'],
                    'content_hash' => hash('sha256', $chunk['content']),
                    'file_hash' => $fileHash,
                    'token_estimate' => (int) max(1, (int) round(mb_strlen($chunk['content']) / 4)),
                ]);
                $stats['chunks']++;

                if ($embeddingsOn) {
                    try {
                        $vec = $this->normalizeEmbedding($this->ollama->embed(
                            $relative."\n".$chunk['content'],
                            $this->settings->ollamaEmbeddingPhysicalModel(),
                        ));
                        if (count($vec) >= 64) {
                            $this->persistEmbedding($row->getKey(), $vec);
                            $stats['embedded']++;
                        }
                    } catch (\Throwable) {
                        // Leave the chunk searchable via keyword fallback.
                    }
                }
            }
        }

        // Prune chunks for files that no longer exist.
        if ($seenPaths !== []) {
            CodeChunk::query()
                ->where('project_id', $projectId)
                ->whereNotIn('path', $seenPaths)
                ->delete();
        }

        return $stats;
    }

    /**
     * Retrieve the top-K most relevant code chunks for a natural-language / code query.
     *
     * @return list<array{path: string, content: string, similarity: float, start_line: int|null, end_line: int|null}>
     */
    public function retrieve(string $query, int $topK = 8, ?string $projectId = null): array
    {
        $query = trim($query);
        if ($query === '' || $topK < 1) {
            return [];
        }

        if ($this->embeddingsEnabled() && $this->supportsVectorEmbeddings()) {
            try {
                $vec = $this->normalizeEmbedding($this->ollama->embed(
                    $query,
                    $this->settings->ollamaEmbeddingPhysicalModel(),
                ));
                if (count($vec) >= 64) {
                    $hits = $this->vectorSearch($vec, $topK, $projectId);
                    if ($hits !== []) {
                        return $hits;
                    }
                }
            } catch (\Throwable) {
                // fall through to keyword search
            }
        }

        return $this->keywordSearch($query, $topK, $projectId);
    }

    // ── File walk + chunking ────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $skipDirs
     * @return iterable<SplFileInfo>
     */
    protected function walk(string $root, array $skipDirs): iterable
    {
        $skip = array_map(static fn ($d): string => trim((string) $d, '/\\'), $skipDirs);
        $dirIterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator($dirIterator, function (SplFileInfo $current) use ($skip): bool {
            if ($current->isDir()) {
                return ! in_array($current->getFilename(), $skip, true)
                    && ! str_starts_with($current->getFilename(), '.');
            }

            return true;
        });

        /** @var iterable<SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator($filter);

        return $iterator;
    }

    protected function isIndexable(SplFileInfo $file): bool
    {
        if (! $file->isFile() || $file->getSize() > self::MAX_FILE_BYTES || $file->getSize() === 0) {
            return false;
        }

        return in_array($this->extension($file), self::CODE_EXTENSIONS, true);
    }

    protected function extension(SplFileInfo $file): string
    {
        $name = strtolower($file->getFilename());
        if (str_ends_with($name, '.blade.php')) {
            return 'blade.php';
        }

        return strtolower($file->getExtension());
    }

    /**
     * @return list<array{content: string, start_line: int, end_line: int}>
     */
    protected function chunk(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [$content];
        $total = count($lines);
        $chunks = [];
        $step = max(1, self::CHUNK_LINES - self::CHUNK_OVERLAP_LINES);

        for ($start = 0; $start < $total; $start += $step) {
            $slice = array_slice($lines, $start, self::CHUNK_LINES);
            $text = trim(implode("\n", $slice));
            if ($text === '') {
                if ($start + self::CHUNK_LINES >= $total) {
                    break;
                }

                continue;
            }
            $chunks[] = [
                'content' => $text,
                'start_line' => $start + 1,
                'end_line' => min($total, $start + self::CHUNK_LINES),
            ];
            if ($start + self::CHUNK_LINES >= $total) {
                break;
            }
        }

        return $chunks;
    }

    // ── Vector storage / search (mirrors MemoryService) ─────────────────────────────

    protected function databaseDriver(): string
    {
        return (string) DB::connection()->getDriverName();
    }

    protected function supportsVectorEmbeddings(): bool
    {
        return in_array($this->databaseDriver(), ['pgsql', 'sqlite'], true);
    }

    /**
     * @param  list<float>  $vec
     * @return list<float>
     */
    protected function normalizeEmbedding(array $vec): array
    {
        $vec = array_values(array_map(static fn ($v): float => (float) $v, $vec));
        $vec = array_slice($vec, 0, self::EMBED_DIM);
        while (count($vec) < self::EMBED_DIM) {
            $vec[] = 0.0;
        }

        return $vec;
    }

    /** @param list<float> $vec */
    protected function persistEmbedding(string $id, array $vec): void
    {
        $slice = array_slice($vec, 0, self::EMBED_DIM);

        if ($this->databaseDriver() === 'pgsql') {
            $literal = '['.implode(',', array_map(static fn (float $f) => sprintf('%.8f', $f), $slice)).']';
            DB::update('UPDATE bossku_ai_code_chunks SET embedding = ?::vector WHERE id = ?::uuid', [$literal, $id]);

            return;
        }

        CodeChunk::query()->whereKey($id)->update([
            'embedding_json' => json_encode($slice, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  list<float>  $vec
     * @return list<array{path: string, content: string, similarity: float, start_line: int|null, end_line: int|null}>
     */
    protected function vectorSearch(array $vec, int $topK, ?string $projectId): array
    {
        if ($this->databaseDriver() === 'pgsql') {
            $literal = '['.implode(',', array_map(static fn (float $f) => sprintf('%.8f', $f), array_slice($vec, 0, self::EMBED_DIM))).']';
            $projectClause = $projectId !== null ? 'project_id = ?' : 'project_id IS NULL';
            $bindings = $projectId !== null
                ? [$literal, $projectId, $literal, $literal]
                : [$literal, $literal, $literal];
            /** @var list<object{path:string, content:string, start_line:?int, end_line:?int, similarity:float}> $rows */
            $rows = DB::select(
                'SELECT path, content, start_line, end_line, (1 - (embedding <=> ?::vector))::float AS similarity
                 FROM bossku_ai_code_chunks
                 WHERE embedding IS NOT NULL AND '.$projectClause.'
                   AND (1 - (embedding <=> ?::vector)) > 0.2
                 ORDER BY (embedding <=> ?::vector) ASC
                 LIMIT '.(int) $topK,
                $bindings
            );

            return array_map(static fn ($r): array => [
                'path' => (string) $r->path,
                'content' => (string) $r->content,
                'similarity' => (float) $r->similarity,
                'start_line' => $r->start_line !== null ? (int) $r->start_line : null,
                'end_line' => $r->end_line !== null ? (int) $r->end_line : null,
            ], $rows);
        }

        // sqlite: in-PHP cosine over the JSON column.
        $candidates = CodeChunk::query()
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId), fn ($q) => $q->whereNull('project_id'))
            ->whereNotNull('embedding_json')
            ->get();

        $ranked = [];
        foreach ($candidates as $chunk) {
            $raw = $chunk->embedding_json;
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            try {
                $stored = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }
            if (! is_array($stored) || count($stored) < 64) {
                continue;
            }
            $similarity = $this->cosineSimilarity($vec, array_map(static fn ($v): float => (float) $v, array_values($stored)));
            if ($similarity <= 0.2) {
                continue;
            }
            $ranked[] = [
                'path' => (string) $chunk->path,
                'content' => (string) $chunk->content,
                'similarity' => $similarity,
                'start_line' => $chunk->start_line,
                'end_line' => $chunk->end_line,
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return array_slice($ranked, 0, $topK);
    }

    /**
     * @return list<array{path: string, content: string, similarity: float, start_line: int|null, end_line: int|null}>
     */
    protected function keywordSearch(string $query, int $topK, ?string $projectId): array
    {
        $terms = array_values(array_filter(
            preg_split('/[^a-zA-Z0-9_]+/', $query) ?: [],
            static fn (string $t): bool => mb_strlen($t) >= 3,
        ));
        if ($terms === []) {
            return [];
        }

        $rows = CodeChunk::query()
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId), fn ($q) => $q->whereNull('project_id'))
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('content', 'like', '%'.$term.'%')->orWhere('path', 'like', '%'.$term.'%');
                }
            })
            ->limit($topK * 3)
            ->get();

        $ranked = [];
        foreach ($rows as $chunk) {
            $haystack = strtolower($chunk->path."\n".$chunk->content);
            $score = 0;
            foreach ($terms as $term) {
                $score += substr_count($haystack, strtolower($term));
            }
            if ($score === 0) {
                continue;
            }
            $ranked[] = [
                'path' => (string) $chunk->path,
                'content' => (string) $chunk->content,
                'similarity' => (float) $score,
                'start_line' => $chunk->start_line,
                'end_line' => $chunk->end_line,
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return array_slice($ranked, 0, $topK);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
