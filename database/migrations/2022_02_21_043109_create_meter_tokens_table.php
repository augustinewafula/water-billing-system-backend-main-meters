<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeterTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meter_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mpesa_transaction_id')->nullable()->constrained();
            $table->string('token');
            $table->string('units');
            $table->decimal('service_fee', 15);
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
        Schema::dropIfExists('meter_tokens');
    }
}
