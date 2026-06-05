<?php

namespace App\Services\Runs;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Project\ProjectPathResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LongPromptTempFileService
{
    public const INLINE_LIMIT = 50000;
    public const MAX_ACCEPTED_CHARS = 1048576;
    public const CHUNK_CHARS = 7500;

    public function __construct(
        protected ProjectPathResolver $paths,
    ) {}

    /**
     * @return array{
     *   prompt: string,
     *   routing_prompt: string,
     *   materialized: bool,
     *   metadata: array<string, mixed>|null
     * }
     */
    public function inline(string $prompt): array
    {
        return [
            'prompt' => $prompt,
            'routing_prompt' => $prompt,
            'materialized' => false,
            'metadata' => null,
        ];
    }

    /**
     * @return array{
     *   prompt: string,
     *   routing_prompt: string,
     *   materialized: bool,
     *   metadata: array<string, mixed>|null
     * }
     */
    public function prepare(string $prompt): array
    {
        if (strlen($prompt) <= self::INLINE_LIMIT) {
            return $this->inline($prompt);
        }

        $active = $this->paths->activeProject();
        if ($active === null) {
            throw new \RuntimeException('BosskuAI cannot create a long-prompt temp file because no active mounted project is available.');
        }

        try {
            $root = $this->paths->repoRoot();
        } catch (\Throwable $e) {
            throw new \RuntimeException('BosskuAI cannot create a long-prompt temp file because no active mounted project is available.');
        }

        $baseDir = $root.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'bossku-prompts';
        File::ensureDirectoryExists($baseDir, 0775, true);
        if (! is_dir($baseDir) || ! is_writable($baseDir)) {
            throw new \RuntimeException('BosskuAI cannot create a long-prompt temp file because active project tmp/bossku-prompts is not writable.');
        }

        $stamp = now('UTC')->format('Ymd-His');
        $relativeDir = 'tmp/bossku-prompts/'.$stamp.'-'.Str::random(10);
        $absoluteDir = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        $chunksDir = $absoluteDir.DIRECTORY_SEPARATOR.'chunks';

        File::ensureDirectoryExists($chunksDir, 0775, true);

        $promptPath = $relativeDir.'/prompt.md';
        $absolutePromptPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $promptPath);
        File::put($absolutePromptPath, $prompt);

        $chunkPaths = [];
        $chunkCount = (int) ceil(strlen($prompt) / self::CHUNK_CHARS);
        for ($i = 0; $i < $chunkCount; $i++) {
            $chunk = substr($prompt, $i * self::CHUNK_CHARS, self::CHUNK_CHARS);
            $chunkPath = $relativeDir.'/chunks/chunk-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT).'.md';
            File::put($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $chunkPath), $chunk);
            $chunkPaths[] = $chunkPath;
        }

        $manifestPath = $relativeDir.'/manifest.json';
        $metadata = [
            'project_id' => $active->id,
            'project_name' => $active->name,
            'relative_dir' => $relativeDir,
            'prompt_path' => $promptPath,
            'manifest_path' => $manifestPath,
            'chunk_paths' => $chunkPaths,
            'chunk_count' => count($chunkPaths),
            'chunk_chars' => self::CHUNK_CHARS,
            'original_length' => strlen($prompt),
            'sha256' => hash('sha256', $prompt),
            'created_at' => now('UTC')->toIso8601String(),
            'cleanup_status' => 'pending',
        ];

        File::put(
            $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $manifestPath),
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return [
            'prompt' => $this->compactPrompt($prompt, $metadata),
            'routing_prompt' => $this->routingPrompt($prompt),
            'materialized' => true,
            'metadata' => $metadata,
        ];
    }

    /** @param array<string, mixed>|null $metadata */
    public function cleanup(?array $metadata): array
    {
        if ($metadata === null || $metadata === []) {
            return ['cleanup_status' => 'skipped'];
        }

        $relativeDir = trim((string) ($metadata['relative_dir'] ?? ''), '/');
        $projectId = (string) ($metadata['project_id'] ?? '');
        if ($relativeDir === '' || ! str_starts_with($relativeDir, 'tmp/bossku-prompts/') || $projectId === '') {
            return array_merge($metadata, ['cleanup_status' => 'skipped']);
        }

        $project = Project::query()->find($projectId);
        if ($project === null) {
            return array_merge($metadata, ['cleanup_status' => 'failed_project_missing']);
        }

        $root = realpath((string) $project->container_path);
        if ($root === false || ! is_dir($root)) {
            return array_merge($metadata, ['cleanup_status' => 'failed_project_unmounted']);
        }

        $absoluteDir = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        $parent = realpath(dirname($absoluteDir));
        if ($parent === false || ! str_starts_with($parent, $root.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'bossku-prompts')) {
            return array_merge($metadata, ['cleanup_status' => 'failed_path_denied']);
        }

        if (is_dir($absoluteDir)) {
            File::deleteDirectory($absoluteDir);
        }

        return array_merge($metadata, [
            'cleanup_status' => is_dir($absoluteDir) ? 'failed_delete' : 'deleted',
            'cleaned_at' => now('UTC')->toIso8601String(),
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    public function storeRunMetadata(?string $runId, ?array $metadata): ?array
    {
        if ($runId === null || $runId === '' || $metadata === null) {
            return $metadata;
        }

        $run = Run::query()->find($runId);
        if ($run === null) {
            return $metadata;
        }

        $runMeta = is_array($run->metadata) ? $run->metadata : [];
        $runMeta['long_prompt'] = $metadata;
        $run->update(['metadata' => $runMeta]);

        return $metadata;
    }

    /** @param array<string, mixed>|null $metadata */
    public function cleanupRun(?string $runId, ?array $metadata = null): ?array
    {
        if ($metadata === null && $runId !== null && $runId !== '') {
            $run = Run::query()->find($runId);
            $metadata = is_array($run?->metadata) && is_array($run->metadata['long_prompt'] ?? null)
                ? $run->metadata['long_prompt']
                : null;
        }

        if ($metadata === null) {
            return null;
        }

        $cleaned = $this->cleanup($metadata);
        $this->storeRunMetadata($runId, $cleaned);

        return $cleaned;
    }

    /** @param array<string, mixed> $metadata */
    public function materializedEvent(array $metadata): array
    {
        return [
            'type' => 'long_prompt_materialized',
            'status' => 'success',
            'summary' => 'Long prompt was written to temporary project files.',
            'artifacts' => [
                'long_prompt' => $this->publicMetadata($metadata),
            ],
        ];
    }

    /** @param array<string, mixed> $metadata */
    public function cleanedEvent(?string $runId, array $metadata): array
    {
        return [
            'type' => 'long_prompt_cleaned',
            'run_id' => $runId,
            'status' => ($metadata['cleanup_status'] ?? null) === 'deleted' ? 'success' : 'partial',
            'summary' => 'Long prompt temporary files were cleaned.',
            'artifacts' => [
                'long_prompt' => $this->publicMetadata($metadata),
            ],
        ];
    }

    /** @param array<string, mixed> $metadata */
    protected function compactPrompt(string $prompt, array $metadata): string
    {
        $chunkPaths = is_array($metadata['chunk_paths'] ?? null) ? $metadata['chunk_paths'] : [];
        $firstChunk = (string) ($chunkPaths[0] ?? '');
        $lastChunk = (string) ($chunkPaths[array_key_last($chunkPaths)] ?? $firstChunk);

        return trim(
            'Long prompt attached ('.$metadata['original_length'].' chars). '
            .'BosskuAI materialized the full user prompt into temporary files under the active project.'."\n\n"
            .'Full prompt file: '.$metadata['prompt_path']."\n"
            .'Manifest file: '.$metadata['manifest_path']."\n"
            .'Chunk files: '.$firstChunk.' through '.$lastChunk.' (read in order; '.$metadata['chunk_count'].' chunk(s)).'."\n"
            .'Instruction: when exact content matters, use file_read_safe on the chunk files in order before relying on details that are not visible below.'."\n\n"
            .'Prompt preview start:'."\n".$this->excerptStart($prompt)."\n\n"
            .'Prompt preview end:'."\n".$this->excerptEnd($prompt)
        );
    }

    protected function routingPrompt(string $prompt): string
    {
        return trim(
            $this->excerptStart($prompt, 3000)
            ."\n\n[... long prompt omitted for routing; full content is attached as temp files ...]\n\n"
            .$this->excerptEnd($prompt, 3000)
        );
    }

    protected function excerptStart(string $value, int $length = 2000): string
    {
        return substr($value, 0, $length);
    }

    protected function excerptEnd(string $value, int $length = 2000): string
    {
        if (strlen($value) <= $length) {
            return $value;
        }

        return substr($value, -$length);
    }

    /** @param array<string, mixed> $metadata */
    protected function publicMetadata(array $metadata): array
    {
        return [
            'relative_dir' => $metadata['relative_dir'] ?? null,
            'prompt_path' => $metadata['prompt_path'] ?? null,
            'manifest_path' => $metadata['manifest_path'] ?? null,
            'chunk_count' => $metadata['chunk_count'] ?? null,
            'original_length' => $metadata['original_length'] ?? null,
            'sha256' => $metadata['sha256'] ?? null,
            'cleanup_status' => $metadata['cleanup_status'] ?? null,
        ];
    }
}
