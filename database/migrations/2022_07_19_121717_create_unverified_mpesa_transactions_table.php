<?php

use App\Enums\UnverifiedMpesaTransactionStatus;
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
        Schema::create('unverified_mpesa_transactions', static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ConversationalId')->nullable();
            $table->string('Originator')->nullable();
            $table->string('ClientIp');
            $table->string('FirstName')->nullable();
            $table->string('MiddleName')->nullable();
            $table->string('LastName')->nullable();
            $table->string('TransactionType')->nullable();
            $table->string('TransID')->unique();
            $table->string('TransTime')->nullable();
            $table->string('BusinessShortCode')->nullable();
            $table->string('BillRefNumber')->nullable();
            $table->string('InvoiceNumber')->nullable();
            $table->string('ThirdPartyTransID')->nullable();
            $table->string('MSISDN')->nullable();
            $table->decimal('TransAmount', 8, 2)->nullable();
            $table->decimal('OrgAccountBalance', 8, 2)->nullable();
            $table->tinyInteger('Status')->unsigned()->default(UnverifiedMpesaTransactionStatus::UNVERIFIED);
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('unverified_mpesa_transactions');
    }
};
