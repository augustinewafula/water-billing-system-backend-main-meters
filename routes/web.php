<?php

use App\Http\Controllers\ForgotPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::post('password/email', [ForgotPasswordController::class, 'getResetToken']);

Route::get('password/reset/{token}', [ForgotPasswordController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('password/reset', [ForgotPasswordController::class, 'submitResetPasswordForm'])->name('password.update');
Route::get('success', function () {
    return view('successful_reset');
})->name('password.reset.success');
