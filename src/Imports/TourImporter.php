<?php

namespace Padmission\FilamentTourEditor\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Cache;
use Padmission\FilamentTourEditor\Models\Tour;

class TourImporter extends Importer
{
    protected static ?string $model = Tour::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Welcome Tour'),
            ImportColumn::make('description')
                ->rules(['nullable', 'string'])
                ->example('A guided tour of the dashboard'),
            ImportColumn::make('route')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('/admin'),
            ImportColumn::make('json_config')
                ->label('Configuration (JSON)')
                ->requiredMapping()
                ->rules(['required', 'json'])
                ->example('{"id":"welcome","steps":[{"title":"Welcome","description":"Welcome to this page.","element":".fi-header"}],"config":{"nextButtonLabel":"Next","previousButtonLabel":"Previous","doneButtonLabel":"Done"}}'),
            ImportColumn::make('is_active')
                ->boolean()
                ->rules(['nullable', 'boolean'])
                ->example('Yes'),
            ImportColumn::make('sort_order')
                ->integer()
                ->rules(['nullable', 'integer'])
                ->example('0'),
        ];
    }

    public function resolveRecord(): Tour
    {
        return new Tour;
    }

    public function beforeFill(): void
    {
        if (is_string($this->data['json_config'] ?? null)) {
            $decoded = json_decode($this->data['json_config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->data['json_config'] = $decoded;
            }
        }
    }

    public function beforeCreate(): void
    {
        if (($this->options['import_mode'] ?? 'add') === 'replace') {
            $cacheKey = "tour-import-purge-{$this->import->getKey()}";
            if (Cache::add($cacheKey, true, 300)) {
                Tour::query()->delete();
            }
        }
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Radio::make('import_mode')
                ->label('Import mode')
                ->options([
                    'add' => 'Add to existing tours',
                    'replace' => 'Replace all existing tours',
                ])
                ->default('add')
                ->required()
                ->descriptions([
                    'add' => 'Imported tours will be added alongside existing ones.',
                    'replace' => 'All existing tours will be deleted before importing.',
                ]),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your tour import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
