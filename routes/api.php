<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MeterBillingController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\MeterStationController;
use App\Http\Controllers\MeterTokenController;
use App\Http\Controllers\MonthlyServiceChargeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UnresolvedTransactionController;
use App\Http\Controllers\UserController;
use App\Models\UnresolvedMpesaTransaction;
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
            Route::post('password/reset', [ForgotPasswordController::class, 'submitResetPasswordForm']);
            Route::group(['middleware' => ['role:super-admin|admin|supervisor', 'auth:api']], function () {
                Route::get('profile', [AuthController::class, 'user']);
                Route::put('profile/{user}', [AuthController::class, 'update'])->middleware('cacheResponse:User');
                Route::get('logout', [AuthController::class, 'logout']);
            });
        });
        Route::put('update-password/{user}', [AuthController::class, 'updatePassword']);
        Route::prefix('user')->group(function () {
            Route::post('login', [AuthController::class, 'initiateUserLogin']);
            Route::post('password/email', [ForgotPasswordController::class, 'getResetToken']);
            Route::group(['middleware' => 'role:user'], function () {
                Route::group(['middleware' => 'auth:api'], function () {
                    Route::get('profile', [AuthController::class, 'user'])->middleware('cacheResponse:User');
                    Route::get('logout', [AuthController::class, 'logout']);
                });
            });
        });
    });
    Route::group(['middleware' => ['auth:api']], static function () {
        Route::apiResource('meters', MeterController::class);
        Route::apiResource('meter-readings', MeterReadingController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('meter-stations', MeterStationController::class)->except(['show']);
        Route::apiResource('monthly-service-charges', MonthlyServiceChargeController::class)->only([
            'index', 'show'
        ]);
        Route::apiResource('transactions', TransactionController::class)->only([
            'index', 'show'
        ])->middleware('doNotCacheResponse');
        Route::apiResource('meter-tokens', MeterTokenController::class)->except([
            'update', 'destroy'
        ]);
        Route::apiResource('settings', SettingController::class)->only([
            'index', 'update'
        ]);
        Route::get('system-users', [UserController::class, 'systemUsersIndex'])->middleware('cacheResponse:User');
        Route::post('system-users', [UserController::class, 'storeSystemUser']);
        Route::group(['middleware' => ['doNotCacheResponse']], static function () {
            Route::get('statistics', [StatisticsController::class, 'index']);
            Route::get('statistics/previous-month-revenue-statistics', [StatisticsController::class, 'previousMonthRevenueStatistics']);
            Route::get('statistics/meter-readings/{meter}', [StatisticsController::class, 'meterReadings']);
            Route::get('statistics/main-meter-readings', [StatisticsController::class, 'mainMeterReading']);
            Route::get('statistics/monthly-revenue', [StatisticsController::class, 'monthlyRevenueStatistics']);
            Route::get('user-billing-report/{user}', [UserController::class, 'billing_report']);
            Route::get('user-billing-report-years/{user}', [UserController::class, 'billing_report_years']);
            Route::get('download-users', [UserController::class, 'download']);
        });
        Route::get('available-meters', [MeterController::class, 'availableIndex'])->middleware('cacheResponse:Meter');
        Route::get('meter-types', [MeterController::class, 'typeIndex'])->middleware('cacheResponse:MeterType');
        Route::get('unresolved-transactions', [UnresolvedTransactionController::class, 'index'])->middleware('doNotCacheResponse');
        Route::put('valve-status/{meter}', [MeterController::class, 'updateValveStatus']);
        Route::get('sms', [SmsController::class, 'index'])->middleware('cacheResponse:Sms');
        Route::post('main-meters', [MeterController::class, 'storeMainMeter']);
        Route::post('unresolved-transactions', [UnresolvedTransactionController::class, 'assign']);
        Route::post('sms', [SmsController::class, 'send']);
        Route::post('meter-tokens-resend/{meter_token}', [MeterTokenController::class, 'resend']);
        Route::post('meter-readings-resend/{meter_reading}', [MeterReadingController::class, 'resend']);
        Route::get('roles', [UserController::class, 'rolesIndex'])->middleware('cacheResponse:Role');
    });
    Route::post('transaction-confirmation', [MeterBillingController::class, 'mpesaConfirmation']);
    Route::post('transaction-validation', [MeterBillingController::class, 'mpesaValidation']);
    Route::fallback(static function () {
        return response()->json([
            'message' => 'Page Not Found. If error persists, contact the website administrator'], 404);
    });
});
