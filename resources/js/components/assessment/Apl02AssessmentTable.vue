<script setup>
import { computed } from 'vue';
import DOMPurify from 'dompurify';

const props = defineProps({ assignment: { type: Object, required: true }, answers: { type: Object, required: true }, editable: Boolean, canSign: { type: Function, required: true } });
const emit = defineEmits(['sign', 'upload']);
const safeHtml = (value) => DOMPurify.sanitize(value || '');
const personName = (person) => person?.person?.namalengkap || person?.namalengkap || person?.username || '-';
const competencySections = computed(() => (props.assignment.version?.sections || []).filter((section) => section.questions?.some((question) => question.code?.endsWith('_MANDIRI'))));
const recommendationSection = computed(() => (props.assignment.version?.sections || []).find((section) => section.questions?.some((question) => question.code === 'APL02_REKOMENDASI')));
const rows = (section) => section.questions.filter((question) => question.code?.endsWith('_MANDIRI')).map((mandiri) => ({ mandiri, evidence: section.questions.find((question) => question.code === mandiri.code.replace('_MANDIRI', '_BUKTI')), file: section.questions.find((question) => question.code === mandiri.code.replace('_MANDIRI', '_FILE')) }));
const recommendation = computed(() => recommendationSection.value?.questions?.find((question) => question.code === 'APL02_REKOMENDASI'));
const signatures = computed(() => recommendationSection.value?.questions?.filter((question) => question.type === 'signature') || []);
</script>

<template>
    <div class="apl-document overflow-x-auto bg-white p-5 sm:p-7">
        <div class="mx-auto min-w-[760px] max-w-5xl text-[12px] text-slate-900">
            <h3 class="mb-5 text-lg font-black">FR.APL.02. ASESMEN MANDIRI</h3>
            <table class="muk-table mb-5"><tbody><tr><th rowspan="2" class="w-52 text-left">Skema Sertifikasi<br>(KKNI/Okupasi/Klaster)</th><th class="w-20 text-left">Judul</th><td class="w-5 text-center">:</td><td>{{ assignment.process?.periode_skema?.skema?.skema || '-' }}</td></tr><tr><th class="text-left">Nomor</th><td class="text-center">:</td><td>{{ assignment.process?.periode_skema?.skema?.no_skema || '-' }}</td></tr><tr><th colspan="2" class="text-left">Nama Asesi</th><td class="text-center">:</td><td>{{ personName(assignment.process?.asesi) }} / {{ assignment.process?.asesi?.username || '-' }}</td></tr><tr><th colspan="2" class="text-left">Nama Asesor</th><td class="text-center">:</td><td>{{ personName(assignment.process?.assessor) }}</td></tr></tbody></table>
            <div class="mb-5 border border-black"><div class="border-b border-black px-3 py-2 font-bold">PANDUAN ASESMEN MANDIRI</div><div class="px-3 py-2 leading-5">Baca setiap elemen, pilih <strong>K</strong> jika yakin mampu atau <strong>BK</strong> jika belum mampu. Tuliskan bukti relevan dan unggah dokumen pendukung jika tersedia.</div></div>

            <section v-for="(section, sectionIndex) in competencySections" :key="section.id" class="mb-6"><table class="muk-table"><thead><tr><th colspan="5" class="bg-slate-100 text-left">Unit Kompetensi {{ sectionIndex + 1 }}: {{ section.title }}</th></tr><tr><th class="w-20">No.</th><th class="text-left">Dapatkah saya?</th><th class="w-14">K</th><th class="w-14">BK</th><th class="w-60">Bukti</th></tr></thead><tbody><tr v-for="row in rows(section)" :key="row.mandiri.id"><td class="text-center text-[10px]">{{ row.mandiri.code }}</td><td><div v-html="safeHtml(row.mandiri.label)"></div></td><td class="text-center"><label class="inline-flex cursor-pointer items-center justify-center"><input v-if="editable" v-model="answers[row.mandiri.id]" type="radio" value="K" class="h-4 w-4"><span v-else class="text-lg">{{ answers[row.mandiri.id] === 'K' ? '☑' : '□' }}</span></label></td><td class="text-center"><label class="inline-flex cursor-pointer items-center justify-center"><input v-if="editable" v-model="answers[row.mandiri.id]" type="radio" value="BK" class="h-4 w-4"><span v-else class="text-lg">{{ answers[row.mandiri.id] === 'BK' ? '☑' : '□' }}</span></label></td><td><textarea v-if="editable && row.evidence" v-model="answers[row.evidence.id]" rows="3" class="w-full resize-y border-0 bg-transparent p-1 text-xs outline-none" placeholder="Tuliskan bukti relevan..."></textarea><p v-else class="min-h-10 whitespace-pre-wrap">{{ row.evidence ? (answers[row.evidence.id] || '-') : '-' }}</p><label v-if="editable && row.file" class="mt-2 inline-flex cursor-pointer text-[11px] font-semibold text-[#4a7c6b]">Unggah bukti<input type="file" class="hidden" @change="emit('upload', row.file, $event)"></label><p v-if="row.file?.evidences?.length" class="mt-1 text-[10px] text-emerald-700">{{ row.file.evidences.length }} berkas tersimpan</p></td></tr></tbody></table></section>

            <table v-if="recommendation" class="muk-table mb-6"><thead><tr><th colspan="2" class="bg-slate-100 text-left">Rekomendasi Asesmen Mandiri</th></tr></thead><tbody><tr><td class="w-1/2">Asesmen dapat dilanjutkan</td><td><label class="flex items-center gap-2"><input v-if="editable" v-model="answers[recommendation.id]" type="radio" value="dapat"><span v-else>{{ answers[recommendation.id] === 'dapat' ? '☑' : '□' }}</span> Dapat dilanjutkan</label></td></tr><tr><td>Asesmen belum dapat dilanjutkan</td><td><label class="flex items-center gap-2"><input v-if="editable" v-model="answers[recommendation.id]" type="radio" value="tidak_dapat"><span v-else>{{ answers[recommendation.id] === 'tidak_dapat' ? '☑' : '□' }}</span> Belum dapat dilanjutkan</label></td></tr></tbody></table>

            <table class="muk-table"><tbody><tr v-for="signature in signatures" :key="signature.id"><th class="w-1/3 text-left">{{ signature.label }}</th><td class="h-20"><p v-if="answers[signature.id]?.signed" class="text-emerald-700">Ditandatangani pada {{ new Date(answers[signature.id].signed_at).toLocaleString('id-ID') }}</p><button v-else-if="canSign(signature)" type="button" @click="emit('sign', signature)" class="rounded bg-[#2d4a3e] px-3 py-2 text-xs font-semibold text-white">Gunakan Tanda Tangan Aktif</button><span v-else class="text-slate-400">Menunggu tanda tangan</span></td></tr></tbody></table>
        </div>
    </div>
</template>

<style scoped>
.muk-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.muk-table :deep(th), .muk-table :deep(td) { border: 1px solid #111; padding: 7px 8px; vertical-align: top; line-height: 1.35; overflow-wrap: anywhere; }
</style>
