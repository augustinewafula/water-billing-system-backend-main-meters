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
        Schema::table('meters', function (Blueprint $table) {
            $table->dropForeign(['concentrator_id']);
            $table->foreign('concentrator_id')
                ->references('id')
                ->on('concentrators')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropForeign(['concentrator_id']);
            $table->foreign('concentrator_id')
                ->references('id')
                ->on('concentrators')
                ->cascadeOnDelete();
        });
    }
};
