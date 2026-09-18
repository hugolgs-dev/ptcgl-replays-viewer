<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Replay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReplayHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hub_lists_only_public_replays(): void
    {
        $public = Replay::factory()->visibility(Visibility::Public)->create();
        Replay::factory()->visibility(Visibility::Unlisted)->create();
        Replay::factory()->visibility(Visibility::Private)->create();

        $this->get(route('replays.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('replays/Index')
                ->has('replays.data', 1)
                ->where('replays.data.0.slug', $public->slug)
            );
    }

    public function test_the_hub_is_reachable_by_guests(): void
    {
        $this->get(route('replays.index'))->assertOk();
    }
}
