<script setup>
import { ref, onMounted, computed } from "vue";
import { userService } from "../../services/lspService";
import BaseModal from "../BaseModal.vue";

const emit = defineEmits(["toast"]);

const list = ref([]);
const loading = ref(false);
const toggling = ref(null);
const filterRole = ref("");
const filterAsesor = ref("");
const search = ref("");
const unitList = ref([]);
const modal = ref(false);
const saving = ref(false);
const error = ref("");
const editId = ref(null);
const form = ref({ username: "", password: "", namalengkap: "", role: "", kdunit: "", isAsesor: false });

const roleOptions = [
    { value: "", label: "Semua Role" },
    { value: "mahasiswa", label: "Mahasiswa" },
    { value: "dosen", label: "Dosen" },
    { value: "tendik", label: "Tendik" },
    { value: "asesor_luar", label: "Asesor Luar" },
];

const asesorOptions = [
    { value: "", label: "Semua" },
    { value: "true", label: "Asesor Aktif" },
    { value: "false", label: "Bukan Asesor" },
];

const roleBadge = (role) =>
    ({
        mahasiswa: "bg-blue-50 text-blue-700",
        dosen: "bg-indigo-50 text-indigo-700",
        tendik: "bg-amber-50 text-amber-700",
        asesor_luar: "bg-purple-50 text-purple-700",
    })[role] || "bg-slate-100 text-slate-500";

const roleLabel = (role) =>
    ({
        mahasiswa: "Mahasiswa",
        dosen: "Dosen",
        tendik: "Tendik",
        asesor_luar: "Asesor Luar",
    })[role] || role;

const filtered = computed(() => {
    return list.value.filter((u) => {
        const matchRole = !filterRole.value || u.role === filterRole.value;
        const matchAsesor =
            filterAsesor.value === ""
                ? true
                : filterAsesor.value === "true"
                  ? u.isAsesor
                  : !u.isAsesor;
        const matchSearch =
            !search.value ||
            u.username
                ?.toString()
                .toLowerCase()
                .includes(search.value.toLowerCase()) ||
            getNama(u).toLowerCase().includes(search.value.toLowerCase());
        return matchRole && matchAsesor && matchSearch;
    });
});

// Stats
const totalUser = computed(() => list.value.length);
const totalAsesor = computed(() => list.value.filter((u) => u.isAsesor).length);
const totalAsesorUniv = computed(
    () => list.value.filter((u) => u.isAsesor && u.role === "dosen").length,
);
const totalAsesorLuar = computed(
    () => list.value.filter((u) => u.role === "asesor_luar").length,
);

const fetch = async () => {
    loading.value = true;
    try {
        const res = await userService.getAll();
        list.value = res.data;
    } catch {
        emit("toast", { message: "Gagal memuat data user", type: "error" });
    } finally {
        loading.value = false;
    }
};

const openEdit = (item) => {
    editId.value = item.kdlsp_user;
    form.value = { username: item.username || "", password: "", namalengkap: item.person?.namalengkap || item.namalengkap || "", role: item.role || "", kdunit: item.kdunit || "", isAsesor: Boolean(item.isAsesor) };
    error.value = "";
    modal.value = true;
};

const submitEdit = async () => {
    saving.value = true; error.value = "";
    try {
        await userService.update(editId.value, { ...form.value, kdunit: form.value.kdunit || null });
        modal.value = false;
        emit("toast", { message: "Data user berhasil diperbarui" });
        await fetch();
    } catch (err) {
        error.value = err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || "Gagal memperbarui user";
    } finally { saving.value = false; }
};

const getNama = (item) => {
    // Jika ada data person (user internal SSO)
    if (item.person) {
        const depan = item.person.gelardepan
            ? item.person.gelardepan + " "
            : "";
        const belakang = item.person.gelarbelakang
            ? ", " + item.person.gelarbelakang
            : "";
        return depan + (item.person.namalengkap || "") + belakang;
    }

    // Fallback ke namalengkap di lsp_user (asesor luar)
    if (item.namalengkap) return item.namalengkap;

    // Terakhir fallback ke username
    return item.username?.toString() || "-";
};

// Toggle untuk dosen internal DAN asesor_luar
const toggleAsesor = async (item) => {
    if (!["dosen", "asesor_luar"].includes(item.role)) return;
    toggling.value = item.kdlsp_user;
    try {
        await userService.toggleAsesor(item.kdlsp_user);
        emit("toast", {
            message: `${getNama(item)} ${item.isAsesor ? "dilepas dari" : "ditetapkan sebagai"} asesor`,
        });
        fetch();
    } catch (err) {
        emit("toast", {
            message:
                err.response?.data?.message || "Gagal mengubah status asesor",
            type: "error",
        });
    } finally {
        toggling.value = null;
    }
};

onMounted(async () => {
    await Promise.all([fetch(), userService.getUnitKerja().then((response) => { unitList.value = response.data; })]);
});
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h3 class="text-sm font-semibold text-[#1e3329]">
                    Data User Terdaftar
                </h3>
                <p class="text-xs text-[#7aab95] mt-0.5">
                    User yang pernah login ke sistem
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Search -->
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
                        placeholder="Cari username / nama..."
                        class="pl-9 pr-4 py-2 text-xs border border-[#c8ddd6] rounded-lg text-[#1e3329] placeholder-slate-400 focus:outline-none focus:border-[#4a7c6b] bg-white w-48"
                    />
                </div>

                <!-- Filter Role -->
                <select
                    v-model="filterRole"
                    class="border border-[#c8ddd6] rounded-lg px-3 py-2 text-xs text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] bg-white"
                >
                    <option
                        v-for="r in roleOptions"
                        :key="r.value"
                        :value="r.value"
                    >
                        {{ r.label }}
                    </option>
                </select>

                <!-- Filter Asesor -->
                <select
                    v-model="filterAsesor"
                    class="border border-[#c8ddd6] rounded-lg px-3 py-2 text-xs text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] bg-white"
                >
                    <option
                        v-for="a in asesorOptions"
                        :key="a.value"
                        :value="a.value"
                    >
                        {{ a.label }}
                    </option>
                </select>

                <!-- Refresh -->
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

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-[#1e3329]">{{ totalUser }}</p>
                <p class="text-xs text-[#7aab95]">Total User</p>
            </div>
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-[#1e3329]">
                    {{ totalAsesor }}
                </p>
                <p class="text-xs text-[#7aab95]">Total Asesor Aktif</p>
            </div>
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-[#1e3329]">
                    {{ totalAsesorUniv }}
                </p>
                <p class="text-xs text-[#7aab95]">Asesor Internal</p>
            </div>
            <div class="bg-white border border-[#dde8e3] rounded-xl px-4 py-3">
                <p class="text-lg font-bold text-[#1e3329]">
                    {{ totalAsesorLuar }}
                </p>
                <p class="text-xs text-[#7aab95]">Asesor Luar</p>
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
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-10"
                        >
                            No
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Nama / Username
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Unit Kerja
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Role
                        </th>
                        <th
                            class="text-left px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Status Asesor
                        </th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="filtered.length === 0">
                        <td
                            colspan="6"
                            class="text-center py-12 text-[#7aab95] text-sm"
                        >
                            Tidak ada data
                        </td>
                    </tr>
                    <tr
                        v-for="(item, i) in filtered"
                        :key="item.kdlsp_user"
                        class="border-b border-[#f0f4f1] hover:bg-[#f9fbfa] transition-colors"
                    >
                        <td class="px-5 py-3.5 text-slate-400 text-xs">
                            {{ i + 1 }}
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-[#1e3329] text-sm">
                                {{ getNama(item) }}
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ item.username }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-500">
                            {{
                                item.unit_kerja?.unitkerjapendek ||
                                item.unit_kerja?.unitkerja ||
                                "-"
                            }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span
                                :class="roleBadge(item.role)"
                                class="px-2.5 py-1 text-xs font-medium rounded-full"
                            >
                                {{ roleLabel(item.role) }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <!-- Toggle untuk dosen internal & asesor luar -->
                            <button
                                v-if="
                                    item.role === 'dosen' ||
                                    item.role === 'asesor_luar'
                                "
                                @click="toggleAsesor(item)"
                                :disabled="toggling === item.kdlsp_user"
                                class="flex items-center gap-2.5 group"
                                :title="
                                    item.isAsesor
                                        ? 'Klik untuk nonaktifkan'
                                        : 'Klik untuk aktifkan'
                                "
                            >
                                <div
                                    :class="
                                        item.isAsesor
                                            ? 'bg-[#2d4a3e]'
                                            : 'bg-slate-200'
                                    "
                                    class="relative w-10 h-5 rounded-full transition-colors duration-300 shrink-0"
                                >
                                    <div
                                        :class="
                                            item.isAsesor
                                                ? 'translate-x-5'
                                                : 'translate-x-0.5'
                                        "
                                        class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-300"
                                    ></div>
                                    <div
                                        v-if="toggling === item.kdlsp_user"
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
                                <span
                                    :class="
                                        item.isAsesor
                                            ? 'text-[#2d4a3e] font-semibold'
                                            : 'text-slate-400'
                                    "
                                    class="text-xs transition-colors duration-200 select-none"
                                >
                                    {{ item.isAsesor ? "Aktif" : "Nonaktif" }}
                                </span>
                            </button>

                            <!-- Role lain (mahasiswa, tendik) -->
                            <span v-else class="text-slate-300 text-xs">—</span>
                        </td>
                        <td class="px-5 py-3.5 text-right"><button v-if="['mahasiswa', 'dosen', 'tendik', 'asesor_luar'].includes(item.role)" @click="openEdit(item)" class="rounded-lg border border-[#c8ddd6] px-3 py-1.5 text-xs font-semibold text-[#4a7c6b] hover:bg-[#eaf2ee]">Edit</button><span v-else class="text-xs text-slate-300">—</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal :show="modal" title="Edit User" size="lg" @close="modal = false">
            <div class="space-y-4">
                <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#3d6355]">Nama Lengkap</label><input v-model="form.namalengkap" class="w-full rounded-xl border border-[#c8ddd6] px-4 py-2.5 text-sm focus:outline-none focus:border-[#4a7c6b]"></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#3d6355]">Username</label><input v-model="form.username" class="w-full rounded-xl border border-[#c8ddd6] px-4 py-2.5 text-sm focus:outline-none focus:border-[#4a7c6b]"></div>
                    <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#3d6355]">Password baru</label><input v-model="form.password" type="password" placeholder="Kosongkan jika tidak diubah" class="w-full rounded-xl border border-[#c8ddd6] px-4 py-2.5 text-sm focus:outline-none focus:border-[#4a7c6b]"></div>
                    <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#3d6355]">Role</label><select v-model="form.role" class="w-full rounded-xl border border-[#c8ddd6] px-4 py-2.5 text-sm"><option v-for="option in roleOptions.filter((item) => item.value)" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                    <div><label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#3d6355]">Unit kerja / prodi</label><select v-model="form.kdunit" class="w-full rounded-xl border border-[#c8ddd6] px-4 py-2.5 text-sm"><option value="">Tanpa unit kerja</option><option v-for="unit in unitList" :key="unit.kdunitkerja" :value="unit.kdunitkerja">{{ unit.unitkerjapendek || unit.unitkerja }}</option></select></div>
                </div>
                <label v-if="['dosen', 'asesor_luar'].includes(form.role)" class="flex items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm text-[#1e3329]"><input v-model="form.isAsesor" type="checkbox"> Aktif sebagai asesor</label>
                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-600">{{ error }}</div>
                <div class="flex justify-end gap-2"><button @click="modal = false" class="rounded-lg px-4 py-2 text-xs text-slate-500 hover:bg-slate-100">Batal</button><button @click="submitEdit" :disabled="saving || !form.username || !form.namalengkap || !form.role" class="rounded-lg bg-[#2d4a3e] px-5 py-2 text-xs font-semibold text-white disabled:opacity-50">{{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}</button></div>
            </div>
        </BaseModal>
    </div>
</template>
