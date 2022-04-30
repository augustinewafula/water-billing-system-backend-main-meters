<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMonthlyServiceChargesTable extends Migration
{
    /**
     * Run the migrations.set sts
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monthly_service_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('service_charge', 15);
            $table->tinyInteger('status')->unsigned()->default(PaymentStatus::NotPaid);
            $table->dateTime('month');
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
        Schema::dropIfExists('monthly_service_charges');
    }
}
