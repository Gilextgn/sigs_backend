#!/bin/bash
# L'instance de démonstration utilise une base SQLite dans le conteneur :
# elle est éphémère, donc le fichier doit exister AVANT toute commande
# artisan qui touche la base.
if [ "$DEMO_MODE" = "true" ] && [ -n "$DB_DATABASE" ]; then
  mkdir -p "$(dirname "$DB_DATABASE")"
  touch "$DB_DATABASE"
fi

# config:clear / route:clear n'accèdent pas à la base : sans risque avant
# les migrations.
php artisan config:clear
php artisan route:clear

# Exécuter les migrations AVANT tout ce qui touche la base. Sur une base
# neuve (démo SQLite), la table "cache" elle-même n'existe pas tant que
# les migrations n'ont pas tourné — cache:clear doit donc venir après.
php artisan migrate --force

php artisan cache:clear

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
