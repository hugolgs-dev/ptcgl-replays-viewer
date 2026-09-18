<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useToast } from '@nuxt/ui/composables';
import { onMounted, onUnmounted } from 'vue';
import type { FlashToast } from '@/types/ui';

const toast = useToast();
let stop: (() => void) | undefined;

onMounted(() => {
    stop = router.on('flash', (event) => {
        const data = (event as CustomEvent).detail?.flash?.toast as
            | FlashToast
            | undefined;

        if (!data) {
            return;
        }

        toast.add({ description: data.message, color: data.type });
    });
});

onUnmounted(() => stop?.());
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
