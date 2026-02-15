<?php

namespace IgcLabs\Floop\Http\Controllers;

use IgcLabs\Floop\FloopManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles feedback submission and work order management via JSON API.
 */
class FloopController extends Controller
{
    public function __construct(protected FloopManager $manager) {}

    /**
     * Validate and store a new work order from the widget.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'type' => 'nullable|in:feedback,task,idea,bug',
            'priority' => 'nullable|in:low,medium,high',
            'extra_context' => 'nullable|array',
            'screenshot' => 'nullable|string|max:'.config('floop.screenshot_max_size', 5242880),
            'console_errors' => 'nullable|array|max:5',
            'console_errors.*.message' => 'required|string|max:500',
            'console_errors.*.timestamp' => 'nullable|string|max:20',
            'network_failures' => 'nullable|array|max:5',
            'network_failures.*.url' => 'required|string|max:2000',
            'network_failures.*.method' => 'nullable|string|max:10',
            'network_failures.*.status' => 'nullable|integer',
            'network_failures.*.statusText' => 'nullable|string|max:200',
            'network_failures.*.timestamp' => 'nullable|string|max:20',
        ]);

        $url = $request->header('X-Feedback-URL')
            ?? $request->header('Referer')
            ?? '';

        $method = $request->header('X-Feedback-Method', 'GET');

        $routeName = $request->input('_route_name', '');
        $routeAction = $request->input('_route_action', '');
        $routeParams = $request->input('_route_params', []);
        $queryParams = $request->input('_query_params', []);
        $views = $request->input('_views', []);
        $viewport = $request->input('_viewport', '');

        $user = 'Guest';
        $authUser = $request->user();
        if ($authUser) {
            /** @var \Illuminate\Foundation\Auth\User $authUser */
            $user = $authUser->getAttribute('name') ?? 'User';
            $email = $authUser->getAttribute('email');
            if ($email) {
                $user .= ' ('.$email.')';
            }
        }

        $data = [
            'message' => $validated['message'],
            'type' => $validated['type'] ?? 'feedback',
            'priority' => $validated['priority'] ?? null,
            'url' => $url,
            'method' => $method,
            'route_name' => $routeName,
            'route_action' => $routeAction,
            'route_params' => is_array($routeParams) ? $routeParams : [],
            'query_params' => is_array($queryParams) ? $queryParams : [],
            'views' => is_array($views) ? $views : [],
            'viewport' => $viewport,
            'user' => $user,
            'user_agent' => $request->userAgent(),
        ];

        if (! empty($validated['extra_context'])) {
            $data['extra_context'] = $validated['extra_context'];
        }

        if (! empty($validated['screenshot'])) {
            $data['screenshot'] = $validated['screenshot'];
        }

        if (! empty($validated['console_errors'])) {
            $data['console_errors'] = $validated['console_errors'];
        }

        if (! empty($validated['network_failures'])) {
            $data['network_failures'] = $validated['network_failures'];
        }

        $filename = $this->manager->store($data);

        $typeLabel = FloopManager::TYPE_LABELS[$data['type']] ?? 'Feedback';

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'message' => "{$typeLabel} submitted successfully.",
        ]);
    }

    /**
     * List all work orders grouped by status.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->manager->all());
    }

    /**
     * Return pending and actioned counts for the badge.
     */
    public function counts(): JsonResponse
    {
        return response()->json($this->manager->counts());
    }

    /**
     * Action, reopen, or delete a work order.
     */
    public function action(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string',
            'action' => 'required|in:done,reopen,delete',
        ]);

        $filename = $validated['filename'];
        $action = $validated['action'];

        /** @var 'done'|'reopen'|'delete' $action */
        $result = match ($action) {
            'done' => $this->manager->markActioned($filename),
            'reopen' => $this->manager->markPending($filename),
            'delete' => $this->manager->delete($filename, 'pending') || $this->manager->delete($filename, 'actioned'),
        };

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => "Could not {$action} item: {$filename}",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Item {$action} successfully.",
        ]);
    }
}
