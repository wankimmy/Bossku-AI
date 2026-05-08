<?php

namespace App\Services\BosskuAi;

class BosskuResponseIndicator
{
    /**
     * @param  array<string, mixed>  $route  routing decision
     * @param  array<string, string|null>  $models  keys: router, orchestrator, executor, auditor, security_auditor, final_reviewer, direct_answer, writer
     */
    public static function line(array $route, array $models): string
    {
        $skill = (string) ($route['skill'] ?? 'generic');
        $workflow = (string) ($route['workflow'] ?? 'unknown');
        $risk = (string) ($route['risk_level'] ?? 'low');

        $router = $models['router'] ?? 'n/a';
        $orch = $models['orchestrator'] ?? 'n/a';
        $exec = $models['executor'] ?? 'n/a';
        $aud = $models['auditor'] ?? 'n/a';

        $extra = '';
        if (! empty($models['security_auditor'])) {
            $extra .= ', security_auditor='.$models['security_auditor'];
        }
        if (! empty($models['final_reviewer'])) {
            $extra .= ', final_reviewer='.$models['final_reviewer'];
        }
        if ($workflow === 'direct_answer' && ! empty($models['direct_answer'])) {
            return '[BOSSKUAI]'."\n"
                .'Skill: '.$skill."\n"
                .'Workflow: '.$workflow."\n"
                .'Risk: '.$risk."\n"
                .'Models: router='.$router.', direct_answer='.$models['direct_answer'];
        }
        if ($workflow === 'writer_only' && ! empty($models['writer'])) {
            return '[BOSSKUAI]'."\n"
                .'Skill: '.$skill."\n"
                .'Workflow: '.$workflow."\n"
                .'Risk: '.$risk."\n"
                .'Models: router='.$router.', writer='.$models['writer'];
        }

        return '[BOSSKUAI]'."\n"
            .'Skill: '.$skill."\n"
            .'Workflow: '.$workflow."\n"
            .'Risk: '.$risk."\n"
            .'Models: router='.$router.', orchestrator='.$orch.', executor='.$exec.', auditor='.$aud.$extra;
    }

    public static function prepend(string $body, string $indicator): string
    {
        return rtrim($indicator)."\n\n".$body;
    }
}
