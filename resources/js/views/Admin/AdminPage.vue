<script setup>
import { computed, onMounted, ref } from "vue";
import AppLayout from "../../layouts/AppLayout.vue";
import {
    apl01PengajuanService,
    periodeService,
    plottingService,
    skemaService,
    unitKompetensiService,
} from "../../services/lspService";

const loading = ref(false);
const error = ref("");
const pengajuan = ref([]);
const skema = ref([]);
const plotting = ref([]);
const periode = ref([]);
const unitKompetensi = ref([]);

const statusMeta = [
    {
        key: "draft",
        label: "Draft",
        color: "bg-slate-400",
        text: "text-slate-600",
    },
    {
        key: "menunggu_review",
        label: "Menunggu Review",
        color: "bg-amber-500",
        text: "text-amber-700",
    },
    {
        key: "diterima",
        label: "Diterima",
        color: "bg-[#2d4a3e]",
        text: "text-[#2d4a3e]",
    },
    {
        key: "perlu_revisi",
        label: "Perlu Revisi",
        color: "bg-blue-500",
        text: "text-blue-700",
    },
    {
        key: "ditolak",
        label: "Ditolak",
        color: "bg-red-500",
        text: "text-red-600",
    },
];

const fetchDashboard = async () => {
    loading.value = true;
    error.value = "";

    try {
        const [pengajuanRes, skemaRes, plottingRes, periodeRes, unitRes] =
            await Promise.all([
                apl01PengajuanService.getAll(),
                skemaService.getAll(),
                plottingService.getAll(),
                periodeService.getAll(),
                unitKompetensiService.getAll(),
            ]);

        pengajuan.value = Array.isArray(pengajuanRes.data)
            ? pengajuanRes.data
            : [];
        skema.value = Array.isArray(skemaRes.data) ? skemaRes.data : [];
        plotting.value = Array.isArray(plottingRes.data)
            ? plottingRes.data
            : [];
        periode.value = Array.isArray(periodeRes.data) ? periodeRes.data : [];
        unitKompetensi.value = Array.isArray(unitRes.data) ? unitRes.data : [];
    } catch (err) {
        error.value =
            err.response?.data?.message || "Gagal memuat dashboard admin";
    } finally {
        loading.value = false;
    }
};

const countByStatus = (status) =>
    pengajuan.value.filter((item) => item.status === status).length;

const totalPengajuan = computed(() => pengajuan.value.length);
const menungguReview = computed(() => countByStatus("menunggu_review"));
const diterima = computed(() => countByStatus("diterima"));
const perluRevisi = computed(() => countByStatus("perlu_revisi"));
const skemaAktif = computed(
    () => skema.value.filter((item) => item.isActive).length,
);
const skemaNonaktif = computed(() => skema.value.length - skemaAktif.value);

const maxStatusCount = computed(() =>
    Math.max(...statusMeta.map((status) => countByStatus(status.key)), 1),
);

const statusChart = computed(() =>
    statusMeta.map((status) => {
        const count = countByStatus(status.key);

        return {
            ...status,
            count,
            width: `${Math.max((count / maxStatusCount.value) * 100, count ? 8 : 0)}%`,
        };
    }),
);

const skemaChart = computed(() => {
    const total = Math.max(skema.value.length, 1);

    return [
        {
            label: "Aktif",
            count: skemaAktif.value,
            class: "bg-[#2d4a3e]",
            width: `${(skemaAktif.value / total) * 100}%`,
        },
        {
            label: "Nonaktif",
            count: skemaNonaktif.value,
            class: "bg-slate-300",
            width: `${(skemaNonaktif.value / total) * 100}%`,
        },
    ];
});

const recentPengajuan = computed(() =>
    [...pengajuan.value]
        .sort(
            (a, b) =>
                new Date(b.submitted_at || b.created_at || 0) -
                new Date(a.submitted_at || a.created_at || 0),
        )
        .slice(0, 5),
);

const cards = computed(() => [
    {
        label: "Menunggu Review",
        value: menungguReview.value,
        hint: "Pengajuan perlu dicek admin",
        class: "text-amber-700",
    },
    {
        label: "Pengajuan Diterima",
        value: diterima.value,
        hint: "Mahasiswa bisa lanjut tahap berikutnya",
        class: "text-[#2d4a3e]",
    },
    {
        label: "Perlu Revisi",
        value: perluRevisi.value,
        hint: "Menunggu perbaikan mahasiswa",
        class: "text-blue-700",
    },
    {
        label: "Skema Aktif",
        value: skemaAktif.value,
        hint: `${skema.value.length} total skema`,
        class: "text-[#1e3329]",
    },
]);

const getPersonName = (item) => {
    const person = item.user?.person;

    if (person?.namalengkap) return person.namalengkap;
    if (item.user?.namalengkap) return item.user.namalengkap;

    return item.user?.username || "-";
};

const getSkemaName = (item) => item.periode_skema?.skema?.skema || "-";

const formatDateTime = (date) =>
    date
        ? new Date(date).toLocaleString("id-ID", {
              day: "2-digit",
              month: "short",
              year: "numeric",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "-";

const statusLabel = (status) =>
    statusMeta.find((item) => item.key === status)?.label || status || "-";

const statusBadge = (status) =>
    ({
        draft: "bg-slate-100 text-slate-600",
        menunggu_review: "bg-amber-50 text-amber-700",
        diterima: "bg-[#eaf2ee] text-[#2d4a3e]",
        perlu_revisi: "bg-blue-50 text-blue-700",
        ditolak: "bg-red-50 text-red-600",
    })[status] || "bg-slate-100 text-slate-600";

onMounted(fetchDashboard);
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-[#1e3329]">Dashboard</h2>
                <p class="text-[#7aab95] text-sm mt-1">
                    Ringkasan aktivitas sertifikasi dan pengajuan APL.01
                </p>
            </div>

            <button
                @click="fetchDashboard"
                class="inline-flex items-center gap-2 border border-[#c8ddd6] bg-white hover:bg-[#f7faf8] text-[#2d4a3e] text-sm font-semibold px-4 py-2 rounded-lg transition-all"
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
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                    />
                </svg>
                Refresh
            </button>
        </div>

        <div
            v-if="error"
            class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-5 py-4 text-sm mb-5"
        >
            {{ error }}
        </div>

        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg
                class="w-6 h-6 animate-spin text-[#4a7c6b]"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                ></path>
            </svg>
        </div>

        <div v-else class="space-y-5">
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <div
                    v-for="card in cards"
                    :key="card.label"
                    class="bg-white border border-[#dde8e3] rounded-xl px-5 py-4 shadow-sm"
                >
                    <p class="text-xs text-[#7aab95] font-semibold">
                        {{ card.label }}
                    </p>
                    <p :class="card.class" class="text-2xl font-bold mt-2">
                        {{ card.value }}
                    </p>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ card.hint }}
                    </p>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-3">
                <section
                    class="xl:col-span-2 bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-sm font-bold text-[#1e3329]">
                                Status Pengajuan APL.01
                            </h3>
                            <p class="text-xs text-[#7aab95] mt-0.5">
                                Total {{ totalPengajuan }} pengajuan
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="item in statusChart"
                            :key="item.key"
                            class="grid grid-cols-[150px_1fr_42px] items-center gap-3"
                        >
                            <p class="text-xs font-semibold text-[#3d6355]">
                                {{ item.label }}
                            </p>
                            <div
                                class="h-3 rounded-full bg-[#f0f4f1] overflow-hidden"
                            >
                                <div
                                    :class="item.color"
                                    :style="{ width: item.width }"
                                    class="h-full rounded-full transition-all"
                                ></div>
                            </div>
                            <p
                                :class="item.text"
                                class="text-sm font-bold text-right"
                            >
                                {{ item.count }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
                >
                    <h3 class="text-sm font-bold text-[#1e3329]">
                        Ketersediaan Skema
                    </h3>
                    <p class="text-xs text-[#7aab95] mt-0.5 mb-5">
                        Status aktif skema sertifikasi
                    </p>

                    <div
                        class="h-5 rounded-full bg-[#f0f4f1] overflow-hidden flex mb-4"
                    >
                        <div
                            v-for="item in skemaChart"
                            :key="item.label"
                            :class="item.class"
                            :style="{ width: item.width }"
                            class="h-full"
                        ></div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="item in skemaChart"
                            :key="item.label"
                            class="flex items-center justify-between"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    :class="item.class"
                                    class="w-2.5 h-2.5 rounded-full"
                                ></span>
                                <span class="text-sm text-[#1e3329]">
                                    {{ item.label }}
                                </span>
                            </div>
                            <span class="text-sm font-bold text-[#1e3329]">
                                {{ item.count }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="grid gap-5 xl:grid-cols-3">
                <section
                    class="xl:col-span-2 bg-white border border-[#dde8e3] rounded-xl overflow-hidden shadow-sm"
                >
                    <div
                        class="flex items-center justify-between px-5 py-4 border-b border-[#dde8e3]"
                    >
                        <div>
                            <h3 class="text-sm font-bold text-[#1e3329]">
                                Pengajuan Terbaru
                            </h3>
                            <p class="text-xs text-[#7aab95] mt-0.5">
                                Aktivitas APL.01 terakhir
                            </p>
                        </div>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-[#f0f4f1]">
                                <th
                                    class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                >
                                    Mahasiswa
                                </th>
                                <th
                                    class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                >
                                    Skema
                                </th>
                                <th
                                    class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                >
                                    Status
                                </th>
                                <th
                                    class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                >
                                    Waktu
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="recentPengajuan.length === 0">
                                <td
                                    colspan="4"
                                    class="text-center py-10 text-[#7aab95]"
                                >
                                    Belum ada pengajuan
                                </td>
                            </tr>
                            <tr
                                v-for="item in recentPengajuan"
                                :key="item.kdlsp_apl01_pengajuan"
                                class="border-t border-[#f0f4f1]"
                            >
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-[#1e3329]">
                                        {{ getPersonName(item) }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ item.user?.username || "-" }}
                                    </p>
                                </td>
                                <td class="px-5 py-3.5 text-[#1e3329]">
                                    {{ getSkemaName(item) }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span
                                        :class="statusBadge(item.status)"
                                        class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold"
                                    >
                                        {{ statusLabel(item.status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-xs text-slate-400">
                                    {{
                                        formatDateTime(
                                            item.submitted_at ||
                                                item.created_at,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section
                    class="bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
                >
                    <h3 class="text-sm font-bold text-[#1e3329]">
                        Ringkasan Master Data
                    </h3>
                    <p class="text-xs text-[#7aab95] mt-0.5 mb-4">
                        Kondisi data sertifikasi
                    </p>

                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Periode</span>
                            <span class="font-bold text-[#1e3329]">
                                {{ periode.length }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Skema</span>
                            <span class="font-bold text-[#1e3329]">
                                {{ skema.length }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Plotting Skema</span>
                            <span class="font-bold text-[#1e3329]">
                                {{ plotting.length }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Unit Kompetensi</span>
                            <span class="font-bold text-[#1e3329]">
                                {{ unitKompetensi.length }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
