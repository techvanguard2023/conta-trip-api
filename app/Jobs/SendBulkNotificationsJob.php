<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tokens;
    protected $title;
    protected $body;
    protected $data;

    public $tries = 2;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(array $tokens, $title, $body, $data = [])
    {
        $this->tokens = $tokens;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Iniciando envio em bulk de notificações', [
            'total_tokens' => count($this->tokens),
            'title' => $this->title
        ]);

        foreach ($this->tokens as $token) {
            // Dispatch cada notificação como um job separado
            SendNotificationJob::dispatch(
                $token,
                $this->title,
                $this->body,
                $this->data
            );
        }

        Log::info('Notificações em bulk enfileiradas com sucesso', [
            'total' => count($this->tokens)
        ]);
    }
}
