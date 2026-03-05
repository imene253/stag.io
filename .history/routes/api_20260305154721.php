// routes/api.php — full updated file
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentProfileController;
use App\Http\Controllers\Api\CompanyProfileController;
use App\Http\Controllers\Api\InternshipOfferController;
use App\Http\Controllers\Api\ApplicationController;
use Illuminate\Support\Facades\Route;

// ─── Public ───────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/offers',         [InternshipOfferController::class, 'index']);
Route::get('/offers/{id}',    [InternshipOfferController::class, 'show']);
Route::get('/students/{id}/cv', [StudentProfileController::class, 'showPublic']);
Route::get('/companies/{id}',   [CompanyProfileController::class, 'showPublic']);

// ─── Authenticated ────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── Student ───────────────────────────────────────────
    Route::middleware('role:student')->prefix('student')->group(function () {
        // Profile
        Route::get('/profile', [StudentProfileController::class, 'show']);
        Route::put('/profile', [StudentProfileController::class, 'update']);

        // Applications
        Route::post('/offers/{id}/apply',     [ApplicationController::class, 'apply']);
        Route::get('/applications',           [ApplicationController::class, 'myApplications']);
        Route::delete('/applications/{id}',   [ApplicationController::class, 'cancel']);
    });

    // ── Company ───────────────────────────────────────────
    Route::middleware('role:company')->prefix('company')->group(function () {
        // Profile
        Route::get('/profile', [CompanyProfileController::class, 'show']);
        Route::put('/profile', [CompanyProfileController::class, 'update']);

        // Offers
        Route::get('/offers',            [InternshipOfferController::class, 'myOffers']);
        Route::post('/offers',           [InternshipOfferController::class, 'store']);
        Route::put('/offers/{id}',       [InternshipOfferController::class, 'update']);
        Route::delete('/offers/{id}',    [InternshipOfferController::class, 'destroy']);

        // Applications (on their offers)
        Route::get('/offers/{id}/applications',        [ApplicationController::class, 'offerApplicants']);
        Route::put('/applications/{id}/accept',        [ApplicationController::class, 'accept']);
        Route::put('/applications/{id}/refuse',        [ApplicationController::class, 'refuse']);
    });

    // ── Admin ─────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/applications',                    [ApplicationController::class, 'adminIndex']);
        Route::get('/applications/pending',            [ApplicationController::class, 'pendingValidation']);
        Route::put('/applications/{id}/validate',      [ApplicationController::class, 'validate']);
        Route::put('/applications/{id}/reject',        [ApplicationController::class, 'reject']);
        Route::get('/stats',                           [ApplicationController::class, 'stats']);
    });

});