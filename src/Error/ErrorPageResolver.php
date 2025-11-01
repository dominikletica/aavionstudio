<?php

declare(strict_types=1);

namespace App\Error;

use Twig\Environment;

final class ErrorPageResolver
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $projectSettings
     */
    public function resolve(array $projectSettings, int $statusCode): ?string
    {
        $candidates = [];

        $settings = $projectSettings['errors'] ?? [];
        $code = (string) $statusCode;

        if (is_array($settings) && isset($settings[$code]) && is_string($settings[$code])) {
            $candidates = array_merge($candidates, $this->candidateTemplates($settings[$code]));
        }

        $candidates[] = sprintf('pages/error/%d.html.twig', $statusCode);

        foreach ($candidates as $template) {
            if ($this->twig->getLoader()->exists($template)) {
                return $template;
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
        $candidates = [];

        if (str_ends_with($normalized, '.html.twig')) {
            $candidates[] = $normalized;
            $candidates[] = sprintf('pages/%s', $normalized);
        } else {
            $candidates[] = $normalized.'.html.twig';
            $candidates[] = sprintf('%s.html.twig', $normalized);
            $candidates[] = sprintf('pages/%s.html.twig', $normalized);
            $candidates[] = sprintf('pages/error/%s.html.twig', $normalized);
        }

        return array_values(array_unique($candidates));
    }
}
