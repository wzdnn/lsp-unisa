<script setup>
import { computed, onMounted, ref } from 'vue';
import { assessmentService, userService } from '../../services/lspService';

const emit = defineEmits(['toast']);
const processes = ref([]);
const users = ref([]);
const loading = ref(false);
const assigning = ref(null);
const selections = ref({});

const assessors = computed(() => users.value.filter((user) =>
    user.role === 'asesor_luar' || (user.role === 'dosen' && user.isAsesor)
));

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
            selections.value[process.id] ??= {
                assessor_id: process.assessor_id || '',
                due_at: '',
            };
        }
    } catch (error) {
        emit('toast', { type: 'error', message: error.response?.data?.message || 'Gagal memuat proses assessment' });
    } finally {
        loading.value = false;
    }
};

const hasApl02 = (process) => process.assignments?.some((assignment) => assignment.version?.form?.code === 'FR.APL.02');
const participantName = (process) => process.asesi?.person?.namalengkap || process.asesi?.username || '-';

const assign = async (process) => {
    assigning.value = process.id;
    try {
        await assessmentService.assignApl02(process.id, selections.value[process.id]);
        emit('toast', { message: 'Asesor ditetapkan dan APL-02 berhasil diberikan kepada asesi' });
        await load();
    } catch (error) {
        const validation = error.response?.data?.errors;
        emit('toast', {
            type: 'error',
            message: validation ? Object.values(validation).flat()[0] : (error.response?.data?.message || 'Gagal membuat penugasan'),
        });
    } finally {
        assigning.value = null;
    }
};

onMounted(load);
</script>

<template>
    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat proses dari APL-01...</div>
    <div v-else-if="!processes.length" class="py-12 text-center">
        <p class="text-sm text-slate-400">Belum ada APL-01 yang diterima.</p>
        <p class="text-xs text-slate-400 mt-1">Proses akan muncul otomatis setelah admin menerima pengajuan APL-01.</p>
    </div>
    <div v-else class="space-y-4">
        <div class="rounded-xl bg-blue-50 border border-blue-100 p-4 text-sm text-blue-700">
            Pilih asesor untuk proses yang berasal dari APL-01. Sistem akan otomatis memberikan template FR.APL.02 published kepada asesi.
        </div>
        <article v-for="process in processes" :key="process.id" class="border border-[#dde8e3] rounded-xl p-5">
            <div class="flex flex-wrap justify-between gap-3">
                <div>
                    <span class="text-xs uppercase tracking-wide text-[#7aab95]">Proses #{{ process.id }} · {{ process.current_stage.replaceAll('_', ' ') }}</span>
                    <h3 class="font-bold text-[#1e3329] mt-1">{{ participantName(process) }}</h3>
                    <p class="text-sm text-slate-500">{{ process.periode_skema?.skema?.skema || '-' }} · {{ process.periode_skema?.periode?.periode || '-' }}</p>
                </div>
                <span class="h-fit rounded-full px-3 py-1 text-xs font-semibold" :class="hasApl02(process) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                    {{ hasApl02(process) ? 'APL-02 sudah ditugaskan' : 'Menunggu penugasan' }}
                </span>
            </div>

            <div v-if="!hasApl02(process)" class="grid md:grid-cols-[1fr_220px_auto] gap-3 mt-5">
                <select v-model="selections[process.id].assessor_id" class="border border-[#c8ddd6] rounded-lg p-2.5 text-sm">
                    <option value="">Pilih asesor aktif</option>
                    <option v-for="assessor in assessors" :key="assessor.kdlsp_user" :value="assessor.kdlsp_user">
                        {{ assessor.person?.namalengkap || assessor.namalengkap || assessor.username }}
                    </option>
                </select>
                <input v-model="selections[process.id].due_at" type="datetime-local" class="border border-[#c8ddd6] rounded-lg p-2.5 text-sm">
                <button @click="assign(process)" :disabled="assigning === process.id || !selections[process.id].assessor_id" class="bg-[#2d4a3e] disabled:opacity-50 text-white rounded-lg px-4 py-2.5 text-sm">
                    {{ assigning === process.id ? 'Memproses...' : 'Tetapkan & Berikan APL-02' }}
                </button>
            </div>
            <div v-else class="mt-4 text-sm text-slate-500">
                Asesor: <strong class="text-[#1e3329]">{{ process.assessor?.person?.namalengkap || process.assessor?.namalengkap || process.assessor?.username || '-' }}</strong>
            </div>
        </article>
    </div>
</template>
