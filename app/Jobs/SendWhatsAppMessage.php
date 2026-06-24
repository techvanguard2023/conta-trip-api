<?php

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly array $phones,
        private readonly string $message,
    ) {}

    public function handle(): void
    {
        $service = new WhatsAppNotificationService();

        foreach ($this->phones as $phone) {
            try {
                $service->sendText($phone, $this->message);
            } catch (\Exception $e) {
                Log::error('Erro ao enviar WhatsApp', ['phone' => $phone, 'error' => $e->getMessage()]);
            }
        }
    }
}
