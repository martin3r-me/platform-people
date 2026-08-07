<?php

namespace Platform\People;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config laden (Laravel Best Practice: in register())
        $this->mergeConfigFrom(__DIR__.'/../config/people.php', 'people');
    }

    public function boot(): void
    {
        // Modul registrieren
        if (
            config()->has('people.routing') &&
            config()->has('people.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'people',
                'title'      => 'People',
                'group'      => 'admin',
                'routing'    => config('people.routing'),
                'guard'      => config('people.guard'),
                'navigation' => config('people.navigation'),
                'sidebar'    => config('people.sidebar'),
            ]);
        }

        // Routes laden
        if (PlatformCore::getModule('people')) {
            ModuleRouter::group('people', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Config veröffentlichen
        $this->publishes([
            __DIR__.'/../config/people.php' => config_path('people.php'),
        ], 'config');

        // Views & Livewire
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'people');
        $this->registerLivewireComponents();

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();
    }

    /**
     * Registriert People-Tools für die AI/Chat-Funktionalität.
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Skill-Katalog
            $registry->register(new \Platform\People\Tools\CreateSkillTool());
            $registry->register(new \Platform\People\Tools\UpdateSkillTool());
            $registry->register(new \Platform\People\Tools\DeleteSkillTool());
            $registry->register(new \Platform\People\Tools\ListSkillsTool());

            // Employee-Skill-Zuordnung (Fähigkeits-Bestand)
            $registry->register(new \Platform\People\Tools\AssignEmployeeSkillTool());
            $registry->register(new \Platform\People\Tools\UpdateEmployeeSkillTool());
            $registry->register(new \Platform\People\Tools\RemoveEmployeeSkillTool());
            $registry->register(new \Platform\People\Tools\ListEmployeeSkillsTool());
        } catch (\Throwable $e) {
            // Registry nicht verfügbar (z. B. in Tests/CLI ohne Core-Tools) — ignorieren.
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\People\\Livewire';
        $prefix = 'people';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
