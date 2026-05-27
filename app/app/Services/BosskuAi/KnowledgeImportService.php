<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Artifact;
use App\Models\BosskuAi\Checklist;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Playbook;
use App\Models\BosskuAi\Rule;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillLink;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\ToolCall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use App\Services\BosskuAi\MemoryService;

class KnowledgeImportService
{
    /** @var array<string, mixed> */
    protected array $skillIndex = [];

    /** @var array<string, string> skill folder name => playbook id uuid after import */
    protected array $playbookByBasename = [];

    /** @var array<string, string> */
    protected array $checklistByBasename = [];

    public function __construct(
        protected string $repoRoot,
        protected ?MemoryService $memoryService = null,
        protected ?YoutubeTranscriptService $youtubeTranscript = null,
        protected ?KnowledgeCaptureService $knowledgeCapture = null,
    ) {}

    protected function youtube(): YoutubeTranscriptService
    {
        return $this->youtubeTranscript ??= app(YoutubeTranscriptService::class);
    }

    protected function capture(): KnowledgeCaptureService
    {
        return $this->knowledgeCapture ??= app(KnowledgeCaptureService::class);
    }

    /**
     * @return array{skills: int, rules: int, playbooks: int, checklists: int, references: int, commands: int, skipped: int, errors: int, messages: list<string>}
     */
    public function import(bool $fresh = false): array
    {
        $stats = [
            'skills' => 0,
            'rules' => 0,
            'playbooks' => 0,
            'checklists' => 0,
            'references' => 0,
            'commands' => 0,
            'skipped' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        if ($fresh) {
            $this->truncateBosskuTables();
        }

        $this->loadSkillIndex();

        $this->importPluginJsons($stats);
        $this->importRootMarkdown($stats);
        $this->importSkillMdFiles($stats);
        $this->importPlaybooks($stats);
        $this->importChecklists($stats);
        $this->importReferenceMdTree($stats);
        $this->importDocs($stats);
        $this->importClaudeCommands($stats);

        $this->linkSkillsToPlaybooksAndChecklists($stats);

        if (Skill::query()->count() === 0) {
            $this->seedFallbackSkills($stats);
        }

        return $stats;
    }

    protected function truncateBosskuTables(): void
    {
        DB::transaction(function () {
            ToolCall::query()->delete();
            RunStep::query()->delete();
            Run::query()->delete();
            MemoryRunLink::query()->delete();
            Memory::query()->delete();
            SkillLink::query()->delete();
            Artifact::query()->delete();
            Skill::query()->delete();
            Rule::query()->delete();
            Playbook::query()->delete();
            Checklist::query()->delete();
        });
    }

    protected function loadSkillIndex(): void
    {
        $path = $this->join('skill-index.json');
        if (! is_readable($path)) {
            Log::info('Knowledge import skipped missing skill-index.json', ['path' => $path]);

            return;
        }
        try {
            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $this->skillIndex = is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            Log::warning('Knowledge import skill-index parse failed', ['error' => $e->getMessage()]);
        }
    }

    /** @param array<string,mixed> $stats */
    protected function importPluginJsons(array &$stats): void
    {
        $paths = [
            '.claude-plugin/plugin.json',
            '.cursor-plugin/plugin.json',
            'packages/bossku-ai/.codex-plugin/plugin.json',
        ];
        foreach ($paths as $rel) {
            $full = $this->join($rel);
            if (! is_readable($full)) {
                $stats['skipped']++;
                $stats['messages'][] = "Skipped plugin: {$rel}";
                continue;
            }
            try {
                $data = json_decode(File::get($full), true, 512, JSON_THROW_ON_ERROR);
                Artifact::query()->create([
                    'type' => 'config',
                    'name' => $data['name'] ?? basename($rel),
                    'description' => $data['description'] ?? null,
                    'content' => File::get($full),
                    'source_path' => $rel,
                    'metadata' => ['parsed' => $data],
                    'tags' => $data['keywords'] ?? [],
                    'token_estimate' => $this->estimateTokens(File::get($full)),
                    'is_active' => true,
                ]);
                $stats['references']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['messages'][] = "Parse error {$rel}: {$e->getMessage()}";
                Log::warning($e->getMessage());
            }
        }
    }

    /** @param array<string,mixed> $stats */
    protected function importRootMarkdown(array &$stats): void
    {
        foreach (['AGENTS.md', 'CLAUDE.md', 'bosskuai.md'] as $file) {
            $full = $this->join($file);
            if (! is_readable($full)) {
                $stats['skipped']++;

                continue;
            }
            $content = File::get($full);
            $sections = $this->splitMarkdownByH2($content);
            Artifact::query()->create([
                'type' => 'reference',
                'name' => $file,
                'description' => 'Full '.$file.' content',
                'content' => $content,
                'source_path' => $file,
                'tags' => ['root', 'contract'],
                'token_estimate' => $this->estimateTokens($content),
                'is_active' => true,
                'metadata' => ['sections' => count($sections)],
            ]);
            $stats['references']++;

            foreach ($sections as $title => $body) {
                if (strlen(trim(strip_tags($body))) < 40) {
                    continue;
                }
                Rule::query()->create([
                    'scope' => 'global',
                    'skill_name' => null,
                    'name' => Str::limit($title, 240),
                    'rule_text' => trim($title."\n\n".$body),
                    'source_path' => $file.'#'.$title,
                    'priority' => 90,
                    'metadata' => ['derived_from' => $file],
                    'is_active' => true,
                ]);
                $stats['rules']++;
            }
        }
    }

    /** @param array<string,mixed> $stats */
    protected function importSkillMdFiles(array &$stats): void
    {
        $base = $this->join('ai-assistant/skills');
        if (! is_dir($base)) {
            $stats['skipped']++;
            $stats['messages'][] = 'Skipped ai-assistant/skills (missing)';

            return;
        }

        $dirs = File::directories($base);
        foreach ($dirs as $dir) {
            $skillMd = $dir.'/SKILL.md';
            if (! is_readable($skillMd)) {
                $stats['skipped']++;

                continue;
            }
            try {
                $raw = File::get($skillMd);
                [$front, $markdown] = $this->splitFrontmatter($raw);
                $meta = [];
                if ($front !== '') {
                    try {
                        $meta = Yaml::parse($front) ?: [];
                    } catch (\Throwable) {
                        $meta = [];
                    }
                }
                $folderName = basename($dir);
                $name = is_array($meta) && isset($meta['name']) ? (string) $meta['name'] : $folderName;
                $description = '';
                if (is_array($meta) && isset($meta['description'])) {
                    $description = (string) $meta['description'];
                }
                if ($description === '') {
                    $description = $this->firstParagraph($markdown);
                }

                $checklistLines = $this->extractChecklistItems($markdown);
                $routing = $this->skillRoutingMeta($name);

                $skill = Skill::query()->updateOrCreate(
                    ['name' => $name],
                    [
                        'description' => $description,
                        'rules' => $routing['hints'] ?? [],
                        'tools' => is_array($meta['tools'] ?? null) ? $meta['tools'] : [],
                        'playbooks' => [],
                        'checklists' => $checklistLines,
                        'source_path' => $this->relPath($skillMd),
                        'content' => $raw,
                        'metadata' => array_merge([
                            'frontmatter' => $meta,
                            'folder' => $folderName,
                        ], $routing),
                        'is_active' => true,
                    ]
                );
                $stats['skills']++;
                unset($skill);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['messages'][] = "Skill parse error {$skillMd}: ".$e->getMessage();
            }
        }
    }

    protected function skillRoutingMeta(string $skillId): array
    {
        $skills = data_get($this->skillIndex, 'skills');
        if (! is_array($skills)) {
            return [];
        }
        foreach ($skills as $s) {
            if (! is_array($s)) {
                continue;
            }
            if (($s['id'] ?? null) === $skillId) {
                return [
                    'hints' => [
                        'tags' => $s['tags'] ?? [],
                        'when' => $s['when_to_use'] ?? $s['description'] ?? '',
                    ],
                    'skill_index_match' => true,
                ];
            }
        }

        return [];
    }

    /** @param array<string,mixed> $stats */
    protected function importPlaybooks(array &$stats): void
    {
        $glob = glob($this->join('ai-assistant/references/playbooks').'/*.md') ?: [];
        foreach ($glob as $full) {
            if (! is_readable($full)) {
                continue;
            }
            try {
                $raw = File::get($full);
                $name = $this->extractH1($raw) ?: pathinfo($full, PATHINFO_FILENAME);
                $pb = Playbook::query()->updateOrCreate(
                    ['source_path' => $this->relPath($full)],
                    [
                        'name' => $name,
                        'description' => $this->firstParagraph($raw),
                        'content' => $raw,
                        'tags' => $this->tagsFromFilename($full),
                        'metadata' => ['items' => $this->extractChecklistItems($raw)],
                        'is_active' => true,
                    ]
                );
                $this->playbookByBasename[pathinfo($full, PATHINFO_FILENAME)] = $pb->getKey();
                $stats['playbooks']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['messages'][] = "Playbook error {$full}: ".$e->getMessage();
            }
        }
    }

    /** @param array<string,mixed> $stats */
    protected function importChecklists(array &$stats): void
    {
        $glob = glob($this->join('ai-assistant/references/checklists').'/*.md') ?: [];
        foreach ($glob as $full) {
            if (! is_readable($full)) {
                continue;
            }
            try {
                $raw = File::get($full);
                $name = $this->extractH1($raw) ?: pathinfo($full, PATHINFO_FILENAME);
                $cl = Checklist::query()->updateOrCreate(
                    ['source_path' => $this->relPath($full)],
                    [
                        'name' => $name,
                        'content' => $raw,
                        'tags' => $this->tagsFromFilename($full),
                        'metadata' => [
                            'items' => $this->extractChecklistItems($raw),
                        ],
                        'is_active' => true,
                    ]
                );
                $this->checklistByBasename[pathinfo($full, PATHINFO_FILENAME)] = $cl->getKey();
                $stats['checklists']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['messages'][] = "Checklist error {$full}: ".$e->getMessage();
            }
        }
    }

    /** @param array<string,mixed> $stats */
    protected function importReferenceMdTree(array &$stats): void
    {
        $roots = ['ai-assistant/references'];
        foreach ($roots as $rel) {
            $basePath = $this->join($rel);
            if (! is_dir($basePath)) {
                continue;
            }
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $fileInfo) {
                /** @var \SplFileInfo $fileInfo */
                if ($fileInfo->getExtension() !== 'md') {
                    continue;
                }
                $path = $fileInfo->getPathname();
                if (Str::contains($path, DIRECTORY_SEPARATOR.'playbooks'.DIRECTORY_SEPARATOR)
                    || Str::contains($path, DIRECTORY_SEPARATOR.'checklists'.DIRECTORY_SEPARATOR)) {
                    continue;
                }
                try {
                    $raw = File::get($path);
                    Artifact::query()->create([
                        'type' => 'reference',
                        'name' => $this->extractH1($raw) ?: $fileInfo->getBasename('.md'),
                        'description' => $this->firstParagraph($raw),
                        'content' => $raw,
                        'source_path' => $this->relPath($path),
                        'tags' => ['reference'],
                        'token_estimate' => $this->estimateTokens($raw),
                        'is_active' => true,
                    ]);
                    $stats['references']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                }
            }
        }
    }

    /** @param array<string,mixed> $stats */
    protected function importDocs(array &$stats): void
    {
        $base = $this->join('docs');
        if (! is_dir($base)) {
            $stats['skipped']++;

            return;
        }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $fileInfo) {
            if ($fileInfo->getExtension() !== 'md') {
                continue;
            }
            $path = $fileInfo->getPathname();
            try {
                $raw = File::get($path);
                Artifact::query()->create([
                    'type' => 'reference',
                    'name' => $this->extractH1($raw) ?: $fileInfo->getBasename('.md'),
                    'description' => $this->firstParagraph($raw),
                    'content' => $raw,
                    'source_path' => $this->relPath($path),
                    'tags' => ['docs'],
                    'token_estimate' => $this->estimateTokens($raw),
                    'is_active' => true,
                ]);
                $stats['references']++;
            } catch (\Throwable) {
                $stats['errors']++;
            }
        }
    }

    /** @param array<string,mixed> $stats */
    protected function importClaudeCommands(array &$stats): void
    {
        $base = $this->join('.claude/commands');
        if (! is_dir($base)) {
            $stats['skipped']++;

            return;
        }
        foreach (glob($base.'/*.md') ?: [] as $full) {
            try {
                $raw = File::get($full);
                Artifact::query()->create([
                    'type' => 'command',
                    'name' => $this->extractH1($raw) ?: pathinfo($full, PATHINFO_FILENAME),
                    'description' => null,
                    'content' => $raw,
                    'source_path' => $this->relPath($full),
                    'tags' => ['command'],
                    'token_estimate' => $this->estimateTokens($raw),
                    'is_active' => true,
                ]);
                $stats['commands']++;
            } catch (\Throwable) {
                $stats['errors']++;
            }
        }
    }

    /** @param array<string,mixed> $stats */
    protected function linkSkillsToPlaybooksAndChecklists(array &$stats): void
    {
        SkillLink::query()->delete();
        $skills = Skill::query()->get();
        foreach ($skills as $skill) {
            $base = preg_replace('/^bosskuai-/', '', str_replace('_', '-', $skill->name));
            $playbookCandidates = [
                $skill->name.'-playbook',
                $base.'-playbook',
                'bosskuai-'.$base.'-playbook',
            ];
            foreach (array_unique($playbookCandidates) as $basename) {
                if (isset($this->playbookByBasename[$basename])) {
                    SkillLink::query()->create([
                        'skill_id' => $skill->id,
                        'link_type' => 'playbook',
                        'linked_id' => $this->playbookByBasename[$basename],
                        'metadata' => ['basename' => $basename],
                    ]);
                    break;
                }
            }
            $chkCandidates = [
                $skill->name.'-checklist',
                $base.'-checklist',
                'bosskuai-'.$base.'-checklist',
            ];
            foreach (array_unique($chkCandidates) as $basename) {
                if (isset($this->checklistByBasename[$basename])) {
                    SkillLink::query()->create([
                        'skill_id' => $skill->id,
                        'link_type' => 'checklist',
                        'linked_id' => $this->checklistByBasename[$basename],
                        'metadata' => ['basename' => $basename],
                    ]);
                    break;
                }
            }
        }
        unset($stats);
    }

    /** @param array<string,mixed> $stats */
    protected function seedFallbackSkills(array &$stats): void
    {
        $names = [
            'cofounder', 'laravel-development', 'nuxt-development', 'database-engineering',
            'redis-caching-queues', 'vps-docker-deployment', 'ui-ux-design-to-code',
            'cybersecurity-risk', 'prompt-injection-defense', 'seo-geo', 'content-calendar',
            'sales-strategy', 'product-strategy', 'saas-billing-ops', 'observability-sre',
            'cost-optimization', 'eval-driven-agent-improvement',
        ];
        foreach ($names as $name) {
            Skill::query()->firstOrCreate(
                ['name' => $name],
                [
                    'description' => 'Fallback seed skill.',
                    'content' => "## {$name}\n\nSeed skill (no SKILL.md imported).",
                    'is_active' => true,
                    'metadata' => ['fallback' => true],
                ]
            );
            $stats['skills']++;
        }
        $stats['messages'][] = 'Seeded fallback skills (skills table was empty)';
    }

    protected function join(string $rel): string
    {
        return rtrim($this->repoRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }

    protected function relPath(string $absolute): string
    {
        $root = realpath($this->repoRoot);
        $absReal = realpath($absolute);

        return $root && $absReal
            ? ltrim(Str::replace($root, '', $absReal), DIRECTORY_SEPARATOR)
            : Str::replace($this->repoRoot.'/', '', $absolute);
    }

    protected function estimateTokens(string $text): int
    {
        return (int) max(1, round(strlen($text) / 4));
    }

    /** @return array{0:string,1:string} */
    protected function splitFrontmatter(string $raw): array
    {
        if (! str_starts_with(trim($raw), '---')) {
            return ['', $raw];
        }
        $end = strpos($raw, "\n---", 3);
        if ($end === false) {
            return ['', $raw];
        }
        $front = substr($raw, 3, $end - 3);

        return [trim($front), trim(substr($raw, $end + 4))];
    }

    /** @return array<string,string> heading => body */
    protected function splitMarkdownByH2(string $markdown): array
    {
        $chunks = preg_split('/\n(?=##\s)/', $markdown) ?: [];
        $out = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (preg_match('/^##\s+(.+?)\s*$/m', $chunk, $m)) {
                $title = trim($m[1]);
                $body = trim(preg_replace('/^##\s+.+?\n+/m', '', $chunk, 1));
                if ($body !== '') {
                    $out[$title] = $body;
                }
            }
        }

        return $out === [] ? ['document' => trim($markdown)] : $out;
    }

    protected function firstParagraph(string $md): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $md) ?: [];
        $buf = '';
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            if (trim($line) === '') {
                if ($buf !== '') {
                    break;
                }

                continue;
            }
            $buf .= ($buf === '' ? '' : ' ').trim($line);
            if (strlen($buf) > 400) {
                break;
            }
        }

        return Str::limit($buf, 500);
    }

    protected function extractH1(string $md): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $md, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** @return list<string> */
    protected function extractChecklistItems(string $md): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $md) ?: [];
        $items = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*[-*]\s+\[[ xX]\]\s*(.+)$/', $line, $m)) {
                $items[] = trim($m[1]);
            }
        }

        return $items;
    }

    /** @return list<string> */
    protected function tagsFromFilename(string $path): array
    {
        $slug = strtolower(pathinfo($path, PATHINFO_FILENAME));
        $pieces = array_map('trim', explode('-', $slug));

        return array_values(array_unique(array_filter($pieces)));
    }

    // ── URL Learning ─────────────────────────────────────────────────────────

    /**
     * Fetch a YouTube video or web article, chunk it, and store in Memory.
     *
     * @return array{url: string, title: string, chunks: int, indexed: int, type: string}
     */
    public function learnUrl(string $url): array
    {
        $isYoutube = str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be');

        if ($isYoutube) {
            $videoId = $this->youtube()->extractVideoId($url);
            if ($videoId === null) {
                throw new \RuntimeException('Could not extract YouTube video ID from: '.$url);
            }
            [$title, $text] = $this->fetchYouTubeTranscript($url, $videoId);
            $sourceType = 'youtube';
        } else {
            if (! $this->capture()->isAllowedUrl($url)) {
                throw new \RuntimeException('URL is not allowed.');
            }
            [$title, $text] = $this->fetchWebPage($url);
            $sourceType = 'web';
        }

        if (trim($text) === '') {
            throw new \RuntimeException('No readable content could be extracted from this URL.');
        }

        // Deduplication: remove existing chunks for this URL before re-indexing
        $existing = Memory::query()
            ->where('source', $url)
            ->where('type', 'knowledge_chunk')
            ->count();
        if ($existing > 0) {
            Memory::query()
                ->where('source', $url)
                ->where('type', 'knowledge_chunk')
                ->delete();
        }

        $chunks = $this->chunkText($text);
        $indexed = 0;
        $total = count($chunks);

        foreach ($chunks as $i => $chunk) {
            try {
                $humanSummary = Str::limit($title.' [chunk '.($i + 1).'/'.$total.']', 200);
                $metadata = [
                    'source_url' => $url,
                    'title' => $title,
                    'chunk_index' => $i,
                    'total_chunks' => $total,
                    'source_type' => $sourceType,
                ];

                if ($this->memoryService !== null) {
                    // Use MemoryService so embeddings are generated for vector search
                    $this->memoryService->store(
                        $chunk,
                        'knowledge_chunk',
                        $metadata,
                        $humanSummary,
                        ['url_learning', $sourceType],
                        $url,
                        0.70,
                        0.75,
                    );
                } else {
                    Memory::query()->create([
                        'type' => 'knowledge_chunk',
                        'content' => $chunk,
                        'human_summary' => $humanSummary,
                        'metadata' => array_merge($metadata, ['importance' => 0.70]),
                        'tags' => ['url_learning', $sourceType],
                        'source' => $url,
                        'is_active' => true,
                        'confidence' => 0.75,
                    ]);
                }
                $indexed++;
            } catch (\Throwable $e) {
                Log::warning('learnUrl chunk store failed', ['url' => $url, 'chunk' => $i, 'error' => $e->getMessage()]);
            }
        }

        if ($total > 0 && $indexed === 0) {
            throw new \RuntimeException('Failed to store any content chunks in memory.');
        }

        return [
            'url' => $url,
            'title' => $title,
            'chunks' => $total,
            'indexed' => $indexed,
            'type' => $sourceType,
            'deduplicated' => $existing > 0,
        ];
    }

    /** @return array{0: string, 1: string} [title, transcript_text] */
    protected function fetchYouTubeTranscript(string $url, string $videoId): array
    {
        $title = $this->youtube()->fetchTitle($url, $videoId);
        $transcript = $this->youtube()->fetchTranscript($videoId);

        if (strlen($transcript) < 100) {
            throw new \RuntimeException(
                'No captions available for this video (try a video with subtitles enabled).',
            );
        }

        return [$title, $transcript];
    }

    /** @return array{0: string, 1: string} [title, plain_text] */
    protected function fetchWebPage(string $url): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(30)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch URL: HTTP '.$response->status());
        }

        $html = $response->body();

        // Extract title
        $title = $url;
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) {
            $title = html_entity_decode($m[1]);
        } elseif (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            $title = html_entity_decode($m[1]);
        }

        $text = $this->htmlToText($html);

        if (strlen($text) < 100) {
            throw new \RuntimeException('Extracted text is too short (< 100 chars) — page may require JavaScript.');
        }

        return [trim($title), $text];
    }

    protected function htmlToText(string $html): string
    {
        // Remove scripts, styles, nav, header, footer, etc.
        $html = preg_replace('/<(script|style|nav|header|footer|aside|figure|form|button|svg|noscript)[^>]*>.*?<\/\1>/si', ' ', $html) ?? $html;
        // Convert block elements to newlines
        $html = preg_replace('/<\/(p|div|li|h[1-6]|br|tr|blockquote)>/i', "\n", $html) ?? $html;
        // Strip remaining tags
        $text = strip_tags($html);
        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse whitespace
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Chunk text into overlapping segments with sentence-boundary awareness.
     *
     * @return list<string>
     */
    protected function chunkText(string $text, int $chunkSize = 1400, int $overlap = 250, int $maxChunks = 60): array
    {
        $chunks = [];
        $start = 0;
        $len = strlen($text);

        while ($start < $len && count($chunks) < $maxChunks) {
            $end = min($start + $chunkSize, $len);

            // Try to break at sentence boundary (last '. ' in final quarter of chunk)
            if ($end < $len) {
                $quarter = (int) ($chunkSize * 0.75);
                $searchFrom = $start + $quarter;
                $lastDot = strrpos(substr($text, $searchFrom, $end - $searchFrom), '. ');
                if ($lastDot !== false) {
                    $end = $searchFrom + $lastDot + 2;
                }
            }

            $chunk = trim(substr($text, $start, $end - $start));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $start = $end - $overlap;
            if ($start >= $len) {
                break;
            }
        }

        return $chunks;
    }
}
