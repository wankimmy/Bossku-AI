<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApprovalsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createApproval(array $overrides = []): Approval
    {
        $run = Run::factory()->create();

        return Approval::create(array_merge([
            'run_id' => $run->id,
            'operation_type' => 'shell_command',
            'operation_description' => 'test operation',
            'risk_level' => 'medium',
            'status' => 'pending',
        ], $overrides));
    }

    #[Test]
    public function approvals_index_returns_200(): void
    {
        $response = $this->getJson('/api/approvals');

        $response->assertStatus(200);
    }

    #[Test]
    public function approvals_index_with_status_pending_filter_returns_only_pending(): void
    {
        $this->createApproval([
            'operation_description' => 'rm -rf /tmp/test',
            'risk_level' => 'high',
            'status' => 'pending',
        ]);

        $run = Run::factory()->create();
        Approval::create([
            'run_id' => $run->id,
            'operation_type' => 'file_write',
            'operation_description' => 'Write config file',
            'risk_level' => 'low',
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/approvals?status=pending');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertSame('pending', $item['status']);
        }
    }

    #[Test]
    public function approvals_approve_sets_status_to_approved(): void
    {
        $approval = $this->createApproval([
            'operation_description' => 'run deploy script',
            'risk_level' => 'high',
        ]);

        $response = $this->postJson("/api/approvals/{$approval->id}/approve", [
            'note' => 'Looks good',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approval.status', 'approved');

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
        ]);
    }

    #[Test]
    public function approvals_reject_sets_status_to_rejected(): void
    {
        $approval = $this->createApproval([
            'operation_description' => 'delete production data',
            'risk_level' => 'critical',
        ]);

        $response = $this->postJson("/api/approvals/{$approval->id}/reject", [
            'note' => 'Too risky',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approval.status', 'rejected');

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
        ]);
    }
}
