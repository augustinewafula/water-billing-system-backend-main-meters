<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Jobs\ProcessTransaction;
use App\Models\ConnectionFee;
use App\Models\ConnectionFeePayment;
use App\Models\CreditAccount;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\MonthlyServiceCharge;
use App\Models\MpesaTransaction;
use App\Models\UnaccountedDebt;
use App\Models\User;
use App\Traits\ProcessesMpesaTransaction;
use DB;
use JsonException;
use Log;
use Throwable;

class TransactionService
{
    use ProcessesMpesaTransaction;

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function transfer(string $from_account_number, string $to_account_number, string $transaction_id): void
    {
        Log::info('Transferring from ' . $from_account_number . ' to ' . $to_account_number . ' transaction_id ' . $transaction_id);
        DB::transaction(function () use ($from_account_number, $to_account_number, $transaction_id) {
            $this->deleteTransactionFromAccount($from_account_number, $transaction_id);

            $mpesa_transaction = MpesaTransaction::findOrFail($transaction_id);
            $this->debitSenderAccount($from_account_number, $mpesa_transaction->TransAmount);
            $this->creditReceiverAccount($mpesa_transaction, $to_account_number);
        });

    }

    private function deleteTransactionFromAccount(string $account_number, string $transaction_id): void
    {
        Log::info('Deleting transaction ' . $transaction_id . ' from account ' . $account_number);
        $meter_reading_ids = $this->deleteTransactionFromMeterBillings($transaction_id);
        $this->recalculateMeterReadingStatus($meter_reading_ids);

        $connection_fee_ids = $this->deleteTransactionFromConnectionFeePayments($transaction_id);
        $this->recalculateConnectionFeeStatus($connection_fee_ids);

        $this->deleteUnaccountedDebts($transaction_id);
        $this->deleteCreditAccounts($transaction_id);
    }

    private function deleteTransactionFromMeterBillings(string $transaction_id): array
    {
        $meter_billings = MeterBilling::where('mpesa_transaction_id', $transaction_id)
            ->get();
        $meter_reading_ids = $meter_billings->pluck('meter_reading_id')->toArray();

        foreach ($meter_billings as $meter_billing) {
            $meter_billing->forceDelete();
        }

        return $meter_reading_ids;
    }

    private function recalculateMeterReadingStatus(array $meter_reading_ids): void
    {
        foreach ($meter_reading_ids as $meter_reading_id) {
            $this->recalculateMeterReadingStatusForMeterReadingId($meter_reading_id);
        }
    }

    private function recalculateMeterReadingStatusForMeterReadingId(string $meter_reading_id): void
    {
        $meter_reading = MeterReading::findOrFail($meter_reading_id);
        $meter_reading->status = $this->getMeterReadingPaymentStatus($meter_reading);
        $meter_reading->save();
    }

    private function getMeterReadingPaymentStatus(MeterReading $meter_reading): int
    {
        $meter_billings_amount_paid_sum = MeterBilling::where('meter_reading_id', $meter_reading->id)->sum('amount_paid');
        $meter_billings_credit_sum = MeterBilling::where('meter_reading_id', $meter_reading->id)->sum('credit');
        $meter_billings_amount_over_paid_sum = MeterBilling::where('meter_reading_id', $meter_reading->id)->sum('amount_over_paid');

        $meter_billings_total_paid = ($meter_billings_amount_paid_sum + $meter_billings_credit_sum) - $meter_billings_amount_over_paid_sum;

        return match ($meter_billings_total_paid) {
            0 => PaymentStatus::NOT_PAID,
            $meter_reading->bill => PaymentStatus::PAID,
            !0 && !$meter_reading->bill => PaymentStatus::PARTIALLY_PAID,
            default => PaymentStatus::OVER_PAID,
        };

    }

    private function deleteTransactionFromConnectionFeePayments(string $transaction_id): array
    {
        $connection_fee_payments = ConnectionFeePayment::where('mpesa_transaction_id', $transaction_id)
            ->get();
        $connection_fee_ids = $connection_fee_payments->pluck('connection_fee_id')->toArray();
        foreach ($connection_fee_payments as $connection_fee_payment) {
            $connection_fee_payment->forceDelete();
        }

        return $connection_fee_ids;
    }

    private function recalculateConnectionFeeStatus(array $connection_fee_ids): void
    {
        foreach ($connection_fee_ids as $connection_fee_id) {
            $this->recalculateConnectionFeeStatusForConnectionFeeId($connection_fee_id);
        }
    }

    private function recalculateConnectionFeeStatusForConnectionFeeId(string $connection_fee_id): void
    {
        $connection_fee = ConnectionFee::findOrFail($connection_fee_id);
        $connection_fee->status = $this->getConnectionFeePaymentStatus($connection_fee);
        $connection_fee->save();
    }

    private function getConnectionFeePaymentStatus(ConnectionFee $connection_fee): int
    {
        $connection_fee_amount_paid_sum = ConnectionFeePayment::where('connection_fee_id', $connection_fee->connection_fee_id)->sum('amount_paid');
        $connection_fee_credit_sum = ConnectionFeePayment::where('connection_fee_id', $connection_fee->connection_fee_id)->sum('credit');
        $connection_fee_amount_over_paid_sum = ConnectionFeePayment::where('connection_fee_id', $connection_fee->connection_fee_id)->sum('amount_over_paid');
        $connection_fee_total_paid = ($connection_fee_amount_paid_sum + $connection_fee_credit_sum) - $connection_fee_amount_over_paid_sum;
        return match ($connection_fee_total_paid) {
            0 => PaymentStatus::NOT_PAID,
            $connection_fee->amount => PaymentStatus::PAID,
            !0 && !$connection_fee->amount => PaymentStatus::PARTIALLY_PAID,
            default => PaymentStatus::OVER_PAID,
        };
    }

    private function deleteUnaccountedDebts(string $transaction_id): void
    {
        UnaccountedDebt::where('mpesa_transaction_id', $transaction_id)
            ->forceDelete();

    }

    private function deleteCreditAccounts(string $transaction_id): void
    {
        CreditAccount::where('mpesa_transaction_id', $transaction_id)
            ->forceDelete();
    }

    public function debitSenderAccount(string $account_number, string $amount): void
    {
        Log::info('Debiting account ' . $account_number . ' amount ' . $amount);
        $user = User::where('account_number', $account_number)->firstOrFail();
        $user->account_balance -= $amount;
        $user->save();
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function creditReceiverAccount(MpesaTransaction $mpesa_transaction, string $account_number): void
    {
        Log::info('Crediting account ' . $account_number . ' amount ' . $mpesa_transaction->TransAmount);
        $mpesa_transaction->update(['BillRefNumber' => $account_number]);
        $this->processMpesaTransaction($mpesa_transaction);
    }


}
