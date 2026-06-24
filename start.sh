#!/bin/bash

# Limpa caches — nunca usar config:cache em ambiente com variáveis dinâmicas
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Roda migrations automaticamente no deploy
php artisan migrate --force

# Inicia o queue worker em background
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &

# Inicia o scheduler em background (roda a cada minuto)
while true; do
    php artisan schedule:run
    sleep 60
done &

# Inicia o servidor web (processo principal)
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
