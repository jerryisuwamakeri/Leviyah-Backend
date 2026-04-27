<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Activity::saving(function (Activity $activity) {
            $lat = app()->bound('geo.lat') ? app('geo.lat') : null;
            $lng = app()->bound('geo.lng') ? app('geo.lng') : null;

            if ($lat && $lng) {
                $props = $activity->properties->toArray();
                $props['latitude']  = $lat;
                $props['longitude'] = $lng;
                $activity->properties = $props;
            }

            $activity->properties = $activity->properties->merge([
                'ip' => request()->ip(),
            ]);
        });
    }
}
