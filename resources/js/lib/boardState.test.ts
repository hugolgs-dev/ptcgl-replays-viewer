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
