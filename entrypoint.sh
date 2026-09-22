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

# Compte du propriétaire de la plateforme (celui qui crée et suspend les
# écoles). Recréé à CHAQUE démarrage si les variables sont définies : une
# migration ne tourne qu'une fois, donc ajouter les variables après un
# premier déploiement n'aurait sinon aucun effet. Une fois que le
# propriétaire a changé son mot de passe dans la console, le redémarrage ne
# l'écrase plus. Accès perdu : définir PLATFORM_OWNER_RESET_PASSWORD=true,
# redémarrer, se connecter avec PLATFORM_OWNER_EMAIL / PASSWORD, puis retirer
# la variable. Un échec ne doit pas empêcher l'application de démarrer.
if [ "$DEMO_MODE" != "true" ] && [ -n "$PLATFORM_OWNER_EMAIL" ] && [ -n "$PLATFORM_OWNER_PASSWORD" ]; then
  RESET_FLAG=""
  if [ "$PLATFORM_OWNER_RESET_PASSWORD" = "true" ]; then
    RESET_FLAG="--reset-password"
  fi
  php artisan platform:create-owner "$PLATFORM_OWNER_EMAIL" "$PLATFORM_OWNER_PASSWORD" --name="${PLATFORM_OWNER_NAME:-Propriétaire SIGS}" $RESET_FLAG || true
fi

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
