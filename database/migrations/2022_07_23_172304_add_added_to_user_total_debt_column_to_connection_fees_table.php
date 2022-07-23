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
    public function up(): void
    {
        Schema::table('connection_fees', static function (Blueprint $table) {
            $table->tinyInteger('added_to_user_total_debt')->unsigned()->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('connection_fees', static function (Blueprint $table) {
            $table->dropColumn('added_to_user_total_debt');
        });
    }
};
