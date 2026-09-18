<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue';
import { useBoardState } from '@/composables/useBoardState';
import type { GameAction, PlayerInfo, PokemonState } from '@/types/replay';
import PlayerSide from './PlayerSide.vue';
import PokemonDetails from './PokemonDetails.vue';

const props = defineProps<{
    actions: GameAction[];
    currentActionIndex: number;
    players: [PlayerInfo, PlayerInfo];
}>();

const indexRef = toRef(props, 'currentActionIndex');
const { boardState } = useBoardState(props.actions, props.players, indexRef);

const animatingCard = ref<string | null>(null);

const currentAction = computed(() => props.actions[props.currentActionIndex]);
const isPlayer1Turn = computed(
    () => currentAction.value?.player === props.players[0].name,
);

// Selected card is tracked by uid so the details follow playback (the
// board is rebuilt on every step) and close when the Pokémon leaves play.
const selectedUid = ref<number | null>(null);

const selected = computed(() => {
    for (const side of [boardState.value.player1, boardState.value.player2]) {
        for (const pokemon of [side.active, ...side.bench]) {
            if (pokemon && pokemon.uid === selectedUid.value) {
                return {
                    pokemon,
                    owner: side.name,
                    isActive: pokemon === side.active,
                };
            }
        }
    }

    return null;
});

const detailsOpen = computed({
    get: () => selected.value !== null,
    set: (open: boolean) => {
        if (!open) {
            selectedUid.value = null;
        }
    },
});

const select = (pokemon: PokemonState): void => {
    selectedUid.value = pokemon.uid;
};

watch(
    () => props.currentActionIndex,
    () => {
        const action = currentAction.value;

        if (!action?.cards?.length) {
            return;
        }

        animatingCard.value = action.cards[0];
        setTimeout(() => {
            animatingCard.value = null;
        }, 600);
    },
);
</script>

<template>
    <div class="relative flex h-full w-full flex-col gap-2 p-4">
        <!-- Opponent Side (Player 2) -->
        <PlayerSide
            :player-state="boardState.player2"
            :is-opponent="true"
            :is-current-turn="!isPlayer1Turn"
            :animating-card="animatingCard"
            :current-action="currentAction"
            @select="select"
        />

        <!-- Center Divider: stadium in play + transient attack/victory badge -->
        <div class="relative z-10 flex items-center justify-center py-2">
            <div class="h-px flex-1 bg-accented" />
            <div
                v-if="boardState.stadium"
                class="mx-4 flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2"
            >
                <UIcon
                    name="i-lucide-building-2"
                    class="h-4 w-4 text-emerald-400"
                />
                <span class="text-xs font-medium text-emerald-400">{{
                    boardState.stadium
                }}</span>
            </div>
            <div
                v-if="
                    currentAction?.type === 'use_attack' && currentAction.damage
                "
                class="mx-4 flex animate-pulse items-center gap-2 rounded-full bg-red-500/20 px-4 py-2"
            >
                <UIcon name="i-lucide-zap" class="h-4 w-4 text-red-500" />
                <span class="text-sm font-bold text-red-500"
                    >{{ currentAction.damage }} DMG</span
                >
            </div>
            <div
                v-else-if="currentAction?.type === 'win'"
                class="mx-4 flex items-center gap-2 rounded-full bg-amber-500/20 px-4 py-2"
            >
                <UIcon name="i-lucide-trophy" class="h-4 w-4 text-amber-400" />
                <span class="text-sm font-bold text-amber-400">Victory!</span>
            </div>
            <div class="h-px flex-1 bg-accented" />
        </div>

        <PokemonDetails
            v-if="selected"
            v-model:open="detailsOpen"
            :pokemon="selected.pokemon"
            :owner="selected.owner"
            :is-active="selected.isActive"
        />

        <!-- Your Side (Player 1) -->
        <PlayerSide
            :player-state="boardState.player1"
            :is-opponent="false"
            :is-current-turn="isPlayer1Turn"
            :animating-card="animatingCard"
            :current-action="currentAction"
            @select="select"
        />
    </div>
</template>
