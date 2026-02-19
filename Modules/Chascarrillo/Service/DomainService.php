<?php

namespace Modules\Chascarrillo\Service;

/**
 * Service to manage multi-domain logic based on configuration.
 * Handles region detection and site suggestions.
 */
class DomainService
{
    /**
     * Gets the configured sites and their domains.
     * Expected structure in config.json:
     * "sites": {
     *    "es": { "domain": "alxarafe.es", "message": "¿Prefieres ver el sitio en Español?" },
     *    "en": { "domain": "alxarafe.com", "message": "Would you prefer to view the site in English?" }
     * }
     */
    public static function getSites(): array
    {
        $config = \Alxarafe\Base\Config::getConfig();
        $sites = $config->sites ?? [];

        // Ensure it's an array of objects/arrays
        return json_decode(json_encode($sites), true);
    }

    /**
     * Gets the domain for a specific language.
     */
    public static function getTargetDomain(string $lang): ?string
    {
        $sites = self::getSites();
        return $sites[$lang]['domain'] ?? null;
    }

    /**
     * Gets the current host.
     */
    public static function getCurrentDomain(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }

    /**
     * Detects if the user should be invited to switch domains based on browser language.
     * Returns suggestion data or null.
     */
    public static function getSuggestion(): ?array
    {
        $config = \Alxarafe\Base\Config::getConfig();
        if (!($config->main->enableWorldsites ?? false)) {
            return null;
        }

        // Don't suggest if user already dismissed it
        if (isset($_COOKIE['skip_domain_suggestion'])) {
            return null;
        }

        $sites = self::getSites();
        if (empty($sites)) {
            return null;
        }

        $currentDomain = self::getCurrentDomain();
        $browserLangs = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $availableLangs = array_keys($sites);
        $preferredLang = self::detectBrowserLang($browserLangs, $availableLangs);

        // Case A: User has a specific language that matches one of our sites
        if ($preferredLang) {
            $target = $sites[$preferredLang] ?? null;
            if ($target && isset($target['domain']) && $target['domain'] !== $currentDomain) {
                return self::formatSuggestion($preferredLang, $target);
            }
        } else {
            // Case B: User doesn't speak the local language (e.g., visits .es but speaks English/Other)
            // If we are on a regional domain (like .es), suggest the international one (.en / .com)
            $esDomain = $sites['es']['domain'] ?? null;
            $enTarget = $sites['en'] ?? null;

            if ($currentDomain === $esDomain && $enTarget) {
                return self::formatSuggestion('en', $enTarget);
            }
        }

        return null;
    }

    /**
     * Helper to format the suggestion response.
     */
    private static function formatSuggestion(string $lang, array $target): ?array
    {
        $currentDomain = self::getCurrentDomain();

        // Skip in localhost unless testing
        if (($currentDomain === 'localhost' || str_contains($currentDomain, '127.0.0.1')) && !isset($_GET['test_suggestion'])) {
            return null;
        }

        return [
            'lang' => $lang,
            'domain' => $target['domain'],
            'url' => self::buildAlternateUrl($target['domain']),
            'message' => $target['message'] ?? "Switch to {$target['domain']}?"
        ];
    }

    public static function getHreflangs(): array
    {
        $links = [];
        foreach (self::getSites() as $lang => $data) {
            if (isset($data['domain'])) {
                $links[$lang] = self::buildAlternateUrl($data['domain']);
            }
        }
        return $links;
    }

    private static function detectBrowserLang(string $acceptLang, array $availableLangs): ?string
    {
        if (empty($acceptLang)) {
            return null;
        }

        $langs = explode(',', $acceptLang);
        foreach ($langs as $lang) {
            $code = substr(trim(strtolower($lang)), 0, 2);
            if (in_array($code, $availableLangs)) {
                return $code;
            }
        }
        return null;
    }

    public static function getTargetUrl(string $lang): ?string
    {
        $domain = self::getTargetDomain($lang);
        return $domain ? self::buildAlternateUrl($domain) : null;
    }

    public static function buildAlternateUrl(string $targetDomain): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return "{$protocol}://{$targetDomain}{$uri}";
    }
}
