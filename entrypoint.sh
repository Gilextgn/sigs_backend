#!/bin/bash
# Nettoyer et régénérer les caches de production
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Exécuter les migrations de base de données
php artisan migrate --force

# Lancer Apache en premier plan
apache2-foreground
