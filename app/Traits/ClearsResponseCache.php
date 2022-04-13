<?php
namespace App\Traits;

use Spatie\ResponseCache\Facades\ResponseCache;

trait ClearsResponseCache
{
    public static function bootClearsResponseCache(): void
    {
        self::created(static function ($model) {
            ResponseCache::clear([class_basename($model)]);
        });

        self::updated(static function ($model) {
            ResponseCache::clear([class_basename($model)]);
        });

        self::deleted(static function ($model) {
            ResponseCache::clear([class_basename($model)]);
        });
    }
}
