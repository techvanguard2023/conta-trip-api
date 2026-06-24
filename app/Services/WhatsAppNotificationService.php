<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.evolution_go.url'), '/');
        $this->apiKey  = config('services.evolution_go.api_key', '');
    }

    /**
     * Envia mensagem de texto para um número no formato internacional sem '+': 5521981321890
     */
    public function sendText(string $phone, string $message): void
    {
        try {
            $request = Http::acceptJson();

            if ($this->apiKey) {
                $request = $request->withHeaders(['apikey' => $this->apiKey]);
            }

            $request->post("{$this->baseUrl}/send/text", [
                'number' => $phone,
                'text'   => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp sendText falhou', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }
}
