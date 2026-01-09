<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE vehicle_models ALTER COLUMN odmeter_ioid DROP NOT NULL');
            DB::statement('ALTER TABLE vehicle_models ALTER COLUMN fuel_ioid DROP NOT NULL');
        } else {
            DB::statement("ALTER TABLE `vehicle_models` MODIFY `odmeter_ioid` VARCHAR(190) NULL");
            DB::statement("ALTER TABLE `vehicle_models` MODIFY `fuel_ioid` VARCHAR(190) NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE vehicle_models ALTER COLUMN odmeter_ioid SET NOT NULL');
            DB::statement('ALTER TABLE vehicle_models ALTER COLUMN fuel_ioid SET NOT NULL');
        } else {
            DB::statement("ALTER TABLE `vehicle_models` MODIFY `odmeter_ioid` VARCHAR(190) NOT NULL");
            DB::statement("ALTER TABLE `vehicle_models` MODIFY `fuel_ioid` VARCHAR(190) NOT NULL");
        }
    }
};
