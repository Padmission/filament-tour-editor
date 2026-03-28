<?php

namespace Padmission\FilamentTourEditor\Resources\TourResource\Schemas;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Padmission\FilamentTourEditor\Models\Tour;
use Padmission\FilamentTourEditor\Resources\TourResource\TourResource;

class TourTableSchema
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions(static::recordActions())
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->color('danger')
                    ->visible(fn (): bool => TourResource::canManageTours()),
            ])
            ->recordAction('edit');
    }

    /**
     * @return array<int, \Filament\Tables\Columns\Column>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('route')
                ->state(fn (Tour $record): string => $record->getResolvedRoutePath())
                ->url(fn (Tour $record): string => $record->getResolvedRoutePath())
                ->openUrlInNewTab(false)
                ->searchable()
                ->sortable()
                ->limit(40),
            TextColumn::make('steps_count')
                ->label('Steps')
                ->state(fn (Tour $record): int => count($record->getSteps())),
            IconColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->sortable(),
            TextColumn::make('created_at')
                ->label('Created Date')
                ->dateTime('m/d/Y')
                ->sortable(),
        ];
    }

    /**
     * @return array<int, \Filament\Actions\Action|ActionGroup>
     */
    public static function recordActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => TourResource::canManageTours())
                ->slideOver()
                ->modalDescription('Edit the tour here, then use the Tour Builder on the page itself if you need to pick or change target elements.')
                ->modalWidth(Width::TwoExtraLarge)
                ->mutateDataUsing(fn (array $data): array => TourResource::normalizeFormData($data)),
            ActionGroup::make([
                ReplicateAction::make()
                    ->label('Clone')
                    ->visible(fn (): bool => TourResource::canManageTours())
                    ->slideOver()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->excludeAttributes(['id', 'created_at', 'updated_at', 'sort_order'])
                    ->schema(TourFormSchema::components())
                    ->mutateRecordDataUsing(fn (array $data): array => TourResource::prefillCloneData($data))
                    ->beforeReplicaSaved(function (Tour $replica): void {
                        $jsonConfig = $replica->json_config ?? [];
                        data_set($jsonConfig, 'id', Str::slug($replica->name));

                        if (blank(data_get($jsonConfig, 'config'))) {
                            data_set($jsonConfig, 'config', [
                                'nextButtonLabel' => 'Next',
                                'previousButtonLabel' => 'Previous',
                                'doneButtonLabel' => 'Done',
                            ]);
                        }

                        $replica->json_config = $jsonConfig;
                    }),
                DeleteAction::make()
                    ->visible(fn (): bool => TourResource::canManageTours()),
            ]),
        ];
    }
}
