<?php

namespace IgcLabs\Floop\Console\Commands;

use IgcLabs\Floop\FloopManager;
use Illuminate\Console\Command;

class FloopClearCommand extends Command
{
    protected $signature = 'floop:clear
        {--actioned : Clear all actioned items}
        {--all : Clear all items (pending and actioned)}';

    protected $description = 'Clear feedback items';

    public function handle(FloopManager $manager): int
    {
        $clearActioned = $this->option('actioned');
        $clearAll = $this->option('all');

        if (! $clearActioned && ! $clearAll) {
            $this->error('You must specify --actioned or --all');

            return self::FAILURE;
        }

        $all = $manager->all();
        $count = 0;

        if ($clearAll) {
            $count = count($all['pending']) + count($all['actioned']);
            if ($count === 0) {
                $this->info('No feedback items to clear.');

                return self::SUCCESS;
            }

            if (! $this->confirm("Delete all {$count} feedback items (pending + actioned)?")) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }

            foreach ($all['pending'] as $item) {
                $manager->delete($item['filename'], 'pending');
            }
            foreach ($all['actioned'] as $item) {
                $manager->delete($item['filename'], 'actioned');
            }
        } else {
            $count = count($all['actioned']);
            if ($count === 0) {
                $this->info('No actioned items to clear.');

                return self::SUCCESS;
            }

            if (! $this->confirm("Delete {$count} actioned feedback items?")) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }

            foreach ($all['actioned'] as $item) {
                $manager->delete($item['filename'], 'actioned');
            }
        }

        $this->info("\u{2705} Cleared {$count} items.");

        return self::SUCCESS;
    }
}
