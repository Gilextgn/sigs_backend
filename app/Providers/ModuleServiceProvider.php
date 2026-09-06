<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Charge automatiquement chaque module métier présent dans app/Modules/*.
 *
 * Convention par module (ex: app/Modules/Students/) :
 *   - routes.php              -> routes API du module (préfixées par /api)
 *   - database/migrations/*   -> migrations propres au module
 *
 * Ajouter un nouveau domaine métier = créer un nouveau dossier dans
 * app/Modules avec cette structure, aucune autre configuration requise.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return;
        }

        foreach (scandir($modulesPath) as $moduleName) {
            if ($moduleName === '.' || $moduleName === '..') {
                continue;
            }

            $modulePath = $modulesPath.DIRECTORY_SEPARATOR.$moduleName;

            if (! is_dir($modulePath)) {
                continue;
            }

            $this->loadModuleRoutes($modulePath);
            $this->loadModuleMigrations($modulePath);
        }
    }

    private function loadModuleRoutes(string $modulePath): void
    {
        $routesFile = $modulePath.'/routes.php';

        if (file_exists($routesFile)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($routesFile);
        }
    }

    private function loadModuleMigrations(string $modulePath): void
    {
        $migrationsPath = $modulePath.'/database/migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }
}
