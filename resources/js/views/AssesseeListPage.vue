<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppLayout from '../layouts/AppLayout.vue';
import { assessmentService } from '../services/lspService';

const router = useRouter();
const assignments = ref([]);
const loading = ref(false);
const error = ref('');
const viewMode = ref(localStorage.getItem('assessee-list-view') || 'table');
const activeTab = ref('all');
const search = ref('');
const intakeFilter = ref('all');
const schemeFilter = ref('all');
const stageFilter = ref('all');
const page = ref(1);
const perPage = ref(10);

const setViewMode = (mode) => { viewMode.value = mode; localStorage.setItem('assessee-list-view', mode); };
const asesiName = (process) => process?.asesi?.person?.namalengkap || process?.asesi?.namalengkap || process?.asesi?.username || '-';
const countStatus = (items, statuses) => items.filter((item) => statuses.includes(item.status)).length;
const stageLabel = (stage) => ({ pra_asesmen: 'Pra-asesmen', asesmen: 'Asesmen', pasca_asesmen: 'Pasca-asesmen', keputusan: 'Keputusan' }[stage] || stage || '-');
const formatDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
const intakeLabel = (process) => {
    const plot = process.periode_skema;
    const period = plot?.periode?.periode || 'Periode';
    const masa = plot?.masa_periode;
    return masa ? `${period} · ${formatDate(masa.tanggal_mulai)}–${formatDate(masa.tanggal_selesai)}` : period;
};
const participantState = (participant) => {
    const items = participant.assignments;
    if (participant.process.status === 'completed') return 'completed';
    if (countStatus(items, ['revision_required'])) return 'revision';
    if (countStatus(items, ['submitted', 'under_review'])) return 'action';
    return 'active';
};
const stateLabel = (state) => ({ action: 'Perlu dinilai', revision: 'Menunggu revisi', active: 'Berjalan', completed: 'Selesai' }[state] || state);
const stateClass = (state) => ({ action: 'bg-amber-50 text-amber-700', revision: 'bg-orange-50 text-orange-700', active: 'bg-blue-50 text-blue-700', completed: 'bg-emerald-50 text-emerald-700' }[state]);

const allParticipants = computed(() => {
    const groups = new Map();
    assignments.value.forEach((assignment) => {
        const id = assignment.process?.id;
        if (!id) return;
        if (!groups.has(id)) groups.set(id, { process: assignment.process, assignments: [] });
        groups.get(id).assignments.push(assignment);
    });
    return [...groups.values()];
});
const schemes = computed(() => [...new Map(allParticipants.value.map(({ process }) => [String(process.periode_skema?.skema?.kdlsp_skema || ''), process.periode_skema?.skema?.skema]).filter(([id, name]) => id && name)).entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name)));
const intakes = computed(() => [...new Map(allParticipants.value.map(({ process }) => [String(process.kdlsp_periode_skema || ''), intakeLabel(process)]).filter(([id]) => id)).entries()].map(([id, name]) => ({ id, name })));
const tabs = computed(() => [
    { key: 'all', label: 'Semua Asesi', count: allParticipants.value.length },
    { key: 'action', label: 'Perlu Dinilai', count: allParticipants.value.filter((item) => participantState(item) === 'action').length },
    { key: 'revision', label: 'Menunggu Revisi', count: allParticipants.value.filter((item) => participantState(item) === 'revision').length },
    { key: 'active', label: 'Berjalan', count: allParticipants.value.filter((item) => participantState(item) === 'active').length },
    { key: 'completed', label: 'Selesai', count: allParticipants.value.filter((item) => participantState(item) === 'completed').length },
]);
const filtered = computed(() => allParticipants.value.filter((participant) => {
    const process = participant.process;
    if (activeTab.value !== 'all' && participantState(participant) !== activeTab.value) return false;
    if (intakeFilter.value !== 'all' && String(process.kdlsp_periode_skema) !== intakeFilter.value) return false;
    if (schemeFilter.value !== 'all' && String(process.periode_skema?.skema?.kdlsp_skema || '') !== schemeFilter.value) return false;
    if (stageFilter.value !== 'all' && process.current_stage !== stageFilter.value) return false;
    const value = `${asesiName(process)} ${process.asesi?.username || ''} ${process.periode_skema?.skema?.skema || ''} ${intakeLabel(process)}`.toLowerCase();
    return value.includes(search.value.trim().toLowerCase());
}));
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)));
const paginated = computed(() => filtered.value.slice((page.value - 1) * perPage.value, page.value * perPage.value));
const rangeStart = computed(() => filtered.value.length ? (page.value - 1) * perPage.value + 1 : 0);
const rangeEnd = computed(() => Math.min(page.value * perPage.value, filtered.value.length));
const resetFilters = () => { search.value = ''; intakeFilter.value = 'all'; schemeFilter.value = 'all'; stageFilter.value = 'all'; activeTab.value = 'all'; };
const openParticipant = ({ process, assignments: items }) => {
    const path = countStatus(items, ['under_review']) ? '/assessments/reviewing' : countStatus(items, ['submitted']) ? '/assessments/pending' : countStatus(items, ['revision_required']) ? '/assessments/assessor-revisions' : '/assessments/completed';
    router.push({ path, query: { process: process.id } });
};
const load = async () => {
    loading.value = true; error.value = '';
    try { assignments.value = (await assessmentService.getAll()).data; }
    catch (exception) { error.value = exception.response?.data?.message || 'Gagal memuat daftar asesi'; }
    finally { loading.value = false; }
};

watch([activeTab, search, intakeFilter, schemeFilter, stageFilter, perPage], () => { page.value = 1; });
watch(totalPages, (value) => { if (page.value > value) page.value = value; });
onMounted(load);
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-xl font-bold text-[#1e3329]">Daftar Asesi Saya</h2><p class="mt-1 text-sm text-[#7aab95]">Pilih asesi berdasarkan sesi atau intake sebelum membuka penilaian.</p></div><div class="inline-flex rounded-lg border border-[#dde8e3] bg-white p-1"><button @click="setViewMode('card')" class="rounded-md px-3 py-1.5 text-xs font-semibold" :class="viewMode === 'card' ? 'bg-[#eaf2ee] text-[#2d4a3e]' : 'text-slate-400'">Card</button><button @click="setViewMode('table')" class="rounded-md px-3 py-1.5 text-xs font-semibold" :class="viewMode === 'table' ? 'bg-[#eaf2ee] text-[#2d4a3e]' : 'text-slate-400'">Tabel</button></div></div>
        <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

        <div class="mb-5 rounded-2xl border border-[#dde8e3] bg-white p-4 shadow-sm">
            <div class="flex gap-2 overflow-x-auto border-b border-[#e7efeb]"><button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-xs font-semibold" :class="activeTab === tab.key ? 'border-[#2d4a3e] text-[#2d4a3e]' : 'border-transparent text-slate-400'">{{ tab.label }}<span class="rounded-full bg-slate-100 px-2 py-0.5">{{ tab.count }}</span></button></div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_240px_220px_180px_auto]"><input v-model="search" type="search" placeholder="Cari nama, NIM, skema..." class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm"><select v-model="intakeFilter" class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm"><option value="all">Semua sesi / intake</option><option v-for="intake in intakes" :key="intake.id" :value="intake.id">{{ intake.name }}</option></select><select v-model="schemeFilter" class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm"><option value="all">Semua skema</option><option v-for="scheme in schemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option></select><select v-model="stageFilter" class="rounded-lg border border-[#c8ddd6] px-3 py-2.5 text-sm"><option value="all">Semua tahap</option><option value="pra_asesmen">Pra-asesmen</option><option value="asesmen">Asesmen</option><option value="pasca_asesmen">Pasca-asesmen</option><option value="keputusan">Keputusan</option></select><button @click="resetFilters" class="rounded-lg border border-[#4a7c6b] px-4 py-2.5 text-xs font-semibold text-[#2d4a3e]">Reset</button></div>
        </div>

        <div v-if="loading" class="py-16 text-center text-sm text-slate-400">Memuat daftar asesi...</div>
        <div v-else-if="!filtered.length" class="rounded-2xl border border-dashed border-[#c8ddd6] bg-white py-16 text-center"><p class="text-sm text-slate-400">Tidak ada asesi pada filter ini.</p><button @click="resetFilters" class="mt-2 text-xs font-semibold text-[#4a7c6b]">Reset filter</button></div>

        <div v-else-if="viewMode === 'card'" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3"><article v-for="participant in paginated" :key="participant.process.id" class="rounded-2xl border border-[#dde8e3] bg-white p-5 shadow-sm"><div class="flex items-start justify-between gap-3"><div><p class="text-xs uppercase text-[#7aab95]">{{ participant.process.periode_skema?.skema?.skema || 'Skema sertifikasi' }}</p><h3 class="mt-1 font-bold text-[#1e3329]">{{ asesiName(participant.process) }}</h3><p class="text-sm text-slate-500">NIM: {{ participant.process.asesi?.username }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="stateClass(participantState(participant))">{{ stateLabel(participantState(participant)) }}</span></div><p class="mt-3 text-xs text-slate-400">{{ intakeLabel(participant.process) }} · {{ stageLabel(participant.process.current_stage) }}</p><div class="mt-4 grid grid-cols-3 gap-2 text-center"><div class="rounded-lg bg-amber-50 p-2"><strong class="block text-amber-700">{{ countStatus(participant.assignments, ['submitted']) }}</strong><span class="text-[10px] text-amber-600">Menunggu</span></div><div class="rounded-lg bg-blue-50 p-2"><strong class="block text-blue-700">{{ countStatus(participant.assignments, ['under_review']) }}</strong><span class="text-[10px] text-blue-600">Dinilai</span></div><div class="rounded-lg bg-emerald-50 p-2"><strong class="block text-emerald-700">{{ countStatus(participant.assignments, ['completed','assessed','result_published']) }}</strong><span class="text-[10px] text-emerald-600">Selesai</span></div></div><button @click="openParticipant(participant)" class="mt-5 w-full rounded-xl bg-[#2d4a3e] px-4 py-2.5 text-sm font-semibold text-white">Buka Penilaian</button></article></div>

        <div v-else class="overflow-x-auto rounded-2xl border border-[#dde8e3] bg-white"><table class="min-w-[1050px] w-full divide-y divide-[#e7efeb] text-sm"><thead class="bg-[#f4f8f6] text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">No.</th><th class="px-4 py-3">Asesi</th><th class="px-4 py-3">Skema</th><th class="px-4 py-3">Sesi / Intake</th><th class="px-4 py-3">Tahap</th><th class="px-4 py-3">Progress Form</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(participant, index) in paginated" :key="participant.process.id" class="hover:bg-slate-50"><td class="px-4 py-4 text-slate-400">{{ (page - 1) * perPage + index + 1 }}</td><td class="px-4 py-4"><p class="font-semibold text-[#1e3329]">{{ asesiName(participant.process) }}</p><p class="text-xs text-slate-500">{{ participant.process.asesi?.username }}</p></td><td class="max-w-56 px-4 py-4 text-xs text-slate-600">{{ participant.process.periode_skema?.skema?.skema || '-' }}</td><td class="px-4 py-4 text-xs text-slate-600">{{ intakeLabel(participant.process) }}</td><td class="px-4 py-4"><span class="rounded-full bg-[#eaf2ee] px-2.5 py-1 text-xs text-[#2d4a3e]">{{ stageLabel(participant.process.current_stage) }}</span></td><td class="px-4 py-4 text-xs"><span class="text-amber-700">{{ countStatus(participant.assignments, ['submitted']) }} menunggu</span><span class="mx-1 text-slate-300">·</span><span class="text-blue-700">{{ countStatus(participant.assignments, ['under_review']) }} dinilai</span><span class="mx-1 text-slate-300">·</span><span class="text-emerald-700">{{ countStatus(participant.assignments, ['completed','assessed','result_published']) }} selesai</span></td><td class="px-4 py-4"><span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold" :class="stateClass(participantState(participant))">{{ stateLabel(participantState(participant)) }}</span></td><td class="px-4 py-4 text-right"><button @click="openParticipant(participant)" class="whitespace-nowrap rounded-lg bg-[#2d4a3e] px-3 py-2 text-xs font-semibold text-white">Buka Penilaian</button></td></tr></tbody></table></div>

        <div v-if="filtered.length" class="mt-4 flex flex-col items-center justify-between gap-3 text-sm text-slate-500 sm:flex-row"><div class="flex items-center gap-2"><span>{{ rangeStart }}–{{ rangeEnd }} dari {{ filtered.length }} asesi</span><select v-model="perPage" class="rounded-lg border px-2 py-1.5 text-xs"><option :value="10">10 / halaman</option><option :value="25">25 / halaman</option><option :value="50">50 / halaman</option></select></div><div class="flex items-center gap-2"><button :disabled="page === 1" @click="page--" class="rounded-lg border px-3 py-1.5 text-xs disabled:opacity-40">Sebelumnya</button><span>Halaman {{ page }} / {{ totalPages }}</span><button :disabled="page === totalPages" @click="page++" class="rounded-lg border px-3 py-1.5 text-xs disabled:opacity-40">Berikutnya</button></div></div>
    </AppLayout>
</template>
