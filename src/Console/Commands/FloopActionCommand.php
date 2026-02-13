<?php

namespace IgcLabs\Floop\Console\Commands;

use IgcLabs\Floop\FloopManager;
use Illuminate\Console\Command;

class FloopActionCommand extends Command
{
    protected $signature = 'floop:action
        {filename : The feedback filename to action}
        {--reopen : Reopen an actioned item (move back to pending)}';

    protected $description = 'Mark a feedback item as actioned (or reopen it)';

    public function handle(FloopManager $manager): int
    {
        $filename = $this->argument('filename');
        $reopen = $this->option('reopen');

        if ($reopen) {
            $result = $manager->markPending($filename);
            if ($result) {
                $this->info("\u{2705} Reopened: {$filename}");

                return self::SUCCESS;
            }
            $this->error("Could not reopen: {$filename} (not found in actioned/)");

            return self::FAILURE;
        }

        $result = $manager->markActioned($filename);
        if ($result) {
            $this->info("\u{2705} Actioned: {$filename}");

            return self::SUCCESS;
        }

        $this->error("Could not action: {$filename} (not found in pending/)");

        return self::FAILURE;
    }
}
