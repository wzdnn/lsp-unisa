<script setup>
import { onMounted, ref } from 'vue';
import { assessmentFormService } from '../../services/lspService';

const emit = defineEmits(['toast']);
const forms = ref([]);
const loading = ref(false);

const load = async () => {
    loading.value = true;
    try { forms.value = (await assessmentFormService.getAll()).data; }
    catch (e) { emit('toast', { type: 'error', message: e.response?.data?.message || 'Gagal memuat template' }); }
    finally { loading.value = false; }
};
const publish = async (version) => {
    if (!confirm('Publikasikan versi ini? Setelah terbit, isinya tidak dapat diubah.')) return;
    try { await assessmentFormService.publish(version.id); emit('toast', { message: 'Versi berhasil dipublikasikan' }); await load(); }
    catch (e) { emit('toast', { type: 'error', message: e.response?.data?.message || 'Gagal memublikasikan versi' }); }
};
const duplicate = async (version) => {
    try { await assessmentFormService.duplicate(version.id); emit('toast', { message: 'Versi draft baru berhasil dibuat' }); await load(); }
    catch (e) { emit('toast', { type: 'error', message: e.response?.data?.message || 'Gagal membuat versi baru' }); }
};
onMounted(load);
</script>

<template>
    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat template...</div>
    <div v-else-if="!forms.length" class="py-12 text-center"><p class="text-sm text-slate-400">Belum ada template assessment.</p><router-link to="/admin/assessment-forms/create" class="inline-block mt-3 text-sm font-semibold text-[#4a7c6b]">Buat template pertama</router-link></div>
    <div v-else class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        <article v-for="item in forms" :key="item.id" class="border border-[#dde8e3] rounded-xl p-5">
            <span class="text-xs uppercase tracking-wide text-[#7aab95]">{{ item.stage.replace('_', ' ') }}</span>
            <h3 class="font-bold text-[#1e3329] mt-1">{{ item.code }}</h3><p class="text-sm text-slate-500">{{ item.name }}</p>
            <div class="space-y-2 mt-4"><div v-for="version in item.versions" :key="version.id" class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2"><span class="text-xs">Versi {{ version.version }} · <strong>{{ version.status }}</strong></span><div class="flex gap-2"><button v-if="version.status === 'draft'" @click="publish(version)" class="text-xs font-semibold text-emerald-700">Publish</button><button v-else @click="duplicate(version)" class="text-xs font-semibold text-[#4a7c6b]">Versi Baru</button></div></div></div>
        </article>
    </div>
</template>
