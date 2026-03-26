<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Middleware;

use Closure;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * When {@see FleetSocialLoginPolicy::isDebugPanelEnabled()} is true, appends the Fleet IdP debug
 * panel HTML before the closing {@code </body>} on text/html responses (web stack).
 */
final class InjectFleetIdpDebugPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! FleetSocialLoginPolicy::isDebugPanelEnabled()) {
            return $response;
        }

        if ($response instanceof StreamedResponse) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '' || stripos($content, '</body>') === false) {
            return $response;
        }

        try {
            $panel = view('fleet-idp::components.fleet-debug-panel')->render();
        } catch (\Throwable) {
            return $response;
        }

        if (trim($panel) === '') {
            return $response;
        }

        $injected = preg_replace('/<\/body>/i', $panel.'</body>', $content, 1, $count);
        if ($count === 0 || ! is_string($injected)) {
            return $response;
        }

        $response->setContent($injected);

        return $response;
    }
}
