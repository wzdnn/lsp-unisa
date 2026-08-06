<script setup>
import { ref, onMounted } from "vue";
import BaseModal from "../BaseModal.vue";
import { skemaService, skemaTarifService } from "../../services/lspService";

const emit = defineEmits(["toast"]);

const list = ref([]);
const loading = ref(false);
const modal = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const toggling = ref(null);
const error = ref("");
const editId = ref(null);
const form = ref({ skema: "", no_skema: "", nominal: "" });

const fetch = async () => {
    loading.value = true;
    try {
        const res = await skemaService.getAll();
        list.value = res.data;
    } catch {
        emit("toast", { message: "Gagal memuat skema", type: "error" });
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    form.value = { skema: "", no_skema: "", nominal: "" };
    isEdit.value = false;
    editId.value = null;
    error.value = "";
    modal.value = true;
};

const openEdit = async (item) => {
    form.value = {
        skema: item.skema,
        no_skema: item.no_skema || "",
        nominal: "",
    };
    isEdit.value = true;
    editId.value = item.kdlsp_skema;
    error.value = "";
    modal.value = true;

    try {
        const res = await skemaTarifService.getOne(item.kdlsp_skema);
        form.value.nominal = res.data?.nominal ?? "";
    } catch {
        form.value.nominal = "";
    }
};

const submit = async () => {
    error.value = "";
    saving.value = true;
    try {
        let kdlsp_skema = editId.value;

        if (isEdit.value) {
            await skemaService.update(editId.value, {
                skema: form.value.skema,
                no_skema: form.value.no_skema,
            });
        } else {
            const res = await skemaService.create({
                skema: form.value.skema,
                no_skema: form.value.no_skema,
            });
            kdlsp_skema = res.data.kdlsp_skema;
        }

        if (form.value.nominal !== "" && form.value.nominal !== null) {
            await skemaTarifService.save({
                kdlsp_skema,
                nominal: form.value.nominal,
            });
        }

        emit("toast", {
            message: isEdit.value
                ? "Skema berhasil diperbarui"
                : "Skema berhasil ditambahkan",
        });
        modal.value = false;
        fetch();
    } catch (err) {
        error.value = err.response?.data?.message || "Terjadi kesalahan";
    } finally {
        saving.value = false;
    }
};

const toggle = async (item) => {
    toggling.value = item.kdlsp_skema;
    try {
        await skemaService.toggle(item.kdlsp_skema);
        emit("toast", {
            message: `Skema ${item.isActive ? "dinonaktifkan" : "diaktifkan"}`,
        });
        fetch();
    } catch {
        emit("toast", { message: "Gagal mengubah status", type: "error" });
    } finally {
        toggling.value = null;
    }
};

const destroy = async (item) => {
    if (!confirm(`Hapus skema "${item.skema}"?`)) return;
    try {
        await skemaService.destroy(item.kdlsp_skema);
        emit("toast", { message: "Skema berhasil dihapus" });
        fetch();
    } catch {
        emit("toast", { message: "Gagal menghapus skema", type: "error" });
    }
};

onMounted(fetch);
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-sm font-semibold text-[#1e3329]">
                    Daftar Skema
                </h3>
                <p class="text-xs text-[#7aab95] mt-0.5">
                    Kelola skema sertifikasi LSP
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
                Tambah Skema
            </button>
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
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-12"
                        >
                            No
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Nama Skema
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Nomor Skema
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Nominal Tagihan
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
                    <tr v-if="list.length === 0">
                        <td
                            colspan="6"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Belum ada skema
                        </td>
                    </tr>
                    <tr
                        v-for="(item, i) in list"
                        :key="item.kdlsp_skema"
                        class="border-b border-[#f0f4f1] hover:bg-[#f9fbfa] transition-colors"
                    >
                        <td class="px-5 py-3.5 text-slate-400 text-xs">
                            {{ i + 1 }}
                        </td>
                        <td class="px-5 py-3.5 font-medium text-[#1e3329]">
                            {{ item.skema }}
                        </td>
                        <td class="px-5 py-3.5 font-medium text-[#1e3329]">
                            {{ item.no_skema }}
                        </td>
                        <td class="px-5 py-3.5 text-[#1e3329]">
                            {{
                                item.tarif?.nominal != null
                                    ? "Rp " +
                                      Number(item.tarif.nominal).toLocaleString(
                                          "id-ID",
                                      )
                                    : "-"
                            }}
                        </td>
                        <td class="px-5 py-3.5">
                            <button
                                @click="toggle(item)"
                                :disabled="toggling === item.kdlsp_skema"
                                class="flex items-center gap-2.5 group"
                                :title="
                                    item.isActive
                                        ? 'Klik untuk nonaktifkan'
                                        : 'Klik untuk aktifkan'
                                "
                            >
                                <!-- Switch Track -->
                                <div
                                    :class="
                                        item.isActive
                                            ? 'bg-[#2d4a3e]'
                                            : 'bg-slate-200'
                                    "
                                    class="relative w-10 h-5 rounded-full transition-colors duration-300 shrink-0"
                                >
                                    <!-- Switch Thumb -->
                                    <div
                                        :class="
                                            item.isActive
                                                ? 'translate-x-5'
                                                : 'translate-x-0.5'
                                        "
                                        class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300"
                                    ></div>

                                    <!-- Loading spinner overlay -->
                                    <div
                                        v-if="toggling === item.kdlsp_skema"
                                        class="absolute inset-0 flex items-center justify-center rounded-full bg-black/10"
                                    >
                                        <svg
                                            class="w-3 h-3 animate-spin text-white"
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
                                </div>

                                <!-- Label -->
                                <span
                                    :class="
                                        item.isActive
                                            ? 'text-[#2d4a3e] font-semibold'
                                            : 'text-slate-400'
                                    "
                                    class="text-xs transition-colors duration-200 select-none"
                                >
                                    {{ item.isActive ? "Aktif" : "Nonaktif" }}
                                </span>
                            </button>
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

        <BaseModal
            :show="modal"
            :title="isEdit ? 'Edit Skema' : 'Tambah Skema'"
            @close="modal = false"
        >
            <div class="space-y-4">
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                        >Nama Skema</label
                    >
                    <input
                        v-model="form.skema"
                        type="text"
                        placeholder="cth: Junior Web Developer"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                        @keyup.enter="submit"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                        >Nomor Skema</label
                    >
                    <input
                        v-model="form.no_skema"
                        type="text"
                        placeholder="cth: 001/FST/LSP.UNISAYOGYA/2024"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                        @keyup.enter="submit"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                        >Nominal Tagihan (Rp)</label
                    >
                    <input
                        v-model.number="form.nominal"
                        type="number"
                        min="0"
                        placeholder="cth: 150000"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                    />
                    <p class="text-xs text-[#7aab95] mt-1">
                        Kosongkan jika skema ini belum dikenai biaya
                    </p>
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
                            !form.skema.trim() ||
                            !form.no_skema.trim()
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
