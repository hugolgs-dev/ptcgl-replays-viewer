<script setup lang="ts">
import { computed, inject } from 'vue';
import type { Ref } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        isActive?: boolean;
        isHighlighted?: boolean;
        damage?: number;
        isKnockedOut?: boolean;
        size?: 'sm' | 'md' | 'lg';
        showDamage?: boolean;
        conditions?: string[];
        tool?: string | null;
    }>(),
    {
        isActive: false,
        isHighlighted: false,
        damage: 0,
        isKnockedOut: false,
        size: 'md',
        showDamage: true,
        conditions: () => [],
        tool: null,
    },
);

// Card art is resolved server-side and provided by the page as a
// name → base-url map (see Show.vue). Append the tcgdex size suffix.
const emit = defineEmits<{ (e: 'select'): void }>();

const cardImages = inject<Ref<Record<string, string>>>('cardImages');
const cardHp = inject<Ref<Record<string, number>>>('cardHp');

// Like the game: show remaining HP when the card's HP is known, else damage.
const hp = computed(() => cardHp?.value[props.name] ?? null);
const remainingHp = computed(() =>
    hp.value === null ? null : Math.max(0, hp.value - props.damage),
);
const hpClass = computed(() => {
    if (hp.value === null || remainingHp.value === null) {
        return 'bg-red-600';
    }

    const ratio = remainingHp.value / hp.value;

    return ratio > 0.5
        ? 'bg-emerald-600'
        : ratio > 0.25
          ? 'bg-amber-500'
          : 'bg-red-600';
});

const CONDITION_STYLE: Record<string, string> = {
    Poisoned: 'bg-purple-600',
    Burned: 'bg-orange-600',
    Asleep: 'bg-blue-600',
    Paralyzed: 'bg-yellow-500 text-black',
    Confused: 'bg-pink-600',
};

const imageUrl = computed(() => {
    const base = cardImages?.value[props.name];

    return base ? `${base}/high.webp` : null;
});

const sizeClasses = computed(() => {
    const map = { sm: 'w-16 h-22', md: 'w-24 h-33', lg: 'w-32 h-44' };

    return map[props.size];
});
</script>

<template>
    <button
        type="button"
        class="relative block cursor-pointer overflow-hidden rounded-lg text-left transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
        :aria-label="`Show ${name} details`"
        @click="emit('select')"
        :class="[
            sizeClasses,
            isActive && 'shadow-lg ring-2 ring-[color:var(--ui-primary)]',
            isHighlighted && 'animate-pulse ring-2 ring-amber-400',
            isKnockedOut && 'opacity-40 grayscale',
        ]"
    >
        <!-- Background / placeholder -->
        <div
            class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-accented to-muted"
        >
            <div v-if="!imageUrl" class="p-2 text-center">
                <span class="text-xs leading-tight font-medium opacity-60">{{
                    name
                }}</span>
            </div>
        </div>

        <!-- Card image -->
        <img
            v-if="imageUrl"
            :src="imageUrl"
            :alt="name"
            loading="lazy"
            class="absolute inset-0 h-full w-full object-cover"
        />

        <!-- HP / damage counter -->
        <div
            v-if="showDamage && !isKnockedOut && (hp !== null || damage > 0)"
            class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-xs font-bold text-white shadow-lg"
            :class="hp !== null ? hpClass : 'bg-red-600'"
            :title="
                hp !== null ? `${remainingHp} / ${hp} HP` : `${damage} damage`
            "
        >
            {{ hp !== null ? remainingHp : damage }}
        </div>

        <!-- Special conditions -->
        <div
            v-if="conditions.length && !isKnockedOut"
            class="absolute top-1 left-1 flex flex-col gap-0.5"
        >
            <span
                v-for="condition in conditions"
                :key="condition"
                class="rounded px-1 text-[8px] font-bold text-white uppercase"
                :class="CONDITION_STYLE[condition] ?? 'bg-gray-600'"
                :title="condition"
            >
                {{ condition.slice(0, 3) }}
            </span>
        </div>

        <!-- Tool -->
        <div
            v-if="tool"
            class="absolute bottom-4 left-1 flex h-4 w-4 items-center justify-center rounded-full bg-black/60"
            :title="tool"
        >
            <UIcon name="i-lucide-wrench" class="h-2.5 w-2.5 text-white" />
        </div>

        <!-- Knocked out overlay -->
        <div
            v-if="isKnockedOut"
            class="absolute inset-0 flex items-center justify-center bg-black/50"
        >
            <span class="-rotate-[15deg] text-xs font-bold text-red-500"
                >KO</span
            >
        </div>

        <!-- Active indicator -->
        <div
            v-if="isActive"
            class="absolute right-0 bottom-0 left-0 bg-[color:var(--ui-primary)]/80 py-0.5 text-center text-[8px] font-bold text-white"
        >
            ACTIVE
        </div>
    </button>
</template>
