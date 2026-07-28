<script setup>
import { ref, onMounted, watch, computed } from "vue";
import {
    periodeService,
    masaService,
    skemaService,
    plottingService,
} from "../../services/lspService";
import BaseModal from "../BaseModal.vue";

const emit = defineEmits(["toast"]);

const periodeList = ref([]);
const masaList = ref([]);
const skemaList = ref([]);
const plottingList = ref([]);
const selectedPeriode = ref("");
const loading = ref(false);
const modal = ref(false);
const saving = ref(false);
const error = ref("");
const selectedIds = ref([]);

const form = ref({
    kdlsp_periode: "",
    kdlsp_periode_masa: "",
    kdlsp_skema: "",
});

const fetchPeriode = async () => {
    const res = await periodeService.getAll();
    periodeList.value = res.data;
    if (res.data.length > 0) selectedPeriode.value = res.data[0].kdlsp_periode;
};

const fetchMasaForFilter = async () => {
    if (!selectedPeriode.value) return;
    const res = await masaService.getAll(selectedPeriode.value);
    masaList.value = res.data;
};

const fetchPlotting = async () => {
    if (!selectedPeriode.value) return;
    loading.value = true;
    try {
        const res = await plottingService.getAll({
            kdlsp_periode: selectedPeriode.value,
        });
        plottingList.value = res.data;
    } catch {
        emit("toast", { message: "Gagal memuat plotting", type: "error" });
    } finally {
        loading.value = false;
    }
};

watch(selectedPeriode, async () => {
    selectedIds.value = [];
    await fetchMasaForFilter();
    await fetchPlotting();
});

const openModal = async () => {
    if (!selectedPeriode.value) {
        emit("toast", {
            message: "Pilih periode terlebih dahulu",
            type: "error",
        });
        return;
    }
    form.value = {
        kdlsp_periode: selectedPeriode.value,
        kdlsp_periode_masa: "",
        kdlsp_skema: "",
    };
    error.value = "";

    const res = await skemaService.getAll();
    skemaList.value = res.data.filter((s) => s.isActive);

    modal.value = true;
};

const submit = async () => {
    error.value = "";
    saving.value = true;
    try {
        await plottingService.create(form.value);
        emit("toast", { message: "Skema berhasil diplot" });
        modal.value = false;
        fetchPlotting();
    } catch (err) {
        error.value = err.response?.data?.message || "Terjadi kesalahan";
    } finally {
        saving.value = false;
    }
};

const destroy = async (item) => {
    if (!confirm("Hapus plotting ini?")) return;
    try {
        await plottingService.destroy(item.kdlsp_periode_skema);
        emit("toast", { message: "Plotting berhasil dihapus" });
        fetchPlotting();
    } catch {
        emit("toast", { message: "Gagal menghapus plotting", type: "error" });
    }
};

const bulkDestroy = async () => {
    if (!selectedIds.value.length) return;
    if (!confirm(`Hapus ${selectedIds.value.length} plotting yang dipilih?`))
        return;
    try {
        await plottingService.bulkDestroy(selectedIds.value);
        emit("toast", { message: "Plotting berhasil dihapus" });
        selectedIds.value = [];
        fetchPlotting();
    } catch {
        emit("toast", { message: "Gagal menghapus plotting", type: "error" });
    }
};

const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
};

const toggleSelectAll = () => {
    if (selectedIds.value.length === plottingList.value.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = plottingList.value.map(
            (p) => p.kdlsp_periode_skema,
        );
    }
};

const formatDate = (d) =>
    d
        ? new Date(d).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          })
        : "-";

onMounted(fetchPeriode);
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h3 class="text-sm font-semibold text-[#1e3329]">
                    Plotting Skema
                </h3>
                <p class="text-xs text-[#7aab95] mt-0.5">
                    Plot skema ke periode dan masa tertentu
                </p>
            </div>
            <div class="flex items-center gap-3">
                <select
                    v-model="selectedPeriode"
                    class="border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] bg-white"
                >
                    <option value="" disabled>Pilih Periode</option>
                    <option
                        v-for="p in periodeList"
                        :key="p.kdlsp_periode"
                        :value="p.kdlsp_periode"
                    >
                        {{ p.periode }}
                    </option>
                </select>
                <button
                    v-if="selectedIds.length > 0"
                    @click="bulkDestroy"
                    class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-4 py-2 rounded-lg transition-all"
                >
                    Hapus ({{ selectedIds.length }})
                </button>
                <button
                    @click="openModal"
                    class="flex items-center gap-2 bg-[#2d4a3e] hover:bg-[#3d6355] text-white text-xs font-medium px-4 py-2 rounded-lg transition-all"
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Tambah Plotting
                </button>
            </div>
        </div>

        <!-- Table -->
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
                        <th class="px-5 py-3 w-10">
                            <input
                                type="checkbox"
                                @change="toggleSelectAll"
                                :checked="
                                    selectedIds.length ===
                                        plottingList.length &&
                                    plottingList.length > 0
                                "
                                class="rounded border-[#c8ddd6] accent-[#2d4a3e]"
                            />
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Skema
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Masa Periode
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
                    <tr v-if="!selectedPeriode">
                        <td
                            colspan="4"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Pilih periode untuk melihat plotting
                        </td>
                    </tr>
                    <tr v-else-if="plottingList.length === 0">
                        <td
                            colspan="4"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Belum ada plotting skema
                        </td>
                    </tr>
                    <tr
                        v-for="item in plottingList"
                        :key="item.kdlsp_periode_skema"
                        class="border-b border-[#f0f4f1] hover:bg-[#f9fbfa] transition-colors"
                        :class="{
                            'bg-[#eaf2ee]/50': selectedIds.includes(
                                item.kdlsp_periode_skema,
                            ),
                        }"
                    >
                        <td class="px-5 py-3.5">
                            <input
                                type="checkbox"
                                :checked="
                                    selectedIds.includes(
                                        item.kdlsp_periode_skema,
                                    )
                                "
                                @change="toggleSelect(item.kdlsp_periode_skema)"
                                class="rounded border-[#c8ddd6] accent-[#2d4a3e]"
                            />
                        </td>
                        <td class="px-5 py-3.5 font-medium text-[#1e3329]">
                            {{ item.skema?.skema }}
                        </td>
                        <td class="px-5 py-3.5 text-slate-500 text-xs">
                            {{ formatDate(item.masa_periode?.tanggal_mulai) }} —
                            {{ formatDate(item.masa_periode?.tanggal_selesai) }}
                        </td>
                        <td class="px-5 py-3.5">
                            <template
                                v-if="
                                    item.skema?.isActive &&
                                    item.masa_periode?.isActive
                                "
                            >
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#eaf2ee] text-[#2d4a3e] text-xs font-medium rounded-full"
                                >
                                    <span
                                        class="w-1.5 h-1.5 rounded-full bg-[#2d4a3e]"
                                    ></span>
                                    Aktif
                                </span>
                            </template>
                            <template v-else>
                                <div class="flex flex-col gap-1">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 text-xs font-medium rounded-full"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full bg-amber-500"
                                        ></span>
                                        Tidak Aktif
                                    </span>
                                    <!-- Detail penyebab -->
                                    <div class="flex flex-col gap-0.5 pl-1">
                                        <span
                                            v-if="!item.skema?.isActive"
                                            class="text-xs text-red-400"
                                        >
                                            ↳ Skema nonaktif
                                        </span>
                                        <span
                                            v-if="!item.masa_periode?.isActive"
                                            class="text-xs text-red-400"
                                        >
                                            ↳ Masa periode nonaktif
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button
                                @click="destroy(item)"
                                class="px-3 py-1.5 text-xs text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-all font-medium"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <BaseModal
            :show="modal"
            title="Tambah Plotting Skema"
            size="lg"
            @close="modal = false"
        >
            <div class="space-y-4">
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                        >Masa Periode</label
                    >
                    <select
                        v-model="form.kdlsp_periode_masa"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                    >
                        <option value="" disabled>Pilih masa periode</option>
                        <option
                            v-for="m in masaList"
                            :key="m.kdlsp_periode_masa"
                            :value="m.kdlsp_periode_masa"
                        >
                            {{ formatDate(m.tanggal_mulai) }} —
                            {{ formatDate(m.tanggal_selesai) }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                        >Skema</label
                    >
                    <select
                        v-model="form.kdlsp_skema"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                    >
                        <option value="" disabled>Pilih skema</option>
                        <option
                            v-for="s in skemaList"
                            :key="s.kdlsp_skema"
                            :value="s.kdlsp_skema"
                        >
                            {{ s.skema }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="error"
                    class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-600 rounded-xl px-4 py-3 text-xs"
                >
                    <svg
                        class="w-3.5 h-3.5 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                        />
                    </svg>
                    {{ error }}
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button
                        @click="modal = false"
                        class="px-4 py-2 text-xs font-medium text-slate-500 hover:bg-slate-100 rounded-lg transition-all"
                    >
                        Batal
                    </button>
                    <button
                        @click="submit"
                        :disabled="
                            saving ||
                            !form.kdlsp_periode_masa ||
                            !form.kdlsp_skema
                        "
                        class="px-5 py-2 text-xs font-semibold bg-[#2d4a3e] hover:bg-[#3d6355] disabled:opacity-50 text-white rounded-lg transition-all flex items-center gap-2"
                    >
                        <svg
                            v-if="saving"
                            class="w-3.5 h-3.5 animate-spin"
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
                        {{ saving ? "Menyimpan..." : "Plot Skema" }}
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
