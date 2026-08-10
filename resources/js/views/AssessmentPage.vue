<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../layouts/AppLayout.vue';
import { useAuthStore } from '../stores/auth';
import { assessmentService } from '../services/lspService';
import DOMPurify from 'dompurify';
import AssessmentInstrumentTable from '../components/assessment/AssessmentInstrumentTable.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const props = defineProps({ view: { type: String, default: 'all' } });
const items = ref([]);
const selected = ref(null);
const answers = ref({});
const reviews = ref({});
const processDecision = ref({ result: 'competent', notes: '', publish: false });
const revisionNote = ref('');
const loading = ref(false);
const saving = ref(false);
const message = ref('');
const error = ref('');

const isAssessor = computed(() => ['dosen', 'asesor_luar'].includes(auth.user?.role));
const isAssessorForm = computed(() => isAssessor.value && selected.value?.assignee_role === 'asesor');
const isReviewMode = computed(() => isAssessor.value && selected.value?.assignee_role === 'asesi');
const isSharedForm = computed(() => selected.value?.version?.form?.filled_by === 'bersama');
const canEdit = computed(() => editable.value && (!isAssessor.value || isAssessorForm.value || isSharedForm.value));
const canSign = (question) => {
    if (question.type !== 'signature' || answers.value[question.id]?.signed) return false;
    const actorRole = isAssessor.value ? 'asesor' : 'asesi';
    const intendedRole = question.settings?.signer_role || actorRole;
    const normalStatus = ['assigned', 'draft', 'revision_required'].includes(selected.value?.status)
        || (isAssessorForm.value && selected.value?.status === 'under_review');
    const sharedAssessorStatus = isAssessor.value && isSharedForm.value && selected.value?.status === 'submitted';
    return intendedRole === actorRole && (normalStatus || sharedAssessorStatus);
};
const isApl02 = computed(() => selected.value?.version?.form?.code === 'FR.APL.02');
const isMukAssessmentForm = computed(() => ['FR.IA.01', 'FR.IA.02', 'FR.IA.03', 'FR.IA.07'].includes(selected.value?.version?.form?.code));
const questions = computed(() => selected.value?.version?.sections?.flatMap((section) => section.questions || []) || []);
const editable = computed(() => ['assigned', 'draft', 'revision_required'].includes(selected.value?.status)
    || (isAssessorForm.value && selected.value?.status === 'under_review'));
const asesiName = (item) => item?.process?.asesi?.person?.namalengkap || item?.process?.asesi?.namalengkap || item?.process?.asesi?.username || '-';
const asesiNumber = (item) => item?.process?.asesi?.username || '-';
const statusLabel = (status) => ({ assigned: 'Ditugaskan', draft: 'Draft', submitted: 'Menunggu penilaian', under_review: 'Sedang dinilai', revision_required: 'Perlu revisi', assessed: 'Sudah dinilai', result_published: 'Hasil terbit', completed: 'Selesai' }[status] || status);
const safeHtml = (html) => DOMPurify.sanitize(html || '');
const filteredItems = computed(() => {
    const filters = {
        active: ['assigned', 'draft', 'submitted', 'under_review'],
        revision: ['revision_required'],
        history: ['assessed', 'result_published', 'completed'],
        pending: ['submitted'],
        reviewing: ['under_review'],
        assessor_revision: ['revision_required'],
        completed: ['assessed', 'result_published', 'completed'],
    };
    const statusFiltered = filters[props.view] ? items.value.filter((item) => filters[props.view].includes(item.status)) : items.value;
    if (!isAssessor.value) return statusFiltered;
    const processId = Number(route.query.process || 0);
    return processId ? statusFiltered.filter((item) => item.process?.id === processId) : [];
});
const selectedParticipant = computed(() => items.value.find((item) => item.process?.id === Number(route.query.process || 0))?.process);
const pageTitle = computed(() => ({
    active: 'Tugas Assessment Aktif', revision: 'Assessment Perlu Revisi', history: 'Riwayat Assessment',
    pending: 'Menunggu Review', reviewing: 'Sedang Dinilai', assessor_revision: 'Menunggu Perbaikan Asesi', completed: 'Assessment Selesai',
}[props.view] || (isAssessor.value ? 'Penilaian Assessment' : 'Form Assessment Saya')));

const load = async () => {
    loading.value = true;
    try { items.value = (await assessmentService.getAll(isAssessor.value ? { process_id: route.query.process } : {})).data; }
    catch (e) { error.value = e.response?.data?.message || 'Gagal memuat assessment'; }
    finally { loading.value = false; }
};

const open = async (item) => {
    error.value = ''; message.value = '';
    try {
        selected.value = (await assessmentService.getOne(item.id)).data;
        answers.value = Object.fromEntries((selected.value.answers || []).map((a) => [a.question_id, a.answer_json ?? a.answer_text ?? '']));
        const storedReviews = Object.fromEntries((selected.value.reviews || []).map((r) => [r.question_id, { result: r.result, notes: r.notes || '' }]));
        reviews.value = Object.fromEntries(selected.value.version.sections.flatMap((s) => s.questions).map((q) => [q.id, storedReviews[q.id] || { result: 'not_assessed', notes: '' }]));
        if (selected.value.process?.final_result) processDecision.value = {
            result: selected.value.process.final_result,
            notes: selected.value.process.decision_notes || '',
            publish: Boolean(selected.value.process.result_published_at),
        };
    } catch (e) { error.value = e.response?.data?.message || 'Gagal membuka assessment'; }
};

const answerPayload = () => questions.value.filter((q) => q.type !== 'information').map((q) => ({
    question_id: q.id,
    ...(Array.isArray(answers.value[q.id]) || typeof answers.value[q.id] === 'object'
        ? { answer_json: answers.value[q.id] ?? null }
        : { answer_text: answers.value[q.id] == null ? null : String(answers.value[q.id]) }),
}));

const save = async () => {
    saving.value = true; error.value = '';
    try { selected.value = (await assessmentService.saveAnswers(selected.value.id, answerPayload())).data; message.value = 'Draft berhasil disimpan'; await load(); }
    catch (e) { error.value = e.response?.data?.message || 'Gagal menyimpan jawaban'; }
    finally { saving.value = false; }
};

const submit = async () => {
    if (!confirm('Kirim form? Jawaban akan dikunci untuk dinilai asesor.')) return;
    await save();
    try { selected.value = (await assessmentService.submit(selected.value.id)).data; message.value = 'Form berhasil dikirim'; await load(); }
    catch (e) { error.value = e.response?.data?.message || 'Gagal mengirim form'; }
};

const upload = async (question, event) => {
    const file = event.target.files?.[0]; if (!file) return;
    try { await assessmentService.uploadEvidence(selected.value.id, question.id, file); await open(selected.value); message.value = 'Bukti berhasil diunggah'; }
    catch (e) { error.value = e.response?.data?.message || 'Gagal mengunggah bukti'; }
};

const sign = async (question) => {
    try {
        selected.value = (await assessmentService.sign(selected.value.id, question.id)).data;
        answers.value[question.id] = selected.value.answers.find((answer) => answer.question_id === question.id)?.answer_json;
        message.value = 'Tanda tangan elektronik berhasil dibubuhkan';
    } catch (e) { error.value = e.response?.data?.message || 'Gagal membubuhkan tanda tangan'; }
};

const saveReview = async () => {
    const payload = Object.entries(reviews.value).map(([question_id, value]) => ({ question_id: Number(question_id), ...value }));
    try { selected.value = (await assessmentService.review(selected.value.id, payload)).data; message.value = 'Penilaian tersimpan'; await load(); return true; }
    catch (e) { error.value = e.response?.data?.message || 'Gagal menyimpan penilaian'; return false; }
};

const saveProcessDecision = async () => {
    if (!confirm('Tetapkan keputusan akhir untuk seluruh proses asesmen ini?')) return;
    try {
        const process = (await assessmentService.decideProcess(selected.value.process.id, processDecision.value)).data;
        selected.value.process = process;
        message.value = processDecision.value.publish ? 'Keputusan akhir diterbitkan kepada asesi' : 'Keputusan akhir disimpan';
        await load();
    } catch (e) { error.value = e.response?.data?.message || 'Gagal menyimpan keputusan akhir'; }
};

const requestRevision = async () => {
    if (!revisionNote.value.trim()) { error.value = 'Catatan revisi wajib diisi'; return; }
    if (!confirm('Kembalikan APL-02 kepada asesi untuk diperbaiki?')) return;
    try { selected.value = (await assessmentService.requestRevision(selected.value.id, revisionNote.value)).data; message.value = 'Form dikembalikan kepada asesi'; revisionNote.value = ''; await load(); }
    catch (e) { error.value = e.response?.data?.message || 'Gagal meminta revisi'; }
};

const completeReview = async () => {
    if (!confirm('Selesaikan review APL-02? Semua butir harus sudah dinilai.')) return;
    try { if (!await saveReview()) return; selected.value = (await assessmentService.completeReview(selected.value.id)).data; message.value = 'Review APL-02 selesai'; await load(); }
    catch (e) { error.value = e.response?.data?.message || 'Review belum dapat diselesaikan'; }
};

const displayAnswer = (question) => {
    const value = answers.value[question.id];
    if (Array.isArray(value)) return value.join(', ');
    if (value?.signed) return `Ditandatangani oleh ${value.signer_role} pada ${new Date(value.signed_at).toLocaleString('id-ID')}`;
    if (value && typeof value === 'object') return JSON.stringify(value);
    return value === true ? 'Ya' : value === false ? 'Tidak' : (value || '-');
};

onMounted(() => {
    if (isAssessor.value && !route.query.process) router.replace('/assessments/assessees');
    else load();
});
watch([() => props.view, () => route.query.process], () => { selected.value = null; error.value = ''; message.value = ''; });
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <button v-if="isAssessor" type="button" @click="router.push('/assessments/assessees')" class="mb-3 text-xs font-semibold text-[#4a7c6b]">← Kembali ke Daftar Asesi</button>
            <h2 class="text-xl font-bold text-[#1e3329]">{{ pageTitle }}</h2>
            <p v-if="isAssessor" class="text-sm text-[#7aab95] mt-1">{{ asesiName({ process: selectedParticipant }) }} · NIM {{ selectedParticipant?.asesi?.username || '-' }}</p>
            <p v-else class="text-sm text-[#7aab95] mt-1">Pra-asesmen, asesmen, dan pasca-asesmen dalam satu alur.</p>
        </div>
        <div v-if="isAssessor" class="mb-5 flex flex-wrap gap-2">
            <router-link :to="{ path: '/assessments/pending', query: { process: route.query.process } }" class="rounded-lg border px-3 py-2 text-xs font-semibold" :class="props.view === 'pending' ? 'border-[#4a7c6b] bg-[#eaf2ee] text-[#2d4a3e]' : 'border-[#dde8e3] bg-white text-slate-500'">Menunggu Review</router-link>
            <router-link :to="{ path: '/assessments/reviewing', query: { process: route.query.process } }" class="rounded-lg border px-3 py-2 text-xs font-semibold" :class="props.view === 'reviewing' ? 'border-[#4a7c6b] bg-[#eaf2ee] text-[#2d4a3e]' : 'border-[#dde8e3] bg-white text-slate-500'">Sedang Dinilai</router-link>
            <router-link :to="{ path: '/assessments/assessor-revisions', query: { process: route.query.process } }" class="rounded-lg border px-3 py-2 text-xs font-semibold" :class="props.view === 'assessor_revision' ? 'border-[#4a7c6b] bg-[#eaf2ee] text-[#2d4a3e]' : 'border-[#dde8e3] bg-white text-slate-500'">Menunggu Revisi</router-link>
            <router-link :to="{ path: '/assessments/completed', query: { process: route.query.process } }" class="rounded-lg border px-3 py-2 text-xs font-semibold" :class="props.view === 'completed' ? 'border-[#4a7c6b] bg-[#eaf2ee] text-[#2d4a3e]' : 'border-[#dde8e3] bg-white text-slate-500'">Selesai</router-link>
        </div>
        <div v-if="error" class="mb-4 rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ error }}</div>
        <div v-if="message" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700">{{ message }}</div>

        <div class="grid lg:grid-cols-[320px_1fr] gap-5">
            <aside class="bg-white border border-[#dde8e3] rounded-2xl p-3 h-fit">
                <p v-if="loading" class="p-4 text-sm text-slate-400">Memuat...</p>
                <p v-else-if="!filteredItems.length" class="p-4 text-sm text-slate-400">Tidak ada form pada kategori ini.</p>
                <button v-for="item in filteredItems" :key="item.id" @click="open(item)" class="w-full text-left p-3 mb-2 rounded-xl border transition" :class="selected?.id === item.id ? 'border-[#4a7c6b] bg-[#f0f4f1]' : 'border-slate-100 hover:border-[#c8ddd6]'">
                    <span class="block text-xs uppercase text-[#7aab95]">{{ item.version?.form?.stage?.replace('_', ' ') }}</span>
                    <strong class="block text-sm text-[#1e3329] mt-1">{{ item.version?.form?.code }} — {{ item.version?.form?.name }}</strong>
                    <span v-if="isAssessor" class="mt-1 block text-xs font-medium text-slate-600">{{ asesiName(item) }}</span>
                    <span v-if="isAssessor" class="block text-[11px] text-slate-400">NIM: {{ asesiNumber(item) }}</span>
                    <span class="inline-block mt-2 text-xs px-2 py-1 rounded bg-slate-100">{{ statusLabel(item.status) }}</span>
                </button>
            </aside>

            <section v-if="selected" class="space-y-5">
                <div v-if="!isMukAssessmentForm" class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div><p class="text-xs uppercase text-[#7aab95]">{{ selected.version.form.stage.replace('_', ' ') }} · versi {{ selected.version.version }}</p><h3 class="text-lg font-bold text-[#1e3329]">{{ selected.version.form.code }} — {{ selected.version.form.name }}</h3></div>
                        <span class="text-xs h-fit px-3 py-1.5 rounded-full bg-[#eaf2ee] text-[#2d4a3e]">{{ statusLabel(selected.status) }}</span>
                    </div>
                    <div v-if="isAssessor" class="mt-4 grid gap-3 rounded-xl border border-[#dde8e3] bg-slate-50 p-4 sm:grid-cols-2">
                        <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Asesi yang diuji</p><p class="mt-1 text-sm font-semibold text-[#1e3329]">{{ asesiName(selected) }}</p><p class="text-xs text-slate-500">NIM: {{ asesiNumber(selected) }}</p></div>
                        <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Skema sertifikasi</p><p class="mt-1 text-sm font-semibold text-[#1e3329]">{{ selected.process?.periode_skema?.skema?.skema || '-' }}</p></div>
                    </div>
                    <div v-if="selected.version.form.description" class="rich-content mt-3 text-sm text-slate-500" v-html="safeHtml(selected.version.form.description)"></div>
                </div>

                <AssessmentInstrumentTable v-if="isMukAssessmentForm" :key="selected.id" :assignment="selected" :answers="answers" :editable="canEdit" :can-sign="canSign" @sign="sign" @upload="upload" />
                <template v-else><div v-for="section in selected.version.sections" :key="section.id" class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <h4 class="font-bold text-[#1e3329]">{{ section.title }}</h4>
                    <p v-if="section.description" class="text-sm text-slate-500 mt-1">{{ section.description }}</p>
                    <div class="divide-y divide-slate-100 mt-4">
                        <div v-for="question in section.questions" :key="question.id" class="py-5">
                            <div class="flex gap-2 text-sm font-medium text-[#1e3329] mb-2"><span class="text-[#7aab95] shrink-0">{{ question.code }}</span><div class="rich-content" v-html="safeHtml(question.label)"></div><span v-if="question.is_required" class="text-red-500">*</span></div>
                            <p v-if="question.instructions" class="text-xs text-slate-400 mb-3">{{ question.instructions }}</p>

                            <div v-if="question.type === 'signature' && (canSign(question) || answers[question.id]?.signed)" class="rounded-xl border border-[#c8ddd6] p-3">
                                <p v-if="answers[question.id]?.signed" class="text-sm text-emerald-700">Sudah ditandatangani pada {{ new Date(answers[question.id].signed_at).toLocaleString('id-ID') }}</p>
                                <button v-else @click="sign(question)" type="button" class="rounded-lg bg-[#2d4a3e] px-4 py-2 text-sm text-white">Gunakan Tanda Tangan Aktif</button>
                            </div>
                            <template v-else-if="canEdit">
                                <div v-if="question.type === 'information'" class="rich-content text-sm bg-slate-50 p-3 rounded-lg" v-html="safeHtml(question.label)"></div>
                                <textarea v-else-if="['long_text','practice_task','oral_question'].includes(question.type)" v-model="answers[question.id]" rows="4" class="w-full border border-[#c8ddd6] rounded-xl p-3 text-sm" />
                                <input v-else-if="['short_text','number','date'].includes(question.type)" v-model="answers[question.id]" :type="question.type === 'short_text' ? 'text' : question.type" class="w-full border border-[#c8ddd6] rounded-xl p-3 text-sm" />
                                <select v-else-if="['radio','select','self_assessment','assessor_observation'].includes(question.type)" v-model="answers[question.id]" class="w-full border border-[#c8ddd6] rounded-xl p-3 text-sm">
                                    <option value="">Pilih jawaban</option><option v-for="option in question.options || (question.type === 'self_assessment' ? [{value:'K',label:'K — Saya mampu'},{value:'BK',label:'BK — Belum mampu'}] : [])" :key="option.value ?? option" :value="option.value ?? option">{{ option.label ?? option }}</option>
                                </select>
                                <div v-else-if="question.type === 'checkbox'" class="space-y-2"><label v-for="option in question.options || []" :key="option.value ?? option" class="flex gap-2 text-sm"><input type="checkbox" :value="option.value ?? option" v-model="answers[question.id]"> {{ option.label ?? option }}</label></div>
                                <input v-else-if="question.type === 'file_upload'" type="file" @change="upload(question, $event)" class="text-sm" />
                            </template>
                            <div v-else class="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">{{ displayAnswer(question) }}</div>

                            <div v-if="isReviewMode" class="mt-3 grid sm:grid-cols-[210px_1fr] gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
                                <select v-model="reviews[question.id].result" class="border border-amber-200 rounded-lg p-2 text-sm bg-white">
                                    <option value="not_assessed">Belum dinilai</option><option value="achieved">Tercapai / Kompeten</option><option value="not_achieved">Belum tercapai</option><option value="needs_follow_up">Perlu penilaian lanjut</option>
                                </select>
                                <input v-model="reviews[question.id].notes" placeholder="Catatan asesor" class="border border-amber-200 rounded-lg p-2 text-sm" />
                            </div>
                        </div>
                    </div>
                </div></template>

                <div v-if="!isReviewMode && selected.status === 'revision_required'" class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="font-semibold text-amber-800">Perlu diperbaiki</p>
                    <p class="text-sm text-amber-700 mt-1">{{ selected.revision_notes }}</p>
                    <p class="text-xs text-amber-600 mt-2">Perbaiki jawaban lalu kirim kembali kepada asesor.</p>
                </div>

                <div v-if="canEdit" class="flex justify-end gap-3"><button @click="save" :disabled="saving" class="px-4 py-2 rounded-lg border border-[#4a7c6b] text-[#2d4a3e] text-sm">Simpan Penilaian</button><button @click="submit" class="px-4 py-2 rounded-lg bg-[#2d4a3e] text-white text-sm">{{ isAssessorForm ? 'Selesaikan Penilaian' : 'Kirim Form' }}</button></div>
                <div v-if="isReviewMode && ['submitted','under_review'].includes(selected.status)" class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <h4 class="font-bold text-[#1e3329]">Penyelesaian Review {{ selected.version.form.code }}</h4>
                    <p class="text-sm text-slate-500 mt-1">Nilai seluruh butir, minta revisi jika bukti belum memadai, atau selesaikan review untuk melanjutkan workflow.</p>
                    <textarea v-model="revisionNote" rows="3" placeholder="Catatan yang harus diperbaiki asesi..." class="w-full mt-4 border border-[#c8ddd6] rounded-xl p-3 text-sm"></textarea>
                    <div class="flex flex-wrap justify-end gap-3 mt-4">
                        <button @click="saveReview" class="px-4 py-2 rounded-lg border border-[#4a7c6b] text-sm">Simpan Penilaian</button>
                        <button @click="requestRevision" class="px-4 py-2 rounded-lg border border-amber-400 text-amber-700 text-sm">Minta Revisi</button>
                        <button @click="completeReview" class="px-4 py-2 rounded-lg bg-[#2d4a3e] text-white text-sm">Selesaikan Review</button>
                    </div>
                </div>
                <div v-if="isApl02 && selected.status === 'completed'" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-800">
                    <p class="font-bold">Pra-asesmen APL-02 selesai</p>
                    <p class="text-sm mt-1">Proses dapat dilanjutkan ke persiapan asesmen dan MAPA.</p>
                </div>
                <div v-if="isAssessor && selected.process?.current_stage === 'keputusan'" class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <h4 class="font-bold text-[#1e3329]">Keputusan Akhir Proses</h4>
                    <p class="mt-1 text-sm text-slate-500">Keputusan ini menggabungkan seluruh instrumen pra-asesmen, asesmen, dan pasca-asesmen.</p>
                    <div class="grid md:grid-cols-2 gap-3 mt-4"><select v-model="processDecision.result" class="border border-[#c8ddd6] rounded-xl p-3 text-sm"><option value="competent">Kompeten</option><option value="not_competent">Belum Kompeten</option></select><input v-model="processDecision.notes" placeholder="Catatan keputusan akhir" class="border border-[#c8ddd6] rounded-xl p-3 text-sm" /></div>
                    <label class="flex gap-2 mt-3 text-sm"><input v-model="processDecision.publish" type="checkbox"> Publikasikan kepada asesi dan selesaikan proses</label>
                    <div class="flex justify-end mt-4"><button @click="saveProcessDecision" class="px-4 py-2 rounded-lg bg-[#2d4a3e] text-white text-sm">Tetapkan Keputusan Akhir</button></div>
                </div>
                <div v-if="!isAssessor && selected.process?.result_published_at" class="rounded-2xl p-5" :class="selected.process.final_result === 'competent' ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200'"><p class="text-xs uppercase">Keputusan akhir proses</p><p class="text-xl font-bold mt-1">{{ selected.process.final_result === 'competent' ? 'KOMPETEN' : 'BELUM KOMPETEN' }}</p><p class="text-sm mt-2">{{ selected.process.decision_notes }}</p></div>
            </section>
            <div v-else class="bg-white border border-[#dde8e3] rounded-2xl p-12 text-center text-slate-400">Pilih form di sebelah kiri.</div>
        </div>
    </AppLayout>
</template>

<style scoped>
.rich-content :deep(ul) { list-style: disc; padding-left: 1.25rem; }
.rich-content :deep(ol) { list-style: decimal; padding-left: 1.25rem; }
.rich-content :deep(blockquote) { border-left: 3px solid #c8ddd6; padding-left: .75rem; color: #64748b; }
.rich-content :deep(h2), .rich-content :deep(h3) { font-weight: 700; }
</style>
