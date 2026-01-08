<?php

declare(strict_types=1);

namespace CobbyPlugin\Util;

/**
 * Security utilities for validating and sanitizing HTTP headers.
 *
 * This trait provides methods to safely handle HTTP_HOST and other headers
 * to prevent HTTP header injection attacks.
 */
trait SecurityTrait
{
    /**
     * Safely gets and validates the HTTP_HOST header.
     *
     * HTTP_HOST can be manipulated by attackers to inject malicious content.
     * This method validates the host against a strict pattern and returns a
     * safe fallback if the host is invalid or missing.
     *
     * SECURITY: Prevents HTTP header injection attacks by validating that
     * the host only contains:
     * - Alphanumeric characters (a-z, A-Z, 0-9)
     * - Dots (.) for domain separators
     * - Hyphens (-) in domain names
     * - Colons (:) and digits for port numbers
     *
     * @param string $fallback Fallback value if HTTP_HOST is missing or invalid (default: 'localhost')
     *
     * @return string Validated HTTP_HOST or fallback
     */
    private function getSafeHttpHost(string $fallback = 'localhost'): string
    {
        // Check if HTTP_HOST exists
        if (!isset($_SERVER['HTTP_HOST'])) {
            // Try SERVER_NAME as alternative
            if (isset($_SERVER['SERVER_NAME'])) {
                $host = $_SERVER['SERVER_NAME'];
            } else {
                return $fallback;
            }
        } else {
            $host = $_SERVER['HTTP_HOST'];
        }

        // Validate host format
        // Allow: alphanumeric, dots, hyphens, colons (for ports)
        // Example valid hosts: example.com, sub.example.com, localhost:8000, 192.168.1.1:3306
        if (!preg_match('/^[a-zA-Z0-9\.\-:]+$/', $host)) {
            // Invalid characters detected - use fallback
            return $fallback;
        }

        // Additional validation: check host length (max 253 characters for domain names)
        if (strlen($host) > 253) {
            return $fallback;
        }

        return $host;
    }

    /**
     * Get the current Shopware version.
     *
     * @return string Shopware version (e.g., "6.5.0.0")
     */
    private function getShopwareVersion(): string
    {
        // Try to get Shopware version from Kernel
        if (defined('\Shopware\Core\Kernel::SHOPWARE_FALLBACK_VERSION')) {
            return \Shopware\Core\Kernel::SHOPWARE_FALLBACK_VERSION;
        }

        // Fallback: Try composer.lock
        $composerLockPath = dirname(__DIR__, 5).'/composer.lock';
        if (file_exists($composerLockPath)) {
            $composerLock = json_decode(file_get_contents($composerLockPath), true);
            if (isset($composerLock['packages'])) {
                foreach ($composerLock['packages'] as $package) {
                    if ('shopware/core' === $package['name']) {
                        return $package['version'] ?? 'unknown';
                    }
                }
            }
        }

        return 'unknown';
    }
}
