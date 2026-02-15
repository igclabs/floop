<?php

namespace IgcLabs\Floop;

use IgcLabs\Floop\Events\FeedbackActioned;
use IgcLabs\Floop\Events\FeedbackStored;
use Illuminate\Support\Str;

/**
 * Handles all work order I/O: storing, listing, actioning, and deleting
 * feedback markdown files on the filesystem.
 */
class FloopManager
{
    protected string $storagePath;

    protected string $pendingPath;

    protected string $actionedPath;

    const TYPE_EMOJIS = [
        'feedback' => "\u{1F4AC}",
        'task' => "\u{1F4CB}",
        'idea' => "\u{1F4A1}",
        'bug' => "\u{1F41B}",
    ];

    const TYPE_LABELS = [
        'feedback' => 'Feedback',
        'task' => 'Task',
        'idea' => 'Idea',
        'bug' => 'Bug',
    ];

    const PRIORITY_EMOJIS = [
        'low' => "\u{1F7E2}",
        'medium' => "\u{1F7E0}",
        'high' => "\u{1F534}",
    ];

    const PRIORITY_LABELS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;
        $this->pendingPath = $storagePath.'/pending';
        $this->actionedPath = $storagePath.'/actioned';

        $this->ensureDirectories();
    }

    /**
     * Check whether the widget is currently enabled.
     */
    public function isEnabled(): bool
    {
        return ! file_exists($this->storagePath.'/.disabled');
    }

    /**
     * Enable the widget by removing the .disabled flag file.
     */
    public function enable(): void
    {
        $flag = $this->storagePath.'/.disabled';
        if (file_exists($flag)) {
            unlink($flag);
        }
    }

    /**
     * Disable the widget by writing a .disabled flag file.
     */
    public function disable(): void
    {
        $this->ensureDirectories();
        file_put_contents($this->storagePath.'/.disabled', 'Disabled at '.now()->toDateTimeString()."\n");
    }

    protected function ensureDirectories(): void
    {
        if (! is_dir($this->pendingPath)) {
            mkdir($this->pendingPath, 0775, true);
        }
        if (! is_dir($this->actionedPath)) {
            mkdir($this->actionedPath, 0775, true);
        }
    }

    protected function sanitiseFilename(string $filename): string
    {
        return basename($filename);
    }

    /**
     * Store a new work order as a markdown file in the pending directory.
     *
     * @param  array{message: string, type?: string, priority?: string, url?: string, route_name?: string, route_action?: string, route_params?: array, query_params?: array, views?: string[], viewport?: string, user?: string, user_agent?: string}  $data
     * @return string The generated filename.
     */
    public function store(array $data): string
    {
        $timestamp = now();
        $slug = Str::slug(Str::limit($data['message'], 50, ''));
        $filename = $timestamp->format('Y-m-d_His').'_'.$slug.'.md';

        $markdown = $this->buildMarkdown($data, $timestamp);

        file_put_contents($this->pendingPath.'/'.$filename, $markdown);

        event(new FeedbackStored($filename, $data['type'], $data['message']));

        return $filename;
    }

    /**
     * Move a work order from pending to actioned and update its status line.
     */
    public function markActioned(string $filename): bool
    {
        $filename = $this->sanitiseFilename($filename);
        $source = $this->pendingPath.'/'.$filename;
        $dest = $this->actionedPath.'/'.$filename;

        if (! file_exists($source)) {
            return false;
        }

        $content = file_get_contents($source);
        $actionedAt = now()->format('Y-m-d H:i');
        $content = preg_replace(
            '/\*\*Status:\*\* .+/',
            "**Status:** \u{2705} Actioned ({$actionedAt})",
            $content
        );

        file_put_contents($dest, $content);
        unlink($source);

        event(new FeedbackActioned($filename));

        return true;
    }

    /**
     * Move a work order from actioned back to pending (reopen).
     */
    public function markPending(string $filename): bool
    {
        $filename = $this->sanitiseFilename($filename);
        $source = $this->actionedPath.'/'.$filename;
        $dest = $this->pendingPath.'/'.$filename;

        if (! file_exists($source)) {
            return false;
        }

        $content = file_get_contents($source);
        $content = preg_replace(
            '/\*\*Status:\*\* .+/',
            "**Status:** \u{1F7E1} Pending",
            $content
        );

        file_put_contents($dest, $content);
        unlink($source);

        return true;
    }

    /**
     * Permanently delete a work order file.
     *
     * @param  string  $status  Either 'pending' or 'actioned'.
     */
    public function delete(string $filename, string $status): bool
    {
        $filename = $this->sanitiseFilename($filename);
        $dir = $status === 'actioned' ? $this->actionedPath : $this->pendingPath;
        $path = $dir.'/'.$filename;

        if (! file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * List all work orders grouped by status.
     *
     * @return array{pending: array, actioned: array}
     */
    public function all(): array
    {
        return [
            'pending' => $this->listDirectory($this->pendingPath, 'pending'),
            'actioned' => $this->listDirectory($this->actionedPath, 'actioned'),
        ];
    }

    /**
     * Get the count of pending and actioned work orders.
     *
     * @return array{pending: int, actioned: int}
     */
    public function counts(): array
    {
        return [
            'pending' => $this->countFiles($this->pendingPath),
            'actioned' => $this->countFiles($this->actionedPath),
        ];
    }

    protected function countFiles(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        return count(glob($path.'/*.md'));
    }

    protected function listDirectory(string $path, string $status): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = glob($path.'/*.md');
        $items = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $content = file_get_contents($file);

            $title = '';
            if (preg_match('/^# (.+)$/m', $content, $matches)) {
                $title = $matches[1];
            }

            $created = '';
            if (preg_match('/\*\*Created:\*\* (.+)$/', $content, $matches)) {
                $created = trim($matches[1]);
            }

            $type = '';
            if (preg_match('/\*\*Type:\*\* (.+)$/', $content, $matches)) {
                $type = trim($matches[1]);
            }

            $priority = '';
            if (preg_match('/\*\*Priority:\*\* (.+)$/', $content, $matches)) {
                $priority = trim($matches[1]);
            }

            $items[] = [
                'filename' => $filename,
                'title' => $title,
                'type' => $type,
                'priority' => $priority,
                'created' => $created,
                'status' => $status,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['filename'], $a['filename']));

        return $items;
    }

    protected function buildMarkdown(array $data, \Illuminate\Support\Carbon $timestamp): string
    {
        $type = $data['type'] ?? 'feedback';
        $emoji = self::TYPE_EMOJIS[$type] ?? self::TYPE_EMOJIS['feedback'];
        $typeLabel = self::TYPE_LABELS[$type] ?? 'Feedback';
        $message = $data['message'] ?? '';
        $priority = $data['priority'] ?? null;
        $created = $timestamp->format('Y-m-d H:i:s');

        $heading = "# {$emoji} {$typeLabel}: ".Str::limit($message, 80);

        $meta = "**Status:** \u{1F7E1} Pending\n";
        $meta .= "**Created:** {$created}\n";
        $meta .= "**Type:** {$typeLabel}\n";

        if ($priority && isset(self::PRIORITY_LABELS[$priority])) {
            $priorityEmoji = self::PRIORITY_EMOJIS[$priority];
            $priorityLabel = self::PRIORITY_LABELS[$priority];
            $meta .= "**Priority:** {$priorityEmoji} {$priorityLabel}\n";
        }

        $messageSection = "## Message\n\n{$message}";

        $contextRows = [];
        if (! empty($data['url'])) {
            $contextRows[] = "| **URL** | `{$data['url']}` |";
        }
        if (! empty($data['route_name'])) {
            $contextRows[] = "| **Route** | `{$data['route_name']}` |";
        }
        if (! empty($data['route_action']) && $data['route_action'] !== 'Closure') {
            $contextRows[] = "| **Controller** | `{$data['route_action']}` |";
        }
        if (! empty($data['method'])) {
            $contextRows[] = "| **Method** | `{$data['method']}` |";
        }
        if (! empty($data['views'])) {
            $contextRows[] = "| **View** | `{$data['views'][0]}` |";
        }
        if (! empty($data['user'])) {
            $contextRows[] = "| **User** | {$data['user']} |";
        }
        if (! empty($data['viewport'])) {
            $contextRows[] = "| **Viewport** | {$data['viewport']} |";
        }
        if (! empty($data['user_agent'])) {
            $contextRows[] = "| **Browser** | {$data['user_agent']} |";
        }

        $md = "{$heading}\n\n{$meta}\n---\n\n{$messageSection}\n";

        if (! empty($contextRows)) {
            $md .= "\n---\n\n## Page Context\n\n";
            $md .= "| Property | Value |\n";
            $md .= "|----------|-------|\n";
            $md .= implode("\n", $contextRows)."\n";
        }

        if (! empty($data['route_params'])) {
            $md .= "\n### Route Parameters\n\n```json\n";
            $md .= json_encode($data['route_params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $md .= "\n```\n";
        }

        if (! empty($data['query_params'])) {
            $md .= "\n### Query Parameters\n\n```json\n";
            $md .= json_encode($data['query_params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $md .= "\n```\n";
        }

        if (! empty($data['views']) && count($data['views']) > 1) {
            $md .= "\n### Blade Views\n\n";
            foreach ($data['views'] as $view) {
                $md .= "- `{$view}`\n";
            }
        }

        return $md;
    }
}
