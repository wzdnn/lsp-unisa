<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
const router = useRouter();
onMounted(() => router.replace('/assessments/pending'));
</script>

<template><p class="p-6">Membuka daftar assessment...</p></template>
