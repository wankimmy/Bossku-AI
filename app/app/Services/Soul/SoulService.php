<?php

namespace App\Services\Soul;

use App\Models\BosskuAi\SoulVersion;

class SoulService
{
    public function active(): ?SoulVersion
    {
        return SoulVersion::active();
    }

    public function getContent(): string
    {
        $active = $this->active();
        if ($active) {
            return (string) $active->content;
        }

        $path = base_path('bossku/soul.md');
        if (file_exists($path)) {
            return (string) file_get_contents($path);
        }

        return '';
    }

    public function update(string $content, string $changeSummary): SoulVersion
    {
        $current = $this->active();
        $newVersion = $this->incrementPatch($current?->version ?? '1.0.0');

        if ($current) {
            $current->active = false;
            $current->save();
        }

        return SoulVersion::create([
            'version'        => $newVersion,
            'content'        => $content,
            'active'         => true,
            'change_summary' => $changeSummary,
        ]);
    }

    private function incrementPatch(string $semver): string
    {
        $parts = explode('.', $semver);
        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);
        $patch = (int) ($parts[2] ?? 0);

        return $major . '.' . $minor . '.' . ($patch + 1);
    }
}
