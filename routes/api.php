<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MeterBillingController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\MeterStationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::prefix('admin')->group(function () {
            Route::post('login', [AuthController::class, 'initiateAdminLogin']);
            Route::post('password/email', [ForgotPasswordController::class, 'getResetToken']);
            Route::group(['middleware' => ['role:admin', 'auth:api']], function () {
                Route::get('profile', [AuthController::class, 'user']);
                Route::put('profile/{user}', [AuthController::class, 'update']);
                Route::get('logout', [AuthController::class, 'logout']);
            });
        });
        Route::put('update-password/{user}', [AuthController::class, 'updatePassword']);
        Route::prefix('user')->group(function () {
            Route::post('login', [AuthController::class, 'initiateUserLogin']);
            Route::post('password/email', [ForgotPasswordController::class, 'getResetToken']);
            Route::group(['middleware' => 'role:user'], function () {
                Route::group(['middleware' => 'auth:api'], function () {
                    Route::get('profile', [AuthController::class, 'user']);
                    Route::get('logout', [AuthController::class, 'logout']);
                });
            });
        });
    });
    Route::group(['middleware' => ['role:admin', 'auth:api']], static function () {
        Route::apiResource('meters', MeterController::class);
        Route::apiResource('meter-readings', MeterReadingController::class);
        Route::apiResource('users', UserController::class);
        Route::get('unresolved-transactions', [TransactionController::class, 'unresolvedTransactionIndex']);
        Route::apiResource('transactions', TransactionController::class)->only([
            'index', 'show'
        ]);
        Route::apiResource('settings', SettingController::class)->only([
            'index', 'update'
        ]);
        Route::get('user-billing-report/{user}', [UserController::class, 'billing_report']);
        Route::get('user-billing-report-years/{user}', [UserController::class, 'billing_report_years']);
        Route::get('meter-stations', [MeterStationController::class, 'index']);
        Route::get('meter-types', [MeterController::class, 'typeIndex']);
        Route::put('valve-status/{meter}', [MeterController::class, 'updateValveStatus']);
        Route::get('sms', [SmsController::class, 'index']);
        Route::post('sms', [SmsController::class, 'send']);
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
    });
    Route::post('transaction-confirmation', [MeterBillingController::class, 'mpesaConfirmation']);
    Route::post('transaction-validation', [MeterBillingController::class, 'mpesaValidation']);
    Route::fallback(static function () {
        return response()->json([
            'message' => 'Page Not Found. If error persists, contact the website administrator'], 404);
    });
});
