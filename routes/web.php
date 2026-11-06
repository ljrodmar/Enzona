<?php

use Illuminate\Support\Facades\Route;
use Macroseso\Enzona\Http\Controllers\EnzonaCallbackController;

Route::middleware(config('enzona.route_middleware', ['web']))->group(function () {
    Route::get('/enzona/return', [EnzonaCallbackController::class, 'return'])->name('enzona.return');
    Route::get('/enzona/cancel', [EnzonaCallbackController::class, 'cancel'])->name('enzona.cancel');
});
