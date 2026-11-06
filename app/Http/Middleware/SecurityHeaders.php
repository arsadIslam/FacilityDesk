<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers on every response.
 *
 * CSP notes: all scripts are same-origin files (Vite build, Livewire), so no
 * inline script is allowed. 'unsafe-eval' is required by Alpine (bundled with
 * Livewire), which evaluates x-* expressions. Inline style attributes (progress
 * bars) need 'unsafe-inline' for styles only; styles cannot run code.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // Only over HTTPS in production: HSTS on a dev host is hard to undo in a browser.
        if (app()->isProduction() && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // Laravel's debug exception page relies on inline scripts; production error pages do not.
        if (config('app.debug') && $response->getStatusCode() >= 500) {
            unset($headers['Content-Security-Policy']);
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // `npm run dev`: assets and hot reload come from the Vite dev server.
        $hot = Vite::isRunningHot() ? rtrim((string) file_get_contents(public_path('hot'))) : null;
        $extra = $hot === null ? '' : ' '.$hot;

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'".$extra,
            "style-src 'self' 'unsafe-inline'".$extra,
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'".($hot === null ? '' : ' '.$hot.' '.preg_replace('#^http#', 'ws', $hot)),
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
