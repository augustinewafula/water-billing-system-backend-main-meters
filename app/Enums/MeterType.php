<?php

namespace App\Enums;

enum MeterType: string
{
    case Water = 'water';
    case Energy = 'energy';

    /**
     * Get human-readable description for the enum case.
     */
    public function description(): string
    {
        return match ($this) {
            self::Water => 'Water Meter',
            self::Energy => 'Energy Meter',
        };
    }

    /**
     * Get an array of just the values (e.g., ['water', 'energy']).
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get associative array of values => descriptions.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $type) => [$type->value => $type->description()])
            ->toArray();
    }
}
