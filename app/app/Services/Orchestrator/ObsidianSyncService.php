<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Run;
use Illuminate\Support\Facades\Log;

class ObsidianSyncService
{
    public function sync(Run $run, array $modelRoute, string $finalOutput = ''): void
    {
        $vaultPath = rtrim((string) config('bossku.obsidian_vault_path', ''), '/');
        if ($vaultPath === '') {
            return;
        }

        $projectFolder = (string) config('bossku.obsidian_project_folder', 'Bossku-AI');
        $projectBase   = "{$vaultPath}/Projects/{$projectFolder}";
        $memoriesDir   = "{$projectBase}/Memories";
        $promptsDir    = "{$projectBase}/Prompts";

        try {
            $this->ensureDirectories($memoriesDir, $promptsDir);

            $slug     = $this->makeSlug($run->prompt ?? '');
            $shortId  = substr((string) $run->id, 0, 8);
            $ts       = now()->format('Y-m-d\TH-i-s');
            $filename = "{$ts}-{$shortId}-{$slug}.md";

            $memFile    = "{$memoriesDir}/{$filename}";
            $promptFile = "{$promptsDir}/{$filename}";

            $skill      = $modelRoute['skill'] ?? null;
            $workflow   = $modelRoute['workflow'] ?? null;
            $riskLevel  = $modelRoute['risk_level'] ?? null;

            $projectLink = "[[Projects/{$projectFolder}/Overview|{$projectFolder}]]";
            file_put_contents($memFile,    $this->buildMemoryFile($run, $filename, $projectLink, $projectFolder, $skill, $workflow, $riskLevel, $finalOutput));
            file_put_contents($promptFile, $this->buildPromptsFile($run, $filename, $projectLink, $projectFolder));
        } catch (\Throwable $e) {
            Log::warning('bosskuai.obsidian_sync.failed', [
                'run_id' => $run->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function ensureDirectories(string $memoriesDir, string $promptsDir): void
    {
        foreach ([$memoriesDir, $promptsDir] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function makeSlug(string $text): string
    {
        $text = mb_strtolower(substr($text, 0, 80));
        $text = preg_replace('/[^a-z0-9\s]/u', '', $text) ?? '';
        $text = preg_replace('/\s+/', '_', trim($text)) ?? '';
        $slug = substr(rtrim($text, '_'), 0, 55);

        return $slug !== '' ? $slug : 'run';
    }

    private function buildMemoryFile(Run $run, string $filename, string $projectLink, string $projectFolder, ?string $skill, ?string $workflow, ?string $riskLevel, string $finalOutput): string
    {
        $prompt  = $run->prompt ?? '';
        $shortId = substr((string) $run->id, 0, 8);
        $ts      = now()->format('Y-m-d\TH:i:sP');
        $title   = strlen($prompt) > 80 ? substr($prompt, 0, 77) . '...' : $prompt;

        $promptSnippet = strlen($prompt) > 500 ? substr($prompt, 0, 497) . '...' : $prompt;
        $outputSnippet = strlen($finalOutput) > 800 ? substr($finalOutput, 0, 797) . '...' : $finalOutput;

        $meta = implode("\n", array_filter([
            $skill     ? "- **Skill:** `{$skill}`"    : null,
            $workflow  ? "- **Workflow:** `{$workflow}`" : null,
            $riskLevel ? "- **Risk:** `{$riskLevel}`" : null,
        ]));

        return <<<MD
        # {$title}

        > Project: {$projectLink}
        > Source: {$projectFolder} Web App
        > Run: `{$shortId}`
        > Updated: `{$ts}`

        ---

        ## Prompt

        {$promptSnippet}

        ## Output

        {$outputSnippet}

        ## Routing
        {$meta}

        MD;
    }

    private function buildPromptsFile(Run $run, string $filename, string $projectLink, string $projectFolder): string
    {
        $prompt   = $run->prompt ?? '';
        $shortId  = substr((string) $run->id, 0, 8);
        $ts       = now()->format('Y-m-d\TH:i:sP');
        $title    = strlen($prompt) > 80 ? substr($prompt, 0, 77) . '...' : $prompt;
        $slugName = pathinfo($filename, PATHINFO_FILENAME);

        return <<<MD
        # {$title} - Prompts

        > Project: {$projectLink}
        > Related memory: [[Projects/{$projectFolder}/Memories/{$slugName}|{$title}]]
        > Run: `{$shortId}`

        ## Prompt 1

        - Timestamp: `{$ts}`

        ````text
        {$prompt}
        ````

        MD;
    }
}
