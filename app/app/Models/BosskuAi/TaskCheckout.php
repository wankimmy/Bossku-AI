<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaskCheckout extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_task_checkouts';

    protected $fillable = [
        'checkoutable_type',
        'checkoutable_id',
        'assignee',
        'run_id',
        'status',
        'lock_token',
        'checked_out_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'checked_out_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_DONE = 'done';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CANCELLED = 'cancelled';
}