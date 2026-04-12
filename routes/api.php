<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeaveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public Auth Routes ──────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/google', [AuthController::class, 'googleRedirect']);
        Route::get('/google/callback', [AuthController::class, 'googleCallback']);
    });

    // ── Authenticated Routes ────────────────────────────────────────
    Route::middleware('auth:api')->group(function () {

        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);

        // Leave Requests (accessible by both roles)
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::get('/leaves/{leave}', [LeaveController::class, 'show']);
        Route::get('/leaves/{leave}/attachment', [LeaveController::class, 'downloadAttachment'])
            ->name('api.v1.leaves.attachment');

        // Employee-only: submit leave
        Route::middleware('role:employee')->group(function () {
            Route::post('/leaves', [LeaveController::class, 'store']);
        });

        // Employee: own balance & ledger
        Route::get('/my/balance', [LeaveController::class, 'balance']);
        Route::get('/my/ledger', [LeaveController::class, 'ledger']);

        // Admin-only routes
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/employees', [AdminController::class, 'employees']);
            Route::get('/employees/{user}/balance', [AdminController::class, 'employeeBalance']);
            Route::patch('/leaves/{leave}/review', [LeaveController::class, 'review']);
        });
    });
});
