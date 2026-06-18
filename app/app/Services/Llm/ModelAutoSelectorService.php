<?php

namespace App\Services\Llm;

class ModelAutoSelectorService
{
  public function __construct(
    protected ProviderFactory $factory,
  ) {}

  /**
   * @return list<array{id: string, label: string, provider: string, score: float, auto_selected: bool}>
   */
  public function recommendForRole(string $role, ?string $providerSlug = null, int $limit = 3): array
  {
    $role = RoleAliasHelper::normalize($role);
    $profile = config("bossku_inference_catalog.role_profiles.{$role}", [
      'reasoning' => 0.33, 'coding' => 0.33, 'speed' => 0.17, 'cost' => 0.17,
    ]);

    $models = config('bossku_inference_catalog.models', []);
    $scored = [];

    foreach ($models as $model) {
      if (($model['available'] ?? true) === false) {
        continue;
      }

      $modelProvider = (string) ($model['provider'] ?? '');
      if ($providerSlug !== null && $modelProvider !== $providerSlug) {
        continue;
      }

      if (! $this->factory->isProviderConfigured($modelProvider)) {
        continue;
      }

      $roles = $model['roles'] ?? [];
      $roleVariants = RoleAliasHelper::variants($role);
      $roleMatch = count(array_intersect($roles, $roleVariants)) > 0;

      $score = $this->scoreModel($model, $profile, $roleMatch);

      $scored[] = [
        'id' => (string) $model['id'],
        'label' => (string) ($model['label'] ?? $model['id']),
        'provider' => $modelProvider,
        'score' => $score,
        'auto_selected' => false,
      ];
    }

    usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

    $result = array_slice($scored, 0, $limit);

    if ($result !== []) {
      $result[0]['auto_selected'] = true;
    }

    return $result;
  }

  /**
   * @return list<array{provider: string, name: string, auth: string, configured: bool, recommended_models: list<array{id: string, label: string, score: float, auto_selected: bool}>}>
   */
  public function cloudProvidersForRole(string $role, int $modelsPerProvider = 3): array
  {
    $providers = config('bossku_inference_catalog.providers', []);
    $result = [];

    foreach ($providers as $slug => $meta) {
      $configured = $this->factory->isProviderConfigured($slug);
      $recommended = $configured
        ? $this->recommendForRole($role, $slug, $modelsPerProvider)
        : [];

      $result[] = [
        'provider' => $slug,
        'name' => (string) ($meta['name'] ?? $slug),
        'auth' => (string) ($meta['auth'] ?? 'api_key'),
        'configured' => $configured,
        'recommended_models' => $recommended,
      ];
    }

    return $result;
  }

  public function autoSelectModel(string $role, string $providerSlug): ?string
  {
    $recommendations = $this->recommendForRole($role, $providerSlug, 1);

    return $recommendations[0]['id'] ?? null;
  }

  /**
   * @param  array<string, float>  $profile
   */
  protected function scoreModel(array $model, array $profile, bool $roleMatch): float
  {
    $reasoning = (float) ($model['reasoning'] ?? 0);
    $coding = (float) ($model['coding'] ?? 0);
    $speed = (float) ($model['speed'] ?? 0);
    $cost = (float) ($model['cost'] ?? 0);

    $base = (
      $reasoning * ($profile['reasoning'] ?? 0.25)
      + $coding * ($profile['coding'] ?? 0.25)
      + $speed * ($profile['speed'] ?? 0.25)
      + $cost * ($profile['cost'] ?? 0.25)
    );

    if ($roleMatch) {
      $base *= 1.15;
    }

    return round($base, 2);
  }
}
