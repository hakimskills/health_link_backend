<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Get all pending registration requests.
     */
    public function getPendingRequests()
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }

        $requests = RegistrationRequest::all();
        return response()->json(['requests' => $requests]);
    }

    /**
     * Approve a registration request and convert it into a user.
     */
    public function approveRequest($id)
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }

        $request = RegistrationRequest::findOrFail($id);

        // Create a new user
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'wilaya' => $request->wilaya,
            'role' => $request->role,
            'password' => $request->password, // Already hashed during registration
        ]);

        // Delete the request since it's now a user
        $request->delete();

        return response()->json([
            'message' => 'User approved successfully',
            'user' => $user
        ]);
    }

    /**
     * Reject a registration request.
     */
    public function rejectRequest($id)
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }

        $request = RegistrationRequest::findOrFail($id);
        $request->delete();

        return response()->json(['message' => 'Registration request rejected']);
    }

    /**
     * Get all users.
     */
    public function getUsers()
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }

        $users = User::all();
        return response()->json(['users' => $users]);
    }

    /**
     * Delete a user.
     */
    public function deleteUser($id)
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
