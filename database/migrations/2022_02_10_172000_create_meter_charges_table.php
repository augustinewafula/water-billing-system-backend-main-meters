<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeterChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meter_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('cost_per_unit', 15);
            $table->tinyInteger('service_charge_in_percentage')->unsigned()->default(false);
            $table->string('for');
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
        Schema::dropIfExists('meter_charges');
    }
}
