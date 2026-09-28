<?php

namespace App\Traits;

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;

trait AuthorizesTripAccess
{
    /**
     * Garante que o usuário autenticado é participante da trip informada.
     * Centraliza a checagem que antes era duplicada manualmente em vários
     * métodos de TripController e ExpenseController.
     */
    private function ensureIsParticipant(Trip $trip, string $message = 'Você não tem permissão para acessar este grupo.'): void
    {
        $isParticipant = $trip->participants()->where('user_id', Auth::id())->exists();

        if (!$isParticipant) {
            abort(403, $message);
        }
    }
}
