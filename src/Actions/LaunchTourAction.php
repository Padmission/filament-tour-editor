<?php

namespace Padmission\FilamentTourEditor\Actions;

use Filament\Actions\Action;
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

                $livewire->dispatch('filament-tour-editor::preview-tour', tour: $tour->toFilamentTourArray());
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
