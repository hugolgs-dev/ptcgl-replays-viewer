<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { GameAction, TurnActionsMap } from '@/types/replay';

const props = defineProps<{
    actions: GameAction[];
    currentActionIndex: number;
    turnActions: TurnActionsMap;
}>();

const emit = defineEmits<{
    (e: 'action-click', index: number): void;
}>();

const scrollRef = ref<HTMLElement | null>(null);

const turns = computed(() =>
    Object.entries(props.turnActions)
        .map(([turn, list]) => [Number(turn), list] as [number, GameAction[]])
        .sort((a, b) => a[0] - b[0]),
);

const keyActions = [
    'use_attack',
    'knockout',
    'win',
    'evolve',
    'use_ability',
    'take_prize',
];

const dotBg: Record<string, string> = {
    use_attack: 'bg-red-500/80',
    knockout: 'bg-red-600/80',
    win: 'bg-yellow-500/80',
    evolve: 'bg-green-500/60',
    use_ability: 'bg-purple-500/60',
    take_prize: 'bg-amber-500/60',
    attach_energy: 'bg-yellow-400/40',
    supporter: 'bg-pink-400/40',
    item: 'bg-sky-400/40',
    stadium: 'bg-emerald-400/40',
};

const timelineIcon: Record<string, string> = {
    use_attack: 'i-lucide-swords',
    knockout: 'i-lucide-skull',
    win: 'i-lucide-award',
    use_ability: 'i-lucide-sparkles',
    attach_energy: 'i-lucide-zap',
};

// Parser invariant: action ids are the flat-array indices.
const globalIndexOf = (action: GameAction): number => action.id;

const isKey = (type: string): boolean => keyActions.includes(type);

const dotClass = (action: GameAction): string => {
    const bg =
        dotBg[action.type] ??
        (isKey(action.type) ? '' : 'bg-[color:var(--ui-bg-muted)]');

    return bg || 'bg-[color:var(--ui-bg-muted)]';
};

// Auto-scroll to keep the current action visible.
watch(
    () => props.currentActionIndex,
    async () => {
        await nextTick();
        const active = scrollRef.value?.querySelector<HTMLElement>(
            '[data-active="true"]',
        );
        active?.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center',
        });
    },
);
</script>

<template>
    <UCard>
        <div ref="scrollRef" class="-mx-2 overflow-x-auto px-2 pb-2">
            <div class="flex min-w-max gap-1">
                <div
                    v-for="[turn, list] in turns"
                    :key="turn"
                    class="flex flex-col gap-1"
                >
                    <!-- Turn label -->
                    <div class="text-center">
                        <UBadge variant="subtle" color="neutral" size="xs">
                            {{ turn === 0 ? 'Setup' : `T${turn}` }}
                        </UBadge>
                    </div>

                    <!-- Action dots -->
                    <div class="flex gap-1">
                        <button
                            v-for="action in list"
                            :key="action.id"
                            type="button"
                            :data-active="
                                globalIndexOf(action) === currentActionIndex
                            "
                            :title="action.description"
                            class="relative flex items-center justify-center rounded-sm transition-all duration-200 hover:z-10 hover:scale-110 focus:ring-2 focus:ring-[color:var(--ui-primary)]/50 focus:outline-none"
                            :class="[
                                isKey(action.type) ? 'h-6 w-6' : 'h-6 w-3',
                                globalIndexOf(action) === currentActionIndex &&
                                    'z-10 scale-110 ring-2 ring-[color:var(--ui-primary)]',
                                globalIndexOf(action) < currentActionIndex
                                    ? 'opacity-60'
                                    : 'opacity-100',
                                dotClass(action),
                            ]"
                            @click="emit('action-click', globalIndexOf(action))"
                        >
                            <UIcon
                                v-if="timelineIcon[action.type]"
                                :name="timelineIcon[action.type]"
                                class="h-3.5 w-3.5 text-white drop-shadow"
                            />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </UCard>
</template>
