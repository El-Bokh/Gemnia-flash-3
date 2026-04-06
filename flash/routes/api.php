<?php

use App\Http\Controllers\Api\Admin\AiRequestController;
use App\Http\Controllers\Api\Admin\CouponController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\FeatureController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\PlanController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\SupportTicketController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VisualStyleController as AdminVisualStyleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\VisualStyleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api automatically by the framework.
| Admin routes are protected by auth:sanctum + admin middleware.
|
*/

// ──────────────────────────────────────────────
//  Auth Routes
// ──────────────────────────────────────────────

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register')->middleware('throttle:5,1');
    Route::post('/login',  [AuthController::class, 'login'])->name('login')->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me',      [AuthController::class, 'me'])->name('me');
    });
});

// ──────────────────────────────────────────────
//  Client Routes (authenticated)
// ──────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Profile
    Route::put('/profile',          [ProfileController::class, 'update']);
    Route::post('/profile/avatar',  [ProfileController::class, 'uploadAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // Conversations
    Route::apiResource('conversations', ConversationController::class);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
        ->name('conversations.messages')
        ->middleware('throttle:20,1');

    // Visual Styles
    Route::get('/styles', [VisualStyleController::class, 'index'])->name('styles.index');

    // Subscription & Quota
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::get('/plans/public',  [SubscriptionController::class, 'plans'])->name('plans.public');
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::post('/subscription/cancel',  [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',                         [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count',             [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/read-all',                [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{notification}/read',     [NotificationController::class, 'markAsRead'])->name('read');
        Route::delete('/{notification}',        [NotificationController::class, 'destroy'])->name('destroy');
    });
});

// ──────────────────────────────────────────────
//  Admin Routes
// ──────────────────────────────────────────────

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/',                  [DashboardController::class, 'index'])->name('index');
            Route::get('/kpis',              [DashboardController::class, 'kpis'])->name('kpis');
            Route::get('/charts',            [DashboardController::class, 'charts'])->name('charts');
            Route::get('/recent-ai-requests',[DashboardController::class, 'recentAiRequests'])->name('recent-ai-requests');
            Route::get('/recent-payments',   [DashboardController::class, 'recentPayments'])->name('recent-payments');
            Route::get('/alerts',            [DashboardController::class, 'alerts'])->name('alerts');
        });

        // Users Management
        Route::prefix('users')->name('users.')->group(function () {
            // Aggregations (before {user} to avoid route conflict)
            Route::get('/aggregations', [UserController::class, 'aggregations'])->name('aggregations');

            // CRUD
            Route::get('/',             [UserController::class, 'index'])->name('index');
            Route::post('/',            [UserController::class, 'store'])->name('store');
            Route::get('/{user}',       [UserController::class, 'show'])->name('show');
            Route::put('/{user}',       [UserController::class, 'update'])->name('update');
            Route::delete('/{user}',    [UserController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::delete('/{user}/force',          [UserController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{user}/restore',          [UserController::class, 'restore'])->name('restore');
            Route::put('/{user}/roles',             [UserController::class, 'assignRoles'])->name('assign-roles');
            Route::post('/{user}/reset-password',   [UserController::class, 'resetPassword'])->name('reset-password');

            // Sub-resources
            Route::get('/{user}/ai-requests',       [UserController::class, 'aiRequests'])->name('ai-requests');
            Route::get('/{user}/generated-images',  [UserController::class, 'generatedImages'])->name('generated-images');
        });

        // Roles Management
        Route::prefix('roles')->name('roles.')->group(function () {
            // Static routes first (before {role} wildcard)
            Route::get('/matrix', [RoleController::class, 'matrix'])->name('matrix');

            // CRUD
            Route::get('/',            [RoleController::class, 'index'])->name('index');
            Route::post('/',           [RoleController::class, 'store'])->name('store');
            Route::get('/{role}',      [RoleController::class, 'show'])->name('show');
            Route::put('/{role}',      [RoleController::class, 'update'])->name('update');
            Route::delete('/{role}',   [RoleController::class, 'destroy'])->name('destroy');

            // Assign permissions to a role
            Route::put('/{role}/permissions', [RoleController::class, 'assignPermissions'])->name('assign-permissions');
        });

        // Permissions Management
        Route::prefix('permissions')->name('permissions.')->group(function () {
            // Static routes first
            Route::get('/grouped', [PermissionController::class, 'grouped'])->name('grouped');

            // CRUD
            Route::get('/',                  [PermissionController::class, 'index'])->name('index');
            Route::post('/',                 [PermissionController::class, 'store'])->name('store');
            Route::put('/{permission}',      [PermissionController::class, 'update'])->name('update');
            Route::delete('/{permission}',   [PermissionController::class, 'destroy'])->name('destroy');
        });

        // Plans Management
        Route::prefix('plans')->name('plans.')->group(function () {
            // Static routes first (before {plan} wildcard)
            Route::get('/comparison', [PlanController::class, 'comparison'])->name('comparison');

            // CRUD
            Route::get('/',             [PlanController::class, 'index'])->name('index');
            Route::post('/',            [PlanController::class, 'store'])->name('store');
            Route::get('/{plan}',       [PlanController::class, 'show'])->name('show');
            Route::put('/{plan}',       [PlanController::class, 'update'])->name('update');
            Route::delete('/{plan}',    [PlanController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::delete('/{plan}/force',          [PlanController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{plan}/restore',          [PlanController::class, 'restore'])->name('restore');
            Route::post('/{plan}/duplicate',        [PlanController::class, 'duplicate'])->name('duplicate');
            Route::post('/{plan}/toggle-active',    [PlanController::class, 'toggleActive'])->name('toggle-active');

            // Feature management on plans
            Route::put('/{plan}/features',           [PlanController::class, 'syncFeatures'])->name('sync-features');
            Route::patch('/{plan}/features/{feature}',[PlanController::class, 'updateFeatureLimit'])->name('update-feature-limit');
        });

        // Features Management
        Route::prefix('features')->name('features.')->group(function () {
            // CRUD
            Route::get('/',              [FeatureController::class, 'index'])->name('index');
            Route::post('/',             [FeatureController::class, 'store'])->name('store');
            Route::get('/{feature}',     [FeatureController::class, 'show'])->name('show');
            Route::put('/{feature}',     [FeatureController::class, 'update'])->name('update');
            Route::delete('/{feature}',  [FeatureController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::post('/{feature}/toggle-active', [FeatureController::class, 'toggleActive'])->name('toggle-active');
            Route::put('/{feature}/plans',          [FeatureController::class, 'assignToPlans'])->name('assign-plans');
        });

        // AI Requests Management
        Route::prefix('ai-requests')->name('ai-requests.')->group(function () {
            // Static routes first (before {aiRequest} wildcard)
            Route::get('/aggregations',    [AiRequestController::class, 'aggregations'])->name('aggregations');
            Route::post('/bulk-retry',     [AiRequestController::class, 'bulkRetry'])->name('bulk-retry');
            Route::post('/bulk-delete',    [AiRequestController::class, 'bulkDelete'])->name('bulk-delete');

            // CRUD
            Route::get('/',                [AiRequestController::class, 'index'])->name('index');
            Route::get('/{aiRequest}',     [AiRequestController::class, 'show'])->name('show');
            Route::put('/{aiRequest}',     [AiRequestController::class, 'update'])->name('update');
            Route::delete('/{aiRequest}',  [AiRequestController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::delete('/{aiRequest}/force',   [AiRequestController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{aiRequest}/restore',   [AiRequestController::class, 'restore'])->name('restore');
            Route::post('/{aiRequest}/retry',     [AiRequestController::class, 'retry'])->name('retry');
            Route::post('/{aiRequest}/cancel',    [AiRequestController::class, 'cancel'])->name('cancel');
            Route::post('/{aiRequest}/notify',    [AiRequestController::class, 'notify'])->name('notify');
        });

        // Payments Management
        Route::prefix('payments')->name('payments.')->group(function () {
            // Static routes first (before {payment} wildcard)
            Route::get('/aggregations', [PaymentController::class, 'aggregations'])->name('aggregations');

            // CRUD
            Route::get('/',              [PaymentController::class, 'index'])->name('index');
            Route::get('/{payment}',     [PaymentController::class, 'show'])->name('show');
            Route::put('/{payment}',     [PaymentController::class, 'update'])->name('update');
            Route::delete('/{payment}',  [PaymentController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::post('/{payment}/refund',    [PaymentController::class, 'refund'])->name('refund');
            Route::delete('/{payment}/force',   [PaymentController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{payment}/restore',   [PaymentController::class, 'restore'])->name('restore');
        });

        // Invoices Management
        Route::prefix('invoices')->name('invoices.')->group(function () {
            // Static routes first
            Route::get('/aggregations',             [InvoiceController::class, 'aggregations'])->name('aggregations');
            Route::post('/generate/{payment}',      [InvoiceController::class, 'generateFromPayment'])->name('generate');

            // CRUD
            Route::get('/',              [InvoiceController::class, 'index'])->name('index');
            Route::get('/{invoice}',     [InvoiceController::class, 'show'])->name('show');
            Route::put('/{invoice}',     [InvoiceController::class, 'update'])->name('update');
            Route::delete('/{invoice}',  [InvoiceController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::get('/{invoice}/download',    [InvoiceController::class, 'download'])->name('download');
            Route::delete('/{invoice}/force',    [InvoiceController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{invoice}/restore',    [InvoiceController::class, 'restore'])->name('restore');
        });

        // Coupons Management
        Route::prefix('coupons')->name('coupons.')->group(function () {
            // Static routes first
            Route::get('/aggregations',    [CouponController::class, 'aggregations'])->name('aggregations');
            Route::post('/validate',       [CouponController::class, 'validateCoupon'])->name('validate');

            // CRUD
            Route::get('/',             [CouponController::class, 'index'])->name('index');
            Route::post('/',            [CouponController::class, 'store'])->name('store');
            Route::get('/{coupon}',     [CouponController::class, 'show'])->name('show');
            Route::put('/{coupon}',     [CouponController::class, 'update'])->name('update');
            Route::delete('/{coupon}',  [CouponController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::post('/{coupon}/toggle',    [CouponController::class, 'toggle'])->name('toggle');
            Route::get('/{coupon}/usage',      [CouponController::class, 'usage'])->name('usage');
            Route::delete('/{coupon}/force',   [CouponController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{coupon}/restore',   [CouponController::class, 'restore'])->name('restore');
        });

        // Support Tickets Management
        Route::prefix('support-tickets')->name('support-tickets.')->group(function () {
            // Static routes first (before {ticket} wildcard)
            Route::get('/aggregations', [SupportTicketController::class, 'aggregations'])->name('aggregations');

            // CRUD
            Route::get('/',              [SupportTicketController::class, 'index'])->name('index');
            Route::get('/{ticket}',      [SupportTicketController::class, 'show'])->name('show');
            Route::put('/{ticket}',      [SupportTicketController::class, 'update'])->name('update');
            Route::delete('/{ticket}',   [SupportTicketController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::post('/{ticket}/assign',    [SupportTicketController::class, 'assign'])->name('assign');
            Route::post('/{ticket}/reply',     [SupportTicketController::class, 'reply'])->name('reply');
            Route::post('/{ticket}/close',     [SupportTicketController::class, 'close'])->name('close');
            Route::post('/{ticket}/reopen',    [SupportTicketController::class, 'reopen'])->name('reopen');
            Route::delete('/{ticketId}/force',   [SupportTicketController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{ticketId}/restore',   [SupportTicketController::class, 'restore'])->name('restore');
        });

        // Notifications Management
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/',                         [AdminNotificationController::class, 'index'])->name('index');
            Route::get('/unread-count',             [AdminNotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('/read-all',                [AdminNotificationController::class, 'markAllAsRead'])->name('read-all');
            Route::post('/{notification}/read',     [AdminNotificationController::class, 'markAsRead'])->name('read');
            Route::delete('/{notification}',        [AdminNotificationController::class, 'destroy'])->name('destroy');
        });

        // System Settings Management
        Route::prefix('settings')->name('settings.')->group(function () {
            // Audit Log
            Route::get('/audit-log',           [SettingsController::class, 'auditLog'])->name('audit-log');

            // Maintenance Mode
            Route::get('/maintenance',         [SettingsController::class, 'maintenanceStatus'])->name('maintenance-status');
            Route::post('/maintenance/toggle', [SettingsController::class, 'toggleMaintenance'])->name('maintenance-toggle');
            Route::put('/maintenance',         [SettingsController::class, 'updateMaintenance'])->name('maintenance-update');

            // AI Integrations
            Route::get('/ai-integrations',          [SettingsController::class, 'aiIntegrations'])->name('ai-integrations');
            Route::put('/ai-integrations',          [SettingsController::class, 'updateAiIntegrations'])->name('ai-integrations-update');
            Route::post('/ai-integrations/test',    [SettingsController::class, 'testAiIntegration'])->name('ai-integrations-test');
        });

        // Visual Styles Management
        Route::prefix('styles')->name('styles.')->group(function () {
            // Static routes first
            Route::post('/reorder', [AdminVisualStyleController::class, 'reorder'])->name('reorder');

            // CRUD
            Route::get('/',            [AdminVisualStyleController::class, 'index'])->name('index');
            Route::post('/',           [AdminVisualStyleController::class, 'store'])->name('store');
            Route::get('/{style}',     [AdminVisualStyleController::class, 'show'])->name('show');
            Route::put('/{style}',     [AdminVisualStyleController::class, 'update'])->name('update');
            Route::delete('/{style}',  [AdminVisualStyleController::class, 'destroy'])->name('destroy');

            // Extra actions
            Route::delete('/{style}/force',            [AdminVisualStyleController::class, 'forceDelete'])->name('force-delete');
            Route::post('/{style}/restore',            [AdminVisualStyleController::class, 'restore'])->name('restore');
            Route::post('/{style}/duplicate',          [AdminVisualStyleController::class, 'duplicate'])->name('duplicate');
            Route::post('/{style}/toggle-active',      [AdminVisualStyleController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{style}/upload-thumbnail',   [AdminVisualStyleController::class, 'uploadThumbnailEndpoint'])->name('upload-thumbnail');
        });

    });


