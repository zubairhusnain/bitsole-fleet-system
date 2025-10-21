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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('distributor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('driver_id'); // tracking server driver ID
            $table->unsignedBigInteger('device_id')->nullable(); // tracking server device ID (current assignment)
            $table->timestamps();
            $table->softDeletes();

            $table->unique('driver_id');
            $table->index(['user_id', 'distributor_id']);
            $table->index('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};