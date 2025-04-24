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
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'required|string|max:100',
        ]);
    
        // Handle file upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('product-images', 'public');
            $validated['image_path'] = $path;
        }
    
        $product = Product::create($validated);
        
        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
            'image_url' => isset($path) ? asset("storage/$path") : null
        ], 201);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'store_id' => 'sometimes|exists:stores,id',
            'product_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'category' => 'sometimes|string|max:100',
        ]);

        // Handle file upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            
            $path = $request->file('image')->store('product-images', 'public');
            $validated['image_path'] = $path;
        }

        $product->update($validated);
        
        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product,
            'image_url' => $product->image_path ? asset("storage/{$product->image_path}") : null
        ]);
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
    
        return response()->json([
            'store' => $store->name,
            'products' => $products,
        ]);
    }
}