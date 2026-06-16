<?php

namespace App\Services\Kernel;

/**
 * Reads the BOSSKU_KERNEL flag. `graph` routes runs through the kernel pipeline;
 * `legacy` (default) keeps the existing OrchestratorService path untouched.
 */
final class KernelMode
{
    public static function value(): string
    {
        return (string) config('bossku.kernel', 'legacy');
    }

    public static function graph(): bool
    {
        return self::value() === 'graph';
    }

    public static function maxSteps(): int
    {
        return max(1, (int) config('bossku.kernel_max_steps', 100));
    }
}
