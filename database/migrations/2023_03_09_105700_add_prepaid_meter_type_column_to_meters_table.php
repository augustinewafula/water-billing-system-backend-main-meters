<?php

use App\Enums\PrepaidMeterType;
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
        Schema::table('meters', function (Blueprint $table) {
            $table->unsignedTinyInteger('prepaid_meter_type')->default(PrepaidMeterType::SH);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn('prepaid_meter_type');
        });
    }
};
