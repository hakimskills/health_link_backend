<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function index()
    {
        $stores = Store::all();
        return response()->json($stores);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_id' => 'required|exists:users,id',
            'store_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('store_images', 'public');
        }

        $store = Store::create($validated);
        return response()->json($store, 201);
    }

    public function getStoresByUser($userId)
    {
        $stores = Store::where('owner_id', $userId)->get();

        if ($stores->isEmpty()) {
            return response()->json(['message' => 'No stores found for this user.'], 404);
        }

        return response()->json($stores);
    }

    public function show($id)
    {
        $store = Store::findOrFail($id);
        return response()->json($store);
    }

    public function update(Request $request, $id)
    {
        $store = Store::findOrFail($id);

        $validated = $request->validate([
            'owner_id' => 'sometimes|exists:users,id',
            'store_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($store->image) {
                Storage::disk('public')->delete($store->image);
            }

            $validated['image'] = $request->file('image')->store('store_images', 'public');
        }

        $store->update($validated);
        return response()->json($store);
    }

    public function destroy($id)
    {
        $store = Store::findOrFail($id);

        // Delete image if exists
        if ($store->image) {
            Storage::disk('public')->delete($store->image);
        }

        $store->delete();
        return response()->json(['message' => 'Store deleted successfully']);
    }

    public function getUserByStore($storeId)
    {
        $store = Store::with('owner')->findOrFail($storeId);

        if (!$store->owner) {
            return response()->json(['message' => 'Owner not found for this store.'], 404);
        }

        return response()->json($store->owner);
    }
}
