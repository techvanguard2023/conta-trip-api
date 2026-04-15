<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class FirebaseNotificationService
{
    protected $messaging;
    protected $async = true;

    public function __construct($async = true)
    {
        $credentialsPath = storage_path('app/firebase-auth.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception("Arquivo de credenciais do Firebase não encontrado em: storage/app/firebase-auth.json");
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
            $this->async = $async;
        } catch (\Exception $e) {
            Log::error('Erro ao inicializar Firebase', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Definir modo assíncrono/síncrono
     */
    public function setAsync(bool $async): self
    {
        $this->async = $async;
        return $this;
    }

    /**
     * Enviar notificação para um único token
     *
     * @param string $token Token FCM do dispositivo
     * @param string $title Título da notificação
     * @param string $body Corpo da notificação
     * @param array $data Dados adicionais
     * @param bool $async Se true, enfileira o job; se false, executa sincronamente
     * @return mixed
     */
    public function sendNotification($token, $title, $body, $data = [], $async = null)
    {
        $async = $async ?? $this->async;

        if ($async) {
            // Enfileirar como job para não bloquear
            \App\Jobs\SendNotificationJob::dispatch($token, $title, $body, $data);
            Log::info('Notificação enfileirada', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title
            ]);
            return ['queued' => true];
        }

        // Modo síncrono (bloqueante)
        try {
            $notification = Notification::create($title, $body);
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $result = $this->messaging->send($message);

            Log::info('Notificação enviada com sucesso', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'message_id' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Erro ao enviar notificação Firebase', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        }
    }

    /**
     * Enviar notificação para múltiplos tokens
     *
     * @param array $tokens Array de tokens FCM
     * @param string $title Título da notificação
     * @param string $body Corpo da notificação
     * @param array $data Dados adicionais
     * @param bool $async Se true, enfileira como bulk job
     * @return array
     */
    public function sendNotificationToMultiple(array $tokens, $title, $body, $data = [], $async = null)
    {
        $async = $async ?? $this->async;

        if (empty($tokens)) {
            Log::warning('Tentativa de enviar notificação sem tokens', [
                'title' => $title
            ]);
            return [
                'queued' => false,
                'success' => 0,
                'failed' => 0,
                'message' => 'Nenhum token disponível'
            ];
        }

        if ($async) {
            // Enfileirar como bulk job
            \App\Jobs\SendBulkNotificationsJob::dispatch($tokens, $title, $body, $data);

            Log::info('Notificações em bulk enfileiradas', [
                'total_tokens' => count($tokens),
                'title' => $title
            ]);

            return [
                'queued' => true,
                'total_tokens' => count($tokens),
                'message' => 'Notificações enfileiradas para envio'
            ];
        }

        // Modo síncrono (bloqueante)
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($tokens as $token) {
            try {
                $this->sendNotification($token, $title, $body, $data, false);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'token' => substr($token, 0, 20) . '...',
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
