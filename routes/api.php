<?php

use App\Http\Controllers\AlertContactController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConcentratorController;
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
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\Statistics\DashboardStatisticsController;
use App\Http\Controllers\Statistics\TransactionStatisticsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UnreadMeterController;
use App\Http\Controllers\UnresolvedTransactionController;
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
        Route::prefix('statistics')->group(function () {
            Route::get('/', [DashboardStatisticsController::class, 'index']);
            Route::get('total-revenue', [DashboardStatisticsController::class, 'totalRevenueSum']);
            Route::get('today-revenue', [TransactionStatisticsController::class, 'todayRevenue']);
            Route::get('this-week-revenue', [TransactionStatisticsController::class, 'thisWeekRevenue']);
            Route::get('this-month-revenue', [TransactionStatisticsController::class, 'thisMonthRevenue']);
            Route::get('this-year-revenue', [TransactionStatisticsController::class, 'thisYearRevenue']);
            Route::get('monthly-revenue-per-station', [TransactionStatisticsController::class, 'monthlyRevenueStatisticsPerStation']);
            Route::get('previous-month-revenue-statistics', [DashboardStatisticsController::class, 'previousMonthRevenueStatistics']);
            Route::get('meter-readings/{meter}', [DashboardStatisticsController::class, 'getMeterReadingStatistics']);
            Route::get('main-meter-readings', [DashboardStatisticsController::class, 'mainMeterReading']);
            Route::get('per-station-average-meter-readings', [DashboardStatisticsController::class, 'perStationAverageMeterReading']);
            Route::get('monthly-revenue', [TransactionStatisticsController::class, 'monthlyRevenueStatistics']);
            Route::get('revenue-years', [TransactionStatisticsController::class, 'revenueYears']);
        });
        Route::apiResource('concentrators', ConcentratorController::class);
        Route::apiResource('meters', MeterController::class);
        Route::apiResource('meter-readings', MeterReadingController::class);
        Route::apiResource('unread-meters', UnreadMeterController::class);
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
        Route::apiResource('sms-templates', SmsTemplateController::class)->except([
            'show', 'update'
        ]);
        Route::get('settings', [SettingController::class, 'index']);
        Route::post('settings', [SettingController::class, 'update']);
        Route::get('permission-models', [RoleController::class, 'permissionModelsIndex']);
        Route::get('system-users', [UserController::class, 'systemUsersIndex']);
        Route::post('system-users', [UserController::class, 'storeSystemUser']);
        Route::get('user-billing-report/{user}', [UserController::class, 'billing_report']);
        Route::get('user-billing-report-years/{user}', [UserController::class, 'billing_report_years']);
        Route::get('download-users', [UserController::class, 'download']);
        Route::get('available-meters', [MeterController::class, 'availableIndex']);
        Route::get('meter-types', [MeterController::class, 'typeIndex']);
        Route::get('meter-types/{name}', [MeterController::class, 'showMeterTypeByNameIndex']);
        Route::get('faulty-meters', [MeterController::class, 'faultyIndex']);
        Route::get('unresolved-transactions', [UnresolvedTransactionController::class, 'index']);
        Route::get('sms', [SmsController::class, 'index']);
        Route::get('sms-credit-balance', [SmsController::class, 'getCreditBalance']);
        Route::get('meter-readings-preview-message/{meter_reading}', [MeterReadingController::class, 'previewMeterReadingMessage']);
        Route::get('daily-meter-readings/{meter}', [MeterReadingController::class, 'dailyReadingsIndex']);
        Route::get('monthly-meter-readings/fetch', [MeterReadingController::class, 'fetchMonthlyReadings']);
        Route::put('valve-status/{meter}', [MeterController::class, 'updateValveStatus']);
        Route::put('can-generate-token/{meter}', [MeterController::class, 'updateCanGenerateTokenStatus']);
        Route::post('main-meters', [MeterController::class, 'storeMainMeter']);
        Route::post('unresolved-transactions', [UnresolvedTransactionController::class, 'assign']);
        Route::post('sms', [SmsController::class, 'send']);
        Route::post('sms-resend/{sms}', [SmsController::class, 'resend']);
        Route::post('meter-tokens-resend/{meter_token}', [MeterTokenController::class, 'resend']);
        Route::post('meter-tokens-clear', [MeterTokenController::class, 'clear']);
        Route::post('meter-clear-tamper-record', [MeterTokenController::class, 'clearTamperRecord']);
        Route::post('meter-send-clear-token-message', [MeterTokenController::class, 'sendClearTokenMessage']);
        Route::post('meter-readings-resend/{meter_reading}', [MeterReadingController::class, 'resend']);
        Route::post('roles-update/{role}', [RoleController::class, 'update']);
        Route::delete('unresolved-transactions/{unresolvedMpesaTransaction}', [UnresolvedTransactionController::class, 'destroy']);
        Route::post('users-account/credit', [TransactionController::class, 'creditAccount']);
        Route::post('users-account/debit', [TransactionController::class, 'debitAccount']);
        Route::post('transfer-transaction', [TransactionController::class, 'transfer']);
        Route::post('generate-meter-token', [MeterTokenController::class, 'generateToken']);
    });
    Route::post('sms-callback', [SmsController::class, 'callback']);
    Route::post('transaction-confirmation', [MeterBillingController::class, 'mpesaConfirmation']);
//    Route::post('pull-transactions', [MeterBillingController::class, 'mpesaPullTransactions']);
    Route::get('mspace-transaction-confirmation', [MeterBillingController::class, 'mspaceMpesaConfirmation']);
    Route::post('transaction-validation', [MeterBillingController::class, 'mpesaValidation']);
    Route::post('query-transaction-status-result-callback', [TransactionController::class, 'queryTransactionStatusResultCallback']);
    Route::post('query-transaction-status-queue-timeout-callback', [TransactionController::class, 'queryTransactionStatusQueueTimeoutCallback']);
    Route::fallback(static function () {
        return response()->json([
            'message' => 'Page Not Found. If error persists, contact the website administrator'], 404);
    });
});
