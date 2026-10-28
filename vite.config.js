import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        // Precompila los mensajes de i18n en build time (AST -> función),
        // para poder usar el build "runtime" de vue-i18n (ver alias abajo)
        // sin el compilador JIT basado en `new Function`, que exige
        // 'unsafe-eval' en script-src. Sin esto la CSP rompía el montaje
        // de la SPA por completo.
        VueI18nPlugin({
            include: fileURLToPath(new URL('./resources/js/i18n/locales/**', import.meta.url)),
        }),
    ],
    resolve: {
        alias: {
            'vue-i18n': 'vue-i18n/dist/vue-i18n.runtime.esm-bundler.js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
