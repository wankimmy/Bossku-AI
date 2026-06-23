<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * A ContextSource backed by an in-memory value (e.g. the retrieved memory
 * payload, the active agent persona, the current date). The caller sets the
 * value at construction; load() returns it. Use this for computed or
 * session-scoped context that doesn't come from a file.
 */
final class ValueSource extends ContextSource
{
    public function __construct(ContextKey $key, private mixed $value)
    {
        parent::__construct($key);
    }

    public function load(): mixed
    {
        return $this->value;
    }

    public function baseline(mixed $value): string
    {
        $text = is_string($value) ? $value : (string) json_encode($value);

        return $text === '' ? '' : "<!-- {$this->key} -->\n{$text}";
    }
}