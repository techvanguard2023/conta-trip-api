<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\DashboardController;
use App\Events\TestNotification;

Route::prefix('v1')->group(function () {

    Route::get('status', function () {
        return response()->json(['status' => 'API V1 ContaTrip is alive!'], 200);
    });

    Route::get('/test-push', function () {
        event(new TestNotification("Mensagem de teste via URL"));
        return "Evento disparado!";
    });

    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Viagens
        Route::get('/trips', [TripController::class, 'index']); // Listar viagens do usuário
        Route::post('/trips', [TripController::class, 'store']); // Criar viagem
        Route::get('/trips/{trip}', [TripController::class, 'show']); // Detalhes da viagem
        Route::post('/trips/join', [TripController::class, 'join']); // Entrar via código
        Route::delete('/trips/{id}', [TripController::class, 'destroy']); // Excluir grupo

        // Despesas
        Route::get('/trips/{trip}/expenses', [ExpenseController::class, 'index']); // Listar despesas
        Route::post('/trips/{trip}/expenses', [ExpenseController::class, 'store']); // Adicionar despesa
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']); // Remover despesa

        Route::get('/trips/{trip}/dashboard', [DashboardController::class, 'index']);
    });

});
