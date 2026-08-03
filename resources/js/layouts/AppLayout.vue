<script setup>
import { ref, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";
import api from "../services/api";

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const collapsed = ref(false);

const navItems = computed(() => {
    if (auth.user?.role === "mahasiswa") {
        return [
            {
                label: "Sertifikasi",
                icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
                to: "/mahasiswa",
                activePaths: ["/mahasiswa/sertifikasi"],
            },
        ];
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
            label: "User & Asesor",
            icon: "M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z",
            to: "/admin/user",
        },
    ];
});

const logout = async () => {
    try {
        await api.post("/logout");
    } catch (e) {
        console.error("Logout error:", e);
    } finally {
        auth.clearUser();
        router.push("/");
    }
};

const isActive = (item) =>
    route.path === item.to ||
    item.activePaths?.some(
        (path) => route.path === path || route.path.startsWith(`${path}/`),
    ) ||
    item.children?.some((child) => isActive(child));

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
        <!-- Sidebar -->
        <aside
            :class="collapsed ? 'w-16' : 'w-64'"
            class="flex flex-col bg-[#1e3329] transition-all duration-300 ease-in-out shrink-0"
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
                        :class="[
                            isActive(item)
                                ? 'bg-[#4a7c6b] text-white'
                                : 'text-[#a8c5b8] hover:bg-[#2d4a3e] hover:text-white',
                            collapsed ? 'justify-center px-0' : 'px-3',
                        ]"
                        class="flex items-center gap-3 h-10 rounded-lg transition-all duration-150 group relative"
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

                        <!-- Active indicator -->
                        <div
                            v-if="isActive(item) && !collapsed"
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
                        v-if="item.children?.length && !collapsed"
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
            <div class="border-t border-[#2d4a3e] p-2 space-y-1">
                <!-- User info -->
                <div
                    :class="collapsed ? 'justify-center px-0' : 'px-3'"
                    class="flex items-center gap-3 h-12 overflow-hidden"
                >
                    <div
                        class="w-7 h-7 rounded-full bg-[#4a7c6b] flex items-center justify-center text-white text-xs font-bold shrink-0"
                    >
                        {{
                            auth.user?.username?.charAt(0)?.toUpperCase() || "A"
                        }}
                    </div>
                    <div
                        :class="collapsed ? 'opacity-0 w-0' : 'opacity-100'"
                        class="overflow-hidden transition-all duration-300 flex-1 min-w-0"
                    >
                        <p
                            class="text-sm text-white font-medium truncate leading-tight"
                        >
                            {{ auth.user?.username || "Admin" }}
                        </p>
                        <p class="text-xs text-[#7aab95] truncate capitalize">
                            {{ auth.user?.role || "admin" }}
                        </p>
                    </div>
                </div>

                <!-- Logout Button -->
                <button
                    @click="logout"
                    :title="collapsed ? 'Logout' : ''"
                    :class="collapsed ? 'justify-center px-0' : 'px-3'"
                    class="w-full flex items-center gap-3 h-9 rounded-lg text-[#a8c5b8] hover:bg-red-900/30 hover:text-red-300 transition-all duration-150 group relative"
                >
                    <svg
                        style="width: 18px; height: 18px"
                        class="shrink-0"
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
                    <span
                        :class="collapsed ? 'opacity-0 w-0' : 'opacity-100'"
                        class="text-sm whitespace-nowrap overflow-hidden transition-all duration-300"
                    >
                        Logout
                    </span>
                    <div
                        v-if="collapsed"
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
                    @click="collapsed = !collapsed"
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

                    <img
                        src="https://ppb.unisayogya.ac.id/wp-content/uploads/2017/08/cropped-logo-unisa-crop.png"
                        alt="Logo UNISA"
                        class="h-8 w-auto object-contain"
                    />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-[#f0f4f1]">
                <slot />
            </main>
        </div>
    </div>
</template>
