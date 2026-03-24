<?php

namespace Padmission\FilamentTourEditor\Resources\TourResource\Schemas;

use App\Forms\Components\ColorPalette;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

class TourFormSchema
{
    public static function configure(
        Schema $schema,
        bool $includeTourId = false,
        ?Closure $pickElementAction = null,
    ): Schema {
        return $schema
            ->schema(static::components($includeTourId, $pickElementAction))
            ->columns(1);
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(
        bool $includeTourId = false,
        ?Closure $pickElementAction = null,
    ): array {
        return [
            Hidden::make('tour_id')
                ->visible($includeTourId),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->rows(2),
            Toggle::make('is_active')
                ->default(false),
            Repeater::make('json_config.steps')
                ->label('Steps')
                ->hintAction(
                    Action::make('showAdvanced')
                        ->label(fn (LivewireComponent $livewire): string => $livewire->showAdvancedTourFields ? 'Hide advanced' : 'Advanced')
                        ->action(function (LivewireComponent $livewire): void {
                            $livewire->showAdvancedTourFields = ! $livewire->showAdvancedTourFields;
                        })
                )
                ->schema([
                    Actions::make([
                        Action::make('pickElement')
                            ->label(fn (Get $get): string => filled($get('element')) ? 'Change target' : 'Pick target')
                            ->icon('heroicon-o-cursor-arrow-rays')
                            ->size(Size::Small)
                            ->action($pickElementAction),
                    ])
                        ->visible($pickElementAction !== null),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->required()
                        ->rows(2),
                    TextInput::make('element')
                        ->label('CSS Selector')
                        ->helperText('Leave empty for a centered modal step')
                        ->hidden(fn (LivewireComponent $livewire): bool => ! $livewire->showAdvancedTourFields)
                        ->dehydratedWhenHidden()
                        ->maxLength(255),
                    Grid::make(2)
                        ->hidden(fn (LivewireComponent $livewire): bool => ! $livewire->showAdvancedTourFields)
                        ->schema([
                            Select::make('icon')
                                ->label('Icon')
                                ->searchable()
                                ->options(static::getHeroiconOptions())
                                ->dehydratedWhenHidden()
                                ->placeholder('Select an icon'),
                            ColorPalette::make('iconColor')
                                ->label('Icon Color')
                                ->dehydratedWhenHidden()
                                ->options([
                                    'gray',
                                    'primary',
                                    'success',
                                    'warning',
                                    'danger',
                                ]),
                        ]),
                ])
                ->compact()
                ->reorderable()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'New Step')
                ->defaultItems(0),
            Grid::make(3)
                ->hidden(fn (LivewireComponent $livewire): bool => ! $livewire->showAdvancedTourFields)
                ->schema([
                    TextInput::make('json_config.config.nextButtonLabel')
                        ->label('Next Button')
                        ->dehydratedWhenHidden()
                        ->default('Next'),
                    TextInput::make('json_config.config.previousButtonLabel')
                        ->label('Previous Button')
                        ->dehydratedWhenHidden()
                        ->default('Previous'),
                    TextInput::make('json_config.config.doneButtonLabel')
                        ->label('Done Button')
                        ->dehydratedWhenHidden()
                        ->default('Done'),
                ])
                ->columnSpanFull(),
            TextInput::make('route')
                ->required()
                ->helperText('The named route or URL path where this tour should appear (e.g., filament.app.pages.dashboard)')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function getHeroiconOptions(): array
    {
        return collect(Heroicon::cases())
            ->filter(fn (Heroicon $icon): bool => str_starts_with($icon->value, 'o-'))
            ->mapWithKeys(fn (Heroicon $icon): array => [
                $icon->getIconForSize(IconSize::Medium) => Str::of($icon->name)
                    ->after('Outlined')
                    ->headline()
                    ->value(),
            ])
            ->all();
    }
}
