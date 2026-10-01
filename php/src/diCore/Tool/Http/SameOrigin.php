<?php

namespace diCore\Tool\Http;

use diCore\Base\CMS;
use diCore\Base\Exception\HttpException;
use diCore\Tool\Auth;

/**
 * Same-origin (CSRF) check for state-changing endpoints authenticated by an ambient
 * cookie. Compares the full normalized origin (scheme + host + non-default port), so
 * http→https downgrades and cross-port requests are rejected too. Origin is read
 * first, Referer as the fallback; a request with neither is not same-origin.
 */
class SameOrigin
{
    public static function isSameOrigin(): bool
    {
        $expected = static::expectedOrigin();
        $source = static::sourceOrigin();

        return $expected !== '' &&
            $source !== '' &&
            strcasecmp($source, $expected) === 0;
    }

    /**
     * @throws HttpException 403 when the request is cross-origin
     */
    public static function assert(
        string $message = 'Cross-origin request rejected'
    ): void {
        if (!static::isSameOrigin()) {
            throw HttpException::forbidden($message);
        }
    }

    /**
     * Header-authenticated requests (X-Session / auth_token) carry no ambient cookie,
     * so a foreign page can't forge them.
     */
    public static function isHeaderAuthenticated(): bool
    {
        return trim((string) \diRequest::header(\diSession::HEADER_NAME)) !== '' ||
            trim((string) \diRequest::header(Auth::HEADER_TOKEN)) !== '';
    }

    /**
     * The CSRF rule in one place: dev and header-authenticated requests pass,
     * everything else must be same-origin.
     */
    public static function isCsrfSafe(): bool
    {
        return static::isDev() ||
            static::isHeaderAuthenticated() ||
            static::isSameOrigin();
    }

    /**
     * Throwing variant for REST controllers (a real 403).
     *
     * @throws HttpException 403
     */
    public static function enforceCsrf(
        string $message = 'Cross-origin request rejected'
    ): void {
        if (!static::isCsrfSafe()) {
            throw HttpException::forbidden($message);
        }
    }

    public static function expectedOrigin(): string
    {
        return static::normalizeOrigin(
            \diRequest::protocolExt() . \diRequest::domain()
        );
    }

    public static function sourceOrigin(): string
    {
        $origin = (string) \diRequest::server('HTTP_ORIGIN');

        if ($origin !== '') {
            return static::normalizeOrigin($origin);
        }

        $referer = (string) \diRequest::server('HTTP_REFERER');

        if ($referer !== '') {
            return static::normalizeOrigin($referer);
        }

        return '';
    }

    public static function normalizeOrigin(string $url): string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;

        // Strip default ports so `https://host` matches `https://host:443`.
        if (
            $port !== null &&
            (($scheme === 'https' && (int) $port === 443) ||
                ($scheme === 'http' && (int) $port === 80))
        ) {
            $port = null;
        }

        return $port !== null ? "$scheme://$host:$port" : "$scheme://$host";
    }

    protected static function isDev(): bool
    {
        return (bool) CMS::isDev();
    }
}
