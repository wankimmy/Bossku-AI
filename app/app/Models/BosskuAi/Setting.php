<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'bossku_ai_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->find($key);

        return $row?->value ?? $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
