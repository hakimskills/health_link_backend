<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // ✅ Fetch all products
    public function index()
    {
        try {
            $products = Product::all();
            return response()->json($products);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function show($id)
{
    $product = Product::find($id);

    if (!$product) {
        return response()->json(['message' => 'Product not found'], 404);
    }

    return response()->json($product);
}

    // ✅ Create new product (as store product)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'price' => 'required|numeric',
            'inventory_price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
            'type' => 'nullable|in:new,inventory',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product_images', 'public');
            $validated['image'] = asset('storage/' . $imagePath);
        }

        // Set default type to 'new' if not provided
        $validated['type'] = $validated['type'] ?? 'new';

        // Ensure inventory_price is present when type is inventory
        if ($validated['type'] === 'inventory' && empty($validated['inventory_price'])) {
            return response()->json(['error' => 'Inventory price is required for inventory type.'], 422);
        }

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    // ✅ Update product
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'price' => 'nullable|numeric',
            'inventory_price' => 'nullable|numeric',
            'stock' => 'nullable|integer',
            'category' => 'required|string|max:100',
            'type' => 'nullable|in:new,inventory',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product_images', 'public');
            $validated['image'] = asset('storage/' . $imagePath);
        }

        // Ensure inventory_price is present when type is inventory
        $newType = $validated['type'] ?? $product->type;
        if ($newType === 'inventory' && empty($validated['inventory_price']) && empty($product->inventory_price)) {
            return response()->json(['error' => 'Inventory price is required for inventory type.'], 422);
        }

        $product->update($validated);

        return response()->json($product);
    }

    // ✅ Delete product
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    // ✅ Get products by store
    public function getProductsByStore($storeId)
    {
        $store = Store::findOrFail($storeId);
        $products = Product::where('store_id', $storeId)->get();

        return response()->json($products);
    }
public function getProductsAndStoreNameByStore($storeId)
{
    $store = Store::findOrFail($storeId);
    $products = Product::where('store_id', $storeId)->get();

    return response()->json([
        'store_name' => $store->store_name,
        'products' => $products
    ]);
}




    // ✅ Stock Clearance (convert product to inventory type)
    public function stockClearance(Request $request)
    {
        $validated = $request->validate([
            'store_product_id' => 'required|exists:products,product_id',
            'inventory_price' => 'required|numeric',
        ]);

        $product = Product::findOrFail($validated['store_product_id']);
        $product->type = 'inventory';
        $product->inventory_price = $validated['inventory_price'];
        $product->save();

        return response()->json([
            'message' => 'Product marked as inventory successfully',
            'product' => $product
        ], 200);
    }
}
