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
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

class TourFormSchema
{
    public static function configure(
        Schema $schema,
        bool $includeTourId = false,
        ?Closure $pickElementAction = null,
        ?string $previewStepActionHandler = null,
    ): Schema {
        return $schema
            ->schema(static::components($includeTourId, $pickElementAction, $previewStepActionHandler))
            ->columns(1);
    }

    /**
     * @return array<int, Component>
     */
    public static function components(
        bool $includeTourId = false,
        ?Closure $pickElementAction = null,
        ?string $previewStepActionHandler = null,
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
                ->label('Active')
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
                            ->color('primary')
                            ->outlined(fn (Get $get): bool => filled($get('element')))
                            ->icon('heroicon-o-cursor-arrow-rays')
                            ->extraAttributes(fn (Component $component): array => [
                                'data-tour-pick-target' => 'true',
                                'data-tour-pick-index' => (string) $component->getParentRepeaterItemIndex(),
                                'data-tour-pick-key' => (string) $component->getParentRepeaterItem()?->getStatePath(isAbsolute: false),
                            ])
                            ->size(Size::Small)
                            ->action($pickElementAction),
                        Action::make('targetPickedMessage')
                            ->label('Target selected')
                            ->link()
                            ->color('success')
                            ->disabled()
                            ->extraAttributes(fn (Component $component): array => [
                                'x-cloak' => true,
                                'x-show' => "recentlyPickedItemIndex === {$component->getParentRepeaterItemIndex()}",
                                'x-transition:enter' => 'transition-opacity duration-300',
                                'x-transition:enter-start' => 'opacity-0',
                                'x-transition:enter-end' => 'opacity-100',
                                'x-transition:leave' => 'transition-opacity duration-700',
                                'x-transition:leave-start' => 'opacity-100',
                                'x-transition:leave-end' => 'opacity-0',
                                'class' => 'pointer-events-none',
                            ]),
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
                    Select::make('popoverWidth')
                        ->label('Popover Width')
                        ->hidden(fn (LivewireComponent $livewire): bool => ! $livewire->showAdvancedTourFields)
                        ->dehydratedWhenHidden()
                        ->native(false)
                        ->options(static::getWidthOptions())
                        ->placeholder('Default width'),
                ])
                ->compact()
                ->reorderable()
                ->extraItemActions($previewStepActionHandler ? [
                    Action::make('previewStep')
                        ->label('Preview step')
                        ->icon(Heroicon::OutlinedPlay)
                        ->callParent($previewStepActionHandler),
                ] : [])
                ->itemLabel(fn (array $state): string => $state['title'] ?? 'New Step')
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
                ->helperText('The URL path where this tour should appear (auto-set from current page)')
                ->hidden(fn (LivewireComponent $livewire): bool => ! $livewire->showAdvancedTourFields)
                ->dehydratedWhenHidden()
                ->maxLength(255)
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (blank($value)) {
                            return;
                        }

                        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());
                        $trimmedPath = ltrim($value, '/');

                        // Check as named route
                        if (! str_contains($value, '/') && \Illuminate\Support\Facades\Route::has($value)) {
                            return;
                        }

                        // Check as URL path (with {record} placeholders matching route parameters)
                        if ($routes->contains(fn ($route) => $route->uri() === $trimmedPath)) {
                            return;
                        }

                        $fail('This route does not match any registered application route.');
                    },
                ])
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

    /**
     * @return array<string, string>
     */
    protected static function getWidthOptions(): array
    {
        return collect(Width::cases())
            ->mapWithKeys(fn (Width $width): array => [
                $width->value => Str::of($width->name)->headline()->value(),
            ])
            ->all();
    }
}
