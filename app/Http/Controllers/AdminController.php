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
        // Ensure the user is authenticated and has the 'admin' role
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Find the registration request by ID
        $registrationRequest = RegistrationRequest::find($id);

        // If the request doesn't exist, return an error
        if (!$registrationRequest) {
            return response()->json(['message' => 'Request not found'], 404);
        }

        // Update the request status to approved
        $registrationRequest->status = 'approved';
        $registrationRequest->save();

        // Respond with the updated request data
        return response()->json([
            'message' => 'Request approved successfully',
            'data' => $registrationRequest
        ], 200);
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
}

