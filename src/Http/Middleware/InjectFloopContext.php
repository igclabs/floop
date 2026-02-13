<?php

namespace IgcLabs\Floop\Http\Middleware;

use Closure;
use IgcLabs\Floop\FloopManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class InjectFloopContext
{
    /**
     * Views captured during this request via the composing event.
     * Read by the widget blade template, which renders last in the layout.
     */
    public static array $capturedViews = [];

    public function handle(Request $request, Closure $next): Response
    {
        static::$capturedViews = [];

        $route = $request->route();

        View::share('_floopContext', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route_name' => $route?->getName() ?? '',
            'route_action' => $route?->getActionName() ?? '',
            'route_params' => $route?->parameters() ?? [],
            'query_params' => $request->query() ?? [],
        ]);

        Event::listen('composing:*', function (string $event, array $data) {
            if (! empty($data[0]) && $data[0] instanceof \Illuminate\View\View) {
                $name = $data[0]->name();

                if ($name === 'floop::widget') {
                    return;
                }

                if (! in_array($name, static::$capturedViews)) {
                    static::$capturedViews[] = $name;
                }
            }
        });

        $response = $next($request);

        if ($this->shouldInjectWidget($response)) {
            $widget = view('floop::widget')->render();
            $body = $response->getContent();
            $response->setContent(str_replace('</body>', $widget.'</body>', $body));
            $response->headers->remove('Content-Length');
        }

        return $response;
    }

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
