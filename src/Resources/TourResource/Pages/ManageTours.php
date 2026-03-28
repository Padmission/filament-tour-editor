<?php

namespace Padmission\FilamentTourEditor\Resources\TourResource\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;
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
}
