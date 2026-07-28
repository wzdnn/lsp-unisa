<script setup>
defineProps({
    show: { type: Boolean, default: false },
    message: { type: String, default: "" },
    type: { type: String, default: "success" },
});
</script>

<template>
    <Teleport to="body">
        <Transition name="toast">
            <div
                v-if="show"
                :class="
                    type === 'success'
                        ? 'bg-[#1e3329] text-white'
                        : 'bg-red-600 text-white'
                "
                class="fixed bottom-6 right-6 z-[60] flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium"
            >
                <svg
                    v-if="type === 'success'"
                    class="w-4 h-4 text-[#7aab95]"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5 13l4 4L19 7"
                    />
                </svg>
                <svg
                    v-else
                    class="w-4 h-4 text-red-200"
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
                {{ message }}
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(1rem);
}
</style>
