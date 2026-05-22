<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BosskuAI Model Routing Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the model assignments for each agent role.
    | It should stay in sync with ai-assistant/config/model-router.yaml.
    |
    */

    'planning' => [
        'primary' => env('BOSSKU_PLANNING_MODEL', 'gpt-5.5'),
        'fallback' => env('BOSSKU_PLANNING_FALLBACK', 'claude-opus-4.7'),
    ],

    'execution' => [
        'primary' => env('BOSSKU_EXECUTION_MODEL', 'kimi-k2.6'),
        'high_risk_primary' => env('BOSSKU_EXECUTION_HIGH_RISK_MODEL', 'gpt-5.5'),
        'fallback' => env('BOSSKU_EXECUTION_FALLBACK', 'deepseek-v4-pro'),
    ],

    'audit' => [
        'primary' => env('BOSSKU_AUDIT_MODEL', 'claude-opus-4.7'),
        'fallback' => env('BOSSKU_AUDIT_FALLBACK', 'gpt-5.5'),
    ],

    'final_review' => [
        'primary' => env('BOSSKU_FINAL_REVIEW_MODEL', 'gpt-5.5'),
        'fallback' => env('BOSSKU_FINAL_REVIEW_FALLBACK', 'claude-opus-4.7'),
        'only_when' => 'high_risk',
    ],

    'router' => [
        'primary' => env('BOSSKU_ROUTER_MODEL', 'gpt-5.5-instant'),
        'fallback' => env('BOSSKU_ROUTER_FALLBACK', 'gpt-5.4'),
    ],

    'escalation_rules' => [
        'auth',
        'payment',
        'security',
        'privacy',
        'migration',
        'production',
        'data_loss',
        'secrets',
        'multi_service_architecture',
        'repeated_failure',
        'legal_compliance',
    ],

    'execution_defaults' => [
        'allow_lower_cost_when_risk' => 'low',
        'force_frontier_when_risk' => 'high',
    ],

    'memory' => [
        'save_after_plan' => true,
        'save_after_audit' => true,
        'sync_vector_after_save' => true,
        'require_privacy_guard' => true,
    ],
];