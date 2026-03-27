import './bootstrap';
import { createApp } from 'vue';

const mountNode = document.getElementById('marketplace-app');
const surface = mountNode?.dataset?.surface;

if (surface === 'admin') {
    import('./AdminApp.vue').then((m) => {
        createApp(m.default).mount('#marketplace-app');
    });
} else {
    import('./StorefrontApp.vue').then((m) => {
        createApp(m.default).mount('#marketplace-app');
    });
}
