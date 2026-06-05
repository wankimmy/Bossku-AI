<?php

namespace App\Models\BosskuAi;

use Database\Factories\BosskuAiRunFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Run extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bossku_ai_runs';

    protected $fillable = [
        'prompt', 'final_output', 'status', 'total_latency_ms',
        'total_token_estimate', 'metadata',
        'audit_score', 'risk_level', 'soul_version_id', 'estimated_cost', 'selected_skill_name',
        'parent_run_id', 'run_kind', 'supervisor_slot',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'estimated_cost' => 'decimal:8',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RunStep::class, 'run_id')->orderBy('step_number');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class, 'run_id');
    }

    public function memoryLinks(): HasMany
    {
        return $this->hasMany(MemoryRunLink::class, 'run_id');
    }

    public function agentMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'run_id');
    }

    public function fileChanges(): HasMany
    {
        return $this->hasMany(FileChange::class, 'run_id');
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class, 'run_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'run_id');
    }

    public function streamEvents(): HasMany
    {
        return $this->hasMany(RunStreamEvent::class, 'run_id')->orderBy('seq');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'run_id');
    }

    public function soulVersion(): BelongsTo
    {
        return $this->belongsTo(SoulVersion::class, 'soul_version_id');
    }

    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }

    public function childRuns(): HasMany
    {
        return $this->hasMany(self::class, 'parent_run_id')->orderBy('supervisor_slot');
    }

    public function workspace(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RunWorkspace::class, 'run_id');
    }

    public function cliSessions(): HasMany
    {
        return $this->hasMany(CliSession::class, 'run_id');
    }

    public function reactionStates(): HasMany
    {
        return $this->hasMany(ReactionState::class, 'run_id');
    }

    protected static function newFactory(): BosskuAiRunFactory
    {
        return BosskuAiRunFactory::new();
    }
}
