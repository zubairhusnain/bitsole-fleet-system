<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('app_custom_notifications', function (Blueprint $table) {
            $table->id(); // Auto-increment primary key
            $table->unsignedBigInteger('event_id'); // Foreign key to tc_event table
            $table->unsignedBigInteger('device_id'); // Foreign key to tc_devices table
            $table->unsignedBigInteger('notification_id'); // Foreign key to tc_notifications table
            $table->string('event_type');
            $table->timestamp('event_time');
            $table->json('attributes')->nullable();
            $table->string('notificators')->nullable(); // e.g., "web,email,sms"
            $table->integer('mail_sent')->default('0')->comment('0 =mail not send, 1=mail sent')->nullable(); // e.g., "web,email,sms"
            $table->integer('is_read')->default('0')->comment('0 =no, 1=yes')->nullable(); // e.g., "web,email,sms"
            $table->integer('user_id')->nullable(); // e.g., "web,email,sms"
            $table->timestamps(); // created_at and updated_at fields
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('mysqlTraccar')->dropIfExists('tc_custom_notifications');
    }
};
