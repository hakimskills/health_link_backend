<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all();
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'store_price' => 'required|numeric', // now we validate store_price
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
        ]);

        // ✅ Handle image upload if exists
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product_images', 'public');
            $validated['image'] = asset('storage/' . $imagePath);
        }

        // Inventory-specific fields should be null when created from store
        $validated['inventory_id'] = null;
        $validated['inventory_price'] = null;

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'store_price' => 'nullable|numeric',
            'inventory_price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
        ]);

        // ✅ Handle image upload if exists
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product_images', 'public');
            $validated['image'] = asset('storage/' . $imagePath);
        }

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            
            // Add authorization check
            $store = $product->store;
            if (!$store || $store->owner_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized to delete this product'], 403);
            }
            
            // Delete associated image
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            
            $product->delete();
            
            return response()->json([
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Product not found'], 404);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Product deletion failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProductsByStore($storeId)
    {
        $store = Store::findOrFail($storeId);

        $products = Product::where('store_id', $storeId)->get();

        return response()->json($products);
    }

    // 🛒 Stock Clearance Function (Move from Store to Inventory)
    public function stockClearance(Request $request)
{
    $validated = $request->validate([
        'store_product_id' => 'required|exists:products,product_id',
        'inventory_id' => 'required|exists:inventory,inventory_id',
        'inventory_price' => 'required|numeric|min:0',
    ]);

    // Using find to directly search by primary key (product_id)
    $product = Product::findOrFail($validated['store_product_id']);

    if (!$product) {
        return response()->json(['message' => 'Product not found'], 404);
    }

    // Switch product ownership to inventory
    $product->store_id = null; // Remove from store
    $product->inventory_id = $validated['inventory_id'];
    $product->inventory_price = $validated['inventory_price'];
    $product->save();

    return response()->json([
        'message' => 'Product moved to inventory successfully',
        'product' => $product
    ], 200);
}

}
