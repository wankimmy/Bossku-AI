<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Llm\ProviderRegistry;
use Illuminate\Http\Request;

class SettingsApiController extends Controller
{
    /** @var list<string> */
    private const STRING_KEYS = [
        'planner_model',
        'reasoning_model',
        'orchestrator_model',
        'router_model',
        'coding_model',
        'executor_model',
        'review_model',
        'auditor_model',
        'security_auditor_model',
        'final_reviewer_model',
        'writer_model',
        'direct_answer_model',
        'executor_default_model',
        'executor_frontend_model',
        'executor_backend_model',
        'executor_devops_model',
        'executor_high_risk_model',
        'embedding_model',
        'ollama_base_url',
        'memory_humanize_model',
        'orchestrator_clarification_mode',
        'orchestrator_plan_confirmation_mode',
    ];

    /** @var list<string> */
    private const BOOL_KEYS = [
        'audit_enabled',
        'memory_storage_enabled',
        'memory_ollama_enabled',
        'routing_llm_enabled',
        'executor_strict_validation',
        'executor_apply_feedback',
        'executor_revision_escalation',
        'executor_risk_aware_profile',
        'executor_patch_precheck',
        'llm_truncation_retry_boost',
        'council_plan_review_enabled',
        'company_staff_enabled',
        'staff_council_enabled',
        'ai_council_enabled',
        'agent_wakeups_enabled',
        'staff_auto_issue_generation_enabled',
    ];

    /** @var list<string> */
    private const INT_KEYS = [
        'max_memory_results',
        'max_revision_rounds',
    ];

    public function show(RuntimeSettings $settings)
    {
        return $settings->allPublic();
    }

    public function update(Request $request, RuntimeSettings $settings, ProviderRegistry $providerRegistry)
    {
        $payload = $this->extractUpdatablePayload($request);
        $request->merge($payload);

        $rules = [];
        foreach (self::STRING_KEYS as $key) {
            $rules[$key] = 'sometimes|nullable|string|max:255';
        }
        foreach (self::BOOL_KEYS as $key) {
            $rules[$key] = 'sometimes';
        }
        foreach (self::INT_KEYS as $key) {
            $rules[$key] = 'sometimes|integer|min:0|max:100';
        }
        $rules['model_aliases'] = 'sometimes|array';
        $rules['model_aliases.*'] = 'sometimes|string|max:255';
        $rules['ollama_api_key'] = 'sometimes|nullable|string|max:512';
        $rules['anthropic_api_key'] = 'sometimes|nullable|string|max:512';
        $rules['orchestrator_clarification_mode'] = 'sometimes|nullable|string|in:smart,always,off';
        $rules['orchestrator_plan_confirmation_mode'] = 'sometimes|nullable|string|in:always,questions,off';

        $data = $request->validate($rules);

        Setting::setValue('planner_provider', 'ollama');
        Setting::setValue('auditor_provider', 'ollama');

        if (array_key_exists('ollama_api_key', $data)) {
            $apiKey = $data['ollama_api_key'];
            unset($data['ollama_api_key']);
            if ($this->shouldPersistApiKey($apiKey)) {
                $settings->setOllamaApiKey(trim((string) $apiKey));
            }
        }

        if (array_key_exists('anthropic_api_key', $data)) {
            $apiKey = $data['anthropic_api_key'];
            unset($data['anthropic_api_key']);
            if ($this->shouldPersistApiKey($apiKey)) {
                $settings->setAnthropicApiKey(trim((string) $apiKey));
            }
        }

        if (isset($data['model_aliases']) && is_array($data['model_aliases'])) {
            $merged = array_merge($settings->modelAliases(), $data['model_aliases']);
            Setting::setValue('model_aliases', json_encode($merged, JSON_THROW_ON_ERROR));
            unset($data['model_aliases']);
            $settings->clearAliasesCache();
        }

        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                continue;
            }
            if (in_array($k, self::BOOL_KEYS, true)) {
                $v = filter_var($v, FILTER_VALIDATE_BOOL) ? '1' : '0';
            }
            Setting::setValue($k, (string) $v);
        }

        $settings->clearAliasesCache();
        $providerRegistry->refresh();

        return $settings->allPublic();
    }

    /**
     * Whitelist updatable keys and normalize legacy clients that POST the full GET payload.
     *
     * @return array<string, mixed>
     */
    protected function extractUpdatablePayload(Request $request): array
    {
        $allowed = array_merge(
            self::STRING_KEYS,
            self::BOOL_KEYS,
            self::INT_KEYS,
            ['model_aliases', 'ollama_api_key', 'anthropic_api_key'],
        );

        $payload = $request->only($allowed);

        if (isset($payload['model_aliases'])) {
            $payload['model_aliases'] = $this->normalizeModelAliases($payload['model_aliases']);
        }

        foreach (self::INT_KEYS as $key) {
            if (! isset($payload[$key])) {
                continue;
            }
            if (is_string($payload[$key]) && is_numeric($payload[$key])) {
                $payload[$key] = (int) $payload[$key];
            }
        }

        return $payload;
    }

    /**
     * @return array<string, string>|null
     */
    protected function normalizeModelAliases(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            /** @var array<string, string> $filtered */
            $filtered = [];
            foreach ($raw as $logical => $physical) {
                if (is_string($logical) && is_string($physical) && $physical !== '') {
                    $filtered[$logical] = $physical;
                }
            }

            return $filtered;
        }

        if (! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $this->normalizeModelAliases($decoded);
    }

    protected function shouldPersistApiKey(mixed $apiKey): bool
    {
        if (! is_string($apiKey)) {
            return false;
        }

        $trimmed = trim($apiKey);
        if ($trimmed === '') {
            return false;
        }

        return ! preg_match('/^[\s•*]+$/u', $trimmed);
    }
}
