<?php

namespace IgcLabs\Floop\Console\Commands;

use IgcLabs\Floop\FloopManager;
use Illuminate\Console\Command;

class FloopListCommand extends Command
{
    protected $signature = 'floop:list
        {--status=pending : Filter by status (pending, actioned, all)}
        {--type= : Filter by type keyword}';

    protected $description = 'List all feedback items';

    public function handle(FloopManager $manager): int
    {
        $statusFilter = $this->option('status');
        $typeFilter = $this->option('type');

        $all = $manager->all();

        $items = match ($statusFilter) {
            'pending' => $all['pending'],
            'actioned' => $all['actioned'],
            'all' => array_merge($all['pending'], $all['actioned']),
            default => $all['pending'],
        };

        if ($typeFilter) {
            $typeFilter = strtolower($typeFilter);
            $items = array_filter($items, function ($item) use ($typeFilter) {
                return str_contains(strtolower($item['type']), $typeFilter);
            });
            $items = array_values($items);
        }

        if (empty($items)) {
            $this->info('No feedback items found.');
            $this->newLine();
            $this->printSummary($all);

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($items as $item) {
            $statusIcon = $item['status'] === 'pending' ? "\u{1F7E1}" : "\u{2705}";
            $title = \Illuminate\Support\Str::limit($item['title'], 60);

            $rows[] = [
                $statusIcon.' '.ucfirst($item['status']),
                $title,
                $item['created'],
                $item['filename'],
            ];
        }

        $this->table(
            ['Status', 'Title', 'Created', 'Filename'],
            $rows
        );

        $this->newLine();
        $this->printSummary($all);

        return self::SUCCESS;
    }

    protected function printSummary(array $all): void
    {
        $pendingCount = count($all['pending']);
        $actionedCount = count($all['actioned']);
        $this->line("\u{1F4CA} {$pendingCount} pending, {$actionedCount} actioned");
    }
}
