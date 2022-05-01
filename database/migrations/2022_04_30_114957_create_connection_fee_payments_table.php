<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConnectionFeePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('connection_fee_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_fee_id');
            $table->foreign('connection_fee_id', 'connection_fee_id')->references('id')->on('connection_fees')->cascadeOnDelete();
            $table->decimal('amount_paid', 15);
            $table->decimal('balance', 15)->default(0);
            $table->decimal('credit', 15)->default(0);
            $table->decimal('amount_over_paid', 15)->default(0);
            $table->decimal('monthly_service_charge_deducted', 15)->default(0);
            $table->foreignUuid('mpesa_transaction_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('connection_fee_payments');
    }
}
