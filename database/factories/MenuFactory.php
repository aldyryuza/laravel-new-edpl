<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->slug(2);

        return [
            'parent_id' => null,
            'code' => $code,
            'title' => fake()->words(2, true),
            'icon' => 'bx bx-circle',
            'route' => null,
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    /**
     * Menu yang mengarah ke route tertentu.
     */
    public function route(string $route): static
    {
        return $this->state(fn () => ['route' => $route]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
