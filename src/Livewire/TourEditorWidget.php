<?php

namespace Padmission\FilamentTourEditor\Livewire;

use Filament\Facades\Filament;
use Illuminate\Support\Str;
use JibayMcs\FilamentTour\FilamentTourPlugin;
use JibayMcs\FilamentTour\Highlight\HasHighlight;
use JibayMcs\FilamentTour\Livewire\FilamentTourWidget;
use JibayMcs\FilamentTour\Tour\HasTour;
use Livewire\Attributes\On;
use Padmission\FilamentTourEditor\Models\Tour;

class TourEditorWidget extends FilamentTourWidget
{
    #[On('filament-tour::load-elements')]
    public function load(): void
    {
        $this->tours = [];
        $this->highlights = [];
        $shouldAutoStartTours = FilamentTourPlugin::resolveAutoStartTours();

        if ($shouldAutoStartTours) {
            $this->loadTraitTours();
            $this->loadDatabaseTours();
        } else {
            $this->loadHighlights();
        }

        $this->dispatch('filament-tour::loaded-elements',
            auto_start_tours: $shouldAutoStartTours,
            only_visible_once: FilamentTourPlugin::get()->getHistoryType() == 'local_storage'
                && (is_bool(FilamentTourPlugin::get()->isOnlyVisibleOnce())
                    ? FilamentTourPlugin::get()->isOnlyVisibleOnce()
                    : config('filament-tour.only_visible_once')),
            tours: $this->tours,
            highlights: $this->highlights,
        );

        $hasCssSelector = is_bool(FilamentTourPlugin::get()->isCssSelectorEnabled())
            ? FilamentTourPlugin::get()->isCssSelectorEnabled()
            : config('filament-tour.enable_css_selector');
        $this->dispatch('filament-tour::change-css-selector-status', enabled: $hasCssSelector);
    }

    #[On('filament-tour-editor::preview-tour')]
    public function preview(array $tour): void
    {
        $this->dispatch('filament-tour::loaded-elements',
            auto_start_tours: false,
            only_visible_once: false,
            tours: [$tour],
            highlights: [],
        );

        $this->dispatch('filament-tour::open-tour', id: Str::after($tour['id'], config('filament-tour.tour_prefix_id', 'tour_')));
    }

    private function loadTraitTours(): void
    {
        $filamentClasses = [];

        foreach (array_merge(Filament::getResources(), Filament::getPages()) as $class) {
            $instance = new $class;

            if ($instance instanceof \Filament\Resources\Resource) {
                collect($instance->getPages())->map(fn ($item) => $item->getPage())
                    ->flatten()
                    ->each(function ($item) use (&$filamentClasses) {
                        $filamentClasses[] = $item;
                    });
            } else {
                $filamentClasses[] = $class;
            }
        }

        foreach ($filamentClasses as $class) {
            $traits = class_uses($class);

            if (in_array(HasTour::class, $traits)) {
                $this->tours = array_merge($this->tours, (new $class)->constructTours($class));
            }

            if (in_array(HasHighlight::class, $traits)) {
                $this->highlights = array_merge($this->highlights, (new $class)->constructHighlights($class));
            }
        }
    }

    private function loadHighlights(): void
    {
        $filamentClasses = [];

        foreach (array_merge(Filament::getResources(), Filament::getPages()) as $class) {
            $instance = new $class;

            if ($instance instanceof \Filament\Resources\Resource) {
                collect($instance->getPages())->map(fn ($item) => $item->getPage())
                    ->flatten()
                    ->each(function ($item) use (&$filamentClasses) {
                        $filamentClasses[] = $item;
                    });
            } else {
                $filamentClasses[] = $class;
            }
        }

        foreach ($filamentClasses as $class) {
            $traits = class_uses($class);

            if (in_array(HasHighlight::class, $traits)) {
                $this->highlights = array_merge($this->highlights, (new $class)->constructHighlights($class));
            }
        }
    }

    private function loadDatabaseTours(): void
    {
        $panelId = Filament::getCurrentPanel()?->getId();

        $dbTours = Tour::query()
            ->active()
            ->when($panelId, fn ($query) => $query->where(
                fn ($q) => $q->where('panel', $panelId)->orWhereNull('panel')
            ))
            ->orderBy('sort_order')
            ->get();

        foreach ($dbTours as $tour) {
            $this->tours[] = $tour->toFilamentTourArray();
        }
    }

    public function render()
    {
        return view('filament-tour::livewire.filament-tour-widget');
    }
}
