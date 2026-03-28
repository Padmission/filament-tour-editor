<?php

namespace Padmission\FilamentTourEditor\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Padmission\FilamentTourEditor\Models\Tour;

class TourExporter extends Exporter
{
    protected static ?string $model = Tour::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('description'),
            ExportColumn::make('route'),
            ExportColumn::make('json_config')
                ->label('Configuration (JSON)')
                ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_UNESCAPED_SLASHES) : ($state ?? '')),
            ExportColumn::make('is_active')
                ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
            ExportColumn::make('sort_order'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your tour export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
