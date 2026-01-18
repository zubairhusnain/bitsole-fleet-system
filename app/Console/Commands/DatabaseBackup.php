<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class DatabaseBackup extends Command
{
    protected $signature = 'backup:database-only';

    protected $description = 'Create a database-only PostgreSQL backup as a zip file';

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

        $disk = Storage::disk('local');
        $backupDir = config('backup.backup.name', Str::slug(config('app.name', 'laravel-backup')));
        $directory = $backupDir;

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $timestamp = now()->format('Y-m-d-His');
        $prefix = Str::slug(env('BACKUP_NAME', config('app.name', 'database-backup')));
        $zipFileName = "{$prefix}-{$timestamp}.zip";
        $zipFullPath = $disk->path($directory.'/'.$zipFileName);

        $innerSqlName = Str::slug(config('app.name', 'app'), '-').'-database-backup.sql';
        $tmpSqlPath = $disk->path($directory.'/.tmp-'.$timestamp.'.sql');

        $this->info("Creating database backup: {$zipFileName}");

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

        $process->run(function ($type, $buffer) use ($tmpSqlPath) {
            file_put_contents($tmpSqlPath, $buffer, FILE_APPEND);
        });

        if (!$process->isSuccessful()) {
            $this->error('Database backup failed: '.$process->getErrorOutput());
            if (file_exists($tmpSqlPath)) {
                @unlink($tmpSqlPath);
            }
            if (file_exists($zipFullPath)) {
                @unlink($zipFullPath);
            }
            return 1;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Unable to create zip archive for database backup.');
            if (file_exists($tmpSqlPath)) {
                @unlink($tmpSqlPath);
            }
            return 1;
        }

        $zip->addFile($tmpSqlPath, $innerSqlName);
        $zip->close();

        if (file_exists($tmpSqlPath)) {
            @unlink($tmpSqlPath);
        }

        $size = filesize($zipFullPath);
        $this->info('Database backup completed: '.$zipFileName.' ('.$this->humanFileSize($size).')');

        return 0;
    }

    protected function humanFileSize(int $bytes, int $decimals = 2): string
    {
        if ($bytes <= 0) {
            return '0B';
        }

        $sz = 'BKMGTP';
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
        $maxIndex = strlen($sz) - 1;
        if ($factor < 0) {
            $factor = 0;
        } elseif ($factor > $maxIndex) {
            $factor = $maxIndex;
        }

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)).$sz[$factor];
    }
}
