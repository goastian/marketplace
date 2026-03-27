<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';

const SURFACE_IDS = {
    STOREFRONT: 'storefront',
    ADMIN: 'admin',
};

const STORAGE_KEY = 'midori_marketplace_admin_token';
const API_BASE = '/api/v1';
const surfaces = [
    { id: SURFACE_IDS.STOREFRONT, label: 'Storefront' },
    { id: SURFACE_IDS.ADMIN, label: 'Panel admin' },
];

const typeOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'theme', label: 'Themes' },
    { value: 'wallpaper', label: 'Fondos' },
    { value: 'widget', label: 'Widgets' },
];

const browserOptions = [
    { value: 'all', label: 'Todos los navegadores' },
    { value: 'chrome', label: 'Chrome' },
    { value: 'firefox', label: 'Firefox' },
    { value: 'midori', label: 'Midori' },
];

const statusOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'draft', label: 'Borrador' },
    { value: 'published', label: 'Publicado' },
];

const activeSurface = ref(SURFACE_IDS.STOREFRONT);
const storefrontLoading = ref(false);
const adminLoading = ref(false);
const previewLoading = ref(false);
const actionLoading = ref(false);
const storefrontError = ref('');
const adminError = ref('');
const adminMessage = ref('');

const search = ref('');
const selectedType = ref('all');
const selectedBrowser = ref('all');
const selectedStatus = ref('all');

const storefrontAssets = ref([]);
const storefrontMeta = reactive({
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 20,
});

const previewItem = ref(null);
const previewDetail = ref(null);

const adminToken = ref(localStorage.getItem(STORAGE_KEY) || '');
const adminUser = ref(null);
const managedAssets = ref([]);
const selectedAssetForVersion = ref('');

const formAsset = reactive({
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

const formVersion = reactive({
    assetId: '',
    version: '',
    status: 'draft',
    minAppVersion: '',
    maxAppVersion: '',
    browsers: ['chrome', 'firefox', 'midori'],
    manifest: '{\n  "schemaVersion": "1.0.0",\n  "kind": "midori-asset"\n}',
    file: null,
});

const assetTypes = ['theme', 'wallpaper', 'widget', 'animation', 'collection', 'midori-update'];

const hasAdminToken = computed(() => adminToken.value.trim().length > 0);

const publishedStorefrontAssets = computed(() => {
    return storefrontAssets.value.filter((asset) => {
        const matchesStatus = selectedStatus.value === 'all' || asset.status === selectedStatus.value;
        const matchesBrowser =
            selectedBrowser.value === 'all' ||
            asset.compatibilityBrowsers.includes(selectedBrowser.value);

        return matchesStatus && matchesBrowser;
    });
});

const curatedAssets = computed(() => {
    return publishedStorefrontAssets.value.slice(0, 3);
});

const counters = computed(() => {
    const all = managedAssets.value;
    return {
        total: all.length,
        published: all.filter((item) => item.status === 'published').length,
        draft: all.filter((item) => item.status === 'draft').length,
        versions: all.reduce((sum, item) => sum + item.versions.length, 0),
    };
});

function inferSurfaceFromUrl() {
    const mountNode = document.getElementById('marketplace-app');
    const initialSurface = mountNode?.dataset?.initialSurface;
    if (initialSurface === SURFACE_IDS.ADMIN) {
        activeSurface.value = SURFACE_IDS.ADMIN;
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const target = params.get('surface');
    if (target === SURFACE_IDS.ADMIN) {
        activeSurface.value = SURFACE_IDS.ADMIN;
    }
}

function updateSurfaceInUrl(surface) {
    const params = new URLSearchParams(window.location.search);
    if (surface === SURFACE_IDS.ADMIN) {
        params.set('surface', SURFACE_IDS.ADMIN);
    } else {
        params.delete('surface');
    }

    const query = params.toString();
    const url = `${window.location.pathname}${query ? `?${query}` : ''}`;
    window.history.replaceState({}, '', url);
}

function setSurface(surface) {
    activeSurface.value = surface;
    updateSurfaceInUrl(surface);
}

function parseAsset(raw) {
    const latestVersion = raw.latest_version || null;
    const compatibilityBrowsers = Array.isArray(latestVersion?.browsers) ? latestVersion.browsers : [];
    const tags = Array.isArray(raw.tags) ? raw.tags : [];

    return {
        id: raw.id,
        slug: raw.slug,
        name: raw.name,
        type: raw.type,
        description: raw.description || 'Sin descripcion disponible.',
        author: raw.author || 'Comunidad Midori',
        license: raw.license || 'No especificada',
        status: raw.status || 'draft',
        tags,
        compatibilityBrowsers,
        latestVersion,
        installs: 0,
        rating: 4.7,
        gradient: gradientForType(raw.type),
        cta: raw.type === 'widget' ? 'Instalar widget' : 'Instalar ahora',
    };
}

function parseManagedAsset(raw) {
    return {
        id: raw.id,
        slug: raw.slug,
        name: raw.name,
        type: raw.type,
        status: raw.status,
        description: raw.description || '',
        author: raw.author || '',
        license: raw.license || '',
        tags: Array.isArray(raw.tags) ? raw.tags : [],
        versions: Array.isArray(raw.versions) ? raw.versions : [],
        updatedAt: raw.updated_at,
    };
}

function gradientForType(type) {
    const map = {
        theme: 'linear-gradient(135deg, #1f4ed6 0%, #0ca678 100%)',
        wallpaper: 'linear-gradient(140deg, #2f4f4f 0%, #65a30d 100%)',
        widget: 'linear-gradient(125deg, #0f766e 0%, #06b6d4 100%)',
        animation: 'linear-gradient(130deg, #0369a1 0%, #16a34a 100%)',
        collection: 'linear-gradient(130deg, #2563eb 0%, #22c55e 100%)',
        'midori-update': 'linear-gradient(130deg, #0ea5e9 0%, #84cc16 100%)',
    };

    return map[type] || 'linear-gradient(130deg, #14532d 0%, #0ea5e9 100%)';
}

function statusLabel(status) {
    const labels = {
        draft: 'Borrador',
        published: 'Publicado',
    };

    return labels[status] || status;
}

function typeLabel(type) {
    const labels = {
        theme: 'Theme',
        wallpaper: 'Fondo',
        widget: 'Widget',
        animation: 'Animacion',
        collection: 'Coleccion',
        'midori-update': 'Update',
    };

    return labels[type] || type;
}

function formatNumber(number) {
    return new Intl.NumberFormat('es-ES').format(number || 0);
}

function formatDate(isoDate) {
    if (!isoDate) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(isoDate));
}

async function apiRequest(path, options = {}) {
    const response = await fetch(`${API_BASE}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.headers || {}),
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(payload?.message || 'No se pudo completar la solicitud.');
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

function authHeaders() {
    return {
        Authorization: `Bearer ${adminToken.value.trim()}`,
    };
}

async function loadStorefront() {
    storefrontLoading.value = true;
    storefrontError.value = '';

    try {
        const params = new URLSearchParams();
        if (search.value.trim()) {
            params.set('q', search.value.trim());
        }
        if (selectedType.value !== 'all') {
            params.set('type', selectedType.value);
        }
        params.set('per_page', '24');

        const query = params.toString();
        const payload = await apiRequest(`/catalog${query ? `?${query}` : ''}`);

        storefrontAssets.value = (payload.data || []).map(parseAsset);
        storefrontMeta.total = payload.meta?.total || 0;
        storefrontMeta.current_page = payload.meta?.current_page || 1;
        storefrontMeta.last_page = payload.meta?.last_page || 1;
        storefrontMeta.per_page = payload.meta?.per_page || 20;
    } catch (error) {
        storefrontError.value = error.message;
    } finally {
        storefrontLoading.value = false;
    }
}

async function loadPreview(slug) {
    previewLoading.value = true;
    previewDetail.value = null;

    try {
        const payload = await apiRequest(`/assets/${slug}`);
        previewDetail.value = payload.data || null;
    } catch (error) {
        previewDetail.value = {
            description: 'No se pudo cargar el detalle extendido del asset.',
            versions: [],
            tags: [],
        };
    } finally {
        previewLoading.value = false;
    }
}

function openPreview(item) {
    previewItem.value = item;
    loadPreview(item.slug);
}

function closePreview() {
    previewItem.value = null;
    previewDetail.value = null;
}

function installAsset(item) {
    const path = `${API_BASE}/assets/${item.slug}/download`;
    window.open(path, '_blank', 'noopener,noreferrer');
}

function sanitizeSlug(value) {
    return value
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-{2,}/g, '-');
}

function resetAssetForm() {
    formAsset.id = null;
    formAsset.type = 'theme';
    formAsset.slug = '';
    formAsset.name = '';
    formAsset.description = '';
    formAsset.author = '';
    formAsset.license = '';
    formAsset.tags = '';
    formAsset.status = 'draft';
}

function editAsset(asset) {
    formAsset.id = asset.id;
    formAsset.type = asset.type;
    formAsset.slug = asset.slug;
    formAsset.name = asset.name;
    formAsset.description = asset.description;
    formAsset.author = asset.author;
    formAsset.license = asset.license;
    formAsset.tags = asset.tags.join(', ');
    formAsset.status = asset.status;
    selectedAssetForVersion.value = String(asset.id);
}

function resetVersionForm() {
    formVersion.assetId = '';
    formVersion.version = '';
    formVersion.status = 'draft';
    formVersion.minAppVersion = '';
    formVersion.maxAppVersion = '';
    formVersion.browsers = ['chrome', 'firefox', 'midori'];
    formVersion.manifest = '{\n  "schemaVersion": "1.0.0",\n  "kind": "midori-asset"\n}';
    formVersion.file = null;
}

async function adminAuthProbe() {
    if (!hasAdminToken.value) {
        adminUser.value = null;
        managedAssets.value = [];
        return;
    }

    adminLoading.value = true;
    adminError.value = '';

    try {
        const me = await apiRequest('/me', {
            headers: authHeaders(),
        });
        adminUser.value = me.data;
        await loadManagedAssets();
    } catch (error) {
        adminUser.value = null;
        managedAssets.value = [];
        adminError.value = 'Token invalido o expirado. Inicia sesion nuevamente con Authentik.';
    } finally {
        adminLoading.value = false;
    }
}

async function loadManagedAssets() {
    const payload = await apiRequest('/me/assets', {
        headers: authHeaders(),
    });

    managedAssets.value = (payload.data || []).map(parseManagedAsset);
}

async function saveAsset() {
    actionLoading.value = true;
    adminError.value = '';
    adminMessage.value = '';

    try {
        const slug = formAsset.slug.trim() ? sanitizeSlug(formAsset.slug) : sanitizeSlug(formAsset.name);
        const body = {
            type: formAsset.type,
            slug,
            name: formAsset.name.trim(),
            description: formAsset.description.trim() || null,
            author: formAsset.author.trim() || null,
            license: formAsset.license.trim() || null,
            tags: formAsset.tags
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean),
            status: formAsset.status,
        };

        if (!body.name || !body.slug) {
            throw new Error('Nombre y slug son obligatorios.');
        }

        const path = formAsset.id ? `/me/assets/${formAsset.id}` : '/me/assets';
        const method = formAsset.id ? 'PUT' : 'POST';

        await apiRequest(path, {
            method,
            headers: {
                ...authHeaders(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        });

        adminMessage.value = formAsset.id ? 'Asset actualizado correctamente.' : 'Asset creado correctamente.';

        await loadManagedAssets();
        resetAssetForm();
    } catch (error) {
        adminError.value = error.message;
    } finally {
        actionLoading.value = false;
    }
}

function onVersionFileChange(event) {
    const [file] = event.target.files || [];
    formVersion.file = file || null;
}

async function publishVersion() {
    actionLoading.value = true;
    adminError.value = '';
    adminMessage.value = '';

    try {
        const assetId = formVersion.assetId || selectedAssetForVersion.value;

        if (!assetId) {
            throw new Error('Selecciona un asset para versionar.');
        }

        let manifestParsed = {};
        try {
            manifestParsed = JSON.parse(formVersion.manifest);
        } catch {
            throw new Error('El manifest debe ser JSON valido.');
        }

        const formData = new FormData();
        formData.append('version', formVersion.version.trim());
        formData.append('status', formVersion.status);
        if (formVersion.minAppVersion.trim()) {
            formData.append('min_app_version', formVersion.minAppVersion.trim());
        }
        if (formVersion.maxAppVersion.trim()) {
            formData.append('max_app_version', formVersion.maxAppVersion.trim());
        }

        formVersion.browsers.forEach((browser, index) => {
            formData.append(`browsers[${index}]`, browser);
        });

        formData.append('manifest', JSON.stringify(manifestParsed));

        if (formVersion.file) {
            formData.append('file', formVersion.file);
        } else {
            formData.append('file_path', `assets/${assetId}/versions/${formVersion.version.trim()}.zip`);
        }

        await apiRequest(`/me/assets/${assetId}/versions`, {
            method: 'POST',
            headers: authHeaders(),
            body: formData,
        });

        adminMessage.value = 'Version subida y registrada correctamente.';
        await loadManagedAssets();
        resetVersionForm();
    } catch (error) {
        adminError.value = error.message;
    } finally {
        actionLoading.value = false;
    }
}

async function quickToggleStatus(asset) {
    actionLoading.value = true;
    adminError.value = '';
    adminMessage.value = '';

    try {
        const nextStatus = asset.status === 'published' ? 'draft' : 'published';

        await apiRequest(`/me/assets/${asset.id}`, {
            method: 'PUT',
            headers: {
                ...authHeaders(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ status: nextStatus }),
        });

        await loadManagedAssets();
        adminMessage.value =
            nextStatus === 'published'
                ? `Asset ${asset.name} publicado.`
                : `Asset ${asset.name} enviado a borrador.`;
    } catch (error) {
        adminError.value = error.message;
    } finally {
        actionLoading.value = false;
    }
}

function logoutAdmin() {
    adminToken.value = '';
    localStorage.removeItem(STORAGE_KEY);
    adminUser.value = null;
    managedAssets.value = [];
    adminMessage.value = 'Sesion cerrada.';
    adminError.value = '';
}

function saveAdminToken() {
    adminError.value = '';
    adminMessage.value = '';

    if (!adminToken.value.trim()) {
        adminError.value = 'Ingresa un Bearer token valido.';
        return;
    }

    adminToken.value = adminToken.value.trim();
    localStorage.setItem(STORAGE_KEY, adminToken.value);
    adminAuthProbe();
}

watch([search, selectedType], () => {
    loadStorefront();
});

watch(activeSurface, (surface) => {
    if (surface === SURFACE_IDS.ADMIN && hasAdminToken.value && !adminUser.value) {
        adminAuthProbe();
    }
});

watch(
    () => formAsset.name,
    (name) => {
        if (!formAsset.id && !formAsset.slug.trim()) {
            formAsset.slug = sanitizeSlug(name);
        }
    },
);

onMounted(() => {
    inferSurfaceFromUrl();
    loadStorefront();

    if (activeSurface.value === SURFACE_IDS.ADMIN && hasAdminToken.value) {
        adminAuthProbe();
    }
});
</script>

<template>
    <div class="marketplace-shell">
        <div class="bg-grid"></div>

        <header class="hero">
            <div class="hero-topline">
                <span>Midori Marketplace</span>
                <p>Storefront publica + consola de publicacion profesional para creators.</p>
            </div>
            <h1>Explora, instala y publica assets con una experiencia clara, rapida y elegante</h1>
            <p>
                Inspirado en patrones de marketplaces de extensiones modernos, adaptado al lenguaje visual Midori con
                Flat Design, contraste limpio y jerarquia fuerte para catalogo y operaciones.
            </p>

            <nav class="surface-switch" aria-label="Seleccion de superficie">
                <button
                    v-for="surface in surfaces"
                    :key="surface.id"
                    type="button"
                    :class="['surface-pill', { active: activeSurface === surface.id }]"
                    @click="setSurface(surface.id)"
                >
                    {{ surface.label }}
                </button>
            </nav>
        </header>

        <main>
            <section v-if="activeSurface === SURFACE_IDS.STOREFRONT" class="storefront-surface">
                <div class="storefront-top">
                    <div class="stats-block">
                        <article>
                            <p>Assets publicados</p>
                            <strong>{{ formatNumber(storefrontMeta.total) }}</strong>
                        </article>
                        <article>
                            <p>Tipos activos</p>
                            <strong>3</strong>
                        </article>
                        <article>
                            <p>Pagina actual</p>
                            <strong>{{ storefrontMeta.current_page }} / {{ storefrontMeta.last_page }}</strong>
                        </article>
                    </div>

                    <div class="filters-block">
                        <label>
                            Buscar
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Buscar themes, fondos y widgets..."
                            />
                        </label>

                        <label>
                            Tipo
                            <select v-model="selectedType">
                                <option v-for="item in typeOptions" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </select>
                        </label>

                        <label>
                            Compatibilidad
                            <select v-model="selectedBrowser">
                                <option v-for="item in browserOptions" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </select>
                        </label>

                        <label>
                            Estado
                            <select v-model="selectedStatus">
                                <option v-for="item in statusOptions" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>

                <section class="curated" v-if="curatedAssets.length">
                    <header>
                        <h2>Selecciones destacadas</h2>
                        <p>Contenido de alto impacto visual para abrir Midori con identidad.</p>
                    </header>
                    <div class="curated-grid">
                        <article
                            v-for="item in curatedAssets"
                            :key="`curated-${item.id}`"
                            class="curated-card"
                            :style="{ background: item.gradient }"
                        >
                            <div>
                                <span class="kind">{{ typeLabel(item.type) }}</span>
                                <h3>{{ item.name }}</h3>
                                <p>{{ item.description }}</p>
                            </div>
                            <button type="button" class="btn-light" @click="openPreview(item)">Ver preview</button>
                        </article>
                    </div>
                </section>

                <section class="catalog">
                    <header>
                        <h2>Catalogo</h2>
                        <p v-if="storefrontLoading">Cargando assets publicados...</p>
                        <p v-else>{{ publishedStorefrontAssets.length }} resultados visibles</p>
                    </header>

                    <p v-if="storefrontError" class="alert error">{{ storefrontError }}</p>

                    <div v-if="!storefrontLoading" class="catalog-grid">
                        <article v-for="item in publishedStorefrontAssets" :key="item.id" class="asset-card">
                            <div class="asset-preview" :style="{ background: item.gradient }">
                                <span>{{ typeLabel(item.type) }}</span>
                                <button type="button" class="btn-ghost" @click="openPreview(item)">Preview</button>
                            </div>
                            <div class="asset-body">
                                <h3>{{ item.name }}</h3>
                                <p>{{ item.description }}</p>

                                <div class="chip-row">
                                    <span class="chip">{{ statusLabel(item.status) }}</span>
                                    <span class="chip" v-for="tag in item.tags.slice(0, 3)" :key="`${item.id}-${tag}`">
                                        {{ tag }}
                                    </span>
                                </div>

                                <div class="meta-row">
                                    <span>{{ item.author }}</span>
                                    <strong>v{{ item.latestVersion?.version || '0.0.0' }}</strong>
                                </div>

                                <div class="meta-row browsers">
                                    <span
                                        v-for="browser in item.compatibilityBrowsers"
                                        :key="`${item.id}-${browser}`"
                                        class="browser-chip"
                                    >
                                        {{ browser }}
                                    </span>
                                </div>

                                <div class="card-actions">
                                    <button type="button" class="btn-primary" @click="installAsset(item)">
                                        {{ item.cta }}
                                    </button>
                                    <button type="button" class="btn-secondary" @click="openPreview(item)">
                                        Detalle
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </section>

            <section v-else class="admin-surface">
                <header class="admin-header">
                    <div>
                        <h2>Panel de publicacion</h2>
                        <p>Gestiona assets, versiones y estado de publicacion con token seguro Authentik.</p>
                    </div>
                    <button v-if="adminUser" type="button" class="btn-secondary" @click="logoutAdmin">
                        Cerrar sesion
                    </button>
                </header>

                <div v-if="!adminUser" class="auth-gate">
                    <h3>Acceso restringido</h3>
                    <p>
                        El admin no se renderiza sin autenticacion. Ingresa un JWT valido emitido por Authentik para
                        habilitar CRUD, upload, versionado y publicacion.
                    </p>
                    <label>
                        Bearer token
                        <textarea
                            v-model="adminToken"
                            rows="4"
                            placeholder="eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
                        ></textarea>
                    </label>
                    <div class="gate-actions">
                        <button type="button" class="btn-primary" :disabled="adminLoading" @click="saveAdminToken">
                            {{ adminLoading ? 'Validando...' : 'Ingresar al admin' }}
                        </button>
                    </div>
                </div>

                <template v-else>
                    <section class="admin-user">
                        <p>
                            Sesion activa como <strong>{{ adminUser?.email }}</strong>
                        </p>
                        <span v-if="adminUser?.name">{{ adminUser.name }}</span>
                    </section>

                    <section class="metrics">
                        <article>
                            <p>Total assets</p>
                            <strong>{{ counters.total }}</strong>
                        </article>
                        <article>
                            <p>Publicados</p>
                            <strong>{{ counters.published }}</strong>
                        </article>
                        <article>
                            <p>Borradores</p>
                            <strong>{{ counters.draft }}</strong>
                        </article>
                        <article>
                            <p>Versiones</p>
                            <strong>{{ counters.versions }}</strong>
                        </article>
                    </section>

                    <p v-if="adminError" class="alert error">{{ adminError }}</p>
                    <p v-if="adminMessage" class="alert success">{{ adminMessage }}</p>

                    <div class="admin-grid">
                        <form class="admin-card" @submit.prevent="saveAsset">
                            <header>
                                <h3>{{ formAsset.id ? 'Editar asset' : 'Nuevo asset' }}</h3>
                                <p>CRUD de metadata principal.</p>
                            </header>

                            <div class="grid-2">
                                <label>
                                    Tipo
                                    <select v-model="formAsset.type">
                                        <option v-for="type in assetTypes" :key="type" :value="type">{{ type }}</option>
                                    </select>
                                </label>
                                <label>
                                    Estado
                                    <select v-model="formAsset.status">
                                        <option value="draft">draft</option>
                                        <option value="published">published</option>
                                    </select>
                                </label>
                            </div>

                            <label>
                                Nombre
                                <input v-model="formAsset.name" type="text" placeholder="Neon Rain" required />
                            </label>

                            <label>
                                Slug
                                <input v-model="formAsset.slug" type="text" placeholder="theme-neon-rain" required />
                            </label>

                            <label>
                                Descripcion
                                <textarea
                                    v-model="formAsset.description"
                                    rows="3"
                                    placeholder="Describe el asset para storefront"
                                ></textarea>
                            </label>

                            <div class="grid-2">
                                <label>
                                    Autor
                                    <input v-model="formAsset.author" type="text" placeholder="Midori Labs" />
                                </label>
                                <label>
                                    Licencia
                                    <input v-model="formAsset.license" type="text" placeholder="MIT" />
                                </label>
                            </div>

                            <label>
                                Tags (separadas por coma)
                                <input v-model="formAsset.tags" type="text" placeholder="dark, futuristic, green" />
                            </label>

                            <div class="card-actions">
                                <button type="submit" class="btn-primary" :disabled="actionLoading">
                                    {{ actionLoading ? 'Guardando...' : 'Guardar asset' }}
                                </button>
                                <button type="button" class="btn-secondary" @click="resetAssetForm">Limpiar</button>
                            </div>
                        </form>

                        <form class="admin-card" @submit.prevent="publishVersion">
                            <header>
                                <h3>Versionado y upload</h3>
                                <p>Sube paquetes y publica versiones por asset.</p>
                            </header>

                            <label>
                                Asset objetivo
                                <select v-model="formVersion.assetId">
                                    <option value="">Selecciona un asset</option>
                                    <option v-for="item in managedAssets" :key="item.id" :value="String(item.id)">
                                        {{ item.name }} ({{ item.slug }})
                                    </option>
                                </select>
                            </label>

                            <div class="grid-2">
                                <label>
                                    Version
                                    <input v-model="formVersion.version" type="text" placeholder="1.0.0" required />
                                </label>
                                <label>
                                    Estado version
                                    <select v-model="formVersion.status">
                                        <option value="draft">draft</option>
                                        <option value="published">published</option>
                                    </select>
                                </label>
                            </div>

                            <div class="grid-2">
                                <label>
                                    App min
                                    <input v-model="formVersion.minAppVersion" type="text" placeholder="1.0.0" />
                                </label>
                                <label>
                                    App max
                                    <input v-model="formVersion.maxAppVersion" type="text" placeholder="2.0.0" />
                                </label>
                            </div>

                            <label>
                                Manifest JSON
                                <textarea v-model="formVersion.manifest" rows="6"></textarea>
                            </label>

                            <label>
                                Paquete del asset
                                <input type="file" accept=".zip,.json,.tar,.gz" @change="onVersionFileChange" />
                            </label>

                            <div class="card-actions">
                                <button type="submit" class="btn-primary" :disabled="actionLoading">
                                    {{ actionLoading ? 'Publicando...' : 'Subir version' }}
                                </button>
                                <button type="button" class="btn-secondary" @click="resetVersionForm">Limpiar</button>
                            </div>
                        </form>
                    </div>

                    <section class="admin-card table-card">
                        <header>
                            <h3>Assets gestionados</h3>
                            <p>{{ managedAssets.length }} elementos cargados</p>
                        </header>

                        <div class="table-wrap" v-if="managedAssets.length">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Versiones</th>
                                        <th>Ultima actualizacion</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in managedAssets" :key="item.id">
                                        <td>
                                            <strong>{{ item.name }}</strong>
                                            <small>{{ item.slug }}</small>
                                        </td>
                                        <td>{{ item.type }}</td>
                                        <td>
                                            <span :class="['chip', item.status === 'published' ? 'success' : 'muted']">
                                                {{ statusLabel(item.status) }}
                                            </span>
                                        </td>
                                        <td>{{ item.versions.length }}</td>
                                        <td>{{ formatDate(item.updatedAt) }}</td>
                                        <td class="td-actions">
                                            <button type="button" class="btn-link" @click="editAsset(item)">
                                                Editar
                                            </button>
                                            <button type="button" class="btn-link" @click="quickToggleStatus(item)">
                                                {{ item.status === 'published' ? 'Despublicar' : 'Publicar' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="btn-link"
                                                @click="selectedAssetForVersion = String(item.id)"
                                            >
                                                Versionar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="empty-state">No hay assets gestionados para este usuario.</p>
                    </section>
                </template>
            </section>
        </main>

        <div v-if="previewItem" class="preview-modal" @click.self="closePreview">
            <article>
                <div class="preview-banner" :style="{ background: previewItem.gradient }"></div>
                <h3>{{ previewItem.name }}</h3>
                <p>{{ previewItem.description }}</p>

                <div class="chip-row">
                    <span class="chip">{{ typeLabel(previewItem.type) }}</span>
                    <span class="chip">v{{ previewItem.latestVersion?.version || '0.0.0' }}</span>
                    <span class="chip" v-for="browser in previewItem.compatibilityBrowsers" :key="browser">
                        {{ browser }}
                    </span>
                </div>

                <section class="preview-detail">
                    <h4>Detalle</h4>
                    <p v-if="previewLoading">Cargando detalle...</p>
                    <template v-else>
                        <p>{{ previewDetail?.description || previewItem.description }}</p>
                        <p>
                            Licencia: <strong>{{ previewDetail?.license || previewItem.license }}</strong>
                        </p>
                        <p>
                            Tags:
                            <span>
                                {{
                                    Array.isArray(previewDetail?.tags) && previewDetail.tags.length
                                        ? previewDetail.tags.join(', ')
                                        : 'Sin tags'
                                }}
                            </span>
                        </p>
                        <p>Versiones publicadas: {{ previewDetail?.versions?.length || 0 }}</p>
                    </template>
                </section>

                <div class="card-actions">
                    <button type="button" class="btn-primary" @click="installAsset(previewItem)">Instalar</button>
                    <button type="button" class="btn-secondary" @click="closePreview">Cerrar</button>
                </div>
            </article>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,500;8..60,700&display=swap');

:global(body) {
    margin: 0;
    min-height: 100vh;
    background: #edf6f1;
    color: #0f172a;
    font-family: 'Manrope', sans-serif;
}

* {
    box-sizing: border-box;
}

.marketplace-shell {
    --midori-900: #0e2d1f;
    --midori-700: #14532d;
    --midori-600: #15803d;
    --midori-500: #16a34a;
    --midori-300: #86efac;
    --cyan-500: #0891b2;
    --slate-900: #0f172a;
    --slate-700: #334155;
    --slate-500: #64748b;
    --surface: #ffffff;
    --surface-soft: #f8fafc;
    --line: #dbe6df;
    --danger: #dc2626;
    --success: #16a34a;

    max-width: 1320px;
    margin: 0 auto;
    padding: 1.5rem 1rem 3rem;
    position: relative;
}

.bg-grid {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(to right, rgba(15, 23, 42, 0.04) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(15, 23, 42, 0.04) 1px, transparent 1px);
    background-size: 26px 26px;
    pointer-events: none;
    z-index: -1;
}

.hero {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfeff 100%);
    border: 1px solid #dbece3;
    border-radius: 1.2rem;
    padding: 1.4rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.hero-topline {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.hero-topline span {
    font-size: 0.75rem;
    letter-spacing: 0.2rem;
    text-transform: uppercase;
    color: var(--midori-700);
    font-weight: 700;
}

.hero-topline p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--slate-700);
}

.hero h1 {
    font-family: 'Source Serif 4', serif;
    margin: 0.8rem 0 0.6rem;
    font-size: clamp(2rem, 3vw, 3rem);
    max-width: 22ch;
    color: var(--slate-900);
}

.hero > p {
    margin: 0;
    max-width: 75ch;
    color: var(--slate-700);
}

.surface-switch {
    margin-top: 1.2rem;
    display: inline-flex;
    border-radius: 999px;
    border: 1px solid #bfdccb;
    background: #f6fff9;
    padding: 0.25rem;
    gap: 0.2rem;
}

.surface-pill {
    border: 0;
    border-radius: 999px;
    padding: 0.6rem 1rem;
    background: transparent;
    color: var(--slate-700);
    font-weight: 700;
    cursor: pointer;
}

.surface-pill.active {
    background: linear-gradient(90deg, #16a34a 0%, #0891b2 100%);
    color: #ffffff;
}

.storefront-surface,
.admin-surface {
    margin-top: 1rem;
}

.storefront-top {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 0.8rem;
}

.stats-block,
.filters-block,
.curated,
.catalog,
.admin-card,
.auth-gate,
.admin-user,
.metrics article {
    border: 1px solid var(--line);
    border-radius: 1rem;
    background: var(--surface);
}

.stats-block {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    padding: 0.6rem;
}

.stats-block article {
    border: 1px solid var(--line);
    border-radius: 0.8rem;
    padding: 0.7rem;
    background: var(--surface-soft);
}

.stats-block p,
.metrics p {
    margin: 0;
    font-size: 0.78rem;
    color: var(--slate-500);
}

.stats-block strong,
.metrics strong {
    font-size: 1.4rem;
    color: var(--slate-900);
}

.filters-block {
    padding: 0.7rem;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}

label {
    display: grid;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: var(--slate-700);
}

input,
select,
textarea {
    width: 100%;
    border: 1px solid #cdd9d2;
    border-radius: 0.7rem;
    background: #ffffff;
    padding: 0.55rem 0.65rem;
    color: var(--slate-900);
    font: inherit;
}

.curated,
.catalog,
.admin-card,
.auth-gate,
.admin-user,
.metrics {
    margin-top: 0.8rem;
    padding: 0.9rem;
}

.curated header,
.catalog header,
.admin-card header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1rem;
}

.curated h2,
.catalog h2,
.admin-card h3,
.admin-header h2 {
    margin: 0;
}

.curated-grid {
    margin-top: 0.7rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.7rem;
}

.curated-card {
    border-radius: 0.9rem;
    min-height: 200px;
    padding: 0.9rem;
    color: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.curated-card .kind {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
}

.curated-card h3 {
    margin: 0.65rem 0 0.4rem;
}

.curated-card p {
    margin: 0;
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.9);
}

.catalog-grid {
    margin-top: 0.7rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.7rem;
}

.asset-card {
    border: 1px solid var(--line);
    border-radius: 0.9rem;
    overflow: hidden;
    background: #ffffff;
}

.asset-preview {
    min-height: 120px;
    padding: 0.65rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.asset-preview span {
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06rem;
    color: #ffffff;
    background: rgba(15, 23, 42, 0.25);
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
}

.asset-body {
    padding: 0.75rem;
}

.asset-body h3 {
    margin: 0;
}

.asset-body p {
    margin: 0.55rem 0;
    color: var(--slate-700);
    font-size: 0.9rem;
}

.chip-row {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.chip {
    font-size: 0.72rem;
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    background: #edf5ef;
    color: var(--slate-700);
}

.meta-row {
    margin-top: 0.55rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78rem;
    color: var(--slate-500);
}

.meta-row.browsers {
    justify-content: flex-start;
    gap: 0.35rem;
}

.browser-chip {
    border-radius: 999px;
    border: 1px solid #cde3da;
    background: #f5fffa;
    color: var(--midori-700);
    padding: 0.15rem 0.5rem;
    font-size: 0.72rem;
}

.card-actions,
.gate-actions,
.admin-header {
    margin-top: 0.65rem;
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
}

button {
    font: inherit;
}

.btn-primary,
.btn-secondary,
.btn-ghost,
.btn-light {
    border: 0;
    border-radius: 0.7rem;
    padding: 0.5rem 0.75rem;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(90deg, #16a34a 0%, #0891b2 100%);
    color: #ffffff;
}

.btn-secondary {
    background: #ecf6ef;
    color: var(--midori-700);
}

.btn-ghost {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.btn-light {
    background: #ffffff;
    color: var(--midori-700);
}

.admin-header {
    margin-top: 0;
}

.auth-gate h3,
.admin-user p {
    margin-top: 0;
}

.admin-user {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.metrics {
    margin-top: 0.8rem;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.6rem;
    padding: 0;
    border: 0;
    background: transparent;
}

.metrics article {
    padding: 0.8rem;
}

.alert {
    margin: 0.75rem 0 0;
    border-radius: 0.7rem;
    padding: 0.6rem 0.7rem;
    font-size: 0.9rem;
}

.alert.error {
    border: 1px solid #fca5a5;
    background: #fff1f1;
    color: #991b1b;
}

.alert.success {
    border: 1px solid #86efac;
    background: #f0fdf4;
    color: #14532d;
}

.admin-grid {
    margin-top: 0.8rem;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.7rem;
}

.grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.table-card {
    margin-top: 0.8rem;
}

.table-wrap {
    overflow: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    text-align: left;
    padding: 0.55rem 0.35rem;
    border-bottom: 1px solid #e6ece7;
    font-size: 0.85rem;
    vertical-align: top;
}

td small {
    display: block;
    color: var(--slate-500);
}

.td-actions {
    display: grid;
    gap: 0.2rem;
}

.btn-link {
    border: 0;
    background: transparent;
    color: #0f766e;
    text-align: left;
    padding: 0;
    cursor: pointer;
}

.chip.success {
    background: #dcfce7;
    color: #14532d;
}

.chip.muted {
    background: #f1f5f9;
    color: #334155;
}

.empty-state {
    color: var(--slate-500);
    margin: 0.5rem 0 0;
}

.preview-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: grid;
    place-items: center;
    padding: 1rem;
}

.preview-modal article {
    width: min(620px, 100%);
    border-radius: 1rem;
    border: 1px solid var(--line);
    background: #ffffff;
    padding: 0.9rem;
}

.preview-banner {
    height: 170px;
    border-radius: 0.8rem;
}

.preview-detail {
    margin-top: 0.7rem;
    border: 1px solid #e6ece7;
    border-radius: 0.8rem;
    padding: 0.7rem;
    background: #f8fafc;
}

.preview-detail h4 {
    margin: 0;
}

@media (max-width: 1080px) {
    .storefront-top,
    .admin-grid {
        grid-template-columns: 1fr;
    }

    .filters-block {
        grid-template-columns: repeat(2, 1fr);
    }

    .curated-grid {
        grid-template-columns: 1fr;
    }

    .metrics {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .marketplace-shell {
        padding: 1rem 0.7rem 2rem;
    }

    .hero {
        padding: 1rem;
    }

    .stats-block {
        grid-template-columns: 1fr;
    }

    .filters-block,
    .grid-2,
    .metrics {
        grid-template-columns: 1fr;
    }
}
</style>
