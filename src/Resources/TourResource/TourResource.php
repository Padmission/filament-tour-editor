<?php

namespace Padmission\FilamentTourEditor\Resources\TourResource;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Padmission\FilamentTourEditor\FilamentTourEditorPlugin;
use Padmission\FilamentTourEditor\Models\Tour;
use Padmission\FilamentTourEditor\Resources\TourResource\Pages\ManageTours;
use Padmission\FilamentTourEditor\Resources\TourResource\Schemas\TourFormSchema;
use Padmission\FilamentTourEditor\Resources\TourResource\Schemas\TourTableSchema;

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
        return TourTableSchema::configure($table);
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
    public static function prefillCloneData(array $data): array
    {
        $data['name'] = static::getClonedTourName($data['name'] ?? 'Tour');
        $data['is_active'] = false;

        return static::normalizeFormData($data);
    }

    public static function canManageTours(): bool
    {
        return Gate::allows('create', Tour::class);
    }
}
