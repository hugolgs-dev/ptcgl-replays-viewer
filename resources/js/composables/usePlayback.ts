import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { GameAction, TurnActionsMap } from '@/types/replay';

export function usePlayback(
    actions: GameAction[],
    turnActions: TurnActionsMap,
) {
    const currentActionIndex = ref(0);
    const isPlaying = ref(false);
    const playbackSpeed = ref(1);
    let intervalId: ReturnType<typeof setInterval> | null = null;

    const currentAction = computed(() => actions[currentActionIndex.value]);
    const currentTurn = computed(() => currentAction.value?.turn ?? 0);
    const isAtStart = computed(() => currentActionIndex.value === 0);
    const isAtEnd = computed(
        () => currentActionIndex.value === actions.length - 1,
    );

    function play() {
        if (isAtEnd.value) {
            return;
        }

        isPlaying.value = true;
    }

    function pause() {
        isPlaying.value = false;
    }

    function next() {
        if (!isAtEnd.value) {
            currentActionIndex.value++;
        }
    }

    function previous() {
        if (!isAtStart.value) {
            currentActionIndex.value--;
        }
    }

    function restart() {
        currentActionIndex.value = 0;
        isPlaying.value = false;
    }

    function goToEnd() {
        currentActionIndex.value = actions.length - 1;
        isPlaying.value = false;
    }

    function goToAction(index: number) {
        currentActionIndex.value = Math.max(
            0,
            Math.min(index, actions.length - 1),
        );
        isPlaying.value = false;
    }

    function goToTurn(turn: number) {
        const turnKey = String(turn);
        const turnActionsList = turnActions[turnKey];

        if (turnActionsList && turnActionsList.length > 0) {
            // Parser invariant: action ids are the flat-array indices.
            currentActionIndex.value = turnActionsList[0].id;
            isPlaying.value = false;
        }
    }

    function setSpeed(speed: number) {
        playbackSpeed.value = speed;
    }

    // Auto-play interval
    function startInterval() {
        stopInterval();
        intervalId = setInterval(() => {
            if (currentActionIndex.value >= actions.length - 1) {
                isPlaying.value = false;

                return;
            }

            currentActionIndex.value++;
        }, 1000 / playbackSpeed.value);
    }

    function stopInterval() {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    watch(isPlaying, (playing) => {
        if (playing) {
            startInterval();
        } else {
            stopInterval();
        }
    });

    watch(playbackSpeed, () => {
        if (isPlaying.value) {
            startInterval();
        }
    });

    // Keyboard shortcuts. Skipped while a form control or button has focus so
    // Space/arrows keep their native meaning there.
    const shortcuts: Record<string, () => void> = {
        ' ': () => (isPlaying.value ? pause() : play()),
        ArrowRight: next,
        ArrowLeft: previous,
        Home: restart,
        End: goToEnd,
        '[': () => goToTurn(currentTurn.value - 1),
        ']': () => goToTurn(currentTurn.value + 1),
    };

    function onKeydown(event: KeyboardEvent) {
        const handler = shortcuts[event.key];
        const target = event.target as HTMLElement | null;

        if (
            !handler ||
            event.metaKey ||
            event.ctrlKey ||
            event.altKey ||
            target?.closest(
                'input, textarea, select, button, [contenteditable], [role="slider"], [role="combobox"]',
            )
        ) {
            return;
        }

        event.preventDefault();
        handler();
    }

    onMounted(() => window.addEventListener('keydown', onKeydown));

    onBeforeUnmount(() => {
        stopInterval();
        window.removeEventListener('keydown', onKeydown);
    });

    return {
        currentActionIndex,
        currentAction,
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
    };
}
