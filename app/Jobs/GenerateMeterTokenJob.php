<?php

namespace App\Jobs;

use App\Enums\MeterCategory;
use App\Enums\PrepaidMeterType;
use App\Models\Concentrator;
use App\Models\Meter;
use App\Models\MeterToken;
use App\Services\ConcentratorService;
use App\Services\PrepaidMeterService;
use App\Traits\CalculatesBill;
use App\Traits\NotifiesUser;
use App\Traits\UpdatesUserAccountBalance;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GenerateMeterTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use CalculatesBill, UpdatesUserAccountBalance, NotifiesUser;

    private const MAX_RETRIES = 6;
    private const RETRY_DELAYS = [10, 20, 40, 80, 160, 320];
    private const TOKEN_ERROR_VALUE = 'false01';

    public int $tries = self::MAX_RETRIES;

    private string $meterId;
    private object $mpesaTransaction;
    private object $deductions;
    private float $userTotalAmount;
    private object $user;
    private PrepaidMeterService $prepaidMeterService;
    private ConcentratorService $concentratorService;

    public function __construct(
        string $meterId,
        object $mpesaTransaction,
        object $deductions,
        float $userTotalAmount,
        object $user
    ) {
        $this->meterId = $meterId;
        $this->mpesaTransaction = $mpesaTransaction;
        $this->deductions = $deductions;
        $this->userTotalAmount = $userTotalAmount;
        $this->user = $user;
        $this->prepaidMeterService = new PrepaidMeterService();
        $this->concentratorService = new ConcentratorService();

        Log::info('Meter token generation job initialized', [
            'meter_id' => $meterId,
            'user_id' => $user->id,
            'transaction_id' => $mpesaTransaction->TransID,
            'amount' => $userTotalAmount
        ]);
    }

    public function backoff(): array
    {
        return self::RETRY_DELAYS;
    }

    public function handle(): void
    {
        try {
            $meter = Meter::findOrFail($this->meterId);

            Log::info('Starting meter token generation process', [
                'meter_number' => $meter->number,
                'user_id' => $this->user->id,
                'account_number' => $this->user->account_number,
                'amount' => $this->userTotalAmount,
                'transaction_id' => $this->mpesaTransaction->TransID,
                'attempt' => $this->attempts()
            ]);

            $token = $this->generateAndValidateToken($meter);

            Log::info('Token generated successfully', [
                'meter_number' => $meter->number,
                'transaction_id' => $this->mpesaTransaction->TransID
            ]);

            $this->createMeterToken($token);
            $this->updateUserBalance();
            $this->sendNotifications($meter, $token);

            if ($meter->concentrator_id) {
                $this->handleConcentratorCommunication($meter, $token);
            }

            Log::info('Token generation process completed successfully', [
                'meter_number' => $meter->number,
                'transaction_id' => $this->mpesaTransaction->TransID,
                'total_attempts' => $this->attempts()
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to complete token generation process', [
                'meter_id' => $this->meterId,
                'user_id' => $this->user->id,
                'transaction_id' => $this->mpesaTransaction->TransID,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString()
            ]);
            throw $throwable;
        }
    }

    /**
     * @throws \JsonException
     */
    private function attemptTokenGeneration(
        Meter $meter,
        float $amount,
        float $costPerUnit,
        float $units,
        bool $usePrismVend2 = false
    ): ?string {
        Log::debug('Attempting token generation with parameters', [
            'meter_number' => $meter->number,
            'amount' => $amount,
            'cost_per_unit' => $costPerUnit,
            'units' => $units,
            'meter_type' => $meter->prepaid_meter_type,
            'use_prism_vend' => $meter->use_prism_vend,
            'use_prism_vend2' => $usePrismVend2
        ]);

        $token = $this->prepaidMeterService->generateMeterToken(
            $meter->number,
            $amount,
            $meter->category,
            $costPerUnit,
            $meter->prepaid_meter_type,
            $units,
            $meter->use_prism_vend,
            $usePrismVend2
        );

        Log::debug('Token generation attempt result', [
            'meter_number' => $meter->number,
            'token_received' => (bool) $token,
            'token_valid' => $token !== self::TOKEN_ERROR_VALUE
        ]);

        return $token;
    }

    /**
     * @throws \JsonException
     */
    private function generateAndValidateToken(Meter $meter): string
    {
        $units = $this->calculateUnits($this->userTotalAmount, $this->user);
        $costPerUnit = $this->getCostPerUnit($this->user);
        $amountAfterFees = $this->getUserAmountAfterServiceFeeDeduction(
            $this->userTotalAmount,
            $this->user
        );

        Log::debug('Starting token generation with calculated values', [
            'meter_number' => $meter->number,
            'units' => $units,
            'cost_per_unit' => $costPerUnit,
            'amount_after_fees' => $amountAfterFees
        ]);

        // Check if we should use the secondary Prism vend server
        $usePrismVend2 = $meter->use_prism_vend && $this->attempts() >= 2;

        $token = $this->attemptTokenGeneration($meter, $amountAfterFees, $costPerUnit, $units, $usePrismVend2);

        if ($this->isInvalidToken($token)) {
            Log::warning('Invalid token received, attempting meter registration', [
                'meter_number' => $meter->number,
                'token_value' => $token,
                'attempt' => $this->attempts()
            ]);

            $this->registerMeterAndRetry($meter);
            $token = $this->attemptTokenGeneration($meter, $amountAfterFees, $costPerUnit, $units, $usePrismVend2);

            if ($this->isInvalidToken($token)) {
                Log::error('Token generation failed after meter registration', [
                    'meter_number' => $meter->number,
                    'token_value' => $token,
                    'total_attempts' => $this->attempts()
                ]);
                throw new RuntimeException('Failed to generate token after registration');
            }
        }

        return strtok($token, ',');
    }

    private function registerMeterAndRetry(Meter $meter): void
    {
        Log::info('Attempting meter registration', [
            'meter_number' => $meter->number,
            'meter_type' => $meter->prepaid_meter_type,
            'category' => $meter->category,
            'concentrator_id' => $meter->concentrator_id
        ]);

        $this->prepaidMeterService->registerPrepaidMeter(
            $meter->number,
            (int)$meter->prepaid_meter_type,
            MeterCategory::fromValue($meter->category),
            $meter->concentrator_id
        );

        Log::info('Meter registration completed', [
            'meter_number' => $meter->number
        ]);
    }

    private function isInvalidToken(?string $token): bool
    {
        return $token === null || $token === '' || $token === self::TOKEN_ERROR_VALUE;
    }

    private function createMeterToken(string $token): void
    {
        $units = $this->calculateUnits($this->userTotalAmount, $this->user);
        $serviceFee = $this->calculateServiceFee($this->user, $this->userTotalAmount, 'prepay');

        Log::debug('Creating meter token record', [
            'meter_id' => $this->meterId,
            'units' => $units,
            'service_fee' => $serviceFee,
            'transaction_id' => $this->mpesaTransaction->id
        ]);

        MeterToken::create([
            'mpesa_transaction_id' => $this->mpesaTransaction->id,
            'token' => $token,
            'units' => $units,
            'service_fee' => $serviceFee,
            'monthly_service_charge_deducted' => $this->deductions->monthly_service_charge_deducted ?? 0,
            'connection_fee_deducted' => $this->deductions->connection_fee_deducted ?? 0,
            'unaccounted_debt_deducted' => $this->deductions->unaccounted_debt_deducted ?? 0,
            'meter_id' => $this->user->meter_id,
        ]);

    }

    private function updateUserBalance(): void
    {
        Log::debug('Updating user account balance', [
            'user_id' => $this->user->id,
            'amount' => $this->userTotalAmount,
            'transaction_id' => $this->mpesaTransaction->id
        ]);

        $this->updateUserAccountBalance(
            $this->user,
            $this->userTotalAmount,
            $this->deductions,
            $this->mpesaTransaction->id,
            true
        );
    }

    private function sendNotifications(Meter $meter, string $token): void
    {
        $message = $this->buildNotificationMessage($meter, $token);

        Log::debug('Sending user notification', [
            'user_id' => $this->user->id,
            'meter_number' => $meter->number,
            'phone_number' => $this->mpesaTransaction->MSISDN
        ]);

        $this->notifyUser(
            (object)['message' => $message, 'title' => 'Meter Tokens'],
            $this->user,
            'meter tokens',
            $this->mpesaTransaction->MSISDN
        );
    }

    private function buildNotificationMessage(Meter $meter, string $token): string
    {
        $units = $this->calculateUnits($this->userTotalAmount, $this->user);
        $date = Carbon::now()->toDateTimeString();

        return "Meter: {$meter->number}\n"
            . "Token: {$token}\n"
            . "Units: {$units}\n"
            . "Amount: {$this->userTotalAmount}\n"
            . "Account: {$this->user->account_number}\n"
            . "Date: {$date}\n"
            . "Ref: {$this->mpesaTransaction->TransID}";
    }

    private function handleConcentratorCommunication(Meter $meter, string $token): void
    {
        Log::info('Initiating concentrator communication', [
            'meter_number' => $meter->number,
            'concentrator_id' => $meter->concentrator_id
        ]);

        if (!$meter->concentrator) {
            Log::warning('Concentrator not found for meter', [
                'meter_number' => $meter->number,
                'concentrator_id' => $meter->concentrator_id
            ]);
            return;
        }

        $sendResult = $this->concentratorService->sendMeterToken($meter, $token);

        if (!$sendResult) {
            $this->handleFailedTokenSend($meter, $token);
        } else {
            Log::info('Token successfully sent to concentrator', [
                'meter_number' => $meter->number,
                'concentrator_id' => $meter->concentrator_id
            ]);
        }
    }

    private function handleFailedTokenSend(Meter $meter, string $token): void
    {
        $meter->loadMissing('concentrator');
        Log::warning('Failed to send token to concentrator, attempting meter re-registration', [
            'meter_number' => $meter->number,
            'concentrator_id' => $meter->concentrator->concentrator_id
        ]);

        try {
            $this->concentratorService->registerMeterWithConcentrator(
                $meter->number,
                $meter->number,
                $meter->concentrator->concentrator_id
            );

            Log::info('Meter re-registration successful, retrying token send', [
                'meter_number' => $meter->number,
                'concentrator_id' => $meter->concentrator_id
            ]);

            $retryResult = $this->concentratorService->sendMeterToken($meter->number, $token);

            if (!$retryResult) {
                Log::error('Token send failed after meter re-registration', [
                    'meter_number' => $meter->number,
                    'concentrator_id' => $meter->concentrator->concentrator_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error during meter re-registration process', [
                'meter_number' => $meter->number,
                'concentrator_id' => $meter->concentrator->concentrator_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function failed(Throwable $throwable): void
    {
        Log::error('Token generation job failed', [
            'meter_id' => $this->meterId,
            'user_id' => $this->user->id,
            'account_number' => $this->user->account_number,
            'transaction_id' => $this->mpesaTransaction->TransID,
            'total_attempts' => $this->attempts(),
            'error' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString()
        ]);

        $this->notifyUser(
            (object)[
                'message' => "Failed to generate token of Ksh {$this->mpesaTransaction->TransAmount} "
                    . "for your meter, please contact management for help.",
                'title' => 'Meter Token Generation Failure'
            ],
            $this->user,
            'general',
            $this->mpesaTransaction->MSISDN
        );
    }
}
