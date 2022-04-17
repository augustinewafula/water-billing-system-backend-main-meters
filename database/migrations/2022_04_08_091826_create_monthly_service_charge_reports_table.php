<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMonthlyServiceChargeReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monthly_service_charge_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('jan')->nullable();
            $table->string('feb')->nullable();
            $table->string('mar')->nullable();
            $table->string('apr')->nullable();
            $table->string('may')->nullable();
            $table->string('jun')->nullable();
            $table->string('jul')->nullable();
            $table->string('aug')->nullable();
            $table->string('sep')->nullable();
            $table->string('oct')->nullable();
            $table->string('nov')->nullable();
            $table->string('dec')->nullable();
            $table->string('year');
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
        Schema::dropIfExists('monthly_service_charge_reports');
    }
}
