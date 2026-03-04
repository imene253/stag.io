// routes/api.php — add these to your existing file
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentProfileController;
use App\Http\Controllers\Api\CompanyProfileController;
use App\Http\Controllers\Api\InternshipOfferController;
use Illuminate\Support\Facades\Route;

// ─── Public ───────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/students/{id}/cv', [StudentProfileController::class,  'showPublic']);
Route::get('/companies/{id}',   [CompanyProfileController::class,  'showPublic']);
Route::get('/offers',           [InternshipOfferController::class, 'index']);    // browse all
Route::get('/offers/{id}',      [InternshipOfferController::class, 'show']);     // single offer

// ─── Authenticated ────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── Student ───────────────────────────────────────────
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/profile', [StudentProfileController::class, 'show']);
        Route::put('/profile', [StudentProfileController::class, 'update']);
    });

    // ── Company ───────────────────────────────────────────
    Route::middleware('role:company')->prefix('company')->group(function () {
        Route::get('/profile',      [CompanyProfileController::class,  'show']);
        Route::put('/profile',      [CompanyProfileController::class,  'update']);

        // Offer CRUD
        Route::get('/offers',       [InternshipOfferController::class, 'myOffers']);
        Route::post('/offers',      [InternshipOfferController::class, 'store']);
        Route::put('/offers/{id}',  [InternshipOfferController::class, 'update']);
        Route::delete('/offers/{id}',[InternshipOfferController::class,'destroy']);
    });

    // ── Admin ─────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
     
    });

});