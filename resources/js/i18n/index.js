import { createI18n } from 'vue-i18n';
// La query "?i18n-precompile" la intercepta vite.config.js (plugin
// precompileI18nLocales): compila estos JSON a funciones en build time,
// para poder usar vue-i18n.runtime.esm-bundler.js (sin compilador JIT
// basado en `new Function`, bloqueado por la CSP) — ver vite.config.js.
import en from './locales/en.json?i18n-precompile';
import es from './locales/es.json?i18n-precompile';

function detectLocale() {
    // Check URL path prefix.
    const pathMatch = window.location.pathname.match(/^\/(en|es)(\/|$)/);
    if (pathMatch) return pathMatch[1];

    // Check HTML lang attribute.
    const htmlLang = document.documentElement.lang;
    if (htmlLang && ['en', 'es'].includes(htmlLang.substring(0, 2))) {
        return htmlLang.substring(0, 2);
    }

    // Check navigator language.
    const navLang = navigator.language?.substring(0, 2);
    if (navLang && ['en', 'es'].includes(navLang)) return navLang;

    return 'en';
}

const i18n = createI18n({
    legacy: false,
    locale: detectLocale(),
    fallbackLocale: 'en',
    messages: { en, es },
});

export default i18n;
