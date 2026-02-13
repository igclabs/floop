<?php

namespace IgcLabs\Floop\Console\Commands;

use IgcLabs\Floop\FloopManager;
use Illuminate\Console\Command;

class FloopDisableCommand extends Command
{
    protected $signature = 'floop:disable';

    protected $description = 'Disable the Floop feedback widget';

    public function handle(FloopManager $manager): void
    {
        $manager->disable();
        $this->info('Floop widget disabled.');
    }
}
