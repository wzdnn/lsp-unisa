<script setup>
import { ref } from "vue";
import api from "../services/api";
import { useAuthStore } from "../stores/auth";
import { useRouter } from "vue-router";
import { resetSessionCheck } from "../router"; // ← tambah import

const auth = useAuthStore();
const router = useRouter();

const username = ref("");
const password = ref("");
const error = ref("");
const loading = ref(false);

const login = async () => {
    error.value = "";
    loading.value = true;

    try {
        await api.post("/login", {
            username: username.value,
            password: password.value,
        });

        const me = await api.get("/me");

        auth.setUser({
            username: me.data.username,
            role: me.data.role,
            lsp_user: me.data.lsp_user ?? null,
        });

        resetSessionCheck();

        const role = auth.user.role;
        if (role === "admin" || role === "superadmin" || role === "tendik") {
            router.push("/admin");
        } else if (role === "dosen") {
            router.push("/dosen");
        } else if (role === "mahasiswa") {
            router.push("/mahasiswa");
        } else {
            router.push("/");
        }
    } catch (err) {
        error.value = err.response?.data?.message || "Login gagal";
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen flex">
        <!-- Left Panel -->
        <div
            class="hidden lg:flex lg:w-1/2 bg-[#2d4a3e] flex-col items-center justify-center px-12 relative overflow-hidden"
        >
            <!-- subtle pattern -->
            <div class="absolute inset-0 opacity-5">
                <div
                    class="absolute top-0 left-0 w-full h-full"
                    style="
                        background-image: radial-gradient(
                            circle at 2px 2px,
                            #fff 1px,
                            transparent 0
                        );
                        background-size: 32px 32px;
                    "
                ></div>
            </div>
            <div
                class="absolute top-0 right-0 w-64 h-64 bg-[#4a7c6b]/20 rounded-full -translate-y-32 translate-x-32"
            ></div>
            <div
                class="absolute bottom-0 left-0 w-48 h-48 bg-[#4a7c6b]/20 rounded-full translate-y-24 -translate-x-24"
            ></div>

            <div class="relative text-center">
                <div
                    class="inline-flex items-center justify-center w-20 h-20 bg-white/10 rounded-2xl mb-6 backdrop-blur-sm border border-white/20"
                >
                    <img
                        src="https://ppb.unisayogya.ac.id/wp-content/uploads/2017/08/cropped-logo-unisa-crop.png"
                        alt="Logo UNISA"
                        class="w-14 h-14 object-contain"
                    />
                </div>
                <h1 class="text-3xl font-bold text-white mb-3 leading-tight">
                    Lembaga Sertifikasi<br />Profesi
                </h1>
                <p class="text-[#a8c5b8] text-sm leading-relaxed max-w-xs">
                    Universitas 'Aisyiyah Yogyakarta — Platform pengelolaan
                    sertifikasi profesi yang terintegrasi.
                </p>

                <div class="mt-10 grid grid-cols-3 gap-4 text-center">
                    <div
                        class="bg-white/10 rounded-xl p-3 border border-white/10"
                    >
                        <p class="text-white font-bold text-xl">LSP</p>
                        <p class="text-[#a8c5b8] text-xs mt-0.5">Sertifikasi</p>
                    </div>
                    <div
                        class="bg-white/10 rounded-xl p-3 border border-white/10"
                    >
                        <p class="text-white font-bold text-xl">BNSP</p>
                        <p class="text-[#a8c5b8] text-xs mt-0.5">
                            Terakreditasi
                        </p>
                    </div>
                    <div
                        class="bg-white/10 rounded-xl p-3 border border-white/10"
                    >
                        <p class="text-white font-bold text-xl">Pro</p>
                        <p class="text-[#a8c5b8] text-xs mt-0.5">Sistem</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel (Form) -->
        <div
            class="w-full lg:w-1/2 bg-[#f5f7f5] flex items-center justify-center px-6 py-12"
        >
            <div class="w-full max-w-sm">
                <!-- Mobile logo -->
                <div class="flex lg:hidden justify-center mb-8">
                    <img
                        src="https://ppb.unisayogya.ac.id/wp-content/uploads/2017/08/cropped-logo-unisa-crop.png"
                        alt="Logo UNISA"
                        class="h-16 w-auto object-contain"
                    />
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-[#1e3329]">
                        Selamat Datang
                    </h2>
                    <p class="text-[#6b8c7d] text-sm mt-1">
                        Masuk ke Sistem LSP UNISA Yogyakarta
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- Username -->
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Username
                        </label>
                        <div class="relative">
                            <div
                                class="absolute inset-y-0 left-3.5 flex items-center pointer-events-none"
                            >
                                <svg
                                    class="w-4 h-4 text-[#7aab95]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                                    />
                                </svg>
                            </div>
                            <input
                                v-model="username"
                                type="text"
                                placeholder="Masukkan username"
                                @keyup.enter="login"
                                class="w-full bg-white border border-[#c8ddd6] text-[#1e3329] placeholder-[#adc4bb] rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all duration-200"
                            />
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-semibold text-[#3d6355] uppercase tracking-wider"
                        >
                            Password
                        </label>
                        <div class="relative">
                            <div
                                class="absolute inset-y-0 left-3.5 flex items-center pointer-events-none"
                            >
                                <svg
                                    class="w-4 h-4 text-[#7aab95]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                                    />
                                </svg>
                            </div>
                            <input
                                v-model="password"
                                type="password"
                                placeholder="Masukkan password"
                                @keyup.enter="login"
                                class="w-full bg-white border border-[#c8ddd6] text-[#1e3329] placeholder-[#adc4bb] rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:border-[#4a7c6b] focus:ring-2 focus:ring-[#4a7c6b]/15 transition-all duration-200"
                            />
                        </div>
                    </div>

                    <!-- Error -->
                    <div
                        v-if="error"
                        class="flex items-center gap-2.5 bg-red-50 border border-red-200 text-red-600 rounded-xl px-4 py-3 text-sm"
                    >
                        <svg
                            class="w-4 h-4 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                            />
                        </svg>
                        {{ error }}
                    </div>

                    <!-- Button -->
                    <button
                        @click="login"
                        :disabled="loading"
                        class="w-full mt-1 bg-[#2d4a3e] hover:bg-[#3d6355] active:bg-[#1e3329] disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl py-3 text-sm transition-all duration-200 flex items-center justify-center gap-2 shadow-md shadow-[#2d4a3e]/20"
                    >
                        <svg
                            v-if="loading"
                            class="w-4 h-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                            ></path>
                        </svg>
                        {{ loading ? "Memproses..." : "Masuk ke Sistem" }}
                    </button>
                </div>

                <p class="text-center text-[#9db8ae] text-xs mt-8">
                    &copy; {{ new Date().getFullYear() }} LSP Universitas
                    'Aisyiyah Yogyakarta
                </p>
            </div>
        </div>
    </div>
</template>
