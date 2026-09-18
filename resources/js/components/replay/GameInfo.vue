<script setup lang="ts">
import { computed } from 'vue';
import type { ParsedGame } from '@/types/replay';

const props = defineProps<{
    game: ParsedGame;
}>();

const player1 = computed(() => props.game.players[0]);
const player2 = computed(() => props.game.players[1]);
</script>

<template>
    <div class="flex items-center gap-4">
        <!-- Player 1 -->
        <div class="flex items-center gap-2">
            <span
                class="text-sm font-semibold"
                :class="
                    game.winner === player1.name
                        ? 'text-amber-400'
                        : 'text-default'
                "
            >
                {{ player1.name }}
            </span>
            <UIcon
                v-if="game.winner === player1.name"
                name="i-lucide-crown"
                class="h-4 w-4 text-amber-400"
            />
        </div>

        <!-- VS -->
        <UBadge variant="outline" color="neutral" class="gap-1">
            <UIcon name="i-lucide-swords" class="h-3 w-3" />
            vs
        </UBadge>

        <!-- Player 2 -->
        <div class="flex items-center gap-2">
            <UIcon
                v-if="game.winner === player2.name"
                name="i-lucide-crown"
                class="h-4 w-4 text-amber-400"
            />
            <span
                class="text-sm font-semibold"
                :class="
                    game.winner === player2.name
                        ? 'text-amber-400'
                        : 'text-default'
                "
            >
                {{ player2.name }}
            </span>
        </div>
    </div>
</template>
