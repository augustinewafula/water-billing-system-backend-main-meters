<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMonthlyServiceChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monthly_service_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('mpesa_transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_paid', 15);
            $table->decimal('balance', 15)->default(0);
            $table->decimal('credit', 15)->default(0);
            $table->decimal('amount_over_paid', 15)->default(0);
            $table->dateTime('month');
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
        Schema::dropIfExists('monthly_service_charges');
    }
}
