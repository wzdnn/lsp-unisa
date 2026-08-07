<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import { useAuthStore } from '../stores/auth';
import { assessmentService } from '../services/lspService';
import DOMPurify from 'dompurify';

const auth = useAuthStore();
const props = defineProps({ view: { type: String, default: 'all' } });
const items = ref([]);
const selected = ref(null);
const answers = ref({});
const reviews = ref({});
const decision = ref({ result: 'competent', notes: '', publish: false });
const revisionNote = ref('');
const loading = ref(false);
const saving = ref(false);
const message = ref('');
const error = ref('');

const isAssessor = computed(() => ['dosen', 'asesor_luar'].includes(auth.user?.role));
const isAssessorForm = computed(() => isAssessor.value && selected.value?.assignee_role === 'asesor');
const isReviewMode = computed(() => isAssessor.value && selected.value?.assignee_role === 'asesi');
const isApl02 = computed(() => selected.value?.version?.form?.code === 'FR.APL.02');
const questions = computed(() => selected.value?.version?.sections?.flatMap((section) => section.questions || []) || []);
const editable = computed(() => ['assigned', 'draft', 'revision_required'].includes(selected.value?.status));
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
    return filters[props.view] ? items.value.filter((item) => filters[props.view].includes(item.status)) : items.value;
});
const pageTitle = computed(() => ({
    active: 'Tugas Assessment Aktif', revision: 'Assessment Perlu Revisi', history: 'Riwayat Assessment',
    pending: 'Menunggu Review', reviewing: 'Sedang Dinilai', assessor_revision: 'Menunggu Perbaikan Asesi', completed: 'Assessment Selesai',
}[props.view] || (isAssessor.value ? 'Penilaian Assessment' : 'Form Assessment Saya')));

const load = async () => {
    loading.value = true;
    try { items.value = (await assessmentService.getAll()).data; }
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
        if (selected.value.decision) decision.value = { result: selected.value.decision.result, notes: selected.value.decision.notes || '', publish: selected.value.decision.is_published };
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

const saveReview = async () => {
    const payload = Object.entries(reviews.value).map(([question_id, value]) => ({ question_id: Number(question_id), ...value }));
    try { selected.value = (await assessmentService.review(selected.value.id, payload)).data; message.value = 'Penilaian tersimpan'; await load(); return true; }
    catch (e) { error.value = e.response?.data?.message || 'Gagal menyimpan penilaian'; return false; }
};

const saveDecision = async () => {
    if (!confirm('Simpan keputusan akhir assessment ini?')) return;
    try { selected.value = (await assessmentService.decide(selected.value.id, decision.value)).data; message.value = 'Keputusan berhasil disimpan'; await load(); }
    catch (e) { error.value = e.response?.data?.message || 'Gagal menyimpan keputusan'; }
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
    return value === true ? 'Ya' : value === false ? 'Tidak' : (value || '-');
};

onMounted(load);
watch(() => props.view, () => { selected.value = null; error.value = ''; message.value = ''; });
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h2 class="text-xl font-bold text-[#1e3329]">{{ pageTitle }}</h2>
            <p class="text-sm text-[#7aab95] mt-1">Pra-asesmen, asesmen, dan pasca-asesmen dalam satu alur.</p>
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
                    <span class="inline-block mt-2 text-xs px-2 py-1 rounded bg-slate-100">{{ statusLabel(item.status) }}</span>
                </button>
            </aside>

            <section v-if="selected" class="space-y-5">
                <div class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div><p class="text-xs uppercase text-[#7aab95]">{{ selected.version.form.stage.replace('_', ' ') }} · versi {{ selected.version.version }}</p><h3 class="text-lg font-bold text-[#1e3329]">{{ selected.version.form.code }} — {{ selected.version.form.name }}</h3></div>
                        <span class="text-xs h-fit px-3 py-1.5 rounded-full bg-[#eaf2ee] text-[#2d4a3e]">{{ statusLabel(selected.status) }}</span>
                    </div>
                    <div v-if="selected.version.form.description" class="rich-content mt-3 text-sm text-slate-500" v-html="safeHtml(selected.version.form.description)"></div>
                </div>

                <div v-for="section in selected.version.sections" :key="section.id" class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <h4 class="font-bold text-[#1e3329]">{{ section.title }}</h4>
                    <p v-if="section.description" class="text-sm text-slate-500 mt-1">{{ section.description }}</p>
                    <div class="divide-y divide-slate-100 mt-4">
                        <div v-for="question in section.questions" :key="question.id" class="py-5">
                            <div class="flex gap-2 text-sm font-medium text-[#1e3329] mb-2"><span class="text-[#7aab95] shrink-0">{{ question.code }}</span><div class="rich-content" v-html="safeHtml(question.label)"></div><span v-if="question.is_required" class="text-red-500">*</span></div>
                            <p v-if="question.instructions" class="text-xs text-slate-400 mb-3">{{ question.instructions }}</p>

                            <template v-if="(!isAssessor || isAssessorForm) && editable">
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
                </div>

                <div v-if="!isReviewMode && selected.status === 'revision_required'" class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="font-semibold text-amber-800">Perlu diperbaiki</p>
                    <p class="text-sm text-amber-700 mt-1">{{ selected.revision_notes }}</p>
                    <p class="text-xs text-amber-600 mt-2">Perbaiki jawaban lalu kirim kembali kepada asesor.</p>
                </div>

                <div v-if="(!isAssessor || isAssessorForm) && editable" class="flex justify-end gap-3"><button @click="save" :disabled="saving" class="px-4 py-2 rounded-lg border border-[#4a7c6b] text-[#2d4a3e] text-sm">Simpan Draft</button><button @click="submit" class="px-4 py-2 rounded-lg bg-[#2d4a3e] text-white text-sm">Kirim Form</button></div>
                <div v-if="isReviewMode && ['submitted','under_review','assessed'].includes(selected.status) && !isApl02" class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <h4 class="font-bold text-[#1e3329]">Keputusan Asesor</h4>
                    <div class="grid md:grid-cols-2 gap-3 mt-4"><select v-model="decision.result" class="border border-[#c8ddd6] rounded-xl p-3 text-sm"><option value="competent">Kompeten</option><option value="not_competent">Belum Kompeten</option></select><input v-model="decision.notes" placeholder="Catatan keputusan" class="border border-[#c8ddd6] rounded-xl p-3 text-sm" /></div>
                    <label class="flex gap-2 mt-3 text-sm"><input v-model="decision.publish" type="checkbox"> Publikasikan hasil kepada asesi</label>
                    <div class="flex justify-end gap-3 mt-4"><button @click="saveReview" class="px-4 py-2 rounded-lg border border-[#4a7c6b] text-sm">Simpan Penilaian</button><button @click="saveDecision" class="px-4 py-2 rounded-lg bg-[#2d4a3e] text-white text-sm">Tetapkan Keputusan</button></div>
                </div>
                <div v-if="isReviewMode && isApl02 && ['submitted','under_review'].includes(selected.status)" class="bg-white border border-[#dde8e3] rounded-2xl p-5">
                    <h4 class="font-bold text-[#1e3329]">Penyelesaian Review APL-02</h4>
                    <p class="text-sm text-slate-500 mt-1">Simpan penilaian, minta revisi jika bukti belum memadai, atau selesaikan pra-asesmen.</p>
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
                <div v-if="!isReviewMode && selected.decision?.is_published" class="rounded-2xl p-5" :class="selected.decision.result === 'competent' ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200'"><p class="text-xs uppercase">Hasil assessment</p><p class="text-xl font-bold mt-1">{{ selected.decision.result === 'competent' ? 'KOMPETEN' : 'BELUM KOMPETEN' }}</p><p class="text-sm mt-2">{{ selected.decision.notes }}</p></div>
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
