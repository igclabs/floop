<?php

namespace IgcLabs\Floop\Console\Commands;

use IgcLabs\Floop\FloopManager;
use Illuminate\Console\Command;

class FloopEnableCommand extends Command
{
    protected $signature = 'floop:enable';

    protected $description = 'Enable the Floop feedback widget';

    public function handle(FloopManager $manager): void
    {
        $manager->enable();
        $this->info('Floop widget enabled.');
    }
}
