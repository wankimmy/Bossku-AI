<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FeedbackItem extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_feedback_items';

    protected $fillable = [
        'target_type', 'target_id', 'signal', 'rating',
        'comment', 'metadata', 'processed', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
