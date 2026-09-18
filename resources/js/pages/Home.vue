<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { login, register } from '@/routes';
import { store } from '@/routes/replays';

const page = usePage<{
    auth: { user: { id: number } | null };
    sampleLog?: string;
}>();
const isAuthenticated = computed((): boolean => page.props.auth.user !== null);

const visibilityItems = [
    { label: 'Unlisted — only people with the link', value: 'unlisted' },
    { label: 'Public — listed in the hub', value: 'public' },
    { label: 'Private — only you', value: 'private' },
];

const form = useForm<{
    log: string;
    visibility: 'public' | 'unlisted' | 'private';
}>({
    log: '',
    visibility: 'unlisted',
});

const submit = (): void => {
    form.post(store.url());
};

const pasteFromClipboard = async (): Promise<void> => {
    form.log = await navigator.clipboard.readText();
};

// Optional Inertia prop: only fetched when asked for.
const loadSample = (): void => {
    router.reload({
        only: ['sampleLog'],
        onSuccess: () => {
            form.log = page.props.sampleLog ?? '';
        },
    });
};
</script>

<template>
    <UApp>
        <Head title="New replay" />

        <SiteHeader />

        <UMain class="flex flex-col justify-center">
            <UContainer class="flex max-w-2xl flex-col gap-6 py-6">
                <UPageHeader
                    title="Pokémon TCG Live Replay Viewer"
                    description="Paste a battle log below to parse it into an interactive replay."
                />

                <UAlert
                    color="neutral"
                    variant="soft"
                    icon="i-lucide-info"
                    title="How to get a battle log"
                    description="In Pokémon TCG Live, open the battle log panel during or after a match and press Copy. The log is now on your clipboard — paste it below."
                />

                <form class="flex flex-col gap-3" @submit.prevent="submit">
                    <UFormField :error="form.errors.log">
                        <template #hint>
                            <UButton
                                variant="link"
                                size="xs"
                                icon="i-lucide-clipboard-paste"
                                @click="pasteFromClipboard"
                            >
                                Paste from clipboard
                            </UButton>
                            <UButton
                                variant="link"
                                size="xs"
                                icon="i-lucide-file-text"
                                @click="loadSample"
                            >
                                Load sample log
                            </UButton>
                        </template>
                        <UTextarea
                            v-model="form.log"
                            :rows="14"
                            placeholder="Paste your battle log here..."
                            autofocus
                            class="w-full font-mono"
                        />
                    </UFormField>

                    <UFormField v-if="isAuthenticated" label="Visibility">
                        <USelect
                            v-model="form.visibility"
                            :items="visibilityItems"
                            class="w-full"
                        />
                    </UFormField>
                    <p v-else class="text-sm text-muted">
                        Guest replays are <strong>unlisted</strong> — shareable
                        by link, but not listed in the hub and not editable
                        afterwards.
                        <Link
                            :href="login()"
                            class="text-primary hover:underline"
                            >Log in</Link
                        >
                        or
                        <Link
                            :href="register()"
                            class="text-primary hover:underline"
                            >create an account</Link
                        >
                        to make replays public or private.
                    </p>

                    <UButton
                        type="submit"
                        size="lg"
                        block
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Parse replay
                    </UButton>
                </form>
            </UContainer>
        </UMain>
    </UApp>
</template>
