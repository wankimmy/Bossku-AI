<?php

namespace App\Services\BosskuAi\Learning;

/**
 * An atomic learned behavior with confidence scoring. Ported from ECC's
 * continuous-learning v2.1 instinct system. An instinct is a single observed
 * pattern (e.g. "this project uses tabs", "Pest tests run with vendor/bin/pest")
 * that an agent captured during a session. It is project-scoped (git-remote
 * hash) so patterns from one project don't contaminate another.
 *
 * Confidence starts at 0.3 (single observation) and rises with repeated
 * sightings. When an instinct is seen in 2+ projects with confidence >=0.8,
 * it is eligible for promotion to a global skill/rule (the evolve pipeline).
 *
 * Instincts are the structured successor to BosskuAI's existing
 * LearningEvent/LearningEngine — they add: atomicity, confidence scoring,
 * project-scoping, and the promotion pipeline.
 */
final class Instinct
{
    /**
     * Minimum confidence for a single observation.
     * Maximum before forced promotion consideration.
     */
    public const MIN_CONFIDENCE = 0.3;

    public const MAX_CONFIDENCE = 0.9;

    public const PROMOTION_CONFIDENCE_THRESHOLD = 0.8;

    public const PROMOTION_PROJECT_THRESHOLD = 2;

    /**
     * @param  string  $id  unique identifier (hash of content + project)
     * @param  string  $content  the observed behavior, one sentence
     * @param  string  $domain  tag: 'convention', 'testing', 'workflow', 'security', 'architecture'
     * @param  string  $scope  'project' (git-remote hash) or 'global'
     * @param  float  $confidence  0.3-0.9, rises with repeated sightings
     * @param  list<string>  $evidence  file paths or commands that produced this observation
     * @param  int  $sightings  how many times this pattern was observed
     * @param  list<string>  $projectIds  which project hashes have seen this
     * @param  ?string  $createdAt  ISO timestamp
     * @param  ?string  $updatedAt  ISO timestamp
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly string $domain,
        public readonly string $scope,
        public float $confidence,
        public array $evidence = [],
        public int $sightings = 1,
        public array $projectIds = [],
        public readonly ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
        if ($confidence < self::MIN_CONFIDENCE) {
            $confidence = self::MIN_CONFIDENCE;
        }
        if ($confidence > self::MAX_CONFIDENCE) {
            $confidence = self::MAX_CONFIDENCE;
        }
        $this->confidence = $confidence;
    }

    /**
     * Record a new sighting of this instinct. Raises confidence and adds the
     * project to the seen-projects list. Confidence rises logarithmically
     * with sightings to avoid runaway certainty.
     *
     * @param  string  $projectId  the git-remote hash of the project that observed it
     * @param  ?string  $evidence  optional new evidence (file path or command)
     */
    public function observe(string $projectId, ?string $evidence = null): self
    {
        $this->sightings++;
        if (! in_array($projectId, $this->projectIds, true)) {
            $this->projectIds[] = $projectId;
        }
        if ($evidence !== null && ! in_array($evidence, $this->evidence, true)) {
            $this->evidence[] = $evidence;
        }

        // Confidence rises with sightings, logarithmic, capped at MAX.
        $this->confidence = min(
            self::MAX_CONFIDENCE,
            self::MIN_CONFIDENCE + (log($this->sightings, 2) * 0.15),
        );
        $this->updatedAt = now()->toIso8601String();

        return $this;
    }

    /**
     * Is this instinct eligible for promotion to a global skill/rule?
     * Criteria: seen in >=2 projects AND confidence >=0.8.
     */
    public function isEligibleForPromotion(): bool
    {
        return count($this->projectIds) >= self::PROMOTION_PROJECT_THRESHOLD
            && $this->confidence >= self::PROMOTION_CONFIDENCE_THRESHOLD;
    }

    /**
     * Promote this instinct to global scope. Returns a new Instinct with
     * scope='global' and the merged project list.
     */
    public function promote(): self
    {
        return new self(
            id: $this->id,
            content: $this->content,
            domain: $this->domain,
            scope: 'global',
            confidence: $this->confidence,
            evidence: $this->evidence,
            sightings: $this->sightings,
            projectIds: $this->projectIds,
            createdAt: $this->createdAt,
            updatedAt: now()->toIso8601String(),
        );
    }

    /** Generate a stable id from content + scope. */
    public static function idFor(string $content, string $scope): string
    {
        return md5($content.'|'.$scope);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'domain' => $this->domain,
            'scope' => $this->scope,
            'confidence' => round($this->confidence, 3),
            'evidence' => $this->evidence,
            'sightings' => $this->sightings,
            'project_ids' => $this->projectIds,
            'eligible_for_promotion' => $this->isEligibleForPromotion(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}