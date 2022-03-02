<?php

namespace App\Traits;

use App\Http\Requests\CreateDailyMeterReadingRequest;
use App\Models\DailyMeterReading;

trait StoreDailyMeterReading
{
    public function storeDailyReading(CreateDailyMeterReadingRequest $request): void
    {
        DailyMeterReading::create([
            'meter_id' => $request->meter_id,
            'reading' => $request->reading
        ]);
    }
}
