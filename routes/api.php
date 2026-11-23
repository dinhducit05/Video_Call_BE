<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);

    Route::get('/rooms/{roomCode}', [RoomController::class, 'show']);
    Route::post('/rooms/{roomCode}/join', [RoomController::class, 'join']);
    Route::post('/rooms/{roomCode}/leave', [RoomController::class, 'leave']);
    Route::get('/rooms/{roomCode}/access', [RoomController::class, 'checkAccess']);
});
