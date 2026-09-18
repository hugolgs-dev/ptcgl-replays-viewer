<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types/auth';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage<{
    auth: Auth;
    mustVerifyEmail?: boolean;
    status?: string;
}>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <UFormField label="Name" :error="errors.name">
                <UInput
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
            </UFormField>

            <UFormField label="Email address" :error="errors.email">
                <UInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
            </UFormField>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-default underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <UAlert
                    v-if="page.props.status === 'verification-link-sent'"
                    color="success"
                    variant="soft"
                    class="mt-2"
                    title="A new verification link has been sent to your email address."
                />
            </div>

            <div class="flex items-center gap-4">
                <UButton
                    type="submit"
                    :loading="processing"
                    data-test="update-profile-button"
                >
                    Save
                </UButton>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
