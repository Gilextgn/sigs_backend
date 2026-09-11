#!/bin/bash
# Nettoyer et régénérer les caches de production
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Exécuter les migrations de base de données
php artisan migrate --force

# TEMPORAIRE : seed initial de la base de production (crée le rôle admin,
# les permissions et le compte admin@sigs.com). A retirer après confirmation
# que le seed a bien tourné sur Render.
php artisan db:seed --force

# Lancer Apache en premier plan
apache2-foreground
