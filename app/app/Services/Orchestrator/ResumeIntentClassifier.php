<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\RuntimeSettings;

/**
 * Classifies user clarification answers when resuming a paused run.
 */
class ResumeIntentClassifier
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected RuntimeSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $context  keys: stage, answers, has_free_text, option_only
     * @return 'replan'|'continue'|'abort'
     */
    public function classify(string $answerBlock, array $context = []): string
    {
        $text = $this->extractDecisionText($answerBlock, $context);

        if ($text === '') {
            return ($context['option_only'] ?? false) === true ? 'continue' : 'replan';
        }

        $heuristic = $this->classifyHeuristic($text);
        if ($heuristic !== null) {
            return $heuristic;
        }

        if (($context['option_only'] ?? false) === true) {
            return 'continue';
        }

        return $this->classifyWithLlm($text, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function extractDecisionText(string $answerBlock, array $context): string
    {
        $parts = [trim($answerBlock)];
        $answers = is_array($context['answers'] ?? null) ? $context['answers'] : [];
        foreach ($answers as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            if (! empty($answer['free_text'])) {
                $parts[] = trim((string) $answer['free_text']);
            }
        }

        return trim(implode("\n", array_filter($parts)));
    }

    protected function classifyHeuristic(string $text): ?string
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(stop|abort|cancel|halt|nevermind|never mind|do not continue|don\'t continue)\b/u', $lower)) {
            return 'abort';
        }

        if (preg_match('/\b(re-?plan|replan|change (the )?(approach|plan|scope|strategy)|different approach)\b/u', $lower)) {
            return 'replan';
        }

        if (preg_match('/\b(stage|step|phase)\s*\d+\b/u', $lower)) {
            return 'replan';
        }

        if (preg_match('/\b(start with|only|just|first|instead|one at a time|smaller scope|break it down|narrow scope|minimal scope|do .+ first)\b/u', $lower)) {
            return 'replan';
        }

        if (preg_match('/\b(yes,? proceed|proceed|continue|go ahead|looks good|approved?|retry with guidance)\b/u', $lower)
            && ! preg_match('/\b(stage|step|phase|only|first|re-?plan)\b/u', $lower)
        ) {
            return 'continue';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function classifyWithLlm(string $text, array $context): string
    {
        try {
            $primary = $this->settings->routerModel();
            $fallbacks = [$this->settings->reasoningModel()];
            $models = array_values(array_unique(array_filter([$primary, ...$fallbacks])));

            $system = <<<'SYS'
You classify a user's reply to a BosskuAI agent clarification pause.
Output ONLY valid JSON: {"intent":"replan"|"continue"|"abort"}.

- replan: user changes scope, strategy, sequencing, or wants only part of the work (e.g. "start with stage 1", "only do X", "change the plan").
- continue: user approves proceeding with current plan/executor retry without changing scope.
- abort: user wants to stop the run.
SYS;

            $user = json_encode([
                'stage' => $context['stage'] ?? null,
                'user_reply' => $text,
            ], JSON_THROW_ON_ERROR);

            $out = $this->fallback->chatWithFallbacks(
                $models,
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                0.1,
                1,
                'clarification',
                fn (mixed $j): bool => is_array($j) && isset($j['intent']),
                512,
                null,
            );

            $parsed = is_array($out['parsed'] ?? null) ? $out['parsed'] : [];
            $intent = strtolower(trim((string) ($parsed['intent'] ?? '')));
            if (in_array($intent, ['replan', 'continue', 'abort'], true)) {
                return $intent;
            }
        } catch (\Throwable) {
            //
        }

        return 'replan';
    }
}
