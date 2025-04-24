<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    // GET: /inventory - List all inventories
    public function index()
    {
        return response()->json(Inventory::all(), 200);
    }

    // GET: /inventory/{id} - Show a specific inventory
    public function show($id)
    {
        $inventory = Inventory::find($id);
        if (!$inventory) {
            return response()->json(['message' => 'Inventory not found'], 404);
        }
        return response()->json($inventory, 200);
    }

    // POST: /inventory - Create a new inventory
    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_id' => 'required|exists:users,id',
            'inventory_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        $inventory = Inventory::create($validated);
        return response()->json($inventory, 201);
    }

    // PUT: /inventory/{id} - Update inventory
    public function update(Request $request, $id)
    {
        $inventory = Inventory::find($id);
        if (!$inventory) {
            return response()->json(['message' => 'Inventory not found'], 404);
        }

        $validated = $request->validate([
            'inventory_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
        ]);

        $inventory->update($validated);
        return response()->json($inventory, 200);
    }

    // DELETE: /inventory/{id} - Delete inventory
    public function destroy($id)
    {
        $inventory = Inventory::find($id);
        if (!$inventory) {
            return response()->json(['message' => 'Inventory not found'], 404);
        }

        $inventory->delete();
        return response()->json(['message' => 'Inventory deleted successfully'], 200);
    }
}
