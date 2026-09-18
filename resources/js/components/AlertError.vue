<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    errors: string[];
    title?: string;
};

const props = withDefaults(defineProps<Props>(), {
    title: 'Something went wrong.',
});

const uniqueErrors = computed(() => Array.from(new Set(props.errors)));
</script>

<template>
    <UAlert
        color="error"
        variant="subtle"
        icon="i-lucide-alert-circle"
        :title="title"
    >
        <template #description>
            <ul class="list-inside list-disc text-sm">
                <li v-for="(error, index) in uniqueErrors" :key="index">
                    {{ error }}
                </li>
            </ul>
        </template>
    </UAlert>
</template>
