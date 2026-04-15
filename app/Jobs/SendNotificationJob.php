<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $token;
    protected $title;
    protected $body;
    protected $data;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct($token, $title, $body, $data = [])
    {
        $this->token = $token;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $service = new FirebaseNotificationService();
            $service->sendNotification(
                $this->token,
                $this->title,
                $this->body,
                $this->data
            );

            Log::info('Notificação enviada com sucesso', [
                'token' => substr($this->token, 0, 20) . '...',
                'title' => $this->title
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar notificação', [
                'token' => substr($this->token, 0, 20) . '...',
                'title' => $this->title,
                'error' => $e->getMessage()
            ]);

            // Falha após 3 tentativas
            if ($this->attempts() >= $this->tries) {
                Log::warning('Notificação descartada após múltiplas tentativas', [
                    'token' => substr($this->token, 0, 20) . '...'
                ]);
                $this->fail($e);
            } else {
                // Tenta novamente
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            }
        }
    }
}
