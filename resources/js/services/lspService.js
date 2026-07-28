import api from "./api";

export const periodeService = {
    getAll: () => api.get("/periode"),
    getOne: (id) => api.get(`/periode/${id}`),
    create: (data) => api.post("/periode", data),
    update: (id, data) => api.put(`/periode/${id}`, data),
    destroy: (id) => api.delete(`/periode/${id}`),
};

export const masaService = {
    getAll: (periodeId) => api.get(`/periode/${periodeId}/masa`),
    create: (periodeId, data) => api.post(`/periode/${periodeId}/masa`, data),
    update: (periodeId, masaId, data) =>
        api.put(`/periode/${periodeId}/masa/${masaId}`, data),
    destroy: (periodeId, masaId) =>
        api.delete(`/periode/${periodeId}/masa/${masaId}`),
    toggle: (periodeId, masaId) =>
        api.patch(`/periode/${periodeId}/masa/${masaId}/toggle`),
};

export const skemaService = {
    getAll: () => api.get("/skema"),
    create: (data) => api.post("/skema", data),
    update: (id, data) => api.put(`/skema/${id}`, data),
    destroy: (id) => api.delete(`/skema/${id}`),
    toggle: (id) => api.patch(`/skema/${id}/toggle`),
};

export const plottingService = {
    getAll: (params) => api.get("/periode-skema", { params }),
    getOne: (id) => api.get(`/periode-skema/${id}`),
    create: (data) => api.post("/periode-skema", data),
    destroy: (id) => api.delete(`/periode-skema/${id}`),
    bulkDestroy: (ids) => api.delete("/periode-skema/bulk", { data: { ids } }),
};

export const apl01PengajuanService = {
    getAll: (params) => api.get("/apl01-pengajuan", { params }),
    getCurrent: (params) => api.get("/apl01-pengajuan/current", { params }),
    getOne: (id) => api.get(`/apl01-pengajuan/${id}`),
    save: (data) => api.post("/apl01-pengajuan", data),
    review: (id, data) => api.patch(`/apl01-pengajuan/${id}/review`, data),
};

export const userService = {
    getAll: (params) => api.get("/users", { params }),
    getDosenInternal: () => api.get("/users/dosen-internal"),
    getAsesor: () => api.get("/users/asesor"),
    getUnitKerja: () => api.get("/users/unit-kerja"),
    toggleAsesor: (id) => api.patch(`/users/${id}/toggle-asesor`),
    storeAsesorLuar: (data) => api.post("/users/asesor-luar", data),
    update: (id, data) => api.put(`/users/${id}`, data),
    destroy: (id) => api.delete(`/users/${id}`),
};

export const unitKompetensiService = {
    getAll(params = {}) {
        return api.get("/unit-kompetensi", { params });
    },
    create(data) {
        return api.post("/unit-kompetensi", data);
    },
    update(id, data) {
        return api.put(`/unit-kompetensi/${id}`, data);
    },
    destroy(id) {
        return api.delete(`/unit-kompetensi/${id}`);
    },
    bulkDestroy(ids) {
        return api.delete("/unit-kompetensi/bulk", { data: { ids } });
    },
};

export const apl01DokumenService = {
    getAll: (pengajuanId) => api.get(`/apl01-pengajuan/${pengajuanId}/dokumen`),

    upload: (pengajuanId, jenisDokumen, file) => {
        const form = new FormData();
        form.append("jenis_dokumen", jenisDokumen);
        form.append("file", file);
        return api.post(`/apl01-pengajuan/${pengajuanId}/dokumen`, form);
    },

    destroy: (pengajuanId, dokumenId) =>
        api.delete(`/apl01-pengajuan/${pengajuanId}/dokumen/${dokumenId}`),

    updateStatus: (pengajuanId, dokumenId, status) =>
        api.patch(
            `/apl01-pengajuan/${pengajuanId}/dokumen/${dokumenId}/status`,
            { status },
        ),
};
