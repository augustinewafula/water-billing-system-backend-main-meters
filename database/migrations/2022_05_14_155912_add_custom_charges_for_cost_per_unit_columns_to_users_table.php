<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomChargesForCostPerUnitColumnsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('use_custom_charges_for_cost_per_unit')->unsigned()->default(0);
            $table->decimal('cost_per_unit', 15)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('use_custom_charges_for_cost_per_unit');
            $table->dropColumn('first_connection_fee_on');
        });
    }
}
