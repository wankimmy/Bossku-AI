<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Memory;
use Illuminate\Support\Str;

/**
 * "Know Your User" profile.
 *
 * The profile is a singleton {@see Memory} row with type='user' (mirrors the
 * `type: user` memory format used across BosskuAI tools). It can be edited by
 * hand or synthesised from stored memories by the planner model, and is always
 * surfaced to the orchestrator so responses are grounded in who the user is.
 */
class UserProfileService
{
    public const TYPE = 'user';

    public const TAG = 'user-profile';

    public function __construct(
        protected MemoryService $memory,
        protected LlmGateway $llmGateway,
        protected RuntimeSettings $settings,
    ) {}

    /** The active user profile, or null when none has been created yet. */
    public function get(): ?Memory
    {
        return Memory::query()
            ->where('type', self::TYPE)
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Upsert the singleton profile.
     *
     * @param  array<string,mixed>  $metadata
     */
    public function save(string $content, ?string $headline, array $metadata = [], string $origin = 'manual'): Memory
    {
        $content = trim($content);
        $headline = $headline !== null ? trim($headline) : $this->deriveHeadline($content);

        $meta = array_merge([
            'source' => 'profile',
            'node_type' => 'memory',
            'origin' => $origin,
            'profile_updated_at' => now()->toIso8601String(),
        ], $metadata);

        $existing = $this->get();

        if ($existing) {
            return $this->memory->updateMemory($existing->getKey(), [
                'type' => self::TYPE,
                'content' => $content,
                'human_summary' => $headline,
                'metadata' => array_merge($existing->metadata ?? [], $meta),
                'tags' => [self::TAG],
                'source' => 'profile',
                'is_active' => true,
                'confidence' => 0.9,
            ]);
        }

        return $this->memory->store(
            content: $content,
            type: self::TYPE,
            metadata: $meta,
            humanSummary: $headline,
            tags: [self::TAG],
            source: 'profile',
            importance: 0.95,
            confidence: 0.9,
        );
    }

    /**
     * Synthesise the profile from stored memories using the planner model,
     * refining the existing profile when one is present.
     */
    public function generate(): Memory
    {
        $sources = $this->gatherSourceMaterial();
        $current = $this->get();
        $model = $this->settings->plannerModel();

        $system = <<<'SYS'
You build a concise "Know Your User" profile for an AI cofounder workspace.
Write in second/third person about the human operator, grounded ONLY in the
provided memories and existing profile. Do not invent facts. If evidence is
thin, keep it short and high-level rather than guessing.

Cover, when supported by evidence:
- Who they are (role, focus, expertise level).
- What they are building (products, companies, domains).
- Working posture and preferences (how they want the assistant to behave).
- Operating standard / instruction sources, if mentioned.

Reply ONLY with valid JSON (no markdown fences), keys:
  "headline": one neutral sentence summarising the user (<= 160 chars),
  "profile_markdown": the profile body as short markdown paragraphs / bullets.
SYS;

        $payload = json_encode([
            'existing_profile' => $current?->content,
            'memories' => $sources,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $out = $this->llmGateway->chat($model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $payload],
        ], 0.3, null, null, 'planner');

        [$headline, $markdown] = $this->parseGeneration((string) ($out['text'] ?? ''), $current);

        $resolvedModel = (string) ($out['model_resolved'] ?? $out['modelResolved'] ?? $model);

        return $this->save($markdown, $headline, [
            'generated_by_model' => $resolvedModel,
            'generated_at' => now()->toIso8601String(),
            'source_memory_count' => count($sources),
        ], 'auto');
    }

    /**
     * High-signal memories that describe the user, excluding the profile itself.
     *
     * @return list<array{type:string,text:string}>
     */
    protected function gatherSourceMaterial(int $limit = 60): array
    {
        return Memory::query()
            ->where('is_active', true)
            ->where('type', '!=', self::TYPE)
            ->orderByDesc('confidence')
            ->orderByDesc('usage_count')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Memory $m): array => [
                'type' => (string) $m->type,
                'text' => $m->human_summary ?: Str::limit(strip_tags($m->content), 300),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string} [headline, markdown]
     */
    protected function parseGeneration(string $text, ?Memory $current): array
    {
        $json = $this->extractJson($text);
        if (is_array($json)) {
            $markdown = trim((string) ($json['profile_markdown'] ?? $json['markdown'] ?? ''));
            $headline = trim((string) ($json['headline'] ?? ''));
            if ($markdown !== '') {
                return [$headline !== '' ? $headline : $this->deriveHeadline($markdown), $markdown];
            }
        }

        // Fallback: model returned prose, not JSON. Keep it usable.
        $markdown = trim($text);
        if ($markdown === '') {
            $markdown = $current?->content ?? 'No profile could be generated yet. Add detail manually or run more sessions first.';
        }

        return [$this->deriveHeadline($markdown), $markdown];
    }

    /** @return array<string,mixed>|null */
    protected function extractJson(string $text): ?array
    {
        $text = trim($text);
        $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function deriveHeadline(string $content): string
    {
        foreach (preg_split('/\r?\n/', $content) ?: [] as $line) {
            $line = trim(preg_replace('/^[#>\-\*\s]+/', '', $line) ?? '');
            if ($line !== '') {
                return Str::limit($line, 160, '');
            }
        }

        return 'BosskuAI user profile';
    }
}
