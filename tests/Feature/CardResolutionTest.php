<?php

namespace Tests\Feature;

use App\Contracts\CardLookup;
use App\Jobs\ResolveReplayCards;
use App\Models\Card;
use App\Models\Replay;
use App\Services\CardResolver;
use App\Services\Tcgdex\TcgdexCardLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CardResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_caches_both_hits_and_misses(): void
    {
        $this->swap(CardLookup::class, new FakeCardLookup([
            'Pikachu' => ['tcgdex_id' => 'base1-58', 'name' => 'Pikachu', 'image' => 'https://assets.tcgdex.net/en/base/base1/58'],
        ]));

        app(CardResolver::class)->resolveMissing(['Pikachu', 'Unknown Card']);

        $hit = Card::where('log_name', 'pikachu')->sole();
        $this->assertSame('https://assets.tcgdex.net/en/base/base1/58', $hit->image);
        $this->assertNotNull($hit->resolved_at);

        $miss = Card::where('log_name', 'unknown card')->sole();
        $this->assertNull($miss->image);
        $this->assertNotNull($miss->resolved_at);
    }

    public function test_resolver_does_not_requery_already_cached_names(): void
    {
        Card::create(['log_name' => 'pikachu', 'image' => 'cached', 'resolved_at' => now()]);

        $fake = new FakeCardLookup([]);
        $this->swap(CardLookup::class, $fake);

        app(CardResolver::class)->resolveMissing(['Pikachu']);

        $this->assertSame([], $fake->queried);
        $this->assertSame('cached', Card::where('log_name', 'pikachu')->sole()->image);
    }

    public function test_storing_a_replay_dispatches_card_resolution_with_its_card_names(): void
    {
        Queue::fake();

        $this->post(route('replays.store'), [
            'log' => "Setup\nAsh played Pikachu to the Active Spot.\nTurn # 1 - Gary's Turn\nGary drew a card.",
        ]);

        Queue::assertPushed(
            ResolveReplayCards::class,
            fn (ResolveReplayCards $job): bool => in_array('Pikachu', $job->cardNames, true),
        );
    }

    public function test_tcgdex_lookup_maps_the_first_result_from_the_api(): void
    {
        Http::fake([
            'api.tcgdex.net/*' => Http::response([
                ['id' => 'base1-58', 'localId' => '58', 'name' => 'Pikachu', 'image' => 'https://assets.tcgdex.net/en/base/base1/58'],
                ['id' => 'base2-87', 'localId' => '87', 'name' => 'Pikachu', 'image' => 'https://assets.tcgdex.net/en/base/base2/87'],
            ]),
        ]);

        $result = (new TcgdexCardLookup)->find('Pikachu');

        $this->assertSame('base1-58', $result['tcgdex_id']);
        $this->assertSame('https://assets.tcgdex.net/en/base/base1/58', $result['image']);
    }

    public function test_tcgdex_lookup_returns_null_when_the_api_has_no_match(): void
    {
        Http::fake(['api.tcgdex.net/*' => Http::response([])]);

        $this->assertNull((new TcgdexCardLookup)->find('Nonexistent'));
    }

    public function test_tcgdex_lookup_prefers_exact_matches_and_reads_the_category(): void
    {
        Http::fake([
            'api.tcgdex.net/v2/en/cards/sv1-1' => Http::response(['id' => 'sv1-1', 'name' => 'Rare Candy', 'category' => 'Trainer', 'trainerType' => 'Item', 'hp' => null]),
            'api.tcgdex.net/v2/en/cards?name=eq*' => Http::response([['id' => 'sv1-1', 'name' => 'Rare Candy', 'image' => 'img']]),
            'api.tcgdex.net/*' => Http::response([['id' => 'wrong', 'name' => 'Rare Candy Jar']]),
        ]);

        $result = (new TcgdexCardLookup)->find('Rare Candy');

        $this->assertSame('sv1-1', $result['tcgdex_id']);
        $this->assertSame('Item', $result['category']);
        $this->assertNull($result['hp']);
    }

    public function test_tcgdex_lookup_throws_on_transport_errors_instead_of_returning_a_miss(): void
    {
        Http::fake(['api.tcgdex.net/*' => Http::response(null, 503)]);

        $this->expectException(RequestException::class);

        (new TcgdexCardLookup)->find('Pikachu');
    }

    public function test_seeding_standard_cards_keeps_the_newest_print_of_each_name(): void
    {
        Http::fake([
            'api.tcgdex.net/v2/en/cards?*' => Http::response([
                ['id' => 'new-1', 'name' => 'Pikachu', 'image' => 'new-img'],
                ['id' => 'old-1', 'name' => 'Pikachu', 'image' => 'old-img'],
                ['id' => 'old-2', 'name' => 'No Art'],
            ]),
            'api.tcgdex.net/v2/en/sets/old' => Http::response(['id' => 'old', 'releaseDate' => '2023-01-01']),
            'api.tcgdex.net/v2/en/sets/new' => Http::response(['id' => 'new', 'releaseDate' => '2025-01-01']),
            'api.tcgdex.net/v2/en/cards/new-1' => Http::response(['id' => 'new-1', 'name' => 'Pikachu', 'hp' => 70, 'category' => 'Pokemon']),
            'api.tcgdex.net/v2/en/cards/old-1' => Http::response(['id' => 'old-1', 'name' => 'Pikachu', 'hp' => 60, 'category' => 'Pokemon']),
        ]);

        $this->artisan('cards:seed-standard')->assertSuccessful();

        $pikachu = Card::where('log_name', 'pikachu')->sole();
        $this->assertSame('new-img', $pikachu->image);
        $this->assertSame(70, $pikachu->hp);
        $this->assertSame('Pokemon', $pikachu->category);
        $this->assertDatabaseMissing('cards', ['log_name' => 'no art']);
    }

    public function test_show_reports_whether_cards_are_still_resolving(): void
    {
        $replay = Replay::factory()->create([
            'content' => ['actions' => [['type' => 'play_to_active', 'cards' => ['Pikachu']]]],
        ]);

        $this->get(route('replays.show', $replay))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('cardsResolving', true));

        Card::create(['log_name' => 'pikachu', 'resolved_at' => now()]);

        $this->get(route('replays.show', $replay))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('cardsResolving', false));
    }

    public function test_show_passes_a_name_to_image_map_for_resolved_cards(): void
    {
        Card::create([
            'log_name' => 'pikachu',
            'name' => 'Pikachu',
            'image' => 'https://assets.tcgdex.net/en/base/base1/58',
            'category' => 'Pokemon',
            'hp' => 60,
            'resolved_at' => now(),
        ]);

        $replay = Replay::factory()->create([
            'content' => [
                'actions' => [
                    ['type' => 'play_to_active', 'cards' => ['Pikachu']],
                    ['type' => 'play_to_bench', 'cards' => ['Mystery Mon']],
                ],
            ],
        ]);

        $this->get(route('replays.show', $replay))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('replay/Show')
                ->where('cardImages.Pikachu', 'https://assets.tcgdex.net/en/base/base1/58')
                ->where('cardCategories.Pikachu', 'Pokemon')
                ->where('cardHp.Pikachu', 60)
                ->missing('cardImages.Mystery Mon')
            );
    }
}

class FakeCardLookup implements CardLookup
{
    /** @var list<string> */
    public array $queried = [];

    /**
     * @param  array<string, array{tcgdex_id: string, name: string, image: ?string, category: ?string}>  $map
     */
    public function __construct(private array $map) {}

    public function find(string $name): ?array
    {
        $this->queried[] = $name;

        return $this->map[$name] ?? null;
    }
}
