<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

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
        'price' => 'required|numeric',
        'stock' => 'required|integer',
        'category' => 'required|string|max:100',
    ]);

    // ✅ Handle image upload if exists
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('product_images', 'public');
        $validated['image'] = asset('storage/' . $imagePath);
    }

    $product = Product::create($validated);

    return response()->json($product, 201);
}

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'store_id' => 'exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
        ]);

        $product->update($validated);
        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
    public function getProductsByStore($storeId)
{
    $store = Store::findOrFail($storeId);

    $products = Product::where('store_id', $storeId)->get();

    
}

}
