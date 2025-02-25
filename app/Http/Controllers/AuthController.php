<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|string|min:6|confirmed'
        ]);

        // Create user and hash password
        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password'])
        ]);

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * Login user and return token
     */
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required|string|min:6'
        ]);

        // Find user by email
        $user = User::where('email', $fields['email'])->first();

        // Check password
        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 200);
    }

    /**
     * Logout user (revoke tokens)
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'You have been logged out'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function userProfile(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }
}
