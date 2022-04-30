<?php

use App\Enums\PaymentStatus;
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
            $table->foreign('meter_id')->references('id')->on('meters')->cascadeOnDelete();
            $table->integer('previous_reading');
            $table->integer('current_reading');
            $table->dateTime('month');
            $table->decimal('bill', 15);
            $table->decimal('service_fee', 15);
            $table->tinyInteger('status')->unsigned()->default(PaymentStatus::NotPaid);
            $table->tinyInteger('sms_sent')->unsigned()->default(false);
            $table->dateTime('send_sms_at');
            $table->timestamps(6);
            $table->softDeletes();
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
