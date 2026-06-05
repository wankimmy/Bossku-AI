<?php

namespace App\Services\Learning;

use App\Models\BosskuAi\FeedbackReport;
use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Run;
use App\Services\Learning\LearningEventPromoter;
use Illuminate\Support\Str;

class FeedbackReportService
{
    public function __construct(
        private readonly LearningEventPromoter $promoter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $type, string $summary, array $payload = [], ?Run $run = null, float $confidence = 0.75): FeedbackReport
    {
        $dedupe = sha1($type.'|'.$summary.'|'.($run?->getKey() ?? 'global'));

        $report = FeedbackReport::query()->firstOrCreate(
            ['dedupe_key' => $dedupe, 'run_id' => $run?->getKey()],
            [
                'report_type' => $type,
                'summary' => $summary,
                'evidence' => $payload,
                'confidence' => $confidence,
                'status' => 'open',
            ],
        );

        if ($run !== null) {
            $this->maybeCreateLearningEvent($run, $report);
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordFromReaction(Run $run, string $reactionKey, array $payload): FeedbackReport
    {
        $type = match ($reactionKey) {
            'ci_failed' => 'ci_failure',
            'changes_requested' => 'review_feedback',
            'merge_conflict' => 'merge_conflict',
            default => 'scm_reaction',
        };

        return $this->record(
            $type,
            'SCM reaction '.$reactionKey.' for run '.$run->getKey(),
            ['reaction' => $reactionKey, 'payload' => $payload],
            $run,
            0.85,
        );
    }

    public function verify(FeedbackReport $report, bool $passed, ?string $verificationOutput = null): FeedbackReport
    {
        $report->update([
            'verified' => $passed,
            'verified_at' => now(),
            'evidence' => array_merge(is_array($report->evidence) ? $report->evidence : [], [
                'verification_output' => $verificationOutput,
            ]),
        ]);

        if ($passed && $report->run_id !== null) {
            $event = LearningEvent::query()
                ->where('run_id', $report->run_id)
                ->where('status', 'pending')
                ->latest()
                ->first();
            if ($event !== null) {
                $this->promoter->promote($event, 'verified_feedback');
            }
        }

        return $report->refresh();
    }

    protected function maybeCreateLearningEvent(Run $run, FeedbackReport $report): void
    {
        $event = LearningEvent::create([
            'run_id' => $run->getKey(),
            'type' => match ($report->report_type) {
                'bug_report', 'ci_failure' => 'failure',
                'improvement_suggestion' => 'pattern',
                default => 'pattern',
            },
            'content' => Str::limit($report->summary, 4000),
            'confidence' => (float) $report->confidence,
            'evidence' => array_merge(is_array($report->evidence) ? $report->evidence : [], [
                'feedback_report_id' => $report->getKey(),
            ]),
            'status' => 'pending',
        ]);

        if (! (bool) config('bossku.learning_require_verification', true)) {
            $this->promoter->promote($event, 'auto');
        }
    }
}
