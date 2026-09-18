<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import type { DropdownMenuItem } from '@nuxt/ui';
import { computed } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types/auth';

defineProps<{
    collapsed?: boolean;
}>();

const page = usePage<{ auth: Auth }>();
const user = computed(() => page.props.auth.user);

const handleLogout = (): void => {
    router.flushAll();
    router.post(logout().url);
};

const items = computed<DropdownMenuItem[][]>(() => [
    [
        {
            label: user.value.name,
            avatar: {
                src: user.value.avatar || undefined,
                text: getInitials(user.value.name),
                alt: user.value.name,
            },
            type: 'label',
        },
    ],
    [
        {
            label: 'Settings',
            icon: 'i-lucide-settings',
            to: edit().url,
        },
    ],
    [
        {
            label: 'Log out',
            icon: 'i-lucide-log-out',
            onSelect: handleLogout,
        },
    ],
]);
</script>

<template>
    <UDropdownMenu
        :items="items"
        :content="{ align: 'start', side: collapsed ? 'right' : 'top' }"
        :ui="{ content: 'w-(--reka-dropdown-menu-trigger-width) min-w-56' }"
    >
        <UButton
            color="neutral"
            variant="ghost"
            block
            :class="collapsed ? 'justify-center' : ''"
            data-test="sidebar-menu-button"
        >
            <UAvatar
                :src="user.avatar || undefined"
                :alt="user.name"
                :text="getInitials(user.name)"
                size="2xs"
            />
            <template v-if="!collapsed">
                <span class="flex-1 truncate text-left">{{ user.name }}</span>
                <UIcon name="i-lucide-chevrons-up-down" class="size-4" />
            </template>
        </UButton>
    </UDropdownMenu>
</template>
