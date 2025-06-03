<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImageSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        try {
            $imagePath = $request->file('image')->store('search_images', 'public');
            $fullImagePath = storage_path('app/public/' . $imagePath);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer your-secret-token'
            ])->attach(
                'image', file_get_contents($fullImagePath), $request->file('image')->getClientOriginalName()
            )->post('http://127.0.0.1:5000/search');

            Storage::disk('public')->delete($imagePath);

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to connect to image search API'], 500);
            }

            $result = $response->json();

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 500);
            }

            return response()->json([
                'message' => 'Image search completed',
                'products' => $result['products']
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Search failed: ' . $e->getMessage()], 500);
        }
    }
}