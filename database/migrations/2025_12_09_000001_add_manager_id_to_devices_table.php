<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('manager_id')->nullable()->after('distributor_id');
        });

        // Migrate existing data: assume current user_id is the manager
        DB::statement('UPDATE devices SET manager_id = user_id');
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('manager_id');
        });
    }
};
