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
        Schema::create('fuel_entries', function (Blueprint $table) {
            $table->id();
            $table->integer('device_id'); // References tc_devices.id
            $table->date('fill_date');
            $table->decimal('quantity', 10, 2); // Liters or Gallons
            $table->decimal('cost', 10, 2); // Total cost
            $table->bigInteger('odometer')->nullable(); // Odometer reading at fill-up
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('device_id');
            $table->index('fill_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_entries');
    }
};
