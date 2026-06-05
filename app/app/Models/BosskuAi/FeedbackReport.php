<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackReport extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_feedback_reports';

    protected $fillable = [
        'run_id',
        'report_type',
        'dedupe_key',
        'summary',
        'evidence',
        'confidence',
        'status',
        'verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'confidence' => 'float',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
