<?php

namespace App\Services\Soul;

use App\Models\BosskuAi\SoulVersion;
use Illuminate\Support\Collection;

class SoulVersionService
{
    public function snapshot(string $content, string $version, bool $setActive = false): SoulVersion
    {
        if ($setActive) {
            SoulVersion::where('active', true)->update(['active' => false]);
        }

        return SoulVersion::create([
            'version' => $version,
            'content' => $content,
            'active'  => $setActive,
        ]);
    }

    public function diff(string $versionA, string $versionB): string
    {
        $a = SoulVersion::where('version', $versionA)->firstOrFail();
        $b = SoulVersion::where('version', $versionB)->firstOrFail();

        $linesA = explode("\n", (string) $a->content);
        $linesB = explode("\n", (string) $b->content);

        $output = [];
        $maxLen = max(count($linesA), count($linesB));

        for ($i = 0; $i < $maxLen; $i++) {
            $lineA = $linesA[$i] ?? null;
            $lineB = $linesB[$i] ?? null;

            if ($lineA === $lineB) {
                $output[] = '  ' . ($lineA ?? '');
                continue;
            }

            if ($lineA !== null) {
                $output[] = '- ' . $lineA;
            }
            if ($lineB !== null) {
                $output[] = '+ ' . $lineB;
            }
        }

        return implode("\n", $output);
    }

    public function history(): Collection
    {
        return SoulVersion::orderByDesc('created_at')->get();
    }
}
