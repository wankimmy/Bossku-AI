<?php

namespace App\Services\Scm;

use App\Contracts\Scm\ScmProvider;
use Illuminate\Support\Facades\Http;

class GithubScmService implements ScmProvider
{
    public function providerId(): string
    {
        return 'github';
    }

    public function getCISummary(string $owner, string $repo, int $pullNumber): array
    {
        $pr = $this->get("/repos/{$owner}/{$repo}/pulls/{$pullNumber}");
        $sha = (string) ($pr['head']['sha'] ?? '');
        if ($sha === '') {
            return ['state' => 'unknown', 'conclusion' => null, 'log_url' => null, 'summary' => 'No head SHA'];
        }

        $checks = $this->get("/repos/{$owner}/{$repo}/commits/{$sha}/check-runs");
        $runs = is_array($checks['check_runs'] ?? null) ? $checks['check_runs'] : [];
        $failed = array_filter($runs, fn ($r) => in_array($r['conclusion'] ?? '', ['failure', 'timed_out', 'cancelled'], true));
        $pending = array_filter($runs, fn ($r) => ($r['status'] ?? '') === 'in_progress' || ($r['status'] ?? '') === 'queued');

        if ($failed !== []) {
            $first = array_values($failed)[0];

            return [
                'state' => 'failed',
                'conclusion' => (string) ($first['conclusion'] ?? 'failure'),
                'log_url' => (string) ($first['html_url'] ?? ''),
                'summary' => (string) ($first['output']['title'] ?? 'CI failed'),
            ];
        }

        if ($pending !== []) {
            return ['state' => 'pending', 'conclusion' => null, 'log_url' => null, 'summary' => 'CI in progress'];
        }

        return ['state' => 'success', 'conclusion' => 'success', 'log_url' => null, 'summary' => 'CI green'];
    }

    public function getReviewDecision(string $owner, string $repo, int $pullNumber): array
    {
        $reviews = $this->get("/repos/{$owner}/{$repo}/pulls/{$pullNumber}/reviews");
        $list = is_array($reviews) ? $reviews : [];
        $states = collect($list)->pluck('state')->map(fn ($s) => strtoupper((string) $s));
        $decision = 'COMMENTED';
        if ($states->contains('CHANGES_REQUESTED')) {
            $decision = 'CHANGES_REQUESTED';
        } elseif ($states->contains('APPROVED')) {
            $decision = 'APPROVED';
        } elseif ($states->contains('DISMISSED')) {
            $decision = 'DISMISSED';
        }

        return [
            'decision' => $decision,
            'review_threads' => [],
        ];
    }

    public function getPendingComments(string $owner, string $repo, int $pullNumber): array
    {
        $comments = $this->get("/repos/{$owner}/{$repo}/pulls/{$pullNumber}/comments");
        if (! is_array($comments)) {
            return [];
        }

        return array_map(fn ($c) => [
            'id' => $c['id'] ?? null,
            'body' => (string) ($c['body'] ?? ''),
            'path' => (string) ($c['path'] ?? ''),
            'user' => (string) ($c['user']['login'] ?? ''),
        ], $comments);
    }

    public function getMergeability(string $owner, string $repo, int $pullNumber): array
    {
        $pr = $this->get("/repos/{$owner}/{$repo}/pulls/{$pullNumber}");

        return [
            'mergeable' => (bool) ($pr['mergeable'] ?? false),
            'mergeable_state' => (string) ($pr['mergeable_state'] ?? 'unknown'),
            'reason' => isset($pr['mergeable']) && $pr['mergeable'] === false ? 'not_mergeable' : null,
        ];
    }

    /**
     * @param  array{title?: string, body?: string, head?: string, base?: string, number?: int}  $options
     */
    public function createOrUpdatePullRequest(string $owner, string $repo, array $options): array
    {
        $number = isset($options['number']) ? (int) $options['number'] : 0;
        $payload = array_filter([
            'title' => $options['title'] ?? null,
            'body' => $options['body'] ?? null,
            'head' => $options['head'] ?? null,
            'base' => $options['base'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($number > 0) {
            $pr = $this->patch("/repos/{$owner}/{$repo}/pulls/{$number}", $payload);

            return [
                'number' => (int) ($pr['number'] ?? $number),
                'html_url' => (string) ($pr['html_url'] ?? ''),
                'state' => (string) ($pr['state'] ?? 'open'),
            ];
        }

        if (! isset($payload['head'], $payload['base'])) {
            throw new \InvalidArgumentException('head and base are required to create a pull request.');
        }

        $pr = $this->post("/repos/{$owner}/{$repo}/pulls", $payload);

        return [
            'number' => (int) ($pr['number'] ?? 0),
            'html_url' => (string) ($pr['html_url'] ?? ''),
            'state' => (string) ($pr['state'] ?? 'open'),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(string $path, array $body): array
    {
        return $this->request('post', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function patch(string $path, array $body): array
    {
        return $this->request('patch', $path, $body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $path): array
    {
        return $this->request('get', $path);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $body = []): array
    {
        $token = (string) config('bossku.scm_github_token', '');
        if ($token === '') {
            throw new \RuntimeException('BOSSKU_GITHUB_TOKEN is not configured.');
        }

        $client = Http::withToken($token)->accept('application/vnd.github+json');
        $response = match ($method) {
            'post' => $client->post('https://api.github.com'.$path, $body),
            'patch' => $client->patch('https://api.github.com'.$path, $body),
            default => $client->get('https://api.github.com'.$path),
        };

        if (! $response->successful()) {
            throw new \RuntimeException('GitHub API error: '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }
}
