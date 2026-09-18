<script setup lang="ts">
import { computed } from 'vue';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

const props = defineProps<Props>();

const items = computed(() =>
    props.breadcrumbs.map((item, index) => ({
        label: item.title,
        to:
            index === props.breadcrumbs.length - 1
                ? undefined
                : toUrl(item.href),
    })),
);
</script>

<template>
    <UBreadcrumb :items="items" />
</template>
