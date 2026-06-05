<?php

namespace App\Services\BosskuAi;

use Illuminate\Support\Str;

class LlmErrorFormatter
{
    public static function humanize(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return 'LLM request failed.';
        }

        if (preg_match('/"error"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $trimmed, $matches) === 1) {
            $decoded = stripcslashes($matches[1]);
            if ($decoded !== '') {
                return $decoded;
            }
        }

        if (str_contains($trimmed, 'requires a subscription')) {
            return 'Ollama Cloud: this model requires a subscription. Upgrade at https://ollama.com/upgrade or point OLLAMA_BASE_URL to a local Ollama instance with free models (e.g. llama3.2).';
        }

        if (str_contains($trimmed, 'All models failed')) {
            return self::humanize((string) preg_replace('/^All models failed for role \w+:\s*/', '', $trimmed));
        }

        return Str::limit($trimmed, 500);
    }
}
