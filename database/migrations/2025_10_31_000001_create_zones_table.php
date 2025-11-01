<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('distributor_id')->nullable()->index();
            $table->unsignedBigInteger('geofence_id')->nullable()->index(); // Traccar geofence id
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active|inactive
            $table->float('speed')->nullable();
            $table->string('type')->nullable(); // circle|rectangle|polygon|route
            $table->json('coordinates')->nullable(); // general coordinates input
            $table->float('radius')->nullable(); // circle radius
            $table->json('polygon')->nullable(); // polygon points
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};