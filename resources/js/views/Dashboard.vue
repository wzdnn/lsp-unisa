<script setup>
import { useAuthStore } from "../stores/auth";
import { useRoute } from "vue-router";

const auth = useAuthStore();

const logout = () => {
    auth.logout();
    router.push("/");
};
</script>

<template>
    <div>
        <h1>Dashboard</h1>
        <p>Login sebagai: {{ auth.user.username }}</p>
        <p>Role: {{ auth.user.role }}</p>

        <button @click="logout">Logout</button>
    </div>
</template>
