<?php

declare(strict_types=1);

namespace App\Error;

use App\Settings\SystemSettings;
use Twig\Environment;

final class ErrorPageResolver
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SystemSettings $systemSettings,
    ) {
    }

    /**
     * @param array<string, mixed>|null $project
     */
    public function resolve(?array $project, int $statusCode): string
    {
        $projectSettings = \is_array($project['settings'] ?? null) ? $project['settings'] : [];
        $projectErrors = $this->normalizeMappings($projectSettings['errors'] ?? []);
        $systemErrors = $this->normalizeMappings($this->systemSettings->get('core.errors', []));

        $code = (string) $statusCode;

        $entry = $projectErrors[$code]
            ?? $projectErrors['default']
            ?? $systemErrors[$code]
            ?? $systemErrors['default']
            ?? ['mode' => 'default'];

        $mode = \is_string($entry['mode'] ?? null) ? strtolower($entry['mode']) : 'default';

        if ($mode === 'template') {
            $template = \is_string($entry['template'] ?? null) ? $entry['template'] : '';
            $resolved = $this-> locateTemplate($template);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($mode === 'entity') {
            // Entity mode depends on snapshot infrastructure; fall back gracefully for now.
            if (isset($entry['template']) && \is_string($entry['template'])) {
                $fallback = $this->locateTemplate($entry['template']);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
        }

        return $this->defaultTemplate($statusCode);
    }

    /**
     * @param mixed $raw
     * @return array<string, array<string, mixed>>
     */
    private function normalizeMappings(mixed $raw): array
    {
        $normalized = [];

        if (!\is_array($raw)) {
            return $normalized;
        }

        foreach ($raw as $code => $value) {
            $key = \is_string($code) ? $code : (string) $code;

            if (\is_string($value)) {
                $normalized[$key] = [
                    'mode' => 'template',
                    'template' => $value,
                ];
                continue;
            }

            if (\is_array($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function locateTemplate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach ($this->candidateTemplates($value) as $candidate) {
            if ($this->twig->getLoader()->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateTemplates(string $value): array
    {
        $normalized = ltrim($value, '/');
        $paths = [];

        if (str_ends_with($normalized, '.html.twig')) {
            $paths[] = $normalized;
            $paths[] = sprintf('pages/%s', $normalized);
        } else {
            $paths[] = $normalized.'.html.twig';
            $paths[] = sprintf('%s.html.twig', $normalized);
            $paths[] = sprintf('pages/%s.html.twig', $normalized);
            $paths[] = sprintf('pages/error/%s.html.twig', $normalized);
        }

        $paths[] = sprintf('pages/error/%s', $normalized);

        return array_values(array_unique($paths));
    }

    private function defaultTemplate(int $statusCode): string
    {
        $candidates = [
            sprintf('pages/error/%d.html.twig', $statusCode),
            'pages/error/default.html.twig',
        ];

        foreach ($candidates as $candidate) {
            if ($this->twig->getLoader()->exists($candidate)) {
                return $candidate;
            }
        }

        return 'pages/error/default.html.twig';
    }
}
