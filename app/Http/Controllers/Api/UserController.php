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

    public function testFcmNotification(Request $request)
    {
        $user = $request->user();

        if (!$user->fcm_token) {
            return response()->json(['message' => 'Usuário não possui fcm_token registrado.'], 400);
        }

        try {
            $firebaseService = new \App\Services\FirebaseNotificationService();
            $firebaseService->sendNotification(
                $user->fcm_token,
                'Teste de Notificação',
                'Esta é uma notificação de teste do ContaTrip!'
            );

            return response()->json(['message' => 'Notificação enviada com sucesso!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao enviar notificação: ' . $e->getMessage()], 500);
        }
    }

    public function uploadProfileImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png|max:2048', // max 2MB
        ]);

        $user = $request->user();

        // Deletar imagem antiga se existir
        if ($user->profile_image) {
            \Storage::disk('public')->delete($user->profile_image);
        }

        // Salvar nova imagem
        $path = $request->file('image')->store('profile-images', 'public');

        // Atualizar usuário
        $user->update([
            'profile_image' => $path
        ]);

        return response()->json([
            'message' => 'Imagem de perfil atualizada com sucesso',
            'profile_image_url' => $user->profile_image_url
        ]);
    }

    public function deleteProfileImage(Request $request)
    {
        $user = $request->user();

        if (!$user->profile_image) {
            return response()->json([
                'message' => 'Usuário não possui imagem de perfil'
            ], 404);
        }

        // Deletar arquivo
        \Storage::disk('public')->delete($user->profile_image);

        // Atualizar usuário
        $user->update([
            'profile_image' => null
        ]);

        return response()->json([
            'message' => 'Imagem de perfil removida com sucesso'
        ]);
    }
}
