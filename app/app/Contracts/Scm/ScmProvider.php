<?php

namespace App\Contracts\Scm;

interface ScmProvider
{
    public function providerId(): string;

    /**
     * @return array{state: string, conclusion: string|null, log_url: string|null, summary: string}
     */
    public function getCISummary(string $owner, string $repo, int $pullNumber): array;

    /**
     * @return array{decision: string, review_threads: list<array<string, mixed>>}
     */
    public function getReviewDecision(string $owner, string $repo, int $pullNumber): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getPendingComments(string $owner, string $repo, int $pullNumber): array;

    /**
     * @return array{mergeable: bool, mergeable_state: string, reason: string|null}
     */
    public function getMergeability(string $owner, string $repo, int $pullNumber): array;

    /**
     * @param  array{title?: string, body?: string, head?: string, base?: string}  $options
     * @return array{number: int, html_url: string, state: string}
     */
    public function createOrUpdatePullRequest(string $owner, string $repo, array $options): array;
}
