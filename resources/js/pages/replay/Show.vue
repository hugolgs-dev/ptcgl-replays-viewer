<script setup lang="ts">
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { useToast } from '@nuxt/ui/composables';
import { computed, provide, toRef } from 'vue';
import ActionAnimation from '@/components/replay/ActionAnimation.vue';
import ActionDisplay from '@/components/replay/ActionDisplay.vue';
import GameBoard from '@/components/replay/GameBoard.vue';
import GameInfo from '@/components/replay/GameInfo.vue';
import ReplayControls from '@/components/replay/ReplayControls.vue';
import Timeline from '@/components/replay/Timeline.vue';
import TurnNavigator from '@/components/replay/TurnNavigator.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { usePlayback } from '@/composables/usePlayback';
import { home, register } from '@/routes';
import { destroy, update } from '@/routes/replays';
import type {
    ActionType,
    GameAction,
    ParsedGame,
    TurnActionsMap,
} from '@/types/replay';

type Visibility = 'public' | 'unlisted' | 'private';

const props = defineProps<{
    replay: { id: number; slug: string; visibility: Visibility };
    game: ParsedGame;
    cardImages: Record<string, string>;
    cardCategories: Record<string, string>;
    cardHp: Record<string, number>;
    cardsResolving: boolean;
    canEdit: boolean;
}>();

const page = usePage<{ auth: { user: { id: number } | null } }>();
const isAuthenticated = computed((): boolean => page.props.auth.user !== null);

const toast = useToast();

const notifyGuestVisibility = (): void => {
    toast.add({
        title: 'Account required',
        description:
            'Guest replays stay unlisted. Create an account to make this replay public or private.',
        color: 'info',
        actions: [
            { label: 'Register', onClick: () => router.visit(register().url) },
        ],
    });
};

const visibilityItems = [
    { label: 'Unlisted', value: 'unlisted' },
    { label: 'Public', value: 'public' },
    { label: 'Private', value: 'private' },
];

const visibilityForm = useForm<{ visibility: Visibility }>({
    visibility: props.replay.visibility,
});

const updateVisibility = (): void => {
    // Partial reload: the large `game` prop is unchanged, don't resend it.
    visibilityForm.patch(update(props.replay.slug).url, {
        preserveScroll: true,
        only: ['replay'],
    });
};

const copyLink = async (): Promise<void> => {
    await navigator.clipboard.writeText(window.location.href);
    toast.add({ title: 'Link copied', color: 'success' });
};

const deleteReplay = (): void => {
    if (confirm('Delete this replay? This cannot be undone.')) {
        router.delete(destroy(props.replay.slug).url);
    }
};

// ── Card art: resolved by a queued job, so poll until every card is known ──
provide('cardImages', toRef(props, 'cardImages'));
provide('cardHp', toRef(props, 'cardHp'));

let polls = 0;
const { start: startPolling, stop: stopPolling } = usePoll(
    2000,
    {
        only: ['cardImages', 'cardCategories', 'cardHp', 'cardsResolving'],
        onFinish: () => {
            if (!props.cardsResolving || ++polls >= 30) {
                stopPolling();
            }
        },
    },
    { autoStart: false },
);

if (props.cardsResolving) {
    startPolling();
}

// ── Replay viewer ──
const rawActions = props.game.actions ?? [];

// The parser only knows a handful of item/supporter names; once tcgdex has
// told us a card's real category, upgrade generic `play_card` actions.
const CATEGORY_TYPES: Record<string, ActionType> = {
    Item: 'item',
    Supporter: 'supporter',
    Stadium: 'stadium',
    Tool: 'attach_tool',
};

const actions = computed<GameAction[]>(() =>
    rawActions.map((action) => {
        if (action.type !== 'play_card') {
            return action;
        }

        const type =
            CATEGORY_TYPES[props.cardCategories[action.cards?.[0] ?? '']];

        return type ? { ...action, type } : action;
    }),
);

function buildTurnActions(list: GameAction[]): TurnActionsMap {
    const map: TurnActionsMap = {
        '0': list.filter((a) => a.phase === 'setup'),
    };

    for (const action of list) {
        if (action.phase === 'game') {
            const key = String(action.turn);
            (map[key] ??= []).push(action);
        }
    }

    return map;
}

const turnActions = computed(() => buildTurnActions(actions.value));

const {
    currentActionIndex,
    currentTurn,
    isPlaying,
    playbackSpeed,
    isAtStart,
    isAtEnd,
    play,
    pause,
    next,
    previous,
    restart,
    goToEnd,
    goToAction,
    goToTurn,
    setSpeed,
} = usePlayback(rawActions, buildTurnActions(rawActions));

const currentAction = computed(() => actions.value[currentActionIndex.value]);
</script>

<template>
    <UApp>
        <Head :title="`Replay ${replay.slug}`" />

        <SiteHeader />

        <UMain>
            <UContainer
                class="flex max-w-[calc(100vw-4rem)] flex-col gap-6 py-6"
            >
                <UPageHeader
                    :ui="{ title: 'text-xl sm:text-2xl font-semibold' }"
                >
                    <template #title>
                        <div class="flex items-center gap-3">
                            <span>Replay {{ replay.slug }}</span>
                            <USelect
                                v-if="canEdit"
                                v-model="visibilityForm.visibility"
                                :items="visibilityItems"
                                :loading="visibilityForm.processing"
                                size="sm"
                                aria-label="Visibility"
                                @update:model-value="updateVisibility"
                            />
                            <UButton
                                v-else-if="!isAuthenticated"
                                variant="subtle"
                                size="sm"
                                class="capitalize"
                                @click="notifyGuestVisibility"
                            >
                                {{ replay.visibility }}
                            </UButton>
                            <UBadge
                                v-else
                                variant="subtle"
                                class="capitalize"
                                >{{ replay.visibility }}</UBadge
                            >
                        </div>
                    </template>
                    <template #links>
                        <UButton
                            variant="ghost"
                            icon="i-lucide-link"
                            @click="copyLink"
                        >
                            Copy link
                        </UButton>
                        <UButton
                            v-if="canEdit"
                            variant="ghost"
                            color="error"
                            icon="i-lucide-trash-2"
                            @click="deleteReplay"
                        >
                            Delete
                        </UButton>
                        <Link :href="home()">
                            <UButton variant="soft">New replay</UButton>
                        </Link>
                    </template>
                </UPageHeader>

                <template v-if="actions.length">
                    <!-- Players + turn navigation -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <GameInfo :game="game" />
                        <TurnNavigator
                            :current-turn="currentTurn"
                            :total-turns="game.totalTurns"
                            @go-to-turn="goToTurn"
                        />
                    </div>

                    <!-- Board + action detail -->
                    <section
                        class="grid grid-cols-1 gap-4 lg:grid-cols-[4fr_1fr]"
                    >
                        <div
                            class="relative min-h-[400px] overflow-hidden rounded-xl border border-default bg-elevated"
                        >
                            <GameBoard
                                :actions="actions"
                                :current-action-index="currentActionIndex"
                                :players="game.players"
                            />
                            <ActionAnimation
                                v-if="currentAction"
                                :key="currentActionIndex"
                                :action="currentAction"
                            />
                        </div>

                        <ActionDisplay
                            v-if="currentAction"
                            :action="currentAction"
                            :action-index="currentActionIndex"
                            :total-actions="actions.length"
                            :players="game.players"
                        />
                    </section>

                    <!-- Timeline -->
                    <Timeline
                        :actions="actions"
                        :current-action-index="currentActionIndex"
                        :turn-actions="turnActions"
                        @action-click="goToAction"
                    />

                    <!-- Controls -->
                    <ReplayControls
                        :is-playing="isPlaying"
                        :current-index="currentActionIndex"
                        :total-actions="actions.length"
                        :playback-speed="playbackSpeed"
                        :is-at-start="isAtStart"
                        :is-at-end="isAtEnd"
                        @play="play"
                        @pause="pause"
                        @next="next"
                        @previous="previous"
                        @restart="restart"
                        @go-to-end="goToEnd"
                        @speed-change="setSpeed"
                        @seek="goToAction"
                    />

                    <p class="text-center text-xs text-muted">
                        Shortcuts: Space play/pause · ← → step · [ ] turn ·
                        Home/End
                    </p>
                </template>

                <UAlert
                    v-else
                    color="neutral"
                    variant="soft"
                    title="Nothing to replay"
                    description="This battle log didn't contain any parseable actions."
                />
            </UContainer>
        </UMain>
    </UApp>
</template>
