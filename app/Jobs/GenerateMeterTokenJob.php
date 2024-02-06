<?php

namespace App\Jobs;

use App\Enums\MeterCategory;
use App\Models\Meter;
use App\Models\MeterToken;
use App\Services\PrepaidMeterService;
use App\Traits\CalculatesBill;
use App\Traits\GeneratesMeterToken;
use App\Traits\NotifiesUser;
use App\Traits\UpdatesUserAccountBalance;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GenerateMeterTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CalculatesBill, UpdatesUserAccountBalance, NotifiesUser;

    public int $tries = 6;

    protected $meter_id;
    protected $mpesa_transaction;
    protected $deductions;
    protected $user_total_amount;
    protected $user;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 20, 40, 80, 160, 320];
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($meter_id, $mpesa_transaction, $deductions, $user_total_amount, $user)
    {
        $this->meter_id = $meter_id;
        $this->mpesa_transaction = $mpesa_transaction;
        $this->deductions = $deductions;
        $this->user_total_amount = $user_total_amount;
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $meter = Meter::find($this->meter_id);
            $units = $this->calculateUnits($this->user_total_amount, $this->user);
            $cost_per_unit = $this->getCostPerUnit($this->user);
            $user_amount_after_service_fee_deduction = $this->getUserAmountAfterServiceFeeDeduction($this->user_total_amount, $this->user);

            $prepaidMeterService = new PrepaidMeterService();
            try {
                $token = $prepaidMeterService->generateMeterToken($meter->number, $user_amount_after_service_fee_deduction, $meter->category, $cost_per_unit, $meter->prepaid_meter_type, $units);
            } catch (Throwable $throwable) {
                Log::info('Failed to generate token for meter ' . $meter->number . ', retrying');
                $prepaidMeterService->registerPrepaidMeter($meter->number, (int)$meter->prepaid_meter_type, MeterCategory::fromValue($meter->category));
                $token = $prepaidMeterService->generateMeterToken($meter->number, $user_amount_after_service_fee_deduction, $meter->category, $cost_per_unit, $meter->prepaid_meter_type, $units);
            }
            if ($token === 'false01' || $token ==='') {
                Log::info('Failed to generate token for meter ' . $meter->number . ', registering meter and retrying');
                $prepaidMeterService->registerPrepaidMeter($meter->number, (int)$meter->prepaid_meter_type, MeterCategory::fromValue($meter->category));
                $token = $prepaidMeterService->generateMeterToken($meter->number, $user_amount_after_service_fee_deduction, $meter->category, $cost_per_unit, $meter->prepaid_meter_type, $units);
            }
            Log::info("Generated token for meter $meter->number is $token");
            throw_if($token === null || $token === '' ||$token === 'false01', RuntimeException::class, 'Failed to generate token');
            $token = strtok($token, ',');
            MeterToken::create([
                'mpesa_transaction_id' => $this->mpesa_transaction->id,
                'token' => strtok($token, ','),
                'units' => $units,
                'service_fee' => $this->calculateServiceFee($this->user, $this->user_total_amount, 'prepay'),
                'monthly_service_charge_deducted' => $this->deductions->monthly_service_charge_deducted,
                'connection_fee_deducted' => $this->deductions->connection_fee_deducted,
                'unaccounted_debt_deducted' => $this->deductions->unaccounted_debt_deducted,
                'meter_id' => $this->user->meter_id,
            ]);
            $this->updateUserAccountBalance($this->user, $this->user_total_amount, $this->deductions, $this->mpesa_transaction->id, true);

            $date = Carbon::now()->toDateTimeString();
            $message = "Meter: $meter->number\n"
                . "Token: $token\n"
                . "Units: $units\n"
                . "Amount: {$this->user_total_amount}\n"
                . "Account: {$this->user->account_number}\n"
                . "Date: $date\n"
                . "Ref: {$this->mpesa_transaction->TransID}";

            $this->notifyUser(
                (object)['message' => $message, 'title' => 'Water Meter Tokens'],
                $this->user,
                'meter tokens',
                $this->mpesa_transaction->MSISDN
            );
        } catch (Throwable $throwable) {
            Log::error($throwable);
            $this->notifyUser(
                (object)['message' => "Failed to generate token of Ksh {$this->mpesa_transaction->TransAmount} for your meter, please contact management for help.",
                    'title' => 'Insufficient amount'],
                $this->user,
                'general',
                $this->mpesa_transaction->MSISDN
            );
        }
    }
}
