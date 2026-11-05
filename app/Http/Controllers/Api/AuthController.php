<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\AuditService;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $identifier = $request->identifier;
        
        $employee = Employee::where('mobile', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$employee->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact administrator.',
            ], 403);
        }

        // Revoke all previous tokens
        $employee->tokens()->delete();

        $token = $employee->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $employee->load('branch', 'company', 'branches'),
                'token' => $token,
                'mustChangePassword' => $employee->must_change_password,
            ],
            'message' => 'Logged in successfully',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load('branch', 'company', 'branches'),
            'message' => 'Profile retrieved',
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|different:current_password',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false;
        if ($user->is_first_login) {
            $user->is_first_login = false;
        }
        $user->save();

        AuditService::log('change_password', 'auth');

        return response()->json([
            'success' => true,
            'data' => $user->load('branch', 'company', 'branches'),
            'message' => 'Password changed successfully',
        ]);
    }
}
