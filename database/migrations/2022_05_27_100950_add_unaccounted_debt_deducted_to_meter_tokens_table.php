<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnaccountedDebtDeductedToMeterTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('meter_tokens', function (Blueprint $table) {
            $table->decimal('unaccounted_debt_deducted', 15)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('meter_tokens', function (Blueprint $table) {
            $table->dropColumn('unaccounted_debt_deducted');
        });
    }
}
