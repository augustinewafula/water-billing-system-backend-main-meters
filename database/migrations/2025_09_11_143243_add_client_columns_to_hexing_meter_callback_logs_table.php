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
        Schema::table('hexing_meter_callback_logs', function (Blueprint $table) {
            $table->string('client_id')->nullable()->after('message_id');
            $table->timestamp('client_forwarded_at')->nullable()->after('callback_received_at');
            $table->integer('client_response_status')->nullable()->after('client_forwarded_at');
            $table->integer('retry_attempts')->default(0)->after('client_response_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hexing_meter_callback_logs', function (Blueprint $table) {
            $table->dropColumn(['client_id', 'client_forwarded_at', 'client_response_status', 'retry_attempts']);
        });
    }
};
