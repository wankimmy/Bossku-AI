<?php

namespace App\Services\Skills;

class SkillValidator
{
    public function validate(string $content): array
    {
        $errors = [];

        if (blank($content)) {
            $errors[] = 'Content is empty.';

            return ['valid' => false, 'errors' => $errors];
        }

        if (! preg_match('/^# .+/m', $content)) {
            $errors[] = 'Missing top-level heading (# Heading).';
        }

        if (! preg_match('/^## Description/m', $content)) {
            $errors[] = 'Missing ## Description section.';
        }

        if (! preg_match('/^## (Rules|Instructions)/m', $content)) {
            $errors[] = 'Missing ## Rules or ## Instructions section.';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
}
