<?php

namespace IgcLabs\Floop\Http\Middleware;

use Closure;
use IgcLabs\Floop\FloopManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures request context (route, controller, views) and optionally
 * auto-injects the Floop widget into HTML responses.
 */
class InjectFloopContext
{
    /**
     * Handle an incoming request.
     *
     * Captures route/controller context via View::share, listens for Blade
     * view renders to build a view list, and (when auto_inject is enabled)
     * appends the widget markup before </body>.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $capturedViews = [];

        $route = $request->route();

        View::share('_floopContext', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route_name' => $route?->getName() ?? '',
            'route_action' => $route?->getActionName() ?? '',
            'route_params' => $route?->parameters() ?? [],
            'query_params' => $request->query(),
        ]);

        Event::listen('composing:*', function (string $event, array $data) use (&$capturedViews) {
            if (! empty($data[0]) && $data[0] instanceof \Illuminate\View\View) {
                $name = $data[0]->name();

                if ($name === 'floop::widget') {
                    return;
                }

                if (! in_array($name, $capturedViews)) {
                    $capturedViews[] = $name;
                }
            }
        });

        $response = $next($request);

        View::share('_floopCapturedViews', $capturedViews);

        if ($this->shouldInjectWidget($response)) {
            $widget = view('floop::widget')->render(); // @phpstan-ignore argument.type
            $body = $response->getContent();
            // Inject before the closing </body> tag; remove Content-Length
            // since the response body has grown.
            $response->setContent(str_replace('</body>', $widget.'</body>', $body));
            $response->headers->remove('Content-Length');
        }

        return $response;
    }

    /**
     * Determine whether the widget should be auto-injected into this response.
     */
    protected function shouldInjectWidget(Response $response): bool
    {
        if (! config('floop.auto_inject', true)) {
            return false;
        }

        if ($response->isRedirection() || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return false;
        }

        $environments = config('floop.environments', ['local']);
        $envAllowed = in_array('*', $environments) || app()->environment($environments);
        if (! $envAllowed) {
            return false;
        }

        if (! app(FloopManager::class)->isEnabled()) {
            return false;
        }

        $content = $response->getContent();

        if (! str_contains($content, '</body>')) {
            return false;
        }

        if (str_contains($content, 'id="floop-widget"')) {
            return false;
        }

        return true;
    }
}
