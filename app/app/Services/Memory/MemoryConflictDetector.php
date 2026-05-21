<?php

namespace App\Services\Memory;

use App\Models\BosskuAi\Memory;
use Illuminate\Support\Collection;

class MemoryConflictDetector
{
    public function detectConflicts(Memory $memory): array
    {
        $category = $memory->type ?? null;

        $candidates = Memory::where('id', '!=', $memory->getKey())
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('type', $category))
            ->get();

        $contentWords = $this->extractKeyTerms((string) $memory->content);

        $conflicting = $candidates->filter(function (Memory $other) use ($contentWords): bool {
            $otherWords = $this->extractKeyTerms((string) $other->content);

            if (empty($contentWords) || empty($otherWords)) {
                return false;
            }

            $intersection = array_intersect($contentWords, $otherWords);
            $union        = array_unique(array_merge($contentWords, $otherWords));

            if (empty($union)) {
                return false;
            }

            $similarity = count($intersection) / count($union);

            return $similarity >= 0.5;
        });

        return $conflicting->pluck('id')->values()->all();
    }

    public function saveConflicts(Memory $memory): void
    {
        $ids = $this->detectConflicts($memory);
        $memory->conflicting_memory_ids_json = $ids;
        $memory->save();
    }

    private function extractKeyTerms(string $text): array
    {
        $text  = mb_strtolower($text);
        $text  = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? $text;
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stopWords = [
            'a', 'an', 'the', 'is', 'it', 'in', 'on', 'at', 'to', 'and', 'or',
            'for', 'of', 'with', 'as', 'by', 'be', 'was', 'are', 'has', 'have',
            'this', 'that', 'from', 'not', 'but', 'so', 'if', 'do', 'did', 'will',
        ];

        $filtered = array_filter($words, fn (string $w): bool =>
            mb_strlen($w) > 2 && ! in_array($w, $stopWords, true)
        );

        return array_values(array_unique($filtered));
    }
}
