<?php

declare(strict_types=1);

namespace Ordain\Delegation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

/**
 * Installation wizard for the Ordain Delegation package.
 */
final class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:install
                            {--force : Overwrite existing configuration files}
                            {--skip-migrations : Skip running migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Ordain Delegation package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayWelcome();

        $this->publishConfiguration();
        $this->runMigrations();
        $this->displayNextSteps();

        return self::SUCCESS;
    }

    private function displayWelcome(): void
    {
        $this->newLine();
        $this->components->info('Installing Ordain Delegation Package...');
        $this->newLine();
    }

    private function publishConfiguration(): void
    {
        $configPath = config_path('permission-delegation.php');
        $force = (bool) $this->option('force');

        if (File::exists($configPath) && ! $force) {
            if (! confirm('Configuration file already exists. Overwrite?', false)) {
                note('Skipping configuration publish.');

                return;
            }
        }

        $this->call('vendor:publish', [
            '--tag' => 'delegation-config',
            '--force' => $force || ! File::exists($configPath),
        ]);

        info('Configuration file published.');
    }

    private function runMigrations(): void
    {
        if ($this->option('skip-migrations')) {
            note('Skipping migrations as requested.');

            return;
        }

        if (! confirm('Run database migrations?', true)) {
            note('Skipping migrations. Run them manually with: php artisan migrate');

            return;
        }

        $this->call('migrate');
        info('Migrations completed.');
    }

    private function displayNextSteps(): void
    {
        $this->newLine();
        $this->components->info('Installation complete!');
        $this->newLine();

        $this->components->bulletList([
            'Add the <fg=yellow>HasDelegation</> trait to your User model',
            'Implement <fg=yellow>DelegatableUserInterface</> on your User model',
            'Add delegation columns to your users table (see migrations)',
            'Configure root admin role in <fg=yellow>config/permission-delegation.php</>',
        ]);

        $this->newLine();
        note('Example User model setup:');
        $this->line('');
        $this->line('<fg=gray>use Ordain\Delegation\Contracts\DelegatableUserInterface;</>');
        $this->line('<fg=gray>use Ordain\Delegation\Traits\HasDelegation;</>');
        $this->line('');
        $this->line('<fg=cyan>class</> <fg=yellow>User</> <fg=cyan>extends</> Authenticatable <fg=cyan>implements</> DelegatableUserInterface');
        $this->line('{');
        $this->line('    <fg=cyan>use</> HasDelegation;');
        $this->line('}');
        $this->newLine();

        warning('Remember to add delegation fields to your User model\'s $fillable or handle them explicitly.');
        $this->newLine();

        $this->components->info('Run `php artisan about` to see Delegation package info.');
    }
}
