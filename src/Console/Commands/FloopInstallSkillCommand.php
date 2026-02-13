<?php

namespace IgcLabs\Floop\Console\Commands;

use Illuminate\Console\Command;

class FloopInstallSkillCommand extends Command
{
    protected $signature = 'floop:install-skill {--force : Overwrite existing SKILL.md}';

    protected $description = 'Install the Floop SKILL.md into your project for Claude Code';

    public function handle(): int
    {
        $destination = base_path('.claude/skills/floop/SKILL.md');
        $source = dirname(__DIR__, 3).'/SKILL.md';

        if (file_exists($destination) && ! $this->option('force')) {
            $this->warn('SKILL.md already exists at .claude/skills/floop/SKILL.md');
            $this->line('Use --force to overwrite.');

            return self::SUCCESS;
        }

        $directory = dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($source, $destination);

        $this->info('SKILL.md installed to .claude/skills/floop/SKILL.md');

        return self::SUCCESS;
    }
}
