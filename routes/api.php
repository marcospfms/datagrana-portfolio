<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\CompositionController;
use App\Http\Controllers\Api\ConsolidatedController;
use App\Http\Controllers\Api\ConsolidatedTransactionController;
use App\Http\Controllers\Api\PortfolioController;
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

    Route::get('/consolidated/summary', [ConsolidatedController::class, 'summary'])->name('consolidated.summary');
    Route::get('/consolidated', [ConsolidatedController::class, 'index'])->name('consolidated.index');
    Route::get('/consolidated/{consolidated}', [ConsolidatedController::class, 'show'])->name('consolidated.show');

    Route::post('/consolidated/transactions', [ConsolidatedTransactionController::class, 'store'])
        ->name('consolidated.transactions.store');
    Route::put('/consolidated/transactions/{type}/{transactionId}', [ConsolidatedTransactionController::class, 'update'])
        ->name('consolidated.transactions.update');
    Route::delete('/consolidated/transactions/{type}/{transactionId}', [ConsolidatedTransactionController::class, 'destroy'])
        ->name('consolidated.transactions.destroy');

    Route::apiResource('portfolios', PortfolioController::class);
    Route::get('/portfolios/{portfolio}/crossing', [PortfolioController::class, 'crossing'])
        ->name('portfolios.crossing');
    Route::post('/portfolios/{portfolio}/compositions', [CompositionController::class, 'store'])
        ->name('compositions.store');
    Route::put('/compositions/batch', [CompositionController::class, 'updateBatch'])
        ->name('compositions.batch');
    Route::put('/compositions/{composition}', [CompositionController::class, 'update'])
        ->name('compositions.update');
    Route::delete('/compositions/{composition}', [CompositionController::class, 'destroy'])
        ->name('compositions.destroy');
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
