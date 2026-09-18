<script setup lang="ts">
import { computed, inject } from 'vue';
import type { Ref } from 'vue';
import type { PokemonState } from '@/types/replay';
import EnergyBadge from './EnergyBadge.vue';

const props = defineProps<{
    pokemon: PokemonState;
    owner: string;
    isActive: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const cardImages = inject<Ref<Record<string, string>>>('cardImages');
const cardHp = inject<Ref<Record<string, number>>>('cardHp');

const hp = computed(() => cardHp?.value[props.pokemon.name] ?? null);
const remainingHp = computed(() =>
    hp.value === null ? null : Math.max(0, hp.value - props.pokemon.damage),
);
const hpColor = computed(() => {
    if (hp.value === null || remainingHp.value === null) {
        return 'neutral';
    }

    const ratio = remainingHp.value / hp.value;

    return ratio > 0.5 ? 'success' : ratio > 0.25 ? 'warning' : 'error';
});

const imageUrl = computed(() => {
    const base = cardImages?.value[props.pokemon.name];

    return base ? `${base}/high.webp` : null;
});

const energyImage = (energy: string): string | null => {
    // Any card thumbnail, not just energy.
    const base = cardImages?.value[energy];

    return base ? `${base}/low.webp` : null;
};
</script>

<template>
    <UModal
        v-model:open="open"
        :title="pokemon.name"
        :description="`${owner} · ${isActive ? 'Active Spot' : 'Bench'}`"
    >
        <template #body>
            <div class="flex flex-col gap-6 sm:flex-row">
                <img
                    v-if="imageUrl"
                    :src="imageUrl"
                    :alt="pokemon.name"
                    class="w-full max-w-64 shrink-0 self-start rounded-lg shadow-lg"
                    :class="pokemon.isKnockedOut && 'grayscale'"
                />
                <div
                    v-else
                    class="flex h-88 w-64 shrink-0 items-center justify-center rounded-lg bg-muted text-sm text-muted"
                >
                    No card art yet
                </div>

                <dl class="flex flex-1 flex-col gap-4 text-sm">
                    <div v-if="hp !== null">
                        <dt class="text-xs tracking-wider text-muted uppercase">
                            HP
                        </dt>
                        <dd class="mt-1 flex flex-col gap-1">
                            <span class="font-semibold"
                                >{{ remainingHp }} / {{ hp }}</span
                            >
                            <UProgress
                                :model-value="remainingHp ?? 0"
                                :max="hp"
                                :color="hpColor"
                                size="sm"
                            />
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs tracking-wider text-muted uppercase">
                            Status
                        </dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            <UBadge
                                v-if="pokemon.isKnockedOut"
                                color="error"
                                variant="subtle"
                            >
                                Knocked Out
                            </UBadge>
                            <UBadge
                                v-else-if="pokemon.damage > 0"
                                color="error"
                                variant="subtle"
                            >
                                {{ pokemon.damage }} damage
                            </UBadge>
                            <UBadge v-else color="success" variant="subtle">
                                Undamaged
                            </UBadge>
                            <UBadge
                                v-for="condition in pokemon.conditions"
                                :key="condition"
                                color="warning"
                                variant="subtle"
                            >
                                {{ condition }}
                            </UBadge>
                        </dd>
                    </div>

                    <div v-if="pokemon.tool">
                        <dt class="text-xs tracking-wider text-muted uppercase">
                            Tool
                        </dt>
                        <dd class="mt-1 flex items-center gap-2">
                            <img
                                v-if="energyImage(pokemon.tool)"
                                :src="energyImage(pokemon.tool)!"
                                :alt="pokemon.tool"
                                class="h-14 w-10 rounded object-cover"
                            />
                            <span>{{ pokemon.tool }}</span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs tracking-wider text-muted uppercase">
                            Energy ({{ pokemon.energy.length }})
                        </dt>
                        <dd class="mt-1">
                            <ul
                                v-if="pokemon.energy.length"
                                class="flex flex-col gap-1"
                            >
                                <li
                                    v-for="(energy, i) in pokemon.energy"
                                    :key="i"
                                    class="flex items-center gap-2"
                                >
                                    <img
                                        v-if="energyImage(energy)"
                                        :src="energyImage(energy)!"
                                        :alt="energy"
                                        class="h-14 w-10 rounded object-cover"
                                    />
                                    <EnergyBadge
                                        v-else
                                        :type="energy"
                                        size="sm"
                                    />
                                    <span>{{ energy }}</span>
                                </li>
                            </ul>
                            <span v-else class="text-muted">None attached</span>
                        </dd>
                    </div>
                </dl>
            </div>
        </template>
    </UModal>
</template>
