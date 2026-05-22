<?php

namespace Database\Seeders;

use App\Services\BosskuAi\AgentPersonaService;
use Illuminate\Database\Seeder;

class AgentPersonaSeeder extends Seeder
{
    public function run(): void
    {
        app(AgentPersonaService::class)->ensurePipelinePersonas();
    }
}
