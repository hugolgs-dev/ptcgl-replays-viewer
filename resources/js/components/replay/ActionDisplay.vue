<script setup lang="ts">
import { computed } from 'vue';
import type { ActionType, GameAction, PlayerInfo } from '@/types/replay';
import { ACTION_COLORS, ACTION_LABELS } from '@/types/replay';

const props = defineProps<{
    action: GameAction;
    actionIndex: number;
    totalActions: number;
    players: [PlayerInfo, PlayerInfo];
}>();

const ICON_MAP: Partial<Record<ActionType, string>> = {
    use_attack: 'i-lucide-swords',
    attach_energy: 'i-lucide-zap',
    use_ability: 'i-lucide-sparkles',
    switch_active: 'i-lucide-arrow-right-left',
    retreat: 'i-lucide-arrow-right-left',
    knockout: 'i-lucide-skull',
    take_prize: 'i-lucide-gift',
    supporter: 'i-lucide-user',
    stadium: 'i-lucide-building-2',
    item: 'i-lucide-package',
    win: 'i-lucide-award',
};

const actionIcon = computed(
    () => ICON_MAP[props.action.type] ?? 'i-lucide-chevron-right',
);
const actionLabel = computed(() => ACTION_LABELS[props.action.type]);
const colorClass = computed(() => ACTION_COLORS[props.action.type]);

const isHighlight = computed(() =>
    ['use_attack', 'knockout', 'win', 'evolve', 'use_ability'].includes(
        props.action.type,
    ),
);

const borderColorClass = computed(() => {
    if (props.action.type === 'use_attack') {
        return 'border-l-red-500';
    }

    if (props.action.type === 'knockout') {
        return 'border-l-red-600';
    }

    if (props.action.type === 'win') {
        return 'border-l-yellow-500';
    }

    if (props.action.type === 'evolve') {
        return 'border-l-green-500';
    }

    if (props.action.type === 'use_ability') {
        return 'border-l-purple-500';
    }

    return '';
});

const isPlayer1 = computed(() => props.action.player === props.players[0].name);

function formatDescription(action: GameAction): string {
    let desc = action.description;

    if (desc.startsWith(`${action.player}'s `)) {
        desc = desc.substring(action.player.length + 3);
    } else if (desc.startsWith(`${action.player} `)) {
        desc = desc.substring(action.player.length + 1);
    }

    return desc.charAt(0).toUpperCase() + desc.slice(1);
}
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Phase indicator -->
        <div class="mb-4 flex items-center gap-2">
            <UBadge
                :variant="action.phase === 'setup' ? 'subtle' : 'solid'"
                size="xs"
            >
                {{
                    action.phase === 'setup'
                        ? 'Setup Phase'
                        : `Turn ${action.turn}`
                }}
            </UBadge>
            <span class="text-xs opacity-40">
                Action {{ actionIndex + 1 }} of {{ totalActions }}
            </span>
        </div>

        <!-- Main action card -->
        <UCard
            class="flex-1"
            :class="[isHighlight && 'border-l-4', borderColorClass]"
        >
            <div class="flex h-full flex-col">
                <!-- Player indicator -->
                <div class="mb-6 flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full"
                        :class="
                            isPlayer1
                                ? 'bg-[color:var(--ui-primary)]/20'
                                : 'bg-amber-500/20'
                        "
                    >
                        <UIcon
                            :name="actionIcon"
                            class="h-6 w-6"
                            :class="
                                isPlayer1
                                    ? 'text-[color:var(--ui-primary)]'
                                    : 'text-amber-400'
                            "
                        />
                    </div>
                    <div>
                        <p class="text-lg font-semibold">{{ action.player }}</p>
                        <UBadge variant="outline" size="xs" :class="colorClass">
                            {{ actionLabel }}
                        </UBadge>
                    </div>
                </div>

                <!-- Description -->
                <div class="flex-1 space-y-4">
                    <p class="text-base leading-relaxed font-medium">
                        {{ formatDescription(action) }}
                    </p>

                    <!-- Details -->
                    <div
                        v-if="action.details?.length"
                        class="space-y-2 rounded-lg bg-muted p-4"
                    >
                        <p
                            class="text-xs font-medium tracking-wider uppercase opacity-40"
                        >
                            Details
                        </p>
                        <ul class="space-y-1">
                            <li
                                v-for="(detail, idx) in action.details"
                                :key="idx"
                                class="flex items-start gap-2 text-sm opacity-60"
                                :class="detail.startsWith('•') && 'pl-6'"
                            >
                                <UIcon
                                    v-if="!detail.startsWith('•')"
                                    name="i-lucide-chevron-right"
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[color:var(--ui-primary)]"
                                />
                                <span>{{ detail }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Damage indicator -->
                    <div
                        v-if="action.damage"
                        class="flex items-center gap-3 rounded-lg border border-red-500/20 bg-red-500/10 p-4"
                    >
                        <UIcon
                            name="i-lucide-swords"
                            class="h-6 w-6 text-red-500"
                        />
                        <div>
                            <p class="text-2xl font-bold text-red-500">
                                {{ action.damage }} damage
                            </p>
                            <p v-if="action.target" class="text-sm opacity-60">
                                to {{ action.target }}
                            </p>
                        </div>
                    </div>

                    <!-- Victory indicator -->
                    <div
                        v-if="action.type === 'win'"
                        class="flex items-center justify-center gap-3 rounded-lg border border-amber-500/20 bg-amber-500/10 p-6"
                    >
                        <UIcon
                            name="i-lucide-award"
                            class="h-8 w-8 text-amber-400"
                        />
                        <p class="text-2xl font-bold text-amber-400">
                            {{ action.player }} Wins!
                        </p>
                    </div>
                </div>
            </div>
        </UCard>
    </div>
</template>
