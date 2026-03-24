<?php

namespace Padmission\FilamentTourEditor\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Str;
use Livewire\Component;
use Padmission\FilamentTourEditor\Models\Tour;

class LaunchTourAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'launchTour';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Take Tour')
            ->icon('heroicon-o-academic-cap')
            ->color('primary')
            ->visible(fn (): bool => $this->resolveCurrentTour() !== null)
            ->action(function (Component $livewire): void {
                $tour = $this->resolveCurrentTour();

                if ($tour === null) {
                    return;
                }

                $tourConfigId = $tour->json_config['id'] ?? Str::slug($tour->name);

                $livewire->dispatch('filament-tour::open-tour', id: $tourConfigId);
            });
    }

    protected function resolveCurrentTour(): ?Tour
    {
        $routeName = request()->route()?->getName();

        if ($routeName === null) {
            return null;
        }

        return Tour::query()
            ->active()
            ->forRoute($routeName)
            ->first();
    }
}
