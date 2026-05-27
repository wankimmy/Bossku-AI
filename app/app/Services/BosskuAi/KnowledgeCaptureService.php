<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Memory;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KnowledgeCaptureService
{
    private const MAX_CONTENT_CHARS = 20000;
    private const MAX_BODY_CHARS = 500000;
    private const MAX_MEMORY_FILE_BYTES = 1000000;

    public function __construct(
        protected MemoryService $memoryService,
        protected YoutubeTranscriptService $youtubeTranscript,
    ) {}

    /**
     * @param  list<string>  $urls
     * @param  list<string>  $tags
     * @return array{created:int, skipped:int, failed:int, items:list<array<string,mixed>>}
     */
    public function importUrls(array $urls, array $tags = [], ?string $note = null): array
    {
        $result = $this->emptyResult();
        $tags = $this->normalizeTags($tags);

        foreach ($this->uniqueStrings($urls) as $url) {
            $item = ['source' => $url, 'status' => 'failed'];

            try {
                if (! $this->isAllowedUrl($url)) {
                    $item['message'] = 'URL is not allowed.';
                    $result['failed']++;
                    $result['items'][] = $item;
                    continue;
                }

                $capture = $this->isYoutubeUrl($url)
                    ? $this->captureYoutube($url, $note)
                    : $this->captureArticle($url, $note);

                $stored = $this->storeKnowledge('url', $capture, $tags);
                $item = array_merge($item, [
                    'status' => $stored ? $capture['status'] : 'skipped',
                    'title' => $capture['title'],
                    'memory_id' => $stored?->getKey(),
                ]);

                if ($stored) {
                    $result['created']++;
                } else {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {
                $item['message'] = $e->getMessage();
                $result['failed']++;
            }

            $result['items'][] = $item;
        }

        return $result;
    }

    /**
     * @return array{created:int, skipped:int, failed:int, items:list<array<string,mixed>>}
     */
    public function importLocalMemory(string $source): array
    {
        $source = strtolower($source);
        if (! in_array($source, ['codex', 'claude'], true)) {
            return [
                'created' => 0,
                'skipped' => 0,
                'failed' => 1,
                'items' => [['source' => $source, 'status' => 'failed', 'message' => 'Unsupported memory source.']],
            ];
        }

        $result = $this->emptyResult();
        $paths = $this->memoryImportPaths($source);

        foreach ($paths as $path) {
            if (! is_readable($path)) {
                $result['skipped']++;
                $result['items'][] = ['source' => $path, 'status' => 'skipped', 'message' => 'Path is not readable.'];
                continue;
            }

            foreach ($this->iterMemoryFiles($path) as $file) {
                try {
                    if ($this->shouldSkipMemoryFile($file)) {
                        $result['skipped']++;
                        $result['items'][] = ['source' => $file, 'status' => 'skipped', 'message' => 'Skipped unsafe or unsupported file.'];
                        continue;
                    }

                    $text = $this->extractMemoryText($file);
                    if ($text === '') {
                        $result['skipped']++;
                        $result['items'][] = ['source' => $file, 'status' => 'skipped', 'message' => 'No useful text found.'];
                        continue;
                    }

                    $capture = [
                        'url' => null,
                        'canonical_url' => null,
                        'title' => basename($file),
                        'content' => $this->truncate($text),
                        'extractor' => 'local_memory',
                        'status' => 'imported',
                        'note' => null,
                    ];

                    $stored = $this->storeKnowledge($source, $capture, ['knowledge', $source]);
                    if ($stored) {
                        $result['created']++;
                        $result['items'][] = ['source' => $file, 'status' => 'imported', 'memory_id' => $stored->getKey()];
                    } else {
                        $result['skipped']++;
                        $result['items'][] = ['source' => $file, 'status' => 'skipped', 'message' => 'Duplicate content.'];
                    }
                } catch (\Throwable $e) {
                    $result['failed']++;
                    $result['items'][] = ['source' => $file, 'status' => 'failed', 'message' => $e->getMessage()];
                }
            }
        }

        return $result;
    }

    /**
     * @return array{data:\Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function recent(int $limit = 30): array
    {
        return [
            'data' => Memory::query()
                ->where('type', 'knowledge')
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->paginate($limit),
        ];
    }

    /** @return array{created:int, skipped:int, failed:int, items:list<array<string,mixed>>} */
    protected function emptyResult(): array
    {
        return ['created' => 0, 'skipped' => 0, 'failed' => 0, 'items' => []];
    }

    /** @return list<string> */
    protected function uniqueStrings(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $out[$value] = $value;
            }
        }

        return array_values($out);
    }

    /** @return list<string> */
    protected function normalizeTags(array $tags): array
    {
        $base = ['knowledge'];
        foreach ($tags as $tag) {
            $tag = Str::of((string) $tag)->trim()->lower()->replaceMatches('/[^a-z0-9_\-]+/', '-')->trim('-')->toString();
            if ($tag !== '') {
                $base[] = $tag;
            }
        }

        return array_values(array_unique($base));
    }

    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
                if ($ip !== '' && ! $this->isPublicIp($ip)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    protected function isYoutubeUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'youtube.com' || str_ends_with($host, '.youtube.com') || $host === 'youtu.be';
    }

    /** @return array<string,mixed> */
    protected function captureYoutube(string $url, ?string $note): array
    {
        $videoId = $this->youtubeTranscript->extractVideoId($url);
        $title = $videoId ? $this->youtubeTranscript->fetchTitle($url, $videoId) : 'YouTube video';
        $author = null;

        $transcript = $videoId ? $this->youtubeTranscript->fetchTranscript($videoId) : '';
        $status = $transcript !== '' ? 'imported' : 'partial';
        $extractor = $transcript !== '' ? 'youtube_transcript' : 'youtube_metadata';
        $content = $transcript !== ''
            ? "Title: {$title}\nURL: {$url}\n\nTranscript:\n{$transcript}"
            : "Title: {$title}\nURL: {$url}\n".($author ? "Author: {$author}\n" : '')."Transcript unavailable.";

        return [
            'url' => $url,
            'canonical_url' => $videoId ? 'https://www.youtube.com/watch?v='.$videoId : $url,
            'title' => $title,
            'content' => $this->truncate($content),
            'extractor' => $extractor,
            'status' => $status,
            'note' => $note,
        ];
    }

    /** @return array<string,mixed> */
    protected function captureArticle(string $url, ?string $note): array
    {
        try {
            $body = $this->fetchPageBody($url);

            if ($body === null) {
                return $this->partialUrlCapture($url, $note, 'fetch_failed');
            }

            $parsed = $this->parseHtml($body);
            $text = trim((string) $parsed['text']);
            if ($text === '') {
                return $this->partialUrlCapture($url, $note, 'empty_extract', (string) ($parsed['title'] ?? ''));
            }

            $title = (string) ($parsed['title'] ?: parse_url($url, PHP_URL_HOST) ?: $url);
            $description = (string) ($parsed['description'] ?? '');
            $content = trim("Title: {$title}\nURL: {$url}\n".($description ? "Summary: {$description}\n" : '')."\n{$text}");

            return [
                'url' => $url,
                'canonical_url' => $parsed['canonical_url'] ?: $url,
                'title' => $title,
                'content' => $this->truncate($content),
                'extractor' => 'article',
                'status' => 'imported',
                'note' => $note,
            ];
        } catch (\Throwable) {
            return $this->partialUrlCapture($url, $note, 'fetch_error');
        }
    }

    protected function fetchPageBody(string $url): ?string
    {
        $browserHeaders = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];

        try {
            $response = Http::timeout(15)
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->withHeaders($browserHeaders)
                ->get($url);

            if ($response->successful()) {
                return substr($response->body(), 0, self::MAX_BODY_CHARS);
            }

            // For bot-blocked responses, try Google's cached copy.
            if (in_array($response->status(), [403, 429, 451, 503], true)) {
                $cacheUrl = 'https://webcache.googleusercontent.com/search?q=cache:'.rawurlencode($url).'&hl=en';
                $cached = Http::timeout(15)->withHeaders($browserHeaders)->get($cacheUrl);
                if ($cached->successful()) {
                    return substr($cached->body(), 0, self::MAX_BODY_CHARS);
                }
            }
        } catch (\Throwable) {
            // fall through to null
        }

        return null;
    }

    /** @return array<string,mixed> */
    protected function partialUrlCapture(string $url, ?string $note, string $reason, string $title = ''): array
    {
        $title = $title !== '' ? $title : (string) (parse_url($url, PHP_URL_HOST) ?: $url);

        return [
            'url' => $url,
            'canonical_url' => $url,
            'title' => $title,
            'content' => "URL: {$url}\nStatus: partial\nReason: {$reason}",
            'extractor' => 'url_partial',
            'status' => 'partial',
            'note' => $note,
        ];
    }

    /** @return array{title:string, description:string, canonical_url:string, text:string} */
    protected function parseHtml(string $html): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($doc);

        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));
        $description = trim((string) ($xpath->query('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]/@content')->item(0)?->textContent ?? ''));
        $canonical = trim((string) ($xpath->query('//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="canonical"]/@href')->item(0)?->textContent ?? ''));

        $paragraphs = [];
        foreach ($xpath->query('//article//p | //main//p | //body//p') ?: [] as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
            if ($text !== '' && ! in_array($text, $paragraphs, true)) {
                $paragraphs[] = $text;
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonical,
            'text' => trim(implode("\n\n", $paragraphs)),
        ];
    }

    /**
     * @param  array{url:?string, canonical_url:?string, title:string, content:string, extractor:string, status:string, note:?string}  $capture
     * @param  list<string>  $tags
     */
    protected function storeKnowledge(string $source, array $capture, array $tags): ?Memory
    {
        $hash = hash('sha256', $source."\n".$this->normalizeContentForHash($capture['content']));
        if ($this->hasDuplicateHash($hash)) {
            return null;
        }

        return $this->memoryService->store(
            content: $capture['content'],
            type: 'knowledge',
            metadata: [
                'url' => $capture['url'],
                'canonical_url' => $capture['canonical_url'],
                'extractor' => $capture['extractor'],
                'status' => $capture['status'],
                'imported_at' => now()->toIso8601String(),
                'title' => $capture['title'],
                'note' => $capture['note'],
                'content_hash' => $hash,
            ],
            humanSummary: $capture['title'],
            tags: array_values(array_unique(array_merge(['knowledge', $source], $tags))),
            source: $source
        );
    }

    protected function hasDuplicateHash(string $hash): bool
    {
        return Memory::query()
            ->where('type', 'knowledge')
            ->get(['metadata'])
            ->contains(fn (Memory $memory): bool => ($memory->metadata['content_hash'] ?? null) === $hash);
    }

    protected function normalizeContentForHash(string $content): string
    {
        return Str::of($content)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    /** @return list<string> */
    protected function memoryImportPaths(string $source): array
    {
        $configured = config("bossku.knowledge_import_paths.{$source}");
        $paths = is_array($configured) ? $configured : [];
        $hasConfigured = count(array_filter($paths)) > 0;
        $home = rtrim((string) (getenv('HOME') ?: ''), DIRECTORY_SEPARATOR);
        $repoRoot = (string) config('bossku.repo_root');

        if (! $hasConfigured && $source === 'codex') {
            $paths[] = $home.'/.codex/memories';
        }
        if (! $hasConfigured && $source === 'claude') {
            $paths[] = $home.'/.claude/projects';
        }
        $paths[] = $repoRoot.'/ai-assistant/memory';

        return array_values(array_unique(array_filter(array_map('strval', $paths))));
    }

    /** @return list<string> */
    protected function iterMemoryFiles(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        $out = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isFile()) {
                $out[] = $fileInfo->getPathname();
            }
        }

        sort($out);

        return $out;
    }

    protected function shouldSkipMemoryFile(string $file): bool
    {
        $name = strtolower(basename($file));
        if (str_starts_with($name, '.env') || str_contains($name, 'secret') || str_contains($name, 'token')) {
            return true;
        }
        if (filesize($file) !== false && filesize($file) > self::MAX_MEMORY_FILE_BYTES) {
            return true;
        }

        return ! in_array(pathinfo($file, PATHINFO_EXTENSION), ['md', 'jsonl', 'txt', 'json'], true);
    }

    protected function extractMemoryText(string $file): string
    {
        $raw = File::get($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if ($ext === 'jsonl') {
            $lines = [];
            foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $text = $this->collectText($decoded);
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
            }
            $raw = implode("\n\n", $lines);
        } elseif ($ext === 'json') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $this->collectText($decoded) : $raw;
        }

        return $this->truncate($this->redact($raw));
    }

    protected function collectText(mixed $value): string
    {
        $keys = ['summary', 'content', 'text', 'message', 'prompt', 'user_prompt', 'transcript'];
        $parts = [];
        $walk = function (mixed $node) use (&$walk, &$parts, $keys): void {
            if (is_array($node)) {
                foreach ($node as $key => $child) {
                    if (is_string($key) && in_array($key, $keys, true) && is_string($child)) {
                        $parts[] = $child;
                    } else {
                        $walk($child);
                    }
                }
            }
        };
        $walk($value);

        return trim(implode("\n\n", array_unique(array_filter($parts))));
    }

    protected function redact(string $text): string
    {
        $patterns = [
            '/(?i)(api[_-]?key|secret|token|password|passwd|pwd|client[_-]?secret|access[_-]?token|refresh[_-]?token)\s*[:=]\s*[^\s\'"]+/',
            '/(?i)bearer\s+[A-Za-z0-9._~+\/=-]{12,}/',
            '/(?i)(sk-[A-Za-z0-9]{20,}|ghp_[A-Za-z0-9_]{20,}|xox[baprs]-[A-Za-z0-9-]{20,})/',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '[REDACTED]', $text) ?? $text;
        }

        return $text;
    }

    protected function truncate(string $text): string
    {
        $text = trim(preg_replace('/\n{4,}/', "\n\n\n", $text) ?? $text);

        return Str::limit($text, self::MAX_CONTENT_CHARS, "\n\n[truncated by BosskuAI knowledge import]");
    }
}
