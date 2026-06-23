<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * A ContextSource backed by a file's contents (e.g. AGENTS.md, a skill body).
 * The value is the file contents string; load() re-reads the file. Ported
 * from opencode's file-backed sources. This is the simplest concrete source
 * and the proof that the algebra composes with real inputs.
 */
final class FileSource extends ContextSource
{
    public function __construct(ContextKey $key, private readonly string $path)
    {
        parent::__construct($key);
    }

    public function load(): mixed
    {
        if (! is_readable($this->path)) {
            return '';
        }

        return (string) file_get_contents($this->path);
    }

    public function baseline(mixed $value): string
    {
        $text = (string) $value;

        return $text === '' ? '' : "<!-- {$this->key} -->\n{$text}";
    }
}