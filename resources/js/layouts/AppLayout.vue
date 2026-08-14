<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const collapsed = ref(localStorage.getItem("sidebar-collapsed") === "true");
const mobileSidebarOpen = ref(false);
const profileOpen = ref(false);
const logoutModalOpen = ref(false);
const loggingOut = ref(false);
const expandedMenus = ref(new Set());

const profileName = computed(() =>
    auth.user?.lsp_user?.person?.namalengkap ||
    auth.user?.lsp_user?.namalengkap ||
    auth.user?.username ||
    "Pengguna",
);

const roleLabel = computed(() => ({
    superadmin: "Super Admin",
    admin: "Admin",
    tendik: "Tenaga Kependidikan",
    dosen: "Dosen / Asesor",
    asesor_luar: "Asesor Eksternal",
    mahasiswa: "Mahasiswa",
}[auth.user?.role] || auth.user?.role || "Pengguna"));

const profileInitial = computed(() => profileName.value.charAt(0).toUpperCase());

const toggleSidebar = () => {
    if (window.innerWidth < 768) {
        if (!mobileSidebarOpen.value && collapsed.value) {
            collapsed.value = false;
            localStorage.setItem("sidebar-collapsed", "false");
        }
        mobileSidebarOpen.value = !mobileSidebarOpen.value;
        return;
    }

    collapsed.value = !collapsed.value;
    localStorage.setItem("sidebar-collapsed", String(collapsed.value));
};

const closeOverlays = () => {
    mobileSidebarOpen.value = false;
    profileOpen.value = false;
};

const handleEscape = (event) => {
    if (event.key !== "Escape") return;
    if (logoutModalOpen.value && !loggingOut.value) logoutModalOpen.value = false;
    else closeOverlays();
};

onMounted(() => window.addEventListener("keydown", handleEscape));
onBeforeUnmount(() => window.removeEventListener("keydown", handleEscape));

const navItems = computed(() => {
    if (auth.user?.role === "mahasiswa") {
        return [
            {
                label: "Sertifikasi",
                icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
                to: "/mahasiswa",
                activePaths: ["/mahasiswa/sertifikasi"],
            },
            {
                label: "Assessment Saya",
                icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
                to: "/assessments/active",
                children: [
                    { label: "Tugas Aktif", to: "/assessments/active" },
                    { label: "Perlu Revisi", to: "/assessments/revision" },
                    { label: "Riwayat", to: "/assessments/history" },
                ],
            },
        ];
    }

    if (["dosen", "asesor_luar"].includes(auth.user?.role)) {
        return [{
            label: "Penilaian Asesi",
            icon: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
            to: "/assessments/assessees",
            activePaths: ["/assessments"],
            children: [
                { label: "Daftar Asesi", to: "/assessments/assessees" },
            ],
        }];
    }

    return [
        {
            label: "Dashboard",
            icon: "M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6",
            to: "/admin",
        },
        {
            label: "Manajemen LSP",
            icon: "M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
            to: "/admin/manajemen",
        },
        {
            label: "Assesment",
            icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
            to: "/admin/assesment/pendaftaran",
            activePaths: ["/admin/assesment"],
            children: [
                {
                    label: "Pendaftaran",
                    to: "/admin/assesment/pendaftaran",
                },
                {
                    label: "Pre-Assesment",
                    to: "/admin/assesment/pre-assesment",
                },
                {
                    label: "Assesment",
                    to: "/admin/assesment/assesment",
                },
                {
                    label: "Post-Assesment",
                    to: "/admin/assesment/post-assesment",
                },
            ],
        },
        {
            label: "Form Assessment",
            icon: "M9 12l2 2 4-4m5-2a8 8 0 11-16 0 8 8 0 0116 0z",
            to: "/admin/assessment-forms",
            children: [
                { label: "Template Tersimpan", to: "/admin/assessment-forms" },
                { label: "Buat Template", to: "/admin/assessment-forms/create" },
                { label: "Proses & Penugasan", to: "/admin/assessment-forms/assignments" },
            ],
        },
        {
            label: "User & Asesor",
            icon: "M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z",
            to: "/admin/user",
        },
    ];
});

const logout = async () => {
    if (loggingOut.value) return;
    loggingOut.value = true;

    try {
        await auth.logout();
        logoutModalOpen.value = false;
        profileOpen.value = false;
        await router.replace("/");
    } finally {
        loggingOut.value = false;
    }
};

const isActive = (item) =>
    route.path === item.to ||
    item.activePaths?.some(
        (path) => route.path === path || route.path.startsWith(`${path}/`),
    ) ||
    item.children?.some((child) => isActive(child));

for (const item of navItems.value) {
    if (item.children?.length && isActive(item)) {
        expandedMenus.value.add(item.to);
    }
}

const isMenuExpanded = (item) => expandedMenus.value.has(item.to);

const handleMenuClick = (item) => {
    if (!item.children?.length) {
        mobileSidebarOpen.value = false;
        return;
    }

    const nextExpanded = new Set(expandedMenus.value);
    if (nextExpanded.has(item.to)) nextExpanded.delete(item.to);
    else nextExpanded.add(item.to);
    expandedMenus.value = nextExpanded;
};

const activeNavLabel = computed(() => {
    for (const item of navItems.value) {
        const activeChild = item.children?.find((child) => isActive(child));
        if (activeChild) return activeChild.label;
        if (isActive(item)) return item.label;
    }
    return "Dashboard";
});
</script>

<template>
    <div class="flex h-screen bg-[#f0f4f1] overflow-hidden">
        <Transition name="fade">
            <button
                v-if="mobileSidebarOpen"
                type="button"
                aria-label="Tutup sidebar"
                class="fixed inset-0 z-30 bg-slate-950/45 md:hidden"
                @click="mobileSidebarOpen = false"
            ></button>
        </Transition>

        <!-- Sidebar -->
        <aside
            :class="[
                collapsed ? 'md:w-16' : 'md:w-64',
                mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
            ]"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#1e3329] shadow-xl transition-all duration-300 ease-in-out shrink-0 md:relative md:shadow-none"
        >
            <!-- Logo -->
            <div
                class="flex items-center h-16 px-3 border-b border-[#2d4a3e] overflow-hidden"
            >
                <div
                    class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#f0f4f1] shrink-0 overflow-hidden"
                >
                    <img
                        src="https://ppb.unisayogya.ac.id/wp-content/uploads/2017/08/cropped-logo-unisa-crop.png"
                        alt="Logo UNISA"
                        class="w-8 h-8 object-contain"
                    />
                </div>
                <div
                    :class="
                        collapsed
                            ? 'opacity-0 w-0 ml-0'
                            : 'opacity-100 w-auto ml-3'
                    "
                    class="overflow-hidden transition-all duration-300"
                >
                    <p
                        class="text-white font-bold text-sm leading-tight whitespace-nowrap"
                    >
                        Sistem LSP
                    </p>
                    <p class="text-[#7aab95] text-xs whitespace-nowrap">
                        UNISA Yogyakarta
                    </p>
                </div>
            </div>

            <!-- Nav Items -->
            <nav class="flex-1 py-3 space-y-0.5 px-2 overflow-y-auto">
                <div
                    v-for="item in navItems"
                    :key="item.to"
                    class="space-y-0.5"
                >
                    <router-link
                        :to="item.to"
                        :title="collapsed ? item.label : ''"
                        :aria-expanded="item.children?.length ? isMenuExpanded(item) : undefined"
                        :class="[
                            isActive(item)
                                ? 'bg-[#4a7c6b] text-white'
                                : 'text-[#a8c5b8] hover:bg-[#2d4a3e] hover:text-white',
                            collapsed ? 'justify-center px-0' : 'px-3',
                        ]"
                        class="flex items-center gap-3 h-10 rounded-lg transition-all duration-150 group relative"
                        @click="handleMenuClick(item)"
                    >
                        <svg
                            class="w-4.5 h-4.5 shrink-0"
                            style="width: 18px; height: 18px"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                :d="item.icon"
                            />
                        </svg>
                        <span
                            :class="
                                collapsed ? 'opacity-0 w-0' : 'opacity-100'
                            "
                            class="text-sm whitespace-nowrap overflow-hidden transition-all duration-300"
                        >
                            {{ item.label }}
                        </span>

                        <!-- Expand indicator -->
                        <svg
                            v-if="item.children?.length && !collapsed"
                            :class="isMenuExpanded(item) ? 'rotate-180' : ''"
                            class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7" />
                        </svg>

                        <!-- Active indicator -->
                        <div
                            v-if="isActive(item) && !item.children?.length && !collapsed"
                            class="absolute right-2 w-1.5 h-1.5 rounded-full bg-[#a8d5c2]"
                        ></div>

                        <!-- Tooltip -->
                        <div
                            v-if="collapsed"
                            class="absolute left-full ml-3 px-2.5 py-1.5 bg-[#1e3329] border border-[#2d4a3e] text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 z-50"
                        >
                            {{ item.label }}
                        </div>
                    </router-link>

                    <div
                        v-if="item.children?.length && !collapsed && isMenuExpanded(item)"
                        class="ml-5 pl-3 border-l border-[#2d4a3e] space-y-0.5"
                    >
                        <router-link
                            v-for="child in item.children"
                            :key="child.to"
                            :to="child.to"
                            :class="
                                isActive(child)
                                    ? 'text-white bg-[#365f51]'
                                    : 'text-[#8fb2a4] hover:bg-[#2d4a3e] hover:text-white'
                            "
                            class="flex items-center gap-2 h-8 px-3 rounded-lg text-xs font-medium transition-all"
                            @click="mobileSidebarOpen = false"
                        >
                            <span
                                :class="
                                    isActive(child)
                                        ? 'bg-[#a8d5c2]'
                                        : 'bg-[#4a7c6b]'
                                "
                                class="w-1.5 h-1.5 rounded-full shrink-0"
                            ></span>
                            <span class="truncate">{{ child.label }}</span>
                        </router-link>
                    </div>
                </div>
            </nav>

            <!-- User & Logout -->
            <div
                :class="collapsed ? 'flex-col gap-1' : 'flex-row items-center gap-1'"
                class="flex border-t border-[#2d4a3e] p-2"
            >
                <!-- User info -->
                <button
                    type="button"
                    @click="profileOpen = true"
                    :title="collapsed ? 'Lihat profil' : ''"
                    :class="collapsed ? 'justify-center px-0' : 'min-w-0 flex-1 px-3'"
                    class="group relative flex items-center gap-3 h-12 overflow-hidden rounded-lg text-left hover:bg-[#2d4a3e] transition-colors"
                >
                    <div
                        class="w-7 h-7 rounded-full bg-[#4a7c6b] flex items-center justify-center text-white text-xs font-bold shrink-0"
                    >
                        {{
                            profileInitial
                        }}
                    </div>
                    <div
                        :class="collapsed ? 'opacity-0 w-0' : 'opacity-100'"
                        class="overflow-hidden transition-all duration-300 flex-1 min-w-0"
                    >
                        <p
                            class="text-sm text-white font-medium truncate leading-tight"
                        >
                            {{ profileName }}
                        </p>
                        <p class="text-xs text-[#7aab95] truncate capitalize">
                            {{ roleLabel }}
                        </p>
                    </div>
                    <div
                        v-if="collapsed"
                        class="absolute left-full ml-3 px-2.5 py-1.5 bg-[#1e3329] border border-[#2d4a3e] text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50"
                    >
                        Lihat profil
                    </div>
                </button>

                <!-- Logout Button -->
                <button
                    @click="logoutModalOpen = true"
                    type="button"
                    title="Logout"
                    aria-label="Logout"
                    class="group relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-[#a8c5b8] hover:bg-red-900/30 hover:text-red-300 transition-all duration-150"
                >
                    <svg
                        class="h-[18px] w-[18px]"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                        />
                    </svg>
                    <div
                        class="absolute left-full ml-3 px-2.5 py-1.5 bg-[#1e3329] border border-[#2d4a3e] text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 z-50"
                    >
                        Logout
                    </div>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Topbar -->
            <header
                class="flex items-center h-16 px-5 bg-white border-b border-[#dde8e3] shrink-0"
            >
                <button
                    @click="toggleSidebar"
                    type="button"
                    :aria-label="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
                    :aria-expanded="!collapsed"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-[#4a7c6b] hover:bg-[#eaf2ee] transition-all duration-150"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>

                <div class="ml-4">
                    <p
                        class="text-xs text-[#7aab95] font-medium uppercase tracking-wider"
                    >
                        LSP UNISA
                    </p>
                    <h1
                        class="text-sm font-semibold text-[#1e3329] leading-tight"
                    >
                        {{ activeNavLabel }}
                    </h1>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-[#4a7c6b] hover:bg-[#eaf2ee] transition-all duration-150 relative"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                        </svg>
                    </button>

                    <div class="h-6 w-px bg-[#dde8e3]"></div>

                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg p-1 hover:bg-[#eaf2ee] transition-colors"
                            aria-label="Buka menu profil"
                            :aria-expanded="profileOpen"
                            @click="profileOpen = !profileOpen"
                        >
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#4a7c6b] text-xs font-bold text-white">{{ profileInitial }}</span>
                            <svg class="hidden h-4 w-4 text-[#4a7c6b] sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <div
                            v-if="profileOpen"
                            class="absolute right-0 top-full z-30 mt-2 w-72 overflow-hidden rounded-xl border border-[#dde8e3] bg-white shadow-xl"
                        >
                            <div class="border-b border-[#e7efeb] p-4">
                                <p class="truncate text-sm font-semibold text-[#1e3329]">{{ profileName }}</p>
                                <p class="mt-0.5 truncate text-xs text-[#7aab95]">{{ auth.user?.username }}</p>
                                <span class="mt-2 inline-flex rounded-full bg-[#eaf2ee] px-2.5 py-1 text-xs font-medium text-[#365f51]">{{ roleLabel }}</span>
                            </div>
                            <button type="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50" @click="profileOpen = false; logoutModalOpen = true">
                                Keluar dari akun
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-[#f0f4f1]">
                <slot />
            </main>
        </div>

        <!-- Profile detail -->
        <div v-if="profileOpen" class="fixed inset-0 z-20" @click="profileOpen = false"></div>

        <!-- Logout confirmation -->
        <Transition name="fade">
            <div
                v-if="logoutModalOpen"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="logout-title"
                @click.self="!loggingOut && (logoutModalOpen = false)"
            >
                <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    </div>
                    <h2 id="logout-title" class="text-lg font-semibold text-slate-900">Konfirmasi logout</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Apakah Anda yakin ingin keluar dari akun ini?</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" :disabled="loggingOut" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 disabled:opacity-50" @click="logoutModalOpen = false">Batal</button>
                        <button type="button" :disabled="loggingOut" class="min-w-24 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-wait disabled:opacity-60" @click="logout">
                            {{ loggingOut ? "Memproses..." : "Ya, logout" }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
