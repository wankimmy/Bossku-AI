<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunStreamEvent extends Model
{
    public $timestamps = false;

    protected $table = 'bossku_ai_run_stream_events';

    protected $fillable = [
        'run_id',
        'seq',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
