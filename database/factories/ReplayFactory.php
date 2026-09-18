<?php

namespace Database\Factories;

use App\Enums\Visibility;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Replay>
 */
class ReplayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'visibility' => Visibility::Unlisted,
            'content' => [
                'players' => [
                    ['name' => $this->faker->userName()],
                    ['name' => $this->faker->userName()],
                ],
                'actions' => [],
                'totalTurns' => $this->faker->numberBetween(1, 20),
                'winner' => null,
                'coinFlipWinner' => null,
            ],
        ];
    }

    public function visibility(Visibility $visibility): static
    {
        return $this->state(['visibility' => $visibility]);
    }
}
