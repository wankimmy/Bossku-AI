<?php

namespace App\Services\Specialists;

enum SpecialistIntent: string
{
    case Generic = 'generic';
    case Seo = 'seo';
    case Marketing = 'marketing';
    case Sales = 'sales';
    case UiUx = 'ui_ux';
    case Blog = 'blog';
    case Qa = 'qa';
    case Security = 'security';
    case Support = 'support';
    case ProjectManagement = 'project_management';
    case Engineering = 'engineering';

    public function skill(): string
    {
        return match ($this) {
            self::Seo => 'seo',
            self::Marketing => 'marketing',
            self::Sales => 'sales',
            self::UiUx => 'uiux',
            self::Blog => 'documentation',
            self::Qa => 'testing',
            self::Security => 'security',
            self::Engineering => 'laravel',
            default => 'generic',
        };
    }

    /** @return list<string> */
    public function staffRoleSlugs(): array
    {
        return match ($this) {
            self::Seo => ['seo-writer', 'marketing-manager'],
            self::Marketing => ['marketing-manager', 'blog-writer'],
            self::Sales => ['sales-manager', 'marketing-manager'],
            self::UiUx => ['ui-ux-designer', 'tech-lead'],
            self::Blog => ['blog-writer', 'seo-writer'],
            self::Qa => ['qa', 'tech-lead'],
            self::Security => ['security', 'tech-lead'],
            self::Support => ['customer-support', 'project-manager'],
            self::ProjectManagement => ['project-manager', 'tech-lead'],
            self::Engineering => ['tech-lead', 'qa'],
            self::Generic => ['project-manager', 'tech-lead'],
        };
    }
}
