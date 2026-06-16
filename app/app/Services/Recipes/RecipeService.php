<?php

namespace App\Services\Recipes;

use App\Services\Orchestrator\OrchestratorService;
use RuntimeException;

/**
 * Recipe runtime: list, render, scan, and run parameterized workflow templates.
 * Running a recipe renders its prompt and dispatches it through the normal
 * orchestrator (so it inherits routing, the kernel, memory, audit) with the
 * recipe's declared workflow.
 */
final class RecipeService
{
    public function __construct(
        private readonly RecipeRepository $recipes,
        private readonly RecipeSecurityScanner $scanner,
    ) {}

    /** @return list<Recipe> */
    public function all(): array
    {
        return $this->recipes->all();
    }

    public function get(string $slug): Recipe
    {
        $recipe = $this->recipes->find($slug);
        if ($recipe === null) {
            throw new RuntimeException("Recipe '{$slug}' not found.");
        }

        return $recipe;
    }

    /**
     * Validate + render a recipe; also returns a security scan of the result.
     *
     * @param  array<string, mixed>  $values
     * @return array{recipe: string, errors: list<string>, prompt: ?string, scan: list<array<string,mixed>>, scan_severity: string}
     */
    public function preview(string $slug, array $values): array
    {
        $recipe = $this->get($slug);
        $errors = $recipe->validate($values);
        $prompt = $errors === [] ? $recipe->render($values) : null;
        $scan = $prompt !== null ? $this->scanner->scanText($prompt) : [];

        return [
            'recipe' => $recipe->slug,
            'errors' => $errors,
            'prompt' => $prompt,
            'scan' => $scan,
            'scan_severity' => $this->scanner->highestSeverity($scan),
        ];
    }

    /**
     * Render and run a recipe through the orchestrator/kernel.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function run(string $slug, array $values, OrchestratorService $orchestrator, ?callable $emit = null): array
    {
        $recipe = $this->get($slug);
        $errors = $recipe->validate($values);
        if ($errors !== []) {
            throw new RuntimeException('Invalid recipe parameters: '.implode(' ', $errors));
        }

        $prompt = $recipe->render($values);

        $options = [
            'metadata' => [
                'recipe' => $recipe->slug,
                'recipe_version' => $recipe->version,
                'recipe_workflow' => $recipe->workflow,
            ],
        ];
        if ($recipe->workflow !== null) {
            // Bias routing toward the recipe's declared workflow.
            $options['routing_prompt'] = $recipe->workflow.' '.$prompt;
        }

        return $orchestrator->run($prompt, $emit, [], $options);
    }
}
