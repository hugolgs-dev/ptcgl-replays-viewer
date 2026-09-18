<script setup lang="ts">
defineProps<{
    isPlaying: boolean;
    currentIndex: number;
    totalActions: number;
    playbackSpeed: number;
    isAtStart: boolean;
    isAtEnd: boolean;
}>();

const emit = defineEmits<{
    (e: 'play'): void;
    (e: 'pause'): void;
    (e: 'next'): void;
    (e: 'previous'): void;
    (e: 'restart'): void;
    (e: 'go-to-end'): void;
    (e: 'speed-change', speed: number): void;
    (e: 'seek', index: number): void;
}>();

const speedOptions = [0.5, 1, 1.5, 2, 3];

const onSeek = (value: number | number[]): void => {
    emit('seek', Array.isArray(value) ? value[0] : value);
};
</script>

<template>
    <UCard>
        <div class="flex flex-col items-center gap-4 sm:flex-row">
            <!-- Main controls -->
            <div class="flex items-center gap-2">
                <UButton
                    variant="ghost"
                    color="neutral"
                    icon="i-lucide-rewind"
                    :disabled="isAtStart"
                    title="Go to start"
                    aria-label="Go to start"
                    @click="emit('restart')"
                />
                <UButton
                    variant="outline"
                    color="neutral"
                    icon="i-lucide-skip-back"
                    :disabled="isAtStart"
                    title="Previous action"
                    aria-label="Previous action"
                    @click="emit('previous')"
                />
                <UButton
                    :icon="isPlaying ? 'i-lucide-pause' : 'i-lucide-play'"
                    size="xl"
                    :disabled="isAtEnd"
                    :title="isPlaying ? 'Pause' : 'Play'"
                    :aria-label="isPlaying ? 'Pause' : 'Play'"
                    class="rounded-full"
                    @click="isPlaying ? emit('pause') : emit('play')"
                />
                <UButton
                    variant="outline"
                    color="neutral"
                    icon="i-lucide-skip-forward"
                    :disabled="isAtEnd"
                    title="Next action"
                    aria-label="Next action"
                    @click="emit('next')"
                />
                <UButton
                    variant="ghost"
                    color="neutral"
                    icon="i-lucide-fast-forward"
                    :disabled="isAtEnd"
                    title="Go to end"
                    aria-label="Go to end"
                    @click="emit('go-to-end')"
                />
            </div>

            <!-- Progress slider -->
            <div class="flex w-full flex-1 items-center gap-3">
                <USlider
                    :model-value="currentIndex"
                    :min="0"
                    :max="Math.max(0, totalActions - 1)"
                    :step="1"
                    class="flex-1"
                    @update:model-value="onSeek"
                />
                <span class="text-xs whitespace-nowrap text-muted">
                    {{ currentIndex + 1 }} / {{ totalActions }}
                </span>
            </div>

            <!-- Speed control -->
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 text-muted">
                    <UIcon name="i-lucide-gauge" class="h-4 w-4" />
                    <span class="text-xs font-medium tracking-wider uppercase"
                        >Speed</span
                    >
                </div>
                <div class="flex items-center gap-1">
                    <UButton
                        v-for="speed in speedOptions"
                        :key="speed"
                        :variant="playbackSpeed === speed ? 'solid' : 'ghost'"
                        :color="playbackSpeed === speed ? 'primary' : 'neutral'"
                        size="sm"
                        :aria-label="`Speed ${speed}x`"
                        @click="emit('speed-change', speed)"
                    >
                        {{ speed }}x
                    </UButton>
                </div>
            </div>
        </div>
    </UCard>
</template>
