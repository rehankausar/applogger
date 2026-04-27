<?php

use FastnetKSA\AppLogger\Http\Controllers\ApplicationLogsController;
use Illuminate\Support\Facades\Route;

$routeConfig = config('applogger.routes');

Route::middleware($routeConfig['middleware'])
    ->prefix($routeConfig['prefix'])
    ->name($routeConfig['name'])
    ->group(function () {
        Route::get('/',                 [ApplicationLogsController::class, 'index'])->name('index');
        Route::get('/health/status',    [ApplicationLogsController::class, 'health'])->name('health');
        Route::post('/cleanup',         [ApplicationLogsController::class, 'cleanup'])->name('cleanup');
        Route::get('/files/list',       [ApplicationLogsController::class, 'fileLogs'])->name('files');
        Route::get('/files/view',       [ApplicationLogsController::class, 'viewFile'])->name('files.view');
        Route::get('/files/download',   [ApplicationLogsController::class, 'downloadFile'])->name('files.download');
        Route::post('/files/delete',    [ApplicationLogsController::class, 'deleteFile'])->name('files.delete');
        Route::post('/files/clear-all', [ApplicationLogsController::class, 'clearAllFiles'])->name('files.clear-all');
        Route::get('/{log}',            [ApplicationLogsController::class, 'show'])->name('show');
    });
