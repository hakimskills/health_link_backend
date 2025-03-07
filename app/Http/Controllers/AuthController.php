<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RegistrationRequest;

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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:registration_requests,email|max:255|unique:users,email',
            'phone_number' => 'required|string|max:20|unique:registration_requests,phone_number|unique:users,phone_number',
            'wilaya' => 'required|string|max:255',
            'role' => 'required|in:Healthcare Professional,Supplier',
            'password' => 'required|string|min:6|confirmed',
        ]);
    
        RegistrationRequest::create([
            'first_name' => $fields['first_name'],
            'last_name' => $fields['last_name'],
            'email' => $fields['email'],
            'phone_number' => $fields['phone_number'],
            'wilaya' => $fields['wilaya'],
            'role' => $fields['role'],
            'password' => Hash::make($fields['password']),
            'status' => 'pending',
        ]);
    
        return response()->json([
            'message' => 'Your registration request has been submitted. An admin will review it.'
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
