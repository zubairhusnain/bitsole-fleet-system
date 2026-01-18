<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:cleanup-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete backups older than configured retention days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('local');
        $backupName = config('backup.backup.name', \Illuminate\Support\Str::slug(config('app.name', 'laravel-backup')));

        // Ensure the directory exists
        if (!$disk->exists($backupName)) {
            $this->info("Backup directory '{$backupName}' does not exist.");
            return 0;
        }

        $files = $disk->files($backupName);
        $count = 0;
        $deleted = 0;

        $retentionDays = (int) env('BACKUP_RETENTION_DAYS', 10);
        $threshold = Carbon::now()->subDays($retentionDays)->timestamp;

        $this->info("Scanning for backups older than {$retentionDays} days in '{$backupName}'...");

        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($extension, ['sql', 'zip'], true)) {
                $lastModified = $disk->lastModified($file);

                if ($lastModified < $threshold) {
                    $disk->delete($file);
                    $this->info("Deleted: " . basename($file));
                    $deleted++;
                }
                $count++;
            }
        }

        $this->info("Cleanup complete. Scanned {$count} files, deleted {$deleted} files.");

        return 0;
    }
}
