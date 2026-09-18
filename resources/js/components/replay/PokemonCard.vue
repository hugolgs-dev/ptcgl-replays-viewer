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
    }>(),
    {
        isActive: false,
        isHighlighted: false,
        damage: 0,
        isKnockedOut: false,
        size: 'md',
        showDamage: true,
    },
);

// Card art is resolved server-side and provided by the page as a
// name → base-url map (see Show.vue). Append the tcgdex size suffix.
const emit = defineEmits<{ (e: 'select'): void }>();

const cardImages = inject<Ref<Record<string, string>>>('cardImages');

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

        <!-- Damage counter -->
        <div
            v-if="showDamage && damage > 0 && !isKnockedOut"
            class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white shadow-lg"
        >
            {{ damage }}
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
