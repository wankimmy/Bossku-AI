<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use App\Services\Project\SshExecutionContext;
use App\Services\Workspace\ByoiWorkspaceProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteExecutionController extends Controller
{
    public function sshStatus(): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) config('bossku.ssh_execution_enabled', false),
            'byoi_enabled' => (bool) config('bossku.byoi_enabled', false),
        ]);
    }

    public function attachByoi(Request $request, string $runId, ByoiWorkspaceProvisioner $byoi): JsonResponse
    {
        if (! $byoi->enabled()) {
            return response()->json(['message' => 'BYOI is disabled.'], 422);
        }

        $run = Run::query()->find($runId);
        if ($run === null) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'worktreePath' => 'required|string|max:500',
            'branch' => 'nullable|string|max:191',
            'base_ref' => 'nullable|string|max:191',
        ]);

        $workspace = $byoi->attachProvisionedHost($run, $validated);

        return response()->json($workspace);
    }

    public function previewSshCommand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'user' => 'required|string|max:128',
            'remote_root' => 'required|string|max:500',
            'command' => 'required|string|max:2000',
        ]);

        $ctx = new SshExecutionContext(
            host: $validated['host'],
            port: (int) ($validated['port'] ?? 22),
            user: $validated['user'],
            remoteRoot: $validated['remote_root'],
        );

        return response()->json([
            'wrapped_command' => $ctx->wrapCommand($validated['command']),
        ]);
    }
}
