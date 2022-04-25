<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFaultyMetersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('faulty_meters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meter_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('fault_type')->unsigned();
            $table->timestamps(6);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('faulty_meters');
    }
}
