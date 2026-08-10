<script setup>
import DOMPurify from 'dompurify';

const props = defineProps({ assignment: { type: Object, required: true }, answers: { type: Object, required: true }, editable: Boolean, canSign: { type: Function, required: true } });
const emit = defineEmits(['sign', 'upload']);
const code = props.assignment.version.form.code;
const isIa01 = code === 'FR.IA.01';
const isOral = ['FR.IA.03', 'FR.IA.07'].includes(code);
const safeHtml = (value) => DOMPurify.sanitize(value || '');
const asesi = props.assignment.process?.asesi;
const assessor = props.assignment.process?.assessor;
const fullName = (user) => user?.person?.namalengkap || user?.namalengkap || user?.username || '-';
const scheme = props.assignment.process?.periode_skema?.skema;
const optionValue = (option) => option?.value ?? option;
const optionLabel = (option) => option?.label ?? option;
const oralAnswer = (question) => {
    if (!props.answers[question.id] || typeof props.answers[question.id] !== 'object' || Array.isArray(props.answers[question.id])) {
        props.answers[question.id] = { response: props.answers[question.id] || '', achievement: '' };
    }
    return props.answers[question.id];
};
</script>

<template>
    <div class="muk-sheet overflow-hidden rounded-xl border-2 border-slate-700 bg-white text-slate-900">
        <div class="border-b-2 border-slate-700 px-4 py-4 text-center text-base font-bold">{{ code }} - {{ assignment.version.form.name.toUpperCase() }}</div>
        <table class="muk-table w-full table-fixed text-sm">
            <tbody>
                <tr><th rowspan="2" class="w-44 text-left">Skema Sertifikasi<br>(KKNI/Okupasi/Klaster)</th><th class="w-20 text-left">Judul</th><td class="w-5 text-center">:</td><td>{{ scheme?.skema || assignment.version.form.name }}</td></tr>
                <tr><th class="text-left">Nomor</th><td class="text-center">:</td><td>{{ scheme?.no_skema || '-' }}</td></tr>
                <tr><th colspan="2" class="text-left">TUK</th><td class="text-center">:</td><td><span class="mr-8">☑ Sewaktu</span><span class="mr-8">☐ Tempat Kerja</span><span>☐ Mandiri</span></td></tr>
                <tr><th colspan="2" class="text-left">Nama Asesor</th><td class="text-center">:</td><td>{{ fullName(assessor) }}</td></tr>
                <tr><th colspan="2" class="text-left">Nama Asesi</th><td class="text-center">:</td><td>{{ fullName(asesi) }}</td></tr>
                <tr><th colspan="2" class="text-left">NIM</th><td class="text-center">:</td><td>{{ asesi?.username || '-' }}</td></tr>
                <tr><th colspan="2" class="text-left">Tanggal</th><td class="text-center">:</td><td>{{ new Date().toLocaleDateString('id-ID') }}</td></tr>
            </tbody>
        </table>

        <div class="border-t-2 border-slate-700 p-4 text-xs leading-relaxed">
            <strong>PANDUAN BAGI ASESOR</strong>
            <ul class="mt-2 list-disc pl-5">
                <li>Gunakan instrumen sesuai unit kompetensi, elemen, dan kriteria unjuk kerja.</li>
                <li>Beri tanda pada pencapaian Ya atau Tidak dan isi penilaian lanjut bila diperlukan.</li>
                <li>Catat tanggapan serta bukti yang relevan sebelum menyelesaikan penilaian.</li>
            </ul>
        </div>

        <section v-for="(section, sectionIndex) in assignment.version.sections" :key="section.id" class="border-t-2 border-slate-700">
            <div class="bg-slate-100 px-4 py-3 text-sm font-bold">{{ section.title }}</div>

            <table v-if="isIa01" class="muk-table w-full table-fixed text-xs">
                <thead><tr><th class="w-10" rowspan="2">No.</th><th rowspan="2">Elemen dan Kriteria Unjuk Kerja</th><th class="w-40" rowspan="2">Standar Industri atau Tempat Kerja</th><th class="w-28" colspan="2">Pencapaian</th><th class="w-28" rowspan="2">Penilaian Lanjut</th></tr><tr><th>Ya</th><th>Tidak</th></tr></thead>
                <tbody><tr v-for="(question, index) in section.questions" :key="question.id">
                    <td class="text-center">{{ index + 1 }}</td><td><div v-html="safeHtml(question.label)"></div><textarea v-if="question.type === 'long_text'" v-model="answers[question.id]" :disabled="!editable" rows="2" class="muk-input mt-2"></textarea><select v-else-if="question.type === 'radio'" v-model="answers[question.id]" :disabled="!editable" class="muk-input mt-2"><option value="">Pilih hasil</option><option v-for="option in question.options || []" :key="optionValue(option)" :value="optionValue(option)">{{ optionLabel(option) }}</option></select><button v-else-if="question.type === 'signature' && canSign(question)" type="button" @click="emit('sign', question)" class="mt-2 rounded bg-[#2d4a3e] px-3 py-2 text-xs text-white">Bubuhkan Tanda Tangan</button></td>
                    <td>{{ question.instructions || 'SOP K3 UNISA Yogyakarta dan SOP K3 Laboratorium UNISA Yogyakarta' }}</td>
                    <template v-if="question.type === 'assessor_observation'"><td v-for="value in ['ya','tidak']" :key="value" class="text-center"><input v-model="answers[question.id]" type="radio" :value="value" :disabled="!editable"></td><td class="text-center"><input v-model="answers[question.id]" type="radio" value="lanjut" :disabled="!editable"></td></template><td v-else colspan="3"></td>
                </tr></tbody>
            </table>

            <table v-else-if="isOral" class="muk-table w-full table-fixed text-xs">
                <thead><tr><th class="w-12" rowspan="2">No.</th><th rowspan="2">Pertanyaan, Kunci Jawaban, dan Tanggapan</th><th class="w-28" colspan="2">Pencapaian</th></tr><tr><th>Ya</th><th>Tidak</th></tr></thead>
                <tbody><tr v-for="(question, index) in section.questions" :key="question.id">
                    <td class="text-center">{{ index + 1 }}</td>
                    <td><div class="font-semibold" v-html="safeHtml(question.label)"></div><div v-if="question.settings?.answer_key" class="mt-2 border-t border-slate-300 pt-2"><strong>Kunci Jawaban:</strong> {{ question.settings.answer_key }}</div><textarea v-if="question.type === 'oral_question'" v-model="oralAnswer(question).response" :disabled="!editable" rows="3" placeholder="Jawaban/tanggapan asesi..." class="muk-input mt-3"></textarea><textarea v-else-if="question.type === 'long_text'" v-model="answers[question.id]" :disabled="!editable" rows="3" class="muk-input mt-2"></textarea><button v-else-if="question.type === 'signature' && canSign(question)" type="button" @click="emit('sign', question)" class="rounded bg-[#2d4a3e] px-3 py-2 text-xs text-white">Bubuhkan Tanda Tangan</button></td>
                    <template v-if="question.type === 'oral_question'"><td class="text-center"><input v-model="oralAnswer(question).achievement" type="radio" value="ya" :disabled="!editable"></td><td class="text-center"><input v-model="oralAnswer(question).achievement" type="radio" value="tidak" :disabled="!editable"></td></template><td v-else-if="question.type === 'assessor_observation'" colspan="2"><select v-model="answers[question.id]" :disabled="!editable" class="muk-input"><option value="">Pilih</option><option v-for="option in question.options || []" :key="optionValue(option)" :value="optionValue(option)">{{ optionLabel(option) }}</option></select></td><td v-else colspan="2"></td>
                </tr></tbody>
            </table>

            <table v-else class="muk-table w-full table-fixed text-xs">
                <thead><tr><th class="w-12">No.</th><th>Instruksi / Skenario Tugas Praktik</th><th class="w-44">Hasil / Pencapaian</th></tr></thead>
                <tbody><tr v-for="(question, index) in section.questions" :key="question.id"><td class="text-center">{{ index + 1 }}</td><td><div v-html="safeHtml(question.label)"></div><textarea v-if="['practice_task','long_text'].includes(question.type)" v-model="answers[question.id]" :disabled="!editable" rows="3" class="muk-input mt-2"></textarea><input v-else-if="question.type === 'file_upload'" type="file" :disabled="!editable" @change="emit('upload', question, $event)" class="mt-2 text-xs"><button v-else-if="question.type === 'signature' && canSign(question)" type="button" @click="emit('sign', question)" class="mt-2 rounded bg-[#2d4a3e] px-3 py-2 text-xs text-white">Bubuhkan Tanda Tangan</button></td><td><select v-if="question.type === 'assessor_observation'" v-model="answers[question.id]" :disabled="!editable" class="muk-input"><option value="">Pilih pencapaian</option><option v-for="option in question.options || []" :key="optionValue(option)" :value="optionValue(option)">{{ optionLabel(option) }}</option></select></td></tr></tbody>
            </table>
        </section>

        <table class="muk-table w-full text-xs"><tbody><tr><th class="w-48 text-left">ASESI</th><td>{{ fullName(asesi) }}</td></tr><tr><th class="text-left">ASESOR</th><td>{{ fullName(assessor) }}</td></tr></tbody></table>
    </div>
</template>

<style scoped>
.muk-table th,.muk-table td{border:1px solid #475569;padding:.55rem;vertical-align:top}.muk-table th{font-weight:700}.muk-input{width:100%;border:1px solid #94a3b8;border-radius:.25rem;background:white;padding:.5rem;font-size:.75rem}.muk-input:disabled{background:#f8fafc;color:#475569}
</style>
