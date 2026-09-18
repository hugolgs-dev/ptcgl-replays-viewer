<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/routes/replays';

export type ReplaySummary = {
    slug: string;
    players: string[];
    winner: string | null;
    totalTurns: number;
    visibility: string;
    createdAt: string | null;
};

const props = defineProps<{ replay: ReplaySummary }>();

const matchup =
    props.replay.players.length > 0
        ? props.replay.players.join(' vs ')
        : 'Unknown players';
</script>

<template>
    <Link :href="show(replay.slug)" class="block">
        <UCard class="transition hover:ring-2">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="font-medium">{{ matchup }}</p>
                    <p class="text-xs text-muted">
                        {{ replay.totalTurns }} turns
                        <span v-if="replay.winner">
                            · winner: {{ replay.winner }}</span
                        >
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <UBadge variant="subtle" size="sm" class="capitalize">{{
                        replay.visibility
                    }}</UBadge>
                    <span class="font-mono text-xs text-muted">{{
                        replay.slug
                    }}</span>
                </div>
            </div>
        </UCard>
    </Link>
</template>
