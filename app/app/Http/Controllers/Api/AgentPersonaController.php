<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\AgentPersona;
use App\Services\BosskuAi\AgentPersonaBuiltinPrompts;
use App\Services\BosskuAi\AgentPersonaService;
use Illuminate\Http\Request;
class AgentPersonaController extends Controller
{
    public function __construct(
        protected AgentPersonaService $personas
    ) {}

    public function index()
    {
        return response()->json([
            'data' => $this->personas->listForApi(),
        ]);
    }

    public function show(string $role)
    {
        $role = $this->personas->normalizeRole($role);
        if (! in_array($role, AgentPersonaService::PIPELINE_ROLES, true)) {
            return response()->json(['message' => 'Unknown agent role.'], 404);
        }

        $this->personas->ensurePipelinePersonas();
        $row = AgentPersona::query()->find($role);
        if ($row === null) {
            return response()->json(['message' => 'Unknown agent role.'], 404);
        }

        return response()->json([
            'role' => $row->role,
            'display_name' => $row->display_name,
            'content' => $row->content,
            'enabled' => $row->enabled,
            'updated_at' => $row->updated_at?->toIso8601String(),
            'builtin_preview' => AgentPersonaBuiltinPrompts::previewFor($role),
        ]);
    }

    public function update(Request $request, string $role)
    {
        $role = $this->personas->normalizeRole($role);
        if (! in_array($role, AgentPersonaService::PIPELINE_ROLES, true)) {
            return response()->json(['message' => 'Unknown agent role.'], 404);
        }

        $data = $request->validate([
            'content' => 'nullable|string',
            'enabled' => 'sometimes|boolean',
            'display_name' => 'sometimes|string|max:120',
        ]);

        $row = AgentPersona::query()->firstOrCreate(
            ['role' => $role],
            [
                'display_name' => AgentPersonaService::defaultDisplayNames()[$role] ?? $role,
                'content' => '',
                'enabled' => true,
            ]
        );

        if (array_key_exists('content', $data)) {
            $row->content = $data['content'];
            // Mark as user-owned so the auto-sync on Docker startup won't overwrite this edit.
            // The user can re-attach to agents/*.md via the reset endpoint.
            $row->md_hash = AgentPersonaService::USER_EDITED_HASH;
        }
        if (array_key_exists('enabled', $data)) {
            $row->enabled = (bool) $data['enabled'];
        }
        if (array_key_exists('display_name', $data)) {
            $row->display_name = $data['display_name'];
        }
        $row->save();
        $this->personas->clearCache();

        return response()->json([
            'role' => $row->role,
            'display_name' => $row->display_name,
            'content' => $row->content,
            'enabled' => $row->enabled,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ]);
    }

    public function reset(string $role)
    {
        $role = $this->personas->normalizeRole($role);
        if (! in_array($role, AgentPersonaService::PIPELINE_ROLES, true)) {
            return response()->json(['message' => 'Unknown agent role.'], 404);
        }

        $content = $this->personas->defaultContentFromAgentsMd($role)
            ?? AgentPersonaBuiltinPrompts::previews()[$role]
            ?? '';

        // Re-attach to agents/*.md: track its hash so future .md edits auto-propagate again.
        $row = AgentPersona::query()->updateOrCreate(
            ['role' => $role],
            [
                'display_name' => AgentPersonaService::defaultDisplayNames()[$role] ?? $role,
                'content' => $content,
                'md_hash' => $this->personas->currentMdHash($role),
                'enabled' => true,
            ]
        );
        $this->personas->clearCache();

        return response()->json([
            'role' => $row->role,
            'display_name' => $row->display_name,
            'content' => $row->content,
            'enabled' => $row->enabled,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ]);
    }
}
