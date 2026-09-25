<template>
    <div
        class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0"
        style="
            background: linear-gradient(
                180deg,
                #d8ecf7 0%,
                #f4f9fc 60%,
                #f4f9fc 100%
            );
        "
    >
        <div>
            <slot name="logo" />
        </div>

        <div
            class="mt-6 w-full overflow-hidden border-t-4 bg-white px-6 py-6 sm:max-w-md sm:rounded-2xl"
            style="
                border-color: #2aabee;
                box-shadow: 0 8px 30px rgba(42, 171, 238, 0.15);
            "
        >
            <slot />
        </div>

        <p
            class="mt-6 text-xs font-medium tracking-wide"
            style="color: #6b8ca3"
        >
            AgendaFácil · acesso administrativo
        </p>
    </div>
</template>
