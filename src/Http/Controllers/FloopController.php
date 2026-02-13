<?php

namespace IgcLabs\Floop\Http\Controllers;

use IgcLabs\Floop\FloopManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FloopController extends Controller
{
    public function __construct(protected FloopManager $manager) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'type' => 'nullable|in:feedback,task,idea,bug',
            'priority' => 'nullable|in:low,medium,high',
            'extra_context' => 'nullable|array',
        ]);

        $typeLabels = [
            'feedback' => 'Feedback',
            'task' => 'Task',
            'idea' => 'Idea',
            'bug' => 'Bug',
        ];

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
        if ($request->user()) {
            $user = $request->user()->name;
            if ($request->user()->email) {
                $user .= ' ('.$request->user()->email.')';
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

        $filename = $this->manager->store($data);

        $typeLabel = $typeLabels[$data['type']] ?? 'Feedback';

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'message' => "{$typeLabel} submitted successfully.",
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json($this->manager->all());
    }

    public function counts(): JsonResponse
    {
        return response()->json($this->manager->counts());
    }

    public function action(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string',
            'action' => 'required|in:done,reopen,delete',
        ]);

        $filename = $validated['filename'];
        $action = $validated['action'];

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
