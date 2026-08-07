<script setup>
import { ref } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import BaseToast from '../../components/BaseToast.vue';
import AssessmentTemplateList from '../../components/assessment/AssessmentTemplateList.vue';
import AssessmentTemplateCreate from '../../components/assessment/AssessmentTemplateCreate.vue';
import AssessmentAssignmentForm from '../../components/assessment/AssessmentAssignmentForm.vue';

const props = defineProps({ mode: { type: String, default: 'list' } });
const page = {
    list: { title: 'Template Tersimpan', description: 'Daftar template dan versi form assessment.' },
    create: { title: 'Buat Template Dinamis', description: 'Susun form pra-asesmen, asesmen, atau pasca-asesmen dengan Tiptap.' },
    assignments: { title: 'Penugasan Assessment', description: 'Tugaskan form yang sudah dipublikasikan kepada asesi dan asesor.' },
};

const toast = ref({ show: false, message: '', type: 'success' });
const showToast = ({ message, type = 'success' }) => {
    toast.value = { show: true, message, type };
    setTimeout(() => (toast.value.show = false), 3000);
};
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <p class="text-xs uppercase tracking-wide text-[#7aab95]">Form Assessment</p>
            <h2 class="text-xl font-bold text-[#1e3329]">{{ page[props.mode].title }}</h2>
            <p class="text-[#7aab95] text-sm mt-1">{{ page[props.mode].description }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-[#dde8e3] p-6 shadow-sm">
            <AssessmentTemplateList v-if="props.mode === 'list'" @toast="showToast" />
            <AssessmentTemplateCreate v-else-if="props.mode === 'create'" @toast="showToast" />
            <AssessmentAssignmentForm v-else @toast="showToast" />
        </div>
        <BaseToast :show="toast.show" :message="toast.message" :type="toast.type" />
    </AppLayout>
</template>
