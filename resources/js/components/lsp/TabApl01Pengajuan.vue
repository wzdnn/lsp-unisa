<script setup>
import { computed, onMounted, ref } from "vue";
import {
    apl01PengajuanService,
    apl01DokumenService,
} from "../../services/lspService";
import BaseModal from "../BaseModal.vue";

const emit = defineEmits(["toast"]);

const list = ref([]);
const loading = ref(false);
const saving = ref(false);
const modal = ref(false);
const selected = ref(null);
const filterStatus = ref("");
const search = ref("");
const reviewForm = ref({
    status: "diterima",
    catatan_admin: "",
});

const statusOptions = [
    { value: "", label: "Semua Status" },
    { value: "draft", label: "Draft" },
    { value: "menunggu_review", label: "Menunggu Review" },
    { value: "diterima", label: "Diterima" },
    { value: "perlu_revisi", label: "Perlu Revisi" },
    { value: "ditolak", label: "Ditolak" },
];

const statusMeta = {
    draft: {
        label: "Draft",
        class: "bg-slate-100 text-slate-600",
    },
    menunggu_review: {
        label: "Menunggu Review",
        class: "bg-amber-50 text-amber-700",
    },
    diterima: {
        label: "Diterima",
        class: "bg-[#eaf2ee] text-[#2d4a3e]",
    },
    perlu_revisi: {
        label: "Perlu Revisi",
        class: "bg-blue-50 text-blue-700",
    },
    ditolak: {
        label: "Ditolak",
        class: "bg-red-50 text-red-600",
    },
};

const fetch = async () => {
    loading.value = true;

    try {
        const res = await apl01PengajuanService.getAll();
        list.value = Array.isArray(res.data) ? res.data : [];
    } catch (err) {
        emit("toast", {
            message:
                err.response?.data?.message || "Gagal memuat pengajuan APL.01",
            type: "error",
        });
    } finally {
        loading.value = false;
    }
};

const updatingDokumen = ref(null);

const getFileUrl = (filePath) => (filePath ? `/storage/${filePath}` : null);

const dokumenStatusMeta = {
    menunggu: { label: "Menunggu", class: "bg-slate-100 text-slate-500" },
    memenuhi: {
        label: "Memenuhi Syarat",
        class: "bg-[#eaf2ee] text-[#2d4a3e]",
    },
    tidak_memenuhi: {
        label: "Tidak Memenuhi",
        class: "bg-red-50 text-red-600",
    },
};

const updateStatusDokumen = async (dokumen, status) => {
    if (!selected.value) return;
    updatingDokumen.value = dokumen.kdlsp_apl01_dokumen;

    try {
        const res = await apl01DokumenService.updateStatus(
            selected.value.kdlsp_apl01_pengajuan,
            dokumen.kdlsp_apl01_dokumen,
            status,
        );
        // update lokal tanpa refetch
        const idx = selected.value.dokumen.findIndex(
            (d) => d.kdlsp_apl01_dokumen === dokumen.kdlsp_apl01_dokumen,
        );
        if (idx >= 0) selected.value.dokumen[idx] = res.data;
        emit("toast", { message: "Status dokumen diperbarui" });
    } catch (err) {
        emit("toast", {
            message:
                err.response?.data?.message || "Gagal update status dokumen",
            type: "error",
        });
    } finally {
        updatingDokumen.value = null;
    }
};

// helper untuk grouping dokumen per bagian
const persyaratanDasarKeys = ["khs", "magang", "sertifikat_pelatihan"];
const persyaratanAdminKeys = ["ktp", "ktm"];

const getDokumenByJenis = (jenis) =>
    selected.value?.dokumen?.find((d) => d.jenis_dokumen === jenis) || null;

const getPersonName = (item) => {
    const person = item.user?.person;

    if (person?.namalengkap) return person.namalengkap;
    if (item.user?.namalengkap) return item.user.namalengkap;

    return item.user?.username || "-";
};

const getSkemaName = (item) => item.periode_skema?.skema?.skema || "-";

const getSkemaNumber = (item) =>
    item.data_sertifikasi?.nomor_skema ||
    item.periode_skema?.skema?.no_skema ||
    "-";

const getPeriodeName = (item) => item.periode_skema?.periode?.periode || "-";

const formatDate = (date) =>
    date
        ? new Date(date).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          })
        : "-";

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

const getMasaName = (item) => {
    const masa = item.periode_skema?.masa_periode;

    if (!masa) return "-";

    return `${formatDate(masa.tanggal_mulai)} - ${formatDate(
        masa.tanggal_selesai,
    )}`;
};

const badgeClass = (status) =>
    statusMeta[status]?.class || "bg-slate-100 text-slate-600";

const statusLabel = (status) => statusMeta[status]?.label || status || "-";

const stats = computed(() => ({
    total: list.value.length,
    menunggu: list.value.filter((item) => item.status === "menunggu_review")
        .length,
    diterima: list.value.filter((item) => item.status === "diterima").length,
    revisi: list.value.filter((item) => item.status === "perlu_revisi").length,
}));

const filtered = computed(() => {
    const keyword = search.value.toLowerCase().trim();

    return list.value.filter((item) => {
        const matchStatus =
            !filterStatus.value || item.status === filterStatus.value;
        const haystack = [
            getPersonName(item),
            item.user?.username,
            getSkemaName(item),
            getSkemaNumber(item),
            getPeriodeName(item),
        ]
            .join(" ")
            .toLowerCase();

        return matchStatus && (!keyword || haystack.includes(keyword));
    });
});

const openDetail = async (item) => {
    modal.value = true;
    selected.value = item;
    reviewForm.value = {
        status:
            item.status === "ditolak" || item.status === "perlu_revisi"
                ? item.status
                : "diterima",
        catatan_admin: item.catatan_admin || "",
    };

    try {
        const res = await apl01PengajuanService.getOne(
            item.kdlsp_apl01_pengajuan,
        );
        selected.value = res.data;
        reviewForm.value.catatan_admin = res.data.catatan_admin || "";
    } catch {
        emit("toast", {
            message: "Gagal memuat detail pengajuan",
            type: "error",
        });
    }
};

const submitReview = async (status = reviewForm.value.status) => {
    if (!selected.value) return;

    saving.value = true;

    try {
        const res = await apl01PengajuanService.review(
            selected.value.kdlsp_apl01_pengajuan,
            {
                status,
                catatan_admin: reviewForm.value.catatan_admin,
            },
        );
        selected.value = res.data;
        emit("toast", { message: "Review pengajuan berhasil disimpan" });
        await fetch();
    } catch (err) {
        emit("toast", {
            message:
                err.response?.data?.message || "Gagal menyimpan review admin",
            type: "error",
        });
    } finally {
        saving.value = false;
    }
};

const sectionRows = (section = {}) =>
    Object.entries(section).filter(
        ([, value]) => value !== null && value !== "",
    );

onMounted(fetch);
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h3 class="text-sm font-semibold text-[#1e3329]">
                    Pengajuan APL.01
                </h3>
                <p class="text-xs text-[#7aab95] mt-0.5">
                    Review permohonan sertifikasi mahasiswa
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <svg
                        class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari mahasiswa / skema..."
                        class="pl-9 pr-4 py-2 text-xs border border-[#c8ddd6] rounded-lg text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] bg-white w-56"
                    />
                </div>

                <select
                    v-model="filterStatus"
                    class="border border-[#c8ddd6] rounded-lg px-3 py-2 text-xs text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] bg-white"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <button
                    @click="fetch"
                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#c8ddd6] text-[#4a7c6b] hover:bg-[#eaf2ee] transition-all"
                >
                    <svg
                        class="w-3.5 h-3.5"
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
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-[#1e3329]">
                    {{ stats.total }}
                </p>
                <p class="text-xs text-[#7aab95]">Total Pengajuan</p>
            </div>
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-amber-700">
                    {{ stats.menunggu }}
                </p>
                <p class="text-xs text-[#7aab95]">Menunggu Review</p>
            </div>
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-[#2d4a3e]">
                    {{ stats.diterima }}
                </p>
                <p class="text-xs text-[#7aab95]">Diterima</p>
            </div>
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-blue-700">
                    {{ stats.revisi }}
                </p>
                <p class="text-xs text-[#7aab95]">Perlu Revisi</p>
            </div>
        </div>

        <div
            class="bg-white rounded-xl border border-[#dde8e3] overflow-hidden"
        >
            <div v-if="loading" class="flex items-center justify-center py-16">
                <svg
                    class="w-5 h-5 animate-spin text-[#4a7c6b]"
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

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="bg-[#f0f4f1] border-b border-[#dde8e3]">
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
                            Periode
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Status
                        </th>
                        <th
                            class="text-right px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="filtered.length === 0">
                        <td
                            colspan="5"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Belum ada pengajuan APL.01
                        </td>
                    </tr>
                    <tr
                        v-for="item in filtered"
                        :key="item.kdlsp_apl01_pengajuan"
                        class="border-b border-[#f0f4f1] hover:bg-[#f9fbfa] transition-colors"
                    >
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-[#1e3329]">
                                {{ getPersonName(item) }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ item.user?.username || "-" }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-[#1e3329]">
                                {{ getSkemaName(item) }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ getSkemaNumber(item) }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-[#1e3329]">
                                {{ getPeriodeName(item) }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ getMasaName(item) }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span
                                :class="badgeClass(item.status)"
                                class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold"
                            >
                                {{ statusLabel(item.status) }}
                            </span>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ formatDateTime(item.submitted_at) }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button
                                @click="openDetail(item)"
                                class="px-3 py-1.5 text-xs text-[#4a7c6b] bg-[#eaf2ee] hover:bg-[#d4e8dd] rounded-lg transition-all font-medium"
                            >
                                Detail
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal
            :show="modal"
            title="Detail Pengajuan APL.01"
            size="xl"
            @close="modal = false"
        >
            <div v-if="selected" class="space-y-5 max-h-[75vh] overflow-y-auto">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p
                            class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                        >
                            Mahasiswa
                        </p>
                        <p class="text-sm font-semibold text-[#1e3329] mt-1">
                            {{ getPersonName(selected) }}
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ selected.user?.username || "-" }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                        >
                            Status
                        </p>
                        <span
                            :class="badgeClass(selected.status)"
                            class="inline-flex mt-1 px-2.5 py-1 rounded-full text-xs font-semibold"
                        >
                            {{ statusLabel(selected.status) }}
                        </span>
                    </div>
                    <div>
                        <p
                            class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                        >
                            Skema
                        </p>
                        <p class="text-sm font-semibold text-[#1e3329] mt-1">
                            {{ getSkemaName(selected) }}
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ getSkemaNumber(selected) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                        >
                            Periode & Masa
                        </p>
                        <p class="text-sm font-semibold text-[#1e3329] mt-1">
                            {{ getPeriodeName(selected) }}
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ getMasaName(selected) }}
                        </p>
                    </div>
                </div>

                <div
                    v-for="section in [
                        {
                            title: 'Data Pribadi',
                            data: selected.data_pribadi,
                        },
                        {
                            title: 'Data Pekerjaan',
                            data: selected.data_pekerjaan,
                        },
                        {
                            title: 'Data Sertifikasi',
                            data: selected.data_sertifikasi,
                        },
                    ]"
                    :key="section.title"
                    class="border border-[#dde8e3] rounded-xl overflow-hidden"
                >
                    <div class="bg-[#f0f4f1] px-4 py-3">
                        <h4 class="text-xs font-bold text-[#2d4a3e] uppercase">
                            {{ section.title }}
                        </h4>
                    </div>
                    <div class="divide-y divide-[#f0f4f1]">
                        <div
                            v-for="[key, value] in sectionRows(section.data)"
                            :key="key"
                            class="grid gap-1 md:grid-cols-3 px-4 py-2.5 text-sm"
                        >
                            <p class="text-xs font-semibold text-[#7aab95]">
                                {{ key.replaceAll("_", " ") }}
                            </p>
                            <p class="md:col-span-2 text-[#1e3329]">
                                {{ value || "-" }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Bagian 3 — Bukti Kelengkapan -->
                <div class="border border-[#dde8e3] rounded-xl overflow-hidden">
                    <div class="bg-[#f0f4f1] px-4 py-3">
                        <h4 class="text-xs font-bold text-[#2d4a3e] uppercase">
                            Bagian 3 — Bukti Kelengkapan Pemohon
                        </h4>
                    </div>

                    <!-- 3.1 -->
                    <div class="px-4 py-3 border-b border-[#f0f4f1]">
                        <p
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-3"
                        >
                            3.1 Bukti Persyaratan Dasar
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="item in selected.data_persyaratan
                                    ?.bagian_3_1 || []"
                                :key="item.jenis_dokumen"
                                class="flex items-start justify-between gap-3 border border-[#f0f4f1] rounded-lg px-3 py-2.5"
                            >
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-xs text-[#1e3329] leading-relaxed"
                                    >
                                        <span class="font-semibold"
                                            >{{ item.no }}.</span
                                        >
                                        {{ item.label }}
                                    </p>
                                    <!-- file link jika ada -->
                                    <a
                                        v-if="
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            )
                                        "
                                        :href="
                                            getFileUrl(
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).file_path,
                                            )
                                        "
                                        target="_blank"
                                        class="inline-flex items-center gap-1 mt-1.5 text-xs text-[#4a7c6b] hover:underline font-medium"
                                    >
                                        <svg
                                            class="w-3 h-3 shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                            />
                                        </svg>
                                        {{
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            ).original_name
                                        }}
                                    </a>
                                    <p
                                        v-else
                                        class="text-xs text-slate-400 italic mt-1"
                                    >
                                        Belum diunggah mahasiswa
                                    </p>
                                </div>

                                <!-- checklist status — hanya tampil jika ada file -->
                                <div
                                    v-if="getDokumenByJenis(item.jenis_dokumen)"
                                    class="flex items-center gap-1.5 shrink-0"
                                >
                                    <span
                                        :class="
                                            dokumenStatusMeta[
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status
                                            ]?.class
                                        "
                                        class="text-xs font-semibold px-2 py-1 rounded-full"
                                    >
                                        {{
                                            dokumenStatusMeta[
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status
                                            ]?.label
                                        }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="
                                            updateStatusDokumen(
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ),
                                                'memenuhi',
                                            )
                                        "
                                        :disabled="
                                            updatingDokumen ===
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            ).kdlsp_apl01_dokumen
                                        "
                                        title="Memenuhi syarat"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-[#c8ddd6] hover:bg-[#eaf2ee] disabled:opacity-50 transition-all"
                                        :class="{
                                            'bg-[#eaf2ee] border-[#4a7c6b]':
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status === 'memenuhi',
                                        }"
                                    >
                                        <svg
                                            class="w-3.5 h-3.5 text-[#2d4a3e]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2.5"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            updateStatusDokumen(
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ),
                                                'tidak_memenuhi',
                                            )
                                        "
                                        :disabled="
                                            updatingDokumen ===
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            ).kdlsp_apl01_dokumen
                                        "
                                        title="Tidak memenuhi syarat"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-[#c8ddd6] hover:bg-red-50 disabled:opacity-50 transition-all"
                                        :class="{
                                            'bg-red-50 border-red-300':
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status === 'tidak_memenuhi',
                                        }"
                                    >
                                        <svg
                                            class="w-3.5 h-3.5 text-red-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2.5"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-slate-300 shrink-0"
                                    >—</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- 3.2 -->
                    <div class="px-4 py-3">
                        <p
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-3"
                        >
                            3.2 Bukti Administratif
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="item in selected.data_persyaratan
                                    ?.bagian_3_2 || []"
                                :key="item.jenis_dokumen"
                                class="flex items-start justify-between gap-3 border border-[#f0f4f1] rounded-lg px-3 py-2.5"
                            >
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-xs text-[#1e3329] leading-relaxed"
                                    >
                                        <span class="font-semibold"
                                            >{{ item.no }}.</span
                                        >
                                        {{ item.label }}
                                    </p>
                                    <a
                                        v-if="
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            )
                                        "
                                        :href="
                                            getFileUrl(
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).file_path,
                                            )
                                        "
                                        target="_blank"
                                        class="inline-flex items-center gap-1 mt-1.5 text-xs text-[#4a7c6b] hover:underline font-medium"
                                    >
                                        <svg
                                            class="w-3 h-3 shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                            />
                                        </svg>
                                        {{
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            ).original_name
                                        }}
                                    </a>
                                    <p
                                        v-else
                                        class="text-xs text-slate-400 italic mt-1"
                                    >
                                        Belum diunggah / tidak diperlukan
                                    </p>
                                </div>

                                <div
                                    v-if="getDokumenByJenis(item.jenis_dokumen)"
                                    class="flex items-center gap-1.5 shrink-0"
                                >
                                    <span
                                        :class="
                                            dokumenStatusMeta[
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status
                                            ]?.class
                                        "
                                        class="text-xs font-semibold px-2 py-1 rounded-full"
                                    >
                                        {{
                                            dokumenStatusMeta[
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status
                                            ]?.label
                                        }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="
                                            updateStatusDokumen(
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ),
                                                'memenuhi',
                                            )
                                        "
                                        :disabled="
                                            updatingDokumen ===
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            ).kdlsp_apl01_dokumen
                                        "
                                        title="Memenuhi syarat"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-[#c8ddd6] hover:bg-[#eaf2ee] disabled:opacity-50 transition-all"
                                        :class="{
                                            'bg-[#eaf2ee] border-[#4a7c6b]':
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status === 'memenuhi',
                                        }"
                                    >
                                        <svg
                                            class="w-3.5 h-3.5 text-[#2d4a3e]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2.5"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            updateStatusDokumen(
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ),
                                                'tidak_memenuhi',
                                            )
                                        "
                                        :disabled="
                                            updatingDokumen ===
                                            getDokumenByJenis(
                                                item.jenis_dokumen,
                                            ).kdlsp_apl01_dokumen
                                        "
                                        title="Tidak memenuhi syarat"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-[#c8ddd6] hover:bg-red-50 disabled:opacity-50 transition-all"
                                        :class="{
                                            'bg-red-50 border-red-300':
                                                getDokumenByJenis(
                                                    item.jenis_dokumen,
                                                ).status === 'tidak_memenuhi',
                                        }"
                                    >
                                        <svg
                                            class="w-3.5 h-3.5 text-red-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2.5"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-slate-300 shrink-0"
                                    >—</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border border-[#dde8e3] rounded-xl p-4 space-y-3">
                    <div>
                        <label
                            class="block text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-1.5"
                        >
                            Catatan Admin
                        </label>
                        <textarea
                            v-model="reviewForm.catatan_admin"
                            rows="3"
                            class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                            placeholder="Tambahkan catatan jika perlu"
                        ></textarea>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button
                            @click="submitReview('perlu_revisi')"
                            :disabled="saving"
                            class="px-4 py-2 text-xs font-semibold bg-blue-50 hover:bg-blue-100 disabled:opacity-50 text-blue-700 rounded-lg transition-all"
                        >
                            Perlu Revisi
                        </button>
                        <button
                            @click="submitReview('ditolak')"
                            :disabled="saving"
                            class="px-4 py-2 text-xs font-semibold bg-red-50 hover:bg-red-100 disabled:opacity-50 text-red-600 rounded-lg transition-all"
                        >
                            Tolak
                        </button>
                        <button
                            @click="submitReview('diterima')"
                            :disabled="saving"
                            class="px-5 py-2 text-xs font-semibold bg-[#2d4a3e] hover:bg-[#3d6355] disabled:opacity-50 text-white rounded-lg transition-all"
                        >
                            Terima
                        </button>
                    </div>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
