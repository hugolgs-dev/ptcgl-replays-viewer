<script setup lang="ts">
import FlashToasts from '@/components/FlashToasts.vue';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <UApp>
        <AuthLayout :title="title" :description="description">
            <slot />
        </AuthLayout>
        <FlashToasts />
    </UApp>
</template>
