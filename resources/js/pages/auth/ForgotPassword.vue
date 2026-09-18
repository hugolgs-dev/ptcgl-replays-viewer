<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <UAlert
        v-if="status"
        color="success"
        variant="soft"
        class="mb-4"
        :title="status"
    />

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <UFormField label="Email address" :error="errors.email">
                <UInput
                    id="email"
                    type="email"
                    name="email"
                    class="w-full"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
            </UFormField>

            <div class="my-6 flex items-center justify-start">
                <UButton
                    type="submit"
                    block
                    :loading="processing"
                    data-test="email-password-reset-link-button"
                >
                    Email password reset link
                </UButton>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted">
            <span>Or, return to</span>
            <TextLink :href="login()">log in</TextLink>
        </div>
    </div>
</template>
