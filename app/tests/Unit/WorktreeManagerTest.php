<?php

namespace Tests\Unit;

use App\Services\Workspace\WorktreeManager;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class WorktreeManagerTest extends TestCase
{
    #[Test]
    public function directory_slug_flattens_slashes_and_rejects_traversal(): void
    {
        $manager = app(WorktreeManager::class);
        $method = new ReflectionMethod($manager, 'directorySlug');
        $method->setAccessible(true);

        $slug = (string) $method->invoke($manager, 'bossku/feature/foo', 'abcd1234-efgh-5678');
        $this->assertStringStartsWith('abcd1234', $slug);
        $this->assertStringNotContainsString('/', $slug);
        $this->assertStringNotContainsString('..', $slug);

        $evil = (string) $method->invoke($manager, '../escape', 'abcd1234-efgh-5678');
        $this->assertStringNotContainsString('..', $evil);
    }

    #[Test]
    public function sanitize_branch_rejects_parent_directory_segments(): void
    {
        $manager = app(WorktreeManager::class);
        $method = new ReflectionMethod($manager, 'sanitizeBranch');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($manager, 'bossku/../main');
    }

    #[Test]
    public function assert_path_within_pool_rejects_escape_attempts(): void
    {
        $repo = sys_get_temp_dir().'/bkwt_'.uniqid();
        File::ensureDirectoryExists($repo.'/.bossku/worktrees');

        $manager = app(WorktreeManager::class);
        $assert = new ReflectionMethod($manager, 'assertPathWithinPool');
        $assert->setAccessible(true);

        $outside = dirname($repo).DIRECTORY_SEPARATOR.'outside_'.uniqid();

        $this->expectException(\RuntimeException::class);
        $assert->invoke($manager, $repo, $outside);

        File::deleteDirectory($repo);
    }
}
