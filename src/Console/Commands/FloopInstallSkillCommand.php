<?php

namespace IgcLabs\Floop\Console\Commands;

use Illuminate\Console\Command;

class FloopInstallSkillCommand extends Command
{
    protected $signature = 'floop:install-skill {--force : Overwrite existing SKILL.md}';

    protected $description = 'Install the Floop SKILL.md into your project for AI coding agents';

    protected array $targets = [
        '.claude' => 'Claude Code',
        '.codex' => 'Codex',
        '.agents' => 'Agents',
        '.opencode' => 'OpenCode',
    ];

    public function handle(): int
    {
        $source = dirname(__DIR__, 3).'/SKILL.md';
        $detected = $this->detectTargets();

        if (empty($detected)) {
            $detected = $this->promptForTargets();
        }

        if (empty($detected)) {
            $this->warn('No targets selected. Nothing installed.');

            return self::SUCCESS;
        }

        foreach ($detected as $dir => $label) {
            $this->installTo($dir, $label, $source);
        }

        return self::SUCCESS;
    }

    protected function detectTargets(): array
    {
        $found = [];

        foreach ($this->targets as $dir => $label) {
            if (is_dir(base_path($dir))) {
                $found[$dir] = $label;
            }
        }

        return $found;
    }

    protected function promptForTargets(): array
    {
        $dirs = array_keys($this->targets);
        $labels = array_values($this->targets);

        $choices = $this->choice(
            'No agent directory detected. Where should the skill be installed? (comma-separate for multiple)',
            $labels,
            0,
            null,
            true
        );

        $selected = [];

        foreach ((array) $choices as $choice) {
            $index = array_search($choice, $labels);
            if ($index !== false) {
                $selected[$dirs[$index]] = $labels[$index];
            }
        }

        return $selected;
    }

    protected function installTo(string $dir, string $label, string $source): void
    {
        $destination = base_path($dir.'/skills/floop/SKILL.md');

        if (file_exists($destination) && ! $this->option('force')) {
            $this->warn("SKILL.md already exists at {$dir}/skills/floop/SKILL.md — use --force to overwrite.");

            return;
        }

        $directory = dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($source, $destination);

        $this->info("SKILL.md installed to {$dir}/skills/floop/SKILL.md ({$label})");
    }
}
