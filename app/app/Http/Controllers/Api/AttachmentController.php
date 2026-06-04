<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Attachment;
use App\Services\Attachments\AttachmentIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentIngestionService $ingestion,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $maxFiles = max(1, (int) config('bossku.attachments.max_per_upload', 10));
        $maxKb = max(1, (int) config('bossku.attachments.max_file_kb', 10240));

        $validated = $request->validate([
            'files' => 'required|array|min:1|max:'.$maxFiles,
            'files.*' => 'required|file|max:'.$maxKb,
            'hint' => 'nullable|string|max:2000',
        ]);

        /** @var list<\Illuminate\Http\UploadedFile> $files */
        $files = $validated['files'];
        $hint = isset($validated['hint']) ? (string) $validated['hint'] : null;

        $results = [];
        foreach ($files as $file) {
            $mime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
            if (! $this->mimeAllowed($mime, $file->getClientOriginalName() ?: '')) {
                return response()->json([
                    'message' => 'File type not allowed: '.$mime,
                    'file' => $file->getClientOriginalName(),
                ], 422);
            }

            $ingested = $this->ingestion->ingestUploadedFile($file, $hint);

            $attachment = Attachment::query()->create([
                'id' => (string) Str::uuid(),
                'original_name' => $file->getClientOriginalName() ?: 'attachment',
                'mime' => $mime,
                'size' => (int) $file->getSize(),
                'kind' => $ingested['kind'],
                'storage_path' => $ingested['storage_path'],
                'extracted_text' => $ingested['extracted_text'],
            ]);

            $results[] = [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'kind' => $attachment->kind,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
                'preview' => $attachment->preview(),
            ];
        }

        return response()->json(['attachments' => $results]);
    }

    public function destroy(string $id): JsonResponse
    {
        $attachment = Attachment::query()->find($id);
        if ($attachment === null) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        if ($attachment->run_id !== null) {
            return response()->json([
                'message' => 'Cannot delete attachment linked to a run.',
            ], 409);
        }

        $disk = Storage::disk('local');
        if ($attachment->storage_path !== '' && $disk->exists($attachment->storage_path)) {
            $disk->delete($attachment->storage_path);
            $dir = dirname($attachment->storage_path);
            if ($dir !== '.' && $dir !== '' && count($disk->files($dir)) === 0) {
                $disk->deleteDirectory($dir);
            }
        }

        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    protected function mimeAllowed(string $mime, string $filename): bool
    {
        /** @var list<string> $allowed */
        $allowed = config('bossku.attachments.allowed_mimes', []);
        if ($allowed === []) {
            return true;
        }

        if (in_array($mime, $allowed, true)) {
            return true;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $extMap = [
            'md' => 'text/markdown',
            'yml' => 'text/yaml',
            'yaml' => 'text/yaml',
        ];
        if (isset($extMap[$ext]) && in_array($extMap[$ext], $allowed, true)) {
            return true;
        }

        // Allow generic octet-stream when extension looks like text/code
        if ($mime === 'application/octet-stream') {
            $kinds = ['txt', 'md', 'json', 'csv', 'log', 'sql', 'env'];
            if (in_array($ext, $kinds, true)) {
                return true;
            }
        }

        return false;
    }
}
