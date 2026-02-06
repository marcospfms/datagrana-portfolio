<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\CompositionController;
use App\Http\Controllers\Api\ConsolidatedController;
use App\Http\Controllers\Api\ConsolidatedTransactionController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\RevenueCatWebhookController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\UserSubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle'])
    ->name('webhooks.revenuecat');

/*
|--------------------------------------------------------------------------
| Authentication (V1)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/google', [AuthController::class, 'google'])->name('auth.google');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::get('/profile', [AuthController::class, 'profile'])->name('auth.profile');
        Route::patch('/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
        Route::put('/password', [AuthController::class, 'updatePassword'])->name('auth.password.update');
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
    Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index'])->name('subscription-plans.index');
    Route::get('/subscription-plans/{plan}', [SubscriptionPlanController::class, 'show'])->name('subscription-plans.show');
    Route::get('/subscription/current', [UserSubscriptionController::class, 'current'])->name('subscription.current');
    Route::get('/subscription/history', [UserSubscriptionController::class, 'history'])->name('subscription.history');

    Route::get('/banks', [AccountController::class, 'banks'])->name('banks.index');
    Route::apiResource('accounts', AccountController::class)->except(['store']);
    Route::middleware('subscription.limit:account')->post('/accounts', [AccountController::class, 'store'])
        ->name('accounts.store');

    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/categories', [AssetController::class, 'categories'])->name('categories');
        Route::get('/', [AssetController::class, 'search'])->name('search');
        Route::get('/popular', [AssetController::class, 'popular'])->name('popular');
        Route::get('/{companyTicker}', [AssetController::class, 'show'])->name('show');
    });

    Route::get('/consolidated/summary', [ConsolidatedController::class, 'summary'])->name('consolidated.summary');
    Route::get('/consolidated', [ConsolidatedController::class, 'index'])->name('consolidated.index');
    Route::get('/consolidated/{consolidated}', [ConsolidatedController::class, 'show'])->name('consolidated.show');
    Route::delete('/consolidated/{consolidated}', [ConsolidatedController::class, 'destroy'])->name('consolidated.destroy');

    Route::post('/consolidated/transactions', [ConsolidatedTransactionController::class, 'store'])
        ->name('consolidated.transactions.store');
    Route::put('/consolidated/transactions/{type}/{transactionId}', [ConsolidatedTransactionController::class, 'update'])
        ->name('consolidated.transactions.update');
    Route::delete('/consolidated/transactions/{type}/{transactionId}', [ConsolidatedTransactionController::class, 'destroy'])
        ->name('consolidated.transactions.destroy');

    Route::apiResource('portfolios', PortfolioController::class)->except(['store']);
    Route::middleware('subscription.limit:portfolio')->post('/portfolios', [PortfolioController::class, 'store'])
        ->name('portfolios.store');
    Route::get('/portfolios/{portfolio}/crossing', [PortfolioController::class, 'crossing'])
        ->name('portfolios.crossing');
    Route::middleware('subscription.limit:composition')
        ->post('/portfolios/{portfolio}/compositions', [CompositionController::class, 'store'])
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
