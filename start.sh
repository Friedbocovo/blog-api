#!/bin/bash

# Script pour démarrer l'API Laravel ET le serveur Reverb WebSocket

# Démarrer Reverb en arrière-plan
echo "Starting Reverb WebSocket server..."
php artisan reverb:start --host=0.0.0.0 --port=${REVERB_PORT:-8081} &

# Attendre un peu que Reverb démarre
sleep 2

# Démarrer le serveur HTTP Laravel
echo "Starting Laravel HTTP server..."
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}