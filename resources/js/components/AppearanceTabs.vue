<script setup lang="ts">
import type { TabsItem } from '@nuxt/ui';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const items = [
    { value: 'light', icon: 'i-lucide-sun', label: 'Light' },
    { value: 'dark', icon: 'i-lucide-moon', label: 'Dark' },
    { value: 'system', icon: 'i-lucide-monitor', label: 'System' },
] satisfies TabsItem[];

const onChange = (value: string | number): void => {
    updateAppearance(value as 'light' | 'dark' | 'system');
};
</script>

<template>
    <UTabs
        :items="items"
        :model-value="appearance"
        :content="false"
        color="neutral"
        class="w-fit"
        @update:model-value="onChange"
    />
</template>
