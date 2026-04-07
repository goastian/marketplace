import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import es from './locales/es.json';

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
