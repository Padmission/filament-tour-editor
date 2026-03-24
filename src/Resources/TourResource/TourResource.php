<?php

namespace Padmission\FilamentTourEditor\Resources\TourResource;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Padmission\FilamentTourEditor\FilamentTourEditorPlugin;
use Padmission\FilamentTourEditor\Models\Tour;
use Padmission\FilamentTourEditor\Resources\TourResource\Pages\ManageTours;
use Padmission\FilamentTourEditor\Resources\TourResource\Schemas\TourFormSchema;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;
    protected static ?string $slug = 'tours';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Schema $schema): Schema
    {
        return TourFormSchema::configure($schema);
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentTourEditorPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return FilamentTourEditorPlugin::get()->getNavigationLabel() ?? parent::getNavigationLabel();
    }

    public static function canAccess(): bool
    {
        return static::canManageTours();
    }

    public static function canViewAny(): bool
    {
        return static::canManageTours();
    }

    public static function canCreate(): bool
    {
        return static::canManageTours();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageTours();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageTours();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('m/d/Y')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => static::canManageTours())
                    ->slideOver()
                    ->modalDescription('Edit the tour here, then use the Tour Builder on the page itself if you need to pick or change target elements.')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->mutateDataUsing(fn (array $data): array => static::normalizeFormData($data)),
                ActionGroup::make([
                    ReplicateAction::make()
                        ->label('Clone')
                        ->visible(fn (): bool => static::canManageTours())
                        ->slideOver()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->excludeAttributes(['id', 'created_at', 'updated_at', 'sort_order'])
                        ->schema(TourFormSchema::components())
                        ->mutateRecordDataUsing(fn (array $data): array => static::prefillCloneData($data))
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
                        ->visible(fn (): bool => static::canManageTours()),
                ]),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->visible(fn (): bool => static::canManageTours()),
            ])
            ->recordAction('edit');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTours::route('/'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        if (blank(data_get($data, 'json_config.id')) && filled($data['name'] ?? null)) {
            data_set($data, 'json_config.id', Str::slug($data['name']));
        }

        if (blank(data_get($data, 'json_config.config'))) {
            data_set($data, 'json_config.config', [
                'nextButtonLabel' => 'Next',
                'previousButtonLabel' => 'Previous',
                'doneButtonLabel' => 'Done',
            ]);
        }

        return $data;
    }

    protected static function getClonedTourName(string $name): string
    {
        return str($name)->finish(' Copy')->value();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function prefillCloneData(array $data): array
    {
        $data['name'] = static::getClonedTourName($data['name'] ?? 'Tour');
        $data['is_active'] = false;

        return static::normalizeFormData($data);
    }

    protected static function canManageTours(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return FilamentTourEditorPlugin::get()->resolveCanAccessBuilder();
    }
}
