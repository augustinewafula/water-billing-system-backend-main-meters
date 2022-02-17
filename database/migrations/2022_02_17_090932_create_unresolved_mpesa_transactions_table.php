<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnresolvedMpesaTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('unresolved_mpesa_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mpesa_transaction_id')->nullable()->constrained();
            $table->tinyInteger('reason')->unsigned();
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
        Schema::dropIfExists('unresolved_mpesa_transactions');
    }
}
