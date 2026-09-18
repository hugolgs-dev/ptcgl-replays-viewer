export type ActionType =
    | 'coin_flip'
    | 'coin_result'
    | 'first_turn_decision'
    | 'draw_opening_hand'
    | 'play_to_active'
    | 'play_to_bench'
    | 'draw_card'
    | 'play_card'
    | 'attach_energy'
    | 'attach_tool'
    | 'use_ability'
    | 'use_attack'
    | 'retreat'
    | 'switch_active'
    | 'evolve'
    | 'knockout'
    | 'take_prize'
    | 'discard'
    | 'shuffle_deck'
    | 'end_turn'
    | 'win'
    | 'supporter'
    | 'stadium'
    | 'item'
    | 'exp_share'
    | 'status_condition'
    | 'unknown';

export interface GameAction {
    /** Sequential from 0; the parser guarantees `actions[i].id === i`. */
    id: number;
    turn: number;
    phase: 'setup' | 'game';
    player: string;
    type: ActionType;
    description: string;
    details?: string[];
    target?: string;
    damage?: number;
    cards?: string[];
}

export interface PlayerInfo {
    name: string;
}

export interface ParsedGame {
    players: [PlayerInfo, PlayerInfo];
    actions: GameAction[];
    totalTurns: number;
    winner: string | null;
    coinFlipWinner: string | null;
}

/** Keyed by turn number (0 = setup). Values are arrays of actions for that turn. */
export type TurnActionsMap = Record<string, GameAction[]>;

// ── Board state types (computed on the client from the parsed actions) ──

export interface PokemonState {
    /** Stable per fold: the same action prefix always yields the same uids. */
    uid: number;
    name: string;
    damage: number;
    isKnockedOut: boolean;
    energy: string[];
    tool: string | null;
    /** Poisoned, Burned, Asleep, Paralyzed, Confused */
    conditions: string[];
}

/** Approximate: the log only names cards it reveals. `known` ⊆ hand. */
export interface HandState {
    size: number;
    known: string[];
}

export interface PlayerBoardState {
    name: string;
    active: PokemonState | null;
    bench: PokemonState[];
    prizeCards: number;
    hand: HandState;
}

export interface BoardState {
    player1: PlayerBoardState;
    player2: PlayerBoardState;
    stadium: string | null;
}

// ── Helper maps ──

export const ACTION_LABELS: Record<ActionType, string> = {
    coin_flip: 'Coin Flip',
    coin_result: 'Coin Result',
    first_turn_decision: 'Turn Order',
    draw_opening_hand: 'Opening Hand',
    play_to_active: 'Play Active',
    play_to_bench: 'Play Bench',
    draw_card: 'Draw',
    play_card: 'Play Card',
    attach_energy: 'Attach Energy',
    attach_tool: 'Attach Tool',
    use_ability: 'Ability',
    use_attack: 'Attack',
    retreat: 'Retreat',
    switch_active: 'Switch',
    evolve: 'Evolution',
    knockout: 'Knockout',
    take_prize: 'Prize',
    discard: 'Discard',
    shuffle_deck: 'Shuffle',
    end_turn: 'End Turn',
    win: 'Victory',
    supporter: 'Supporter',
    stadium: 'Stadium',
    item: 'Item',
    exp_share: 'Exp. Share',
    status_condition: 'Condition',
    unknown: 'Action',
};

export const ACTION_COLORS: Record<ActionType, string> = {
    coin_flip: 'text-yellow-400',
    coin_result: 'text-yellow-400',
    first_turn_decision: 'text-blue-400',
    draw_opening_hand: 'text-blue-400',
    play_to_active: 'text-red-400',
    play_to_bench: 'text-green-400',
    draw_card: 'text-blue-400',
    play_card: 'text-cyan-400',
    attach_energy: 'text-yellow-400',
    attach_tool: 'text-orange-400',
    use_ability: 'text-purple-400',
    use_attack: 'text-red-500',
    retreat: 'text-gray-400',
    switch_active: 'text-cyan-400',
    evolve: 'text-green-500',
    knockout: 'text-red-600',
    take_prize: 'text-amber-400',
    discard: 'text-gray-500',
    shuffle_deck: 'text-gray-400',
    end_turn: 'text-gray-500',
    win: 'text-yellow-500',
    supporter: 'text-pink-400',
    stadium: 'text-emerald-400',
    item: 'text-sky-400',
    exp_share: 'text-amber-400',
    status_condition: 'text-purple-400',
    unknown: 'text-gray-400',
};
