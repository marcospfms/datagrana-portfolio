<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AuthBase from '@/layouts/AuthLayout.vue';
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Registro desativado"
    >
        <Head title="Register" />

        <div class="text-center text-sm text-muted-foreground">
            O registro pelo site foi desativado.
        </div>
    </AuthBase>
</template>
