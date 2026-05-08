<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\RuntimeSettings;
use Illuminate\Http\Request;

class SettingsApiController extends Controller
{
    public function show(RuntimeSettings $settings)
    {
        return $settings->allPublic();
    }

    public function update(Request $request, RuntimeSettings $settings)
    {
        $data = $request->validate([
            'planner_model' => 'sometimes|string',
            'auditor_model' => 'sometimes|string',
            'executor_model' => 'sometimes|string',
            'ollama_base_url' => 'sometimes|string',
            'embedding_model' => 'sometimes|string',
            'max_memory_results' => 'sometimes|integer',
            'audit_enabled' => 'sometimes|string',
            'memory_storage_enabled' => 'sometimes|string',
            'routing_llm_enabled' => 'sometimes|string',
            'orchestrator_model' => 'sometimes|string',
        ]);

        // Runtime is Ollama-only; keep providers canonical even if old DB settings exist.
        Setting::setValue('planner_provider', 'ollama');
        Setting::setValue('auditor_provider', 'ollama');

        foreach ($data as $k => $v) {
            if (str_ends_with($k, '_enabled')) {
                $v = filter_var($v, FILTER_VALIDATE_BOOL) ? '1' : '0';
            }
            Setting::setValue($k, (string) $v);
        }

        return $settings->allPublic();
    }
}
