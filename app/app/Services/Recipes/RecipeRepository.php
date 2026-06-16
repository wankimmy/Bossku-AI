<?php

namespace App\Services\Recipes;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads file-first recipes from `recipes/` (repo root) and `app/recipes/`.
 * Recipes are YAML or JSON, so they travel with the repo and are readable by
 * any tool (Claude Code, Codex, Cursor) — the Laravel runtime just executes them.
 */
final class RecipeRepository
{
    /** @var array<string, Recipe>|null */
    private ?array $cache = null;

    /** @return list<string> directories searched for recipes */
    public function directories(): array
    {
        $repoRoot = (string) config('bossku.repo_root', dirname(base_path()));

        return array_values(array_unique(array_filter([
            $repoRoot.'/recipes',
            base_path('recipes'),
        ], 'is_dir')));
    }

    /** @return list<Recipe> */
    public function all(): array
    {
        return array_values($this->load());
    }

    public function find(string $slug): ?Recipe
    {
        return $this->load()[$slug] ?? null;
    }

    /** @return array<string, Recipe> */
    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $recipes = [];
        foreach ($this->directories() as $dir) {
            foreach (glob($dir.'/*.{yaml,yml,json}', GLOB_BRACE) ?: [] as $file) {
                $slug = pathinfo($file, PATHINFO_FILENAME);
                if (isset($recipes[$slug])) {
                    continue; // first directory wins
                }
                $parsed = $this->parse($file);
                if ($parsed !== null) {
                    $recipes[$slug] = Recipe::fromArray($slug, $parsed, $dir);
                }
            }
        }

        return $this->cache = $recipes;
    }

    /** @return array<string, mixed>|null */
    private function parse(string $file): ?array
    {
        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        try {
            $data = str_ends_with($file, '.json')
                ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR)
                : Yaml::parse($contents);
        } catch (\Throwable) {
            return null; // ponytail: skip an unparseable recipe rather than crash the list
        }

        return is_array($data) ? $data : null;
    }
}
