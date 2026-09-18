<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Jobs\ResolveReplayCards;
use App\Models\Card;
use App\Models\Replay;
use App\Services\BattleLogParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReplayController extends Controller
{
    /**
     * Upper bound on a pasted battle log (characters). Real logs are ~20-60k.
     */
    public const MAX_LOG_LENGTH = 250_000;

    /**
     * Public hub: a paginated list of publicly listed replays.
     */
    public function index(): Response
    {
        $replays = Replay::listing()
            ->where('visibility', Visibility::Public)
            ->latest()
            ->paginate(12)
            ->through(fn (Replay $replay): array => $replay->summary());

        return Inertia::render('replays/Index', [
            'replays' => $replays,
        ]);
    }

    /**
     * The authenticated user's own replays, across every visibility.
     */
    public function mine(Request $request): Response
    {
        $replays = $request
            ->user()
            ->replays()
            ->listing()
            ->latest()
            ->paginate(12)
            ->through(fn (Replay $replay): array => $replay->summary());

        return Inertia::render('replays/MyReplays', [
            'replays' => $replays,
        ]);
    }

    /**
     * Parse a raw battle log, store the result, and redirect to the replay.
     */
    public function store(Request $request, BattleLogParser $parser): RedirectResponse
    {
        $validated = $request->validate([
            'log' => ['required', 'string', 'max:'.self::MAX_LOG_LENGTH],
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
        ]);

        $game = $parser->parse($validated['log']);

        if (! $this->looksLikeBattleLog($game)) {
            throw ValidationException::withMessages([
                'log' => __("This doesn't look like a Pokémon TCG Live battle log."),
            ]);
        }

        $replay = new Replay;
        $replay->user_id = $request->user()?->id;
        $replay->raw_log = $validated['log'];
        $replay->content = $game;
        $replay->visibility = $this->resolveVisibility($request, $validated['visibility'] ?? null);
        $replay->save();

        ResolveReplayCards::dispatch($replay->cardNames());

        return to_route('replays.show', $replay);
    }

    /**
     * Show a single replay (private ones only to their owner).
     */
    public function show(Request $request, Replay $replay): Response
    {
        abort_unless($replay->isViewableBy($request->user()), 404);

        $summary = $replay->summary();

        return Inertia::render('replay/Show', [
            'replay' => $replay->only(['id', 'slug', 'visibility']),
            'game' => $replay->content,
            ...$this->cardsFor($replay),
            'canEdit' => $replay->isEditableBy($request->user()),
            'og' => [
                'title' => implode(' vs ', $summary['players']) ?: 'Replay '.$replay->slug,
                'description' => ($summary['winner'] ? "Winner: {$summary['winner']} · " : '')."{$summary['totalTurns']} turns",
            ],
        ]);
    }

    /**
     * Update a replay's visibility (owner only).
     */
    public function update(Request $request, Replay $replay): RedirectResponse
    {
        Gate::authorize('update', $replay);

        $validated = $request->validate([
            'visibility' => ['required', Rule::enum(Visibility::class)],
        ]);

        $replay->visibility = Visibility::from($validated['visibility']);
        $replay->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visibility updated.')]);

        return back();
    }

    /**
     * Delete a replay (owner only).
     */
    public function destroy(Replay $replay): RedirectResponse
    {
        Gate::authorize('delete', $replay);

        $replay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Replay deleted.')]);

        return to_route('replays.mine');
    }

    /**
     * Cheap structural sanity check: two players, at least one turn, and the
     * parser recognised most of the lines.
     *
     * @param  array{players: array{0: array{name: string}, 1: array{name: string}}, actions: list<array<string, mixed>>, totalTurns: int}  $game
     */
    private function looksLikeBattleLog(array $game): bool
    {
        $actions = $game['actions'];

        if ($actions === [] || $game['totalTurns'] < 1 || $game['players'][1]['name'] === '') {
            return false;
        }

        $unknown = count(array_filter($actions, fn (array $action): bool => $action['type'] === 'unknown'));

        return $unknown / count($actions) < 0.5;
    }

    /**
     * Guests are always unlisted; authenticated users may pick any visibility.
     */
    private function resolveVisibility(Request $request, ?string $requested): Visibility
    {
        if ($request->user() === null) {
            return Visibility::Unlisted;
        }

        return Visibility::tryFrom($requested ?? '') ?? Visibility::Unlisted;
    }

    /**
     * Card props for the viewer: raw-name => tcgdex base image URL, raw-name
     * => category, and whether any card is still waiting on the resolver job.
     *
     * @return array{cardImages: array<string, string>, cardCategories: array<string, string>, cardHp: array<string, int>, cardsResolving: bool}
     */
    private function cardsFor(Replay $replay): array
    {
        $names = $replay->cardNames();
        $props = ['cardImages' => [], 'cardCategories' => [], 'cardHp' => [], 'cardsResolving' => false];

        if ($names === []) {
            return $props;
        }

        $cardsByKey = Card::whereIn('log_name', array_map(Card::normalizeName(...), $names))
            ->get()
            ->keyBy('log_name');

        foreach ($names as $name) {
            $card = $cardsByKey->get(Card::normalizeName($name));

            if ($card === null) {
                $props['cardsResolving'] = true;

                continue;
            }

            if ($card->image !== null) {
                $props['cardImages'][$name] = $card->image;
            }

            if ($card->category !== null) {
                $props['cardCategories'][$name] = $card->category;
            }

            if ($card->hp !== null) {
                $props['cardHp'][$name] = $card->hp;
            }
        }

        return $props;
    }
}
