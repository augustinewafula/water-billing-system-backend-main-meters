<?php

namespace App\Traits;

use Illuminate\Support\Collection;

trait initializesDeductionsAmount
{
    /**
     * @return Collection
     */
    private function initializeDeductions(): Collection
    {
        $deductions = new Collection();
        $deductions->monthly_service_charge_deducted = 0;
        $deductions->unaccounted_debt_deducted = 0;
        $deductions->connection_fee_deducted = 0;
        return $deductions;
    }

}
