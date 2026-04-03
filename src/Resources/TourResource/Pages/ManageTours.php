<?php

namespace Padmission\FilamentTourEditor\Resources\TourResource\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Padmission\FilamentTourEditor\Exports\TourExporter;
use Padmission\FilamentTourEditor\Imports\TourImporter;
use Padmission\FilamentTourEditor\Resources\TourResource\TourResource;

class ManageTours extends ManageRecords
{
    protected static string $resource = TourResource::class;
    protected ?string $subheading = 'Create tours with the Tour Builder on the page you want the tour to run on. Use this screen to edit, order, and delete existing tours.';
    public bool $showAdvancedTourFields = false;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()
                    ->label('Export Tours')
                    ->exporter(TourExporter::class),
                ImportAction::make()
                    ->label('Import Tours')
                    ->importer(TourImporter::class),
            ]),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')
                ->badge(fn (): int => $this->getTourBaseQuery()->count()),
        ];

        foreach (Filament::getPanels() as $panel) {
            $panelId = $panel->getId();

            $tabs[$panelId] = Tab::make($this->getPanelTabLabel($panelId))
                ->badge(fn (): int => $this->getTourBaseQuery()->where('panel', $panelId)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('panel', $panelId));
        }

        if ($this->getTourBaseQuery()->whereNull('panel')->exists()) {
            $tabs['unassigned'] = Tab::make('Unassigned')
                ->badge(fn (): int => $this->getTourBaseQuery()->whereNull('panel')->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('panel'));
        }

        return $tabs;
    }

    protected function getTourBaseQuery(): Builder
    {
        return TourResource::getEloquentQuery();
    }

    protected function getPanelTabLabel(string $panelId): string
    {
        return match ($panelId) {
            'app' => 'App',
            'admin' => 'Admin',
            'pm' => 'Property Manager',
            'cm' => 'Case Manager',
            'pt' => 'Participant',
            default => Str::of($panelId)->headline()->value(),
        };
    }
}
