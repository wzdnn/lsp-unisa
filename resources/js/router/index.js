// router/index.js
import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";
import api from "../services/api";
import Login from "../views/Login.vue";
import AdminPage from "../views/Admin/AdminPage.vue";
import AdminManagementPage from "../views/Admin/AdminManagementPage.vue";
import AdminAssesmentPage from "../views/Admin/AdminAssesmentPage.vue";
import UserPage from "../views/Admin/UserPage.vue";
import DosenPage from "../views/Dosen/DosenPage.vue";
import MahasiswaPage from "../views/Mahasiswa/MahasiswaPage.vue";
import MahasiswaSertifikasiForm from "../views/Mahasiswa/MahasiswaSertifikasiForm.vue";
const AssessmentPage = () => import("../views/AssessmentPage.vue");
const AssesseeListPage = () => import("../views/AssesseeListPage.vue");
const AssessmentFormPage = () => import("../views/Admin/AssessmentFormPage.vue");

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
        path: "/admin/assesment",
        redirect: "/admin/assesment/pendaftaran",
    },
    {
        path: "/admin/assesment/:stage",
        component: AdminAssesmentPage,
        meta: { role: ["admin", "superadmin", "tendik"] },
    },
    {
        path: "/admin/assessment-forms",
        component: AssessmentFormPage,
        props: { mode: "list" },
        meta: { role: ["admin", "superadmin", "tendik"] },
    },
    {
        path: "/admin/assessment-forms/create",
        component: AssessmentFormPage,
        props: { mode: "create" },
        meta: { role: ["admin", "superadmin", "tendik"] },
    },
    {
        path: "/admin/assessment-forms/assignments",
        redirect: "/admin/assesment/pre-assesment",
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
    {
        path: "/assessments",
        component: AssessmentPage,
        props: { view: "all" },
        meta: { role: ["mahasiswa", "dosen", "asesor_luar"] },
    },
    {
        path: "/assessments/active",
        component: AssessmentPage,
        props: { view: "active" },
        meta: { role: ["mahasiswa"] },
    },
    {
        path: "/assessments/revision",
        component: AssessmentPage,
        props: { view: "revision" },
        meta: { role: ["mahasiswa"] },
    },
    {
        path: "/assessments/history",
        component: AssessmentPage,
        props: { view: "history" },
        meta: { role: ["mahasiswa"] },
    },
    {
        path: "/assessments/assessees",
        component: AssesseeListPage,
        meta: { role: ["dosen", "asesor_luar"] },
    },
    {
        path: "/assessments/pending",
        component: AssessmentPage,
        props: { view: "pending" },
        meta: { role: ["dosen", "asesor_luar"] },
    },
    {
        path: "/assessments/reviewing",
        component: AssessmentPage,
        props: { view: "reviewing" },
        meta: { role: ["dosen", "asesor_luar"] },
    },
    {
        path: "/assessments/assessor-revisions",
        component: AssessmentPage,
        props: { view: "assessor_revision" },
        meta: { role: ["dosen", "asesor_luar"] },
    },
    {
        path: "/assessments/completed",
        component: AssessmentPage,
        props: { view: "completed" },
        meta: { role: ["dosen", "asesor_luar"] },
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
