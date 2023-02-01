<?php

use App\Enums\MeterCategory;
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
        Schema::table('meters', static function (Blueprint $table) {
            $table->unsignedTinyInteger('category')->default(MeterCategory::WATER);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('meters', static function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
