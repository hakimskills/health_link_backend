<?php

namespace App\Http\Controllers;

use App\Models\DigitalProduct;
use Illuminate\Http\Request;

class DigitalProductController extends Controller
{
    // ✅ GET all digital products
    public function index()
    {
        return response()->json(DigitalProduct::all());
    }

    // ✅ GET single product by ID
    public function show($id)
    {
        $product = DigitalProduct::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product);
    }

    // ✅ POST create
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
            'product_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'description' => 'required|string',
            'url' => 'required|url',
        ]);
    
        // Handle file uploads
        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }
        
        if ($request->hasFile('product_image')) {
            $validated['product_image'] = $request->file('product_image')->store('products', 'public');
        }
    
        $product = DigitalProduct::create($validated);
        return response()->json($product, 201);
    }

    // ✅ PUT/PATCH update
    public function update(Request $request, $id)
    {
        $product = DigitalProduct::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'logo' => 'nullable|string',
            'product_image' => 'nullable|string',
            'description' => 'sometimes|required|string',
            'url' => 'sometimes|required|url',
        ]);

        $product->update($validated);
        return response()->json($product);
    }

    // ✅ DELETE
    public function destroy($id)
    {
        $product = DigitalProduct::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->delete();
        return response()->json(['message' => 'Digital product deleted successfully']);
    }
}
