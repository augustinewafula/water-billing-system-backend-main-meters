<?php

namespace App\Jobs;

use App\Traits\ProcessesMpesaTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ProcessesMpesaTransaction;
    protected $mpesa_transaction;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($mpesa_transaction)
    {
        $this->mpesa_transaction = $mpesa_transaction;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->processMpesaTransaction($this->mpesa_transaction);
        } catch (\JsonException|\Throwable $e) {
            \Log::error($e);
        }
    }
}
