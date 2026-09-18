<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        type: string;
        count?: number;
        size?: 'sm' | 'md';
    }>(),
    { count: 1, size: 'sm' },
);

const colorMap: Record<string, string> = {
    fire: 'bg-orange-500',
    water: 'bg-blue-500',
    grass: 'bg-green-500',
    lightning: 'bg-yellow-400',
    psychic: 'bg-purple-500',
    fighting: 'bg-amber-700',
    darkness: 'bg-gray-800',
    metal: 'bg-gray-400',
    fairy: 'bg-pink-400',
    dragon: 'bg-amber-500',
    colorless: 'bg-gray-300',
    basic: 'bg-gray-400',
};

const bgColor = computed(() => {
    const key = props.type.toLowerCase().replace(/\s+energy$/i, '');

    return colorMap[key] || 'bg-gray-400';
});

const sizeClass = computed(() =>
    props.size === 'sm' ? 'w-4 h-4 text-[8px]' : 'w-6 h-6 text-xs',
);
</script>

<template>
    <div
        :class="[bgColor, sizeClass]"
        class="flex items-center justify-center rounded-full font-bold text-white shadow-md"
    >
        {{ count > 1 ? count : '' }}
    </div>
</template>
