<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasUuids;

    public const KIND_TEXT = 'text';

    public const KIND_PDF = 'pdf';

    public const KIND_IMAGE = 'image';

    public const KIND_OTHER = 'other';

    protected $table = 'bossku_ai_attachments';

    protected $fillable = [
        'run_id',
        'original_name',
        'mime',
        'size',
        'kind',
        'storage_path',
        'extracted_text',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function preview(int $maxChars = 200): string
    {
        $text = trim((string) ($this->extracted_text ?? ''));
        if ($text === '') {
            return match ($this->kind) {
                self::KIND_IMAGE => '[Image — no description available]',
                self::KIND_PDF => '[PDF — no text extracted]',
                self::KIND_OTHER => '[Binary file — not readable as text]',
                default => '[Empty attachment]',
            };
        }

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars).'…';
    }
}
