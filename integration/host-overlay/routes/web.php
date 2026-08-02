<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', fn () => redirect('/dashboard'));
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['auth', 'company.active', 'workcore.tenant'])->group(function (): void {
    Route::get('/', fn () => redirect('/dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.update-profile');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.update-password');
    Route::post('/settings/toggle-notifications', [SettingsController::class, 'toggleNotifications'])->name('settings.toggle-notifications');
    Route::get('/system-settings', [SettingController::class, 'index'])->name('settings.index')->middleware('admin');
    Route::post('/system-settings', [SettingController::class, 'update'])->name('settings.update')->middleware('admin');
    Route::resource('users', UserManagementController::class)->middleware('admin');
    Route::post('users/import', [UserManagementController::class, 'import'])->name('users.import')->middleware('admin');
});
