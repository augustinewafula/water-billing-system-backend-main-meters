<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MeterBillingController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\MeterStationController;
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
            Route::group(['middleware' => 'role:admin'], function () {
                Route::group(['middleware' => 'auth:api'], function(){
                    Route::get('profile', [AuthController::class, 'user']);
                    Route::get('logout', [AuthController::class, 'logout']);
                });
            });
        });
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
    Route::apiResource('meters', MeterController::class);
    Route::apiResource('meter-readings', MeterReadingController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('transactions', TransactionController::class)->only([
        'index', 'show'
    ]);
    Route::get('meter-stations', [MeterStationController::class, 'index']);
    Route::get('meter-types', [MeterController::class, 'typeIndex']);
    Route::post('mpesa/transaction-confirmation', [MeterBillingController::class, 'mpesaConfirmation']);
    Route::put('valve-status/{meter}', [MeterController::class, 'updateValveStatus']);
    Route::post('sms', [SmsController::class, 'send']);
    Route::fallback(static function () {
        return response()->json([
            'message' => 'Page Not Found. If error persists, contact the website administrator'], 404);
    });
});
