<?php

namespace IgcLabs\Floop\Console\Commands;

use IgcLabs\Floop\FloopManager;
use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class FloopWatchCommand extends Command
{
    protected $signature = 'floop:watch
        {--interval= : Polling interval in seconds}
        {--once : Process current pending items then exit}
        {--tools= : Comma-separated allowed Claude tools}
        {--model= : Claude model to use}
        {--timeout= : Max seconds per work order}';

    protected $description = 'Watch for pending work orders and process them with Claude Code';

    protected array $processed = [];

    public function handle(FloopManager $manager): int
    {
        if (! $this->claudeExists()) {
            $this->error('Claude CLI not found on PATH. Install it: https://docs.anthropic.com/en/docs/claude-code');

            return self::FAILURE;
        }

        $interval = (int) ($this->option('interval') ?? config('floop.watch.interval', 5));
        $once = $this->option('once');

        $this->info("\u{1F440} Floop watcher started (polling every {$interval}s)");
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        do {
            $pending = $manager->all()['pending'];

            $newItems = array_filter($pending, function ($item) {
                return ! in_array($item['filename'], $this->processed);
            });

            // Oldest first (FIFO) — the list comes sorted newest-first
            $newItems = array_reverse($newItems);

            if (empty($newItems)) {
                if ($once) {
                    $this->info('No new work orders to process.');

                    return self::SUCCESS;
                }

                sleep($interval);

                continue;
            }

            foreach ($newItems as $item) {
                $this->processed[] = $item['filename'];
                $this->processWorkOrder($manager, $item['filename']);

                if ($this->laravel->runningInConsole() && connection_aborted()) {
                    break 2;
                }
            }

            if ($once) {
                return self::SUCCESS;
            }

            sleep($interval);
        } while (true);

        return self::SUCCESS;
    }

    protected function processWorkOrder(FloopManager $manager, string $filename): void
    {
        $content = $manager->readPending($filename);

        if ($content === null) {
            $this->warn("Skipping {$filename} — file no longer exists.");

            return;
        }

        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->info("\u{1F4CB} Processing: {$filename}");
        $this->line(str_repeat('─', 60));
        $this->newLine();

        $prompt = $this->buildPrompt($filename, $content);
        $timeout = (int) ($this->option('timeout') ?? config('floop.watch.timeout', 300));
        $maxRetries = 2;

        for ($attempt = 1; $attempt <= $maxRetries + 1; $attempt++) {
            $success = $this->runClaude($prompt, $timeout);

            if ($success) {
                $this->newLine();
                $this->info("\u{2705} Finished: {$filename}");

                return;
            }

            if ($attempt <= $maxRetries) {
                $this->warn("Attempt {$attempt} failed — retrying...");
            }
        }

        $this->error("Failed after ".($maxRetries + 1)." attempts — skipping: {$filename}");
    }

    protected function runClaude(string $prompt, int $timeout): bool
    {
        $command = ['claude', '-p', $prompt, '--verbose', '--output-format', 'stream-json'];

        $tools = $this->option('tools') ?? config('floop.watch.tools', 'Bash,Read,Edit,Write,Glob,Grep');
        $command[] = '--allowedTools';
        $command[] = $tools;

        $model = $this->option('model') ?? config('floop.watch.model');
        if ($model) {
            $command[] = '--model';
            $command[] = $model;
        }

        $process = new Process($command);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout($timeout);

        $startTime = time();
        $buffer = '';

        try {
            $process->start();

            $process->wait(function ($type, $data) use ($startTime, &$buffer) {
                if ($type === Process::ERR) {
                    $this->getOutput()->write("<fg=red>{$data}</>");

                    return;
                }

                $buffer .= $data;

                // Process complete lines (NDJSON)
                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1);

                    $this->processStreamLine($line, $startTime);
                }
            });

            // Process any remaining buffer
            if (trim($buffer) !== '') {
                $this->processStreamLine($buffer, $startTime);
            }

            if (! $process->isSuccessful()) {
                $this->warn('Exit code: '.$process->getExitCode());
            }

            return $process->isSuccessful();
        } catch (ProcessTimedOutException $e) {
            $this->newLine();
            $this->warn("\u{23F1} Timed out after {$timeout}s");

            return false;
        }
    }

    protected function processStreamLine(string $line, int $startTime): void
    {
        $line = trim($line);
        if ($line === '') {
            return;
        }

        $event = json_decode($line, true);
        if (! is_array($event) || ! isset($event['type'])) {
            return;
        }

        $elapsed = $this->formatElapsed(time() - $startTime);

        match ($event['type']) {
            'assistant' => $this->handleAssistantEvent($event, $elapsed),
            'result' => $this->handleResultEvent($event, $elapsed),
            default => null,
        };
    }

    protected function handleAssistantEvent(array $event, string $elapsed): void
    {
        $content = $event['message']['content'] ?? [];

        foreach ($content as $block) {
            if (($block['type'] ?? '') !== 'tool_use') {
                continue;
            }

            $tool = $block['name'] ?? '';
            $input = $block['input'] ?? [];
            $label = $this->describeToolUse($tool, $input);

            if ($label !== null) {
                $this->line("  <fg=gray>{$elapsed}</>  {$label}");
            }
        }
    }

    protected function handleResultEvent(array $event, string $elapsed): void
    {
        $cost = isset($event['cost_usd']) ? '$'.number_format($event['cost_usd'], 2) : null;
        $turns = $event['num_turns'] ?? null;
        $durationMs = $event['duration_ms'] ?? null;

        $parts = [];
        if ($durationMs !== null) {
            $parts[] = $this->formatElapsed((int) round($durationMs / 1000));
        }
        if ($turns !== null) {
            $parts[] = "{$turns} turns";
        }
        if ($cost !== null) {
            $parts[] = $cost;
        }

        $summary = $parts ? ' ('.implode(', ', $parts).')' : '';
        $this->line("  <fg=gray>{$elapsed}</>  \u{1F3C1} Done{$summary}");
    }

    protected function describeToolUse(string $tool, array $input): ?string
    {
        return match ($tool) {
            'Read' => "\u{1F4C4} Read: ".$this->shortenPath($input['file_path'] ?? ''),
            'Edit' => "\u{270F}\u{FE0F}  Edit: ".$this->shortenPath($input['file_path'] ?? ''),
            'Write' => "\u{1F4DD} Write: ".$this->shortenPath($input['file_path'] ?? ''),
            'Glob' => "\u{1F50D} Glob: ".($input['pattern'] ?? ''),
            'Grep' => "\u{1F50E} Grep: ".($input['pattern'] ?? ''),
            'Bash' => "\u{26A1} Run: ".$this->truncate($input['command'] ?? '', 60),
            'Task' => "\u{1F916} Agent: ".$this->truncate($input['description'] ?? $input['prompt'] ?? '', 50),
            default => null,
        };
    }

    protected function shortenPath(string $path): string
    {
        $basePath = base_path().'/';
        if (str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath));
        }

        return $path;
    }

    protected function truncate(string $text, int $length): string
    {
        $text = str_replace("\n", ' ', $text);

        return strlen($text) > $length ? substr($text, 0, $length).'...' : $text;
    }

    protected function formatElapsed(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    protected function buildPrompt(string $filename, string $content): string
    {
        $skillPath = dirname(__DIR__, 3).'/SKILL.md';
        $skillContent = file_exists($skillPath) ? file_get_contents($skillPath) : '';

        // Strip the YAML front matter from SKILL.md
        if (str_starts_with($skillContent, '---')) {
            $endPos = strpos($skillContent, '---', 3);
            if ($endPos !== false) {
                $skillContent = trim(substr($skillContent, $endPos + 3));
            }
        }

        $prompt = <<<PROMPT
You have a Floop work order to process.

## Work Order: {$filename}

{$content}

## Instructions

{$skillContent}

## Important

- Process exactly ONE work order: {$filename}
- When finished: php artisan floop:action {$filename} --note="Brief description of what you changed"
- If you can't action it, still close the loop with a note explaining why
PROMPT;

        return $prompt;
    }

    protected function claudeExists(): bool
    {
        $process = Process::fromShellCommandline('which claude');
        $process->run();

        return $process->isSuccessful();
    }
}
