<?php

namespace App\Services\Attachments;

use App\Models\BosskuAi\Attachment;
use Illuminate\Support\Collection;

class AttachmentRunContextService
{
    /**
     * @param  list<string>  $attachmentIds
     */
    public function buildContextBlock(array $attachmentIds): string
    {
        $attachments = $this->loadAttachments($attachmentIds);
        if ($attachments->isEmpty()) {
            return '';
        }

        $sections = [];
        foreach ($attachments as $attachment) {
            $body = trim((string) ($attachment->extracted_text ?? ''));
            if ($body === '') {
                $body = $attachment->preview(500);
            }

            $sections[] = "--- Attached file: {$attachment->original_name} ({$attachment->kind}, ".number_format((int) $attachment->size)." bytes) ---\n{$body}";
        }

        return "Attached files:\n\n".implode("\n\n", $sections)."\n\n--- End attached files ---\n\n";
    }

    /**
     * @param  list<string>  $attachmentIds
     */
    public function linkToRun(array $attachmentIds, string $runId): void
    {
        if ($attachmentIds === []) {
            return;
        }

        Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->whereNull('run_id')
            ->update(['run_id' => $runId]);
    }

    /**
     * @param  list<string>  $attachmentIds
     * @return Collection<int, Attachment>
     */
    public function loadAttachments(array $attachmentIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $attachmentIds))));
        if ($ids === []) {
            return collect();
        }

        $max = max(1, (int) config('bossku.attachments.max_per_run', 10));
        $ids = array_slice($ids, 0, $max);

        return Attachment::query()
            ->whereIn('id', $ids)
            ->orderBy('created_at')
            ->get();
    }

    public function prependToPrompt(string $prompt, array $attachmentIds): string
    {
        $block = $this->buildContextBlock($attachmentIds);
        if ($block === '') {
            return $prompt;
        }

        return $block.$prompt;
    }
}
