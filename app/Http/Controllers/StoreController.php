<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

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
        ]);

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
            'owner_id' => 'exists:users,id',
            'store_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
        ]);

        $store->update($validated);
        return response()->json($store);
    }

    public function destroy($id)
    {
        $store = Store::findOrFail($id);
        $store->delete();
        return response()->json(['message' => 'Store deleted successfully']);
    }
}
