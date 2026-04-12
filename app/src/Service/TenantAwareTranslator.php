<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Decorator del Translator de Symfony que antepone las traducciones
 * específicas del tenant activo a las globales.
 *
 * Flujo por request:
 *  1. TenantTranslationListener (priority 25) fija _tenant_translation_path en el request.
 *  2. Al primer trans(), este decorator carga los archivos YAML de esa carpeta.
 *  3. Si la clave existe en el tenant → la devuelve; si no → delega al translator original.
 *
 * Estructura de archivos esperada:
 *   translations/{slug}/messages.es.yaml
 *   translations/{slug}/validators.es.yaml
 *   ...
 */
final class TenantAwareTranslator implements TranslatorInterface, LocaleAwareInterface, TranslatorBagInterface
{
    /** @var array<string, array<string, array<string, string>>> [slug => [domain+locale => [id => text]]] */
    private array $cache = [];

    /** Slug cargado en el request actual (se limpia entre requests por ser service shared=false) */
    private ?string $loadedSlug = null;

    public function __construct(
        private readonly TranslatorInterface $inner,
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
    ) {}

    // ─── TranslatorInterface ─────────────────────────────────────────────────

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $locale ??= $this->getLocale();
        $domain ??= 'messages';

        $override = $this->lookupTenantTranslation($id, $domain, $locale);
        if ($override !== null) {
            return $parameters !== [] ? strtr($override, $parameters) : $override;
        }

        return $this->inner->trans($id, $parameters, $domain, $locale);
    }

    // ─── LocaleAwareInterface ────────────────────────────────────────────────

    public function setLocale(string $locale): void
    {
        if ($this->inner instanceof LocaleAwareInterface) {
            $this->inner->setLocale($locale);
        }
    }

    public function getLocale(): string
    {
        return $this->inner instanceof LocaleAwareInterface
            ? $this->inner->getLocale()
            : 'es';
    }

    // ─── TranslatorBagInterface ──────────────────────────────────────────────

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        /** @var TranslatorBagInterface $inner */
        return $this->inner->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        /** @var TranslatorBagInterface $inner */
        return $this->inner->getCatalogues();
    }

    // ─── Tenant lookup ───────────────────────────────────────────────────────

    private function lookupTenantTranslation(string $id, string $domain, string $locale): ?string
    {
        $slug = $this->resolveCurrentSlug();
        if ($slug === null) {
            return null;
        }

        $this->loadTenantMessages($slug, $domain, $locale);

        return $this->cache[$slug][$domain . '.' . $locale][$id] ?? null;
    }

    private function resolveCurrentSlug(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $path = $request->attributes->get('_tenant_translation_path');
        if (!is_string($path) || $path === $this->projectDir . '/translations') {
            // Sin path específico de tenant → no hay override
            return null;
        }

        return $request->attributes->get('_tenant_slug');
    }

    private function loadTenantMessages(string $slug, string $domain, string $locale): void
    {
        $cacheKey = $domain . '.' . $locale;

        if (isset($this->cache[$slug][$cacheKey])) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $path    = $request?->attributes->get('_tenant_translation_path');

        if (!is_string($path) || !is_dir($path)) {
            $this->cache[$slug][$cacheKey] = [];
            return;
        }

        $file = $path . '/' . $domain . '.' . $locale . '.yaml';

        if (!file_exists($file)) {
            $this->cache[$slug][$cacheKey] = [];
            return;
        }

        $parsed = Yaml::parseFile($file);
        $this->cache[$slug][$cacheKey] = is_array($parsed)
            ? $this->flatten($parsed)
            : [];
    }

    /**
     * Aplana un array YAML anidado usando notación punto.
     * ['menu' => ['pacientes' => 'Pacientes']] → ['menu.pacientes' => 'Pacientes']
     *
     * @param array<string, mixed> $array
     * @return array<string, string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $key : (string) $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey);
            } else {
                $result[$fullKey] = (string) $value;
            }
        }

        return $result;
    }
}
