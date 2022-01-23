<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MeterController;
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
    Route::apiResource('users', UserController::class);
});
