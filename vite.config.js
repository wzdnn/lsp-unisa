import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: "127.0.0.1",
        port: 5173,
        proxy: {
            "/api": {
                target: "http://127.0.0.1:8000",
                changeOrigin: false,
                secure: false,
                cookieDomainRewrite: "127.0.0.1",
                configure: (proxy) => {
                    proxy.on("proxyReq", (proxyReq, req) => {
                        if (req.headers.cookie) {
                            proxyReq.setHeader("cookie", req.headers.cookie);
                        }
                    });
                },
            },
        },
    },
});
