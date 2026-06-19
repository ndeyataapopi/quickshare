<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerModuleProviders();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->bootModuleMigrations();
    }

    /**
     * Auto-discover and register each module's service provider.
     */
    protected function registerModuleProviders(): void
    {
        $modules = $this->getModules();

        foreach ($modules as $module) {
            $providerClass = "App\\Modules\\{$module}\\{$module}ServiceProvider";

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Load migrations from each module that has them.
     */
    protected function bootModuleMigrations(): void
    {
        $modules = $this->getModules();

        foreach ($modules as $module) {
            $migrationPath = app_path("Modules/{$module}/Migrations");

            if (is_dir($migrationPath)) {
                $this->loadMigrationsFrom($migrationPath);
            }
        }
    }

    /**
     * Get all module directory names.
     */
    protected function getModules(): array
    {
        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return [];
        }

        return array_values(array_filter(
            scandir($modulesPath),
            fn ($dir) => ! in_array($dir, ['.', '..']) && is_dir("{$modulesPath}/{$dir}")
        ));
    }
}
