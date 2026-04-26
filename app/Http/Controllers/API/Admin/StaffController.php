<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $query = Staff::with('roles');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
        }
        if ($request->status) $query->where('status', $request->status);

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:staff',
            'password'    => 'required|string|min:8',
            'phone'       => 'nullable|string|max:20',
            'department'  => 'nullable|string|max:100',
            'position'    => 'nullable|string|max:100',
            'hourly_rate' => 'nullable|numeric|min:0',
            'role'        => 'required|string|exists:roles,name',
        ]);

        $qrToken             = Str::uuid()->toString();
        $data['password']    = Hash::make($data['password']);
        $data['employee_id'] = 'EMP-' . strtoupper(Str::random(6));
        $data['qr_code']     = $qrToken;

        $role = $data['role'];
        unset($data['role']);

        $staff = Staff::create($data);
        $staff->assignRole($role);

        return response()->json($staff->load('roles'), 201);
    }

    public function show(Staff $staff)
    {
        return response()->json($staff->load(['roles', 'attendances' => fn($q) => $q->latest()->limit(30)]));
    }

    public function update(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'department'  => 'nullable|string|max:100',
            'position'    => 'nullable|string|max:100',
            'hourly_rate' => 'nullable|numeric|min:0',
            'status'      => 'sometimes|in:active,inactive,suspended',
            'role'        => 'sometimes|string|exists:roles,name',
        ]);

        if (isset($data['role'])) {
            $staff->syncRoles([$data['role']]);
            unset($data['role']);
        }

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('staff', 'public');
        }

        $staff->update($data);

        return response()->json($staff->fresh()->load('roles'));
    }

    public function destroy(Staff $staff)
    {
        $staff->delete();
        return response()->json(['message' => 'Staff member removed.']);
    }

    public function qrCode(Staff $staff)
    {
        return response()->json([
            'employee_id' => $staff->employee_id,
            'qr_data'     => $staff->qr_code,
            'name'        => $staff->name,
        ]);
    }

    public function regenerateQr(Staff $staff)
    {
        $staff->update(['qr_code' => Str::uuid()->toString()]);
        return $this->qrCode($staff);
    }

    public function attendance(Request $request, Staff $staff)
    {
        $attendances = StaffAttendance::where('staff_id', $staff->id)
            ->when($request->month, fn($q) => $q->whereMonth('date', $request->month))
            ->when($request->year,  fn($q) => $q->whereYear('date', $request->year))
            ->orderByDesc('date')
            ->get();

        $totalHours = $attendances->sum('hours_worked');

        return response()->json([
            'attendances'  => $attendances,
            'total_hours'  => round($totalHours, 2),
            'days_present' => $attendances->count(),
        ]);
    }

    public function clockIn(Request $request)
    {
        $request->validate(['qr_code' => 'required|string']);

        $staff = Staff::where('qr_code', $request->qr_code)
            ->where('status', 'active')
            ->firstOrFail();

        $today = today()->toDateString();

        $attendance = StaffAttendance::where('staff_id', $staff->id)
            ->where('date', $today)->first();

        if ($attendance && $attendance->clock_in_at) {
            return response()->json(['message' => 'Already clocked in today.'], 422);
        }

        $attendance = StaffAttendance::create([
            'staff_id'    => $staff->id,
            'date'        => $today,
            'clock_in_at' => now(),
            'method'      => 'qr_scan',
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'message'    => "Welcome {$staff->name}! Clocked in at " . now()->format('H:i'),
            'staff'      => $staff->only(['id', 'name', 'avatar', 'position']),
            'attendance' => $attendance,
        ]);
    }

    public function clockOut(Request $request)
    {
        $request->validate(['qr_code' => 'required|string']);

        $staff = Staff::where('qr_code', $request->qr_code)
            ->where('status', 'active')
            ->firstOrFail();

        $attendance = StaffAttendance::where('staff_id', $staff->id)
            ->where('date', today())
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->firstOrFail();

        $attendance->update(['clock_out_at' => now()]);
        $attendance->calculateHours();

        return response()->json([
            'message'      => "Goodbye {$staff->name}! Clocked out at " . now()->format('H:i'),
            'hours_worked' => $attendance->fresh()->hours_worked,
            'attendance'   => $attendance->fresh(),
        ]);
    }
}
