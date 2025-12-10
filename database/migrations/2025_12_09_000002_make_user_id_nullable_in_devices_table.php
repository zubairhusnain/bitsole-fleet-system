<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // We cannot easily revert nullable to not null without ensuring no nulls exist.
            // But for rollback purposes, we can try:
            // $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
