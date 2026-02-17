<?php

namespace IgcLabs\Floop\Console\Commands;

use IgcLabs\Floop\FloopManager;
use Illuminate\Console\Command;

class FloopActionCommand extends Command
{
    protected $signature = 'floop:action
        {filename? : The feedback filename to action}
        {--reopen : Reopen an actioned item (move back to pending)}
        {--note= : A note describing what was done}';

    protected $description = 'Mark a feedback item as actioned (or reopen it)';

    public function handle(FloopManager $manager): int
    {
        $reopen = $this->option('reopen');
        $filename = $this->argument('filename') ?? $this->promptForFilename($manager, $reopen);

        if ($filename === null) {
            return self::SUCCESS;
        }

        if ($reopen) {
            $result = $manager->markPending($filename);
            if ($result) {
                $this->info("\u{2705} Reopened: {$filename}");

                return self::SUCCESS;
            }
            $this->error("Could not reopen: {$filename} (not found in actioned/)");

            return self::FAILURE;
        }

        $result = $manager->markActioned($filename, $this->option('note'));
        if ($result) {
            $this->info("\u{2705} Actioned: {$filename}");

            return self::SUCCESS;
        }

        $this->error("Could not action: {$filename} (not found in pending/)");

        return self::FAILURE;
    }

    protected function promptForFilename(FloopManager $manager, bool $reopen): ?string
    {
        $all = $manager->all();
        $items = $reopen ? $all['actioned'] : $all['pending'];

        if (empty($items)) {
            $status = $reopen ? 'actioned' : 'pending';
            $this->info("No {$status} work orders found.");

            return null;
        }

        $labels = [];
        $filenames = [];
        foreach ($items as $item) {
            $labels[] = $item['title'].' ('.$item['created'].')';
            $filenames[] = $item['filename'];
        }

        $selected = $this->choice(
            $reopen ? 'Which work order do you want to reopen?' : 'Which work order do you want to action?',
            $labels
        );

        $index = array_search($selected, $labels);

        return $filenames[$index];
    }
}
