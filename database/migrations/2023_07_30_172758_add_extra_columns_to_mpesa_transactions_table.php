<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->smallInteger('credited')->default(false);
            $table->uuid('credited_by')->nullable();
            $table->foreign('credited_by')->references('id')->on('users')->onDelete('set null');
            $table->text('reason_for_crediting')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropColumn('credited');
            $table->dropForeign(['credited_by']);
            $table->dropColumn('credited_by');
            $table->dropColumn('reason_for_crediting');
        });
    }
};
