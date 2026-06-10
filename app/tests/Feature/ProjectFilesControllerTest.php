<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectFilesControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bkproj_'.uniqid();
        File::ensureDirectoryExists($this->repo.'/src');
        File::put($this->repo.'/src/hello.txt', "line one\nline two\n");
        config(['bossku.repo_root' => $this->repo]);

        Project::query()->create([
            'name' => 'Test project',
            'host_path' => $this->repo,
            'container_path' => $this->repo,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->repo)) {
            File::deleteDirectory($this->repo);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_lists_project_tree_and_reads_a_file(): void
    {
        $this->getJson('/api/project/tree')
            ->assertOk()
            ->assertJsonPath('path', '')
            ->assertJsonFragment(['name' => 'src', 'type' => 'dir']);

        $this->getJson('/api/project/tree?path=src')
            ->assertOk()
            ->assertJsonPath('path', 'src')
            ->assertJsonFragment(['name' => 'hello.txt', 'type' => 'file']);

        $this->getJson('/api/project/file?path=src/hello.txt')
            ->assertOk()
            ->assertJsonPath('path', 'src/hello.txt')
            ->assertJsonPath('contents', "line one\nline two\n");
    }

    #[Test]
    public function it_blocks_path_traversal(): void
    {
        // ProjectPathResolver throws 'Path denied.' or 'Path denied or not found.'
        // depending on whether realpath() resolves — environment-dependent, both
        // are a correct traversal block.
        $file = $this->getJson('/api/project/file?path=../../../etc/passwd');
        $file->assertStatus(422);
        $this->assertStringStartsWith('Path denied', (string) $file->json('message'));

        $tree = $this->getJson('/api/project/tree?path=../../../etc/passwd');
        $tree->assertStatus(422);
        $this->assertStringStartsWith('Path denied', (string) $tree->json('message'));
    }

    #[Test]
    public function it_proposes_approves_and_applies_file_changes(): void
    {
        $propose = $this->postJson('/api/project/changes', [
            'path' => 'src/hello.txt',
            'new_contents' => "updated line\n",
        ])->assertCreated();

        $approvalId = $propose->json('id');
        $this->assertNotEmpty($approvalId);

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id' => $approvalId,
            'operation_type' => 'file_write',
            'status' => 'pending',
        ]);

        $this->postJson("/api/project/changes/{$approvalId}/approve")
            ->assertOk();

        $this->postJson("/api/project/changes/{$approvalId}/apply")
            ->assertOk()
            ->assertJsonPath('path', 'src/hello.txt');

        $this->assertSame('updated line', trim(File::get($this->repo.'/src/hello.txt')));

        $approval = Approval::find($approvalId);
        $this->assertSame('approved', $approval?->status);
    }

    #[Test]
    public function it_rejects_pending_changes(): void
    {
        $propose = $this->postJson('/api/project/changes', [
            'path' => 'src/hello.txt',
            'new_contents' => 'should not apply',
        ])->assertCreated();

        $id = $propose->json('id');

        $this->postJson("/api/project/changes/{$id}/reject", ['note' => 'no thanks'])
            ->assertOk();

        $this->assertSame("line one\nline two\n", File::get($this->repo.'/src/hello.txt'));
        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id' => $id,
            'status' => 'rejected',
        ]);
    }
}
