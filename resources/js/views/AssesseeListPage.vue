<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import AppLayout from "../layouts/AppLayout.vue";
import { assessmentService } from "../services/lspService";

const router = useRouter();
const assignments = ref([]);
const loading = ref(false);
const error = ref("");
const search = ref("");

const asesiName = (process) =>
    process?.asesi?.person?.namalengkap ||
    process?.asesi?.namalengkap ||
    process?.asesi?.username ||
    "-";
const statusLabel = (status) =>
    ({
        assigned: "Ditugaskan",
        draft: "Draft",
        submitted: "Menunggu review",
        under_review: "Sedang dinilai",
        revision_required: "Perlu revisi",
        assessed: "Sudah dinilai",
        result_published: "Hasil terbit",
        completed: "Selesai",
    })[status] || status;
const participants = computed(() => {
    const groups = new Map();
    assignments.value.forEach((assignment) => {
        const id = assignment.process?.id;
        if (!id) return;
        if (!groups.has(id))
            groups.set(id, { process: assignment.process, assignments: [] });
        groups.get(id).assignments.push(assignment);
    });
    const keyword = search.value.trim().toLowerCase();
    return [...groups.values()].filter(
        ({ process }) =>
            !keyword ||
            `${asesiName(process)} ${process.asesi?.username || ""} ${process.periode_skema?.skema?.skema || ""}`
                .toLowerCase()
                .includes(keyword),
    );
});
const countStatus = (items, statuses) =>
    items.filter((item) => statuses.includes(item.status)).length;
const openParticipant = ({ process, assignments: items }) => {
    const path = countStatus(items, ["under_review"])
        ? "/assessments/reviewing"
        : countStatus(items, ["submitted"])
          ? "/assessments/pending"
          : countStatus(items, ["revision_required"])
            ? "/assessments/assessor-revisions"
            : "/assessments/completed";
    router.push({ path, query: { process: process.id } });
};
onMounted(async () => {
    loading.value = true;
    try {
        assignments.value = (await assessmentService.getAll()).data;
    } catch (e) {
        error.value = e.response?.data?.message || "Gagal memuat daftar asesi";
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-[#1e3329]">
                    Daftar Asesi Saya
                </h2>
                <p class="mt-1 text-sm text-[#7aab95]">
                    Pilih peserta terlebih dahulu sebelum membuka instrumen
                    penilaian.
                </p>
            </div>
            <input
                v-model="search"
                type="search"
                placeholder="Cari nama, NIM, atau skema..."
                class="w-full rounded-lg border border-[#c8ddd6] bg-white px-4 py-2.5 text-sm outline-none focus:border-[#4a7c6b] sm:w-72"
            />
        </div>
        <div
            v-if="error"
            class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"
        >
            {{ error }}
        </div>
        <div v-if="loading" class="py-16 text-center text-sm text-slate-400">
            Memuat daftar asesi...
        </div>
        <div
            v-else-if="!participants.length"
            class="rounded-2xl border border-dashed border-[#c8ddd6] bg-white py-16 text-center text-sm text-slate-400"
        >
            Belum ada asesi yang ditugaskan kepada Anda.
        </div>
        <div v-else class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="participant in participants"
                :key="participant.process.id"
                class="rounded-2xl border border-[#dde8e3] bg-white p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs uppercase tracking-wide text-[#7aab95]"
                        >
                            {{
                                participant.process.periode_skema?.skema
                                    ?.skema || "Skema sertifikasi"
                            }}
                        </p>
                        <h3 class="mt-1 font-bold text-[#1e3329]">
                            {{ asesiName(participant.process) }}
                        </h3>
                        <p class="text-sm text-slate-500">
                            NIM: {{ participant.process.asesi?.username }}
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-[#eaf2ee] px-2.5 py-1 text-xs capitalize text-[#2d4a3e]"
                        >{{
                            participant.process.current_stage?.replace("_", " ")
                        }}</span
                    >
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-amber-50 p-2">
                        <strong class="block text-amber-700">{{
                            countStatus(participant.assignments, ["submitted"])
                        }}</strong
                        ><span class="text-[10px] text-amber-600"
                            >Menunggu</span
                        >
                    </div>
                    <div class="rounded-lg bg-blue-50 p-2">
                        <strong class="block text-blue-700">{{
                            countStatus(participant.assignments, [
                                "under_review",
                            ])
                        }}</strong
                        ><span class="text-[10px] text-blue-600">Dinilai</span>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-2">
                        <strong class="block text-emerald-700">{{
                            countStatus(participant.assignments, [
                                "completed",
                                "assessed",
                                "result_published",
                            ])
                        }}</strong
                        ><span class="text-[10px] text-emerald-600"
                            >Selesai</span
                        >
                    </div>
                </div>
                <div class="mt-3 space-y-1">
                    <p
                        v-for="item in participant.assignments.slice(0, 4)"
                        :key="item.id"
                        class="flex justify-between gap-2 text-xs text-slate-500"
                    >
                        <span>{{ item.version?.form?.code }}</span
                        ><span>{{ statusLabel(item.status) }}</span>
                    </p>
                </div>
                <button
                    type="button"
                    @click="openParticipant(participant)"
                    class="mt-5 w-full rounded-xl bg-[#2d4a3e] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#3d6355]"
                >
                    Buka Penilaian Asesi
                </button>
            </article>
        </div>
    </AppLayout>
</template>
