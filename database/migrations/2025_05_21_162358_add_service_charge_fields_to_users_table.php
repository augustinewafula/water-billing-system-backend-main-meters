<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('should_pay_monthly_service_charge')
                ->default(false)
                ->after('remember_token');

            $table->decimal('monthly_service_charge', 10, 2)
                ->nullable()
                ->after('should_pay_monthly_service_charge');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'should_pay_monthly_service_charge',
                'monthly_service_charge',
            ]);
        });
    }
};
