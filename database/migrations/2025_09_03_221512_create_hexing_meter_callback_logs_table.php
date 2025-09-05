<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hexing_meter_callback_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('meter_id');
            $table->string('message_id')->nullable();
            $table->string('action');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('meter_id')->references('id')->on('meters')->onDelete('cascade');
            $table->index(['meter_id', 'action']);
            $table->index(['message_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hexing_meter_callback_logs');
    }
};
