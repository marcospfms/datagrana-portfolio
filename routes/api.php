<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (V1)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'google'])->name('auth.google');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    });
});

/*
|--------------------------------------------------------------------------
| Core (V2)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/banks', [AccountController::class, 'banks'])->name('banks.index');
    Route::apiResource('accounts', AccountController::class);

    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/categories', [AssetController::class, 'categories'])->name('categories');
        Route::get('/', [AssetController::class, 'search'])->name('search');
        Route::get('/popular', [AssetController::class, 'popular'])->name('popular');
        Route::get('/{companyTicker}', [AssetController::class, 'show'])->name('show');
    });
});

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toISOString(),
    ]);
})->name('health');
