<?php

use Illuminate\Support\Facades\Route;

// API routes
Route::prefix('api')->middleware('api')->group(function () {
    // simple test route
    Route::get('ping', function () {
        return response()->json(['message' => 'pong']);
    });

    // Add your API routes here, for example:
    // Route::get('users', [\App\Http\Controllers\UserController::class, 'index']);
});
