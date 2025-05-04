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

    // ✅ Create new product (as store product)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product_images', 'public');
            $validated['image'] = asset('storage/' . $imagePath);
        }

        $validated['type'] = 'new'; // Default type for newly added store products

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    // ✅ Update product
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
            'type' => 'nullable|in:new,inventory', // Allow optional type change
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product_images', 'public');
            $validated['image'] = asset('storage/' . $imagePath);
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

    // ✅ Stock Clearance (convert product to inventory type)
    public function stockClearance(Request $request)
    {
        $validated = $request->validate([
            'store_product_id' => 'required|exists:products,product_id',
        ]);

        $product = Product::findOrFail($validated['store_product_id']);

        $product->type = 'inventory';
        $product->store_id = null;
        $product->save();

        return response()->json([
            'message' => 'Product marked as inventory successfully',
            'product' => $product
        ], 200);
    }
}
