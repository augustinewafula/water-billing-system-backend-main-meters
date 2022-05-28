<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMonthlyServiceChargePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monthly_service_charge_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('monthly_service_charge_id');
            $table->foreign('monthly_service_charge_id', 'monthly_service_charge_id')->references('id')->on('monthly_service_charges')->cascadeOnDelete();
            $table->decimal('amount_paid', 15);
            $table->decimal('balance', 15)->default(0);
            $table->decimal('credit', 15)->default(0);
            $table->decimal('amount_over_paid', 15)->default(0);
            $table->foreignUuid('mpesa_transaction_id')->nullable()->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('monthly_service_charge_payments');
    }
}
