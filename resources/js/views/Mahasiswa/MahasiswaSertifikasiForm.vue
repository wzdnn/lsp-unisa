<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppLayout from "../../layouts/AppLayout.vue";
import {
    apl01DokumenService,
    apl01PengajuanService,
    plottingService,
    unitKompetensiService,
} from "../../services/lspService";
import { useAuthStore } from "../../stores/auth";

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const loading = ref(false);
const saving = ref(false);
const error = ref("");
const notice = ref("");
const plotting = ref(null);
const pengajuan = ref(null);
const unitList = ref([]);
const dokumenList = ref([]);
const uploadingKey = ref(null);
const fileInputRefs = {};
const triggerUpload = (jenisDokumen) => {
    fileInputRefs[jenisDokumen]?.click();
};

const assessmentOptions = [
    "Sertifikasi",
    "Pengakuan Kompetensi Terkini (PKT)",
    "Rekognisi Pembelajaran Lampau",
    "Lainnya",
];

// ── helpers ──────────────────────────────────────────────────────────────────

const firstFilled = (...values) =>
    values.find(
        (v) => v !== undefined && v !== null && String(v).trim() !== "",
    ) || "";

const formatDateInput = (value) => (value ? String(value).slice(0, 10) : "");

const normalizeGender = (value) => {
    const g = String(value || "").toLowerCase();
    if (["l", "laki-laki", "laki laki", "pria"].includes(g)) return "Laki-laki";
    if (["p", "wanita", "perempuan"].includes(g)) return "Wanita";
    return value || "";
};

const formatDate = (date) =>
    date
        ? new Date(date).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          })
        : "-";

// ── program studi (untuk label dinamis bagian 3.1) ────────────────────────────

const prodiName = computed(() => {
    const unitKerja = auth.user?.lsp_user?.unit_kerja;

    return unitKerja?.unitkerja || unitKerja?.unitkerjapendek || "-";
});

// ── definisi item persyaratan (statis) ────────────────────────────────────────

const persyaratanDasar = computed(() => [
    {
        no: 1,
        jenis_dokumen: "khs",
        label: `Fotokopi Kartu Hasil Studi semester 1-5 pada ${prodiName.value}`,
        hasUpload: true,
    },
    {
        no: 2,
        jenis_dokumen: "magang",
        label: "Surat keterangan telah menyelesaikan magang/praktek kerja lapangan yang berkaitan keselamatan kerja dari instansi terkait/tempat magang/praktek kerja lapangan",
        hasUpload: true,
    },
    {
        no: 3,
        jenis_dokumen: "sertifikat_pelatihan",
        label: "Sertifikat pelatihan berbasis kompetensi keselamatan kerja yang dikeluarkan oleh Lembaga Pelatihan di Unisa Yogyakarta",
        hasUpload: true,
    },
]);

const persyaratanAdministratif = computed(() => [
    {
        no: 1,
        jenis_dokumen: "ktp",
        label: "Fotokopi KTP",
        hasUpload: true,
    },
    {
        no: 2,
        jenis_dokumen: "ktm",
        label: "Fotokopi KTM",
        hasUpload: true,
    },
    {
        no: 3,
        jenis_dokumen: "foto",
        label: "Pas Foto terbaru 3x4 sebanyak 4 lembar dengan background warna merah",
        hasUpload: false,
    },
    {
        no: 4,
        jenis_dokumen: "apl02",
        label: "Mengisi formulir asesmen Mandiri (APL 02) dan dilengkapi dengan bukti yang relevan (jika ada)",
        hasUpload: false,
    },
]);

// ── helper dokumen ────────────────────────────────────────────────────────────

const getDokumen = (jenisDokumen) =>
    dokumenList.value.find((d) => d.jenis_dokumen === jenisDokumen) || null;

const getFileUrl = (filePath) => (filePath ? `/storage/${filePath}` : null);

// ── form ──────────────────────────────────────────────────────────────────────

const makeForm = () => {
    const person = auth.user?.lsp_user?.person || {};

    return {
        data_pribadi: {
            nama_lengkap: firstFilled(
                person.namalengkap,
                person.nama_lengkap,
                person.nama,
            ),
            no_identitas: firstFilled(
                person.nik,
                person.noktp,
                person.no_ktp,
                person.noidentitas,
                person.no_identitas,
            ),
            tempat_lahir: firstFilled(
                person.tempatlahir,
                person.tempat_lahir,
                person.tmplahir,
            ),
            tanggal_lahir: formatDateInput(
                firstFilled(
                    person.tanggallahir,
                    person.tanggal_lahir,
                    person.tgllahir,
                    person.tgl_lahir,
                ),
            ),
            jenis_kelamin: normalizeGender(
                firstFilled(
                    person.jeniskelamin,
                    person.jenis_kelamin,
                    person.gender,
                    person.jk,
                ),
            ),
            kebangsaan: "Indonesia",
            alamat_rumah: firstFilled(
                person.alamatlengkap,
                person.alamat_lengkap,
                person.alamatrumah,
                person.alamat_rumah,
                person.alamat,
            ),
            kode_pos: firstFilled(
                person.kodepos,
                person.kode_pos,
                person.kdpos,
            ),
            telepon_rumah: firstFilled(
                person.teleponrumah,
                person.telepon_rumah,
                person.telp_rumah,
            ),
            hp: firstFilled(
                person.notelpon,
                person.nohp,
                person.no_hp,
                person.hp,
                person.telepon,
            ),
            email: firstFilled(person.email, person.emailpribadi),
            kualifikasi_pendidikan: "",
        },
        data_pekerjaan: {
            nama_institusi: "Universitas Aisyiyah Yogyakarta",
            jabatan: "Mahasiswa",
            alamat_kantor:
                "Jl. Siliwangi (Ring Road Barat) No. 63 Nogotirto, Gamping, Sleman, Yogyakarta.",
            kode_pos_kantor: "55292",
            telepon_kantor: "(0274) 4469199",
            fax_kantor: "(0274) 4469204",
            email_kantor: "info@unisayogya.ac.id",
        },
        data_sertifikasi: {
            judul_skema: "",
            nomor_skema: "",
            tujuan_asesmen: "Sertifikasi",
            tujuan_lainnya: "",
        },
        data_persyaratan: buildPersyaratanPayload(),
    };
};

// data_persyaratan yang dikirim ke backend — hanya metadata, bukan file
const buildPersyaratanPayload = () => ({
    bagian_3_1: persyaratanDasar.value.map(({ no, jenis_dokumen, label }) => ({
        no,
        jenis_dokumen,
        label,
    })),
    bagian_3_2: persyaratanAdministratif.value.map(
        ({ no, jenis_dokumen, label }) => ({
            no,
            jenis_dokumen,
            label,
        }),
    ),
});

const form = ref(makeForm());

// ── field definitions ─────────────────────────────────────────────────────────

const personalFields = [
    {
        key: "nama_lengkap",
        label: "Nama lengkap",
        autocomplete: "name",
        span: "md:col-span-2",
    },
    { key: "no_identitas", label: "No. KTP/NIK/Paspor" },
    { key: "tempat_lahir", label: "Tempat lahir" },
    { key: "tanggal_lahir", label: "Tanggal lahir", type: "date" },
    {
        key: "jenis_kelamin",
        label: "Jenis kelamin",
        options: ["Laki-laki", "Wanita"],
    },
    { key: "kebangsaan", label: "Kebangsaan" },
    {
        key: "alamat_rumah",
        label: "Alamat rumah",
        textarea: true,
        span: "md:col-span-2",
    },
    { key: "kode_pos", label: "Kode pos" },
    { key: "telepon_rumah", label: "Telepon rumah" },
    { key: "hp", label: "HP" },
    { key: "email", label: "E-mail", type: "email", autocomplete: "email" },
    {
        key: "kualifikasi_pendidikan",
        label: "Kualifikasi pendidikan",
        span: "md:col-span-2",
    },
];

const workFields = [
    {
        key: "nama_institusi",
        label: "Nama institusi / perusahaan",
        span: "md:col-span-2",
    },
    { key: "jabatan", label: "Jabatan" },
    {
        key: "alamat_kantor",
        label: "Alamat kantor",
        textarea: true,
        span: "md:col-span-2",
    },
    { key: "kode_pos_kantor", label: "Kode pos kantor" },
    { key: "telepon_kantor", label: "Telepon kantor" },
    { key: "fax_kantor", label: "Fax" },
    { key: "email_kantor", label: "E-mail kantor", type: "email" },
];

// ── computed ──────────────────────────────────────────────────────────────────

const storageKey = computed(
    () =>
        `apl01-draft-${route.params.id}-${auth.user?.username || "mahasiswa"}`,
);

const selectedSkemaName = computed(
    () =>
        plotting.value?.skema?.skema ||
        form.value.data_sertifikasi.judul_skema ||
        "-",
);

const periodeName = computed(() => plotting.value?.periode?.periode || "-");

const pengajuanStatus = computed(() =>
    String(pengajuan.value?.status || "").trim(),
);

const pengajuanId = computed(
    () => pengajuan.value?.kdlsp_apl01_pengajuan || null,
);

const statusLabel = computed(() => {
    const labels = {
        draft: "Draft",
        menunggu_review: "Menunggu Review",
        diterima: "Diterima",
        perlu_revisi: "Perlu Revisi",
        ditolak: "Ditolak",
    };
    return labels[pengajuanStatus.value] || "";
});

const statusClass = computed(() => {
    const classes = {
        draft: "bg-slate-100 text-slate-600 border-slate-200",
        menunggu_review: "bg-amber-50 text-amber-700 border-amber-200",
        diterima: "bg-[#eaf2ee] text-[#2d4a3e] border-[#c8ddd6]",
        perlu_revisi: "bg-blue-50 text-blue-700 border-blue-200",
        ditolak: "bg-red-50 text-red-600 border-red-200",
    };
    return (
        classes[pengajuanStatus.value] ||
        "bg-slate-100 text-slate-600 border-slate-200"
    );
});

const canEdit = computed(
    () =>
        !["menunggu_review", "diterima", "ditolak"].includes(
            pengajuanStatus.value,
        ),
);

const masaName = computed(() => {
    const masa = plotting.value?.masa_periode;
    if (!masa) return "-";
    return `${formatDate(masa.tanggal_mulai)} - ${formatDate(masa.tanggal_selesai)}`;
});

// ── merge helpers ─────────────────────────────────────────────────────────────

const mergeSection = (defaults, draft = {}) =>
    Object.fromEntries(
        Object.entries(defaults).map(([key, defaultValue]) => {
            const draftValue = draft[key];
            if (
                draftValue === undefined ||
                draftValue === null ||
                String(draftValue).trim() === ""
            ) {
                return [key, defaultValue];
            }
            return [key, draftValue];
        }),
    );

const mergeForm = (draft) => {
    const base = makeForm();
    return {
        data_pribadi: mergeSection(base.data_pribadi, draft?.data_pribadi),
        data_pekerjaan: mergeSection(
            base.data_pekerjaan,
            draft?.data_pekerjaan,
        ),
        data_sertifikasi: mergeSection(
            base.data_sertifikasi,
            draft?.data_sertifikasi,
        ),
        data_persyaratan: buildPersyaratanPayload(), // selalu dari definisi statis
    };
};

const applySkemaDefaults = () => {
    const skema = plotting.value?.skema || {};
    if (!form.value.data_sertifikasi.judul_skema) {
        form.value.data_sertifikasi.judul_skema = skema.skema || "";
    }
    if (!form.value.data_sertifikasi.nomor_skema) {
        form.value.data_sertifikasi.nomor_skema = skema.no_skema || "";
    }
};

// ── fetch ─────────────────────────────────────────────────────────────────────

const loadDraft = () => {
    try {
        const raw = localStorage.getItem(storageKey.value);
        if (!raw) return;
        form.value = mergeForm(JSON.parse(raw));
    } catch {
        form.value = makeForm();
    }
};

const syncDokumen = (raw) => {
    dokumenList.value = Array.isArray(raw) ? raw : [];
};

const fetchPengajuan = async () => {
    try {
        const res = await apl01PengajuanService.getCurrent({
            kdlsp_periode_skema: route.params.id,
        });

        if (!res.data) return;

        pengajuan.value = res.data;
        syncDokumen(res.data.dokumen);
        form.value = mergeForm({
            data_pribadi: res.data.data_pribadi,
            data_pekerjaan: res.data.data_pekerjaan,
            data_sertifikasi: res.data.data_sertifikasi,
        });
        localStorage.removeItem(storageKey.value);
        applySkemaDefaults();
    } catch {
        pengajuan.value = null;
    }
};

const fetchUnits = async () => {
    const skemaId =
        plotting.value?.kdlsp_skema || plotting.value?.skema?.kdlsp_skema;
    if (!skemaId) return;
    try {
        const res = await unitKompetensiService.getAll({
            kdlsp_skema: skemaId,
        });
        unitList.value = Array.isArray(res.data) ? res.data : [];
    } catch {
        unitList.value = [];
    }
};

const fetchDetail = async () => {
    loading.value = true;
    error.value = "";
    try {
        const res = await plottingService.getOne(route.params.id);
        plotting.value = res.data;
        loadDraft();
        applySkemaDefaults();
        await fetchPengajuan();
        await fetchUnits();
    } catch (err) {
        error.value =
            err.response?.data?.message || "Gagal memuat detail skema";
    } finally {
        loading.value = false;
    }
};

// ── upload / hapus dokumen ────────────────────────────────────────────────────

const handleUpload = async (jenisDokumen, event) => {
    const file = event.target.files?.[0];
    if (!file || !pengajuanId.value) return;

    uploadingKey.value = jenisDokumen;
    error.value = "";

    try {
        const res = await apl01DokumenService.upload(
            pengajuanId.value,
            jenisDokumen,
            file,
        );
        // replace atau tambah ke list lokal
        const idx = dokumenList.value.findIndex(
            (d) => d.jenis_dokumen === jenisDokumen,
        );
        if (idx >= 0) {
            dokumenList.value[idx] = res.data;
        } else {
            dokumenList.value.push(res.data);
        }
        notice.value = "File berhasil diunggah.";
    } catch (err) {
        error.value = err.response?.data?.message || "Gagal mengunggah file";
    } finally {
        uploadingKey.value = null;
        // reset input supaya bisa upload ulang file yang sama
        event.target.value = "";
    }
};

const handleDeleteDokumen = async (jenisDokumen) => {
    const dok = getDokumen(jenisDokumen);
    if (!dok || !pengajuanId.value) return;
    if (!confirm("Hapus file ini?")) return;

    uploadingKey.value = jenisDokumen;
    error.value = "";

    try {
        await apl01DokumenService.destroy(
            pengajuanId.value,
            dok.kdlsp_apl01_dokumen,
        );
        dokumenList.value = dokumenList.value.filter(
            (d) => d.jenis_dokumen !== jenisDokumen,
        );
        notice.value = "File dihapus.";
    } catch (err) {
        error.value = err.response?.data?.message || "Gagal menghapus file";
    } finally {
        uploadingKey.value = null;
    }
};

// ── save / submit ─────────────────────────────────────────────────────────────

const makePayload = (submit = false) => ({
    kdlsp_periode_skema: route.params.id,
    data_pribadi: form.value.data_pribadi,
    data_pekerjaan: form.value.data_pekerjaan,
    data_sertifikasi: form.value.data_sertifikasi,
    data_persyaratan: buildPersyaratanPayload(),
    submit,
});

const saveDraft = async () => {
    saving.value = true;
    error.value = "";
    try {
        const res = await apl01PengajuanService.save(makePayload(false));
        pengajuan.value = res.data;
        syncDokumen(res.data.dokumen);
        localStorage.removeItem(storageKey.value);
        notice.value = "Draft permohonan tersimpan ke database.";
    } catch (err) {
        error.value =
            err.response?.data?.message || "Gagal menyimpan draft permohonan";
    } finally {
        saving.value = false;
    }
};

const markReady = async () => {
    saving.value = true;
    error.value = "";
    try {
        const res = await apl01PengajuanService.save(makePayload(true));
        pengajuan.value = res.data;
        syncDokumen(res.data.dokumen);
        localStorage.removeItem(storageKey.value);
        notice.value = "Pengajuan berhasil dikirim untuk review admin.";
    } catch (err) {
        error.value = err.response?.data?.message || "Gagal mengirim pengajuan";
    } finally {
        saving.value = false;
    }
};

const resetDraft = () => {
    if (!confirm("Kosongkan isian form ini?")) return;
    localStorage.removeItem(storageKey.value);
    form.value = makeForm();
    applySkemaDefaults();
    notice.value = "Isian form dikosongkan.";
};

onMounted(fetchDetail);
</script>

<template>
    <AppLayout>
        <div class="max-w-6xl mx-auto space-y-5">
            <!-- header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                    >
                        FR.APL.01
                    </p>
                    <h2 class="text-xl font-bold text-[#1e3329] mt-1">
                        Permohonan Sertifikasi Kompetensi
                    </h2>
                    <p class="text-[#7aab95] text-sm mt-1">
                        Rincian data pemohon dan data sertifikasi.
                    </p>
                </div>
                <button
                    type="button"
                    @click="router.push('/mahasiswa')"
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
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    Kembali
                </button>
            </div>

            <!-- notice -->
            <div
                v-if="notice"
                class="bg-[#eaf2ee] border border-[#c8ddd6] text-[#2d4a3e] rounded-lg px-4 py-3 text-sm font-medium flex items-center justify-between gap-3"
            >
                <span>{{ notice }}</span>
                <button
                    type="button"
                    @click="notice = ''"
                    class="text-[#4a7c6b] hover:text-[#2d4a3e]"
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
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- status bar -->
            <div
                v-if="pengajuan"
                class="flex flex-wrap items-center justify-between gap-3 bg-white border border-[#dde8e3] rounded-xl px-5 py-4 shadow-sm"
            >
                <div>
                    <p
                        class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                    >
                        Status Pengajuan
                    </p>
                    <p class="text-sm text-[#1e3329] mt-1">
                        {{
                            pengajuan.submitted_at
                                ? "Dikirim pada " +
                                  formatDate(pengajuan.submitted_at)
                                : "Belum dikirim untuk review admin"
                        }}
                    </p>
                </div>
                <span
                    :class="statusClass"
                    class="inline-flex items-center px-3 py-1.5 rounded-full border text-xs font-semibold"
                >
                    {{ statusLabel }}
                </span>
            </div>

            <!-- loading -->
            <div
                v-if="loading"
                class="bg-white border border-[#dde8e3] rounded-xl py-14 text-center text-[#7aab95]"
            >
                Memuat form permohonan...
            </div>

            <!-- error -->
            <div
                v-else-if="error && !saving"
                class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-5 py-4 text-sm"
            >
                {{ error }}
            </div>

            <!-- form -->
            <form v-else class="space-y-5" @submit.prevent="saveDraft">
                <!-- info skema -->
                <section
                    class="bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
                >
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <p
                                class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                            >
                                Skema
                            </p>
                            <p class="text-sm font-bold text-[#1e3329] mt-1">
                                {{ selectedSkemaName }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                            >
                                Periode
                            </p>
                            <p class="text-sm font-bold text-[#1e3329] mt-1">
                                {{ periodeName }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                            >
                                Masa
                            </p>
                            <p class="text-sm font-bold text-[#1e3329] mt-1">
                                {{ masaName }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- bagian 1 -->
                <section
                    class="bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
                >
                    <div class="mb-5">
                        <p
                            class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                        >
                            Bagian 1
                        </p>
                        <h3 class="text-base font-bold text-[#1e3329] mt-1">
                            Rincian Data Pemohon Sertifikasi
                        </h3>
                    </div>

                    <div class="space-y-6">
                        <!-- data pribadi -->
                        <div>
                            <h4
                                class="text-sm font-semibold text-[#2d4a3e] mb-3"
                            >
                                a. Data Pribadi
                            </h4>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div
                                    v-for="field in personalFields"
                                    :key="field.key"
                                    :class="field.span"
                                >
                                    <label
                                        class="block text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-1.5"
                                    >
                                        {{ field.label }}
                                    </label>
                                    <textarea
                                        v-if="field.textarea"
                                        v-model="form.data_pribadi[field.key]"
                                        rows="3"
                                        class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                                    ></textarea>
                                    <select
                                        v-else-if="field.options"
                                        v-model="form.data_pribadi[field.key]"
                                        class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                                    >
                                        <option value="">Pilih</option>
                                        <option
                                            v-for="option in field.options"
                                            :key="option"
                                            :value="option"
                                        >
                                            {{ option }}
                                        </option>
                                    </select>
                                    <input
                                        v-else
                                        v-model="form.data_pribadi[field.key]"
                                        :type="field.type || 'text'"
                                        :autocomplete="field.autocomplete"
                                        class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- data pekerjaan -->
                        <div>
                            <h4
                                class="text-sm font-semibold text-[#2d4a3e] mb-3"
                            >
                                b. Data Pekerjaan Sekarang
                            </h4>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div
                                    v-for="field in workFields"
                                    :key="field.key"
                                    :class="field.span"
                                >
                                    <label
                                        class="block text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-1.5"
                                    >
                                        {{ field.label }}
                                    </label>
                                    <textarea
                                        v-if="field.textarea"
                                        v-model="form.data_pekerjaan[field.key]"
                                        rows="3"
                                        class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                                    ></textarea>
                                    <input
                                        v-else
                                        v-model="form.data_pekerjaan[field.key]"
                                        :type="field.type || 'text'"
                                        class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- bagian 2 -->
                <section
                    class="bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
                >
                    <div class="mb-5">
                        <p
                            class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                        >
                            Bagian 2
                        </p>
                        <h3 class="text-base font-bold text-[#1e3329] mt-1">
                            Data Sertifikasi
                        </h3>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label
                                class="block text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-1.5"
                            >
                                Judul skema sertifikasi
                            </label>
                            <input
                                v-model="form.data_sertifikasi.judul_skema"
                                type="text"
                                class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-1.5"
                            >
                                Nomor skema sertifikasi
                            </label>
                            <input
                                v-model="form.data_sertifikasi.nomor_skema"
                                type="text"
                                class="w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                            />
                        </div>
                    </div>

                    <div class="mt-5">
                        <p
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-2"
                        >
                            Tujuan asesmen
                        </p>
                        <div class="grid gap-2 md:grid-cols-2">
                            <label
                                v-for="option in assessmentOptions"
                                :key="option"
                                class="flex items-center gap-3 border border-[#dde8e3] rounded-lg px-3 py-2.5 text-sm text-[#1e3329] bg-white hover:bg-[#f9fbfa]"
                            >
                                <input
                                    v-model="
                                        form.data_sertifikasi.tujuan_asesmen
                                    "
                                    type="radio"
                                    :value="option"
                                    class="accent-[#2d4a3e]"
                                />
                                <span>{{ option }}</span>
                            </label>
                        </div>
                        <input
                            v-if="
                                form.data_sertifikasi.tujuan_asesmen ===
                                'Lainnya'
                            "
                            v-model="form.data_sertifikasi.tujuan_lainnya"
                            type="text"
                            class="mt-3 w-full border border-[#c8ddd6] rounded-lg px-3 py-2 text-sm text-[#1e3329] focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 bg-white transition-all"
                            placeholder="Tulis tujuan asesmen lainnya"
                        />
                    </div>

                    <!-- unit kompetensi -->
                    <div v-if="unitList.length" class="mt-6">
                        <p
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider mb-2"
                        >
                            Unit kompetensi
                        </p>
                        <div
                            class="border border-[#dde8e3] rounded-lg overflow-hidden"
                        >
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="bg-[#f0f4f1] border-b border-[#dde8e3]"
                                    >
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                        >
                                            Kode unit
                                        </th>
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                        >
                                            Judul unit
                                        </th>
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                        >
                                            Standar
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="unit in unitList"
                                        :key="unit.kdlsp_skema_unitkompetensi"
                                        class="border-b border-[#f0f4f1] last:border-b-0"
                                    >
                                        <td
                                            class="px-4 py-3 font-medium text-[#1e3329]"
                                        >
                                            {{ unit.kode_unit }}
                                        </td>
                                        <td class="px-4 py-3 text-[#1e3329]">
                                            {{ unit.judul_unit }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-500">
                                            {{ unit.standar_kompetensi_kerja }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- BAGIAN 3                                                   -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <section
                    class="bg-white border border-[#dde8e3] rounded-xl p-5 shadow-sm"
                >
                    <div class="mb-5">
                        <p
                            class="text-xs font-semibold text-[#7aab95] uppercase tracking-wider"
                        >
                            Bagian 3
                        </p>
                        <h3 class="text-base font-bold text-[#1e3329] mt-1">
                            Bukti Kelengkapan Pemohon
                        </h3>
                        <p class="text-sm text-[#7aab95] mt-1">
                            Unggah dokumen pendukung. File yang diterima: PDF,
                            JPG, PNG (maks. 5 MB).
                        </p>
                    </div>

                    <!-- peringatan: harus simpan draft dulu sebelum bisa upload -->
                    <div
                        v-if="!pengajuanId && canEdit"
                        class="mb-5 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-3 text-sm"
                    >
                        Simpan draft terlebih dahulu agar Anda dapat mengunggah
                        dokumen.
                    </div>

                    <!-- 3.1 Bukti Persyaratan Dasar -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-[#2d4a3e] mb-3">
                            3.1 Bukti Persyaratan Dasar
                        </h4>
                        <div
                            class="border border-[#dde8e3] rounded-lg overflow-hidden"
                        >
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="bg-[#f0f4f1] border-b border-[#dde8e3]"
                                    >
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-8"
                                        >
                                            No
                                        </th>
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                        >
                                            Bukti Persyaratan Dasar
                                        </th>
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-48"
                                        >
                                            Dokumen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in persyaratanDasar"
                                        :key="item.jenis_dokumen"
                                        class="border-b border-[#f0f4f1] last:border-b-0"
                                    >
                                        <td
                                            class="px-4 py-3 text-[#1e3329] align-top"
                                        >
                                            {{ item.no }}.
                                        </td>
                                        <td
                                            class="px-4 py-3 text-[#1e3329] align-top leading-relaxed"
                                        >
                                            {{ item.label }}
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <template v-if="item.hasUpload">
                                                <!-- sudah ada file -->
                                                <div
                                                    v-if="
                                                        getDokumen(
                                                            item.jenis_dokumen,
                                                        )
                                                    "
                                                    class="space-y-1.5"
                                                >
                                                    <a
                                                        :href="
                                                            getFileUrl(
                                                                getDokumen(
                                                                    item.jenis_dokumen,
                                                                ).file_path,
                                                            )
                                                        "
                                                        target="_blank"
                                                        class="flex items-center gap-1.5 text-xs text-[#2d4a3e] font-medium hover:underline"
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
                                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                                            />
                                                        </svg>
                                                        <span
                                                            class="truncate max-w-[120px]"
                                                            >{{
                                                                getDokumen(
                                                                    item.jenis_dokumen,
                                                                ).original_name
                                                            }}</span
                                                        >
                                                    </a>
                                                    <button
                                                        v-if="canEdit"
                                                        type="button"
                                                        @click="
                                                            handleDeleteDokumen(
                                                                item.jenis_dokumen,
                                                            )
                                                        "
                                                        :disabled="
                                                            uploadingKey ===
                                                            item.jenis_dokumen
                                                        "
                                                        class="text-xs text-red-500 hover:text-red-700 disabled:opacity-50"
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>

                                                <!-- belum ada file -->
                                                <div v-else>
                                                    <label
                                                        v-if="
                                                            canEdit &&
                                                            pengajuanId
                                                        "
                                                        class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-[#2d4a3e] border border-[#c8ddd6] rounded-lg px-3 py-1.5 hover:bg-[#f0f4f1] transition-all"
                                                        :class="{
                                                            'opacity-50 pointer-events-none':
                                                                uploadingKey ===
                                                                item.jenis_dokumen,
                                                        }"
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
                                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                                            />
                                                        </svg>
                                                        {{
                                                            uploadingKey ===
                                                            item.jenis_dokumen
                                                                ? "Mengunggah..."
                                                                : "Unggah"
                                                        }}
                                                        <input
                                                            type="file"
                                                            class="sr-only"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            @change="
                                                                handleUpload(
                                                                    item.jenis_dokumen,
                                                                    $event,
                                                                )
                                                            "
                                                        />
                                                    </label>
                                                    <span
                                                        v-else
                                                        class="text-xs text-slate-400 italic"
                                                        >Belum diunggah</span
                                                    >
                                                </div>
                                            </template>
                                            <span
                                                v-else
                                                class="text-xs text-slate-400"
                                                >—</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3.2 Bukti Administratif -->
                    <div>
                        <h4 class="text-sm font-semibold text-[#2d4a3e] mb-3">
                            3.2 Bukti Administratif
                        </h4>
                        <div
                            class="border border-[#dde8e3] rounded-lg overflow-hidden"
                        >
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="bg-[#f0f4f1] border-b border-[#dde8e3]"
                                    >
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-8"
                                        >
                                            No
                                        </th>
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                                        >
                                            Bukti Administratif
                                        </th>
                                        <th
                                            class="text-left px-4 py-3 text-xs font-semibold text-[#3d6355] uppercase tracking-wider w-48"
                                        >
                                            Dokumen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in persyaratanAdministratif"
                                        :key="item.jenis_dokumen"
                                        class="border-b border-[#f0f4f1] last:border-b-0"
                                    >
                                        <td
                                            class="px-4 py-3 text-[#1e3329] align-top"
                                        >
                                            {{ item.no }}.
                                        </td>
                                        <td
                                            class="px-4 py-3 text-[#1e3329] align-top leading-relaxed"
                                        >
                                            {{ item.label }}
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <template v-if="item.hasUpload">
                                                <!-- sudah ada file -->
                                                <div
                                                    v-if="
                                                        getDokumen(
                                                            item.jenis_dokumen,
                                                        )
                                                    "
                                                    class="space-y-1.5"
                                                >
                                                    <a
                                                        :href="
                                                            getFileUrl(
                                                                getDokumen(
                                                                    item.jenis_dokumen,
                                                                ).file_path,
                                                            )
                                                        "
                                                        target="_blank"
                                                        class="flex items-center gap-1.5 text-xs text-[#2d4a3e] font-medium hover:underline"
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
                                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                                            />
                                                        </svg>
                                                        <span
                                                            class="truncate max-w-[120px]"
                                                            >{{
                                                                getDokumen(
                                                                    item.jenis_dokumen,
                                                                ).original_name
                                                            }}</span
                                                        >
                                                    </a>
                                                    <button
                                                        v-if="canEdit"
                                                        type="button"
                                                        @click="
                                                            handleDeleteDokumen(
                                                                item.jenis_dokumen,
                                                            )
                                                        "
                                                        :disabled="
                                                            uploadingKey ===
                                                            item.jenis_dokumen
                                                        "
                                                        class="text-xs text-red-500 hover:text-red-700 disabled:opacity-50"
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>

                                                <!-- belum ada file -->
                                                <div v-else>
                                                    <template
                                                        v-if="
                                                            canEdit &&
                                                            pengajuanId
                                                        "
                                                    >
                                                        <button
                                                            type="button"
                                                            @click.stop="
                                                                triggerUpload(
                                                                    item.jenis_dokumen,
                                                                )
                                                            "
                                                            :disabled="
                                                                uploadingKey ===
                                                                item.jenis_dokumen
                                                            "
                                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#2d4a3e] border border-[#c8ddd6] rounded-lg px-3 py-1.5 hover:bg-[#f0f4f1] transition-all disabled:opacity-50"
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
                                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                                                />
                                                            </svg>
                                                            {{
                                                                uploadingKey ===
                                                                item.jenis_dokumen
                                                                    ? "Mengunggah..."
                                                                    : "Unggah"
                                                            }}
                                                        </button>
                                                        <input
                                                            :ref="
                                                                (el) => {
                                                                    if (el)
                                                                        fileInputRefs[
                                                                            item.jenis_dokumen
                                                                        ] = el;
                                                                }
                                                            "
                                                            type="file"
                                                            class="sr-only"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            @change="
                                                                handleUpload(
                                                                    item.jenis_dokumen,
                                                                    $event,
                                                                )
                                                            "
                                                        />
                                                    </template>
                                                    <span
                                                        v-else
                                                        class="text-xs text-slate-400 italic"
                                                        >Belum diunggah</span
                                                    >
                                                </div>
                                            </template>
                                            <span
                                                v-else
                                                class="text-xs text-slate-400"
                                                >—</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- action buttons -->
                <div class="flex flex-wrap justify-end gap-2 pb-6">
                    <button
                        type="button"
                        @click="resetDraft"
                        :disabled="saving || !canEdit"
                        class="border border-[#c8ddd6] bg-white hover:bg-[#f7faf8] disabled:opacity-50 text-slate-600 text-sm font-semibold px-4 py-2.5 rounded-lg transition-all"
                    >
                        Kosongkan
                    </button>
                    <button
                        type="submit"
                        :disabled="saving || !canEdit"
                        class="border border-[#c8ddd6] bg-white hover:bg-[#f7faf8] disabled:opacity-50 text-[#2d4a3e] text-sm font-semibold px-4 py-2.5 rounded-lg transition-all"
                    >
                        {{ saving ? "Menyimpan..." : "Simpan Draft" }}
                    </button>
                    <button
                        type="button"
                        @click="markReady"
                        :disabled="saving || !canEdit"
                        class="bg-[#2d4a3e] hover:bg-[#3d6355] disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-all"
                    >
                        {{ saving ? "Mengirim..." : "Tandai Siap" }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
