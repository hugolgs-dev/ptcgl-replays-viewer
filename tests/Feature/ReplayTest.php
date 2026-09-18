<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Http\Controllers\ReplayController;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReplayTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_LOG = "Setup\nAsh drew 7 cards for the opening hand.\nGary drew 7 cards for the opening hand.\nTurn # 1 - Ash's Turn\nAsh drew a card.\nAsh ended their turn.";

    public function test_a_real_battle_log_passes_the_guardrail(): void
    {
        Queue::fake();

        $this->post(route('replays.store'), ['log' => file_get_contents(base_path('tests/Fixtures/froslass-vs-toucannon.txt'))])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(9, Replay::sole()->content['totalTurns']);
    }

    public function test_text_that_is_not_a_battle_log_is_rejected(): void
    {
        foreach (['hello world', "<script>alert(1)</script>\nSELECT * FROM users;", str_repeat("lorem ipsum dolor\n", 50)] as $junk) {
            $this->post(route('replays.store'), ['log' => $junk])->assertSessionHasErrors('log');
        }

        $this->assertDatabaseCount('replays', 0);
    }

    public function test_a_guest_can_submit_a_log_and_is_redirected_to_the_replay(): void
    {
        $response = $this->post(route('replays.store'), ['log' => self::SAMPLE_LOG]);

        $replay = Replay::sole();

        $response->assertRedirect(route('replays.show', $replay));
        $this->assertNull($replay->user_id);
        $this->assertSame(['Ash', 'Gary'], [
            $replay->content['players'][0]['name'],
            $replay->content['players'][1]['name'],
        ]);
    }

    public function test_an_authenticated_user_owns_the_replay_they_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('replays.store'), ['log' => self::SAMPLE_LOG]);

        $this->assertSame($user->id, Replay::sole()->user_id);
    }

    public function test_the_raw_log_is_stored_so_replays_can_be_reparsed(): void
    {
        $this->post(route('replays.store'), ['log' => self::SAMPLE_LOG]);

        $replay = Replay::sole();
        $this->assertSame(self::SAMPLE_LOG, $replay->raw_log);

        $replay->content = ['players' => [], 'actions' => [], 'totalTurns' => 0, 'winner' => null, 'coinFlipWinner' => null];
        $replay->save();

        $this->artisan('replays:reparse')->assertSuccessful();

        $this->assertSame('Ash', $replay->refresh()->content['players'][0]['name']);
    }

    public function test_oversized_logs_are_rejected(): void
    {
        $this->post(route('replays.store'), ['log' => str_repeat('a', ReplayController::MAX_LOG_LENGTH + 1)])
            ->assertSessionHasErrors('log');
    }

    public function test_submissions_are_rate_limited(): void
    {
        foreach (range(1, 10) as $i) {
            $this->post(route('replays.store'), ['log' => self::SAMPLE_LOG])->assertRedirect();
        }

        $this->post(route('replays.store'), ['log' => self::SAMPLE_LOG])->assertStatus(429);
    }

    public function test_an_owner_can_delete_their_replay(): void
    {
        $owner = User::factory()->create();
        $replay = Replay::factory()->for($owner)->create();

        $this->actingAs($owner)->delete(route('replays.destroy', $replay))->assertRedirect(route('replays.mine'));

        $this->assertDatabaseMissing('replays', ['id' => $replay->id]);
    }

    public function test_a_non_owner_cannot_delete_a_replay(): void
    {
        $replay = Replay::factory()->create();

        $this->actingAs(User::factory()->create())->delete(route('replays.destroy', $replay))->assertForbidden();
        $this->delete(route('replays.destroy', $replay))->assertForbidden();

        $this->assertDatabaseHas('replays', ['id' => $replay->id]);
    }

    public function test_old_guest_replays_are_pruned_but_owned_ones_are_kept(): void
    {
        $guestOld = Replay::factory()->create(['user_id' => null, 'created_at' => now()->subDays(Replay::GUEST_RETENTION_DAYS + 1)]);
        $guestNew = Replay::factory()->create(['user_id' => null]);
        $owned = Replay::factory()->create(['created_at' => now()->subYear()]);

        $this->artisan('model:prune', ['--model' => Replay::class])->assertSuccessful();

        $this->assertDatabaseMissing('replays', ['id' => $guestOld->id]);
        $this->assertDatabaseHas('replays', ['id' => $guestNew->id]);
        $this->assertDatabaseHas('replays', ['id' => $owned->id]);
    }

    public function test_storing_a_replay_requires_a_log(): void
    {
        $response = $this->post(route('replays.store'), ['log' => '']);

        $response->assertSessionHasErrors('log');
        $this->assertDatabaseCount('replays', 0);
    }

    public function test_a_replay_receives_a_seven_character_base62_slug_on_creation(): void
    {
        $replay = Replay::factory()->create();

        $this->assertSame(7, strlen($replay->slug));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{7}$/', $replay->slug);
    }

    public function test_replays_are_resolved_by_slug_not_id(): void
    {
        $replay = Replay::factory()->create();

        $this->assertSame($replay->slug, $replay->getRouteKey());
        $this->get("/replay/{$replay->slug}")->assertOk();
        $this->get("/replay/{$replay->id}")->assertNotFound();
    }

    public function test_guest_replays_are_forced_to_unlisted(): void
    {
        $this->post(route('replays.store'), ['log' => self::SAMPLE_LOG, 'visibility' => 'public']);

        $this->assertSame(Visibility::Unlisted, Replay::sole()->visibility);
    }

    public function test_an_authenticated_user_can_choose_a_visibility(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('replays.store'), [
            'log' => self::SAMPLE_LOG,
            'visibility' => 'private',
        ]);

        $this->assertSame(Visibility::Private, Replay::sole()->visibility);
    }

    public function test_private_replays_are_hidden_from_everyone_but_their_owner(): void
    {
        $owner = User::factory()->create();
        $replay = Replay::factory()->for($owner)->visibility(Visibility::Private)->create();

        $this->get(route('replays.show', $replay))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('replays.show', $replay))->assertNotFound();
        $this->actingAs($owner)->get(route('replays.show', $replay))->assertOk();
    }

    public function test_unlisted_replays_are_viewable_by_anyone_with_the_link(): void
    {
        $replay = Replay::factory()->visibility(Visibility::Unlisted)->create();

        $this->get(route('replays.show', $replay))->assertOk();
    }

    public function test_an_owner_can_change_their_replay_visibility(): void
    {
        $owner = User::factory()->create();
        $replay = Replay::factory()->for($owner)->visibility(Visibility::Unlisted)->create();

        $this->actingAs($owner)
            ->from(route('replays.show', $replay))
            ->patch(route('replays.update', $replay), ['visibility' => 'public'])
            ->assertRedirect(route('replays.show', $replay));

        $this->assertSame(Visibility::Public, $replay->refresh()->visibility);
    }

    public function test_a_non_owner_cannot_change_visibility(): void
    {
        $replay = Replay::factory()->visibility(Visibility::Unlisted)->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('replays.update', $replay), ['visibility' => 'public'])
            ->assertForbidden();

        $this->assertSame(Visibility::Unlisted, $replay->refresh()->visibility);
    }

    public function test_a_guest_cannot_change_visibility(): void
    {
        $replay = Replay::factory()->visibility(Visibility::Unlisted)->create();

        $this->patch(route('replays.update', $replay), ['visibility' => 'public'])
            ->assertForbidden();
    }

    public function test_show_exposes_can_edit_only_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $replay = Replay::factory()->for($owner)->visibility(Visibility::Public)->create();

        $this->get(route('replays.show', $replay))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canEdit', false));

        $this->actingAs($owner)->get(route('replays.show', $replay))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canEdit', true));
    }

    public function test_the_replay_page_renders_with_its_content(): void
    {
        $replay = Replay::factory()->create();

        $this->get(route('replays.show', $replay))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('replay/Show')
                ->where('replay.id', $replay->id)
                ->has('game')
                ->missing('replay.user_id')
            );
    }

    public function test_a_replay_can_be_created_and_persists_content_as_an_array(): void
    {
        $content = [
            'players' => [
                ['name' => 'Ash', 'prizeCards' => 6],
                ['name' => 'Gary', 'prizeCards' => 6],
            ],
            'actions' => [],
            'totalTurns' => 12,
            'winner' => 'Ash',
            'coinFlipWinner' => 'Gary',
        ];

        $replay = Replay::create([
            'user_id' => User::factory()->create()->id,
            'content' => $content,
        ]);

        $this->assertDatabaseHas('replays', ['id' => $replay->id]);

        $fresh = $replay->fresh();
        $this->assertIsArray($fresh->content);
        $this->assertSame($content, $fresh->content);
        $this->assertSame('Ash', $fresh->content['winner']);
    }

    public function test_a_replay_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $replay = Replay::factory()->for($user)->create();

        $this->assertTrue($replay->user->is($user));
    }

    public function test_a_user_has_many_replays(): void
    {
        $user = User::factory()->create();
        Replay::factory()->count(3)->for($user)->create();

        $this->assertCount(3, $user->replays);
    }

    public function test_deleting_a_user_cascades_to_their_replays(): void
    {
        $replay = Replay::factory()->create();

        $replay->user->delete();

        $this->assertDatabaseMissing('replays', ['id' => $replay->id]);
    }
}
