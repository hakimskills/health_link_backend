<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductRating;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;


class ProductRatingController extends Controller
{
    // 🔘 Get all ratings for a product
    public function index($productId)
    {
        $ratings = ProductRating::with('user')
            ->where('product_id', $productId)
            ->latest()
            ->get();

        return response()->json($ratings);
    }

    // ➕ Store or update rating
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        $user = Auth::user();

        $rating = ProductRating::updateOrCreate(
            [
                'product_id' => $request->product_id,
                'user_id' => $user->id,
            ],
            [
                'rating' => $request->rating,
                'review' => $request->review,
            ]
        );

        // Update average_rating in products table
        $this->updateAverageRating($request->product_id);

        return response()->json(['message' => 'Rating saved', 'data' => $rating], 201);
    }

    // ❌ Delete a rating
    public function destroy($id)
    {
        $rating = ProductRating::findOrFail($id);

        if ($rating->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $productId = $rating->product_id;
        $rating->delete();

        // Update average_rating in products table
        $this->updateAverageRating($productId);

        return response()->json(['message' => 'Rating deleted']);
    }

    // 📊 Get average rating for a product
    public function average($productId)
    {
        $average = ProductRating::where('product_id', $productId)->avg('rating');
        return response()->json(['average_rating' => $average ? round($average, 1) : null]);
    }

    // 🔄 Update average_rating in products table
    protected function updateAverageRating($productId)
    {
        $average = ProductRating::where('product_id', $productId)->avg('rating');
        Product::where('id', $productId)->update(['average_rating' => $average ? round($average, 1) : null]);
    }
}