<?php

namespace App\Services\Attachments;

use App\Services\BosskuAi\LlmGateway;
use App\Services\Llm\OllamaClient;
use Illuminate\Support\Facades\Log;

class VisionService
{
    public function __construct(
        protected OllamaClient $ollama,
        protected LlmGateway $llmGateway,
    ) {}

    /**
     * Describe an image file for text-only LLM pipelines.
     */
    public function describeImage(string $absolutePath, string $mime, ?string $userHint = null): string
    {
        if (! is_readable($absolutePath)) {
            return '[Image attached but file is not readable on server]';
        }

        $bytes = file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            return '[Image attached but could not be read]';
        }

        $model = (string) config('bossku.vision_model', 'llava');
        try {
            $resolved = $this->llmGateway->resolveAlias($model);
        }
        catch (\Throwable) {
            $resolved = $model;
        }

        $hint = trim((string) $userHint);
        $prompt = $hint !== ''
            ? "The user attached this image with the message: {$hint}\n\nDescribe the image in detail. Include visible text (OCR), UI elements, diagrams, and anything relevant to answering their request."
            : 'Describe this image in detail for an AI assistant. Include visible text (OCR), layout, colors, UI elements, and any actionable details.';

        try {
            return trim($this->ollama->chatWithImages(
                $resolved,
                [
                    ['role' => 'user', 'content' => $prompt],
                ],
                [base64_encode($bytes)],
                0.2,
            ));
        }
        catch (\Throwable $e) {
            Log::warning('bossku.vision.describe_failed', [
                'model' => $resolved,
                'mime' => $mime,
                'error' => $e->getMessage(),
            ]);

            return '[Image attached — vision model unavailable. Configure BOSSKU_VISION_MODEL to a vision-capable Ollama model (e.g. llava). Error: '.$e->getMessage().']';
        }
    }
}
