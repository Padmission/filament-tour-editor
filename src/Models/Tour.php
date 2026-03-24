<?php

namespace Padmission\FilamentTourEditor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tour extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'json_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForRoute(Builder $query, string $route): Builder
    {
        return $query->where('route', $route);
    }

    public function getSteps(): array
    {
        return $this->json_config['steps'] ?? [];
    }

    /**
     * Convert to the array format expected by the filament-tour JS layer.
     *
     * @return array<string, mixed>
     */
    public function toFilamentTourArray(): array
    {
        $prefixId = config('filament-tour.tour_prefix_id', 'tour_');
        $id = $this->json_config['id'] ?? Str::slug($this->name);
        $config = $this->json_config['config'] ?? [];
        $steps = $this->getSteps();

        $mappedSteps = collect($steps)->map(function (array $step, int $index) use ($steps) {
            $data = [
                'uncloseable' => false,
                'popover' => [
                    'title' => view('filament-tour::tour.step.popover.title')
                        ->with('title', $step['title'] ?? '')
                        ->with('icon', $step['icon'] ?? null)
                        ->with('iconColor', $step['iconColor'] ?? null)
                        ->render(),
                    'description' => e($step['description'] ?? ''),
                ],
                'progress' => [
                    'current' => $index,
                    'total' => count($steps),
                ],
            ];

            if (! empty($step['element'])) {
                $data['element'] = $step['element'];
            }

            return $data;
        })->toArray();

        return [
            'routesIgnored' => false,
            'uncloseable' => false,
            'route' => $this->getResolvedRoutePath(),
            'id' => "{$prefixId}{$id}",
            'alwaysShow' => $this->json_config['alwaysShow'] ?? false,
            'colors' => [
                'light' => 'rgb(0,0,0)',
                'dark' => '#fff',
            ],
            'steps' => json_encode($mappedSteps),
            'nextButtonLabel' => $config['nextButtonLabel'] ?? 'Next',
            'previousButtonLabel' => $config['previousButtonLabel'] ?? 'Previous',
            'doneButtonLabel' => $config['doneButtonLabel'] ?? 'Done',
        ];
    }

    public function getResolvedRoutePath(): string
    {
        try {
            return parse_url(route($this->route))['path'] ?? '/';
        } catch (\Exception) {
            return $this->route;
        }
    }

    protected static function newFactory(): \Padmission\FilamentTourEditor\Database\Factories\TourFactory
    {
        return \Padmission\FilamentTourEditor\Database\Factories\TourFactory::new();
    }
}
