<script setup>
import { ref, onMounted } from "vue";
import { userService } from "../../services/lspService";
import BaseModal from "../BaseModal.vue";

const emit = defineEmits(["toast"]);

const list = ref([]);
const unitList = ref([]);
const loading = ref(false);
const modal = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const deleting = ref(null);
const error = ref("");
const editId = ref(null);

const form = ref({
    username: "",
    password: "",
    namalengkap: "",
});

const fetch = async () => {
    loading.value = true;
    try {
        const res = await userService.getAll({ role: "asesor_luar" });
        list.value = res.data;
    } catch {
        emit("toast", { message: "Gagal memuat data", type: "error" });
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    form.value = {
        username: "",
        password: "",
        namalengkap: "",
    };
    isEdit.value = false;
    editId.value = null;
    error.value = "";
    modal.value = true;
};

const openEdit = (item) => {
    form.value = {
        username: item.username,
        password: "",
        namalengkap: item.namalengkap || "",
    };

    isEdit.value = true;
    editId.value = item.kdlsp_user;
    error.value = "";
    modal.value = true;
};

const submit = async () => {
    error.value = "";
    saving.value = true;
    try {
        if (isEdit.value) {
            await userService.update(editId.value, form.value);
            emit("toast", { message: "Data asesor luar berhasil diperbarui" });
        } else {
            await userService.storeAsesorLuar(form.value);
            emit("toast", { message: "Asesor luar berhasil ditambahkan" });
        }
        modal.value = false;
        fetch();
    } catch (err) {
        error.value =
            err.response?.data?.message ||
            Object.values(err.response?.data?.errors || {})[0]?.[0] ||
            "Terjadi kesalahan";
    } finally {
        saving.value = false;
    }
};

const destroy = async (item) => {
    if (!confirm(`Hapus asesor luar "${item.namalengkap || item.username}"?`))
        return;

    deleting.value = item.kdlsp_user;

    try {
        await userService.destroy(item.kdlsp_user);
        emit("toast", { message: "Asesor luar berhasil dihapus" });
        fetch();
    } catch {
        emit("toast", {
            message: "Gagal menghapus asesor luar",
            type: "error",
        });
    } finally {
        deleting.value = null;
    }
};

const getNama = (item) => {
    return item.namalengkap || item.username;
};

onMounted(fetch);
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-sm font-semibold text-[#1e3329]">
                    Asesor Luar Universitas
                </h3>
                <p class="text-xs text-[#7aab95] mt-0.5">
                    Asesor dari luar yang ditugaskan oleh admin
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
                Tambah Asesor Luar
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
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-10"
                        >
                            No
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Nama
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Username
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
                            Belum ada asesor luar
                        </td>
                    </tr>
                    <tr
                        v-for="(item, i) in list"
                        :key="item.kdlsp_user"
                        class="border-b border-[#f0f4f1] hover:bg-[#f9fbfa] transition-colors"
                    >
                        <td class="px-5 py-3.5 text-slate-400 text-xs">
                            {{ i + 1 }}
                        </td>
                        <td class="px-5 py-3.5 font-medium text-[#1e3329]">
                            {{ getNama(item) }}
                        </td>
                        <td
                            class="px-5 py-3.5 text-slate-500 text-xs font-mono"
                        >
                            {{ item.username }}
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
                                    :disabled="deleting === item.kdlsp_user"
                                    class="px-3 py-1.5 text-xs text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-all font-medium disabled:opacity-50"
                                >
                                    {{
                                        deleting === item.kdlsp_user
                                            ? "..."
                                            : "Hapus"
                                    }}
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
            :title="isEdit ? 'Edit Asesor Luar' : 'Tambah Asesor Luar'"
            size="lg"
            @close="modal = false"
        >
            <div class="space-y-4">
                <!-- Nama Lengkap -->
                <div>
                    <label
                        class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                    >
                        Nama Lengkap <span class="text-red-400">*</span>
                    </label>
                    <input
                        v-model="form.namalengkap"
                        type="text"
                        placeholder="cth: Dr. Budi Santoso, M.Kom"
                        class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <!-- Username -->
                    <div>
                        <label
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                        >
                            Username <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model="form.username"
                            type="text"
                            placeholder="Username login"
                            :disabled="isEdit"
                            class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all disabled:bg-slate-50 disabled:text-slate-400"
                        />
                    </div>

                    <!-- Password -->
                    <div>
                        <label
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider block mb-1.5"
                        >
                            Password
                            {{ isEdit ? "(kosongkan jika tidak diubah)" : "*" }}
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            placeholder="Min. 6 karakter"
                            class="w-full border border-[#c8ddd6] rounded-xl px-4 py-2.5 text-sm text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all"
                        />
                    </div>
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
                            !form.namalengkap ||
                            !form.username ||
                            (!isEdit && !form.password)
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
                                  : "Tambah Asesor"
                        }}
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
