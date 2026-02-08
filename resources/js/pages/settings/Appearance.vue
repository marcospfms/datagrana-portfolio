<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <h1 class="sr-only">Appearance Settings</h1>

        <SettingsLayout>
            <div class="space-y-4">
                <h2 class="text-lg font-semibold">Settings desativado</h2>
                <p class="text-sm text-muted-foreground">
                    Estas configurações foram desativadas temporariamente.
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
