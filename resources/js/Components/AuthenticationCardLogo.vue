<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'" class="flex flex-col items-center gap-2">
        <span
            class="inline-flex items-center justify-center text-white text-3xl"
            style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #2AABEE 0%, #229ED8 100%); box-shadow: 0 6px 18px rgba(42, 171, 238, 0.45);"
        >✈</span>
        <span class="text-xl font-bold tracking-tight" style="color: #1B87B8;">AgendaFácil</span>
    </Link>
</template>
