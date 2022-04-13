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
        $table->foreign('station_id')->references('id')->on('meter_stations')->cascadeOnDelete();
        $table->tinyInteger('mode')->unsigned()->default(MeterMode::Manual);
        $table->foreignUuid('type_id')->nullable()->constrained('meter_types')->cascadeOnDelete();
        $table->integer('last_reading');
        $table->dateTime('last_reading_date')->nullable();
        $table->dateTime('last_billing_date')->nullable();
        $table->dateTime('last_communication_date')->nullable();
        $table->decimal('battery_voltage', 15)->nullable();
        $table->integer('signal_intensity')->nullable();
        $table->string('valve_last_switched_off_by')->default('system')->comment('user or system');
        $table->string('sim_card_number')->nullable();
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
