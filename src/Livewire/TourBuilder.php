<?php

namespace Padmission\FilamentTourEditor\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Padmission\FilamentTourEditor\Models\Tour;
use Padmission\FilamentTourEditor\Resources\TourResource\Schemas\TourFormSchema;

class TourBuilder extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public string $currentPath = '';
    public bool $showAdvancedTourFields = false;

    #[On('launch-tour-builder')]
    public function handleTourBuilderLaunch(): void
    {
        $this->mountAction('buildTour');
    }

    #[On('request-tour-reset')]
    public function handleTourResetRequest(): void
    {
        $this->dispatch('filament-tour-editor::reset-history');
    }

    public function onElementPicked(string $selector, string $itemKey): void
    {
        if (empty($this->mountedActions)) {
            return;
        }

        $mountedActionIndex = array_key_last($this->mountedActions);

        if ($mountedActionIndex === null) {
            return;
        }

        $normalizedItemKey = $this->normalizePickedItemKey($itemKey);
        data_set(
            $this->mountedActions,
            "{$mountedActionIndex}.data.json_config.steps.{$normalizedItemKey}.element",
            $selector,
        );
    }

    public function buildTourAction(): Action
    {
        return Action::make('buildTour')
            ->label('Build Tour')
            ->icon('heroicon-o-academic-cap')
            ->authorize(function (): bool {
                $tour = $this->resolveTourForCurrentPage();

                if ($tour) {
                    return Gate::allows('update', $tour);
                }

                return Gate::allows('create', Tour::class);
            })
            ->slideOver()
            ->modalDescription('Add steps, then choose targets from the page. Click to select a target, or hold Shift while clicking to interact with menus, nav items, and slide-overs first. Open Advanced only if you need to adjust the selector, icon, or button labels.')
            ->modalSubmitActionLabel('Save')
            ->modalWidth(Width::TwoExtraLarge)
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('previewTour', ['preview' => true])
                    ->close(false)
                    ->label('Preview')
                    ->icon('heroicon-o-play'),
            ])
            ->fillForm(fn (): array => $this->getCurrentTourFormData())
            ->schema(TourFormSchema::components(
                includeTourId: true,
                pickElementAction: function (array $arguments, ?SchemaComponent $schemaComponent = null): void {
                    $itemKey = data_get($arguments, 'item')
                        ?? $schemaComponent?->getParentRepeaterItem()?->getStatePath(isAbsolute: false);

                    if ($itemKey === null) {
                        return;
                    }

                    $this->dispatch('start-picking', itemKey: (string) $itemKey);
                },
                previewStepActionHandler: 'previewMountedStep',
            ))
            ->action(function (array $data, array $arguments, Action $action): void {
                $tour = $this->resolveTourForCurrentPage($data['tour_id'] ?? null);
                $validSteps = $this->extractValidSteps($data);

                if ($validSteps === null) {
                    Notification::make()
                        ->title('Add at least one step with a title')
                        ->danger()
                        ->send();

                    $this->halt();

                    return;
                }

                if ($arguments['preview'] ?? false) {
                    $this->dispatchPreviewTour($this->buildPreviewTour($data, $validSteps, $tour));
                    $action->halt();

                    return;
                }

                $tourData = [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'route' => $data['route'] ?? $this->currentPath ?: '/',
                    'panel' => \Filament\Facades\Filament::getCurrentPanel()?->getId(),
                    'is_active' => $data['is_active'] ?? false,
                    'json_config' => [
                        'id' => $tour?->json_config['id'] ?? Str::slug($data['name']),
                        'steps' => $validSteps,
                        'config' => data_get($data, 'json_config.config', [
                            'nextButtonLabel' => 'Next',
                            'previousButtonLabel' => 'Previous',
                            'doneButtonLabel' => 'Done',
                        ]),
                    ],
                ];

                if ($tour) {
                    $tour->update($tourData);
                } else {
                    Tour::create($tourData);
                }

                Notification::make()
                    ->title('Tour saved!')
                    ->success()
                    ->send();
            });
    }

    protected function previewStepAction(string|int|null $itemKey): void
    {
        if ($itemKey === null) {
            return;
        }

        $data = $this->getMountedActionData();

        if ($data === null) {
            return;
        }

        $step = data_get($data, "json_config.steps.{$itemKey}");

        if (! is_array($step)) {
            return;
        }

        if (blank($step['title'] ?? null)) {
            Notification::make()
                ->title('Add a step title before previewing')
                ->warning()
                ->send();

            return;
        }

        $previewData = $data;
        $previewData['name'] = filled($previewData['name'] ?? null) ? $previewData['name'] : $step['title'];
        $validStepEntries = $this->extractValidStepEntries($data);
        $previewStartIndex = collect($validStepEntries)
            ->search(fn (array $entry): bool => (string) $entry['key'] === (string) $itemKey);

        if ($previewStartIndex === false) {
            Notification::make()
                ->title('Add a step title before previewing')
                ->warning()
                ->send();

            return;
        }

        $validSteps = array_map(
            fn (array $entry): array => $entry['step'],
            $validStepEntries,
        );

        $tour = $this->resolveTourForCurrentPage($previewData['tour_id'] ?? null);

        $this->dispatchPreviewTour($this->buildPreviewTour(
            $previewData,
            $validSteps,
            $tour,
            previewStartIndex: $previewStartIndex,
        ));
    }

    public function previewMountedStep(array|string|int|null $itemKey): void
    {
        if (is_array($itemKey)) {
            $itemKey = data_get($itemKey, 'item');
        }

        $this->previewStepAction($itemKey);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $validSteps
     * @return array<string, mixed>
     */
    protected function buildPreviewTour(
        array $data,
        array $validSteps,
        ?Tour $tour = null,
        ?int $previewStartIndex = null,
    ): array {
        $jsonConfig = [
            'id' => 'preview-' . Str::lower(Str::random(8)),
            'steps' => $validSteps,
            'config' => data_get($data, 'json_config.config', [
                'nextButtonLabel' => 'Next',
                'previousButtonLabel' => 'Previous',
                'doneButtonLabel' => 'Done',
            ]),
            'alwaysShow' => true,
        ];

        if ($tour?->json_config['id'] ?? null) {
            $jsonConfig['original_id'] = $tour->json_config['id'];
        }

        $previewTour = new Tour([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'route' => $data['route'] ?? $this->currentPath ?: '/',
            'panel' => \Filament\Facades\Filament::getCurrentPanel()?->getId(),
            'is_active' => false,
            'json_config' => $jsonConfig,
        ]);

        $previewTourArray = $previewTour->toFilamentTourArray();

        if ($previewStartIndex !== null) {
            $previewTourArray['previewStartIndex'] = $previewStartIndex;
        }

        return $previewTourArray;
    }

    public function canAccess(): bool
    {
        return Gate::allows('create', Tour::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCurrentTourFormData(): array
    {
        $this->showAdvancedTourFields = false;

        $defaultData = [
            'route' => $this->currentPath ?: '/',
            'is_active' => false,
            'json_config' => [
                'steps' => [],
                'config' => [
                    'nextButtonLabel' => 'Next',
                    'previousButtonLabel' => 'Previous',
                    'doneButtonLabel' => 'Done',
                ],
            ],
        ];

        $tour = $this->resolveTourForCurrentPage();

        if (! $tour) {
            return $defaultData;
        }

        return array_replace_recursive($defaultData, [
            'tour_id' => $tour->getKey(),
            'name' => $tour->name,
            'description' => $tour->description,
            'route' => $tour->route,
            'is_active' => $tour->is_active,
            'json_config' => [
                'id' => $tour->json_config['id'] ?? null,
                'steps' => $tour->getSteps(),
                'config' => $tour->json_config['config'] ?? [],
            ],
        ]);
    }

    protected function resolveTourForCurrentPage(?int $tourId = null): ?Tour
    {
        if ($tourId) {
            return Tour::query()->find($tourId);
        }

        if (blank($this->currentPath)) {
            return null;
        }

        return Tour::query()
            ->forRoute($this->currentPath)
            ->orderBy('sort_order')
            ->first();
    }

    public function render(): View
    {
        return view('filament-tour-editor::livewire.tour-builder');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getMountedActionData(): ?array
    {
        if (empty($this->mountedActions)) {
            return null;
        }

        $mountedActionIndex = array_key_last($this->mountedActions);

        if ($mountedActionIndex === null) {
            return null;
        }

        $data = data_get($this->mountedActions, "{$mountedActionIndex}.data");

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $tour
     */
    protected function dispatchPreviewTour(array $tour): void
    {
        $this->dispatch('filament-tour-editor::start-preview');
        $this->dispatch('filament-tour-editor::preview-tour', tour: $tour);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>|null
     */
    protected function extractValidSteps(array $data): ?array
    {
        $validSteps = array_map(
            fn (array $entry): array => $entry['step'],
            $this->extractValidStepEntries($data),
        );

        return empty($validSteps) ? null : $validSteps;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{key: string|int, step: array<string, mixed>}>
     */
    protected function extractValidStepEntries(array $data): array
    {
        return collect(data_get($data, 'json_config.steps', []))
            ->filter(fn (mixed $step): bool => is_array($step) && ! empty($step['title']))
            ->map(fn (array $step, string|int $key): array => [
                'key' => $key,
                'step' => $step,
            ])
            ->values()
            ->toArray();
    }

    protected function normalizePickedItemKey(string $itemKey): string
    {
        return (string) Str::of($itemKey)->afterLast('.');
    }
}
