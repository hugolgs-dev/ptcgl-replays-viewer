<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    currentTurn: number;
    totalTurns: number;
}>();

const emit = defineEmits<{
    (e: 'go-to-turn', turn: number): void;
}>();

const items = computed(() =>
    Array.from({ length: props.totalTurns + 1 }, (_, i) => ({
        label: i === 0 ? 'Setup' : `Turn ${i}`,
        value: i,
    })),
);
</script>

<template>
    <div class="flex items-center gap-2">
        <UButton
            variant="ghost"
            color="neutral"
            icon="i-lucide-chevron-left"
            size="sm"
            aria-label="Previous turn"
            :disabled="currentTurn <= 0"
            @click="emit('go-to-turn', Math.max(0, currentTurn - 1))"
        />

        <USelect
            :model-value="currentTurn"
            :items="items"
            value-key="value"
            class="w-[120px]"
            size="sm"
            @update:model-value="emit('go-to-turn', $event)"
        />

        <UButton
            variant="ghost"
            color="neutral"
            icon="i-lucide-chevron-right"
            size="sm"
            aria-label="Next turn"
            :disabled="currentTurn >= totalTurns"
            @click="emit('go-to-turn', Math.min(totalTurns, currentTurn + 1))"
        />
    </div>
</template>
