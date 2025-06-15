<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RecommendationController extends Controller
{
    public function getRecommendations(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            Log::error('Unauthorized access to recommendations endpoint');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Log::debug('Calling Flask API for recommendations', ['user_id' => $user->id]);

        $client = new Client();
        $maxRetries = 2;
        $retryCount = 0;
        $flaskUrl = 'http://127.0.0.1:5000/recommend';

        while ($retryCount <= $maxRetries) {
            try {
                $response = $client->get($flaskUrl, [
                    'query' => ['user_id' => $user->id],
                    'timeout' => 5, // Reduced timeout to fail faster
                    'connect_timeout' => 5,
                ]);

                $data = json_decode($response->getBody(), true);

                if ($data === null) {
                    Log::error('Failed to decode JSON response from Flask', [
                        'response_body' => (string) $response->getBody()
                    ]);
                    return response()->json([
                        'message' => 'Invalid response from recommendation service'
                    ], 500);
                }

                Log::info('Successfully fetched recommendations from Flask', ['user_id' => $user->id]);
                return response()->json([
                    'status' => 'success',
                    'interacted_products' => $data['interacted_products'],
                    'recommended_products' => $data['recommended_products'],
                ], 200);

            } catch (RequestException $e) {
                $retryCount++;
                Log::warning('Flask API request failed', [
                    'attempt' => $retryCount,
                    'error' => $e->getMessage(),
                ]);

                if ($retryCount > $maxRetries) {
                    Log::error('Flask API error after max retries', ['error' => $e->getMessage()]);
                    return response()->json([
                        'message' => 'Failed to fetch recommendations',
                        'error' => $e->getMessage(),
                    ], 500);
                }
                sleep(1);
            }
        }

        Log::error('No response from Flask API after retries');
        return response()->json(['message' => 'No response from recommendation service'], 500);
    }
}