<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import AppLayout from "../../layouts/AppLayout.vue";
import {
    plottingService,
    apl01PengajuanService,
} from "../../services/lspService";
import BaseModal from "../../components/BaseModal.vue";

const router = useRouter();
const loading = ref(false);
const list = ref([]);
const error = ref("");
const confirmModal = ref(false);
const selectedItem = ref(null);
const myPengajuanSkemaIds = ref(new Set());

const fetchSkemaTersedia = async () => {
    loading.value = true;
    error.value = "";

    try {
        const res = await plottingService.getAll();

        list.value = Array.isArray(res.data) ? res.data : res.data?.data || [];
    } catch (err) {
        error.value =
            err.response?.data?.message || "Gagal memuat skema sertifikasi";
    } finally {
        loading.value = false;
    }
};

const skemaTersedia = computed(() =>
    list.value.filter((item) => {
        const periodeAktif =
            item.periode?.isActive ?? item.periode?.is_active ?? true;
        const masaAktif =
            item.masa_periode?.isActive ?? item.masa_periode?.is_active ?? true;
        const skemaAktif =
            item.skema?.isActive ?? item.skema?.is_active ?? true;

        return periodeAktif && masaAktif && skemaAktif;
    }),
);

const getSkemaName = (item) =>
    item.skema?.skema || item.nama_skema || item.skema || "-";

const getPeriodeName = (item) =>
    item.periode?.periode ||
    item.periode?.nama_periode ||
    item.nama_periode ||
    "-";

const formatDate = (d) =>
    d
        ? new Date(d).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          })
        : "-";

const getMasaName = (item) => {
    const masa = item.masa_periode;
    return masa
        ? `${formatDate(masa.tanggal_mulai)} - ${formatDate(masa.tanggal_selesai)}`
        : "-";
};

const pilihSkema = (item) => {
    if (myPengajuanSkemaIds.value.has(item.kdlsp_periode_skema)) {
        router.push({
            name: "mahasiswa-sertifikasi-form",
            params: { id: item.kdlsp_periode_skema },
        });
        return;
    }

    selectedItem.value = item;
    confirmModal.value = true;
};

const confirmPilihSkema = () => {
    confirmModal.value = false;
    router.push({
        name: "mahasiswa-sertifikasi-form",
        params: { id: selectedItem.value.kdlsp_periode_skema },
    });
};

const fetchMyPengajuan = async () => {
    try {
        const res = await apl01PengajuanService.getAll();
        myPengajuanSkemaIds.value = new Set(
            (Array.isArray(res.data) ? res.data : []).map(
                (p) => p.kdlsp_periode_skema,
            ),
        );
    } catch {
        myPengajuanSkemaIds.value = new Set();
    }
};

onMounted(() => {
    fetchSkemaTersedia();
    fetchMyPengajuan();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h2 class="text-xl font-bold text-[#1e3329]">
                Sertifikasi Tersedia
            </h2>
            <p class="text-[#7aab95] text-sm mt-1">
                Pilih skema sertifikasi yang tersedia pada periode aktif.
            </p>
        </div>

        <div
            v-if="loading"
            class="bg-white border border-[#dde8e3] rounded-xl py-14 text-center text-[#7aab95]"
        >
            Memuat skema sertifikasi...
        </div>

        <div
            v-else-if="error"
            class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-5 py-4 text-sm"
        >
            {{ error }}
        </div>

        <div
            v-else-if="skemaTersedia.length === 0"
            class="bg-white border border-[#dde8e3] rounded-xl py-14 text-center text-[#7aab95]"
        >
            Belum ada skema sertifikasi yang tersedia saat ini.
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="item in skemaTersedia"
                :key="item.kdlsp_periode_skema"
                class="bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
            >
                <div class="mb-4">
                    <p
                        class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                    >
                        Skema Sertifikasi
                    </p>
                    <h3 class="text-base font-bold text-[#1e3329] mt-1">
                        {{ getSkemaName(item) }}
                    </h3>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-[#7aab95]">Periode</span>
                        <span class="font-medium text-[#1e3329] text-right">
                            {{ getPeriodeName(item) }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-[#7aab95]">Masa</span>
                        <span class="font-medium text-[#1e3329] text-right">
                            {{ getMasaName(item) }}
                        </span>
                    </div>
                </div>

                <button
                    @click="pilihSkema(item)"
                    class="mt-5 w-full bg-[#2d4a3e] hover:bg-[#3d6355] text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all"
                >
                    {{
                        myPengajuanSkemaIds.has(item.kdlsp_periode_skema)
                            ? "Lanjutkan"
                            : "Pilih Skema"
                    }}
                </button>
            </div>
        </div>

        <!-- modal konfirmasi -->
        <BaseModal
            :show="confirmModal"
            title="Konfirmasi Pengambilan Skema"
            @close="confirmModal = false"
        >
            <div class="space-y-4">
                <p class="text-sm text-[#1e3329]">
                    Apakah Anda yakin ingin mengambil skema sertifikasi
                    <strong>{{ getSkemaName(selectedItem) }}</strong>
                    pada periode
                    <strong>{{ getPeriodeName(selectedItem) }}</strong
                    >?
                </p>
                <p
                    class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5"
                >
                    Setelah dikonfirmasi, tagihan pembayaran akan diterbitkan
                    atas nama Anda dan Anda akan diarahkan ke halaman pengisian
                    FR.APL.01.
                </p>
                <div class="flex justify-end gap-2 pt-1">
                    <button
                        @click="confirmModal = false"
                        class="px-4 py-2 text-xs font-medium text-slate-500 hover:bg-slate-100 rounded-lg transition-all"
                    >
                        Batal
                    </button>
                    <button
                        @click="confirmPilihSkema"
                        class="px-5 py-2 text-xs font-semibold bg-[#2d4a3e] hover:bg-[#3d6355] text-white rounded-lg transition-all"
                    >
                        Ya, Ambil Skema Ini
                    </button>
                </div>
            </div>
        </BaseModal>
    </AppLayout>
</template>
