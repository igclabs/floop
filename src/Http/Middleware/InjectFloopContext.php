<?php

namespace IgcLabs\Floop\Http\Middleware;

use Closure;
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

        return $next($request);
    }
}
