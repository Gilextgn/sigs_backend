#!/bin/bash
# Exécuter les migrations de base de données
php artisan migrate --force

# Lancer Apache en premier plan
apache2-foreground
