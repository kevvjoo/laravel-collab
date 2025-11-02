<?php

declare(strict_types=1);

namespace Kevjo\LaravelCollab\Console\Commands;

use Illuminate\Console\Command;
use Kevjo\LaravelCollab\Facades\Collab;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'collab:cleanup 
                            {--all : Clean up everything including old history}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup expired locks, stale sessions, and old history';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧹 Starting Laravel Collab cleanup...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $results = [];

        // Cleanup expired locks
        $this->info('🔒 Cleaning up expired locks...');
        if ($dryRun) {
            $count = Collab::expiredLocks()->count();
            $this->line("   Would delete: {$count} expired locks");
        } else {
            $count = Collab::cleanupExpiredLocks();
            $this->line("   ✓ Deleted: {$count} expired locks");
        }
        $results['expired_locks'] = $count;
        $this->newLine();

        // Cleanup stale sessions
        $this->info('👥 Cleaning up stale sessions...');
        if ($dryRun) {
            $count = Collab::getStaleSessions()->count();
            $this->line("   Would delete: {$count} stale sessions");
        } else {
            $count = Collab::cleanupStaleSessions();
            $this->line("   ✓ Deleted: {$count} stale sessions");
        }
        $results['stale_sessions'] = $count;
        $this->newLine();

        // Cleanup old history (if --all flag is used)
        if ($this->option('all') && config('collab.history.retention_days')) {
            $this->info('📚 Cleaning up old history...');
            $retentionDays = config('collab.history.retention_days');

            if ($dryRun) {
                $this->line("   Would delete: history older than {$retentionDays} days");
                $count = 0;
            } else {
                $count = Collab::cleanupOldHistory();
                $this->line("   ✓ Deleted: {$count} old history records");
            }
            $results['old_history'] = $count;
            $this->newLine();
        }

        // Summary
        if ($dryRun) {
            $this->warn('🔍 Dry run completed - no changes were made');
        } else {
            $this->info('✨ Cleanup completed successfully!');
        }

        $this->newLine();
        $this->info('📊 Summary:');
        $this->table(
            ['Category', 'Count'],
            collect($results)->map(fn($count, $key) => [
                str_replace('_', ' ', ucfirst($key)),
                $count
            ])->toArray()
        );

        return self::SUCCESS;
    }
}