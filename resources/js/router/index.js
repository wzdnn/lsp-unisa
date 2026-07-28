// router/index.js
import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";
import api from "../services/api";
import Login from "../views/Login.vue";
import AdminPage from "../views/Admin/AdminPage.vue";
import AdminManagementPage from "../views/Admin/AdminManagementPage.vue";
import UserPage from "../views/Admin/UserPage.vue";
import DosenPage from "../views/Dosen/DosenPage.vue";
import MahasiswaPage from "../views/Mahasiswa/MahasiswaPage.vue";
import MahasiswaSertifikasiForm from "../views/Mahasiswa/MahasiswaSertifikasiForm.vue";

const routes = [
    { path: "/", component: Login },
    {
        path: "/admin",
        component: AdminPage,
        meta: { role: ["admin", "superadmin", "tendik"] },
    },
    {
        path: "/admin/user",
        component: UserPage,
        meta: { role: ["admin", "superadmin"] },
    },
    {
        path: "/admin/manajemen",
        component: AdminManagementPage,
        meta: { role: ["admin", "superadmin", "tendik"] },
    },
    {
        path: "/dosen",
        component: DosenPage,
        meta: { role: ["dosen", "asesor_luar"] },
    },
    {
        path: "/mahasiswa",
        component: MahasiswaPage,
        meta: { role: ["mahasiswa"] },
    },
    {
        path: "/mahasiswa/sertifikasi/:id",
        name: "mahasiswa-sertifikasi-form",
        component: MahasiswaSertifikasiForm,
        meta: { role: ["mahasiswa"] },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

let sessionChecked = false;

export function resetSessionCheck() {
    sessionChecked = false;
}

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!sessionChecked) {
        sessionChecked = true;

        try {
            const res = await api.get("/me");
            if (res.data && res.data.role) {
                auth.setUser({
                    username: res.data.username,
                    role: res.data.role,
                    lsp_user: res.data.lsp_user ?? null,
                });
            } else {
                auth.clearUser();
            }
        } catch {
            auth.clearUser();
        }
    }

    if (!to.meta.role) return true;
    if (!auth.user) return { path: "/" };
    if (to.meta.role.includes(auth.user.role)) return true;

    const redirectMap = {
        admin: "/admin",
        superadmin: "/admin",
        tendik: "/admin",
        dosen: "/dosen",
        asesor_luar: "/dosen",
        mahasiswa: "/mahasiswa",
    };

    return { path: redirectMap[auth.user.role] || "/" };
});

export default router;
