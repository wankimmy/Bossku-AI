<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * An immutable snapshot of one source's admitted state: the value, the
 * rendered baseline text, and the epoch it was admitted in. Part of a
 * SystemContext Generation.
 */
final readonly class AdmittedSource
{
    public function __construct(
        public ContextKey $key,
        public mixed $value,
        public string $text,
        public int $epoch,
    ) {}
}