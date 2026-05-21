<?php

/**
 * 数据同步路由
 * 供 Electron/Flutter 客户端调用
 */
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('sync')->group(function () {
    Route::get('products', [SyncController::class, 'products']);
    Route::get('customers', [SyncController::class, 'customers']);
    Route::get('config', [SyncController::class, 'config']);
    Route::post('orders', [SyncController::class, 'uploadOrders']);
});