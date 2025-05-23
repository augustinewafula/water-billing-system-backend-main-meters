<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\MonthlyServiceCharge;
use App\Models\User;
use Carbon\Carbon;

class GenerateMonthlyServiceChargeAction
{
    /**
     * Execute the action to create a new monthly service charge
     *
     * @param array $data
     * @return MonthlyServiceCharge
     */
    public function execute(array $data): MonthlyServiceCharge
    {
        return MonthlyServiceCharge::create([
            'user_id' => $data['user_id'],
            'service_charge' => $data['service_charge'],
            'month' => $data['month'],
            'status' => $data['status'] ?? PaymentStatus::NOT_PAID,
        ]);
    }
}
