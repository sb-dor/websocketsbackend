<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Broadcasting auth — must accept Sanctum Bearer tokens (not the default web-session route)
Broadcast::routes(['middleware' => ['auth:sanctum']]);
require base_path('routes/channels.php');

// Auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Rooms
    Route::get('/rooms',          [RoomController::class, 'index']);
    Route::post('/rooms',         [RoomController::class, 'store']);
    Route::post('/rooms/join',    [RoomController::class, 'join']);
    Route::get('/rooms/{code}',   [RoomController::class, 'show']);

    // Messages
    Route::get('/rooms/{code}/messages',  [MessageController::class, 'index']);
    Route::post('/rooms/{code}/messages', [MessageController::class, 'store']);
    Route::post('/rooms/{code}/typing', [MessageController::class, 'typing']);
    Route::post('/rooms/{code}/stop-typing', [MessageController::class, 'stopTyping']);
});
