<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SoulVersion extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_soul_versions';

    protected $fillable = [
        'version', 'content', 'active', 'change_summary', 'suggestions_applied',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'suggestions_applied' => 'array',
        ];
    }

    public static function activateVersion(string $id): void
    {
        static::where('active', true)->update(['active' => false]);
        static::where('id', $id)->update(['active' => true]);
    }

    public static function active(): ?self
    {
        return static::where('active', true)->first();
    }
}
