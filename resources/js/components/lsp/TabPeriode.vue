<script setup>
import { ref, onMounted } from "vue";
import { periodeService } from "../../services/lspService";
import BaseModal from "../BaseModal.vue";

const emit = defineEmits(["toast"]);

const list = ref([]);
const loading = ref(false);
const modal = ref(false);
const isEdit = ref(false);
const error = ref("");
const saving = ref(false);
const form = ref({ periode: "" });
const editId = ref(null);

const fetch = async () => {
    loading.value = true;
    try {
        const res = await periodeService.getAll();
        list.value = res.data;
    } catch {
        emit("toast", { message: "Gagal memuat periode", type: "error" });
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    form.value = { periode: "" };
    isEdit.value = false;
    editId.value = null;
    error.value = "";
    modal.value = true;
};

const openEdit = (item) => {
    form.value = { periode: item.periode };
    isEdit.value = true;
    editId.value = item.kdlsp_periode;
    error.value = "";
    modal.value = true;
};

const submit = async () => {
    error.value = "";
    saving.value = true;
    try {
        if (isEdit.value) {
            await periodeService.update(editId.value, form.value);
            emit("toast", { message: "Periode berhasil diperbarui" });
        } else {
            await periodeService.create(form.value);
            emit("toast", { message: "Periode berhasil ditambahkan" });
        }
        modal.value = false;
        fetch();
    } catch (err) {
        error.value = err.response?.data?.message || "Terjadi kesalahan";
    } finally {
        saving.value = false;
    }
};

const destroy = async (item) => {
    if (
        !confirm(
            `Hapus periode "${item.periode}"? Semua data terkait akan ikut terhapus.`,
        )
    )
        return;
    try {
        await periodeService.destroy(item.kdlsp_periode);
        emit("toast", { message: "Periode berhasil dihapus" });
        fetch();
    } catch {
        emit("toast", { message: "Gagal menghapus periode", type: "error" });
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

onMounted(fetch);
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-sm font-semibold text-[#1e3329]">
                    Daftar Periode
                </h3>
                <p class="text-xs text-[#7aab95] mt-0.5">
                    Kelola periode sertifikasi LSP
                </p>
            </div>
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
                Tambah Periode
            </button>
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
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-12"
                        >
                            No
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Nama Periode
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Dibuat
                        </th>
                        <th
                            class="text-right px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="list.length === 0">
                        <td
                            colspan="4"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Belum ada data periode
                        </td>
                    </tr>
                    <tr
                        v-for="(item, i) in list"
                        :key="item.kdlsp_periode"
                        class="border-b border-[#f0f4f1] hover:bg-[#f9fbfa] transition-colors"
                    >
                        <td class="px-5 py-3.5 text-slate-400 text-xs">
                            {{ i + 1 }}
                        </td>
                        <td class="px-5 py-3.5 font-medium text-[#1e3329]">
                            {{ item.periode }}
                        </td>
                        <td class="px-5 py-3.5 text-slate-400 text-xs">
                            {{ formatDate(item.created_at) }}
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

        <!-- Modal -->
        <BaseModal
            :show="modal"
            :title="isEdit ? 'Edit Periode' : 'Tambah Periode'"
            @close="modal = false"
        >
            <div class="space-y-4">
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                    >
                        Nama Periode
                    </label>
                    <input
                        v-model="form.periode"
                        type="text"
                        placeholder="cth: Periode I 2025"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                        @keyup.enter="submit"
                    />
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
                        :disabled="saving || !form.periode.trim()"
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
