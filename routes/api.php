<?php

use App\Http\Controllers\AlertContactController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConnectionFeeController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MeterBillingController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\MeterStationController;
use App\Http\Controllers\MeterTokenController;
use App\Http\Controllers\MonthlyServiceChargeController;
use App\Http\Controllers\RoleController;
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
    Route::group(['middleware' => ['auth:api']], static function () {
        Route::apiResource('meters', MeterController::class);
        Route::apiResource('meter-readings', MeterReadingController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('meter-stations', MeterStationController::class)->except(['show']);
        Route::apiResource('alert-contacts', AlertContactController::class)->except(['show']);
        Route::apiResource('roles', RoleController::class)->except(['update']);
        Route::apiResource('monthly-service-charges', MonthlyServiceChargeController::class)->only([
            'index', 'show'
        ]);
        Route::apiResource('connection-fees', ConnectionFeeController::class)->only([
            'index', 'show'
        ]);
        Route::apiResource('transactions', TransactionController::class)->only([
            'index', 'show'
        ]);
        Route::apiResource('meter-tokens', MeterTokenController::class)->except([
            'update', 'destroy'
        ]);
        Route::get('settings', [SettingController::class, 'index']);
        Route::post('settings', [SettingController::class, 'update']);
        Route::get('permission-models', [RoleController::class, 'permissionModelsIndex']);
        Route::get('system-users', [UserController::class, 'systemUsersIndex']);
        Route::post('system-users', [UserController::class, 'storeSystemUser']);
        Route::get('statistics', [StatisticsController::class, 'index']);
        Route::get('statistics/previous-month-revenue-statistics', [StatisticsController::class, 'previousMonthRevenueStatistics']);
        Route::get('statistics/meter-readings/{meter}', [StatisticsController::class, 'meterReadings']);
        Route::get('statistics/main-meter-readings', [StatisticsController::class, 'mainMeterReading']);
        Route::get('statistics/per-station-average-meter-readings', [StatisticsController::class, 'perStationAverageMeterReading']);
        Route::get('statistics/monthly-revenue', [StatisticsController::class, 'monthlyRevenueStatistics']);
        Route::get('user-billing-report/{user}', [UserController::class, 'billing_report']);
        Route::get('user-billing-report-years/{user}', [UserController::class, 'billing_report_years']);
        Route::get('download-users', [UserController::class, 'download']);
        Route::get('available-meters', [MeterController::class, 'availableIndex']);
        Route::get('meter-types', [MeterController::class, 'typeIndex']);
        Route::get('meter-types/{name}', [MeterController::class, 'showMeterTypeByNameIndex']);
        Route::get('unresolved-transactions', [UnresolvedTransactionController::class, 'index']);
        Route::get('sms', [SmsController::class, 'index']);
        Route::get('sms-credit-balance', [SmsController::class, 'getCreditBalance']);
        Route::get('meter-readings-preview-message/{meter_reading}', [MeterReadingController::class, 'previewMeterReadingMessage']);
        Route::put('valve-status/{meter}', [MeterController::class, 'updateValveStatus']);
        Route::post('main-meters', [MeterController::class, 'storeMainMeter']);
        Route::post('unresolved-transactions', [UnresolvedTransactionController::class, 'assign']);
        Route::post('sms', [SmsController::class, 'send']);
        Route::post('meter-tokens-resend/{meter_token}', [MeterTokenController::class, 'resend']);
        Route::post('meter-tokens-clear', [MeterTokenController::class, 'clear']);
        Route::post('meter-readings-resend/{meter_reading}', [MeterReadingController::class, 'resend']);
        Route::post('roles-update/{role}', [RoleController::class, 'update']);
        Route::delete('unresolved-transactions/{unresolvedMpesaTransaction}', [UnresolvedTransactionController::class, 'destroy']);
    });
    Route::post('sms-callback', [SmsController::class, 'callback']);
    Route::post('transaction-confirmation', [MeterBillingController::class, 'mpesaConfirmation']);
//    Route::post('pull-transactions', [MeterBillingController::class, 'mpesaPullTransactions']);
    Route::get('mspace-transaction-confirmation', [MeterBillingController::class, 'mspaceMpesaConfirmation']);
    Route::post('transaction-validation', [MeterBillingController::class, 'mpesaValidation']);
    Route::fallback(static function () {
        return response()->json([
            'message' => 'Page Not Found. If error persists, contact the website administrator'], 404);
    });
});
