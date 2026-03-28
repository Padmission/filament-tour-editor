<?php

namespace Padmission\FilamentTourEditor;

use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use JibayMcs\FilamentTour\FilamentTourPlugin;
use Livewire\Component;
use Padmission\FilamentTourEditor\Models\Tour;
use Padmission\FilamentTourEditor\Resources\TourResource\TourResource;

class FilamentTourEditorPlugin implements Plugin
{
    use EvaluatesClosures;

    private bool|Closure $enableBuilder = true;
    private bool|Closure $enableCssSelector = false;
    private bool|Closure $enableResource = true;
    private string|Closure|null $navigationGroup = 'System';
    private string|Closure|null $navigationLabel = 'Tours';

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-tour-editor';
    }

    public function register(Panel $panel): void
    {
        // Register the base tour plugin if not already registered
        if (! $panel->hasPlugin('filament-tour')) {
            $tourPlugin = FilamentTourPlugin::make();

            if ($this->evaluate($this->enableCssSelector)) {
                $tourPlugin->enableCssSelector();
            }

            $panel->plugin($tourPlugin);
        }

        // Mount the tour builder component (invisible — slideover only)
        if ($this->evaluate($this->enableBuilder)) {
            $panel->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<livewire:filament-tour-builder />'),
            );

            $panel->userMenuItems([
                Action::make('launchTourBuilder')
                    ->label('Build Tour')
                    ->icon('heroicon-o-academic-cap')
                    ->visible(fn (): bool => Gate::allows('create', Tour::class))
                    ->action(function (Component $livewire): void {
                        $livewire->dispatch('launch-tour-builder');
                    }),
                Action::make('requestTourReset')
                    ->label('Reset Tours')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Component $livewire): void {
                        $livewire->dispatch('request-tour-reset');
                    }),
            ]);
        }

        // Register the resource for CRUD management
        if ($this->evaluate($this->enableResource)) {
            $panel->resources([
                TourResource::class,
            ]);
        }
    }

    public function boot(Panel $panel): void {}

    public function enableBuilder(bool|Closure $enable = true): static
    {
        $this->enableBuilder = $enable;

        return $this;
    }

    public function enableCssSelector(bool|Closure $enable = true): static
    {
        $this->enableCssSelector = $enable;

        return $this;
    }

    public function enableResource(bool|Closure $enable = true): static
    {
        $this->enableResource = $enable;

        return $this;
    }

    public function navigationGroup(string|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationLabel(string|Closure|null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->evaluate($this->navigationGroup);
    }

    public function getNavigationLabel(): ?string
    {
        return $this->evaluate($this->navigationLabel);
    }
}
