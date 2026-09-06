# SIGS Admin — Backend Laravel (API)

## Sauvegardes automatiques

La commande `php artisan sigs:backup` crée une sauvegarde SQL dans `storage/app/backups`. Le planificateur l'exécute chaque jour à 23:55 ; le serveur doit exécuter `php artisan schedule:work` en continu. Sous Windows, définir `MYSQLDUMP_PATH` dans `.env` si `mysqldump` n'est pas disponible dans le PATH.

API REST **Laravel 11** organisée en **un module par domaine métier**
(`app/Modules/<Domaine>`), chargée automatiquement par
`App\Providers\ModuleServiceProvider` (routes + migrations).

## Structure

```
app/Modules/
├── Auth/            login / logout (Sanctum, session SPA)
├── Users/            utilisateurs, rôles, permissions (checkbox)
├── AcademicYears/     années scolaires + paramètres établissement
├── SchoolClasses/     cycles + classes + scolarité
├── Students/           élèves + tuteurs (chiffrés), matricule auto
├── Tranches/          tranches de paiement (règle : somme <= scolarité)
├── Fees/              autres frais (affectation multi-classes)
├── Payments/           paiements multi-lignes, non modifiables
├── Debtors/            liste et calcul des débiteurs
├── Teachers/            enseignants
├── Payroll/             paie enseignants
├── Security/            journal d'audit
└── Dashboard/           agrégations pour les KPI du tableau de bord
```

Chaque module suit la même convention :
`Models/`, `Http/Controllers/`, `Http/Requests/`, `routes.php`,
`database/migrations/`. Ajouter un nouveau domaine = créer un nouveau
dossier avec cette structure, rien d'autre à enregistrer.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# renseigner DB_* dans .env, puis :
php artisan migrate --seed
php artisan serve
```

Compte de démarrage créé par le seeder : `admin@schoolflow.local` /
`ChangeMoi!2026` (à changer immédiatement).

## Règles métier préservées (issues du prototype d'origine)

- Matricule élève généré **côté serveur** (`Students/Services/MatriculeGenerator`),
  format `ELV-AAAA-000001`, avec verrou pessimiste anti-collision.
- Tuteur/parent **obligatoire** à l'inscription d'un élève.
- Somme des tranches d'une classe **ne peut jamais dépasser** la scolarité
  annuelle (`Tranches/Http/Controllers/TrancheController`).
- Un paiement peut contenir **plusieurs lignes** (tranches + autres frais),
  **n'est jamais modifiable** après création (`Payments/Services/PaymentService`),
  et ne peut pas dépasser le montant restant dû par ligne.
- Autres frais affectables à **plusieurs classes en un clic**.
- Permissions **par checkbox**, par rôle + surcharges individuelles
  (`user_permissions`), middleware `permission:<code>`.
- Champs sensibles (nom/prénom élève, coordonnées tuteur, téléphone
  enseignant/utilisateur) **chiffrés au repos** via les casts Eloquent
  `encrypted` (remplace l'AES-256-GCM fait main par l'AES-256-CBC natif
  de Laravel, piloté par `APP_KEY` — même intention de sécurité, chiffrement
  géré par le framework plutôt qu'à la main).
- Journal d'audit (`audit_logs`) conservé.

## Authentification (SPA)

Le frontend (React) doit :
1. `GET /sanctum/csrf-cookie`
2. `POST /api/auth/login` avec `{ email, password }`
3. Les requêtes suivantes passent par les cookies de session (pas de token
   à gérer manuellement).
