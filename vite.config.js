import { fileURLToPath, URL } from 'node:url';
import { readFile } from 'node:fs/promises';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { generateJSON } from '@intlify/bundle-utils';

// Precompila resources/js/i18n/locales/*.json (AST -> función) en build
// time, para poder usar vue-i18n.runtime.esm-bundler.js (ver alias abajo)
// sin su compilador JIT (new Function), que exige 'unsafe-eval' en
// script-src. Sin esto la CSP rompía por completo el montaje de la SPA.
//
// @intlify/unplugin-vue-i18n (v11.2.8) se probó primero, pero fuerza
// `jit: true` sin excepción para imports directos de JSON como los
// nuestros (no hay opción pública que lo desactive en esta versión) — de
// ahí este transform propio, mínimo, usando la misma librería interna
// (@intlify/bundle-utils) que ese plugin usa por debajo.
function precompileI18nLocales() {
    const SUFFIX = '?i18n-precompile';
    return {
        name: 'precompile-i18n-locales',
        enforce: 'pre',
        resolveId(source, importer) {
            if (!source.endsWith(SUFFIX)) return;
            const real = source.slice(0, -SUFFIX.length);
            return this.resolve(real, importer, { skipSelf: true }).then((r) => r && r.id + SUFFIX);
        },
        async load(id) {
            // El sufijo propio evita que el loader nativo de .json de Vite
            // (que haría JSON.parse sobre código ya transformado) intercepte
            // este archivo primero.
            if (!id.endsWith(SUFFIX)) return;
            const real = id.slice(0, -SUFFIX.length);
            const raw = await readFile(real, 'utf8');
            const { code: generated } = await generateJSON(raw, { type: 'plain', env: 'production' });
            return { code: generated, map: null };
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        precompileI18nLocales(),
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
