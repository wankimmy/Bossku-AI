<?php

namespace App\Services\Attachments;

use App\Models\BosskuAi\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;

class AttachmentIngestionService
{
    public function __construct(
        protected VisionService $vision,
    ) {}

    /**
     * @return array{
     *   kind: string,
     *   storage_path: string,
     *   extracted_text: string|null
     * }
     */
    public function ingestUploadedFile(UploadedFile $file, ?string $userHint = null): array
    {
        $id = (string) Str::uuid();
        $safeName = $this->sanitizeFilename($file->getClientOriginalName() ?: 'attachment');

        $disk = Storage::disk('local');
        $stored = $disk->putFileAs('bossku-attachments/'.$id, $file, $safeName);
        if ($stored === false) {
            throw new \RuntimeException('Failed to store attachment.');
        }

        $relativePath = $stored;
        $absolutePath = $disk->path($relativePath);
        $mime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
        $kind = $this->detectKind($mime, $safeName);

        $extracted = match ($kind) {
            Attachment::KIND_TEXT => $this->extractTextFile($absolutePath),
            Attachment::KIND_PDF => $this->extractPdf($absolutePath),
            Attachment::KIND_IMAGE => $this->vision->describeImage($absolutePath, $mime, $userHint),
            default => '[Binary file attached — content not extracted as text]',
        };

        return [
            'kind' => $kind,
            'storage_path' => $relativePath,
            'extracted_text' => $this->capExtractedText($extracted),
        ];
    }

    public function capExtractedText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $max = max(1000, (int) config('bossku.attachments.max_extracted_chars', 40000));
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) <= $max) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, $max)."\n\n[… truncated at {$max} characters]";
    }

    public function detectKind(string $mime, string $filename): string
    {
        if (str_starts_with($mime, 'image/')) {
            return Attachment::KIND_IMAGE;
        }

        if ($mime === 'application/pdf' || str_ends_with(strtolower($filename), '.pdf')) {
            return Attachment::KIND_PDF;
        }

        if ($this->isTextMime($mime) || $this->isTextExtension($filename)) {
            return Attachment::KIND_TEXT;
        }

        return Attachment::KIND_OTHER;
    }

    protected function extractTextFile(string $absolutePath): string
    {
        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            return '[Could not read text file]';
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if (is_string($converted)) {
                $raw = $converted;
            }
        }

        return $raw;
    }

    protected function extractPdf(string $absolutePath): string
    {
        if (! class_exists(PdfParser::class)) {
            return '[PDF attached — install smalot/pdfparser (composer require smalot/pdfparser)]';
        }

        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($absolutePath);
            $text = trim($pdf->getText());

            return $text !== '' ? $text : '[PDF attached — no extractable text found]';
        }
        catch (\Throwable $e) {
            return '[PDF attached — extraction failed: '.$e->getMessage().']';
        }
    }

    protected function isTextMime(string $mime): bool
    {
        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        return in_array($mime, [
            'application/json',
            'application/xml',
            'application/javascript',
            'application/x-yaml',
            'application/yaml',
            'application/sql',
            'application/x-httpd-php',
            'application/typescript',
            'application/vnd.ms-excel',
            'application/csv',
        ], true);
    }

    protected function isTextExtension(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, [
            'txt', 'md', 'markdown', 'json', 'csv', 'tsv', 'xml', 'yaml', 'yml',
            'log', 'ini', 'env', 'sql', 'html', 'htm', 'css', 'scss', 'sass',
            'js', 'jsx', 'ts', 'tsx', 'vue', 'php', 'py', 'rb', 'go', 'rs',
            'java', 'kt', 'swift', 'c', 'h', 'cpp', 'hpp', 'cs', 'sh', 'bash',
            'zsh', 'ps1', 'bat', 'dockerfile', 'gitignore', 'gitattributes',
            'toml', 'lock', 'gradle', 'properties',
        ], true);
    }

    protected function sanitizeFilename(string $name): string
    {
        $base = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $name));
        $base = preg_replace('/[^\w.\-()+ ]+/u', '_', $base) ?? 'attachment';
        $base = trim($base, '. ');

        return $base !== '' ? $base : 'attachment';
    }
}
