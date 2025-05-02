<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
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
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/user/update-name', [UserController::class, 'updateName']);
    Route::put('/user/update-email', [UserController::class, 'updateEmail']);
    Route::put('/user/update-phone', [UserController::class, 'updatePhoneNumber']);
    Route::put('/user/update-wilaya', [UserController::class, 'updateWilaya']);
    Route::put('/user/update-password', [UserController::class, 'updatePassword']);
    Route::delete('/user/delete', [UserController::class, 'deleteUser']);
});




// Admin-only routes (role check is handled inside the controller)
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/admin/registration-requests', [AdminController::class, 'getPendingRequests']);
    Route::post('/admin/approve-request/{id}', [AdminController::class, 'approveRequest']);
    Route::post('/admin/reject-request/{id}', [AdminController::class, 'rejectRequest']);
    Route::post('/users/{id}/ban', [AdminController::class, 'banUser']);
    Route::post('/users/{id}/unban', [AdminController::class, 'unbanUser']);
});


// Store Routes (only accessible to authenticated users)
Route::middleware('auth:sanctum')->group(function () {
    // Create a store
    Route::post('/store', [StoreController::class, 'store']);
    Route::post('/profile/upload-image', [UserController::class, 'uploadProfileImage']);
    
    // Get stores by user (owner)
    Route::get('/stores', [StoreController::class, 'index']);
    
    // Show a single store
    Route::get('/store/{store}', [StoreController::class, 'show']);
    
    // Update a store
    Route::put('/store/{store}', [StoreController::class, 'update']);
    
    // Delete a store
    Route::delete('/store/{store}', [StoreController::class, 'destroy']);
    Route::get('/stores/user/{userId}', [StoreController::class, 'getStoresByUser']);
   
});

Route::middleware('auth:sanctum')->group(function () {
    // Create a new inventory
Route::post('/inventory', [InventoryController::class, 'store']);

// Get all inventories
Route::get('/inventory', [InventoryController::class, 'index']);

// Get a single inventory by ID
Route::get('/inventory/{id}', [InventoryController::class, 'show']);

// Update inventory
Route::put('/inventory/{id}', [InventoryController::class, 'update']);
Route::patch('/inventory/{id}', [InventoryController::class, 'update']); // optional

// Delete inventory
Route::delete('/inventory/{id}', [InventoryController::class, 'destroy']);

});

// Product Routes (only accessible to authenticated users)
Route::middleware('auth:sanctum')->group(function () {
    // Create a product
    Route::post('/product', [ProductController::class, 'store']);
    
    // Get products by store
    Route::get('/products/{store}', [ProductController::class, 'index']);
    Route::post('/products/stock-clearance', [ProductController::class, 'stockClearance']);
    
    
    // Show a single product
    Route::get('/product/{product}', [ProductController::class, 'show']);
    
    // Update a product
    Route::put('/product/{product}', [ProductController::class, 'update']);
    
    // Delete a product
    Route::delete('/product/{product}', [ProductController::class, 'destroy']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/product-orders', [ProductOrderController::class, 'store']);         
    Route::get('/product-orders', [ProductOrderController::class, 'index']);           
    Route::get('/product-orders/{id}', [ProductOrderController::class, 'show']);         
    Route::put('/product-orders/{id}', [ProductOrderController::class, 'update']);     
    Route::delete('/product-orders/{id}', [ProductOrderController::class, 'destroy']);  
});
