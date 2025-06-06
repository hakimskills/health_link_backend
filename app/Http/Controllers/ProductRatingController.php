<?php

namespace App\Http\Controllers;

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
            'product_id' => 'required|exists:products,product_id',
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

        return response()->json(['message' => 'Rating saved', 'data' => $rating], 201);
    }

    // ❌ Delete a rating (optional)
    public function destroy($id)
    {
        $rating = ProductRating::findOrFail($id);

        if ($rating->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rating->delete();

        return response()->json(['message' => 'Rating deleted']);
    }
    public function average($productId)
{
    $average = ProductRating::where('product_id', $productId)->avg('rating');
    return response()->json(['average_rating' => round($average, 1)]);
}
}
