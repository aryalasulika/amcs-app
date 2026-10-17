<?php

use App\Http\Controllers\Api\V1\AircraftTypeController;
use App\Http\Controllers\Api\V1\AirlineController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MovementController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OptionsController;
use App\Http\Controllers\Api\V1\ParkingStandController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\StandController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AMCS REST API (v1)
|--------------------------------------------------------------------------
| Authenticated either through Sanctum bearer tokens (external clients) or
| the first-party session cookie (AMC dashboard / ATC monitor).
*/

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        // Authentication
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Reference data for forms
        Route::get('options', [OptionsController::class, 'index']);

        // Live apron board & allocation helpers
        Route::get('stands', [StandController::class, 'index']);
        Route::get('stands/available', [StandController::class, 'available']);
        Route::post('stands/recommend', [StandController::class, 'recommend']);
        Route::post('stands/reset', [StandController::class, 'reset']);
        Route::get('stands/{stand}', [StandController::class, 'show']);

        // Movements (pergerakan)
        Route::get('movements', [MovementController::class, 'index']);
        Route::post('movements', [MovementController::class, 'store']);
        Route::get('movements/{movement}', [MovementController::class, 'show']);
        Route::match(['put', 'patch'], 'movements/{movement}', [MovementController::class, 'update']);
        Route::delete('movements/{movement}', [MovementController::class, 'destroy']);
        Route::patch('movements/{movement}/state', [MovementController::class, 'transition']);
        Route::post('movements/{movement}/block-on', [MovementController::class, 'blockOn']);
        Route::post('movements/{movement}/block-off', [MovementController::class, 'blockOff']);
        Route::post('movements/{movement}/delay', [MovementController::class, 'delay']);
        Route::get('movements/{movement}/logs', [MovementController::class, 'logs']);

        // Reports & exports
        Route::get('reports/daily', [ReportController::class, 'daily']);
        Route::get('reports/monthly', [ReportController::class, 'monthly']);
        Route::get('reports/summary', [ReportController::class, 'summary']);
        Route::get('reports/history', [ReportController::class, 'history']);
        Route::get('reports/activity', [ReportController::class, 'activity']);
        Route::get('reports/export', [ReportController::class, 'export']);

        // Audit trail (read-only)
        Route::get('audit-logs', [AuditLogController::class, 'index']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        // Master data (administrator)
        Route::apiResource('parking-stands', ParkingStandController::class)->except(['show']);
        Route::apiResource('airlines', AirlineController::class)->except(['show']);
        Route::apiResource('aircraft-types', AircraftTypeController::class)->except(['show']);
        Route::apiResource('users', UserController::class);
        Route::get('roles', [RoleController::class, 'index']);
    });
});
