<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::with('creator:id,name')->latest()->get();

        return response()->json([
            'promotions'        => $promotions,
            'active_percentage' => Promotion::activePercentage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:opening,discount,stocks',
            'description' => 'nullable|string|max:500',
            'percentage'  => 'required|numeric|min:0.01|max:100',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
        ]);

        $data['created_by'] = $request->user()->id;

        $promotion = Promotion::create($data);

        activity('promotion')
            ->causedBy($request->user())
            ->performedOn($promotion)
            ->withProperties(['percentage' => $data['percentage']])
            ->log("Promotion '{$data['name']}' created ({$data['percentage']}% off)");

        return response()->json($promotion->load('creator:id,name'), 201);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'type'        => 'sometimes|in:opening,discount,stocks',
            'description' => 'nullable|string|max:500',
            'percentage'  => 'sometimes|numeric|min:0.01|max:100',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date',
        ]);

        $promotion->update($data);

        activity('promotion')
            ->causedBy($request->user())
            ->performedOn($promotion)
            ->log("Promotion '{$promotion->name}' updated");

        return response()->json($promotion->fresh()->load('creator:id,name'));
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();
        return response()->json(['message' => 'Promotion deleted.']);
    }

    public function toggle(Promotion $promotion)
    {
        $promotion->update(['is_active' => !$promotion->is_active]);
        return response()->json($promotion->fresh());
    }

    /** Public: active promotion data for storefront banner */
    public function active()
    {
        $promos = Promotion::where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('percentage')
            ->get(['id', 'name', 'type', 'description', 'percentage', 'ends_at']);

        return response()->json([
            'promotions'        => $promos,
            'active_percentage' => Promotion::activePercentage(),
        ]);
    }
}
