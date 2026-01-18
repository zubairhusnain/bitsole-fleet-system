<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupController extends Controller
{
    private function checkAdmin(Request $request)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }
    }

    public function index(Request $request)
    {
        $this->checkAdmin($request);

        $disk = Storage::disk('local');
        $backupDir = \Illuminate\Support\Str::slug(env('BACKUP_NAME', env('APP_NAME', 'laravel-backup')));

        $files = [];
        try {
            if ($disk->exists($backupDir)) {
                $files = $disk->files($backupDir);
            }
        } catch (\Exception $e) {
            return response()->json(['backups' => []]);
        }
        $backups = [];
        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($extension, ['sql', 'zip'], true)) {
                $backups[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => $this->humanFileSize($disk->size($file)),
                    'date' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                    'timestamp' => $disk->lastModified($file),
                ];
            }
        }

        // Sort by date desc
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return response()->json(['backups' => $backups]);
    }

    public function download(Request $request)
    {
        $this->checkAdmin($request);

        $path = $request->query('path');
        if (!$path) {
            return response()->json(['message' => 'Path is required'], 400);
        }

        $disk = Storage::disk('local');
        // Security check to ensure path is within allowed directory
        // if (!str_contains($path, 'Portal | AM TeleTech')) {
        //      // Optional: stricter check
        // }

        if (!$disk->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->download($disk->path($path));
    }

    public function delete(Request $request)
    {
        $this->checkAdmin($request);

        $path = $request->input('path');
        if (!$path) {
            return response()->json(['message' => 'Path is required'], 400);
        }

        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            $disk->delete($path);
            return response()->json(['message' => 'Backup deleted']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }

    private function humanFileSize($bytes, $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }
}
