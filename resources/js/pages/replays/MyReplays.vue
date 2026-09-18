<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ReplayCard from '@/components/ReplayCard.vue';
import type { ReplaySummary } from '@/components/ReplayCard.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { home } from '@/routes';

defineProps<{
    replays: {
        data: ReplaySummary[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>

<template>
    <UApp>
        <Head title="My replays" />

        <SiteHeader />

        <UMain>
            <UContainer class="flex max-w-3xl flex-col gap-6 py-6">
                <UPageHeader title="My replays">
                    <template #links>
                        <Link :href="home()">
                            <UButton variant="soft">New replay</UButton>
                        </Link>
                    </template>
                </UPageHeader>

                <UAlert
                    v-if="!replays.data.length"
                    color="neutral"
                    variant="soft"
                    title="No replays yet"
                    description="You haven't created any replays yet."
                />

                <ul v-else class="flex flex-col gap-3">
                    <li v-for="replay in replays.data" :key="replay.slug">
                        <ReplayCard :replay="replay" />
                    </li>
                </ul>

                <div class="flex justify-between">
                    <Link
                        v-if="replays.prev_page_url"
                        :href="replays.prev_page_url"
                    >
                        <UButton variant="ghost" icon="i-lucide-arrow-left"
                            >Previous</UButton
                        >
                    </Link>
                    <span v-else />
                    <Link
                        v-if="replays.next_page_url"
                        :href="replays.next_page_url"
                    >
                        <UButton
                            variant="ghost"
                            trailing-icon="i-lucide-arrow-right"
                            >Next</UButton
                        >
                    </Link>
                </div>
            </UContainer>
        </UMain>
    </UApp>
</template>
