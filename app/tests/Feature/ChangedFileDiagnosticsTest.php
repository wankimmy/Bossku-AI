<?php

namespace Tests\Feature;

use App\Services\Project\ChangedFileDiagnostics;
use App\Services\Project\ProjectPathResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChangedFileDiagnosticsTest extends TestCase
{
    private string $base;

    private ChangedFileDiagnostics $diagnostics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/cfd_'.uniqid();
        mkdir($this->base, 0777, true);

        // Stub path resolution to the temp dir so the diagnostic logic is tested
        // without the DB-backed active-project lookup.
        $paths = new class($this->base) extends ProjectPathResolver
        {
            public function __construct(private readonly string $root)
            {
                parent::__construct(null);
            }

            public function resolve(string $relativePath = ''): array
            {
                return ['absolute' => $this->root.'/'.$relativePath, 'relative' => $relativePath];
            }
        };

        $this->diagnostics = new ChangedFileDiagnostics($paths);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->base.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->base);
        parent::tearDown();
    }

    #[Test]
    public function valid_php_passes_lint(): void
    {
        file_put_contents($this->base.'/Good.php', "<?php\n\nclass Good { public function x(): int { return 1; } }\n");

        $result = $this->diagnostics->check(['Good.php']);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['ok']);
        $this->assertSame('php -l', $result[0]['checker']);
    }

    #[Test]
    public function broken_php_fails_lint_with_error(): void
    {
        file_put_contents($this->base.'/Bad.php', "<?php\n\nclass Bad { public function x() { return 1 \n");

        $result = $this->diagnostics->check(['Bad.php']);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['ok']);
        $this->assertNotEmpty($result[0]['errors']);
    }

    #[Test]
    public function invalid_json_is_flagged(): void
    {
        file_put_contents($this->base.'/broken.json', '{"a": 1,}');

        $result = $this->diagnostics->check(['broken.json']);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['ok']);
        $this->assertSame('json', $result[0]['checker']);
    }

    #[Test]
    public function valid_json_passes(): void
    {
        file_put_contents($this->base.'/ok.json', '{"a": 1}');

        $result = $this->diagnostics->check(['ok.json']);

        $this->assertTrue($result[0]['ok']);
    }

    #[Test]
    public function unknown_extension_yields_no_diagnostic(): void
    {
        file_put_contents($this->base.'/notes.txt', 'just text');

        $this->assertSame([], $this->diagnostics->check(['notes.txt']));
    }

    #[Test]
    public function missing_file_is_skipped(): void
    {
        $this->assertSame([], $this->diagnostics->check(['does-not-exist.php']));
    }
}
