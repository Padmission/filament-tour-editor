<?php

namespace Padmission\FilamentTourEditor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Padmission\FilamentTourEditor\Models\Tour;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'name' => $name,
            'description' => fake()->paragraph(),
            'route' => '/dashboard',
            'json_config' => [
                'id' => Str::slug($name),
                'steps' => [
                    [
                        'title' => 'Welcome',
                        'description' => 'Welcome to this page.',
                        'element' => '.fi-header',
                    ],
                ],
                'config' => [
                    'nextButtonLabel' => 'Next',
                    'previousButtonLabel' => 'Previous',
                    'doneButtonLabel' => 'Done',
                ],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
