<script setup>
import { ref } from "vue";
import AppLayout from "../../layouts/AppLayout.vue";
import BaseToast from "../../components/BaseToast.vue";
import TabPeriode from "../../components/lsp/TabPeriode.vue";
import TabMasa from "../../components/lsp/TabMasa.vue";
import TabSkema from "../../components/lsp/TabSkema.vue";
import TabPlotting from "../../components/lsp/TabPlotting.vue";
import TabUnitKompetensi from "../../components/lsp/TabUnitKompetensi.vue";

const activeTab = ref("periode");
const tabs = [
    {
        key: "periode",
        label: "Periode",
        icon: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z",
    },
    {
        key: "masa",
        label: "Masa Periode",
        icon: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
    },
    {
        key: "skema",
        label: "Skema",
        icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
    },
    {
        key: "unitkompetensi",
        label: "Unit Kompetensi",
        icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
    },
    {
        key: "plotting",
        label: "Plotting Skema",
        icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
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
            <h2 class="text-xl font-bold text-[#1e3329]">Manajemen LSP</h2>
            <p class="text-[#7aab95] text-sm mt-1">
                Kelola periode, masa, skema, plotting, dan unit kompetensi
            </p>
        </div>

        <div
            class="bg-white rounded-2xl border border-[#dde8e3] overflow-hidden shadow-sm"
        >
            <div class="flex border-b border-[#dde8e3] overflow-x-auto">
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

            <div class="p-6">
                <TabPeriode v-if="activeTab === 'periode'" @toast="showToast" />
                <TabMasa v-if="activeTab === 'masa'" @toast="showToast" />
                <TabSkema v-if="activeTab === 'skema'" @toast="showToast" />
                <TabUnitKompetensi
                    v-if="activeTab === 'unitkompetensi'"
                    @toast="showToast"
                />
                <TabPlotting
                    v-if="activeTab === 'plotting'"
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
