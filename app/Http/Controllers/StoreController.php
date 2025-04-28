<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    // Get all stores with owner info
    public function index()
    {
        $stores = Store::with(['owner', 'products'])
            ->latest()
            ->paginate(10);

        // Add is_mine flag based on auth user
        $userId = Auth::id();
        $stores->getCollection()->transform(function ($store) use ($userId) {
            $store->is_mine = $store->owner_id === $userId;
            return $store;
        });

        return response()->json([
            'data' => $stores->items(),
            'meta' => [
                'current_page' => $stores->currentPage(),
                'total' => $stores->total(),
                'per_page' => $stores->perPage()
            ]
        ]);
    }

    // Create new store
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'address' => 'required|string|max:500',
            'specialties' => 'required|array',
            'specialties.*' => 'string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('store-logos', 'public');
            $validated['logo_path'] = $path;
        }

        // Set owner as current authenticated user
        $validated['owner_id'] = Auth::id();

        $store = Store::create($validated);
        
        return response()->json([
            'message' => 'Store created successfully',
            'data' => $store->load('owner')
        ], 201);
    }
    public function getStoresByUser($userId)
{
    $stores = Store::where('owner_id', $userId)->get();

    if ($stores->isEmpty()) {
        return response()->json(['message' => 'No stores found for this user.'], 404);
    }

    return response()->json($stores);
}


    // Get single store
    public function show(Store $store)
    {
        $store->load(['owner', 'products']);
        $store->is_mine = $store->owner_id === Auth::id();
        
        return response()->json([
            'data' => $store
        ]);
    }

    // Update store (owner only)
    public function update(Request $request, Store $store)
    {
        // Only owner can update
        if ($store->owner_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|max:255',
            'address' => 'sometimes|required|string|max:500',
            'specialties' => 'sometimes|required|array',
            'specialties.*' => 'string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        // Handle logo update
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($store->logo_path) {
                Storage::disk('public')->delete($store->logo_path);
            }
            $path = $request->file('logo')->store('store-logos', 'public');
            $validated['logo_path'] = $path;
        }

        $store->update($validated);

        return response()->json([
            'message' => 'Store updated successfully',
            'data' => $store->fresh('owner')
        ]);
    }

    public function destroy(Store $store)
    {
        $store->products()->delete(); // Delete related products first
        $store->delete(); // Then delete the store
    
        return response()->json(['message' => 'Store and its products deleted successfully.']);
    }
    

    // Admin verification endpoint
    public function verify(Store $store)
{
    // Only users with Admin role can verify
    if (auth()->user()->role !== 'Admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $store->update(['is_verified' => true]);

    return response()->json([
        'message' => 'Store verified successfully',
        'data' => $store
    ]);
}
}