<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->unsignedBigInteger('vehicle_id'); // Traccar Device ID
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, active, completed, cancelled
            $table->timestamps();

            $table->index('vehicle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};
