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
            $name = '';
            $email = '';

            $authenticated_user = auth()->guard('api')->user();
            if ($authenticated_user){
                $name = $authenticated_user->name;
                $email = $authenticated_user->email;
            }
            $activity->properties = $activity->properties->put('causer_details', [
                'name' => $name,
                'email' => $email,
                'ip' => Request::ip(),
                'user_agent' => Request::header('user-agent'),
                'url' => Request::fullUrl(),
            ]);
        });

    }
}
