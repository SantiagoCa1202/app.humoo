<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\BeoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PrepItemController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::get(
        '/documents/{document}/download',
        [DocumentController::class, 'downloadSigned']
    )->middleware('signed')->name('api.documents.download');

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

            Route::get(
                '/documents',
                [DocumentController::class, 'index']
            );

            Route::post(
                '/documents',
                [DocumentController::class, 'store']
            );

            Route::get(
                '/documents/{document}',
                [DocumentController::class, 'show']
            );

            Route::put(
                '/documents/{document}/event-link',
                [DocumentController::class, 'linkEvent']
            );

            Route::get(
                '/documents/{document}/versions',
                [BeoController::class, 'versions']
            );

            Route::get(
                '/documents/{document}/extraction',
                [BeoController::class, 'extraction']
            );

            Route::patch(
                '/documents/{document}/review',
                [BeoController::class, 'review']
            );

            Route::get(
                '/documents/{document}/comparison',
                [BeoController::class, 'comparison']
            );

            Route::get(
                '/menus',
                [MenuController::class, 'index']
            );

            Route::post(
                '/menus',
                [MenuController::class, 'store']
            );

            Route::get(
                '/menus/{menu}',
                [MenuController::class, 'show']
            );

            Route::patch(
                '/menus/{menu}',
                [MenuController::class, 'update']
            );

            Route::get(
                '/menus/{menu}/versions',
                [MenuController::class, 'versions']
            );

            Route::post(
                '/menus/{menu}/duplicate',
                [MenuController::class, 'duplicate']
            );

            Route::get(
                '/recipes/catalog',
                [RecipeController::class, 'catalog']
            );

            Route::get(
                '/recipes',
                [RecipeController::class, 'index']
            );

            Route::post(
                '/recipes',
                [RecipeController::class, 'store']
            );

            Route::get(
                '/recipes/{recipe}',
                [RecipeController::class, 'show']
            );

            Route::patch(
                '/recipes/{recipe}',
                [RecipeController::class, 'update']
            );

            Route::get(
                '/recipes/{recipe}/versions',
                [RecipeController::class, 'versions']
            );

            Route::get(
                '/recipes/{recipe}/versions/{recipeVersion}',
                [RecipeController::class, 'version']
            );

            Route::get(
                '/recipes/{recipe}/versions/{recipeVersion}/comparison',
                [RecipeController::class, 'comparison']
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
