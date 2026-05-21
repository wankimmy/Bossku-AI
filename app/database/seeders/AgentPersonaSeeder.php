<?php

namespace Database\Seeders;

use App\Models\BosskuAi\AgentPersona;
use App\Services\BosskuAi\AgentPersonaBuiltinPrompts;
use App\Services\BosskuAi\AgentPersonaService;
use Illuminate\Database\Seeder;

class AgentPersonaSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(AgentPersonaService::class);
        $names = AgentPersonaService::defaultDisplayNames();
        $stubs = AgentPersonaBuiltinPrompts::previews();

        foreach (AgentPersonaService::PIPELINE_ROLES as $role) {
            $fromMd = $service->defaultContentFromAgentsMd($role);
            $content = $fromMd ?? ($stubs[$role] ?? 'BosskuAI '.$role.' agent.');
            AgentPersona::query()->updateOrCreate(
                ['role' => $role],
                [
                    'display_name' => $names[$role] ?? ucfirst(str_replace('_', ' ', $role)),
                    'content' => $content,
                    'enabled' => true,
                ]
            );
        }
    }
}
