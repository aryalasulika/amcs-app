<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\AdminPageController;
use App\Http\Controllers\Web\AtcMonitorController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ReportPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('atc-monitor', [AtcMonitorController::class, 'index'])->name('atc');
    Route::get('reports', [ReportPageController::class, 'index'])->name('reports');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('stands', [AdminPageController::class, 'stands'])->name('stands');
        Route::get('aircraft-types', [AdminPageController::class, 'aircraftTypes'])->name('aircraft-types');
        Route::get('airlines', [AdminPageController::class, 'airlines'])->name('airlines');
        Route::get('users', [AdminPageController::class, 'users'])->name('users');
        Route::get('audit-logs', [AdminPageController::class, 'auditLogs'])->name('audit-logs');
    });
});
