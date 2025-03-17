<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WordController;
use App\Http\Controllers\Api\CategoryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function() {
    Route::get('/user', fn(Request $request) => $request->user());

    Route::post('/category', [CategoryController::class, 'store'])->name('category.store'); // tested
    Route::delete('/category/{id}', [CategoryController::class, 'delete'])->name('category.delete'); // tested

    Route::controller(WordController::class)->prefix('/word')->group(function() {
        Route::post('/', 'store')->name('word.store'); // tested
        Route::get('/', 'getWords')->name('word.getWords'); // tested
        Route::get('/dictionary', 'getDictionaryWords')->name('word.getDictionaryWords'); // tested
        Route::get('/{id}', 'show')->name('word.show'); // tested
        Route::put('/{id}', 'update')->name('word.update'); // tested
        Route::delete('/{id}', 'delete')->name('word.delete'); // tested
        Route::post('/{id}/move', 'move')->name('word.move'); // tested
        Route::post('/{id}/progress', 'progress')->name('word.progress'); // tested
    });
});

Route::group([], function() {
    Route::post('/login', [AuthController::class, 'login'])->name('login'); // tested
    Route::post('/register', [AuthController::class, 'register'])->name('register'); // tested
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum'); // tested
    Route::post('/me', [AuthController::class, 'me'])->name('me'); // tested
});
