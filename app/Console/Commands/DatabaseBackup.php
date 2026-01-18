<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DatabaseBackup extends Command
{
    protected $signature = 'backup:database-only';

    protected $description = 'Create a database-only PostgreSQL backup as a .sql file';

    public function handle()
    {
        if (!env('BACKUP_ENABLED', true)) {
            $this->info('BACKUP_ENABLED is false. Skipping database backup.');
            return 0;
        }

        $connection = Config::get('database.default', 'pgsql');
        $config = Config::get("database.connections.{$connection}");

        if (!$config || ($config['driver'] ?? null) !== 'pgsql') {
            $this->error('DatabaseBackup currently supports only the pgsql driver.');
            return 1;
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $database = $config['database'] ?? null;
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        if (!$database || !$username) {
            $this->error('Database configuration is incomplete (database/username missing).');
            return 1;
        }

        $dumpBinaryPath = rtrim((string) env('DB_DUMP_BINARY_PATH', ''), '/');
        $pgDump = $dumpBinaryPath ? $dumpBinaryPath.'/pg_dump' : 'pg_dump';

        $backupBaseName = env('BACKUP_NAME', env('APP_NAME', 'database-backup'));
        $backupSlug = Str::slug($backupBaseName ?: 'database-backup');

        $disk = Storage::disk('local');
        $directory = $backupSlug;

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $timestamp = now()->format('Y-m-d-His');
        $fileName = "{$backupSlug}-{$timestamp}.sql";
        $fullPath = $disk->path($directory.'/'.$fileName);

        $this->info("Creating database backup: {$fileName}");

        $process = new Process([
            $pgDump,
            '--host='.$host,
            '--port='.$port,
            '--username='.$username,
            '--format=plain',
            '--no-owner',
            '--no-privileges',
            $database,
        ]);

        $process->setEnv(array_merge($process->getEnv(), [
            'PGPASSWORD' => (string) $password,
        ]));

        $process->setTimeout(300);
        $process->setIdleTimeout(300);

        $process->run(function ($type, $buffer) use ($fullPath) {
            file_put_contents($fullPath, $buffer, FILE_APPEND);
        });

        if (!$process->isSuccessful()) {
            $this->error('Database backup failed: '.$process->getErrorOutput());
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            return 1;
        }

        $size = filesize($fullPath);
        $this->info('Database backup completed: '.$fileName.' ('.$this->humanFileSize($size).')');

        return 0;
    }

    protected function humanFileSize(int $bytes, int $decimals = 2): string
    {
        if ($bytes <= 0) {
            return '0B';
        }

        $sz = 'BKMGTP';
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)).$sz[$factor];
    }
}

