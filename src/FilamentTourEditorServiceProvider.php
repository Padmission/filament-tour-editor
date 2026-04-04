<?php

namespace Padmission\FilamentTourEditor;

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\FilamentTourEditor\Commands\GenerateTourMigrationCommand;
use Padmission\FilamentTourEditor\Livewire\TourBuilder;
use Padmission\FilamentTourEditor\Livewire\TourEditorWidget;
use Padmission\FilamentTourEditor\Models\Tour;
use Padmission\FilamentTourEditor\Policies\TourPolicy;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTourEditorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-tour-editor';
    public static string $viewNamespace = 'filament-tour-editor';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile(static::$name)
            ->hasViews(static::$viewNamespace)
            ->hasCommand(GenerateTourMigrationCommand::class)
            ->hasMigration('create_tours_table')
            ->runsMigrations();
    }

    public function packageBooted(): void
    {
        $this->publishes([
            __DIR__ . '/Policies/TourPolicy.php' => app_path('Policies/TourPolicy.php'),
        ], 'filament-tour-editor-policies');

        // Use the app's published policy if available, otherwise fall back to the package default
        $appPolicy = 'App\\Policies\\TourPolicy';
        Gate::policy(Tour::class, class_exists($appPolicy) ? $appPolicy : TourPolicy::class);

        // Override the base filament-tour widget to inject database tours
        Livewire::component('filament-tour-widget', TourEditorWidget::class);

        // Register the tour builder component
        Livewire::component('filament-tour-builder', TourBuilder::class);
    }
}
