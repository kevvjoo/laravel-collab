<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'collab:install 
                            {--force : Overwrite existing files}
                            {--no-migrate : Skip running migrations}';

    /**
     * The console command description.
     */
    protected $description = 'Install Laravel Collab package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->info('  ╔═══════════════════════════════════╗');
        $this->info('  ║   🤝 Laravel Collab Installer    ║');
        $this->info('  ╚═══════════════════════════════════╝');
        $this->newLine();

        // Step 1: Publish configuration
        $this->info('📝 Publishing configuration file...');
        $params = ['--tag' => 'collab-config'];
        if ($this->option('force')) {
            $params['--force'] = true;
        }
        $this->call('vendor:publish', $params);
        $this->line('   ✓ Configuration published');
        $this->newLine();

        // Step 2: Publish migrations
        $this->info('📦 Publishing migrations...');
        $params = ['--tag' => 'collab-migrations'];
        if ($this->option('force')) {
            $params['--force'] = true;
        }
        $this->call('vendor:publish', $params);
        $this->line('   ✓ Migrations published');
        $this->newLine();

        // Step 3: Run migrations
        if (!$this->option('no-migrate')) {
            if ($this->confirm('Do you want to run migrations now?', true)) {
                $this->info('🔨 Running migrations...');
                $this->call('migrate');
                $this->line('   ✓ Migrations completed');
                $this->newLine();
            }
        }

        // Success message
        $this->info('✨ Laravel Collab installed successfully!');
        $this->newLine();

        // Show next steps
        $this->info('📋 Next Steps:');
        $this->newLine();
        $this->line('  1. Add the trait to your models:');
        $this->line('     use YourVendor\Collab\Traits\HasConcurrentEditing;');
        $this->newLine();
        $this->line('     class Post extends Model {');
        $this->line('         use HasConcurrentEditing;');
        $this->line('     }');
        $this->newLine();
        $this->line('  2. Use in your controllers:');
        $this->line('     $result = $post->acquireLock(auth()->user());');
        $this->newLine();
        $this->line('  3. Configure settings (optional):');
        $this->line('     config/collab.php');
        $this->newLine();
        $this->line('  4. Add to task scheduler (optional):');
        $this->line('     $schedule->command(\'collab:cleanup\')->hourly();');
        $this->newLine();
        $this->line('📖 Documentation: https://github.com/yourvendor/laravel-collab');
        $this->newLine();

        return self::SUCCESS;
    }
}