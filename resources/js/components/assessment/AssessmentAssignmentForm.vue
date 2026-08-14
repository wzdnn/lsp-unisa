<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { assessmentService, userService } from '../../services/lspService';
import AssessmentReadOnlyDocument from './AssessmentReadOnlyDocument.vue';

const emit = defineEmits(['toast']);
const processes = ref([]);
const users = ref([]);
const loading = ref(false);
const assigning = ref(null);
const selections = ref({});
const activeTab = ref('all');
const search = ref('');
const stageFilter = ref('all');
const assessorFilter = ref('all');
const schemeFilter = ref('all');
const page = ref(1);
const perPage = ref(10);
const expandedRows = ref([]);
const preview = ref(null);
const previewLoading = ref(false);
const preAssessmentCodes = ['FR.APL.02', 'FR.MAPA.01', 'FR.MAPA.02', 'FR.AK.01', 'FR.AK.07'];

const assessors = computed(() => users.value.filter((user) =>
    user.role === 'asesor_luar' || (user.role === 'dosen' && user.isAsesor)
));
const assessorName = (assessor) => assessor?.person?.namalengkap || assessor?.namalengkap || assessor?.username || '-';
const participantName = (process) => process.asesi?.person?.namalengkap || process.asesi?.namalengkap || process.asesi?.username || '-';
const apl02 = (process) => process.assignments?.find((assignment) => assignment.version?.form?.code === 'FR.APL.02');
const hasApl02 = (process) => Boolean(apl02(process));
const isFinished = (process) => process.status === 'completed';
const stageLabel = (stage) => ({ pra_asesmen: 'Pra-asesmen', asesmen: 'Asesmen', pasca_asesmen: 'Pasca-asesmen', keputusan: 'Keputusan' }[stage] || stage || '-');
const assignmentStatus = (process) => {
    const status = apl02(process)?.status;
    return ({ assigned: 'Ditugaskan', draft: 'Draft', submitted: 'Menunggu review', under_review: 'Sedang dinilai', revision_required: 'Perlu revisi', completed: 'Selesai' }[status] || (hasApl02(process) ? status : 'Belum ditugaskan'));
};
const statusClass = (process) => {
    if (!hasApl02(process)) return 'bg-amber-50 text-amber-700';
    if (apl02(process)?.status === 'revision_required') return 'bg-orange-50 text-orange-700';
    if (['completed', 'assessed', 'result_published'].includes(apl02(process)?.status)) return 'bg-emerald-50 text-emerald-700';
    return 'bg-blue-50 text-blue-700';
};
const isExpanded = (id) => expandedRows.value.includes(id);
const toggleExpanded = (id) => { expandedRows.value = isExpanded(id) ? expandedRows.value.filter((item) => item !== id) : [...expandedRows.value, id]; };
const preAssessmentForms = (process) => preAssessmentCodes.map((code) => ({
    code,
    assignment: process.assignments?.find((item) => item.version?.form?.code === code) || null,
}));
const formStatusLabel = (item) => item.assignment ? ({ assigned: 'Ditugaskan', draft: 'Draft', submitted: 'Menunggu review', under_review: 'Sedang dinilai', revision_required: 'Perlu revisi', assessed: 'Sudah dinilai', result_published: 'Hasil terbit', completed: 'Selesai' }[item.assignment.status] || item.assignment.status) : 'Belum tersedia';
const formStatusClass = (item) => {
    const value = item.assignment?.status;
    if (!value) return 'bg-slate-100 text-slate-500';
    if (value === 'revision_required') return 'bg-orange-50 text-orange-700';
    if (['completed', 'assessed', 'result_published'].includes(value)) return 'bg-emerald-50 text-emerald-700';
    if (['submitted', 'under_review'].includes(value)) return 'bg-blue-50 text-blue-700';
    return 'bg-amber-50 text-amber-700';
};
const openPreview = async (assignment) => {
    if (!assignment) return;
    previewLoading.value = true;
    try { preview.value = (await assessmentService.getOne(assignment.id)).data; }
    catch (error) { emit('toast', { type: 'error', message: error.response?.data?.message || 'Gagal membuka dokumen' }); }
    finally { previewLoading.value = false; }
};

const load = async () => {
    loading.value = true;
    try {
        const [processResponse, userResponse] = await Promise.all([
            assessmentService.getProcesses(),
            userService.getAll(),
        ]);
        processes.value = processResponse.data;
        users.value = userResponse.data;
        for (const process of processes.value) {
            selections.value[process.id] ??= { assessor_id: process.assessor_id || '', due_at: '' };
        }
    } catch (error) {
        emit('toast', { type: 'error', message: error.response?.data?.message || 'Gagal memuat proses assessment' });
    } finally {
        loading.value = false;
    }
};

const schemes = computed(() => {
    const values = new Map();
    processes.value.forEach((process) => {
        const id = process.periode_skema?.skema?.kdlsp_skema;
        const name = process.periode_skema?.skema?.skema;
        if (id && name) values.set(String(id), name);
    });
    return [...values.entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));
});
const tabs = computed(() => [
    { key: 'all', label: 'Semua', count: processes.value.length },
    { key: 'unassigned', label: 'Belum Ditugaskan', count: processes.value.filter((item) => !hasApl02(item)).length },
    { key: 'assigned', label: 'Sudah Ditugaskan', count: processes.value.filter((item) => hasApl02(item) && !isFinished(item)).length },
    { key: 'completed', label: 'Proses Selesai', count: processes.value.filter(isFinished).length },
]);
const filtered = computed(() => processes.value.filter((process) => {
    if (activeTab.value === 'unassigned' && hasApl02(process)) return false;
    if (activeTab.value === 'assigned' && (!hasApl02(process) || isFinished(process))) return false;
    if (activeTab.value === 'completed' && !isFinished(process)) return false;
    if (stageFilter.value !== 'all' && process.current_stage !== stageFilter.value) return false;
    if (assessorFilter.value !== 'all' && String(process.assessor_id || '') !== assessorFilter.value) return false;
    if (schemeFilter.value !== 'all' && String(process.periode_skema?.skema?.kdlsp_skema || '') !== schemeFilter.value) return false;
    const value = `${participantName(process)} ${process.asesi?.username || ''} ${assessorName(process.assessor)} ${process.periode_skema?.skema?.skema || ''} ${process.id}`.toLowerCase();
    return value.includes(search.value.trim().toLowerCase());
}));
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)));
const paginated = computed(() => filtered.value.slice((page.value - 1) * perPage.value, page.value * perPage.value));
const rangeStart = computed(() => filtered.value.length ? (page.value - 1) * perPage.value + 1 : 0);
const rangeEnd = computed(() => Math.min(page.value * perPage.value, filtered.value.length));

const assign = async (process) => {
    assigning.value = process.id;
    try {
        await assessmentService.assignApl02(process.id, selections.value[process.id]);
        emit('toast', { message: 'Asesor ditetapkan dan APL-02 berhasil diberikan kepada asesi' });
        await load();
    } catch (error) {
        const validation = error.response?.data?.errors;
        emit('toast', { type: 'error', message: validation ? Object.values(validation).flat()[0] : (error.response?.data?.message || 'Gagal membuat penugasan') });
    } finally {
        assigning.value = null;
    }
};

watch([activeTab, search, stageFilter, assessorFilter, schemeFilter, perPage], () => { page.value = 1; });
watch(totalPages, (value) => { if (page.value > value) page.value = value; });
onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">
            Proses muncul otomatis setelah APL.01 diterima. Tetapkan asesor untuk memberikan FR.APL.02 kepada asesi.
        </div>

        <div class="flex gap-2 overflow-x-auto border-b border-[#dde8e3]">
            <button v-for="tab in tabs" :key="tab.key" type="button" @click="activeTab = tab.key" class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm font-semibold transition" :class="activeTab === tab.key ? 'border-[#2d4a3e] text-[#2d4a3e]' : 'border-transparent text-slate-400 hover:text-[#4a7c6b]'">
                {{ tab.label }} <span class="rounded-full px-2 py-0.5 text-xs" :class="activeTab === tab.key ? 'bg-[#eaf2ee]' : 'bg-slate-100'">{{ tab.count }}</span>
            </button>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_180px_200px_220px_auto]">
            <input v-model="search" class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm" placeholder="Cari nama, NIM, skema, asesor, atau ID...">
            <select v-model="stageFilter" class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm"><option value="all">Semua tahap</option><option value="pra_asesmen">Pra-asesmen</option><option value="asesmen">Asesmen</option><option value="pasca_asesmen">Pasca-asesmen</option><option value="keputusan">Keputusan</option></select>
            <select v-model="schemeFilter" class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm"><option value="all">Semua skema</option><option v-for="scheme in schemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option></select>
            <select v-model="assessorFilter" class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm"><option value="all">Semua asesor</option><option value="">Belum ada asesor</option><option v-for="assessor in assessors" :key="assessor.kdlsp_user" :value="String(assessor.kdlsp_user)">{{ assessorName(assessor) }}</option></select>
            <button type="button" @click="load" class="rounded-lg border border-[#4a7c6b] px-4 py-2.5 text-sm font-semibold text-[#2d4a3e]">Muat Ulang</button>
        </div>

        <div class="overflow-hidden rounded-xl border border-[#dde8e3]">
            <div v-if="loading" class="py-14 text-center text-sm text-slate-400">Memuat proses dari APL.01...</div>
            <div v-else-if="!filtered.length" class="py-14 text-center"><p class="text-sm font-medium text-slate-500">Tidak ada proses pada filter ini.</p><p class="mt-1 text-xs text-slate-400">Coba ubah tab, pencarian, atau filter yang digunakan.</p></div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-[1360px] w-full divide-y divide-[#e7efeb] text-sm">
                    <thead class="bg-[#f4f8f6] text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">No.</th><th class="px-4 py-3">Asesi</th><th class="px-4 py-3">Skema / Periode</th><th class="px-4 py-3">Tahap</th><th class="px-4 py-3">Status APL.02</th><th class="px-4 py-3">Asesor</th><th class="px-4 py-3">Tenggat</th><th class="px-4 py-3 text-right">Aksi</th><th class="px-4 py-3 text-right">Detail</th></tr></thead>
                    <tbody class="divide-y divide-[#edf3f0] bg-white">
                        <template v-for="(process, index) in paginated" :key="process.id">
                        <tr class="align-top hover:bg-[#f9fbfa]" :class="isExpanded(process.id) ? 'bg-[#f7faf8]' : ''">
                            <td class="px-4 py-4 text-slate-400">{{ (page - 1) * perPage + index + 1 }}</td>
                            <td class="px-4 py-4"><p class="font-semibold text-[#1e3329]">{{ participantName(process) }}</p><p class="mt-0.5 text-xs text-slate-500">NIM: {{ process.asesi?.username || '-' }} · #{{ process.id }}</p></td>
                            <td class="max-w-60 px-4 py-4"><p class="font-medium text-slate-700">{{ process.periode_skema?.skema?.skema || '-' }}</p><p class="mt-0.5 text-xs text-slate-400">{{ process.periode_skema?.periode?.periode || '-' }}</p></td>
                            <td class="px-4 py-4"><span class="whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ stageLabel(process.current_stage) }}</span></td>
                            <td class="px-4 py-4"><span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(process)">{{ assignmentStatus(process) }}</span></td>
                            <template v-if="!hasApl02(process)">
                                <td class="px-4 py-3"><select v-model="selections[process.id].assessor_id" class="w-full min-w-48 rounded-lg border border-[#c8ddd6] p-2 text-xs"><option value="">Pilih asesor aktif</option><option v-for="assessor in assessors" :key="assessor.kdlsp_user" :value="assessor.kdlsp_user">{{ assessorName(assessor) }}</option></select></td>
                                <td class="px-4 py-3"><input v-model="selections[process.id].due_at" type="datetime-local" class="w-full min-w-48 rounded-lg border border-[#c8ddd6] p-2 text-xs"></td>
                                <td class="px-4 py-3 text-right"><button @click="assign(process)" :disabled="assigning === process.id || !selections[process.id].assessor_id" class="whitespace-nowrap rounded-lg bg-[#2d4a3e] px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">{{ assigning === process.id ? 'Memproses...' : 'Tetapkan Asesor' }}</button></td>
                            </template>
                            <template v-else>
                                <td class="px-4 py-4"><p class="font-medium text-slate-700">{{ assessorName(process.assessor) }}</p><p class="text-xs text-slate-400">{{ process.assessor?.role === 'asesor_luar' ? 'Asesor eksternal' : 'Asesor internal' }}</p></td>
                                <td class="px-4 py-4 text-xs text-slate-600">{{ apl02(process)?.due_at ? new Date(apl02(process).due_at).toLocaleString('id-ID') : 'Tidak ditentukan' }}</td>
                                <td class="px-4 py-4 text-right text-xs font-medium text-emerald-700">Sudah ditugaskan</td>
                            </template>
                            <td class="px-4 py-4 text-right"><button type="button" @click="toggleExpanded(process.id)" class="inline-flex items-center gap-1 text-xs font-semibold text-[#4a7c6b]">{{ isExpanded(process.id) ? 'Tutup' : 'Lihat form' }}<svg class="h-4 w-4 transition-transform" :class="isExpanded(process.id) ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></button></td>
                        </tr>
                        <tr v-if="isExpanded(process.id)" class="bg-[#f7faf8]"><td colspan="9" class="px-5 pb-5 pt-1"><div class="overflow-hidden rounded-xl border border-[#dde8e3] bg-white"><div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e7efeb] px-4 py-3"><div><p class="text-xs font-bold uppercase tracking-wide text-[#2d4a3e]">Dokumen pra-assessment</p><p class="text-xs text-slate-400">Template published yang menjadi dokumen transaksi peserta.</p></div><span class="text-xs font-semibold text-slate-500">{{ preAssessmentForms(process).filter((item) => item.assignment && ['completed','assessed','result_published'].includes(item.assignment.status)).length }}/5 selesai</span></div><div class="overflow-x-auto"><table class="min-w-full text-xs"><thead class="bg-slate-50 text-left uppercase text-slate-400"><tr><th class="px-4 py-2.5">Form</th><th class="px-4 py-2.5">Nama dokumen</th><th class="px-4 py-2.5">Versi</th><th class="px-4 py-2.5">Pengisi</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">Tenggat</th><th class="px-4 py-2.5 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in preAssessmentForms(process)" :key="item.code"><td class="px-4 py-3 font-bold text-[#1e3329]">{{ item.code }}</td><td class="px-4 py-3 text-slate-600">{{ item.assignment?.version?.form?.name || 'Menunggu template/aktivasi tahap' }}</td><td class="px-4 py-3 text-slate-600">{{ item.assignment ? `v${item.assignment.version?.version}` : '-' }}</td><td class="px-4 py-3 capitalize text-slate-600">{{ item.assignment?.version?.form?.filled_by || '-' }}</td><td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 font-semibold" :class="formStatusClass(item)">{{ formStatusLabel(item) }}</span></td><td class="px-4 py-3 text-slate-500">{{ item.assignment?.due_at ? new Date(item.assignment.due_at).toLocaleString('id-ID') : '-' }}</td><td class="px-4 py-3 text-right"><button v-if="item.assignment" type="button" @click="openPreview(item.assignment)" class="font-semibold text-[#4a7c6b]">Lihat dokumen</button><span v-else class="text-slate-300">Belum tersedia</span></td></tr></tbody></table></div></div></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="filtered.length" class="flex flex-col items-center justify-between gap-3 text-sm text-slate-500 sm:flex-row">
            <div class="flex items-center gap-2"><span>Menampilkan {{ rangeStart }}–{{ rangeEnd }} dari {{ filtered.length }}</span><select v-model="perPage" class="rounded-lg border border-[#c8ddd6] px-2 py-1.5 text-xs"><option :value="10">10 / halaman</option><option :value="25">25 / halaman</option><option :value="50">50 / halaman</option></select></div>
            <div class="flex items-center gap-2"><button type="button" :disabled="page === 1" @click="page--" class="rounded-lg border px-3 py-1.5 text-xs disabled:opacity-40">Sebelumnya</button><span>Halaman {{ page }} dari {{ totalPages }}</span><button type="button" :disabled="page === totalPages" @click="page++" class="rounded-lg border px-3 py-1.5 text-xs disabled:opacity-40">Berikutnya</button></div>
        </div>

        <div v-if="preview || previewLoading" class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-slate-950/60 p-4 sm:p-8" @click.self="preview = null"><div class="w-full max-w-6xl"><div class="mb-3 flex justify-end"><button type="button" @click="preview = null" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow">Tutup dokumen</button></div><div v-if="previewLoading" class="rounded-xl bg-white p-16 text-center text-sm text-slate-400">Memuat dokumen...</div><div v-else class="overflow-x-auto pb-8"><AssessmentReadOnlyDocument :assignment="preview" /></div></div></div>
    </div>
</template>
