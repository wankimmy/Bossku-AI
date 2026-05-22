<?php

namespace App\Services\Orchestrator;

use App\Support\StringCoercion;

/**
 * Groups auditor findings into user-facing dimensions for full repo audits.
 */
class AuditReportFormatter
{
    /** @var array<string, string> */
    private const CATEGORY_TO_DIMENSION = [
        'functionality' => 'functionality',
        'correctness' => 'functionality',
        'design' => 'design',
        'maintainability' => 'design',
        'performance' => 'performance',
        'security' => 'security',
        'tests' => 'tests',
    ];

    /** @var list<string> */
    private const DIMENSION_ORDER = [
        'functionality',
        'design',
        'performance',
        'tests',
        'security',
    ];

    /** @var array<string, string> */
    private const DIMENSION_LABELS = [
        'functionality' => 'Functionality',
        'design' => 'Design & best practices',
        'performance' => 'Performance',
        'tests' => 'Tests',
        'security' => 'Security',
    ];

    /**
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastSecurity
     * @return list<string>
     */
    public function formatDimensionSections(array $lastAudit, ?array $lastSecurity): array
    {
        $byDimension = [];
        foreach ($this->normalizeFindings($lastAudit) as $finding) {
            $dim = $this->dimensionForCategory((string) ($finding['category'] ?? ''));
            $byDimension[$dim][] = $finding;
        }

        if (is_array($lastSecurity['security_issues'] ?? null)) {
            foreach ($lastSecurity['security_issues'] as $issue) {
                if (! is_array($issue)) {
                    continue;
                }
                $byDimension['security'][] = [
                    'severity' => StringCoercion::toString($issue['severity'] ?? null, 'medium'),
                    'title' => StringCoercion::toString($issue['title'] ?? $issue['issue'] ?? null, 'Security issue'),
                    'description' => StringCoercion::toString($issue['description'] ?? $issue['detail'] ?? null),
                ];
            }
        }

        $lines = ['', '## Audit by dimension'];
        foreach (self::DIMENSION_ORDER as $dim) {
            $items = $byDimension[$dim] ?? [];
            $label = self::DIMENSION_LABELS[$dim] ?? ucfirst($dim);
            $lines[] = '';
            $lines[] = '### '.$label;
            if ($items === []) {
                $lines[] = '- No issues recorded in this dimension (or dimension not evaluated).';

                continue;
            }
            foreach (array_slice($items, 0, 8) as $item) {
                $sev = StringCoercion::toString($item['severity'] ?? null, 'medium');
                $title = StringCoercion::toString($item['title'] ?? null, 'Finding');
                $lines[] = '- ['.$sev.'] '.$title;
            }
            if (count($items) > 8) {
                $lines[] = '- …and '.(count($items) - 8).' more';
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $lastAudit
     * @return list<array<string, mixed>>
     */
    private function normalizeFindings(array $lastAudit): array
    {
        $findings = is_array($lastAudit['findings'] ?? null) ? $lastAudit['findings'] : [];

        return array_values(array_filter($findings, 'is_array'));
    }

    private function dimensionForCategory(string $category): string
    {
        $key = strtolower(trim($category));

        return self::CATEGORY_TO_DIMENSION[$key] ?? 'functionality';
    }
}
