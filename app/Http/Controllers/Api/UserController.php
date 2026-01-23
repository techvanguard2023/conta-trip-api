<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Trip;

class UserController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        // Mapear pixKey para pix_key se enviado em camelCase
        if ($request->has('pixKey')) {
            $request->merge(['pix_key' => $request->input('pixKey')]);
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
            'pix_key' => 'nullable|string|max:255',
        ]);
        
        $user->update($validated);
        
        return response()->json([
            'message' => 'Perfil atualizado com sucesso',
            'data' => $user
        ]);
    }

    public function getPixKey(Request $request, $userId)
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($userId);
        
        // Verificar se os usuários compartilham algum trip
        $sharedTrips = Trip::whereHas('participants', function($query) use ($currentUser) {
            $query->where('user_id', $currentUser->id);
        })->whereHas('participants', function($query) use ($targetUser) {
            $query->where('user_id', $targetUser->id);
        })->exists();
        
        if (!$sharedTrips) {
            return response()->json([
                'message' => 'Não autorizado'
            ], 403);
        }
        
        if (!$targetUser->pix_key) {
            return response()->json([
                'message' => 'Chave PIX não cadastrada'
            ], 404);
        }
        return response()->json([
            'pixKey' => $targetUser->pix_key
        ]);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $user = $request->user();
        $user->update([
            'fcm_token' => $request->token
        ]);

        return response()->json([
            'message' => 'FCM Token atualizado com sucesso'
        ]);
    }
}
