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
        Schema::table('mpesa_transaction_pull_logs', function (Blueprint $table) {
            // Add nullable shortcode column safely
            $table->string('shortcode')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transaction_pull_logs', function (Blueprint $table) {
            $table->dropColumn('shortcode');
        });
    }
};
