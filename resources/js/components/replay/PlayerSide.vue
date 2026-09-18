<script setup lang="ts">
import { computed } from 'vue';
import type {
    GameAction,
    PlayerBoardState,
    PokemonState,
} from '@/types/replay';
import EnergyBadge from './EnergyBadge.vue';
import PokemonCard from './PokemonCard.vue';

const props = defineProps<{
    playerState: PlayerBoardState;
    isOpponent: boolean;
    isCurrentTurn: boolean;
    animatingCard: string | null;
    currentAction?: GameAction;
}>();

const emit = defineEmits<{
    (e: 'select', pokemon: PokemonState): void;
}>();

const isAttacking = computed(
    () =>
        props.currentAction?.type === 'use_attack' &&
        props.currentAction.player === props.playerState.name,
);
const isBeingAttacked = computed(
    () =>
        props.currentAction?.type === 'use_attack' &&
        props.currentAction.player !== props.playerState.name,
);
const emptyBenchSlots = computed(() =>
    Math.max(0, 5 - props.playerState.bench.length),
);
</script>

<template>
    <div
        class="flex flex-1 flex-col gap-3 rounded-xl p-3 transition-all duration-300"
        :class="[
            isOpponent ? 'flex-col' : 'flex-col-reverse',
            isCurrentTurn &&
                'bg-[color:var(--ui-primary)]/5 ring-1 ring-[color:var(--ui-primary)]/20',
        ]"
    >
        <!-- Player info -->
        <div
            class="flex items-center justify-between"
            :class="isOpponent ? 'flex-row' : 'flex-row-reverse'"
        >
            <div class="flex items-center gap-2">
                <span
                    class="text-sm font-semibold"
                    :class="
                        isCurrentTurn ? 'text-[color:var(--ui-primary)]' : ''
                    "
                >
                    {{ playerState.name }}
                </span>
                <UBadge v-if="isCurrentTurn" variant="subtle" size="xs"
                    >Turn</UBadge
                >
            </div>
            <div class="flex items-center gap-1 opacity-60">
                <UIcon name="i-lucide-gift" class="h-4 w-4 text-amber-400" />
                <span class="text-xs font-medium">{{
                    playerState.prizeCards
                }}</span>
            </div>
        </div>

        <!-- Board area -->
        <div
            class="flex items-center gap-4"
            :class="isOpponent ? 'flex-row' : 'flex-row-reverse'"
        >
            <!-- Active Pokemon -->
            <div class="flex flex-col items-center gap-1">
                <span class="text-[10px] tracking-wider uppercase opacity-40"
                    >Active</span
                >
                <div
                    class="relative transition-transform duration-300"
                    :class="[
                        isAttacking && 'animate-attack',
                        isBeingAttacked && 'animate-hit',
                    ]"
                >
                    <PokemonCard
                        v-if="playerState.active"
                        :name="playerState.active.name"
                        :is-active="true"
                        :damage="playerState.active.damage"
                        :is-knocked-out="playerState.active.isKnockedOut"
                        :is-highlighted="
                            animatingCard === playerState.active.name
                        "
                        size="lg"
                        @select="emit('select', playerState.active)"
                    />
                    <div
                        v-else
                        class="flex h-44 w-32 items-center justify-center rounded-lg border-2 border-dashed border-default"
                    >
                        <span class="text-xs opacity-40">Empty</span>
                    </div>
                    <!-- Energy badges on active -->
                    <div
                        v-if="playerState.active?.energy.length"
                        class="absolute -bottom-2 left-1/2 flex -translate-x-1/2 gap-0.5"
                    >
                        <EnergyBadge
                            v-for="(e, i) in playerState.active.energy.slice(
                                0,
                                6,
                            )"
                            :key="i"
                            :type="e"
                            size="sm"
                        />
                        <span
                            v-if="playerState.active.energy.length > 6"
                            class="text-[8px] opacity-40"
                        >
                            +{{ playerState.active.energy.length - 6 }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bench -->
            <div class="flex flex-1 flex-col items-center gap-1">
                <span class="text-[10px] tracking-wider uppercase opacity-40"
                    >Bench</span
                >
                <div class="flex flex-wrap justify-center gap-2">
                    <div
                        v-for="pokemon in playerState.bench"
                        :key="pokemon.uid"
                        class="relative"
                    >
                        <PokemonCard
                            :name="pokemon.name"
                            :damage="pokemon.damage"
                            :is-knocked-out="pokemon.isKnockedOut"
                            :is-highlighted="animatingCard === pokemon.name"
                            size="sm"
                            @select="emit('select', pokemon)"
                        />
                        <div
                            v-if="pokemon.energy.length"
                            class="absolute -bottom-1 left-1/2 flex -translate-x-1/2 gap-0.5"
                        >
                            <EnergyBadge
                                v-for="(e, i) in pokemon.energy.slice(0, 3)"
                                :key="i"
                                :type="e"
                                size="sm"
                            />
                        </div>
                    </div>
                    <div
                        v-for="i in emptyBenchSlots"
                        :key="'empty-' + i"
                        class="h-22 w-16 rounded-lg border border-dashed border-default"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
