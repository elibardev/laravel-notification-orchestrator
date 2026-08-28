<?php

declare(strict_types=1);
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Http\DeviceController;
use Illuminate\Support\Facades\Route;

$config = app(Configuration::class);
Route::prefix($config->get('api.prefix').'/devices')->middleware($config->get('api.middleware'))->name($config->get('api.name_prefix').'devices.')->group(function () {
    Route::get('', [DeviceController::class, 'index'])->name('index');
    Route::post('', [DeviceController::class, 'store'])->name('store');
    Route::patch('{device}', [DeviceController::class, 'update'])->name('update');
    Route::delete('{device}', [DeviceController::class, 'destroy'])->name('destroy');
});
