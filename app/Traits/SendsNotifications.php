<?php

namespace App\Traits;

use App\Services\FirebaseNotificationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

trait SendsNotifications
{
    /**
     * Obter tokens FCM dos participantes de uma trip
     */
    protected function getParticipantTokens($trip, $excludeUserId = null)
    {
        $query = $trip->participants()
            ->whereNotNull('user_id')
            ->with('user');

        if ($excludeUserId) {
            $query->whereNot('user_id', $excludeUserId);
        }

        return $query->get()
            ->map(fn($participant) => $participant->user->fcm_token)
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Enviar notificação de nova despesa (assíncrono por padrão)
     */
    protected function notifyNewExpense($trip, $expense)
    {
        $tokens = $this->getParticipantTokens($trip, $expense->payer_id);

        if (empty($tokens)) {
            return null;
        }

        try {
            // Usar modo assíncrono para não bloquear a requisição
            $service = new FirebaseNotificationService(true);
            return $service->notifyNewExpense($trip, $expense, $tokens);
        } catch (\Exception $e) {
            \Log::error('Erro ao enfileirar notificação de despesa', ['error' => $e->getMessage()]);
            // Não lançar exceção para não falhar a requisição
        }
    }

    /**
     * Enviar notificação de novo membro (assíncrono por padrão)
     */
    protected function notifyNewMember($trip, $member)
    {
        $tokens = $this->getParticipantTokens($trip);

        if (empty($tokens)) {
            return null;
        }

        try {
            $service = new FirebaseNotificationService(true);
            return $service->notifyNewMember($trip, $member, $tokens);
        } catch (\Exception $e) {
            \Log::error('Erro ao enfileirar notificação de novo membro', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Enviar notificação de alteração de status (assíncrono por padrão)
     */
    protected function notifyTripStatusChanged($trip)
    {
        $tokens = $this->getParticipantTokens($trip);

        if (empty($tokens)) {
            return null;
        }

        try {
            $service = new FirebaseNotificationService(true);
            return $service->notifyTripStatusChanged($trip, $tokens);
        } catch (\Exception $e) {
            \Log::error('Erro ao enfileirar notificação de status', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Enviar notificação de atualização de despesa (assíncrono por padrão)
     */
    protected function notifyExpenseUpdated($trip, $expense)
    {
        $tokens = $this->getParticipantTokens($trip);

        if (empty($tokens)) {
            return null;
        }

        try {
            $service = new FirebaseNotificationService(true);
            return $service->notifyExpenseUpdated($trip, $expense, $tokens);
        } catch (\Exception $e) {
            \Log::error('Erro ao enfileirar notificação de atualização', ['error' => $e->getMessage()]);
        }
    }
}
