<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { NavigationMenuItem } from '@nuxt/ui';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import { home } from '@/routes';
import { mine } from '@/routes/replays';

const mainNavItems: NavigationMenuItem[] = [
    {
        label: 'My replays',
        icon: 'i-lucide-film',
        to: mine().url,
    },
];

const footerNavItems: NavigationMenuItem[] = [
    {
        label: 'Repository',
        icon: 'i-lucide-folder-git-2',
        to: 'https://github.com/laravel/vue-starter-kit',
        target: '_blank',
    },
    {
        label: 'Documentation',
        icon: 'i-lucide-book-open',
        to: 'https://laravel.com/docs/starter-kits#vue',
        target: '_blank',
    },
];
</script>

<template>
    <UDashboardSidebar
        collapsible
        resizable
        :ui="{ footer: 'flex-col items-stretch gap-2' }"
    >
        <template #header>
            <Link :href="home()" class="flex items-center gap-x-2">
                <AppLogo />
            </Link>
        </template>

        <template #default="{ collapsed }">
            <UNavigationMenu
                :collapsed="collapsed"
                :items="mainNavItems"
                orientation="vertical"
            />
        </template>

        <template #footer="{ collapsed }">
            <UNavigationMenu
                :collapsed="collapsed"
                :items="footerNavItems"
                orientation="vertical"
                class="w-full"
            />
            <NavUser :collapsed="collapsed" />
        </template>
    </UDashboardSidebar>
</template>
