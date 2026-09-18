import type {
    BoardState,
    GameAction,
    PlayerBoardState,
    PlayerInfo,
    PokemonState,
} from '@/types/replay';

// ── Regexes over log sentences (apostrophes are normalised by the parser) ──
const ENERGY_RE =
    /attached (.+? Energy) to (.+?)(?: on the Bench| in the Active Spot)?\.?$/i;
const DAMAGE_PUT_RE = /put (a|\d+) damage counters? on .+?'s (.+?)\.?$/i;
const DAMAGE_MOVE_RE =
    /moved (\d+) damage counters? from .+?'s (.+?) to .+?'s (.+?)\.?$/i;
const DAMAGE_TOOK_RE = /'s (.+?) took (\d+) damage/i;
const BENCH_ONE_RE = /drew (.+?) and played it to the Bench/i;

export function findPokemon(
    side: PlayerBoardState,
    name: string,
): PokemonState | null {
    const lower = name.trim().toLowerCase();

    if (side.active?.name.toLowerCase() === lower) {
        return side.active;
    }

    return side.bench.find((p) => p.name.toLowerCase() === lower) ?? null;
}

/**
 * PTCGL prints the wrong owner in "put a damage counter on X's Y" lines, so
 * look on the expected side first, then the other one.
 */
function findAnywhere(
    state: BoardState,
    name: string,
    first: PlayerBoardState,
): PokemonState | null {
    const other = first === state.player1 ? state.player2 : state.player1;

    return findPokemon(first, name) ?? findPokemon(other, name);
}

export function calculateBoardState(
    actions: GameAction[],
    players: [PlayerInfo, PlayerInfo],
): BoardState {
    const state: BoardState = {
        player1: {
            name: players[0].name || 'Player 1',
            active: null,
            bench: [],
            prizeCards: 6,
        },
        player2: {
            name: players[1].name || 'Player 2',
            active: null,
            bench: [],
            prizeCards: 6,
        },
        stadium: null,
    };

    let nextUid = 0;
    const makePokemon = (name: string): PokemonState => ({
        uid: nextUid++,
        name: name.trim(),
        damage: 0,
        isKnockedOut: false,
        energy: [],
    });

    // ponytail: same-named Pokémon (two Snorunt) resolve to the first one; the
    // log gives no way to tell them apart.
    const applyDetail = (
        playerState: PlayerBoardState,
        details: string[],
        index: number,
    ): void => {
        const detail = details[index];
        let m: RegExpMatchArray | null;

        if ((m = detail.match(ENERGY_RE))) {
            findAnywhere(state, m[2], playerState)?.energy.push(m[1]);
        } else if ((m = detail.match(DAMAGE_PUT_RE))) {
            const target = findAnywhere(state, m[2], playerState);

            if (target) {
                target.damage += (m[1] === 'a' ? 1 : Number(m[1])) * 10;
            }
        } else if ((m = detail.match(DAMAGE_MOVE_RE))) {
            const amount = Number(m[1]) * 10;
            const from = findAnywhere(state, m[2], playerState);
            const to = findAnywhere(state, m[3], playerState);

            if (from) {
                from.damage = Math.max(0, from.damage - amount);
            }

            if (to) {
                to.damage += amount;
            }
        } else if ((m = detail.match(DAMAGE_TOOK_RE))) {
            const target = findAnywhere(state, m[1], playerState);

            if (target) {
                target.damage += Number(m[2]);
            }
        } else if ((m = detail.match(BENCH_ONE_RE))) {
            playerState.bench.push(makePokemon(m[1]));
        } else if (detail.includes('played them to the Bench')) {
            // The names follow on the next "• A, B" line.
            const list = details[index + 1];

            if (list?.startsWith('•')) {
                for (const name of list.slice(1).split(',')) {
                    if (name.trim()) {
                        playerState.bench.push(makePokemon(name));
                    }
                }
            }
        }
    };

    for (const action of actions) {
        const isPlayer1 = action.player === players[0].name;
        const playerState = isPlayer1 ? state.player1 : state.player2;
        const opponentState = isPlayer1 ? state.player2 : state.player1;
        const card = action.cards?.[0];

        switch (action.type) {
            case 'play_to_active':
                if (card) {
                    playerState.active = makePokemon(card);
                }

                break;

            case 'play_to_bench':
                if (card) {
                    playerState.bench.push(makePokemon(card));
                }

                break;

            case 'switch_active': {
                if (!card) {
                    break;
                }

                const benchIndex = playerState.bench.findIndex(
                    (p) => p.name === card,
                );
                const incoming =
                    benchIndex === -1
                        ? makePokemon(card)
                        : playerState.bench.splice(benchIndex, 1)[0];

                if (playerState.active && !playerState.active.isKnockedOut) {
                    playerState.bench.push(playerState.active);
                }

                playerState.active = incoming;
                break;
            }

            case 'evolve': {
                const [from, to] = action.cards ?? [];
                const target =
                    from && to ? findPokemon(playerState, from) : null;

                if (target) {
                    target.name = to;
                }

                break;
            }

            case 'attach_energy': {
                const m = action.description.match(ENERGY_RE);

                if (m) {
                    findAnywhere(state, m[2], playerState)?.energy.push(m[1]);
                }

                break;
            }

            case 'use_attack':
                if (action.damage && action.target) {
                    const target = findPokemon(opponentState, action.target);

                    if (target) {
                        target.damage += action.damage;
                    }
                }

                break;

            case 'knockout': {
                if (!card) {
                    break;
                }

                if (playerState.active?.name === card) {
                    playerState.active.isKnockedOut = true;
                } else {
                    // Benched KO (snipe): the card leaves the board.
                    const index = playerState.bench.findIndex(
                        (p) => p.name === card,
                    );

                    if (index !== -1) {
                        playerState.bench.splice(index, 1);
                    }
                }

                break;
            }

            case 'take_prize':
                playerState.prizeCards = Math.max(
                    0,
                    playerState.prizeCards -
                        (action.description.includes('2 Prize') ? 2 : 1),
                );
                break;

            case 'stadium':
                if (card) {
                    state.stadium = card;
                }

                break;
        }

        // Side effects (energy from supporters, ability damage counters, search
        // results benched, bench damage) live in the indented detail lines.
        (action.details ?? []).forEach((_, index) =>
            applyDetail(playerState, action.details!, index),
        );
    }

    return state;
}
