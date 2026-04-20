<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const API_BASE = '/api/v1';
const LOGIN_URL = '/auth/login?next=/admin';

const loading = ref(false);
const error = ref('');
const message = ref('');
const actionLoading = ref(false);

const user = ref(null);
const assets = ref([]);

const assetTypes = ['theme', 'wallpaper', 'widget', 'animation', 'collection', 'midori-update'];

const form = reactive({
    id: null,
    type: 'theme',
    slug: '',
    name: '',
    description: '',
    author: '',
    license: '',
    tags: '',
    status: 'draft',
});

const versionForm = reactive({
    assetId: '',
    version: '',
    status: 'draft',
    minAppVersion: '',
    maxAppVersion: '',
    browsers: ['chrome', 'firefox', 'midori'],
    manifest: '{\n  "schemaVersion": "1.0.0",\n  "kind": "midori-asset"\n}',
    file: null,
});

const isAuthenticated = computed(() => !!user.value);

const counters = computed(() => {
    const all = assets.value;
    return {
        total: all.length,
        published: all.filter((a) => a.status === 'published').length,
        draft: all.filter((a) => a.status === 'draft').length,
        versions: all.reduce((s, a) => s + a.versions.length, 0),
    };
});

function authHeaders() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    return csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};
}

async function apiFetch(path, opts = {}) {
    const res = await fetch(`${API_BASE}${path}`, {
        ...opts,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...authHeaders(), ...(opts.headers || {}) },
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(body?.message || `Error ${res.status}`);
        err.status = res.status;
        throw err;
    }
    return body;
}

function statusLabel(s) {
    return s === 'published' ? t('admin.published') : s === 'draft' ? t('admin.drafts') : s;
}

const otherLocale = computed(() => (locale.value === 'es' ? 'en' : 'es'));
const localeLabel = computed(() => (locale.value === 'es' ? 'EN' : 'ES'));

function switchLocale() {
    locale.value = otherLocale.value;
}

function formatDate(d) {
    if (!d) return '-';
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(d));
}

async function authenticate() {
    loading.value = true;
    error.value = '';
    try {
        const me = await apiFetch('/me');
        user.value = me.data;
        await loadAssets();
    } catch {
        user.value = null;
        error.value = 'Session expired. Please log in again.';
    } finally {
        loading.value = false;
    }
}

function logout() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/auth/logout';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = '_token';
    input.value = csrfToken || '';
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function startLogin() {
    window.top.location.href = LOGIN_URL;
}

async function loadAssets() {
    const payload = await apiFetch('/me/assets');
    assets.value = (payload.data || []).map((a) => ({
        ...a,
        tags: Array.isArray(a.tags) ? a.tags : [],
        versions: Array.isArray(a.versions) ? a.versions : [],
    }));
}

function sanitizeSlug(v) {
    return v.toLowerCase().trim().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-');
}

function editAsset(a) {
    form.id = a.id;
    form.type = a.type;
    form.slug = a.slug;
    form.name = a.name;
    form.description = a.description || '';
    form.author = a.author || '';
    form.license = a.license || '';
    form.tags = a.tags.join(', ');
    form.status = a.status;
}

function resetForm() {
    form.id = null;
    form.type = 'theme';
    form.slug = '';
    form.name = '';
    form.description = '';
    form.author = '';
    form.license = '';
    form.tags = '';
    form.status = 'draft';
}

function resetVersionForm() {
    versionForm.assetId = '';
    versionForm.version = '';
    versionForm.status = 'draft';
    versionForm.minAppVersion = '';
    versionForm.maxAppVersion = '';
    versionForm.browsers = ['chrome', 'firefox', 'midori'];
    versionForm.manifest = '{\n  "schemaVersion": "1.0.0",\n  "kind": "midori-asset"\n}';
    versionForm.file = null;
}

async function saveAsset() {
    actionLoading.value = true;
    error.value = '';
    message.value = '';
    try {
        const slug = form.slug.trim() ? sanitizeSlug(form.slug) : sanitizeSlug(form.name);
        const body = {
            type: form.type,
            slug,
            name: form.name.trim(),
            description: form.description.trim() || null,
            author: form.author.trim() || null,
            license: form.license.trim() || null,
            tags: form.tags.split(',').map((t) => t.trim()).filter(Boolean),
            status: form.status,
        };
        if (!body.name || !body.slug) throw new Error('Nombre y slug son obligatorios.');

        const path = form.id ? `/me/assets/${form.id}` : '/me/assets';
        const method = form.id ? 'PUT' : 'POST';

        await apiFetch(path, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        message.value = form.id ? 'Asset actualizado.' : 'Asset creado.';
        await loadAssets();
        resetForm();
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
}

function onFileChange(ev) {
    versionForm.file = ev.target.files?.[0] || null;
}

async function submitVersion() {
    actionLoading.value = true;
    error.value = '';
    message.value = '';
    try {
        if (!versionForm.assetId) throw new Error('Selecciona un asset.');
        let manifest;
        try { manifest = JSON.parse(versionForm.manifest); } catch { throw new Error('Manifest JSON invalido.'); }

        const fd = new FormData();
        fd.append('version', versionForm.version.trim());
        fd.append('status', versionForm.status);
        if (versionForm.minAppVersion.trim()) fd.append('min_app_version', versionForm.minAppVersion.trim());
        if (versionForm.maxAppVersion.trim()) fd.append('max_app_version', versionForm.maxAppVersion.trim());
        versionForm.browsers.forEach((b, i) => fd.append(`browsers[${i}]`, b));
        fd.append('manifest', JSON.stringify(manifest));
        if (versionForm.file) {
            fd.append('file', versionForm.file);
        } else {
            fd.append('file_path', `assets/${versionForm.assetId}/versions/${versionForm.version.trim()}.zip`);
        }

        await apiFetch(`/me/assets/${versionForm.assetId}/versions`, {
            method: 'POST',
            body: fd,
        });

        message.value = 'Version registrada.';
        await loadAssets();
        resetVersionForm();
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
}

async function toggleStatus(a) {
    actionLoading.value = true;
    error.value = '';
    message.value = '';
    try {
        const next = a.status === 'published' ? 'draft' : 'published';
        await apiFetch(`/me/assets/${a.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: next }),
        });
        await loadAssets();
        message.value = `${a.name} → ${statusLabel(next)}.`;
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
}

onMounted(() => {
    authenticate();
});
</script>

<template>
    <div class="admin-app">
        <nav class="admin-topbar">
            <div class="admin-topbar-inner">
                <a href="/" class="admin-logo">
                    <span class="admin-logo-icon">M</span>
                    <span>{{ t('admin.title') }} · Midori Marketplace</span>
                </a>
                <div class="admin-topbar-right">
                    <button class="btn-lang-dark" @click="switchLocale">🌐 {{ localeLabel }}</button>
                    <template v-if="isAuthenticated">
                        <span class="admin-user">{{ user?.email }}</span>
                        <button class="btn-sm btn-outline" @click="logout">{{ t('auth.logout') }}</button>
                    </template>
                </div>
            </div>
        </nav>

        <main class="admin-main">
            <!-- Auth gate -->
            <section v-if="!isAuthenticated" class="auth-gate">
                <div class="gate-card">
                    <div class="gate-icon">🔒</div>
                    <h1>{{ t('admin.title') }}</h1>
                    <p v-if="loading">{{ t('common.loading') }}</p>
                    <p v-else-if="error" class="alert-error">{{ error }}</p>
                    <p v-else>Inicia sesion con Authentik para cargar el panel administrativo.</p>
                    <button v-if="!loading" type="button" class="btn-primary gate-login" @click="startLogin">
                        Iniciar sesion
                    </button>
                </div>
            </section>

            <!-- Dashboard -->
            <template v-else>
                <p v-if="error" class="alert-error">{{ error }}</p>
                <p v-if="message" class="alert-success">{{ message }}</p>

                <section class="metrics-row">
                    <article v-for="(val, key) in counters" :key="key" class="metric-card">
                        <span class="metric-label">{{ key === 'total' ? t('admin.totalAssets') : key === 'published' ? t('admin.published') : key === 'draft' ? t('admin.drafts') : t('admin.uploadVersion') }}</span>
                        <strong>{{ val }}</strong>
                    </article>
                </section>

                <div class="admin-columns">
                    <!-- Asset form -->
                    <form class="admin-panel" @submit.prevent="saveAsset">
                        <h2>{{ form.id ? t('admin.editAsset') : t('admin.createAsset') }}</h2>
                        <div class="form-row">
                            <label><span>Tipo</span>
                                <select v-model="form.type"><option v-for="t in assetTypes" :key="t" :value="t">{{ t }}</option></select>
                            </label>
                            <label><span>Estado</span>
                                <select v-model="form.status"><option value="draft">draft</option><option value="published">published</option></select>
                            </label>
                        </div>
                        <label><span>Nombre</span><input v-model="form.name" required placeholder="Neon Rain" /></label>
                        <label><span>Slug</span><input v-model="form.slug" required placeholder="theme-neon-rain" /></label>
                        <label><span>Descripcion</span><textarea v-model="form.description" rows="3" placeholder="Describe el asset..."></textarea></label>
                        <div class="form-row">
                            <label><span>Autor</span><input v-model="form.author" placeholder="Midori Labs" /></label>
                            <label><span>Licencia</span><input v-model="form.license" placeholder="MIT" /></label>
                        </div>
                        <label><span>Tags</span><input v-model="form.tags" placeholder="dark, futuristic" /></label>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" :disabled="actionLoading">{{ t('common.save') }}</button>
                            <button type="button" class="btn-outline" @click="resetForm">{{ t('common.delete') }}</button>
                        </div>
                    </form>

                    <!-- Version form -->
                    <form class="admin-panel" @submit.prevent="submitVersion">
                        <h2>{{ t('admin.uploadVersion') }}</h2>
                        <label><span>Asset</span>
                            <select v-model="versionForm.assetId">
                                <option value="">Selecciona...</option>
                                <option v-for="a in assets" :key="a.id" :value="String(a.id)">{{ a.name }} ({{ a.slug }})</option>
                            </select>
                        </label>
                        <div class="form-row">
                            <label><span>Version</span><input v-model="versionForm.version" required placeholder="1.0.0" /></label>
                            <label><span>Estado</span>
                                <select v-model="versionForm.status"><option value="draft">draft</option><option value="published">published</option></select>
                            </label>
                        </div>
                        <div class="form-row">
                            <label><span>App min</span><input v-model="versionForm.minAppVersion" placeholder="1.0.0" /></label>
                            <label><span>App max</span><input v-model="versionForm.maxAppVersion" placeholder="2.0.0" /></label>
                        </div>
                        <label><span>Manifest JSON</span><textarea v-model="versionForm.manifest" rows="5"></textarea></label>
                        <label><span>Paquete</span><input type="file" accept=".zip,.json,.tar,.gz" @change="onFileChange" /></label>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" :disabled="actionLoading">{{ t('admin.uploadVersion') }}</button>
                            <button type="button" class="btn-outline" @click="resetVersionForm">{{ t('common.cancel') }}</button>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <section class="admin-panel table-section">
                    <h2>{{ t('admin.assets') }}</h2>
                    <div class="table-scroll" v-if="assets.length">
                        <table>
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>{{ t('admin.assets') }}</th>
                                    <th>Status</th>
                                    <th>Versions</th>
                                    <th>Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in assets" :key="a.id">
                                    <td><strong>{{ a.name }}</strong><small>{{ a.slug }}</small></td>
                                    <td>{{ a.type }}</td>
                                    <td><span :class="['status-badge', a.status]">{{ statusLabel(a.status) }}</span></td>
                                    <td>{{ a.versions.length }}</td>
                                    <td>{{ formatDate(a.updated_at) }}</td>
                                    <td class="actions-cell">
                                        <button class="btn-link" @click="editAsset(a)">{{ t('common.edit') }}</button>
                                        <button class="btn-link" @click="toggleStatus(a)">{{ a.status === 'published' ? t('admin.drafts') : t('admin.published') }}</button>
                                        <button class="btn-link" @click="versionForm.assetId = String(a.id)">{{ t('admin.uploadVersion') }}</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="empty">{{ t('common.noResults') }}</p>
                </section>
            </template>
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:global(body) {
    margin: 0;
    min-height: 100vh;
    background: #f1f5f3;
    color: #1a1a2e;
    font-family: 'Inter', system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
}

:global(*) { box-sizing: border-box; }

.admin-app {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Topbar */
.admin-topbar {
    background: #0f172a;
    color: #e2e8f0;
    border-bottom: 1px solid #1e293b;
}

.admin-topbar-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.25rem;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.admin-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: inherit;
    font-weight: 600;
    font-size: 0.9rem;
}

.admin-logo-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: linear-gradient(135deg, #16a34a, #0d9488);
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 0.85rem;
}

.admin-topbar-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-user {
    font-size: 0.82rem;
    color: #94a3b8;
}

.btn-lang-dark {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid #334155;
    background: transparent;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-lang-dark:hover {
    border-color: #16a34a;
    color: #4ade80;
}

/* Auth gate */
.auth-gate {
    flex: 1;
    display: grid;
    place-items: center;
    padding: 2rem 1rem;
}

.gate-card {
    width: min(420px, 100%);
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
}

.gate-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.gate-card h1 {
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
}

.gate-card > p {
    color: #64748b;
    font-size: 0.88rem;
    margin: 0 0 1.25rem;
}

/* Forms */
.admin-main {
    flex: 1;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 1.25rem;
}

label {
    display: grid;
    gap: 0.25rem;
    font-size: 0.82rem;
    color: #475569;
}

label span {
    font-weight: 600;
}

input, select, textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.5rem 0.6rem;
    font: inherit;
    font-size: 0.85rem;
    background: #fff;
    color: #1a1a2e;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22,163,74,0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

/* Buttons */
.btn-primary {
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    background: #16a34a;
    color: #fff;
    cursor: pointer;
}

.btn-primary:hover { opacity: 0.92; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.gate-login {
    min-width: 180px;
}

.btn-outline, .btn-sm {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 500;
    background: transparent;
    color: #475569;
    cursor: pointer;
}

.admin-topbar .btn-outline {
    border-color: #334155;
    color: #94a3b8;
}

.btn-link {
    border: none;
    background: transparent;
    color: #0f766e;
    font: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    padding: 0;
}

.btn-link:hover { text-decoration: underline; }

/* Layout */
.metrics-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.metric-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.8rem;
}

.metric-label {
    font-size: 0.75rem;
    color: #64748b;
}

.metric-card strong {
    display: block;
    font-size: 1.5rem;
    margin-top: 0.15rem;
}

.admin-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.admin-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    display: grid;
    gap: 0.6rem;
}

.admin-panel h2 {
    margin: 0;
    font-size: 1rem;
}

/* Table */
.table-section {
    margin-bottom: 2rem;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    text-align: left;
    padding: 0.55rem 0.4rem;
    border-bottom: 1px solid #e8ece9;
    font-size: 0.82rem;
    vertical-align: top;
}

th {
    font-weight: 600;
    color: #64748b;
}

td small {
    display: block;
    color: #94a3b8;
    font-size: 0.75rem;
}

.actions-cell {
    display: flex;
    gap: 0.6rem;
}

.status-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 5px;
}

.status-badge.published {
    background: #dcfce7;
    color: #14532d;
}

.status-badge.draft {
    background: #f1f5f9;
    color: #475569;
}

.empty {
    color: #94a3b8;
    font-size: 0.88rem;
}

/* Alerts */
.alert-error {
    padding: 0.5rem 0.75rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 8px;
    font-size: 0.85rem;
}

.alert-success {
    padding: 0.5rem 0.75rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #14532d;
    border-radius: 8px;
    font-size: 0.85rem;
}

@media (max-width: 900px) {
    .admin-columns { grid-template-columns: 1fr; }
    .metrics-row { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .metrics-row { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .actions-cell { flex-direction: column; gap: 0.2rem; }
}
</style>
