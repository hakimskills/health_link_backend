<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeviceToken;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $user = $request->user();

        // Save or update the token
        $user->deviceTokens()->updateOrCreate(
            ['device_token' => $request->device_token],
            ['device_type' => $request->header('User-Agent')]
        );

        return response()->json(['message' => 'Device token saved successfully']);
    }
}
