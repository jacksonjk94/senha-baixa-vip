<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0" style="background: linear-gradient(180deg, #D8ECF7 0%, #F4F9FC 60%, #F4F9FC 100%);">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white overflow-hidden sm:rounded-2xl border-t-4" style="border-color: #2AABEE; box-shadow: 0 8px 30px rgba(42, 171, 238, 0.15);">
            <slot />
        </div>

        <p class="mt-6 text-xs font-medium tracking-wide" style="color: #6B8CA3;">AgendaFácil · acesso administrativo</p>
    </div>
</template>
