<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Request;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Activity::saving(static function (Activity $activity) {
            $activity->properties = $activity->properties->put('causer_details', [
                'ip' => Request::ip(),
                'user_agent' => Request::header('user-agent'),
                'url' => Request::fullUrl(),
            ]);
        });

    }
}
