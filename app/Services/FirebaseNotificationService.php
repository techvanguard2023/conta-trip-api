<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase-auth.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception("Arquivo de credenciais do Firebase não encontrado em: storage/app/firebase-auth.json");
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Enviar notificação para um único token
     */
    public function sendNotification($token, $title, $body, $data = [])
    {
        try {
            $notification = Notification::create($title, $body);
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            return $this->messaging->send($message);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar notificação Firebase', [
                'token' => $token,
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Enviar notificação para múltiplos tokens
     */
    public function sendNotificationToMultiple(array $tokens, $title, $body, $data = [])
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($tokens as $token) {
            try {
                $this->sendNotification($token, $title, $body, $data);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'token' => $token,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Enviar notificação de nova despesa
     */
    public function notifyNewExpense($trip, $expense, $tokens)
    {
        $title = 'Nova Despesa';
        $body = "Despesa adicionada: {$expense->description}";
        $data = [
            'tripId' => $trip->id,
            'expenseId' => $expense->id,
            'action' => 'view_expense',
            'amount' => (string) $expense->amount
        ];

        return $this->sendNotificationToMultiple($tokens, $title, $body, $data);
    }

    /**
     * Enviar notificação de novo membro adicionado
     */
    public function notifyNewMember($trip, $member, $tokens)
    {
        $title = 'Novo Membro';
        $body = "{$member->name} foi adicionado ao grupo {$trip->name}";
        $data = [
            'tripId' => $trip->id,
            'action' => 'view_trip',
            'memberId' => $member->id
        ];

        return $this->sendNotificationToMultiple($tokens, $title, $body, $data);
    }

    /**
     * Enviar notificação de alteração de status do grupo
     */
    public function notifyTripStatusChanged($trip, $tokens)
    {
        $status = $trip->status === 'archived' ? 'arquivado' : 'reativado';
        $title = 'Grupo ' . ucfirst($status);
        $body = "O grupo {$trip->name} foi {$status}";
        $data = [
            'tripId' => $trip->id,
            'action' => 'view_trip',
            'status' => $trip->status
        ];

        return $this->sendNotificationToMultiple($tokens, $title, $body, $data);
    }

    /**
     * Enviar notificação de acerto de contas (PIX)
     */
    public function notifyPaymentSettlement($trip, $payer, $receiver, $amount, $tokens)
    {
        $title = 'Acerto de Contas';
        $body = "{$payer->name} deve transferir R$ {$amount} para {$receiver->name}";
        $data = [
            'tripId' => $trip->id,
            'action' => 'view_trip',
            'type' => 'payment_settlement',
            'amount' => (string) $amount
        ];

        return $this->sendNotificationToMultiple($tokens, $title, $body, $data);
    }

    /**
     * Enviar notificação de atualização de despesa
     */
    public function notifyExpenseUpdated($trip, $expense, $tokens)
    {
        $title = 'Despesa Atualizada';
        $body = "Despesa atualizada: {$expense->description}";
        $data = [
            'tripId' => $trip->id,
            'expenseId' => $expense->id,
            'action' => 'view_expense',
            'amount' => (string) $expense->amount
        ];

        return $this->sendNotificationToMultiple($tokens, $title, $body, $data);
    }
}
