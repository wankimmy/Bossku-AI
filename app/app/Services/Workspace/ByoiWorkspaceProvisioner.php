<?php

namespace App\Services\Workspace;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunWorkspace;

/**
 * Bring-your-own-infrastructure workspace hook (container/remote host).
 */
class ByoiWorkspaceProvisioner
{
    public function enabled(): bool
    {
        return (bool) config('bossku.byoi_enabled', false);
    }

    /**
     * @param  array<string, mixed>  $provisionJson
     */
    public function attachProvisionedHost(Run $run, array $provisionJson): RunWorkspace
    {
        $host = (string) ($provisionJson['host'] ?? '');
        $worktreePath = (string) ($provisionJson['worktreePath'] ?? $provisionJson['worktree_path'] ?? '');
        if ($host === '' || $worktreePath === '') {
            throw new \InvalidArgumentException('BYOI provision JSON requires host and worktreePath.');
        }

        return RunWorkspace::query()->updateOrCreate(
            ['run_id' => $run->getKey()],
            [
                'project_id' => null,
                'base_ref' => (string) ($provisionJson['base_ref'] ?? 'HEAD'),
                'branch_name' => (string) ($provisionJson['branch'] ?? 'bossku/byoi'),
                'worktree_path' => $worktreePath,
                'status' => 'ready',
                'metadata' => [
                    'execution_mode' => 'byoi',
                    'remote_host' => $host,
                    'provision' => $provisionJson,
                ],
            ],
        );
    }
}
