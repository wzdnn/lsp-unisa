<script setup>
import { computed, onMounted, ref } from 'vue';
import { assessmentFormService } from '../../services/lspService';

const emit = defineEmits(['toast']);
const forms = ref([]);
const loading = ref(false);
const viewMode = ref(localStorage.getItem('assessment-template-view') || 'table');
const filters = ref({ search: '', stage: '', scheme: '', program: '', status: '' });
const expandedRows = ref([]);

const setViewMode = (mode) => { viewMode.value = mode; localStorage.setItem('assessment-template-view', mode); };
const stageLabel = (stage) => ({ pra_asesmen: 'Pra-asesmen', asesmen: 'Asesmen', pasca_asesmen: 'Pasca-asesmen' }[stage] || stage || '-');
const programLabel = (programs) => programs?.map((program) => program.unitkerjapendek || program.unitkerja).join(', ') || 'Tanpa prodi';
const latestVersion = (item) => item.versions?.[0];
const isExpanded = (id) => expandedRows.value.includes(id);
const toggleExpanded = (id) => { expandedRows.value = isExpanded(id) ? expandedRows.value.filter((item) => item !== id) : [...expandedRows.value, id]; };
const statusLabel = (status) => ({ draft: 'Draft', published: 'Published', archived: 'Archived' }[status] || status || '-');
const statusClass = (status) => ({ draft: 'bg-amber-50 text-amber-700', published: 'bg-emerald-50 text-emerald-700', archived: 'bg-slate-100 text-slate-500' }[status] || 'bg-slate-100 text-slate-600');
const formatDate = (value) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
const schemes = computed(() => [...new Map(forms.value.filter((item) => item.scheme).map((item) => [item.scheme.kdlsp_skema, item.scheme])).values()]);
const programs = computed(() => [...new Map(forms.value.flatMap((item) => item.programs || []).map((program) => [program.kdunitkerja, program])).values()]);
const filteredForms = computed(() => forms.value.filter((item) => {
    const keyword = filters.value.search.trim().toLowerCase();
    return (!keyword || `${item.code} ${item.name} ${item.scheme?.skema || ''} ${programLabel(item.programs)}`.toLowerCase().includes(keyword))
        && (!filters.value.stage || item.stage === filters.value.stage)
        && (!filters.value.scheme || String(item.kdlsp_skema) === String(filters.value.scheme))
        && (!filters.value.program || item.programs?.some((program) => String(program.kdunitkerja) === String(filters.value.program)))
        && (!filters.value.status || item.versions?.some((version) => version.status === filters.value.status));
}));

const resetFilters = () => { filters.value = { search: '', stage: '', scheme: '', program: '', status: '' }; };
const load = async () => {
    loading.value = true;
    try { forms.value = (await assessmentFormService.getAll()).data; }
    catch (error) { emit('toast', { type: 'error', message: error.response?.data?.message || 'Gagal memuat template' }); }
    finally { loading.value = false; }
};
const publish = async (version) => {
    if (!confirm('Publikasikan versi ini? Setelah terbit, isinya tidak dapat diubah.')) return;
    try { await assessmentFormService.publish(version.id); emit('toast', { message: 'Versi berhasil dipublikasikan' }); await load(); }
    catch (error) { emit('toast', { type: 'error', message: error.response?.data?.message || 'Gagal memublikasikan versi' }); }
};
const duplicate = async (version) => {
    try { await assessmentFormService.duplicate(version.id); emit('toast', { message: 'Versi draft baru berhasil dibuat' }); await load(); }
    catch (error) { emit('toast', { type: 'error', message: error.response?.data?.message || 'Gagal membuat versi baru' }); }
};
onMounted(load);
</script>

<template>
    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat template...</div>
    <div v-else-if="!forms.length" class="py-12 text-center"><p class="text-sm text-slate-400">Belum ada template assessment.</p><router-link to="/admin/assessment-forms/create" class="mt-3 inline-block text-sm font-semibold text-[#4a7c6b]">Buat template pertama</router-link></div>
    <div v-else>
        <div class="mb-4 rounded-xl border border-[#dde8e3] bg-slate-50/60 p-4"><div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,2fr)_repeat(4,minmax(130px,1fr))_auto]">
            <input v-model="filters.search" type="search" placeholder="Cari kode, nama, skema, atau prodi..." class="rounded-lg border border-[#c8ddd6] bg-white px-3 py-2 text-sm outline-none focus:border-[#4a7c6b]">
            <select v-model="filters.stage" class="rounded-lg border border-[#c8ddd6] bg-white px-3 py-2 text-sm"><option value="">Semua tahap</option><option value="pra_asesmen">Pra-asesmen</option><option value="asesmen">Asesmen</option><option value="pasca_asesmen">Pasca-asesmen</option></select>
            <select v-model="filters.scheme" class="rounded-lg border border-[#c8ddd6] bg-white px-3 py-2 text-sm"><option value="">Semua skema</option><option v-for="scheme in schemes" :key="scheme.kdlsp_skema" :value="scheme.kdlsp_skema">{{ scheme.skema }}</option></select>
            <select v-model="filters.program" class="rounded-lg border border-[#c8ddd6] bg-white px-3 py-2 text-sm"><option value="">Semua prodi</option><option v-for="program in programs" :key="program.kdunitkerja" :value="program.kdunitkerja">{{ program.unitkerjapendek || program.unitkerja }}</option></select>
            <select v-model="filters.status" class="rounded-lg border border-[#c8ddd6] bg-white px-3 py-2 text-sm"><option value="">Semua status</option><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select>
            <button type="button" @click="resetFilters" class="rounded-lg border border-[#c8ddd6] bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Reset</button>
        </div></div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><p class="text-xs text-slate-500">Menampilkan <strong class="text-[#2d4a3e]">{{ filteredForms.length }}</strong> dari {{ forms.length }} template</p><div class="inline-flex rounded-lg border border-[#dde8e3] bg-slate-50 p-1">
            <button type="button" @click="setViewMode('card')" class="rounded-md px-3 py-1.5 text-xs font-semibold" :class="viewMode === 'card' ? 'bg-white text-[#2d4a3e] shadow-sm' : 'text-slate-500'">Card</button><button type="button" @click="setViewMode('table')" class="rounded-md px-3 py-1.5 text-xs font-semibold" :class="viewMode === 'table' ? 'bg-white text-[#2d4a3e] shadow-sm' : 'text-slate-500'">Tabel</button>
        </div></div>

        <div v-if="viewMode === 'card' && filteredForms.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="(item, index) in filteredForms" :key="item.id" class="relative rounded-xl border border-[#dde8e3] p-5"><span class="absolute right-4 top-4 text-xs font-semibold text-slate-300">#{{ index + 1 }}</span><span class="text-xs uppercase tracking-wide text-[#7aab95]">{{ stageLabel(item.stage) }}</span><h3 class="mt-1 font-bold text-[#1e3329]">{{ item.code }}</h3><p class="text-sm text-slate-500">{{ item.name }}</p><p class="mt-2 text-xs text-slate-500">{{ item.scheme?.skema || 'Tanpa skema' }}</p><p class="text-xs text-[#7aab95]">{{ programLabel(item.programs) }}</p>
                <div class="mt-4 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><div><p class="text-xs text-slate-400">Versi terbaru</p><p class="mt-1 text-xs font-semibold">v{{ latestVersion(item)?.version || '-' }} <span class="ml-1 rounded-full px-2 py-0.5" :class="statusClass(latestVersion(item)?.status)">{{ statusLabel(latestVersion(item)?.status) }}</span></p></div><button @click="toggleExpanded(item.id)" class="text-xs font-semibold text-[#4a7c6b]">{{ isExpanded(item.id) ? 'Tutup' : `Lihat ${item.versions?.length || 0} versi` }}</button></div>
                <div v-if="isExpanded(item.id)" class="mt-2 divide-y rounded-lg border border-[#dde8e3] px-3"><div v-for="version in item.versions" :key="version.id" class="flex items-center justify-between gap-3 py-3"><div><p class="text-xs font-semibold">Versi {{ version.version }}</p><span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px]" :class="statusClass(version.status)">{{ statusLabel(version.status) }}</span></div><button v-if="version.status === 'draft'" @click="publish(version)" class="text-xs font-semibold text-emerald-700">Publish</button><button v-else @click="duplicate(version)" class="text-xs font-semibold text-[#4a7c6b]">Versi Baru</button></div></div>
            </article>
        </div>

        <div v-else-if="viewMode === 'table' && filteredForms.length" class="overflow-x-auto rounded-xl border border-[#dde8e3]"><table class="min-w-full divide-y divide-[#dde8e3] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="w-14 px-4 py-3 text-center">No.</th><th class="px-4 py-3">Kode dan nama</th><th class="px-4 py-3">Tahap</th><th class="px-4 py-3">Skema</th><th class="px-4 py-3">Program studi</th><th class="px-4 py-3">Versi terbaru</th><th class="w-28 px-4 py-3 text-right">Detail</th></tr></thead>
            <tbody class="divide-y divide-slate-100 bg-white"><template v-for="(item, index) in filteredForms" :key="item.id">
                <tr class="cursor-pointer hover:bg-slate-50/70" :class="isExpanded(item.id) ? 'bg-[#f7faf8]' : ''" @click="toggleExpanded(item.id)"><td class="px-4 py-4 text-center text-xs text-slate-400">{{ index + 1 }}</td><td class="px-4 py-4"><strong class="block text-[#1e3329]">{{ item.code }}</strong><span class="text-xs text-slate-500">{{ item.name }}</span></td><td class="px-4 py-4"><span class="rounded-full bg-[#eaf2ee] px-2.5 py-1 text-xs text-[#2d4a3e]">{{ stageLabel(item.stage) }}</span></td><td class="px-4 py-4 text-xs text-slate-600">{{ item.scheme?.skema || 'Tanpa skema' }}</td><td class="max-w-64 px-4 py-4 text-xs text-slate-600">{{ programLabel(item.programs) }}</td><td class="min-w-40 px-4 py-4"><div class="flex items-center gap-2"><strong class="text-xs">v{{ latestVersion(item)?.version || '-' }}</strong><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(latestVersion(item)?.status)">{{ statusLabel(latestVersion(item)?.status) }}</span></div><p class="mt-1 text-[11px] text-slate-400">{{ item.versions?.length || 0 }} versi tersimpan</p></td><td class="px-4 py-4 text-right"><button @click.stop="toggleExpanded(item.id)" class="inline-flex items-center gap-1 text-xs font-semibold text-[#4a7c6b]">{{ isExpanded(item.id) ? 'Tutup' : 'Lihat' }}<svg class="h-4 w-4 transition-transform" :class="isExpanded(item.id) ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></button></td></tr>
                <tr v-if="isExpanded(item.id)" class="bg-[#f7faf8]"><td colspan="7" class="px-5 pb-5 pt-1"><div class="overflow-hidden rounded-xl border border-[#dde8e3] bg-white"><div class="border-b px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#2d4a3e]">Riwayat versi {{ item.code }}</div><table class="w-full text-xs"><thead class="bg-slate-50 text-left uppercase text-slate-400"><tr><th class="px-4 py-2.5">Versi</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">Dipublikasikan</th><th class="px-4 py-2.5 text-right">Aksi</th></tr></thead><tbody class="divide-y"><tr v-for="version in item.versions" :key="version.id"><td class="px-4 py-3 font-semibold">Versi {{ version.version }}</td><td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 font-semibold" :class="statusClass(version.status)">{{ statusLabel(version.status) }}</span></td><td class="px-4 py-3 text-slate-500">{{ formatDate(version.published_at) }}</td><td class="px-4 py-3 text-right"><button v-if="version.status === 'draft'" @click="publish(version)" class="font-semibold text-emerald-700">Publish</button><button v-else @click="duplicate(version)" class="font-semibold text-[#4a7c6b]">Buat Versi Baru</button></td></tr></tbody></table></div></td></tr>
            </template></tbody>
        </table></div>

        <div v-if="!filteredForms.length" class="rounded-xl border border-dashed border-[#c8ddd6] py-12 text-center"><p class="text-sm text-slate-400">Tidak ada template yang sesuai dengan filter.</p><button @click="resetFilters" class="mt-2 text-xs font-semibold text-[#4a7c6b]">Reset filter</button></div>
    </div>
</template>
