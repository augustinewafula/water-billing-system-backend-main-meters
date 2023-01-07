<?php

namespace App\Actions;

use App\Models\Meter;
use App\Models\UnreadMeter;
use App\Rules\uniqueMeterReadingMonth;
use App\Rules\uniqueUnreadMeterMonth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class GenerateUnreadMeters
{
    public function execute(Carbon $month): void
    {
        \Log::info('Generating unread meters for ' . $month->format('F Y'));
        $meters = Meter::notPrepaid()
            ->whereDoesntHave('meter_readings', static function ($query) use ($month) {
            $query->whereMonth('month', $month->month);
        })->get();
        \Log::info('Meters without readings', ['meters' => $meters->pluck('id')]);

        foreach ($meters as $meter) {
            $data = [
                'meter_id' => $meter->id,
                'month' => $month->startOfMonth()->format('Y-m'),
                'reason' => 'Failed to obtain meter reading',
            ];
            try {
                $validator = Validator::make($data, [
                    'month' => [new uniqueUnreadMeterMonth(), new uniqueMeterReadingMonth()],
                ]);
                if ($validator->fails()) {
                    continue;
                }
                UnreadMeter::create($data);
            } catch (\Exception $e) {
                \Log::error('Error generating unread meter', ['error' => $e->getMessage()]);
            }
        }
    }

}
