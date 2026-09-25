<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'" class="flex flex-col items-center gap-2">
        <span
            class="inline-flex items-center justify-center text-3xl text-white"
            style="
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background: linear-gradient(135deg, #2aabee 0%, #229ed8 100%);
                box-shadow: 0 6px 18px rgba(42, 171, 238, 0.45);
            "
            >✈</span
        >
        <span class="text-xl font-bold tracking-tight" style="color: #1b87b8"
            >AgendaFácil</span
        >
    </Link>
</template>
