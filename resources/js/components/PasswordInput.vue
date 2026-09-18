<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue';
import { ref, useTemplateRef } from 'vue';

defineOptions({ inheritAttrs: false });

const showPassword = ref(false);
const inputRef = useTemplateRef<ComponentPublicInstance>('inputRef');

defineExpose({
    focus: () => inputRef.value?.$el?.querySelector('input')?.focus(),
});
</script>

<template>
    <UInput
        ref="inputRef"
        :type="showPassword ? 'text' : 'password'"
        class="w-full"
        v-bind="$attrs"
    >
        <template #trailing>
            <UButton
                color="neutral"
                variant="link"
                size="sm"
                :icon="showPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :tabindex="-1"
                @click="showPassword = !showPassword"
            />
        </template>
    </UInput>
</template>
