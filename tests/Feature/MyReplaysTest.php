<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MyReplaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('replays.mine'))->assertRedirect(route('login'));
    }

    public function test_it_lists_only_the_authenticated_users_replays_across_all_visibilities(): void
    {
        $user = $this->actingAsUser();

        Replay::factory()
            ->for($user)
            ->visibility(Visibility::Public)
            ->create();
        Replay::factory()
            ->for($user)
            ->visibility(Visibility::Private)
            ->create();
        Replay::factory()->visibility(Visibility::Public)->create(); // someone else's

        $this
            ->get(route('replays.mine'))
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page->component('replays/MyReplays')->has('replays.data', 2),
            );
    }

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }
}
