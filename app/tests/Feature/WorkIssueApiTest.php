<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\WorkIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkIssueApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_shows_and_updates_work_issues_for_kanban(): void
    {
        $project = $this->project();
        $issue = WorkIssue::query()->create([
            'project_id' => $project->id,
            'title' => 'Build checkout settings UI',
            'description' => 'Create the settings page.',
            'status' => 'todo',
            'priority' => 'high',
            'approval_state' => 'approved',
            'assignee_role_slug' => 'tech-lead',
            'metadata' => ['source' => 'test'],
        ]);

        $this->getJson('/api/work-issues')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Build checkout settings UI')
            ->assertJsonPath('data.0.status', 'todo');

        $this->getJson('/api/work-issues/'.$issue->id)
            ->assertOk()
            ->assertJsonPath('title', 'Build checkout settings UI');

        $this->patchJson('/api/work-issues/'.$issue->id, [
            'status' => 'in_progress',
            'priority' => 'medium',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('priority', 'medium');
    }

    private function project(): Project
    {
        return Project::query()->create([
            'name' => 'Work Issue API Project',
            'host_path' => sys_get_temp_dir(),
            'container_path' => sys_get_temp_dir(),
            'is_active' => true,
        ]);
    }
}
