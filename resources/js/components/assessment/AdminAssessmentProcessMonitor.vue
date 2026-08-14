<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { assessmentService } from '../../services/lspService';

const props = defineProps({ stage: { type: String, required: true } });
const emit = defineEmits(['toast']);
const processes = ref([]);
const loading = ref(false);
const search = ref('');
const status = ref('all');

const stageKey = computed(() => ({
    'pre-assesment': 'pra_asesmen',
    assesment: 'asesmen',
    'post-assesment': 'pasca_asesmen',
}[props.stage] || props.stage));

const load = async () => {
    loading.value = true;
    try {
        processes.value = (await assessmentService.getProcesses()).data;
    } catch (error) {
        emit('toast', { type: 'error', message: error.response?.data?.message || 'Gagal memuat monitoring assessment' });
    } finally {
        loading.value = false;
    }
};

const participantName = (process) => process.asesi?.person?.namalengkap || process.asesi?.namalengkap || process.asesi?.username || '-';
const assessorName = (process) => process.assessor?.person?.namalengkap || process.assessor?.namalengkap || process.assessor?.username || 'Belum ditetapkan';
const stageAssignments = (process) => (process.assignments || []).filter((item) => item.version?.form?.stage === stageKey.value);
const isOverdue = (item) => item.due_at && new Date(item.due_at) < new Date() && !['completed', 'assessed', 'result_published'].includes(item.status);
const progress = (process) => {
    const items = stageAssignments(process);
    const completed = items.filter((item) => ['completed', 'assessed', 'result_published'].includes(item.status)).length;
    return { completed, total: items.length, percent: items.length ? Math.round((completed / items.length) * 100) : 0 };
};
const processState = (process) => {
    const items = stageAssignments(process);
    if (!items.length) return 'not_started';
    if (items.some(isOverdue)) return 'overdue';
    if (items.some((item) => item.status === 'revision_required')) return 'revision';
    if (items.every((item) => ['completed', 'assessed', 'result_published'].includes(item.status))) return 'completed';
    return 'active';
};
const stateLabel = (value) => ({ not_started: 'Belum dimulai', active: 'Berjalan', revision: 'Perlu revisi', overdue: 'Terlambat', completed: 'Selesai' }[value] || value);
const stateClass = (value) => ({
    not_started: 'bg-slate-100 text-slate-600', active: 'bg-blue-50 text-blue-700', revision: 'bg-amber-50 text-amber-700',
    overdue: 'bg-red-50 text-red-700', completed: 'bg-emerald-50 text-emerald-700',
}[value]);

const scopedProcesses = computed(() => processes.value.filter((process) => {
    const order = ['pra_asesmen', 'asesmen', 'pasca_asesmen', 'keputusan'];
    return order.indexOf(process.current_stage) >= order.indexOf(stageKey.value);
}));
const filtered = computed(() => scopedProcesses.value.filter((process) => {
    const haystack = `${participantName(process)} ${process.asesi?.username || ''} ${assessorName(process)} ${process.periode_skema?.skema?.skema || ''}`.toLowerCase();
    return haystack.includes(search.value.toLowerCase()) && (status.value === 'all' || processState(process) === status.value);
}));
const metric = (key) => scopedProcesses.value.filter((process) => processState(process) === key).length;

watch(() => props.stage, load);
onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <button v-for="item in [{key:'all',label:'Total',count:scopedProcesses.length},{key:'active',label:'Berjalan',count:metric('active')},{key:'revision',label:'Revisi',count:metric('revision')},{key:'overdue',label:'Terlambat',count:metric('overdue')},{key:'completed',label:'Selesai',count:metric('completed')}]" :key="item.key" type="button" @click="status = item.key" class="rounded-xl border p-4 text-left transition" :class="status === item.key ? 'border-[#4a7c6b] bg-[#eef5f1]' : 'border-[#dde8e3] bg-white'">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</p>
                <p class="mt-1 text-2xl font-bold text-[#1e3329]">{{ item.count }}</p>
            </button>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <input v-model="search" class="flex-1 rounded-lg border border-[#c8ddd6] px-3 py-2 text-sm" placeholder="Cari nama, NIM, asesor, atau skema...">
            <select v-model="status" class="rounded-lg border border-[#c8ddd6] px-3 py-2 text-sm">
                <option value="all">Semua status</option><option value="not_started">Belum dimulai</option><option value="active">Berjalan</option><option value="revision">Perlu revisi</option><option value="overdue">Terlambat</option><option value="completed">Selesai</option>
            </select>
            <button type="button" class="rounded-lg border border-[#4a7c6b] px-4 py-2 text-sm font-semibold text-[#2d4a3e]" @click="load">Muat ulang</button>
        </div>

        <div class="overflow-hidden rounded-xl border border-[#dde8e3]">
            <div v-if="loading" class="p-10 text-center text-sm text-slate-400">Memuat proses...</div>
            <div v-else-if="!filtered.length" class="p-10 text-center text-sm text-slate-400">Tidak ada proses pada filter ini.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#e7efeb] text-sm">
                    <thead class="bg-[#f4f8f6] text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">No.</th><th class="px-4 py-3">Asesi</th><th class="px-4 py-3">Skema</th><th class="px-4 py-3">Asesor</th><th class="px-4 py-3">Progress</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Form bermasalah</th></tr></thead>
                    <tbody class="divide-y divide-[#edf3f0] bg-white">
                        <tr v-for="(process, index) in filtered" :key="process.id" class="align-top hover:bg-[#f9fbfa]">
                            <td class="px-4 py-4 text-slate-400">{{ index + 1 }}</td>
                            <td class="px-4 py-4"><p class="font-semibold text-[#1e3329]">{{ participantName(process) }}</p><p class="text-xs text-slate-500">{{ process.asesi?.username || '-' }} · Proses #{{ process.id }}</p></td>
                            <td class="px-4 py-4 text-slate-600">{{ process.periode_skema?.skema?.skema || '-' }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ assessorName(process) }}</td>
                            <td class="min-w-40 px-4 py-4"><div class="mb-1 flex justify-between text-xs"><span>{{ progress(process).completed }}/{{ progress(process).total }} form</span><span>{{ progress(process).percent }}%</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#4a7c6b]" :style="{ width: `${progress(process).percent}%` }"></div></div></td>
                            <td class="px-4 py-4"><span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold" :class="stateClass(processState(process))">{{ stateLabel(processState(process)) }}</span></td>
                            <td class="px-4 py-4 text-xs text-slate-600"><div v-for="assignment in stageAssignments(process).filter((item) => item.status === 'revision_required' || isOverdue(item))" :key="assignment.id" class="mb-1"><strong>{{ assignment.version?.form?.code }}</strong> — {{ isOverdue(assignment) ? 'melewati tenggat' : 'menunggu revisi' }}</div><span v-if="!stageAssignments(process).some((item) => item.status === 'revision_required' || isOverdue(item))" class="text-slate-400">Tidak ada</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
