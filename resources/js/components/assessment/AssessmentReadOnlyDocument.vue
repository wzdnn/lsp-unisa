<script setup>
import { computed } from 'vue';
import DOMPurify from 'dompurify';

const props = defineProps({ assignment: { type: Object, required: true } });
const form = computed(() => props.assignment.version?.form || {});
const isApl02 = computed(() => form.value.code === 'FR.APL.02');
const answerMap = computed(() => Object.fromEntries((props.assignment.answers || []).map((answer) => [answer.question_id, answer.answer_json ?? answer.answer_text])));
const safeHtml = (value) => DOMPurify.sanitize(value || '');
const personName = (person) => person?.person?.namalengkap || person?.namalengkap || person?.username || '-';
const answerValue = (question) => answerMap.value[question.id];
const isSelected = (question, value) => {
    const answer = answerValue(question);
    if (Array.isArray(answer)) return answer.map(String).includes(String(value));
    if (answer && typeof answer === 'object') return Object.values(answer).map(String).includes(String(value));
    return String(answer ?? '').toLowerCase() === String(value ?? '').toLowerCase();
};
const displayAnswer = (question) => {
    const answer = answerValue(question);
    if (answer === null || answer === undefined || answer === '') return '-';
    if (question.type === 'signature' && typeof answer === 'object') return answer.signed ? `Ditandatangani ${new Date(answer.signed_at).toLocaleString('id-ID')}` : '-';
    if (Array.isArray(answer)) return answer.join(', ');
    if (typeof answer === 'object') return Object.entries(answer).map(([key, value]) => `${key}: ${value}`).join('; ');
    return String(answer);
};
const statusLabel = (status) => ({ assigned: 'Ditugaskan', draft: 'Draft', submitted: 'Menunggu review', under_review: 'Sedang dinilai', revision_required: 'Perlu revisi', completed: 'Selesai' }[status] || status);
</script>

<template>
    <div class="document-page">
        <div class="mb-5 flex items-start justify-between gap-4"><div><h2 class="text-lg font-black">{{ form.code }}. {{ form.name?.toUpperCase() }}</h2><p class="mt-1 text-[11px] text-slate-500">Versi {{ assignment.version?.version }} - {{ statusLabel(assignment.status) }}</p></div><span class="border border-black px-2 py-1 text-[10px] font-bold">BNSP - 2023</span></div>

        <table class="document-table mb-5"><tbody><tr><th rowspan="2" class="w-52 text-left">Skema Sertifikasi<br>(KKNI/Okupasi/Klaster)</th><th class="w-20 text-left">Judul</th><td class="w-5 text-center">:</td><td>{{ assignment.process?.periode_skema?.skema?.skema || '-' }}</td></tr><tr><th class="text-left">Nomor</th><td class="text-center">:</td><td>{{ assignment.process?.periode_skema?.skema?.no_skema || '-' }}</td></tr><tr><th colspan="2" class="text-left">TUK</th><td class="text-center">:</td><td>Sewaktu / Tempat Kerja / Mandiri</td></tr><tr><th colspan="2" class="text-left">Nama Asesor</th><td class="text-center">:</td><td>{{ personName(assignment.process?.assessor) }}</td></tr><tr><th colspan="2" class="text-left">Nama Asesi</th><td class="text-center">:</td><td>{{ personName(assignment.process?.asesi) }} ({{ assignment.process?.asesi?.username || '-' }})</td></tr></tbody></table>

        <div v-if="form.description" class="mb-5 border border-black p-3 text-xs leading-5" v-html="safeHtml(form.description)"></div>

        <template v-if="isApl02">
            <section v-for="section in assignment.version?.sections || []" :key="section.id" class="mb-5"><table class="document-table"><thead><tr><th colspan="5" class="bg-slate-100 text-left">{{ section.title }}</th></tr><tr><th class="w-32">No.</th><th class="text-left">Dapatkah saya?</th><th class="w-12">K</th><th class="w-12">BK</th><th class="w-40">Bukti</th></tr></thead><tbody><tr v-for="question in section.questions" :key="question.id"><td class="text-center text-[10px]">{{ question.code }}</td><td><div v-html="safeHtml(question.label)"></div><p v-if="question.instructions" class="mt-1 text-[10px] text-slate-500">{{ question.instructions }}</p></td><td class="text-center text-base">{{ isSelected(question, 'K') ? '✓' : '□' }}</td><td class="text-center text-base">{{ isSelected(question, 'BK') ? '✓' : '□' }}</td><td class="text-xs">{{ displayAnswer(question) }}</td></tr></tbody></table></section>
        </template>

        <template v-else>
            <section v-for="(section, sectionIndex) in assignment.version?.sections || []" :key="section.id" class="mb-5"><table class="document-table"><thead><tr><th colspan="4" class="bg-slate-100 text-left">{{ sectionIndex + 1 }}. {{ section.title }}</th></tr><tr><th class="w-14">No.</th><th class="text-left">Uraian / Pernyataan</th><th class="w-56 text-left">Pilihan</th><th class="w-56 text-left">Isian / Hasil</th></tr></thead><tbody><tr v-for="question in section.questions" :key="question.id"><td class="text-center">{{ question.code }}</td><td><div v-html="safeHtml(question.label)"></div><p v-if="question.instructions" class="mt-1 text-[10px] text-slate-500">{{ question.instructions }}</p></td><td><div v-if="question.options?.length" class="space-y-1"><div v-for="option in question.options" :key="option.value ?? option" class="flex gap-2"><span class="text-sm">{{ isSelected(question, option.value ?? option) ? '☑' : '□' }}</span><span>{{ option.label ?? option }}</span></div></div><span v-else class="text-slate-400">-</span></td><td class="whitespace-pre-wrap">{{ displayAnswer(question) }}</td></tr></tbody></table></section>
        </template>

        <table class="document-table mt-7"><tbody><tr><th class="w-1/2 text-left">Tanda tangan Asesor</th><th class="text-left">Tanggal</th></tr><tr><td class="h-20 align-bottom">{{ personName(assignment.process?.assessor) }}</td><td class="h-20 align-bottom">{{ assignment.reviewed_at ? new Date(assignment.reviewed_at).toLocaleDateString('id-ID') : '-' }}</td></tr><tr><th class="text-left">Tanda tangan Asesi</th><th class="text-left">Tanggal</th></tr><tr><td class="h-20 align-bottom">{{ personName(assignment.process?.asesi) }}</td><td class="h-20 align-bottom">{{ assignment.submitted_at ? new Date(assignment.submitted_at).toLocaleDateString('id-ID') : '-' }}</td></tr></tbody></table>
    </div>
</template>

<style scoped>
.document-page { width: 100%; max-width: 980px; margin: 0 auto; background: white; color: #111827; padding: 32px; font-family: Arial, sans-serif; font-size: 12px; box-shadow: 0 8px 30px rgb(15 23 42 / .12); }
.document-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.document-table :deep(th), .document-table :deep(td) { border: 1px solid #111; padding: 7px 8px; vertical-align: top; line-height: 1.35; overflow-wrap: anywhere; }
.document-table :deep(th) { font-weight: 700; }
@media (max-width: 720px) { .document-page { min-width: 760px; padding: 22px; } }
</style>
