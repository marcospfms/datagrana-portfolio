<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AuthBase from '@/layouts/AuthLayout.vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Login desativado"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div class="text-center text-sm text-muted-foreground">
            O login pelo site foi desativado.
        </div>
    </AuthBase>
</template>
