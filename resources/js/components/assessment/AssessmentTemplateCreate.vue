<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import RichTextEditor from '../RichTextEditor.vue';
import { assessmentFormService } from '../../services/lspService';

const emit = defineEmits(['toast']);
const router = useRouter();
const busy = ref(false);
const blankQuestion = () => ({ code: '', type: 'short_text', label: '', is_required: false, options: [] });
const form = ref({ code: '', name: '', stage: 'pra_asesmen', filled_by: 'asesi', reviewed_by: 'asesor', description: '', sections: [{ title: 'Bagian 1', description: '', questions: [blankQuestion()] }] });
const types = ['short_text','long_text','number','date','radio','checkbox','select','file_upload','self_assessment','assessor_observation','oral_question','practice_task','information','signature'];
const save = async () => {
    busy.value = true;
    try { await assessmentFormService.create(form.value); emit('toast', { message: 'Template berhasil disimpan sebagai draft' }); router.push('/admin/assessment-forms'); }
    catch (e) { emit('toast', { type: 'error', message: e.response?.data?.message || 'Gagal menyimpan template' }); }
    finally { busy.value = false; }
};
</script>

<template>
    <div class="max-w-5xl mx-auto">
        <div class="grid md:grid-cols-2 gap-3"><input v-model="form.code" placeholder="Kode, contoh FR.APL.02" class="border rounded-lg p-3 text-sm"><input v-model="form.name" placeholder="Nama form" class="border rounded-lg p-3 text-sm"><select v-model="form.stage" class="border rounded-lg p-3 text-sm"><option value="pra_asesmen">Pra-asesmen</option><option value="asesmen">Asesmen</option><option value="pasca_asesmen">Pasca-asesmen</option></select><select v-model="form.filled_by" class="border rounded-lg p-3 text-sm"><option value="asesi">Diisi asesi</option><option value="asesor">Diisi asesor</option><option value="bersama">Diisi bersama</option><option value="admin">Diisi admin</option></select></div>
        <div class="mt-4"><p class="text-xs font-semibold text-slate-500 mb-2">Deskripsi dan petunjuk form</p><RichTextEditor v-model="form.description" /></div>
        <section v-for="(section, si) in form.sections" :key="si" class="mt-5 border border-[#dde8e3] rounded-xl p-5"><div class="flex gap-3"><input v-model="section.title" placeholder="Judul bagian" class="flex-1 border rounded-lg p-2.5 text-sm"><button v-if="form.sections.length > 1" @click="form.sections.splice(si, 1)" class="text-xs text-red-500">Hapus bagian</button></div>
            <div v-for="(q, qi) in section.questions" :key="qi" class="mt-4 rounded-xl bg-slate-50 p-4"><div class="grid md:grid-cols-[120px_180px_1fr_auto] gap-2"><input v-model="q.code" placeholder="Kode" class="border rounded-lg p-2 text-sm"><select v-model="q.type" class="border rounded-lg p-2 text-sm"><option v-for="type in types" :key="type">{{ type }}</option></select><label class="flex items-center gap-2 text-xs"><input v-model="q.is_required" type="checkbox"> Wajib dijawab</label><button @click="section.questions.splice(qi, 1)" class="text-xs text-red-500">Hapus</button></div><div class="mt-3"><RichTextEditor v-model="q.label" placeholder="Tulis pertanyaan atau instruksi..." /></div><input v-if="['radio','checkbox','select'].includes(q.type)" :value="q.options.join(',')" @input="q.options=$event.target.value.split(',').map(v=>v.trim()).filter(Boolean)" placeholder="Pilihan dipisahkan koma" class="w-full mt-2 border rounded-lg p-2.5 text-sm"></div>
            <button @click="section.questions.push(blankQuestion())" class="mt-3 text-xs font-semibold text-[#4a7c6b]">+ Tambah pertanyaan</button>
        </section>
        <div class="flex justify-between mt-5"><button @click="form.sections.push({ title: `Bagian ${form.sections.length + 1}`, description: '', questions: [blankQuestion()] })" class="text-sm font-semibold text-[#4a7c6b]">+ Tambah bagian</button><button @click="save" :disabled="busy" class="bg-[#2d4a3e] text-white rounded-lg px-5 py-2.5 text-sm">{{ busy ? 'Menyimpan...' : 'Simpan Draft' }}</button></div>
    </div>
</template>
