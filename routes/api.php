<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\DashboardController;
use App\Events\TestNotification;
use App\Http\Controllers\Api\UserController;

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
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::get('reset-password/{token}', function (Request $request, $token) {
        $email = $request->query('email');
        return redirect("https://www.divididinho.com.br/?token={$token}&email={$email}");
    })->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::put('/update-me', [UserController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/fcm-token', [UserController::class, 'updateFcmToken']);
        Route::get('/test-fcm', [UserController::class, 'testFcmNotification']);

        Route::get('/users/{userId}/pix', [UserController::class, 'getPixKey']);

        Route::get('/trips', [TripController::class, 'index']); 
        Route::post('/trips', [TripController::class, 'store']); 
        Route::get('/trips/{trip}', [TripController::class, 'show']); 
        Route::get('/trips/{trip}/pix-keys', [TripController::class, 'listPixKeys']); 
        Route::post('/trips/join', [TripController::class, 'join']); 
        Route::delete('/trips/{id}', [TripController::class, 'destroy']); 
        Route::post('/trips/{trip}/participants', [TripController::class, 'addParticipant']);
        Route::delete('/trips/{trip}/participants/{participantId}', [TripController::class, 'removeParticipant']);

        Route::get('/trips/{trip}/expenses', [ExpenseController::class, 'index']); 
        Route::post('/trips/{trip}/expenses', [ExpenseController::class, 'store']); 
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']); 

        Route::get('/trips/{trip}/dashboard', [DashboardController::class, 'index']);
    });

});
