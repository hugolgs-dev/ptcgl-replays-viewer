<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashToasts from '@/components/FlashToasts.vue';
import { useAppearance } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';
import { login, logout, register } from '@/routes';
import { index as replaysIndex, mine as replaysMine } from '@/routes/replays';

const page = usePage<{ auth: { user: { name: string } | null } }>();
const user = computed(() => page.props.auth.user);

const { appearance, updateAppearance } = useAppearance();

const appearanceIcons = {
    light: 'i-lucide-sun',
    dark: 'i-lucide-moon',
    system: 'i-lucide-monitor',
} as const;

const appearanceItems = computed(() =>
    (['light', 'dark', 'system'] as const).map((value) => ({
        label: value.charAt(0).toUpperCase() + value.slice(1),
        icon: appearanceIcons[value],
        checked: appearance.value === value,
        onSelect: (): void => updateAppearance(value),
    })),
);

const handleLogout = (): void => {
    router.post(logout().url);
};

const menuItems = computed(() => [
    [
        {
            label: 'My replays',
            onSelect: (): void => router.visit(replaysMine().url),
        },
    ],
    [{ label: 'Log out', onSelect: handleLogout }],
]);
</script>

<template>
    <UHeader title="PTCG Replays Viewer" to="/" :ui="{ root: 'relative' }">
        <nav class="flex items-center gap-2">
            <Link :href="replaysIndex()">
                <UButton variant="ghost" size="sm">Browse</UButton>
            </Link>

            <UDropdownMenu :items="appearanceItems">
                <UButton
                    variant="ghost"
                    color="neutral"
                    size="sm"
                    :icon="appearanceIcons[appearance]"
                    aria-label="Theme"
                />
            </UDropdownMenu>

            <template v-if="user">
                <UDropdownMenu :items="menuItems">
                    <UButton variant="ghost" size="sm" class="gap-2">
                        <UAvatar
                            :alt="user.name"
                            :text="getInitials(user.name)"
                            size="2xs"
                        />
                        {{ user.name }}
                    </UButton>
                </UDropdownMenu>
            </template>
            <template v-else>
                <Link :href="login()">
                    <UButton variant="ghost" size="sm">Log in</UButton>
                </Link>
                <Link :href="register()">
                    <UButton size="sm">Register</UButton>
                </Link>
            </template>
        </nav>
    </UHeader>

    <FlashToasts />
</template>
