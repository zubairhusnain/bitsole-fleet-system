<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->string('vehicle_label')->nullable()->index();
            $table->string('type_model')->nullable();
            $table->dateTime('incident_start')->nullable();
            $table->dateTime('incident_end')->nullable();
            $table->dateTime('impact_time')->nullable()->index();
            $table->string('driver')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
