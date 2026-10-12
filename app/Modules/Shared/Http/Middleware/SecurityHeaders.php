<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds hardening response headers to every response.
 *
 * The baseline headers (nosniff, frame denial, referrer/permissions policy) are
 * always sent. HSTS and the Content Security Policy are opt-in via
 * `config('security.headers')` because they need TLS and a verified directive
 * set respectively. When CSP is enabled it is nonce-based, so the Vite-generated
 * tags and the inline theme bootstrap in the SPA shell still execute.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate the nonce before the response is rendered so `@vite` and the
        // Blade shell can attach it to their tags.
        $nonce = config('security.headers.csp_enabled') ? Vite::useCspNonce() : null;

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()',
        );
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (config('security.headers.hsts') && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.config('security.headers.hsts_max_age').'; includeSubDomains',
            );
        }

        if ($nonce !== null) {
            $header = config('security.headers.csp_report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $this->contentSecurityPolicy($nonce));
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $connect = array_merge(
            ["'self'", 'ws:', 'wss:'],
            config('security.headers.csp_connect_extra', []),
        );

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'nonce-".$nonce."'",
            'connect-src '.implode(' ', $connect),
        ]);
    }
}
