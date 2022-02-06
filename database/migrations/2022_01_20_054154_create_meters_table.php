<?php

use App\Enums\MeterMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMetersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique();
            $table->tinyInteger('valve_status')->unsigned()->nullable();
            $table->uuid('station_id');
            $table->foreign('station_id')->references('id')->on('meter_stations');
            $table->tinyInteger('mode')->unsigned()->default(MeterMode::Manual);
            $table->string('type_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meters');
    }
}
