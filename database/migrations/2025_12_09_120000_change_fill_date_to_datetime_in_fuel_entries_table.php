<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check driver to handle specific SQL if needed, but Schema builder should handle it.
        // For MySQL/MariaDB: modify()
        // For PostgreSQL: change() (requires doctrine/dbal)
        // Since we might not have doctrine/dbal, using raw SQL is safer if simple change fails.
        // But standard Laravel way:
        
        $driver = DB::connection()->getDriverName();

        Schema::table('fuel_entries', function (Blueprint $table) use ($driver) {
             if ($driver === 'pgsql') {
                 // Postgres specific
                 DB::statement('ALTER TABLE fuel_entries ALTER COLUMN fill_date TYPE TIMESTAMP USING fill_date::timestamp');
             } else {
                 // MySQL/MariaDB
                 $table->dateTime('fill_date')->change();
             }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        Schema::table('fuel_entries', function (Blueprint $table) use ($driver) {
             if ($driver === 'pgsql') {
                 DB::statement('ALTER TABLE fuel_entries ALTER COLUMN fill_date TYPE DATE');
             } else {
                 $table->date('fill_date')->change();
             }
        });
    }
};
