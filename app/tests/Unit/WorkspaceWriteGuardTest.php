<?php

namespace Tests\Unit;

use App\Services\Project\WorkspaceWriteGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceWriteGuardTest extends TestCase
{
    #[Test]
    public function it_allows_writable_paths(): void
    {
        $dir = sys_get_temp_dir().'/bossku-write-guard-'.uniqid('', true);
        mkdir($dir, 0777, true);
        $file = $dir.'/test.txt';
        touch($file);

        $guard = new WorkspaceWriteGuard;
        $guard->ensureWritable($file);

        $this->assertFileExists($file);
        @unlink($file);
        @rmdir($dir);
    }

    #[Test]
    public function it_creates_missing_parent_directories(): void
    {
        $dir = sys_get_temp_dir().'/bossku-write-guard-'.uniqid('', true);
        $nested = $dir.'/nested/deep/file.txt';

        $guard = new WorkspaceWriteGuard;
        $guard->ensureWritable($nested);

        $this->assertDirectoryExists(dirname($nested));
        $this->assertTrue(is_writable(dirname($nested)));

        @rmdir(dirname(dirname($nested)));
        @rmdir(dirname($nested));
        @rmdir($dir.'/nested');
        @rmdir($dir);
    }
}
