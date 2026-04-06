<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const API_BASE = '/api/v1';
const MIDORI_DOWNLOAD_URL = 'https://astian.org/midori-browser/';

const typeFilters = [
    { value: 'all', label: 'Todos' },
    { value: 'theme', label: 'Themes' },
    { value: 'wallpaper', label: 'Fondos' },
    { value: 'widget', label: 'Widgets' },
    { value: 'animation', label: 'Animaciones' },
];

const sortOptions = [
    { value: 'recommended', label: 'Recomendados' },
    { value: 'newest', label: 'Mas recientes' },
    { value: 'name', label: 'Nombre A-Z' },
];

const perPageOptions = [12, 24, 48];

const loading = ref(false);
const error = ref('');
const search = ref('');
const selectedType = ref('all');
const selectedSort = ref('recommended');
const perPage = ref(12);
const assets = ref([]);
const meta = ref({ total: 0, current_page: 1, last_page: 1 });

const previewOpen = ref(false);
const previewItem = ref(null);
const previewDetail = ref(null);
const previewLoading = ref(false);

const featured = computed(() => assets.value.slice(0, 1));
const hasResults = computed(() => assets.value.length > 0);

const rangeLabel = computed(() => {
    const start = (meta.value.current_page - 1) * perPage.value + 1;
    const end = Math.min(start + assets.value.length - 1, meta.value.total);
    return `${start}-${end}`;
});

const visibleAssets = computed(() => {
    const list = [...assets.value];
    if (selectedSort.value === 'name') {
        list.sort((a, b) => a.name.localeCompare(b.name));
    }
    return list;
});

function gradientForType(type) {
    const map = {
        theme: 'linear-gradient(135deg, #0d9488 0%, #065f46 100%)',
        wallpaper: 'linear-gradient(140deg, #1e3a5f 0%, #4ade80 100%)',
        widget: 'linear-gradient(130deg, #0e7490 0%, #22d3ee 100%)',
        animation: 'linear-gradient(135deg, #059669 0%, #0891b2 100%)',
        collection: 'linear-gradient(135deg, #0f766e 0%, #16a34a 100%)',
        'midori-update': 'linear-gradient(135deg, #047857 0%, #06b6d4 100%)',
    };
    return map[type] || 'linear-gradient(135deg, #14532d 0%, #0ea5e9 100%)';
}

function typeLabel(type) {
    const labels = { theme: 'Theme', wallpaper: 'Fondo', widget: 'Widget', animation: 'Animacion', collection: 'Coleccion' };
    return labels[type] || type;
}

function iconForType(type) {
    const icons = { theme: '◐', wallpaper: '◻', widget: '⊞', animation: '◎', collection: '▣' };
    return icons[type] || '⬡';
}

function parseAsset(raw) {
    const lv = raw.latest_version || null;
    const browsers = Array.isArray(lv?.browsers) ? lv.browsers : [];
    const tags = Array.isArray(raw.tags) ? raw.tags : [];
    return {
        id: raw.id,
        slug: raw.slug,
        name: raw.name,
        type: raw.type,
        description: raw.description || '',
        author: raw.author || 'Midori Community',
        tags,
        browsers,
        version: lv?.version || null,
        gradient: gradientForType(raw.type),
    };
}

async function apiFetch(path) {
    const res = await fetch(`${API_BASE}${path}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body?.message || `Error ${res.status}`);
    }
    return res.json();
}

async function loadCatalog() {
    loading.value = true;
    error.value = '';
    try {
        const params = new URLSearchParams();
        if (search.value.trim()) params.set('q', search.value.trim());
        if (selectedType.value !== 'all') params.set('type', selectedType.value);
        params.set('per_page', String(perPage.value));
        params.set('page', String(meta.value.current_page));
        const q = params.toString();
        const payload = await apiFetch(`/catalog${q ? `?${q}` : ''}`);
        assets.value = (payload.data || []).map(parseAsset);
        meta.value = {
            total: payload.meta?.total || 0,
            current_page: payload.meta?.current_page || 1,
            last_page: payload.meta?.last_page || 1,
        };
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function changePage(page) {
    if (page < 1 || page > meta.value.last_page) return;
    meta.value.current_page = page;
    loadCatalog();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function openPreview(item) {
    previewItem.value = item;
    previewOpen.value = true;
    previewLoading.value = true;
    previewDetail.value = null;
    try {
        const payload = await apiFetch(`/assets/${item.slug}`);
        previewDetail.value = payload.data || null;
    } catch {
        previewDetail.value = null;
    } finally {
        previewLoading.value = false;
    }
}

function closePreview() {
    previewOpen.value = false;
    previewItem.value = null;
    previewDetail.value = null;
}

function installAsset(item) {
    window.open(`${API_BASE}/assets/${item.slug}/download`, '_blank', 'noopener,noreferrer');
}

let searchTimer = null;
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        meta.value.current_page = 1;
        loadCatalog();
    }, 350);
}

watch([selectedType, perPage], () => {
    meta.value.current_page = 1;
    loadCatalog();
});

onMounted(() => {
    loadCatalog();
});
</script>

<template>
    <div class="marketplace">
        <!-- ═══ Navbar ═══ -->
        <nav class="navbar">
            <div class="navbar-inner">
                <a href="/" class="nav-logo">
                    <span class="nav-logo-mark">M</span>
                </a>

                <div class="nav-links">
                    <a href="/" class="nav-link active">Marketplace</a>
                </div>

                <a :href="MIDORI_DOWNLOAD_URL" target="_blank" rel="noopener noreferrer" class="btn-download">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 1v9m0 0l3-3m-3 3L5 7M2 12v1.5A1.5 1.5 0 003.5 15h9a1.5 1.5 0 001.5-1.5V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Download Midori
                </a>
            </div>
        </nav>

        <!-- ═══ Search bar ═══ -->
        <div class="search-section">
            <div class="search-row">
                <div class="search-box">
                    <svg class="search-ico" viewBox="0 0 20 20" fill="currentColor" width="18" height="18">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                    </svg>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar por nombre, tag o autor..."
                        @input="onSearchInput"
                    />
                </div>
            </div>
        </div>

        <!-- ═══ Type tabs ═══ -->
        <div class="tabs-section">
            <div class="tabs-inner">
                <button
                    v-for="f in typeFilters"
                    :key="f.value"
                    :class="['tab-btn', { active: selectedType === f.value }]"
                    @click="selectedType = f.value"
                >
                    {{ f.label }}
                </button>
            </div>
        </div>

        <!-- ═══ Featured ═══ -->
        <section class="featured-section" v-if="!search.trim() && selectedType === 'all' && featured.length && meta.current_page === 1">
            <div class="featured-inner">
                <h2 class="section-title">Destacados</h2>
                <div class="featured-card" v-for="item in featured" :key="`f-${item.id}`">
                    <div class="featured-thumb" :style="{ background: item.gradient }"></div>
                    <div class="featured-body">
                        <span class="featured-type-badge">{{ typeLabel(item.type) }}</span>
                        <h3>{{ item.name }}</h3>
                        <p>{{ item.description || 'Contenido destacado para personalizar tu nueva pestaña en Midori.' }}</p>
                        <button class="btn-explore" @click="openPreview(item)">Ver detalle</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ Catalog grid ═══ -->
        <main class="catalog-section">
            <div class="catalog-inner">
                <!-- Toolbar -->
                <div class="catalog-toolbar">
                    <p class="showing-label">
                        Mostrando <strong>{{ rangeLabel }}</strong> de <strong>{{ meta.total }}</strong> items
                    </p>
                    <div class="toolbar-controls">
                        <label class="toolbar-select">
                            Por pagina
                            <select v-model.number="perPage">
                                <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </label>
                        <label class="toolbar-select">
                            Tipo
                            <select v-model="selectedType">
                                <option v-for="f in typeFilters" :key="f.value" :value="f.value">{{ f.label }}</option>
                            </select>
                        </label>
                        <label class="toolbar-select">
                            Ordenar
                            <select v-model="selectedSort">
                                <option v-for="s in sortOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </label>
                    </div>
                </div>

                <!-- States -->
                <p v-if="loading" class="state-msg">Cargando catalogo...</p>
                <p v-else-if="error" class="state-msg error-msg">{{ error }}</p>
                <p v-else-if="!hasResults" class="state-msg">No se encontraron resultados. Prueba con otro filtro o busqueda.</p>

                <!-- Grid -->
                <div v-if="!loading && hasResults" class="grid">
                    <article
                        v-for="item in visibleAssets"
                        :key="item.id"
                        class="card"
                        @click="openPreview(item)"
                    >
                        <div class="card-visual">
                            <div class="card-icon-wrap" :style="{ background: item.gradient }">
                                <span class="card-icon-glyph">{{ iconForType(item.type) }}</span>
                            </div>
                        </div>
                        <h3 class="card-name">{{ item.name }}</h3>
                        <p class="card-author">Por {{ item.author }}</p>
                        <div class="card-badges">
                            <span class="badge type-badge">{{ typeLabel(item.type) }}</span>
                            <span v-for="tag in item.tags.slice(0, 1)" :key="tag" class="badge tag-badge">{{ tag }}</span>
                        </div>
                    </article>
                </div>

                <!-- Pagination -->
                <div v-if="meta.last_page > 1 && !loading" class="pagination">
                    <button :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
                        ← Anterior
                    </button>
                    <span class="page-info">Pagina {{ meta.current_page }} de {{ meta.last_page }}</span>
                    <button :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">
                        Siguiente →
                    </button>
                </div>
            </div>
        </main>

        <!-- ═══ Preview modal ═══ -->
        <Teleport to="body">
            <div v-if="previewOpen && previewItem" class="modal-overlay" @click.self="closePreview">
                <div class="modal-panel">
                    <button class="modal-close" @click="closePreview" aria-label="Cerrar">✕</button>

                    <div class="modal-banner" :style="{ background: previewItem.gradient }">
                        <div class="modal-banner-icon">{{ iconForType(previewItem.type) }}</div>
                    </div>

                    <div class="modal-body">
                        <span class="badge type-badge">{{ typeLabel(previewItem.type) }}</span>
                        <h2>{{ previewItem.name }}</h2>
                        <p class="modal-author">Por {{ previewItem.author }}</p>

                        <div v-if="previewLoading" class="modal-loading">Cargando detalle...</div>
                        <template v-else>
                            <p class="modal-desc">
                                {{ previewDetail?.description || previewItem.description || 'Sin descripcion disponible.' }}
                            </p>

                            <div class="modal-info-grid">
                                <div v-if="previewItem.version" class="info-item">
                                    <span class="info-label">Version</span>
                                    <span>{{ previewItem.version }}</span>
                                </div>
                                <div v-if="previewItem.browsers.length" class="info-item">
                                    <span class="info-label">Compatibilidad</span>
                                    <div class="browser-row">
                                        <span v-for="b in previewItem.browsers" :key="b" class="badge browser-badge">{{ b }}</span>
                                    </div>
                                </div>
                                <div v-if="previewItem.tags.length" class="info-item">
                                    <span class="info-label">Tags</span>
                                    <div class="card-badges">
                                        <span v-for="tag in previewItem.tags" :key="tag" class="badge tag-badge">{{ tag }}</span>
                                    </div>
                                </div>
                                <div v-if="previewDetail?.versions?.length" class="info-item">
                                    <span class="info-label">Versiones</span>
                                    <span>{{ previewDetail.versions.length }} disponibles</span>
                                </div>
                            </div>
                        </template>

                        <div class="modal-actions">
                            <button class="btn-install" @click="installAsset(previewItem)">
                                Agregar a Midori
                            </button>
                            <button class="btn-secondary" @click="closePreview">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ═══ Footer ═══ -->
        <footer class="site-footer">
            <div class="footer-inner">
                <div class="footer-col">
                    <h4>Producto</h4>
                    <a :href="MIDORI_DOWNLOAD_URL" target="_blank" rel="noopener noreferrer">Download Midori</a>
                    <a href="/">Marketplace</a>
                </div>
                <div class="footer-col">
                    <h4>Recursos</h4>
                    <a href="https://astian.org" target="_blank" rel="noopener noreferrer">Astian</a>
                </div>
                <div class="footer-col">
                    <h4>Comunidad</h4>
                    <a href="https://github.com/goastian" target="_blank" rel="noopener noreferrer">GitHub</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2024-{{ new Date().getFullYear() }} Astian, Inc. Midori Marketplace.</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

:global(body) {
    margin: 0;
    min-height: 100vh;
    background: #f9fafb;
    color: #1f2937;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    -webkit-font-smoothing: antialiased;
}

:global(*) {
    box-sizing: border-box;
}

.marketplace {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ═══ Navbar ═══ */
.navbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.navbar-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
    height: 60px;
    display: flex;
    align-items: center;
    gap: 2rem;
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    flex-shrink: 0;
}

.nav-logo-mark {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #16a34a, #0d9488);
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 1.1rem;
}

.nav-links {
    flex: 1;
    display: flex;
    gap: 1.5rem;
}

.nav-link {
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    color: #6b7280;
    transition: color 0.15s;
}

.nav-link:hover,
.nav-link.active {
    color: #111827;
}

.btn-download {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    background: #16a34a;
    color: #ffffff;
    transition: background 0.15s;
    flex-shrink: 0;
}

.btn-download:hover {
    background: #15803d;
}

/* ═══ Search ═══ */
.search-section {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 1.5rem 1.5rem 0;
}

.search-row {
    display: flex;
    gap: 1rem;
}

.search-box {
    flex: 1;
    max-width: 600px;
    position: relative;
}

.search-ico {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
}

.search-box input {
    width: 100%;
    height: 44px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 0 1rem 0 2.75rem;
    font: inherit;
    font-size: 0.9rem;
    background: #ffffff;
    color: #1f2937;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.search-box input::placeholder {
    color: #9ca3af;
}

.search-box input:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

/* ═══ Type tabs ═══ */
.tabs-section {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 1rem 1.5rem 0;
}

.tabs-inner {
    display: flex;
    gap: 0.35rem;
    overflow-x: auto;
}

.tab-btn {
    border: 1px solid #e5e7eb;
    background: transparent;
    padding: 0.45rem 1rem;
    border-radius: 999px;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s;
}

.tab-btn:hover {
    border-color: #d1d5db;
    color: #374151;
}

.tab-btn.active {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
}

/* ═══ Featured ═══ */
.featured-section {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 1.5rem 1.5rem 0;
}

.section-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0 0 0.75rem;
    color: #111827;
}

.featured-card {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-radius: 16px;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.featured-thumb {
    min-height: 220px;
}

.featured-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.featured-type-badge {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 600;
    background: #f3f4f6;
    color: #6b7280;
    border-radius: 6px;
    padding: 0.2rem 0.55rem;
    width: fit-content;
    margin-bottom: 0.5rem;
}

.featured-body h3 {
    margin: 0 0 0.5rem;
    font-size: 1.3rem;
    color: #111827;
}

.featured-body p {
    margin: 0 0 1rem;
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.55;
}

.btn-explore {
    width: fit-content;
    border: 1px solid #e5e7eb;
    background: transparent;
    color: #374151;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font: inherit;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-explore:hover {
    background: #f3f4f6;
}

/* ═══ Catalog ═══ */
.catalog-section {
    flex: 1;
}

.catalog-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem;
}

.catalog-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.showing-label {
    margin: 0;
    font-size: 0.82rem;
    color: #9ca3af;
}

.showing-label strong {
    color: #374151;
}

.toolbar-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.toolbar-select {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: #6b7280;
}

.toolbar-select select {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.3rem 0.5rem;
    font: inherit;
    font-size: 0.8rem;
    background: #ffffff;
    color: #374151;
    cursor: pointer;
}

.state-msg {
    text-align: center;
    padding: 4rem 1rem;
    color: #9ca3af;
    font-size: 0.95rem;
}

.error-msg {
    color: #ef4444;
}

/* ═══ Grid ═══ */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.85rem;
}

.card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 1rem;
    cursor: pointer;
    transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    border-color: #d1d5db;
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
}

.card-visual {
    margin-bottom: 0.75rem;
}

.card-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: grid;
    place-items: center;
}

.card-icon-glyph {
    font-size: 1.4rem;
    color: rgba(255, 255, 255, 0.85);
}

.card-name {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #111827;
    line-height: 1.3;
}

.card-author {
    margin: 0.15rem 0 0;
    font-size: 0.78rem;
    color: #9ca3af;
}

.card-badges {
    margin-top: 0.55rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.badge {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 500;
    padding: 0.15rem 0.45rem;
    border-radius: 5px;
}

.type-badge {
    background: #f3f4f6;
    color: #6b7280;
}

.tag-badge {
    background: rgba(22, 163, 74, 0.1);
    color: #16a34a;
}

.browser-badge {
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
}

/* ═══ Pagination ═══ */
.pagination {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
}

.pagination button {
    border: 1px solid #e5e7eb;
    background: #ffffff;
    border-radius: 10px;
    padding: 0.45rem 0.9rem;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.15s;
}

.pagination button:hover:not(:disabled) {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.pagination button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.page-info {
    font-size: 0.82rem;
    color: #9ca3af;
}

/* ═══ Modal ═══ */
.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 200;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    padding: 1rem;
}

.modal-panel {
    width: min(560px, 100%);
    max-height: 90vh;
    overflow-y: auto;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.15);
    position: relative;
}

.modal-close {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 10;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: none;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 0.9rem;
    cursor: pointer;
    display: grid;
    place-items: center;
    transition: background 0.15s;
}

.modal-close:hover {
    background: rgba(0, 0, 0, 0.5);
}

.modal-banner {
    height: 200px;
    border-radius: 20px 20px 0 0;
    display: grid;
    place-items: center;
}

.modal-banner-icon {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.5);
}

.modal-body {
    padding: 1.25rem;
}

.modal-body h2 {
    margin: 0.5rem 0 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #111827;
}

.modal-author {
    margin: 0.15rem 0 0;
    font-size: 0.85rem;
    color: #9ca3af;
}

.modal-loading {
    padding: 1rem 0;
    color: #9ca3af;
    font-size: 0.88rem;
}

.modal-desc {
    margin: 1rem 0;
    font-size: 0.9rem;
    color: #6b7280;
    line-height: 1.6;
}

.modal-info-grid {
    display: grid;
    gap: 0.75rem;
    padding: 1rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #374151;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    min-width: 110px;
    flex-shrink: 0;
}

.browser-row {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.modal-actions {
    display: flex;
    gap: 0.6rem;
}

.btn-install {
    flex: 1;
    border: none;
    border-radius: 12px;
    padding: 0.7rem 1rem;
    font: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    background: #16a34a;
    color: #ffffff;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-install:hover {
    background: #15803d;
}

.btn-secondary {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 0.7rem 1rem;
    font: inherit;
    font-size: 0.9rem;
    font-weight: 500;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-secondary:hover {
    background: #f3f4f6;
}

/* ═══ Footer ═══ */
.site-footer {
    margin-top: 3rem;
    border-top: 1px solid #e5e7eb;
    background: #ffffff;
}

.footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.footer-col h4 {
    margin: 0 0 0.6rem;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
}

.footer-col a {
    display: block;
    text-decoration: none;
    font-size: 0.85rem;
    color: #9ca3af;
    padding: 0.15rem 0;
    transition: color 0.15s;
}

.footer-col a:hover {
    color: #374151;
}

.footer-bottom {
    border-top: 1px solid #e5e7eb;
    text-align: center;
    padding: 1rem 1.5rem;
}

.footer-bottom p {
    margin: 0;
    font-size: 0.75rem;
    color: #d1d5db;
}

/* ═══ Responsive ═══ */
@media (max-width: 800px) {
    .navbar-inner {
        padding: 0 1rem;
        gap: 1rem;
    }

    .nav-links {
        display: none;
    }

    .featured-card {
        grid-template-columns: 1fr;
    }

    .featured-thumb {
        min-height: 160px;
    }

    .catalog-toolbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .toolbar-controls {
        flex-wrap: wrap;
    }

    .grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 0.65rem;
    }

    .footer-inner {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }

    .card {
        padding: 0.75rem;
    }

    .card-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 10px;
    }

    .card-icon-glyph {
        font-size: 1.1rem;
    }

    .card-name {
        font-size: 0.85rem;
    }

    .btn-download span {
        display: none;
    }

    .search-section,
    .tabs-section,
    .featured-section {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .catalog-inner {
        padding: 1rem;
    }
}
</style>
