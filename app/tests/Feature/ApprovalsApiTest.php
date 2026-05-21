<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Approval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function approvals_index_returns_200(): void
    {
        $response = $this->getJson('/api/approvals');

        $response->assertStatus(200);
    }

    /** @test */
    public function approvals_index_with_status_pending_filter_returns_only_pending(): void
    {
        Approval::create([
            'operation_type'        => 'shell_command',
            'operation_description' => 'rm -rf /tmp/test',
            'risk_level'            => 'high',
            'status'                => 'pending',
        ]);

        Approval::create([
            'operation_type'        => 'file_write',
            'operation_description' => 'Write config file',
            'risk_level'            => 'low',
            'status'                => 'approved',
        ]);

        $response = $this->getJson('/api/approvals?status=pending');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertSame('pending', $item['status']);
        }
    }

    /** @test */
    public function approvals_approve_sets_status_to_approved(): void
    {
        $approval = Approval::create([
            'operation_type'        => 'shell_command',
            'operation_description' => 'run deploy script',
            'risk_level'            => 'high',
            'status'                => 'pending',
        ]);

        $response = $this->postJson("/api/approvals/{$approval->id}/approve", [
            'note' => 'Looks good',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approval.status', 'approved');

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id'     => $approval->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function approvals_reject_sets_status_to_rejected(): void
    {
        $approval = Approval::create([
            'operation_type'        => 'shell_command',
            'operation_description' => 'delete production data',
            'risk_level'            => 'critical',
            'status'                => 'pending',
        ]);

        $response = $this->postJson("/api/approvals/{$approval->id}/reject", [
            'note' => 'Too risky',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('approval.status', 'rejected');

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id'     => $approval->id,
            'status' => 'rejected',
        ]);
    }
}
