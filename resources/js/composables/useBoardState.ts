import { computed } from 'vue';
import type { Ref } from 'vue';
import { calculateBoardState } from '@/lib/boardState';
import type { BoardState, GameAction, PlayerInfo } from '@/types/replay';

/**
 * Reactively computes the visual board state by replaying all actions
 * up to `currentIndex`. The result changes whenever the ref changes.
 */
export function useBoardState(
    actions: GameAction[],
    players: [PlayerInfo, PlayerInfo],
    currentIndex: Ref<number>,
) {
    const boardState = computed<BoardState>(() => {
        return calculateBoardState(
            actions.slice(0, currentIndex.value + 1),
            players,
        );
    });

    return { boardState };
}
