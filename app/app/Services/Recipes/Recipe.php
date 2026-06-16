<?php

namespace App\Services\Recipes;

/**
 * A parameterized, shareable workflow template (goose recipe parity, BosskuAI
 * flavored). File-first under `recipes/`; renders into a prompt that runs
 * through the orchestrator/kernel with the named workflow.
 */
final class Recipe
{
    /**
     * @param  list<RecipeParameter>  $parameters
     * @param  list<string>  $skills
     * @param  array<string, mixed>|null  $responseSchema
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $version,
        public readonly string $title,
        public readonly string $description,
        public readonly string $prompt,
        public readonly array $parameters = [],
        public readonly ?string $workflow = null,
        public readonly array $skills = [],
        public readonly ?array $responseSchema = null,
        public readonly ?string $instructions = null,
        public readonly ?string $dir = null,
    ) {}

    /**
     * @param  array<string, mixed>  $a  parsed recipe (yaml/json)
     */
    public static function fromArray(string $slug, array $a, ?string $dir = null): self
    {
        $params = [];
        foreach ((array) ($a['parameters'] ?? []) as $p) {
            if (is_array($p)) {
                $params[] = RecipeParameter::fromArray($p);
            }
        }

        return new self(
            slug: $slug,
            version: (string) ($a['version'] ?? '1.0.0'),
            title: (string) ($a['title'] ?? $slug),
            description: (string) ($a['description'] ?? ''),
            prompt: (string) ($a['prompt'] ?? $a['instructions'] ?? ''),
            parameters: $params,
            workflow: isset($a['workflow']) ? (string) $a['workflow'] : null,
            skills: array_values(array_map('strval', (array) ($a['skills'] ?? []))),
            responseSchema: is_array($a['response_schema'] ?? null) ? $a['response_schema'] : null,
            instructions: isset($a['instructions']) ? (string) $a['instructions'] : null,
            dir: $dir,
        );
    }

    /**
     * Validate supplied values against the parameter schema.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>  error messages (empty = valid)
     */
    public function validate(array $values): array
    {
        $errors = [];
        foreach ($this->parameters as $param) {
            $error = $param->validate($values[$param->key] ?? null);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Render the prompt with `{{ key }}` substitution. Missing values fall back
     * to declared defaults; `{{recipe_dir}}` resolves to the recipe directory.
     *
     * @param  array<string, mixed>  $values
     */
    public function render(array $values): string
    {
        $resolved = [];
        foreach ($this->parameters as $param) {
            $resolved[$param->key] = (string) ($param->cast($values[$param->key] ?? null) ?? '');
        }
        $resolved['recipe_dir'] = $this->dir ?? '';

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static fn (array $m): string => $resolved[$m[1]] ?? $m[0],
            $this->prompt,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'workflow' => $this->workflow,
            'skills' => $this->skills,
            'parameters' => array_map(fn (RecipeParameter $p): array => $p->toArray(), $this->parameters),
            'response_schema' => $this->responseSchema,
        ];
    }
}
