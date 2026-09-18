<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui';
import Heading from '@/components/Heading.vue';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const sidebarNavItems: NavigationMenuItem[] = [
    {
        label: 'Profile',
        to: editProfile().url,
    },
    {
        label: 'Security',
        to: editSecurity().url,
    },
    {
        label: 'Appearance',
        to: editAppearance().url,
    },
];
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <UNavigationMenu
                    :items="sidebarNavItems"
                    orientation="vertical"
                    aria-label="Settings"
                />
            </aside>

            <USeparator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
