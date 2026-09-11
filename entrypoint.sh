#!/bin/bash
# Nettoyer et régénérer les caches de production
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# L'instance de démonstration utilise une base SQLite dans le conteneur :
# elle est éphémère, donc le fichier doit exister avant les migrations.
if [ "$DEMO_MODE" = "true" ] && [ -n "$DB_DATABASE" ]; then
  mkdir -p "$(dirname "$DB_DATABASE")"
  touch "$DB_DATABASE"
fi

# Exécuter les migrations de base de données
php artisan migrate --force

# Démonstration publique uniquement : jeu de données fictif + compte en
# lecture seule, régénérés à chaque démarrage. Jamais exécuté en
# production, où DEMO_MODE n'est pas défini.
if [ "$DEMO_MODE" = "true" ]; then
  php artisan db:seed --force
  php artisan demo:provision
  php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder --force
fi

# Lancer Apache en premier plan
apache2-foreground
