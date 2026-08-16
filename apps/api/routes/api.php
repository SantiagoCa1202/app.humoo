<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PrepItemController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::prefix('auth')->group(function () {
        Route::post(
            '/login',
            [AuthController::class, 'login']
        );
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get(
            '/me',
            [AuthController::class, 'me']
        );

        Route::get(
            '/workspaces',
            [WorkspaceController::class, 'index']
        );

        Route::get(
            '/auth/sessions',
            [SessionController::class, 'index']
        );

        Route::post(
            '/auth/logout',
            [AuthController::class, 'logout']
        );

        Route::middleware('workspace')->group(function () {
            Route::get(
                '/workspaces/current',
                [WorkspaceController::class, 'current']
            );

            Route::get(
                '/workspaces/current/members',
                [WorkspaceController::class, 'members']
            );

            Route::get(
                '/workspaces/current/roles',
                [WorkspaceController::class, 'roles']
            );

            Route::get(
                '/audit-logs',
                [AuditLogController::class, 'index']
            );

            Route::get(
                '/events',
                [EventController::class, 'index']
            );

            Route::post(
                '/events',
                [EventController::class, 'store']
            );

            Route::get(
                '/events/{event}',
                [EventController::class, 'show']
            );

            Route::patch(
                '/prep-items/{item}',
                [PrepItemController::class, 'update']
            );
        });
    });
});
