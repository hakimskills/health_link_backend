<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{
    /**
     * Update first name and last name.
     */
    public function updateName(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
        ]);

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
        ]);

        return response()->json(['message' => 'Name updated successfully']);
    }

    /**
     * Update email.
     */
    public function updateEmail(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required|string',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 403);
        }

        $user->update(['email' => $request->email]);

        return response()->json(['message' => 'Email updated successfully']);
    }

    /**
     * Update phone number.
     */
    public function updatePhoneNumber(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'phone_number' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required|string',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 403);
        }

        $user->update(['phone_number' => $request->phone_number]);

        return response()->json(['message' => 'Phone number updated successfully']);
    }

    /**
     * Update wilaya.
     */
    public function updateWilaya(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'wilaya' => 'required|string|max:255',
            'current_password' => 'required|string',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 403);
        }

        $user->update(['wilaya' => $request->wilaya]);

        return response()->json(['message' => 'Wilaya updated successfully']);
    }

    /**
     * Update password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 403);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password updated successfully']);
    }
    public function deleteUser(Request $request)
{
    $request->validate([
        'password' => 'required',
    ]);

    $user = Auth::user();

    // Check if the password is correct
    if (!Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Incorrect password'], 403);
    }

    // Delete the user
    $user->delete();

    return response()->json(['message' => 'User deleted successfully']);
}


public function uploadProfileImage(Request $request)
{
    $request->validate([
        'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    ]);

    $user = $request->user();

    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('profile_images', 'public');

        $user->profile_image = asset('storage/' . $imagePath);
        $user->save();

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'profile_image' => $user->profile_image,
        ], 201);
    }

    return response()->json(['message' => 'No image uploaded.'], 400);
}


public function updateProfile(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $validator = Validator::make($request->all(), [
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,'.$user->id,
        'phone_number' => 'sometimes|string|max:20',
        'wilaya' => 'sometimes|string|max:255',
        'current_password' => 'required|string|min:6',
        'password' => 'sometimes|string|min:6|confirmed'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'The provided password does not match your current password.'
        ], 401);
    }

    try {
        $updateData = $request->only([
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'wilaya'
        ]);

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'user' => $user,
            'message' => 'Profile updated successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update profile',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function getUserById($id)
{
    $user = User::find($id);

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    return response()->json($user);
}


}
