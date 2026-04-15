<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\FirebaseNotificationService;

class TestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test
                            {--user-id= : ID do usuário para testar}
                            {--sync : Executar sincronamente em vez de assincronamente}
                            {--title=Teste : Título da notificação}
                            {--body=Mensagem de teste : Corpo da notificação}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testar envio de notificações Firebase';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔔 Testando sistema de notificações Firebase...');
        $this->newLine();

        // Obter usuário
        $userId = $this->option('user-id') ?? User::whereNotNull('fcm_token')->first()?->id;

        if (!$userId) {
            $this->error('❌ Nenhum usuário com token FCM encontrado!');
            $this->info('Crie um usuário e registre um token com:');
            $this->line('POST /api/v1/fcm-token { "token": "seu_token_aqui" }');
            return 1;
        }

        $user = User::findOrFail($userId);

        if (!$user->fcm_token) {
            $this->error("❌ Usuário {$user->name} não possui token FCM!");
            return 1;
        }

        $this->info("✅ Usuário encontrado: {$user->name}");
        $this->info("📱 Token: " . substr($user->fcm_token, 0, 30) . '...');
        $this->newLine();

        try {
            $isSync = $this->option('sync');
            $title = $this->option('title');
            $body = $this->option('body');

            $this->info("Enviando notificação (" . ($isSync ? 'SÍNCRONO' : 'ASSÍNCRONO') . ")...");

            $service = new FirebaseNotificationService(!$isSync); // true = async

            if ($isSync) {
                $this->warn('⏳ Aguardando resposta do Firebase (pode levar alguns segundos)...');
            }

            $result = $service->sendNotification(
                $user->fcm_token,
                $title,
                $body,
                [
                    'type' => 'test',
                    'timestamp' => now()->toDateTimeString()
                ]
            );

            $this->newLine();
            $this->info('✅ Notificação enviada com sucesso!');
            $this->line('📤 Resultado: ' . json_encode($result));
            $this->newLine();

            if (!$isSync) {
                $this->info('💡 Modo assíncrono: A notificação foi enfileirada.');
                $this->info('   Execute "php artisan queue:work" para processar os jobs.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Erro ao enviar notificação!");
            $this->line("Mensagem: {$e->getMessage()}");
            $this->line("Código: {$e->getCode()}");

            if (str_contains($e->getMessage(), 'credential')) {
                $this->error("\n⚠️  Problema com credenciais Firebase!");
                $this->info("Certifique-se que storage/app/firebase-auth.json existe e é válido.");
            }

            if (str_contains($e->getMessage(), 'invalid')) {
                $this->error("\n⚠️  Token Firebase inválido ou expirado!");
                $this->info("Registre um novo token via: POST /api/v1/fcm-token");
            }

            return 1;
        }
    }
}
