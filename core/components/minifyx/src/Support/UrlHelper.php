<?php

declare(strict_types=1);

namespace MinifyX\Support;

final class UrlHelper
{
    public static function resolveScheme(string $siteUrl, ?bool $isHttps = null): string
    {
        if ($isHttps === true) {
            return 'https';
        }
        if ($isHttps === false) {
            return 'http';
        }

        $scheme = parse_url($siteUrl, PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? strtolower($scheme) : 'https';
    }

    public static function schemeAwareSiteUrl(string $siteUrl, ?bool $isHttps = null): string
    {
        if ($siteUrl === '') {
            return '';
        }

        $parts = parse_url($siteUrl);
        if (!is_array($parts) || empty($parts['host'])) {
            return rtrim($siteUrl, '/') . '/';
        }

        $scheme = self::resolveScheme($siteUrl, $isHttps);
        $path = $parts['path'] ?? '/';
        $authority = $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');

        return $scheme . '://' . $authority . rtrim($path, '/') . '/';
    }

    public static function absolutize(string $url, string $siteUrl, ?bool $isHttps = null): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return self::resolveScheme($siteUrl, $isHttps) . ':' . $url;
        }

        $base = self::schemeAwareSiteUrl($siteUrl, $isHttps);
        if ($base === '') {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            $parts = parse_url($base);
            if (is_array($parts) && !empty($parts['host'])) {
                $authority = $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');

                return self::resolveScheme($base, $isHttps) . '://' . $authority . $url;
            }
        }

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    public static function detectHttpsFromServer(): ?bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($forwarded) && strtolower($forwarded) === 'https') {
            return true;
        }

        return null;
    }
}
