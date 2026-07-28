<script setup>
import { ref, onMounted, computed } from "vue";
import { skemaService, unitKompetensiService } from "../../services/lspService";
import BaseModal from "../BaseModal.vue";

const emit = defineEmits(["toast"]);

// ─── State ────────────────────────────────────────────────────
const skemaList = ref([]);
const list = ref([]);
const selectedSkema = ref("");
const loading = ref(false);
const modal = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const error = ref("");
const editId = ref(null);
const selectedIds = ref([]);

const form = ref({
    kdlsp_skema: "",
    kode_unit: "",
    judul_unit: "",
    standar_kompetensi_kerja: "",
});

// ─── Computed ─────────────────────────────────────────────────
const selectedSkemaLabel = computed(() => {
    const s = skemaList.value.find(
        (s) => s.kdlsp_skema === selectedSkema.value,
    );
    return s ? s.skema : "";
});

const isFormValid = computed(
    () =>
        form.value.kdlsp_skema &&
        form.value.kode_unit.trim() &&
        form.value.judul_unit.trim() &&
        form.value.standar_kompetensi_kerja.trim(),
);

// ─── Fetch ────────────────────────────────────────────────────
const fetchSkema = async () => {
    try {
        const res = await skemaService.getAll();
        skemaList.value = res.data.filter((s) => s.isActive);
        if (skemaList.value.length > 0) {
            selectedSkema.value = skemaList.value[0].kdlsp_skema;
            await fetchList();
        }
    } catch {
        emit("toast", { message: "Gagal memuat daftar skema", type: "error" });
    }
};

const fetchList = async () => {
    if (!selectedSkema.value) return;
    loading.value = true;
    selectedIds.value = [];
    try {
        const res = await unitKompetensiService.getAll({
            kdlsp_skema: selectedSkema.value,
        });
        list.value = res.data;
    } catch {
        emit("toast", {
            message: "Gagal memuat unit kompetensi",
            type: "error",
        });
    } finally {
        loading.value = false;
    }
};

const onSkemaChange = () => fetchList();

// ─── Modal: Buat ──────────────────────────────────────────────
const openCreate = () => {
    if (!selectedSkema.value) {
        emit("toast", {
            message: "Pilih skema terlebih dahulu",
            type: "error",
        });
        return;
    }
    form.value = {
        kdlsp_skema: selectedSkema.value,
        kode_unit: "",
        judul_unit: "",
        standar_kompetensi_kerja: "",
    };
    isEdit.value = false;
    editId.value = null;
    error.value = "";
    modal.value = true;
};

// ─── Modal: Edit ──────────────────────────────────────────────
const openEdit = (item) => {
    form.value = {
        kdlsp_skema: item.kdlsp_skema,
        kode_unit: item.kode_unit,
        judul_unit: item.judul_unit,
        standar_kompetensi_kerja: item.standar_kompetensi_kerja,
    };
    isEdit.value = true;
    editId.value = item.kdlsp_skema_unitkompetensi;
    error.value = "";
    modal.value = true;
};

// ─── Submit ───────────────────────────────────────────────────
const submit = async () => {
    error.value = "";
    saving.value = true;
    try {
        if (isEdit.value) {
            await unitKompetensiService.update(editId.value, form.value);
            emit("toast", { message: "Unit kompetensi berhasil diperbarui" });
        } else {
            await unitKompetensiService.create(form.value);
            emit("toast", { message: "Unit kompetensi berhasil ditambahkan" });
        }
        modal.value = false;
        fetchList();
    } catch (err) {
        error.value = err.response?.data?.message || "Terjadi kesalahan";
    } finally {
        saving.value = false;
    }
};

// ─── Hapus ────────────────────────────────────────────────────
const destroy = async (item) => {
    if (!confirm(`Hapus unit "${item.kode_unit} - ${item.judul_unit}"?`))
        return;
    try {
        await unitKompetensiService.destroy(item.kdlsp_skema_unitkompetensi);
        emit("toast", { message: "Unit kompetensi berhasil dihapus" });
        fetchList();
    } catch {
        emit("toast", {
            message: "Gagal menghapus unit kompetensi",
            type: "error",
        });
    }
};

const bulkDestroy = async () => {
    if (!selectedIds.value.length) return;
    if (
        !confirm(
            `Hapus ${selectedIds.value.length} unit kompetensi yang dipilih?`,
        )
    )
        return;
    try {
        await unitKompetensiService.bulkDestroy(selectedIds.value);
        emit("toast", { message: "Unit kompetensi berhasil dihapus" });
        selectedIds.value = [];
        fetchList();
    } catch {
        emit("toast", {
            message: "Gagal menghapus unit kompetensi",
            type: "error",
        });
    }
};

// ─── Select ───────────────────────────────────────────────────
const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
};

const toggleSelectAll = () => {
    if (selectedIds.value.length === list.value.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = list.value.map((i) => i.kdlsp_skema_unitkompetensi);
    }
};

// ─── Lifecycle ────────────────────────────────────────────────
onMounted(fetchSkema);
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h3 class="text-sm font-semibold text-[#1e3329]">
                    Unit Kompetensi Skema
                </h3>
                <p class="text-xs text-[#7aab95] mt-0.5">
                    Kelola unit kompetensi untuk setiap skema sertifikasi
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Filter Skema -->
                <select
                    v-model="selectedSkema"
                    @change="onSkemaChange"
                    class="border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] bg-white"
                >
                    <option value="" disabled>Pilih Skema</option>
                    <option
                        v-for="s in skemaList"
                        :key="s.kdlsp_skema"
                        :value="s.kdlsp_skema"
                    >
                        {{ s.skema }}
                    </option>
                </select>

                <!-- Bulk delete -->
                <button
                    v-if="selectedIds.length > 0"
                    @click="bulkDestroy"
                    class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-4 py-2 rounded-lg transition-all"
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
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                        />
                    </svg>
                    Hapus ({{ selectedIds.length }})
                </button>

                <!-- Tambah -->
                <button
                    @click="openCreate"
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
                    Tambah Unit
                </button>
            </div>
        </div>

        <!-- Badge info skema aktif -->
        <div
            v-if="selectedSkemaLabel"
            class="mb-4 inline-flex items-center gap-2 bg-[#eaf2ee] border border-[#c8ddd6] text-[#2d4a3e] text-xs font-medium px-3 py-1.5 rounded-full"
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
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
            </svg>
            {{ selectedSkemaLabel }}
            <span class="text-[#7aab95]">·</span>
            <span class="text-[#7aab95]">{{ list.length }} unit</span>
        </div>

        <!-- Table -->
        <div
            class="bg-white rounded-xl border border-[#dde8e3] overflow-hidden"
        >
            <!-- Loading -->
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
                                    selectedIds.length === list.length &&
                                    list.length > 0
                                "
                                class="rounded border-[#c8ddd6] accent-[#2d4a3e]"
                            />
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-12"
                        >
                            No
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Kode Unit
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Judul Unit Kompetensi
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Standar Kompetensi Kerja
                        </th>
                        <th
                            class="text-right px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Belum pilih skema -->
                    <tr v-if="!selectedSkema">
                        <td
                            colspan="5"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Pilih skema untuk melihat unit kompetensi
                        </td>
                    </tr>

                    <!-- Kosong -->
                    <tr v-else-if="list.length === 0">
                        <td
                            colspan="5"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Belum ada unit kompetensi untuk skema ini
                        </td>
                    </tr>

                    <!-- Data -->
                    <tr
                        v-for="(item, i) in list"
                        :key="item.kdlsp_skema_unitkompetensi"
                        class="border-b border-[#f0f4f1] hover:bg-[#f9fbfa] transition-colors"
                        :class="{
                            'bg-[#eaf2ee]/50': selectedIds.includes(
                                item.kdlsp_skema_unitkompetensi,
                            ),
                        }"
                    >
                        <td class="px-5 py-3.5">
                            <input
                                type="checkbox"
                                :checked="
                                    selectedIds.includes(
                                        item.kdlsp_skema_unitkompetensi,
                                    )
                                "
                                @change="
                                    toggleSelect(
                                        item.kdlsp_skema_unitkompetensi,
                                    )
                                "
                                class="rounded border-[#c8ddd6] accent-[#2d4a3e]"
                            />
                        </td>
                        <td class="px-5 py-3.5 text-slate-400 text-xs">
                            {{ i + 1 }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span
                                class="inline-flex items-center px-2.5 py-1 bg-[#eaf2ee] text-[#2d4a3e] text-xs font-mono font-semibold rounded-lg"
                            >
                                {{ item.kode_unit }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-[#1e3329]">
                            {{ item.judul_unit }}
                        </td>
                        <td class="px-5 py-3.5 font-medium text-[#1e3329]">
                            {{ item.standar_kompetensi_kerja }}
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    @click="openEdit(item)"
                                    class="px-3 py-1.5 text-xs text-[#4a7c6b] bg-[#eaf2ee] hover:bg-[#d4e8dd] rounded-lg transition-all font-medium"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="destroy(item)"
                                    class="px-3 py-1.5 text-xs text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-all font-medium"
                                >
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Tambah / Edit -->
        <BaseModal
            :show="modal"
            :title="isEdit ? 'Edit Unit Kompetensi' : 'Tambah Unit Kompetensi'"
            size="lg"
            @close="modal = false"
        >
            <div class="space-y-4">
                <!-- Pilih Skema -->
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                    >
                        Skema
                    </label>
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

                <!-- Kode Unit -->
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                    >
                        Kode Unit
                    </label>
                    <input
                        v-model="form.kode_unit"
                        type="text"
                        placeholder="cth: J.620100.001.01"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] font-mono placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                    />
                </div>

                <!-- Judul Unit -->
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                    >
                        Judul Unit Kompetensi
                    </label>
                    <input
                        v-model="form.judul_unit"
                        type="text"
                        placeholder="cth: Menerapkan Konsep Pemrograman Berorientasi Objek"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                        @keyup.enter="submit"
                    />
                </div>

                <!-- Standar Kompetensi Kerja -->
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                    >
                        Standar Kompetensi Kerja
                    </label>
                    <input
                        v-model="form.standar_kompetensi_kerja"
                        type="text"
                        placeholder="cth: SKKNI No 39 Tahun..."
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                        @keyup.enter="submit"
                    />
                </div>

                <!-- Error -->
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

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-1">
                    <button
                        @click="modal = false"
                        class="px-4 py-2 text-xs font-medium text-slate-500 hover:bg-slate-100 rounded-lg transition-all"
                    >
                        Batal
                    </button>
                    <button
                        @click="submit"
                        :disabled="saving || !isFormValid"
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
                        {{
                            saving
                                ? "Menyimpan..."
                                : isEdit
                                  ? "Perbarui"
                                  : "Simpan"
                        }}
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
