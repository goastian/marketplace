import './bootstrap';
import { createApp } from 'vue';
import i18n from './i18n';

const mountNode = document.getElementById('marketplace-app');
const surface = mountNode?.dataset?.surface;

if (surface === 'admin') {
    import('./AdminApp.vue').then((m) => {
        createApp(m.default).use(i18n).mount('#marketplace-app');
    });
} else {
    import('./StorefrontApp.vue').then((m) => {
        createApp(m.default).use(i18n).mount('#marketplace-app');
    });
}
