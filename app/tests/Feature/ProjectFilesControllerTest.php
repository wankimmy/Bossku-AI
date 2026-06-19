<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Setting;
use App\Services\Project\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectFilesControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $workspaceParent;

    private string $repoA;

    private string $repoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceParent = sys_get_temp_dir().'/bkproj_'.uniqid();
        $this->repoA = $this->workspaceParent.'/project-a';
        $this->repoB = $this->workspaceParent.'/project-b';
        File::ensureDirectoryExists($this->repoA.'/src');
        File::ensureDirectoryExists($this->repoB.'/src');
        File::put($this->repoA.'/src/hello.txt', "line one\nline two\n");
        File::put($this->repoB.'/src/hello.txt', "project b\n");

        config([
            'bossku.workspace_host_prefix' => $this->workspaceParent,
            'bossku.workspace_mount' => '/workspace',
            'bossku.repo_root' => $this->repoA,
        ]);

        Project::query()->create([
            'name' => 'Project A',
            'host_path' => $this->repoA,
            'container_path' => $this->repoA,
            'is_active' => true,
        ]);
        Project::query()->create([
            'name' => 'Project B',
            'host_path' => $this->repoB,
            'container_path' => $this->repoB,
            'is_active' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workspaceParent)) {
            File::deleteDirectory($this->workspaceParent);
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

        $this->assertSame('updated line', trim(File::get($this->repoA.'/src/hello.txt')));

        $approval = Approval::find($approvalId);
        $this->assertSame('approved', $approval?->status);
    }

    #[Test]
    public function it_creates_missing_parent_directories_for_new_files(): void
    {
        $propose = $this->postJson('/api/project/changes', [
            'path' => 'docs/PRODUCT_SPEC.md',
            'new_contents' => "Nested write proof.\n",
        ]);
        $this->assertSame(201, $propose->status(), $propose->content());

        $approvalId = $propose->json('id');
        $this->assertNotEmpty($approvalId);

        $this->postJson("/api/project/changes/{$approvalId}/approve")->assertOk();
        $this->postJson("/api/project/changes/{$approvalId}/apply")
            ->assertOk()
            ->assertJsonPath('path', 'docs/PRODUCT_SPEC.md');

        $this->assertFileExists($this->repoA.'/docs/PRODUCT_SPEC.md');
        $this->assertSame(
            'Nested write proof.',
            trim(File::get($this->repoA.'/docs/PRODUCT_SPEC.md')),
        );
    }

    #[Test]
    public function it_rejects_traversal_for_new_file_writes(): void
    {
        $this->postJson('/api/project/changes', [
            'path' => '../outside.md',
            'new_contents' => 'outside',
        ])
            ->assertStatus(422);

        $this->postJson('/api/project/changes', [
            'path' => sys_get_temp_dir().'/outside.md',
            'new_contents' => 'outside',
        ])
            ->assertStatus(422);

        $this->assertFileDoesNotExist($this->workspaceParent.'/outside.md');
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

        $this->assertSame("line one\nline two\n", File::get($this->repoA.'/src/hello.txt'));
        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id' => $id,
            'status' => 'rejected',
        ]);
    }

    #[Test]
    public function file_writes_land_in_active_project_not_sibling(): void
    {
        $projectB = Project::query()->where('name', 'Project B')->firstOrFail();
        $this->postJson("/api/project/{$projectB->id}/activate")->assertOk();

        $propose = $this->postJson('/api/project/changes', [
            'path' => 'src/new-file.txt',
            'new_contents' => "created in b\n",
        ])->assertCreated();

        $approvalId = $propose->json('id');
        $this->postJson("/api/project/changes/{$approvalId}/approve")->assertOk();
        $this->postJson("/api/project/changes/{$approvalId}/apply")->assertOk();

        $this->assertFileExists($this->repoB.'/src/new-file.txt');
        $this->assertFileDoesNotExist($this->repoA.'/src/new-file.txt');
        $this->assertSame('created in b', trim(File::get($this->repoB.'/src/new-file.txt')));
    }

    #[Test]
    public function file_writes_use_setting_active_project_when_active_flag_drifts(): void
    {
        $projectB = Project::query()->where('name', 'Project B')->firstOrFail();
        Project::query()->update(['is_active' => false]);
        Setting::setValue(ProjectService::SETTING_ACTIVE_PROJECT_ID, $projectB->id);

        $propose = $this->postJson('/api/project/changes', [
            'path' => 'src/setting-active.txt',
            'new_contents' => "created through setting\n",
        ])->assertCreated();

        $approvalId = $propose->json('id');
        $this->postJson("/api/project/changes/{$approvalId}/approve")->assertOk();
        $this->postJson("/api/project/changes/{$approvalId}/apply")->assertOk();

        $this->assertFileExists($this->repoB.'/src/setting-active.txt');
        $this->assertFileDoesNotExist($this->repoA.'/src/setting-active.txt');
    }
}
