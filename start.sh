#!/bin/bash

# Recria o arquivo de credenciais do Firebase a partir de uma env var, se
# configurada. O arquivo é ignorado pelo git de propósito (é um segredo),
# então um deploy baseado em git nunca o traz pro servidor sozinho — isso
# fazia toda notificação falhar com "Arquivo de credenciais do Firebase
# não encontrado". Configure FIREBASE_CREDENTIALS_BASE64 nas variáveis de
# ambiente do painel (nunca no .env commitado) com o conteúdo do
# firebase-auth.json em base64.
if [ -n "$FIREBASE_CREDENTIALS_BASE64" ]; then
    mkdir -p storage/app
    echo "$FIREBASE_CREDENTIALS_BASE64" | base64 -d > storage/app/firebase-auth.json
fi

# Limpa caches — nunca usar config:cache em ambiente com variáveis dinâmicas
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cria o symlink público do storage (necessário para servir imagens)
php artisan storage:link --force

# Roda migrations automaticamente no deploy
php artisan migrate --force

# Inicia o queue worker em background (reinicia automaticamente quando o
# processo encerra por --max-time ou por falha — sem isso, o worker morre
# após 1h e as notificações param de ser enviadas silenciosamente)
while true; do
    php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    sleep 2
done &

# Inicia o scheduler em background (roda a cada minuto)
while true; do
    php artisan schedule:run
    sleep 60
done &

# Inicia o servidor web (processo principal)
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
