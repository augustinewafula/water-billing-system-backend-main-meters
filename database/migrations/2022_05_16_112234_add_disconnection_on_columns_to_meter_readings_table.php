<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisconnectionOnColumnsToMeterReadingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dateTime('tell_user_meter_disconnection_on');
            $table->dateTime('actual_meter_disconnection_on');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropColumn('tell_user_meter_disconnection_on');
            $table->dropColumn('actual_meter_disconnection_on');
        });
    }
}
