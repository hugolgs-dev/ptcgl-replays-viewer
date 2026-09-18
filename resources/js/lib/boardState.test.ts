import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { GameAction, PlayerInfo } from '@/types/replay';
import { calculateBoardState } from './boardState.ts';

const players: [PlayerInfo, PlayerInfo] = [{ name: 'Alice' }, { name: 'Bob' }];

let nextId = 0;
const act = (
    partial: Partial<GameAction> & Pick<GameAction, 'player' | 'type'>,
): GameAction => ({
    id: nextId++,
    turn: 1,
    phase: 'game',
    description: '',
    ...partial,
});

test('damage survives evolution and shows on benched targets', () => {
    const state = calculateBoardState(
        [
            act({
                player: 'Bob',
                type: 'play_to_active',
                cards: ['Charmander'],
            }),
            act({ player: 'Bob', type: 'play_to_bench', cards: ['Pikachu'] }),
            act({
                player: 'Alice',
                type: 'use_attack',
                target: 'Charmander',
                damage: 30,
            }),
            act({
                player: 'Bob',
                type: 'evolve',
                cards: ['Charmander', 'Charmeleon'],
            }),
            act({
                player: 'Alice',
                type: 'use_attack',
                target: 'Charmeleon',
                damage: 20,
            }),
            act({
                player: 'Alice',
                type: 'use_attack',
                target: 'Pikachu',
                damage: 10,
            }),
        ],
        players,
    );

    assert.equal(state.player2.active?.name, 'Charmeleon');
    assert.equal(state.player2.active?.damage, 50);
    assert.equal(state.player2.bench[0]?.damage, 10);
});

test('a benched knockout removes only that card; an active knockout keeps it flagged', () => {
    const state = calculateBoardState(
        [
            act({
                player: 'Bob',
                type: 'play_to_active',
                cards: ['Charmander'],
            }),
            act({ player: 'Bob', type: 'play_to_bench', cards: ['Dunsparce'] }),
            act({ player: 'Bob', type: 'play_to_bench', cards: ['Dunsparce'] }),
            act({ player: 'Bob', type: 'knockout', cards: ['Dunsparce'] }),
            act({ player: 'Bob', type: 'knockout', cards: ['Charmander'] }),
        ],
        players,
    );

    assert.equal(state.player2.bench.length, 1);
    assert.equal(state.player2.active?.isKnockedOut, true);
});

test('switching promotes the named bench card and benches the old active', () => {
    const state = calculateBoardState(
        [
            act({
                player: 'Alice',
                type: 'play_to_active',
                cards: ['Pikachu'],
            }),
            act({ player: 'Alice', type: 'play_to_bench', cards: ['Eevee'] }),
            act({ player: 'Alice', type: 'switch_active', cards: ['Eevee'] }),
        ],
        players,
    );

    assert.equal(state.player1.active?.name, 'Eevee');
    assert.deepEqual(
        state.player1.bench.map((p) => p.name),
        ['Pikachu'],
    );
});

test('detail lines bench search results, attach energy and apply damage counters', () => {
    const state = calculateBoardState(
        [
            act({
                player: 'Alice',
                type: 'play_to_active',
                cards: ['Yveltal'],
            }),
            act({
                player: 'Bob',
                type: 'play_to_active',
                cards: ['Dunsparce'],
            }),
            act({
                player: 'Alice',
                type: 'item',
                cards: ['Buddy-Buddy Poffin'],
                details: [
                    'Alice drew 2 cards and played them to the Bench.',
                    '• Snorunt, Snorunt',
                    'Alice shuffled their deck.',
                ],
            }),
            act({
                player: 'Alice',
                type: 'supporter',
                cards: ['Crispin'],
                details: [
                    'Alice attached Basic Darkness Energy to Yveltal in the Active Spot.',
                ],
            }),
            act({
                player: 'Alice',
                type: 'attach_energy',
                description:
                    'Alice attached Legacy Energy to Snorunt on the Bench.',
            }),
            act({
                player: 'Alice',
                type: 'unknown',
                description: 'Risky Ruins was activated.',
                details: ["Alice put 2 damage counters on Alice's Snorunt."],
            }),
            act({
                player: 'Alice',
                type: 'use_ability',
                details: [
                    "Alice moved 2 damage counters from Alice's Snorunt to Alice's Dunsparce.",
                ],
            }),
            act({
                player: 'Alice',
                type: 'use_attack',
                target: 'Dunsparce',
                damage: 120,
                details: ["Bob's Dunsparce took 50 damage."],
            }),
        ],
        players,
    );

    assert.deepEqual(
        state.player1.bench.map((p) => p.name),
        ['Snorunt', 'Snorunt'],
    );
    assert.deepEqual(state.player1.active?.energy, ['Basic Darkness Energy']);
    assert.deepEqual(state.player1.bench[0]?.energy, ['Legacy Energy']);
    assert.equal(state.player1.bench[0]?.damage, 0);
    assert.equal(state.player2.active?.damage, 20 + 120 + 50);
});

test('hand size and known cards follow draws, plays and reveals', () => {
    const state = calculateBoardState(
        [
            act({
                player: 'Alice',
                type: 'draw_opening_hand',
                description: 'Alice drew 7 cards for the opening hand.',
                details: ['7 drawn cards.', '• Froslass, Rare Candy, Yveltal'],
            }),
            act({
                player: 'Alice',
                type: 'draw_card',
                description: 'Alice drew Staryu.',
            }),
            act({
                player: 'Alice',
                type: 'play_to_active',
                cards: ['Yveltal'],
                description: 'Alice played Yveltal to the Active Spot.',
            }),
            act({
                player: 'Alice',
                type: 'item',
                cards: ['Ultra Ball'],
                description: 'Alice played Ultra Ball.',
                details: [
                    'Alice discarded 2 cards.',
                    '• Rare Candy, Staryu',
                    'Alice drew Meowth ex.',
                ],
            }),
            act({
                player: 'Alice',
                type: 'unknown',
                description: "Boss's Orders was added to Bob's hand.",
            }),
            act({
                player: 'Alice',
                type: 'supporter',
                cards: ['Lillie'],
                description: 'Alice played Lillie.',
                details: [
                    'Alice shuffled 5 cards into their deck.',
                    'Alice drew 3 cards.',
                ],
            }),
        ],
        players,
    );

    // 7 +1 -1 (Yveltal) -1 (Ultra Ball) -2 (discard) +1 (Meowth) -1 (Lillie) = 4,
    // then the whole hand is shuffled away (clamped at 0, known cleared), +3 drawn.
    assert.equal(state.player1.hand.size, 3);
    assert.deepEqual(state.player1.hand.known, []);
    assert.deepEqual(state.player2.hand, { size: 1, known: ["Boss's Orders"] });
});

test('tools attach and discard; conditions apply and clear on leaving the active spot', () => {
    const state = calculateBoardState(
        [
            act({
                player: 'Alice',
                type: 'play_to_active',
                cards: ['Gardevoir ex'],
            }),
            act({ player: 'Alice', type: 'play_to_bench', cards: ['Kirlia'] }),
            act({
                player: 'Alice',
                type: 'attach_tool',
                cards: ['Forest Seal Stone', 'Gardevoir ex'],
            }),
            act({
                player: 'Bob',
                type: 'status_condition',
                description: "Alice's Gardevoir ex is now Poisoned.",
            }),
            act({
                player: 'Bob',
                type: 'status_condition',
                description: "Alice's Gardevoir ex is now Asleep.",
            }),
            act({
                player: 'Alice',
                type: 'discard',
                description:
                    "Forest Seal Stone was discarded from Alice's Gardevoir ex.",
            }),
            act({ player: 'Alice', type: 'switch_active', cards: ['Kirlia'] }),
        ],
        players,
    );

    assert.equal(state.player1.active?.name, 'Kirlia');
    assert.deepEqual(state.player1.bench[0]?.conditions, []);
    assert.equal(state.player1.bench[0]?.tool, null);
});

test('conditions show while the Pokémon stays active', () => {
    const state = calculateBoardState(
        [
            act({
                player: 'Alice',
                type: 'play_to_active',
                cards: ['Pikachu'],
            }),
            act({
                player: 'Bob',
                type: 'status_condition',
                description: "Alice's Pikachu is now Burned.",
            }),
        ],
        players,
    );

    assert.deepEqual(state.player1.active?.conditions, ['Burned']);
});

test('uids are stable across recomputation of the same prefix', () => {
    const actions = [
        act({ player: 'Alice', type: 'play_to_bench', cards: ['A'] }),
        act({ player: 'Alice', type: 'play_to_bench', cards: ['B'] }),
    ];

    const first = calculateBoardState(actions, players).player1.bench.map(
        (p) => p.uid,
    );
    const second = calculateBoardState(actions, players).player1.bench.map(
        (p) => p.uid,
    );

    assert.deepEqual(first, second);
});
