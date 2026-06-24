<?php

namespace App\Traits;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Expense;
use App\Models\RecurringExpense;
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

    protected function getParticipantPhones($trip, ?string $excludeUserId = null): array
    {
        $query = $trip->participants()
            ->whereNotNull('user_id')
            ->with('user');

        if ($excludeUserId) {
            $query->whereNot('user_id', $excludeUserId);
        }

        return $query->get()
            ->filter(fn($p) => $p->user?->phone && $p->user?->whatsapp_notifications)
            ->map(fn($p) => $p->user->phone)
            ->values()
            ->toArray();
    }

    protected function notifyWhatsAppNewExpense($trip, Expense $expense): void
    {
        $phones = $this->getParticipantPhones($trip, $expense->payer_id);
        if (empty($phones)) return;

        $payerName = $trip->participants()->find($expense->payer_id)?->name ?? 'Alguém';
        $amount    = 'R$ ' . number_format($expense->amount, 2, ',', '.');

        $message = "💸 *{$trip->name}*\n"
                 . "{$payerName} adicionou uma despesa: *{$expense->description}* ({$amount}).\n"
                 . "Acesse o app pra ver os detalhes.";

        $this->dispatchWhatsApp($phones, $message);
    }

    protected function notifyWhatsAppPayment($trip, Expense $expense): void
    {
        $phones = $this->getParticipantPhones($trip, $expense->payer_id);
        if (empty($phones)) return;

        $payerName = $trip->participants()->find($expense->payer_id)?->name ?? 'Alguém';
        $amount    = 'R$ ' . number_format($expense->amount, 2, ',', '.');

        $message = "✅ *{$trip->name}*\n"
                 . "{$payerName} quitou uma dívida de *{$amount}*.\n"
                 . "Acesse o app pra ver o saldo atualizado.";

        $this->dispatchWhatsApp($phones, $message);
    }

    protected function notifyWhatsAppRecurringDue($trip, RecurringExpense $template): void
    {
        $phones = $this->getParticipantPhones($trip);
        if (empty($phones)) return;

        $amount  = 'R$ ' . number_format($template->amount, 2, ',', '.');

        $message = "🔁 *{$trip->name}*\n"
                 . "A despesa recorrente *{$template->description}* ({$amount}) está pendente de confirmação.\n"
                 . "Acesse o app pra confirmar o lançamento.";

        $this->dispatchWhatsApp($phones, $message);
    }

    protected function notifyRecurringExpensePending($trip, RecurringExpense $template): void
    {
        $tokens = $this->getParticipantTokens($trip);
        if (empty($tokens)) return;

        try {
            $service = new FirebaseNotificationService(true);
            $service->sendNotificationToMultiple(
                $tokens,
                'Despesa Recorrente Pendente',
                "Confirme: {$template->description}",
                ['tripId' => $trip->id, 'action' => 'view_recurring']
            );
        } catch (\Exception $e) {
            \Log::error('Erro ao enviar notificação de recorrente', ['error' => $e->getMessage()]);
        }
    }

    private function dispatchWhatsApp(array $phones, string $message): void
    {
        SendWhatsAppMessage::dispatch($phones, $message);
    }
}
