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
        Schema::create('unread_meters', static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meter_id')->constrained('meters')->cascadeOnDelete();
            $table->dateTime('month');
            $table->text('reason');
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
        Schema::dropIfExists('unread_meters');
    }
};
