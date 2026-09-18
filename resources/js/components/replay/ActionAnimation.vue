<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { GameAction } from '@/types/replay';

const props = defineProps<{
    action: GameAction;
}>();

const show = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    show.value = true;
    timer = setTimeout(() => {
        show.value = false;
    }, 800);
});

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});

/** Map action types to an icon name + Tailwind color class + animation class */
function getAnimConfig(type: string) {
    const map: Record<string, { icon: string; color: string; anim: string }> = {
        use_attack: {
            icon: 'i-lucide-swords',
            color: 'text-red-500',
            anim: 'animate-attack-flash',
        },
        knockout: {
            icon: 'i-lucide-skull',
            color: 'text-red-500',
            anim: 'animate-knockout',
        },
        win: {
            icon: 'i-lucide-trophy',
            color: 'text-amber-400',
            anim: 'animate-victory',
        },
        evolve: {
            icon: 'i-lucide-arrow-up',
            color: 'text-green-400',
            anim: 'animate-evolve',
        },
        attach_energy: {
            icon: 'i-lucide-zap',
            color: 'text-yellow-400',
            anim: 'animate-energy',
        },
        use_ability: {
            icon: 'i-lucide-sparkles',
            color: 'text-purple-400',
            anim: 'animate-ability',
        },
        take_prize: {
            icon: 'i-lucide-gift',
            color: 'text-amber-400',
            anim: 'animate-prize',
        },
        switch_active: {
            icon: 'i-lucide-arrow-right-left',
            color: 'text-cyan-400',
            anim: 'animate-switch',
        },
        retreat: {
            icon: 'i-lucide-arrow-right-left',
            color: 'text-cyan-400',
            anim: 'animate-switch',
        },
        coin_flip: {
            icon: 'i-lucide-circle-dot',
            color: 'text-yellow-400',
            anim: 'animate-coin-flip',
        },
        coin_result: {
            icon: 'i-lucide-circle-dot',
            color: 'text-yellow-400',
            anim: 'animate-coin-flip',
        },
        supporter: {
            icon: 'i-lucide-user',
            color: 'text-pink-400',
            anim: 'animate-card-play',
        },
        stadium: {
            icon: 'i-lucide-building-2',
            color: 'text-emerald-400',
            anim: 'animate-card-play',
        },
        item: {
            icon: 'i-lucide-package',
            color: 'text-sky-400',
            anim: 'animate-card-play',
        },
    };

    return map[type] ?? null;
}

const config = getAnimConfig(props.action.type);
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show && config"
            class="pointer-events-none absolute inset-0 z-50 flex items-center justify-center bg-black/20 backdrop-blur-sm"
        >
            <div class="flex flex-col items-center gap-2" :class="config.anim">
                <UIcon
                    :name="config.icon"
                    class="h-12 w-12"
                    :class="config.color"
                />

                <!-- Damage number for attacks -->
                <span
                    v-if="action.type === 'use_attack' && action.damage"
                    class="text-4xl font-black text-red-500"
                >
                    {{ action.damage }}
                </span>

                <!-- Knockout text -->
                <span
                    v-if="action.type === 'knockout'"
                    class="text-2xl font-black tracking-wider text-red-500 uppercase"
                >
                    Knocked Out!
                </span>

                <!-- Victory text -->
                <span
                    v-if="action.type === 'win'"
                    class="text-2xl font-black tracking-wider text-amber-400 uppercase"
                >
                    Victory!
                </span>

                <!-- Evolve text -->
                <span
                    v-if="action.type === 'evolve'"
                    class="text-xl font-bold text-green-400"
                >
                    Evolution!
                </span>
            </div>
        </div>
    </Transition>
</template>
