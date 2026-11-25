<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallHistoryController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);

    Route::get('/rooms/{roomCode}', [RoomController::class, 'show']);
    Route::post('/rooms/{roomCode}/join', [RoomController::class, 'join']);
    Route::post('/rooms/{roomCode}/start', [RoomController::class, 'startCall']);
    Route::post('/rooms/{roomCode}/end', [RoomController::class, 'endCall']);

    Route::get('/rooms/{roomCode}/participants', [RoomController::class, 'participants']);
    Route::patch('/rooms/{roomCode}/media', [RoomController::class, 'updateMediaState']);
    Route::post('/rooms/{roomCode}/leave', [RoomController::class, 'leave']);
    Route::get('/rooms/{roomCode}/access', [RoomController::class, 'checkAccess']);

    Route::get('/rooms/{roomCode}/messages', [MessageController::class, 'index']);
    Route::post('/rooms/{roomCode}/messages', [MessageController::class, 'store']);

    Route::get('/call-histories', [CallHistoryController::class, 'index']);
});
