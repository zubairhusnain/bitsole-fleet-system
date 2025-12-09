<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckAndAddEventColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:check-columns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for is_read and mail_sent columns in tc_events and add them if missing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking tc_events table for missing columns...');

        if (!Schema::connection('pgsql')->hasTable('tc_events')) {
            $this->error('Table tc_events does not exist!');
            return Command::FAILURE;
        }

        // Process is_read column
        if (!Schema::connection('pgsql')->hasColumn('tc_events', 'is_read')) {
            Schema::connection('pgsql')->table('tc_events', function (Blueprint $table) {
                $table->tinyInteger('is_read')->default(0)->nullable();
            });
            $this->info('Added column: is_read');

            $affected = DB::connection('pgsql')->table('tc_events')->update(['is_read' => 1]);
            $this->info("Updated {$affected} pre-existing events to is_read=1.");
            Log::info("Added column is_read and updated {$affected} records via cron.");
        } else {
            $this->info('Column exists: is_read');
        }

        // Process mail_sent column
        if (!Schema::connection('pgsql')->hasColumn('tc_events', 'mail_sent')) {
            Schema::connection('pgsql')->table('tc_events', function (Blueprint $table) {
                $table->tinyInteger('mail_sent')->default(0)->nullable();
            });
            $this->info('Added column: mail_sent');

            $affected = DB::connection('pgsql')->table('tc_events')->update(['mail_sent' => 1]);
            $this->info("Updated {$affected} pre-existing events to mail_sent=1.");
            Log::info("Added column mail_sent and updated {$affected} records via cron.");
        } else {
            $this->info('Column exists: mail_sent');
        }

        $this->info('Check complete.');
        return Command::SUCCESS;
    }
}
