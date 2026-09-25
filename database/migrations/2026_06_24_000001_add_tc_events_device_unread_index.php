<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(
            'CREATE INDEX IF NOT EXISTS tc_events_deviceid_is_read_idx ON tc_events (deviceid, is_read) WHERE is_read = 0'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS tc_events_deviceid_is_read_idx');
    }
};
