<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RecommendationController extends Controller
{
   

    public function index()
    {
        // Get the authenticated user
        $user = Auth::user();
        $userId = $user->id;
        $userName = $user->first_name . ' ' . $user->last_name;

        // Path to Python script and CSV
        $pythonScript = base_path('scripts/recommend.py');
        $csvPath = base_path('scripts/doctor_product_dataset_50x50.csv');

        // Run the Python script
        $process = new Process(['C:\\ProgramData\\anaconda3\\python.exe', $pythonScript, $userId, $csvPath]);
        $process->run();

        // Check if the process was successful
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Parse the Python script output (assuming it returns JSON)
        $output = json_decode($process->getOutput(), true);

        // Return response
        return response()->json([
            'user_id' => $userId,
            'user_name' => $userName,
            'purchased' => $output['purchased'] ?? [],
            'similar_users' => $output['similar_users'] ?? [],
            'recommendations' => $output['recommendations'] ?? []
        ]);
    }
}