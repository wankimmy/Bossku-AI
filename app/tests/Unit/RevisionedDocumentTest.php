<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Plans\RevisionedDocument;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for revisioned plan documents. Proves: append-only revisions, revision
 * numbering, latest/current access, stale-target detection for approvals, and
 * history retrieval.
 */
class RevisionedDocumentTest extends TestCase
{
    #[Test]
    public function empty_document_has_no_latest(): void
    {
        $doc = new RevisionedDocument('plan');

        $this->assertNull($doc->latest());
        $this->assertSame(0, $doc->currentRevisionNumber());
    }

    #[Test]
    public function revise_creates_first_revision(): void
    {
        $doc = new RevisionedDocument('plan');
        $number = $doc->revise('Plan v1: do the thing', 'planner');

        $this->assertSame(1, $number);
        $this->assertSame(1, $doc->currentRevisionNumber());

        $latest = $doc->latest();
        $this->assertNotNull($latest);
        $this->assertSame('Plan v1: do the thing', $latest->content);
        $this->assertSame('planner', $latest->author);
    }

    #[Test]
    public function multiple_revisions_are_append_only(): void
    {
        $doc = new RevisionedDocument('plan');
        $doc->revise('v1', 'planner');
        $doc->revise('v2 with more detail', 'planner');
        $doc->revise('v3 final plan', 'orchestrator');

        $this->assertSame(3, $doc->currentRevisionNumber());
        $this->assertSame('v3 final plan', $doc->latest()->content);
        $this->assertSame('orchestrator', $doc->latest()->author);
    }

    #[Test]
    public function specific_revision_is_retrievable(): void
    {
        $doc = new RevisionedDocument('plan');
        $doc->revise('first', 'planner');
        $doc->revise('second', 'planner');

        $r1 = $doc->revision(1);
        $this->assertSame('first', $r1->content);
        $this->assertSame(1, $r1->number);

        $r2 = $doc->revision(2);
        $this->assertSame('second', $r2->content);
    }

    #[Test]
    public function is_stale_detects_old_revision_reference(): void
    {
        $doc = new RevisionedDocument('plan');
        $doc->revise('v1', 'planner');
        $doc->revise('v2', 'planner');
        $doc->revise('v3', 'planner');

        // An approval referencing revision 1 is stale (current is 3).
        $this->assertTrue($doc->isStale(1));
        $this->assertTrue($doc->isStale(2));

        // Reference to current revision is not stale.
        $this->assertFalse($doc->isStale(3));
    }

    #[Test]
    public function history_returns_all_revisions_in_order(): void
    {
        $doc = new RevisionedDocument('plan');
        $doc->revise('a', 'planner');
        $doc->revise('b', 'planner');
        $doc->revise('c', 'orchestrator');

        $history = $doc->history();

        $this->assertCount(3, $history);
        $this->assertSame('a', $history[0]->content);
        $this->assertSame('b', $history[1]->content);
        $this->assertSame('c', $history[2]->content);
    }

    #[Test]
    public function revision_to_array_serializes(): void
    {
        $doc = new RevisionedDocument('design');
        $doc->revise('design spec', 'designer');

        $arr = $doc->latest()->toArray();

        $this->assertSame('design', $arr['document_key']);
        $this->assertSame(1, $arr['number']);
        $this->assertSame('design spec', $arr['content']);
        $this->assertSame('designer', $arr['author']);
    }

    #[Test]
    public function different_documents_are_independent(): void
    {
        $plan = new RevisionedDocument('plan');
        $design = new RevisionedDocument('design');

        $plan->revise('plan content', 'planner');
        $design->revise('design content', 'designer');

        $this->assertSame('plan content', $plan->latest()->content);
        $this->assertSame('design content', $design->latest()->content);
        $this->assertSame(1, $plan->currentRevisionNumber());
        $this->assertSame(1, $design->currentRevisionNumber());
    }
}