<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
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

// Posts (public access)
Route::apiResource('posts', PostController::class);

// Admin-only routes (role check is handled inside the controller)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/registration-requests', [AdminController::class, 'getPendingRequests']);
    Route::post('/admin/approve-request/{id}', [AdminController::class, 'approveRequest']);
    Route::post('/admin/reject-request/{id}', [AdminController::class, 'rejectRequest']);
});
