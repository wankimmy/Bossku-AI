<?php

namespace App\Services\Council;

use App\Support\PromptContextHelper;

class CouncilQuestionService
{
    /**
     * @param  array<string, mixed>  $modelRoute
     * @param  list<array{role?: string, content?: string}>  $conversation
     * @return array{needs_questions: bool, already_answered: bool, questions: list<array<string, mixed>>}
     */
    public function analyze(string $userPrompt, array $modelRoute, array $conversation = []): array
    {
        $current = PromptContextHelper::currentRequest($userPrompt);
        $lower = mb_strtolower($current);

        if (PromptContextHelper::isMetaAboutAssistant($userPrompt)) {
            return ['needs_questions' => false, 'already_answered' => false, 'questions' => []];
        }

        $questions = [];

        if ($this->looksAmbiguous($lower)) {
            $questions[] = $this->question(
                'goal',
                'What outcome do you want from this request?',
                ['Advice only', 'Draft content', 'Implement in code', 'Audit/review existing work'],
            );
        }

        if (preg_match('/\b(seo|landing page|marketing|positioning|campaign)\b/u', $lower)
            && ! preg_match('/\b(audience|customer|buyer|icp|b2b|b2c|saas|ecommerce)\b/u', $lower)) {
            $questions[] = $this->question(
                'audience',
                'Who is the target audience or buyer?',
                ['Developers', 'SMB owners', 'Enterprise teams', 'Consumers', 'Other'],
                allowCustom: true,
            );
        }

        if (preg_match('/\b(sales|outreach|proposal|pitch)\b/u', $lower)
            && ! preg_match('/\b(price|pricing|offer|product|service)\b/u', $lower)) {
            $questions[] = $this->question(
                'offer',
                'What product or offer should the message promote?',
                [],
                inputType: 'short_text',
            );
        }

        if (preg_match('/\b(ui\/ux|wireframe|screen|onboarding|dashboard)\b/u', $lower)
            && ! preg_match('/\b(mobile|desktop|responsive|platform)\b/u', $lower)) {
            $questions[] = $this->question(
                'platform',
                'Which platform should the UI/UX review prioritize?',
                ['Mobile', 'Desktop', 'Both'],
            );
        }

        $questions = array_slice($questions, 0, 3);
        $alreadyAnswered = $this->conversationAnswersMissingFacts($conversation, $questions);

        return [
            'needs_questions' => $questions !== [] && ! $alreadyAnswered,
            'already_answered' => $alreadyAnswered,
            'questions' => $questions,
        ];
    }

    protected function looksAmbiguous(string $lower): bool
    {
        if (preg_match('/\b(best|better|should i|which one|help me decide|recommend)\b/u', $lower)) {
            return true;
        }

        return mb_strlen($lower) < 28 && ! preg_match('/\b(fix|audit|implement|write|create|explain)\b/u', $lower);
    }

    /**
     * @param  list<string>  $options
     * @return array<string, mixed>
     */
    protected function question(
        string $id,
        string $prompt,
        array $options = [],
        string $inputType = 'single_choice',
        bool $allowCustom = false,
    ): array {
        return [
            'id' => $id,
            'prompt' => $prompt,
            'input_type' => $inputType,
            'options' => array_map(
                static fn (string $label, int $idx) => ['id' => 'opt_'.$idx, 'label' => $label],
                $options,
                array_keys($options),
            ),
            'allow_custom' => $allowCustom,
        ];
    }

    /**
     * @param  list<array{role?: string, content?: string}>  $conversation
     * @param  list<array<string, mixed>>  $questions
     */
    protected function conversationAnswersMissingFacts(array $conversation, array $questions): bool
    {
        if ($conversation === [] || $questions === []) {
            return false;
        }

        $recentUser = '';
        foreach (array_reverse($conversation) as $turn) {
            if (($turn['role'] ?? '') === 'user') {
                $recentUser = mb_strtolower((string) ($turn['content'] ?? ''));
                break;
            }
        }

        if ($recentUser === '') {
            return false;
        }

        foreach ($questions as $question) {
            $id = (string) ($question['id'] ?? '');
            if ($id === 'audience' && preg_match('/\b(developer|smb|enterprise|consumer|b2b|b2c)\b/u', $recentUser)) {
                continue;
            }
            if ($id === 'platform' && preg_match('/\b(mobile|desktop|both)\b/u', $recentUser)) {
                continue;
            }
            if ($id === 'offer' && mb_strlen($recentUser) > 12) {
                continue;
            }
            if ($id === 'goal' && preg_match('/\b(advice|draft|implement|audit|review)\b/u', $recentUser)) {
                continue;
            }

            return false;
        }

        return true;
    }
}
