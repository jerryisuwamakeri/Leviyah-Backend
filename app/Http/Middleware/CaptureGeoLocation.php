<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CaptureGeoLocation
{
    public function handle(Request $request, Closure $next)
    {
        $lat = $request->header('X-Latitude');
        $lng = $request->header('X-Longitude');

        if ($lat && $lng) {
            app()->instance('geo.lat', (float) $lat);
            app()->instance('geo.lng', (float) $lng);
        }

        return $next($request);
    }
}
