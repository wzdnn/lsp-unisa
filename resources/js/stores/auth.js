// stores/auth.js
import { defineStore } from "pinia";
import { resetSessionCheck } from "../router";
import api from "../services/api";

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: JSON.parse(localStorage.getItem("user")) || null,
    }),

    actions: {
        setUser(user) {
            this.user = user;
            localStorage.setItem("user", JSON.stringify(user));
        },

        async logout() {
            try {
                await api.post("/logout");
            } catch {
            } finally {
                this.user = null;
                localStorage.removeItem("user");
                resetSessionCheck();
            }
        },

        clearUser() {
            this.user = null;
            localStorage.removeItem("user");
        },
    },
});
