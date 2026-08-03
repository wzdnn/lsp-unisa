<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppLayout from "../../layouts/AppLayout.vue";
import BaseToast from "../../components/BaseToast.vue";
import TabApl01Pengajuan from "../../components/lsp/TabApl01Pengajuan.vue";

const route = useRoute();
const router = useRouter();

const tabs = [
    {
        key: "pendaftaran",
        label: "Pendaftaran",
        to: "/admin/assesment/pendaftaran",
        icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
    },
    {
        key: "pre-assesment",
        label: "Pre-Assesment",
        to: "/admin/assesment/pre-assesment",
        icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2",
    },
    {
        key: "assesment",
        label: "Assesment",
        to: "/admin/assesment/assesment",
        icon: "M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z",
    },
    {
        key: "post-assesment",
        label: "Post-Assesment",
        to: "/admin/assesment/post-assesment",
        icon: "M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
    },
];

const stageParam = computed(() => route.params.stage || "pendaftaran");
const activeStage = computed(() =>
    tabs.some((tab) => tab.key === stageParam.value)
        ? stageParam.value
        : "pendaftaran",
);
const activeTab = computed(
    () => tabs.find((tab) => tab.key === activeStage.value) || tabs[0],
);

watch(
    () => route.params.stage,
    (stage) => {
        if (!tabs.some((tab) => tab.key === stage)) {
            router.replace("/admin/assesment/pendaftaran");
        }
    },
    { immediate: true },
);

const toast = ref({ show: false, message: "", type: "success" });
const showToast = ({ message, type = "success" }) => {
    toast.value = { show: true, message, type };
    setTimeout(() => (toast.value.show = false), 3000);
};
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h2 class="text-xl font-bold text-[#1e3329]">Assesment</h2>
            <p class="text-[#7aab95] text-sm mt-1">
                Alur sertifikasi dari pendaftaran hingga post-assesment
            </p>
        </div>

        <div
            class="bg-white rounded-2xl border border-[#dde8e3] overflow-hidden shadow-sm"
        >
            <div class="flex border-b border-[#dde8e3] overflow-x-auto">
                <router-link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :to="tab.to"
                    :class="
                        activeStage === tab.key
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
                </router-link>
            </div>

            <div class="p-6">
                <TabApl01Pengajuan
                    v-if="activeStage === 'pendaftaran'"
                    @toast="showToast"
                />

                <div
                    v-else
                    class="min-h-[320px] flex items-center justify-center rounded-xl border border-dashed border-[#c8ddd6] bg-[#f9fbfa]"
                >
                    <div class="text-center px-6">
                        <div
                            class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-lg bg-[#eaf2ee] text-[#2d4a3e]"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    :d="activeTab.icon"
                                />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-[#1e3329]">
                            {{ activeTab.label }}
                        </h3>
                        <p class="text-sm text-[#7aab95] mt-1">
                            Belum tersedia
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <BaseToast
            :show="toast.show"
            :message="toast.message"
            :type="toast.type"
        />
    </AppLayout>
</template>
