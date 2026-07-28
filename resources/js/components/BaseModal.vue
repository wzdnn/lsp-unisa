<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: "" },
    size: { type: String, default: "md" },
});
const emit = defineEmits(["close"]);
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center px-4"
                @click.self="emit('close')"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                ></div>

                <!-- Panel -->
                <div
                    :class="{
                        'max-w-md': size === 'md',
                        'max-w-lg': size === 'lg',
                        'max-w-2xl': size === 'xl',
                    }"
                    class="relative w-full bg-white rounded-2xl shadow-2xl overflow-hidden"
                >
                    <!-- Header -->
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-slate-100"
                    >
                        <h3 class="text-sm font-semibold text-[#1e3329]">
                            {{ title }}
                        </h3>
                        <button
                            @click="emit('close')"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="px-6 py-5">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: all 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
