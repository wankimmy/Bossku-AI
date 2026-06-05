<?php

namespace Tests\Unit;

use App\Services\Governance\ProposedFileChangeGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProposedFileChangeGuardTest extends TestCase
{
    private ProposedFileChangeGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new ProposedFileChangeGuard;
    }

    #[Test]
    public function it_rejects_placeholder_after_content(): void
    {
        $reason = $this->guard->validate(
            "<?php\nclass Foo {}\n",
            'Will be determined after reading the file',
            'modified',
            'app/Foo.php',
        );

        $this->assertNotNull($reason);
    }

    #[Test]
    public function it_rejects_destructive_wipe(): void
    {
        $before = implode("\n", array_map(fn ($i) => "// line {$i}", range(1, 30)));
        $reason = $this->guard->validate($before, 'placeholder only', 'modified', 'app/Foo.php');

        $this->assertNotNull($reason);
    }

    #[Test]
    public function it_allows_valid_small_modification(): void
    {
        $before = "<?php\n\nclass ReceiptController {}\n";
        $after = "<?php\n\nclass ReceiptController {\n    // validated\n}\n";

        $this->assertNull($this->guard->validate($before, $after, 'modified', 'app/ReceiptController.php'));
    }

    #[Test]
    public function it_allows_deleted_with_empty_after(): void
    {
        $this->assertNull($this->guard->validate("<?php\n", '', 'deleted', 'app/x.php'));
    }

    #[Test]
    public function it_allows_short_plain_text_line_edits(): void
    {
        $this->assertNull($this->guard->validate("original\n", "changed\n", 'modified', 'keep.txt'));
    }
}
