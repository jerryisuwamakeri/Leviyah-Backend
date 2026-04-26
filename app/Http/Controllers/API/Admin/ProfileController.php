<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $staff = $request->user();
        return response()->json([
            'staff'       => $staff,
            'roles'       => $staff->getRoleNames(),
            'permissions' => $staff->getAllPermissions()->pluck('name'),
        ]);
    }

    public function update(Request $request)
    {
        $staff = $request->user();

        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'phone'      => 'nullable|string|max:20',
            'position'   => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|max:2048']);
            $data['avatar'] = $request->file('avatar')->store('staff', 'public');
        }

        $staff->update($data);

        activity('profile')
            ->causedBy($staff)
            ->log("Profile updated by {$staff->name}");

        return response()->json($staff->fresh());
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $staff = $request->user();

        if (!Hash::check($request->current_password, $staff->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $staff->update(['password' => Hash::make($request->password)]);

        // Revoke all other tokens for security
        $staff->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        activity('profile')
            ->causedBy($staff)
            ->log("Password changed by {$staff->name}");

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
