<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            'condition' => 'required|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png|max:10240'
        ]);

        $validated['type'] = 'used_equipment';
        if (isset($validated['images'])) {
            unset($validated['images']);
        }

        $product = Product::create($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('product_images', 'public');
                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_path' => asset('storage/' . $path),
                    'is_primary' => $index === 0
                ]);
            }
        }

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
        \Log::info('Starting product creation', ['request_data' => $request->all()]);

        $validator = \Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'inventory_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'required|string|max:100',
            'type' => 'nullable|in:new,inventory',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png|max:2048'
        ]);

        if ($validator->fails()) {
            \Log::warning('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $validated['type'] = $validated['type'] ?? 'new';

        if ($validated['type'] === 'inventory' && empty($validated['inventory_price'])) {
            \Log::error('Inventory price missing for inventory type');
            return response()->json(['error' => 'Inventory price is required for inventory type.'], 422);
        }

        \DB::beginTransaction();
        try {
            \Log::info('Creating product');
            $product = \App\Models\Product::create($validated);
            \Log::info('Product created', ['product_id' => $product->product_id, 'attributes' => $product->toArray()]);

            $imagePaths = [];
            $imageIds = [];
            $multipartData = [];
            if ($request->hasFile('images') && is_array($request->file('images'))) {
                \Log::info('Processing product images', ['image_count' => count($request->file('images'))]);
                foreach ($request->file('images') as $index => $image) {
                    if ($image->isValid()) {
                        $path = $image->store('product_images', 'public');
                        $fullPath = storage_path('app/public/' . $path);
                        if (!file_exists($fullPath) || !is_readable($fullPath)) {
                            \Log::error('Image file not accessible', ['fullPath' => $fullPath]);
                            throw new \Exception('Image file not accessible: ' . $fullPath);
                        }
                        \Log::info('Image stored', ['path' => $path, 'filename' => $image->getClientOriginalName()]);

                        $productImage = \App\Models\ProductImage::create([
                            'product_id' => $product->product_id,
                            'image_path' => asset('storage/' . $path),
                            'is_primary' => $index === 0
                        ]);
                        \Log::info('ProductImage created', ['image_id' => $productImage->id]);

                        $imagePaths[] = $path;
                        $imageIds[] = $productImage->id;
                        $multipartData[] = [
                            'name' => 'images',
                            'contents' => file_get_contents($fullPath),
                            'filename' => $image->getClientOriginalName()
                        ];
                    }
                }
            }

            \DB::commit();
            \Log::info('Database transaction committed', ['product_id' => $product->product_id]);

            // Ensure ProductImage records are visible to Flask
            \DB::connection()->getPdo()->exec('FLUSH TABLES product_images');

            // Call Flask to extract features
            if (!empty($multipartData)) {
                foreach ($imageIds as $id) {
                    $multipartData[] = [
                        'name' => 'image_ids[]',
                        'contents' => (string)$id
                    ];
                }

                \Log::info('Sending images to Flask for feature extraction', ['image_count' => count($imageIds)]);
                $client = new \GuzzleHttp\Client();
                $maxRetries = 2;
                $retryCount = 0;
                $success = false;

                while ($retryCount < $maxRetries && !$success) {
                    try {
                        \Log::info('Attempting Flask request', ['attempt' => $retryCount + 1]);
                        $response = $client->post('http://127.0.0.1:5000/extract-features', [
                            'multipart' => $multipartData,
                            'timeout' => 30
                        ]);

                        $featureData = json_decode($response->getBody(), true);
                        \Log::info('Flask feature extraction successful', [
                            'response' => $featureData,
                            'image_ids' => $imageIds
                        ]);
                        $success = true;

                        if (!isset($featureData['features']) || !is_array($featureData['features'])) {
                            \Log::warning('No features found in Flask response', ['response' => $featureData]);
                        }
                    } catch (\GuzzleHttp\Exception\RequestException $e) {
                        \Log::warning('Flask request failed', [
                            'error' => $e->getMessage(),
                            'response' => $e->hasResponse() ? (string)$e->getResponse()->getBody() : null,
                            'attempt' => $retryCount + 1
                        ]);
                        $retryCount++;
                        if ($retryCount < $maxRetries) {
                            sleep(1);
                        }
                    }
                }

                if (!$success) {
                    \Log::error('Flask feature extraction failed after retries', ['image_ids' => $imageIds]);
                }
            }

            \Log::info('Product creation completed', ['product_id' => $product->product_id, 'image_count' => count($imageIds)]);
            return response()->json([
                'message' => 'Product created successfully',
                'data' => $product->load('images')
            ], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Product creation failed: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Product creation failed: ' . $e->getMessage()], 500);
        }
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
            'type' => 'nullable|in:new,inventory',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png|max:10240',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer|exists:product_images,id',
            'primary_image_id' => 'nullable|integer|exists:product_images,id'
        ]);

        $newType = $validated['type'] ?? $product->type;
        if ($newType === 'inventory' && empty($validated['inventory_price']) && empty($product->inventory_price)) {
            return response()->json(['error' => 'Inventory price is required for inventory type.'], 422);
        }

        if (isset($validated['images'])) {
            unset($validated['images']);
        }
        if (isset($validated['delete_images'])) {
            unset($validated['delete_images']);
        }
        if (isset($validated['primary_image_id'])) {
            unset($validated['primary_image_id']);
        }

        $product->update($validated);

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

        if ($request->has('primary_image_id')) {
            ProductImage::where('product_id', $product->product_id)
                ->update(['is_primary' => false]);
            ProductImage::where('id', $request->primary_image_id)
                ->where('product_id', $product->product_id)
                ->update(['is_primary' => true]);
        }

        return response()->json($product->load('images'));
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        foreach ($product->images as $image) {
            $path = str_replace(asset('storage/'), '', $image->image_path);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
        $product->delete();
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

        $image = ProductImage::where('id', $validated['image_id'])
            ->where('product_id', $productId)
            ->firstOrFail();

        ProductImage::where('product_id', $productId)
            ->update(['is_primary' => false]);
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

        $isPrimary = $image->is_primary;
        $path = str_replace(asset('storage/'), '', $image->image_path);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        $image->delete();

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
    public function searchByImage(Request $request)
    {
        \Log::info('Starting image-based search', ['request_data' => $request->all()]);

        $validator = \Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png|max:2048'
        ]);

        if ($validator->fails()) {
            \Log::warning('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->file('image')->store('temp', 'public');
            $fullPath = storage_path('app/public/' . $path);
            \Log::info('Query image stored', ['path' => $path]);

            $client = new \GuzzleHttp\Client();
            $response = $client->post('http://127.0.0.1:5000/search', [
                'multipart' => [
                    [
                        'name' => 'image',
                        'contents' => file_get_contents($fullPath),
                        'filename' => $request->file('image')->getClientOriginalName()
                    ]
                ],
                'timeout' => 30
            ]);

            $results = json_decode($response->getBody(), true);
            \Log::info('Flask search response', ['results' => $results]);

            \Storage::disk('public')->delete($path);
            \Log::info('Cleaned up temp file', ['path' => $path]);

            if (!isset($results['matches']) || !is_array($results['matches'])) {
                \Log::warning('No matches found in Flask response', ['response' => $results]);
                return response()->json([
                    'message' => 'No matching products found',
                    'data' => []
                ], 200);
            }

            $imageIds = array_column($results['matches'], 'image_id');
            $products = \App\Models\Product::whereHas('images', function ($query) use ($imageIds) {
                $query->whereIn('id', $imageIds);
            })->with(['images' => function ($query) use ($imageIds) {
                $query->whereIn('id', $imageIds)->select('id', 'product_id', 'image_path', 'is_primary');
            }])->get();

            $resultsWithScores = [];
            foreach ($products as $product) {
                foreach ($product->images as $image) {
                    $match = array_values(array_filter($results['matches'], fn($m) => $m['image_id'] == $image->id))[0] ?? null;
                    if ($match) {
                        $resultsWithScores[] = [
                            'product' => $product->only(['product_id', 'product_name', 'price', 'category', 'type']),
                            'image' => $image->only(['id', 'image_path', 'is_primary']),
                            'distance' => $match['distance']
                        ];
                    }
                }
            }

            // Sort results by distance ascending (most similar first)
            usort($resultsWithScores, function ($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });

            return response()->json([
                'message' => 'Search completed',
                'data' => $resultsWithScores
            ], 200);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error('Flask search failed', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? (string)$e->getResponse()->getBody() : null
            ]);
            return response()->json([
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Search failed: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}



?>