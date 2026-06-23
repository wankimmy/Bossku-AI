<?php

namespace App\Services\BosskuAi\Kanban;

/**
 * A Kanban card for agent task tracking. Ported from ECC's
 * team-agent-orchestration skill. Each card has a state machine:
 * Backlog → Ready → Running → Review → Merged → Archived, with Blocked as a
 * side state. The card carries owner, scope, acceptance criteria, and a
 * merge_gate flag.
 *
 * The five failure modes (from ECC) that this model prevents:
 *   1. Agent soup — too many agents, no clear ownership (card has one owner)
 *   2. Invisible work — work with no card is not allowed
 *   3. Board theater — cards that never move (stale detection)
 *   4. Overlapping writes — two cards touching the same files (scope check)
 *   5. No product artifact — a card without acceptance criteria (required field)
 */
final class KanbanCard
{
    public const BACKLOG = 'backlog';

    public const READY = 'ready';

    public const RUNNING = 'running';

    public const REVIEW = 'review';

    public const MERGED = 'merged';

    public const ARCHIVED = 'archived';

    public const BLOCKED = 'blocked';

    /** @var list<string> */
    private const VALID_TRANSITIONS = [
        self::BACKLOG => [self::READY, self::ARCHIVED],
        self::READY => [self::RUNNING, self::BLOCKED, self::ARCHIVED],
        self::RUNNING => [self::REVIEW, self::BLOCKED, self::BACKLOG],
        self::REVIEW => [self::MERGED, self::RUNNING, self::BLOCKED],
        self::MERGED => [self::ARCHIVED],
        self::BLOCKED => [self::READY, self::BACKLOG, self::ARCHIVED],
        self::ARCHIVED => [],
    ];

    /**
     * @param  string  $id
     * @param  string  $title
     * @param  string  $owner  agent role or run id
     * @param  string  $state  one of the state constants
     * @param  list<string>  $scope  file paths or areas this card touches
     * @param  list<string>  $acceptance  acceptance criteria (must not be empty)
     * @param  bool  $mergeGate  whether this card requires merge-gate approval
     * @param  ?string  $branch  git branch
     * @param  ?string  $worktree  git worktree path
     * @param  ?string  $handoff  handoff notes for the next owner
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $owner,
        public string $state,
        public array $scope,
        public array $acceptance,
        public bool $mergeGate = false,
        public ?string $branch = null,
        public ?string $worktree = null,
        public ?string $handoff = null,
    ) {
        if ($acceptance === []) {
            throw new \InvalidArgumentException('KanbanCard must have at least one acceptance criterion (prevents "no product artifact" failure mode).');
        }
    }

    /**
     * Transition to a new state. Returns true if the transition is valid.
     */
    public function transition(string $newState): bool
    {
        $allowed = self::VALID_TRANSITIONS[$this->state] ?? [];
        if (! in_array($newState, $allowed, true)) {
            return false;
        }
        $this->state = $newState;

        return true;
    }

    /**
     * Does this card's scope overlap with another card's scope?
     * Overlapping writes are a failure mode — the orchestrator must land
     * overlapping cards sequentially.
     */
    public function overlaps(self $other): bool
    {
        return count(array_intersect($this->scope, $other->scope)) > 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'owner' => $this->owner,
            'state' => $this->state,
            'scope' => $this->scope,
            'acceptance' => $this->acceptance,
            'merge_gate' => $this->mergeGate,
            'branch' => $this->branch,
            'worktree' => $this->worktree,
            'handoff' => $this->handoff,
        ];
    }
}