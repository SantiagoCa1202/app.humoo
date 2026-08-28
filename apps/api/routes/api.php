<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatActionController;
use App\Http\Controllers\Api\V1\CommandCenterController;
use App\Http\Controllers\Api\V1\ConfirmationController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ExtractionWorkerController;
use App\Http\Controllers\Api\V1\BeoController;
use App\Http\Controllers\Api\V1\BeoDomainController;
use App\Http\Controllers\Api\V1\OperationalVisibilityController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OpenAIHealthController;
use App\Http\Controllers\Api\V1\PrepItemController;
use App\Http\Controllers\Api\V1\PrepListController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\StationController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::prefix('internal/extraction-jobs')
        ->middleware('extraction.worker')
        ->group(function () {
            Route::post('/claim', [ExtractionWorkerController::class, 'claim']);
            Route::post('/{run}/heartbeat', [ExtractionWorkerController::class, 'heartbeat']);
            Route::get('/{run}/document', [ExtractionWorkerController::class, 'download']);
            Route::post('/{run}/result', [ExtractionWorkerController::class, 'result']);
            Route::post('/{run}/failure', [ExtractionWorkerController::class, 'failure']);
        });

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
            '/account',
            [AccountController::class, 'show']
        );

        Route::patch(
            '/account',
            [AccountController::class, 'update']
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
                '/billing',
                [BillingController::class, 'index']
            );

            Route::get(
                '/billing/plans',
                [BillingController::class, 'plans']
            );

            Route::get(
                '/billing/invoices',
                [BillingController::class, 'invoices']
            );

            Route::get('/search', GlobalSearchController::class);

            Route::get(
                '/command-center',
                CommandCenterController::class
            );

            Route::get(
                '/notifications',
                [NotificationController::class, 'index']
            );

            Route::get(
                '/notifications/unread-count',
                [NotificationController::class, 'unreadCount']
            );

            Route::patch(
                '/notifications/{notification}/read',
                [NotificationController::class, 'read']
            );

            Route::post(
                '/notifications/read-all',
                [NotificationController::class, 'readAll']
            );

            Route::get(
                '/notification-preferences',
                [NotificationController::class, 'preferences']
            );

            Route::patch(
                '/notification-preferences/{eventKey}',
                [NotificationController::class, 'updatePreference']
            );

            Route::get(
                '/chat',
                [ChatController::class, 'show']
            );

            Route::get(
                '/chat/conversations',
                [ChatController::class, 'history']
            );

            Route::delete(
                '/chat/conversations/{conversationId}',
                [ChatController::class, 'destroy']
            );

            Route::post(
                '/chat/messages',
                [ChatController::class, 'send']
            );

            Route::post(
                '/chat/actions',
                ChatActionController::class
            );

            Route::get(
                '/internal/ai/health',
                OpenAIHealthController::class
            );

            Route::post(
                '/confirmations/{token}/confirm',
                [ConfirmationController::class, 'confirm']
            );

            Route::post(
                '/confirmations/{token}/cancel',
                [ConfirmationController::class, 'cancel']
            );

            Route::post(
                '/confirmations/{token}/reject',
                [ConfirmationController::class, 'reject']
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

            Route::post(
                '/documents/{document}/extraction/retry',
                [DocumentController::class, 'retryExtraction']
            );

            Route::patch(
                '/documents/{document}/review',
                [BeoController::class, 'review']
            );

            Route::get(
                '/documents/{document}/comparison',
                [BeoController::class, 'comparison']
            );

            Route::get('/beo-import-batches', [BeoDomainController::class, 'batches']);
            Route::post('/beo-import-batches', [BeoDomainController::class, 'storeBatch']);
            Route::get('/beo-import-batches/{batch}', [BeoDomainController::class, 'showBatch']);
            Route::get('/event-orders', [BeoDomainController::class, 'orders']);
            Route::get('/event-orders/{beo}', [BeoDomainController::class, 'showOrder']);
            Route::get('/event-orders/{beo}/versions', [BeoDomainController::class, 'versions']);
            Route::get('/event-functions', [BeoDomainController::class, 'functions']);
            Route::get('/operational-visibility', [OperationalVisibilityController::class, 'show']);
            Route::patch('/operational-visibility', [OperationalVisibilityController::class, 'update']);

            Route::get(
                '/teams',
                [TeamController::class, 'index']
            );

            Route::post(
                '/teams',
                [TeamController::class, 'store']
            );

            Route::get(
                '/teams/{team}',
                [TeamController::class, 'show']
            );

            Route::patch(
                '/teams/{team}',
                [TeamController::class, 'update']
            );

            Route::delete(
                '/teams/{team}',
                [TeamController::class, 'destroy']
            );

            Route::put(
                '/teams/{team}/members',
                [TeamController::class, 'syncMembers']
            );

            Route::get(
                '/stations',
                [StationController::class, 'index']
            );

            Route::post(
                '/stations',
                [StationController::class, 'store']
            );

            Route::get(
                '/stations/{station}',
                [StationController::class, 'show']
            );

            Route::patch(
                '/stations/{station}',
                [StationController::class, 'update']
            );

            Route::delete(
                '/stations/{station}',
                [StationController::class, 'destroy']
            );

            Route::get(
                '/availability',
                [AvailabilityController::class, 'index']
            );

            Route::put(
                '/availability/{membership}',
                [AvailabilityController::class, 'sync']
            );

            Route::get(
                '/shifts',
                [ShiftController::class, 'index']
            );

            Route::post(
                '/shifts',
                [ShiftController::class, 'store']
            );

            Route::get(
                '/shifts/{shift}',
                [ShiftController::class, 'show']
            );

            Route::patch(
                '/shifts/{shift}',
                [ShiftController::class, 'update']
            );

            Route::delete(
                '/shifts/{shift}',
                [ShiftController::class, 'destroy']
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

            Route::post(
                '/events/{event}/cancel',
                [EventController::class, 'cancel']
            );

            Route::delete(
                '/events/{event}',
                [EventController::class, 'destroy']
            );

            Route::patch(
                '/prep-items/{item}',
                [PrepItemController::class, 'update']
            );

            Route::get(
                '/tasks',
                [TaskController::class, 'index']
            );

            Route::post(
                '/tasks',
                [TaskController::class, 'store']
            );

            Route::get(
                '/tasks/{task}',
                [TaskController::class, 'show']
            );

            Route::patch(
                '/tasks/{task}',
                [TaskController::class, 'update']
            );

            Route::delete(
                '/tasks/{task}',
                [TaskController::class, 'destroy']
            );

            Route::get(
                '/prep-lists',
                [PrepListController::class, 'index']
            );

            Route::post(
                '/prep-lists',
                [PrepListController::class, 'store']
            );

            Route::get(
                '/prep-lists/{prepList}',
                [PrepListController::class, 'show']
            );

            Route::patch(
                '/prep-lists/{prepList}',
                [PrepListController::class, 'update']
            );

            Route::post(
                '/prep-lists/{prepList}/generate',
                [PrepListController::class, 'generate']
            );

            Route::post(
                '/prep-lists/{prepList}/regenerate',
                [PrepListController::class, 'regenerate']
            );

            Route::get(
                '/prep-lists/{prepList}/versions',
                [PrepListController::class, 'versions']
            );
        });
    });
});
