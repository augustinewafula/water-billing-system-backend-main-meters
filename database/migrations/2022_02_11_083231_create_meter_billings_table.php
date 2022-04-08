<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeterBillingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meter_billings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('meter_reading_id');
            $table->foreign('meter_reading_id')->references('id')->on('meter_readings')->cascadeOnDelete();
            $table->decimal('amount_paid', 15);
            $table->foreignUuid('mpesa_transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 15)->default(0);
            $table->decimal('credit', 15)->default(0);
            $table->decimal('amount_over_paid', 15)->default(0);
            $table->decimal('monthly_service_charge_deducted', 15)->default(0);
            $table->string('date_paid')->nullable();
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
        Schema::dropIfExists('meter_billings');
    }
}
