<?php

namespace Modules\Chascarrillo\Service;

/**
 * Service to manage multi-domain logic (chascarrillo.es vs chascarrillo.com).
 * Handles region detection and SEO hreflang tags.
 */
class DomainService
{
    private const DOMAINS = [
        'es' => 'chascarrillo.es',
        'en' => 'chascarrillo.com',
    ];

    /**
     * Gets the current host.
     */
    public static function getCurrentDomain(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }

    /**
     * Gets the domain for a specific language.
     */
    public static function getTargetDomain(string $lang): ?string
    {
        return self::DOMAINS[$lang] ?? null;
    }

    /**
     * Detects if the user should be invited to switch domains based on browser language.
     * Returns suggestion data or null.
     */
    public static function getSuggestion(): ?array
    {
        // Don't suggest if user already dismissed it
        if (isset($_COOKIE['skip_domain_suggestion'])) {
            return null;
        }

        $currentDomain = self::getCurrentDomain();
        $browserLangs = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        $preferredLang = self::detectBrowserLang($browserLangs);

        if (!$preferredLang) {
            return null;
        }

        $targetDomain = self::getTargetDomain($preferredLang);

        // If we are NOT in the preferred domain, suggest switching
        if ($targetDomain && $targetDomain !== $currentDomain) {
            // Special case: if we are in localhost/dev, we might want to skip this or mock it
            if ($currentDomain === 'localhost' || str_contains($currentDomain, '127.0.0.1')) {
                // For development, we only show if explicitly requested or just skip
                return null;
            }

            return [
                'lang' => $preferredLang,
                'domain' => $targetDomain,
                'url' => self::buildAlternateUrl($targetDomain),
                'message' => $preferredLang === 'es'
                    ? '¿Prefieres ver el sitio en Español? Visita chascarrillo.es'
                    : 'Would you prefer to view the site in English? Visit chascarrillo.com'
            ];
        }

        return null;
    }

    /**
     * Generates hreflang links for SEO.
     */
    public static function getHreflangs(): array
    {
        $links = [];
        foreach (self::DOMAINS as $lang => $domain) {
            $links[$lang] = self::buildAlternateUrl($domain);
        }
        return $links;
    }

    private static function detectBrowserLang(string $acceptLang): ?string
    {
        if (empty($acceptLang)) {
            return null;
        }

        $langs = explode(',', $acceptLang);
        foreach ($langs as $lang) {
            $code = substr(trim(strtolower($lang)), 0, 2);
            if (isset(self::DOMAINS[$code])) {
                return $code;
            }
        }
        return null;
    }

    private static function buildAlternateUrl(string $targetDomain): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // In local development, we don't really have the other domain active, 
        // but for the sake of the link, we build it correctly.
        return "{$protocol}://{$targetDomain}{$uri}";
    }
}
