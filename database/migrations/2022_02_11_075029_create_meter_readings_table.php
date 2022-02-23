<?php

use App\Enums\MeterReadingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeterReadingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('meter_id');
            $table->foreign('meter_id')->references('id')->on('meters');
            $table->integer('previous_reading');
            $table->integer('current_reading');
            $table->string('month');
            $table->decimal('bill', 15);
            $table->decimal('service_fee', 15);
            $table->tinyInteger('status')->unsigned()->default(MeterReadingStatus::NotPaid);
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
        Schema::dropIfExists('meter_readings');
    }
}
