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
        $command = ['claude', '-p', $prompt, '--verbose'];

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

        try {
            $process->start();

            $process->wait(function ($type, $buffer) {
                $this->getOutput()->write($buffer);
            });

            return $process->isSuccessful();
        } catch (ProcessTimedOutException $e) {
            $this->newLine();
            $this->warn("\u{23F1} Timed out after {$timeout}s");

            return false;
        }
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
