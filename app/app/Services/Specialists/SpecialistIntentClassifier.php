<?php

namespace App\Services\Specialists;

class SpecialistIntentClassifier
{
    public function classify(string $prompt, array $modelRoute = []): SpecialistIntent
    {
        $lower = mb_strtolower(trim($prompt));
        $skill = mb_strtolower((string) ($modelRoute['skill'] ?? ''));

        if ($skill === 'seo' || preg_match('/\b(seo|search engine|keyword|meta description|serp|organic traffic)\b/u', $lower)) {
            return SpecialistIntent::Seo;
        }
        if (preg_match('/\b(sales pitch|cold outreach|cold sales|sales email|sales proposal|objection|pipeline messaging|outbound email|conversion copy)\b/u', $lower)) {
            return SpecialistIntent::Sales;
        }
        if ($skill === 'marketing' || preg_match('/\b(marketing|campaign|positioning|brand voice|launch plan|growth strategy|social media)\b/u', $lower)) {
            return SpecialistIntent::Marketing;
        }
        if ($skill === 'uiux' || preg_match('/\b(ui\/ux|user experience|usability|wireframe|design critique|visual hierarchy)\b/u', $lower)) {
            return SpecialistIntent::UiUx;
        }
        if (preg_match('/\b(blog|article|newsletter|editorial|long-form)\b/u', $lower)) {
            return SpecialistIntent::Blog;
        }
        if (preg_match('/\b(qa|regression|acceptance criteria|test plan)\b/u', $lower)) {
            return SpecialistIntent::Qa;
        }
        if (($modelRoute['needs_security_auditor'] ?? false) || preg_match('/\b(security audit|owasp|vulnerability|penetration)\b/u', $lower)) {
            return SpecialistIntent::Security;
        }
        if (preg_match('/\b(support|customer|faq|help docs|user confusion)\b/u', $lower)) {
            return SpecialistIntent::Support;
        }
        if (preg_match('/\b(milestone|kanban|roadmap|scope|delivery plan)\b/u', $lower)) {
            return SpecialistIntent::ProjectManagement;
        }
        if (preg_match('/\b(architecture|implement|refactor|backend|frontend|codebase|bug)\b/u', $lower)) {
            return SpecialistIntent::Engineering;
        }

        return SpecialistIntent::Generic;
    }
}
