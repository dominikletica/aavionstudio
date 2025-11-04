<?php

declare(strict_types=1);

namespace App\Translation;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DebugTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    public function __construct(
        private readonly TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $inner,
        private readonly RequestStack $requestStack,
        private readonly CatalogueManager $catalogueManager,
    ) {
    }

    public function trans(
        string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string {
        $targetLocale = $locale ?? $this->getLocale();
        $this->catalogueManager->ensureLocale($targetLocale);

        $fallbackLocale = $this->catalogueManager->getFallbackLocale();
        if ($fallbackLocale !== $targetLocale) {
            $this->catalogueManager->ensureLocale($fallbackLocale);
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $request->attributes->get('_translation_debug_keys', false)) {
            return $id;
        }

        return $this->inner->trans($id, $parameters, $domain, $locale);
    }

    public function setLocale($locale): void
    {
        $this->inner->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->inner->getLocale();
    }

    public function getCatalogue(string $locale = null): MessageCatalogueInterface
    {
        return $this->inner->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        return $this->inner->getCatalogues();
    }
}
