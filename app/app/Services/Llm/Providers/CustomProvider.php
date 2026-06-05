<?php

namespace App\Services\Llm\Providers;

class CustomProvider extends OpenAiCompatibleProvider
{
    public function getSlug(): string
    {
        return 'custom';
    }
}
