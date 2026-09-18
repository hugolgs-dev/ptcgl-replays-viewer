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
const DISCARDED_FROM_RE = /^(.+?) was discarded from .+?'s (.+?)\.?$/i;
// ponytail: condition wording guessed from PTCGL; confirm against a real log.
const CONDITION_ON_RE =
    /'s (.+?) is now (Poisoned|Burned|Asleep|Paralyzed|Confused)/i;
const CONDITION_OFF_RE =
    /'s (.+?) (?:is no longer (?:Poisoned|Burned|Asleep|Paralyzed|Confused)|woke up|recovered)/i;
// Hand bookkeeping.
const DREW_ONE_RE = /^\w+ drew a card\.$/i;
const DREW_MANY_RE = /^\w+ drew (\d+) cards( for the opening hand)?\.$/i;
const DREW_NAMED_RE = /^\w+ drew (?!a card\b|\d+ cards\b)(.+?)\.$/i;
const DRAWN_LIST_RE = /^(\d+) drawn cards\.$/i;
const ADDED_TO_HAND_RE = /^(.+?) was added to (\w+)'s hand\.$/i;
const SHUFFLED_IN_RE = /shuffled (\d+) cards into their deck/i;
const DISCARDED_MANY_RE = /discarded (\d+) cards\.$/i;
const PLAYED_FROM_HAND = new Set([
    'play_to_active',
    'play_to_bench',
    'item',
    'supporter',
    'stadium',
    'play_card',
    'attach_energy',
    'attach_tool',
    'evolve',
]);

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

const bulletNames = (line: string | undefined): string[] =>
    line?.startsWith('•')
        ? line
              .slice(1)
              .split(',')
              .map((n) => n.trim())
              .filter(Boolean)
        : [];

export function calculateBoardState(
    actions: GameAction[],
    players: [PlayerInfo, PlayerInfo],
): BoardState {
    const side = (name: string): PlayerBoardState => ({
        name,
        active: null,
        bench: [],
        prizeCards: 6,
        hand: { size: 0, known: [] },
    });
    const state: BoardState = {
        player1: side(players[0].name || 'Player 1'),
        player2: side(players[1].name || 'Player 2'),
        stadium: null,
    };

    let nextUid = 0;
    const makePokemon = (name: string): PokemonState => ({
        uid: nextUid++,
        name: name.trim(),
        damage: 0,
        isKnockedOut: false,
        energy: [],
        tool: null,
        conditions: [],
    });

    /**
     * PTCGL prints the wrong owner in "put a damage counter on X's Y" lines,
     * so look on the expected side first, then the other one.
     */
    const findAnywhere = (
        name: string,
        first: PlayerBoardState,
    ): PokemonState | null => {
        const other = first === state.player1 ? state.player2 : state.player1;

        return findPokemon(first, name) ?? findPokemon(other, name);
    };

    const sideOf = (playerName: string): PlayerBoardState =>
        playerName === state.player2.name ? state.player2 : state.player1;

    const hand = {
        add: (s: PlayerBoardState, count: number, names: string[] = []) => {
            s.hand.size += count;
            s.hand.known.push(...names.filter((n) => n !== 'A card'));
        },
        remove: (s: PlayerBoardState, count: number, names: string[] = []) => {
            s.hand.size = Math.max(0, s.hand.size - count);

            for (const name of names) {
                const i = s.hand.known.indexOf(name);

                if (i !== -1) {
                    s.hand.known.splice(i, 1);
                }
            }

            // Unknown cards leave first; never claim to know more than we hold.
            s.hand.known.splice(
                0,
                Math.max(0, s.hand.known.length - s.hand.size),
            );
        },
    };

    // ponytail: same-named Pokémon (two Snorunt) resolve to the first one; the
    // log gives no way to tell them apart.
    const applyLine = (
        playerState: PlayerBoardState,
        line: string,
        nextLine: string | undefined,
    ): void => {
        let m: RegExpMatchArray | null;

        if ((m = line.match(ENERGY_RE))) {
            findAnywhere(m[2], playerState)?.energy.push(m[1]);
        } else if ((m = line.match(DAMAGE_PUT_RE))) {
            const target = findAnywhere(m[2], playerState);

            if (target) {
                target.damage += (m[1] === 'a' ? 1 : Number(m[1])) * 10;
            }
        } else if ((m = line.match(DAMAGE_MOVE_RE))) {
            const amount = Number(m[1]) * 10;
            const from = findAnywhere(m[2], playerState);
            const to = findAnywhere(m[3], playerState);

            if (from) {
                from.damage = Math.max(0, from.damage - amount);
            }

            if (to) {
                to.damage += amount;
            }
        } else if ((m = line.match(DAMAGE_TOOK_RE))) {
            const target = findAnywhere(m[1], playerState);

            if (target) {
                target.damage += Number(m[2]);
            }
        } else if ((m = line.match(DISCARDED_FROM_RE))) {
            const target = findAnywhere(m[2], playerState);

            if (target?.tool === m[1]) {
                target.tool = null;
            } else if (target) {
                const i = target.energy.indexOf(m[1]);

                if (i !== -1) {
                    target.energy.splice(i, 1);
                }
            }
        } else if ((m = line.match(CONDITION_ON_RE))) {
            const target = findAnywhere(m[1], playerState);

            if (target && !target.conditions.includes(m[2])) {
                target.conditions.push(m[2]);
            }
        } else if ((m = line.match(CONDITION_OFF_RE))) {
            const target = findAnywhere(m[1], playerState);

            if (target) {
                target.conditions = [];
            }
        } else if ((m = line.match(BENCH_ONE_RE))) {
            playerState.bench.push(makePokemon(m[1]));
        } else if (line.includes('played them to the Bench')) {
            for (const name of bulletNames(nextLine)) {
                playerState.bench.push(makePokemon(name));
            }
        } else if (DREW_ONE_RE.test(line)) {
            hand.add(playerState, 1);
        } else if ((m = line.match(DREW_MANY_RE))) {
            hand.add(playerState, Number(m[1]), bulletNames(nextLine));
        } else if ((m = line.match(DRAWN_LIST_RE))) {
            hand.add(playerState, 0, bulletNames(nextLine));
        } else if ((m = line.match(DREW_NAMED_RE))) {
            hand.add(playerState, 1, [m[1]]);
        } else if ((m = line.match(ADDED_TO_HAND_RE))) {
            hand.add(sideOf(m[2]), 1, [m[1]]);
        } else if ((m = line.match(SHUFFLED_IN_RE))) {
            hand.remove(playerState, Number(m[1]));
        } else if ((m = line.match(DISCARDED_MANY_RE))) {
            hand.remove(playerState, Number(m[1]), bulletNames(nextLine));
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
                    // Leaving the Active Spot cures special conditions.
                    playerState.active.conditions = [];
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
                    target.conditions = [];
                }

                break;
            }

            case 'attach_tool': {
                const [tool, targetName] = action.cards ?? [];
                const target = targetName
                    ? findPokemon(playerState, targetName)
                    : null;

                if (target && tool) {
                    target.tool = tool;
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

        if (PLAYED_FROM_HAND.has(action.type)) {
            const played = action.type === 'evolve' ? action.cards?.[1] : card;
            hand.remove(playerState, 1, played ? [played] : []);
        }

        // Effects live in prose: the main line (energy attached, cards drawn,
        // conditions) and the indented detail lines (ability damage counters,
        // search results benched, bench damage, hand changes).
        const lines = [action.description, ...(action.details ?? [])];
        lines.forEach((line, i) => applyLine(playerState, line, lines[i + 1]));
    }

    return state;
}
