<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\PrepItemController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::prefix('auth')->group(function () {
        Route::post(
            '/register',
            [AuthController::class, 'register']
        )->middleware('throttle:auth-login');

        Route::post(
            '/login',
            [AuthController::class, 'login']
        )->middleware('throttle:auth-login');

        Route::post(
            '/forgot-password',
            [AuthController::class, 'forgotPassword']
        )->middleware('throttle:auth-password-reset');

        Route::post(
            '/reset-password',
            [AuthController::class, 'resetPassword']
        )->middleware('throttle:auth-password-reset');
    });

    Route::get(
        '/invitations/{token}',
        [InvitationController::class, 'show']
    );

    Route::middleware([
        'auth:sanctum',
        'active.session',
    ])->group(function () {
        Route::get(
            '/auth/me',
            [AuthController::class, 'me']
        );

        Route::get(
            '/me',
            [AuthController::class, 'me']
        );

        Route::get(
            '/workspaces',
            [WorkspaceController::class, 'index']
        );

        Route::post(
            '/workspaces',
            [WorkspaceController::class, 'store']
        );

        Route::get(
            '/auth/sessions',
            [SessionController::class, 'index']
        );

        Route::delete(
            '/auth/sessions/{session}',
            [SessionController::class, 'destroy']
        );

        Route::post(
            '/auth/logout',
            [AuthController::class, 'logout']
        );

        Route::post(
            '/invitations/accept',
            [InvitationController::class, 'accept']
        );

        Route::middleware('workspace')->group(function () {
            Route::get(
                '/workspaces/current',
                [WorkspaceController::class, 'current']
            );

            Route::patch(
                '/workspaces/current',
                [WorkspaceController::class, 'update']
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
                '/workspaces/current/invitations',
                [InvitationController::class, 'index']
            );

            Route::post(
                '/workspaces/current/invitations',
                [InvitationController::class, 'store']
            );

            Route::delete(
                '/workspaces/current/invitations/{invitation}',
                [InvitationController::class, 'destroy']
            );

            Route::patch(
                '/workspaces/current/members/{membership}',
                [MemberController::class, 'update']
            );

            Route::delete(
                '/workspaces/current/members/{membership}',
                [MemberController::class, 'destroy']
            );

            Route::get(
                '/audit-logs',
                [AuditLogController::class, 'index']
            );

            Route::apiResource(
                'clients',
                ClientController::class
            );

            Route::apiResource(
                'contacts',
                ContactController::class
            );

            Route::apiResource(
                'venues',
                VenueController::class
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
                '/events/{event}',
                [EventController::class, 'update']
            );

            Route::delete(
                '/events/{event}',
                [EventController::class, 'destroy']
            );

            Route::patch(
                '/prep-items/{item}',
                [PrepItemController::class, 'update']
            );
        });
    });
});
