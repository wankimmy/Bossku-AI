<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Setting extends Model
{
    protected $table = 'bossku_ai_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        // Reading a setting must never crash a caller. If the settings table is
        // not available yet (e.g. before migrations run, or in a unit test that
        // doesn't touch the DB), fall back to the default instead of throwing.
        try {
            $row = static::query()->find($key);
        } catch (QueryException) {
            return $default;
        }

        return $row?->value ?? $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        try {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        } catch (QueryException) {
            // Settings storage unavailable — treat the write as best-effort.
        }
    }
}
