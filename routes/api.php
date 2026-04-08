<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyProfileController;
use App\Http\Controllers\Api\ConventionController;
use App\Http\Controllers\Api\InternshipOfferController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\StudentProfileController;
use Illuminate\Support\Facades\Route;

// ─── Public ───────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/create-admin', [AuthController::class, 'createAdmin']); // utility endpoint
Route::get('/offers',         [InternshipOfferController::class, 'index']);
Route::get('/offers/{id}',    [InternshipOfferController::class, 'show']);
Route::get('/students/{id}/cv', [StudentProfileController::class, 'showPublic']);
Route::get('/companies/{id}',   [CompanyProfileController::class, 'showPublic']);

// ─── Authenticated ────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Download (student + company + admin)
    Route::get('/conventions/{id}/download', [ConventionController::class, 'download']);

    // ── Student ───────────────────────────────────────────
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/profile',    [StudentProfileController::class, 'show']);
        Route::put('/profile',    [StudentProfileController::class, 'update']);
        Route::post('/offers/{id}/apply',   [ApplicationController::class, 'apply']);
        Route::get('/applications',         [ApplicationController::class, 'myApplications']);
        Route::delete('/applications/{id}', [ApplicationController::class, 'cancel']);
        Route::get('/convention',           [ConventionController::class,  'myConvention']);
    });

    // ── Company ───────────────────────────────────────────
    Route::middleware('role:company')->prefix('company')->group(function () {
        Route::get('/profile', [CompanyProfileController::class, 'show']);
        Route::put('/profile', [CompanyProfileController::class, 'update']);
        Route::get('/offers',             [InternshipOfferController::class, 'myOffers']);
        Route::post('/offers',            [InternshipOfferController::class, 'store']);
        Route::put('/offers/{id}',        [InternshipOfferController::class, 'update']);
        Route::delete('/offers/{id}',     [InternshipOfferController::class, 'destroy']);
        Route::get('/offers/{id}/applications', [ApplicationController::class, 'offerApplicants']);
        Route::put('/applications/{id}/accept', [ApplicationController::class, 'accept']);
        Route::put('/applications/{id}/refuse', [ApplicationController::class, 'refuse']);
        Route::get('/conventions',        [ConventionController::class, 'companyConventions']);
    });

    // ── Admin ─────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Company approvals
        Route::get('/companies/pending',            [AuthController::class, 'pendingCompanies']);
        Route::put('/companies/{id}/approve',       [AuthController::class, 'approveCompany']);
        Route::put('/companies/{id}/reject',        [AuthController::class, 'rejectCompany']);

        // Applications
        Route::get('/applications',               [ApplicationController::class, 'adminIndex']);
        Route::get('/applications/pending',       [ApplicationController::class, 'pendingValidation']);
        Route::put('/applications/{id}/validate', [ApplicationController::class, 'validate']);   // auto-generates PDF
        Route::put('/applications/{id}/reject',   [ApplicationController::class, 'reject']);
        Route::get('/stats',                      [ApplicationController::class, 'stats']);

        // Conventions
        Route::get('/conventions',                        [ConventionController::class, 'index']);
        Route::post('/applications/{id}/generate',        [ConventionController::class, 'generate']);       // manual generate/regenerate
    });

});