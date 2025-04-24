<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegistrationStatusMail;

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

        // Send approval email
        Mail::to($request->email)->send(new RegistrationStatusMail('approved', $request->first_name, $request->last_name));

        // Delete the request since it's now a user
        $request->delete();

        return response()->json([
            'message' => 'User approved successfully and email sent',
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

        try {
            $request = RegistrationRequest::findOrFail($id);

            // Send rejection email
            Mail::to($request->email)->send(new RegistrationStatusMail('rejected', $request->first_name, $request->last_name));

            // Delete the request
            $request->delete();

            return response()->json(['message' => 'Registration request rejected and email sent']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error occurred while processing the request: ' . $e->getMessage()], 500);
        }
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

        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error occurred while processing the request: ' . $e->getMessage()], 500);
        }
    }
    public function banUser($id)
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }
    
        $user = User::findOrFail($id);
    
        if ($user->banned) {
            return response()->json(['message' => 'User is already banned.']);
        }
    
        $user->banned = true;
        $user->save();
    
        // Optional: Notify the user via email
        // Mail::to($user->email)->send(new UserBannedMail($user));
    
        return response()->json(['message' => 'User has been banned successfully.']);
    }
    public function unbanUser($id)
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }
    
        $user = User::findOrFail($id);
    
        if (!$user->banned) {
            return response()->json(['message' => 'User is not banned.']);
        }
    
        $user->banned = false;
        $user->save();
    
        return response()->json(['message' => 'User has been unbanned successfully.']);
    }
    
    
}

