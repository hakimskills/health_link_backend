<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class ProductController extends Controller
{
    public function index()
    {
        try {
            $products = Product::with('images')->get();
            return response()->json($products);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function storeUsedEquipment(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'inventory_price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
            'condition' => 'required|string|max:255', // Condition is required for used_equipment
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png|max:10240'
        ]);

        // Explicitly set type to used_equipment
        $validated['type'] = 'used_equipment';

        // Remove images from the validated array as we'll handle them separately
        if (isset($validated['images'])) {
            unset($validated['images']);
        }

        // Create product record
        $product = Product::create($validated);

        // Process images if uploaded
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('product_images', 'public');

                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_path' => asset('storage/' . $path),
                    'is_primary' => $index === 0 // Set the first image as primary
                ]);
            }
        }

        // Load the images relationship for the response
        $product->load('images');

        return response()->json($product, 201);
    }
    

    public function show($id)
    {
        $product = Product::with('images')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'inventory_price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'category' => 'required|string|max:100',
            'type' => 'nullable|in:new,inventory',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png|max:10240'
        ]);

        // Set default type to 'new' if not provided
        $validated['type'] = $validated['type'] ?? 'new';

        // Ensure inventory_price is present when type is inventory
        if ($validated['type'] === 'inventory' && empty($validated['inventory_price'])) {
            return response()->json(['error' => 'Inventory price is required for inventory type.'], 422);
        }
        
        // Remove images from the validated array as we'll handle them separately
        if (isset($validated['images'])) {
            unset($validated['images']);
        }
        
        // Create product record
        $product = Product::create($validated);
        
        // Process images if uploaded
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('product_images', 'public');
                
                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_path' => asset('storage/' . $path),
                    'is_primary' => $index === 0 // Set the first image as primary
                ]);
            }
        }
        
        // Load the images relationship for the response
        $product->load('images');
        
        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
{
    $product = Product::findOrFail($id);

    $validated = $request->validate([
        'store_id' => 'nullable|exists:stores,id',
        'product_name' => 'nullable|string|max:255',
        'description' => 'nullable|string',
        'price' => 'nullable|numeric',
        'inventory_price' => 'nullable|numeric',
        'stock' => 'nullable|integer',
        'category' => 'nullable|string|max:100',
        'type' => 'nullable|in:new,inventory,used_equipment',
        'condition' => 'nullable|string|max:100',
        'images' => 'nullable|array',
        'images.*' => 'image|mimes:jpeg,jpg,png|max:10240',
        'delete_images' => 'nullable|array',
        'delete_images.*' => 'integer|exists:product_images,id',
        'primary_image_id' => 'nullable|integer|exists:product_images,id'
    ]);

    // Check inventory price requirement
    $newType = $validated['type'] ?? $product->type;
    if ($newType === 'inventory' && empty($validated['inventory_price']) && empty($product->inventory_price)) {
        return response()->json(['error' => 'Inventory price is required for inventory type.'], 422);
    }

    // Handle condition: only update if type is used_equipment
    if ($newType === 'used_equipment') {
        $product->condition = $validated['condition'] ?? $product->condition;
    }

    // Remove non-column inputs from validated array
    unset($validated['images'], $validated['delete_images'], $validated['primary_image_id'], $validated['condition']);

    // Update product basic info
    $product->update($validated);

    // Handle image deletions
    if ($request->has('delete_images')) {
        foreach ($request->delete_images as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image && $image->product_id == $product->product_id) {
                $path = str_replace(asset('storage/'), '', $image->image_path);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
                $image->delete();
            }
        }
    }

    // Handle image uploads
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $image) {
            $path = $image->store('product_images', 'public');
            ProductImage::create([
                'product_id' => $product->product_id,
                'image_path' => asset('storage/' . $path),
                'is_primary' => false
            ]);
        }
    }

    // Handle primary image update
    if ($request->has('primary_image_id')) {
        ProductImage::where('product_id', $product->product_id)->update(['is_primary' => false]);
        ProductImage::where('id', $request->primary_image_id)
            ->where('product_id', $product->product_id)
            ->update(['is_primary' => true]);
    }

    return response()->json($product->load('images'));
}


    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        
        // Delete associated images from storage
        foreach ($product->images as $image) {
            // Extract the path from the full URL
            $path = str_replace(asset('storage/'), '', $image->image_path);
            
            // Delete the file if it exists
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
        
        $product->delete(); // This will cascade delete related images due to foreign key
        
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function getProductsByStore($storeId)
    {
        $store = Store::findOrFail($storeId);
        $products = Product::with('images')->where('store_id', $storeId)->get();
        return response()->json($products);
    }

    public function getProductsAndStoreNameByStore($storeId)
    {
        $store = Store::findOrFail($storeId);
        $products = Product::with('images')->where('store_id', $storeId)->get();
        return response()->json([
            'store_name' => $store->store_name,
            'products' => $products
        ]);
    }

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
            'product' => $product->load('images')
        ], 200);
    }
    
    public function setProductPrimaryImage(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        
        $validated = $request->validate([
            'image_id' => 'required|exists:product_images,id',
        ]);
        
        // First, ensure the image belongs to this product
        $image = ProductImage::where('id', $validated['image_id'])
            ->where('product_id', $productId)
            ->firstOrFail();
        
        // Reset all images to non-primary
        ProductImage::where('product_id', $productId)
            ->update(['is_primary' => false]);
            
        // Set the selected image as primary
        $image->is_primary = true;
        $image->save();
        
        return response()->json([
            'message' => 'Primary image updated successfully',
            'product' => $product->load('images')
        ]);
    }
    
    public function deleteProductImage($productId, $imageId)
    {
        $product = Product::findOrFail($productId);
        
        $image = ProductImage::where('id', $imageId)
            ->where('product_id', $productId)
            ->firstOrFail();
        
        // If we're deleting the primary image, make another one primary if available
        $isPrimary = $image->is_primary;
        
        // Extract the path from the full URL
        $path = str_replace(asset('storage/'), '', $image->image_path);
        
        // Delete the file if it exists
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        
        $image->delete();
        
        // If we deleted the primary image, set a new one
        if ($isPrimary) {
            $newPrimaryImage = ProductImage::where('product_id', $productId)->first();
            if ($newPrimaryImage) {
                $newPrimaryImage->is_primary = true;
                $newPrimaryImage->save();
            }
        }
        
        return response()->json([
            'message' => 'Image deleted successfully',
            'product' => $product->load('images')
        ]);
    }
     public function checkOwner($id)
    {
        $product = Product::with('store')->find($id);
    
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }
    
        $isOwner = Auth::id() === $product->store->owner_id;
    
        return response()->json([
            'isOwner' => $isOwner,
            'product_id' => $product->product_id,
            'store_owner_id' => $product->store->owner_id,
            'authenticated_user_id' => Auth::id()
        ]);
    }

}