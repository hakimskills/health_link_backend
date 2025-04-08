<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authenticated user info
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');



// User-related routes (only accessible to authenticated users)
Route::middleware('auth:sanctum')->prefix('user')->group(function() {
    Route::get('/', [UserController::class, 'getAuthenticatedUser']); // GET /api/user
    Route::put('/', [UserController::class, 'updateProfile']);       // PUT /api/user
    Route::delete('/', [UserController::class, 'deleteUser']);      // DELETE /api/user
});

// Posts (public access)
Route::apiResource('posts', PostController::class);

// Admin-only routes (role check is handled inside the controller)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/registration-requests', [AdminController::class, 'getPendingRequests']);
    Route::post('/admin/approve-request/{id}', [AdminController::class, 'approveRequest']);
    Route::post('/admin/reject-request/{id}', [AdminController::class, 'rejectRequest']);
    Route::get('/admin/users', [AdminController::class, 'getUsers']);
    Route::delete('/admin/user/{id}', [AdminController::class, 'deleteUser']);
});

// Store Routes (only accessible to authenticated users)
Route::middleware('auth:sanctum')->group(function () {
    // Store CRUD routes
    Route::apiResource('stores', StoreController::class)->except(['index']);
    
    // Public store listing (with is_mine flag)
    Route::get('/stores', [StoreController::class, 'index']);
    
    // Admin verification endpoint
    Route::post('/stores/{store}/verify', [StoreController::class, 'verify'])
        ->middleware('admin'); // Ensure you have an 'admin' middleware
});

// Public routes (if any)
Route::get('/public/stores', [StoreController::class, 'index']); // Public listing
Route::get('/public/stores/{store}', [StoreController::class, 'show']); // Public view

// Product Routes (only accessible to authenticated users)
Route::middleware('auth:sanctum')->group(function () {
    // Create a product
    Route::post('/product', [ProductController::class, 'store']);
    
    // Get products by store
    Route::get('/products/{store}', [ProductController::class, 'index']);
    
    
    // Show a single product
    Route::get('/product/{product}', [ProductController::class, 'show']);
    
    // Update a product
    Route::put('/product/{product}', [ProductController::class, 'update']);
    
    // Delete a product
    Route::delete('/product/{product}', [ProductController::class, 'destroy']);
});
