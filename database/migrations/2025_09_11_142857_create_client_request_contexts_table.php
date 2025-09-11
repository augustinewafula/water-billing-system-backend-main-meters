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
        Schema::create('client_request_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('meter_id');
            $table->string('client_id');
            $table->string('message_id');
            $table->string('action_type');
            $table->json('original_request');
            $table->json('hexing_response');
            $table->string('status')->default('pending');
            $table->timestamps();
            
            $table->index('message_id');
            $table->index(['client_id', 'action_type']);
            $table->foreign('meter_id')->references('id')->on('meters');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_request_contexts');
    }
};
