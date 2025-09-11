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
        Schema::create('client_callback_urls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_id')->index();
            $table->string('callback_url');
            $table->string('secret_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->integer('timeout_seconds')->default(30);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['client_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_callback_urls');
    }
};
