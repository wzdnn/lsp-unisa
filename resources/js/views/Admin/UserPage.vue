<script setup>
import { ref } from "vue";
import AppLayout from "../../layouts/AppLayout.vue";
import BaseToast from "../../components/BaseToast.vue";
import TabUser from "../../components/lsp/TabUser.vue";
import TabAsesorLuar from "../../components/lsp/TabAsesorLuar.vue";

const activeTab = ref("user");
const tabs = [
    {
        key: "user",
        label: "Semua User",
        icon: "M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z",
    },
    {
        key: "asesor_luar",
        label: "Asesor Luar",
        icon: "M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z",
    },
];

const toast = ref({ show: false, message: "", type: "success" });
const showToast = ({ message, type = "success" }) => {
    toast.value = { show: true, message, type };
    setTimeout(() => (toast.value.show = false), 3000);
};
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h2 class="text-xl font-bold text-[#1e3329]">Manajemen User</h2>
            <p class="text-[#7aab95] text-sm mt-1">
                Kelola user terdaftar dan asesor luar universitas
            </p>
        </div>

        <div
            class="bg-white rounded-2xl border border-[#dde8e3] overflow-hidden shadow-sm"
        >
            <!-- Tab Header -->
            <div class="flex border-b border-[#dde8e3]">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    :class="
                        activeTab === tab.key
                            ? 'text-[#2d4a3e] border-b-2 border-[#2d4a3e] bg-[#f0f4f1]'
                            : 'text-slate-400 hover:text-[#4a7c6b] hover:bg-[#f9fbfa]'
                    "
                    class="flex items-center gap-2 px-5 py-4 text-xs font-semibold transition-all whitespace-nowrap"
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
                            stroke-width="1.5"
                            :d="tab.icon"
                        />
                    </svg>
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <TabUser v-if="activeTab === 'user'" @toast="showToast" />
                <TabAsesorLuar
                    v-if="activeTab === 'asesor_luar'"
                    @toast="showToast"
                />
            </div>
        </div>

        <BaseToast
            :show="toast.show"
            :message="toast.message"
            :type="toast.type"
        />
    </AppLayout>
</template>
