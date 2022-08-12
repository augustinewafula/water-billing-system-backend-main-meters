<?php

namespace App\Traits;

use App\Http\Requests\CreateMeterBillingRequest;
use Throwable;

trait ProcessMeterReadingsAvailableCredits
{
    use initializesDeductionsAmount;
    use CalculatesUserAmount;
    /**
     * @throws Throwable
     * @throws Throwable
     */
    public function processAvailableCredits($user, $meter_reading): void
    {
        throw_if($user === null, 'RuntimeException', 'Meter user not found');
        $deductions = $this->initializeDeductions();
        if ($this->userHasAccountBalance($user)) {
            $request = new CreateMeterBillingRequest();
            $request->setMethod('POST');
            $request->request->add([
                'meter_id' => $user->meter_id,
                'amount_paid' => 0,
                'deductions' => $deductions,
            ]);

            $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, 0, $deductions);

            $this->processMeterBillings($request, [$meter_reading], $user, $user->last_mpesa_transaction_id, $user_total_amount);
        }
    }

    public function userHasAccountBalance($user): bool
    {
        return $user->account_balance > 0;
    }

}
