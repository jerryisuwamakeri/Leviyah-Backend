<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $staff = Staff::where('email', $request->email)->first();

        if (!$staff || !Hash::check($request->password, $staff->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if ($staff->status !== 'active') {
            return response()->json(['message' => 'Your account is inactive.'], 403);
        }

        $staff->update(['last_login_at' => now()]);
        $token = $staff->createToken('staff_token')->plainTextToken;

        return response()->json([
            'staff'  => $staff->load('roles'),
            'token'  => $token,
            'roles'  => $staff->getRoleNames(),
            'permissions' => $staff->getAllPermissions()->pluck('name'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        $staff = $request->user();
        return response()->json([
            'staff'       => $staff,
            'roles'       => $staff->getRoleNames(),
            'permissions' => $staff->getAllPermissions()->pluck('name'),
        ]);
    }
}
