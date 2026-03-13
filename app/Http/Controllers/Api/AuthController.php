<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller {
    public function login(LoginRequest $request) {
        $credentials = $request->validated();

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::guard('api')->user();

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'user' => $user,
            ],
        ]);
    }

    public function user(Request $request) {
        $user = Auth::guard('api')->user();

        return response()->json([
            'message' => 'User data retrieved',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function refresh() {
        $token = Auth::guard('api')->refresh();

        return response()->json([
            'message' => 'Token refreshed successfully',
            'data' => [
                'access_token' => $token,
            ],
        ]);
    }

    public function logout() {
        Auth::guard('api')->logout();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
