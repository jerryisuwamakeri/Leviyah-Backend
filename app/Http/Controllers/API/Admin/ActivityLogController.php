<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('causer')->latest();

        if ($request->log_name)  $query->where('log_name', $request->log_name);
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->causer_id) $query->where('causer_id', $request->causer_id);

        return response()->json($query->paginate($request->per_page ?? 30));
    }
}
