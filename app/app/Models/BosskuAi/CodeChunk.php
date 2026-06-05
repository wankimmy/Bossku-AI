<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CodeChunk extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_code_chunks';

    protected $fillable = [
        'project_id', 'path', 'language', 'chunk_index', 'start_line', 'end_line',
        'content', 'content_hash', 'file_hash', 'token_estimate', 'embedding_json',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'start_line' => 'integer',
            'end_line' => 'integer',
            'token_estimate' => 'integer',
        ];
    }
}
