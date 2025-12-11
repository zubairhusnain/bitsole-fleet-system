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
    protected $description = 'Delete backups older than 10 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('local');
        $appName = config('app.name');
        
        // Ensure the directory exists
        if (!$disk->exists($appName)) {
            $this->info("Backup directory '{$appName}' does not exist.");
            return 0;
        }

        $files = $disk->files($appName);
        $count = 0;
        $deleted = 0;

        $threshold = Carbon::now()->subDays(10)->timestamp;

        $this->info("Scanning for backups older than 10 days in '{$appName}'...");

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
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
