<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConnectionFeeChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('connection_fee_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('connection_fee', 15);
            $table->decimal('connection_fee_monthly_installment', 15);
            $table->uuid('station_id');
            $table->foreign('station_id')->references('id')->on('meter_stations')->cascadeOnDelete();
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('connection_fee_charges');
    }
}
