<?php

declare(strict_types=1);
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Http\BroadcastAuthController;
use Elibardev\NotificationOrchestrator\Http\NotificationController;
use Elibardev\NotificationOrchestrator\Http\PreferenceController;
use Illuminate\Support\Facades\Route;

$config = app(Configuration::class);
Route::prefix($config->get('api.prefix'))->middleware($config->get('api.middleware'))->name($config->get('api.name_prefix'))->group(function () use ($config) {
    Route::get('bootstrap', [NotificationController::class, 'bootstrap'])->name('bootstrap');
    Route::get('', [NotificationController::class, 'index'])->name('index');
    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::patch('{notification}/read', [NotificationController::class, 'read'])->name('read');
    Route::patch('{notification}/unread', [NotificationController::class, 'unread'])->name('unread');
    Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
    if ($config->enabled('preferences')) {
        Route::get('preferences', [PreferenceController::class, 'show'])->name('preferences.show');
        Route::put('preferences', [PreferenceController::class, 'update'])->name('preferences.update');
        Route::delete('preferences', [PreferenceController::class, 'destroy'])->name('preferences.destroy');
    }
    if ($config->enabled('broadcast')) {
        Route::post('broadcasting/auth', BroadcastAuthController::class)->name('broadcasting.auth');
    }
});
