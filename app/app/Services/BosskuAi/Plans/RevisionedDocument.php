<?php

namespace App\Services\BosskuAi\Plans;

/**
 * A revisioned, append-only plan document. Ported from paperclip's documents +
 * document_revisions model. Plans live as revisioned artifacts (not mutable
 * blobs), with optimistic locking via baseRevisionId and stale-target detection
 * on approvals.
 *
 * Each revision has: an id, the document key (e.g. 'plan', 'design', 'notes'),
 * the content, the author (agent role), the revision number, and a timestamp.
 * The latest revision is the current content; prior revisions are kept for
 * audit/time-travel. Stale detection: if an approval references revision 3 but
 * the current is revision 5, the approval is stale and must be re-surfaced.
 */
final class RevisionedDocument
{
    /** @var list<Revision> */
    private array $revisions = [];

    public function __construct(public readonly string $key) {}

    /**
     * Append a new revision. Returns the revision number.
     *
     * @param  string  $content  the full document content
     * @param  string  $author  agent role or user id
     */
    public function revise(string $content, string $author): int
    {
        $number = count($this->revisions) + 1;
        $this->revisions[] = new Revision(
            id: md5($this->key.'|'.$number.'|'.time()),
            documentKey: $this->key,
            number: $number,
            content: $content,
            author: $author,
            createdAt: now()->toIso8601String(),
        );

        return $number;
    }

    /** Get the latest revision, or null if the document is empty. */
    public function latest(): ?Revision
    {
        return $this->revisions[count($this->revisions) - 1] ?? null;
    }

    /** Get a specific revision by number (1-indexed). */
    public function revision(int $number): ?Revision
    {
        return $this->revisions[$number - 1] ?? null;
    }

    /** The current revision number (0 if empty). */
    public function currentRevisionNumber(): int
    {
        return count($this->revisions);
    }

    /**
     * Is the given baseRevisionId stale (i.e. the document has moved past it)?
     * Used for optimistic locking on approvals: if the approval references an
     * old revision, it's stale and must be re-surfaced.
     */
    public function isStale(int $baseRevisionId): bool
    {
        return $baseRevisionId < $this->currentRevisionNumber();
    }

    /** @return list<Revision> all revisions in order */
    public function history(): array
    {
        return $this->revisions;
    }
}