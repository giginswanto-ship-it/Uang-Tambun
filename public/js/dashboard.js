// Konfigurasi & State Aplikasi (Berbasis SQL + Laravel)
const DASHBOARD_CONFIG = window.__DASHBOARD_CONFIG__ || { csrfToken: '', routes: {} };
const ROUTES = DASHBOARD_CONFIG.routes || {};

// Preset Warna Kategori
const PRESET_CATEGORY_COLORS = [
    '#0284c7', '#8b5cf6', '#f59e0b', '#10b981', '#ec4899',
    '#64748b', '#ef4444', '#06b6d4', '#84cc16', '#6366f1'
];

const UNCATEGORIZED_LABEL = 'Belum Dikategorikan';

// Pengaturan Kategori Default
const DEFAULT_CATEGORY_SETTINGS = {
    defaultCategory: '',
    sortBy: 'manual',
    showCategorySection: true,
    budgetAlertPercent: 80
};

// State Aplikasi dari SQL Server
let appState = window.__INITIAL_STATE__ || {
    projectName: 'Project Pembangunan Ruko 3 Lantai',
    categories: [],
    categorySettings: { ...DEFAULT_CATEGORY_SETTINGS },
    investorFunds: [],
    kasbonList: [],
    expenses: []
};

// Modal & Form Selection State
let selectNewCategoryInExpenseForm = false;
let pendingDeleteCategoryId = null;
let selectedExpenseCategory = '';
let lastAddedExpenseId = null;
let editingInvestorId = null;
let editingKasbonId = null;
let editingExpenseId = null;
let pendingExpenseCategories = {};
let draftCategorySettings = null;
let draftCategoryMetaList = null;
let categorySettingsDirty = false;

// Chart Instance
let categoryChartInstance = null;

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return (meta && meta.getAttribute('content')) || DASHBOARD_CONFIG.csrfToken || '';
}

async function apiRequest(url, method = 'GET', payload = null) {
    const options = {
        method,
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    };

    if (payload !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
    }

    let response;
    try {
        response = await fetch(url, options);
    } catch (networkErr) {
        showToast('Gagal terhubung ke server Laravel. Periksa koneksi server.', 'warning');
        throw networkErr;
    }

    let data = null;
    try {
        data = await response.json();
    } catch (_) {
        data = null;
    }

    if (!response.ok) {
        let errorMessage = data && data.message ? data.message : 'Terjadi kesalahan saat menyimpan ke database.';
        if (data && data.errors && typeof data.errors === 'object') {
            const firstField = Object.keys(data.errors)[0];
            if (firstField && Array.isArray(data.errors[firstField]) && data.errors[firstField][0]) {
                errorMessage = data.errors[firstField][0];
            }
        }
        showToast(errorMessage, 'warning');
        const err = new Error(errorMessage);
        err.response = response;
        err.data = data;
        throw err;
    }

    if (data && data.state) {
        appState = data.state;
        ensureCategoriesIntegrity();
    }

    return data;
}

// Inisialisasi saat DOM Loaded
document.addEventListener('DOMContentLoaded', () => {
    renderColorSwatches();
    ensureCategoriesIntegrity();
    setDefaultDates();
    selectedExpenseCategory = appState.categorySettings?.defaultCategory || '';
    renderAll();
});

// Tutup modal teratas / input inline dengan tombol Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const catModal = document.getElementById('categoryModal');
        const delModal = document.getElementById('deleteCategoryModal');
        const setModal = document.getElementById('categorySettingsModal');
        const projEditBox = document.getElementById('projectNameEditBox');

        if (catModal && !catModal.classList.contains('hidden')) {
            closeCategoryModal();
        } else if (delModal && !delModal.classList.contains('hidden')) {
            closeDeleteCategoryModal();
        } else if (setModal && !setModal.classList.contains('hidden')) {
            closeCategorySettingsModal();
        } else if (projEditBox && !projEditBox.classList.contains('hidden')) {
            cancelProjectNameEdit();
        }
    }
});

// HTML Escaping Helper
function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Set default input date hari ini
function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('invDate').value = today;
    document.getElementById('kasDate').value = today;
    document.getElementById('expDate').value = today;
}

// Ensure categories & settings structure stays normalized
function ensureCategoriesIntegrity() {
    if (!Array.isArray(appState.categories)) {
        appState.categories = [];
    }

    appState.categories = appState.categories.map((cat, idx) => ({
        id: String(cat.id ?? `CAT-${idx + 1}`),
        name: String(cat.name || '').trim(),
        desc: String(cat.desc || '').trim(),
        color: cat.color || PRESET_CATEGORY_COLORS[idx % PRESET_CATEGORY_COLORS.length],
        budget: Math.max(0, Number(cat.budget) || 0),
        isActive: cat.isActive !== false,
        sortOrder: Number.isFinite(Number(cat.sortOrder)) ? Number(cat.sortOrder) : idx
    })).filter(cat => cat.name !== '' && cat.name !== UNCATEGORIZED_LABEL);

    const rawSettings = (appState.categorySettings && typeof appState.categorySettings === 'object')
        ? appState.categorySettings
        : {};
    const validSort = ['manual', 'name', 'amount_desc'].includes(rawSettings.sortBy)
        ? rawSettings.sortBy
        : DEFAULT_CATEGORY_SETTINGS.sortBy;
    const alertPct = Math.min(100, Math.max(1, Number(rawSettings.budgetAlertPercent) || DEFAULT_CATEGORY_SETTINGS.budgetAlertPercent));
    const defaultCatValid = appState.categories.some(c => c.name === rawSettings.defaultCategory && c.isActive !== false)
        ? rawSettings.defaultCategory
        : '';

    appState.categorySettings = {
        defaultCategory: defaultCatValid,
        sortBy: validSort,
        showCategorySection: rawSettings.showCategorySection !== false,
        budgetAlertPercent: alertPct
    };

    appState.investorFunds = Array.isArray(appState.investorFunds) ? appState.investorFunds : [];
    appState.kasbonList = Array.isArray(appState.kasbonList) ? appState.kasbonList : [];
    appState.expenses = Array.isArray(appState.expenses) ? appState.expenses : [];
}

// Format Currency Helper
function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(number || 0);
}

// Hitung total biaya per item dengan fallback qty * price
function getExpenseTotal(item) {
    return Number(item.total) || ((Number(item.qty) || 0) * (Number(item.price) || 0));
}

// Calculations & Financial Math
function calculateSummary() {
    const totalInvestor = appState.investorFunds.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    const totalKasbon = appState.kasbonList.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);

    // Per Pelaksana Kasbon
    const renggaKasbon = appState.kasbonList
        .filter(k => k.pelaksana === 'Moh. Rengga')
        .reduce((sum, item) => sum + (Number(item.amount) || 0), 0);

    const ashabilKasbon = appState.kasbonList
        .filter(k => k.pelaksana === 'Moh. Ashabil')
        .reduce((sum, item) => sum + (Number(item.amount) || 0), 0);

    // Expenses Per Pelaksana
    const renggaBiaya = appState.expenses
        .filter(e => e.pelaksana === 'Moh. Rengga')
        .reduce((sum, item) => sum + getExpenseTotal(item), 0);

    const ashabilBiaya = appState.expenses
        .filter(e => e.pelaksana === 'Moh. Ashabil')
        .reduce((sum, item) => sum + getExpenseTotal(item), 0);

    const totalExpenses = renggaBiaya + ashabilBiaya;

    // Balance Checks
    const sisaPrincipal = totalInvestor - totalKasbon;
    const renggaSisa = renggaKasbon - renggaBiaya;
    const ashabilSisa = ashabilKasbon - ashabilBiaya;
    const combinedPelaksanaSisa = renggaSisa + ashabilSisa;

    // Total Budget dihitung murni dari jumlah budget setiap kategori aktif
    const activeCategories = appState.categories.filter(c => c.isActive !== false);
    const activeCategoryCount = activeCategories.length;
    const budgetedCategoryCount = activeCategories.filter(c => (Number(c.budget) || 0) > 0).length;
    const activeCategoryBudgetSum = activeCategories
        .reduce((sum, c) => sum + (Number(c.budget) || 0), 0);

    const totalBudget = activeCategoryBudgetSum;
    const budgetSourceLabel = totalBudget > 0
        ? `Akumulasi budget dari ${budgetedCategoryCount} dari ${activeCategoryCount} kategori aktif`
        : 'Belum ada budget kategori (klik Atur Budget Kategori)';

    const budgetUsed = totalExpenses;
    const budgetRemaining = totalBudget - budgetUsed;
    const budgetUsagePercent = totalBudget > 0
        ? Math.round((budgetUsed / totalBudget) * 100)
        : (budgetUsed > 0 ? 100 : 0);

    return {
        totalInvestor,
        totalKasbon,
        sisaPrincipal,
        renggaKasbon,
        renggaBiaya,
        renggaSisa,
        ashabilKasbon,
        ashabilBiaya,
        ashabilSisa,
        totalExpenses,
        combinedPelaksanaSisa,
        activeCategoryCount,
        budgetedCategoryCount,
        activeCategoryBudgetSum,
        totalBudget,
        budgetSourceLabel,
        budgetUsed,
        budgetRemaining,
        budgetUsagePercent
    };
}

function renderProjectBudgetSummary(summary) {
    document.getElementById('kpiTotalBudget').innerText = formatRupiah(summary.totalBudget);
    document.getElementById('kpiBudgetSourceNote').innerText = summary.budgetSourceLabel;
    document.getElementById('kpiBudgetUsed').innerText = formatRupiah(summary.budgetUsed);
    document.getElementById('kpiBudgetUsedNote').innerText = `Dari ${appState.expenses.length} transaksi pengeluaran tercatat`;
    document.getElementById('kpiBudgetRemaining').innerText = formatRupiah(summary.budgetRemaining);

    const pctBadge = document.getElementById('kpiBudgetUsagePctBadge');
    const cardRem = document.getElementById('kpiBudgetRemainingCard');
    const badgeRem = document.getElementById('kpiBudgetRemainingBadge');
    const valRem = document.getElementById('kpiBudgetRemaining');
    const barEl = document.getElementById('kpiBudgetProgressBar');
    const noteRem = document.getElementById('kpiBudgetRemainingNote');

    const barWidth = Math.min(100, Math.max(0, summary.budgetUsagePercent));
    barEl.style.width = `${barWidth}%`;
    pctBadge.innerText = `${summary.budgetUsagePercent}%`;

    const alertPct = appState.categorySettings?.budgetAlertPercent || 80;

    if (summary.budgetRemaining < 0) {
        cardRem.className = 'bg-gradient-to-br from-rose-950 to-slate-900 text-white rounded-2xl p-5 border border-rose-800 shadow-md relative overflow-hidden flex flex-col justify-between';
        badgeRem.className = 'px-2 py-0.5 bg-rose-500/30 text-rose-300 rounded-full text-[11px] font-bold';
        badgeRem.innerText = 'OVER BUDGET';
        valRem.className = 'text-2xl sm:text-3xl font-black text-rose-400 mb-1';
        barEl.className = 'bg-rose-400 h-full rounded-full transition-all duration-500';
        pctBadge.className = 'px-2.5 py-0.5 bg-rose-100 text-rose-800 rounded-full text-[11px] font-bold';
        noteRem.innerText = `Melebihi budget sebesar ${formatRupiah(Math.abs(summary.budgetRemaining))}`;
    } else if (summary.totalBudget > 0 && summary.budgetUsagePercent >= alertPct) {
        cardRem.className = 'bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-5 border border-amber-500/60 shadow-md relative overflow-hidden flex flex-col justify-between';
        badgeRem.className = 'px-2 py-0.5 bg-amber-500/30 text-amber-300 rounded-full text-[11px] font-bold';
        badgeRem.innerText = 'MENDEKATI BATAS';
        valRem.className = 'text-2xl sm:text-3xl font-black text-amber-300 mb-1';
        barEl.className = 'bg-amber-400 h-full rounded-full transition-all duration-500';
        pctBadge.className = 'px-2.5 py-0.5 bg-amber-100 text-amber-800 rounded-full text-[11px] font-bold';
        noteRem.innerText = `Tersisa ${Math.max(0, 100 - summary.budgetUsagePercent)}% dari Total Budget`;
    } else {
        cardRem.className = 'bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-5 border border-slate-700 shadow-md relative overflow-hidden flex flex-col justify-between';
        badgeRem.className = summary.totalBudget > 0
            ? 'px-2 py-0.5 bg-emerald-500/30 text-emerald-300 rounded-full text-[11px] font-bold'
            : 'px-2 py-0.5 bg-slate-500/40 text-slate-300 rounded-full text-[11px] font-bold';
        badgeRem.innerText = summary.totalBudget > 0 ? 'TERSEDIA' : 'BELUM DIATUR';
        valRem.className = 'text-2xl sm:text-3xl font-black text-emerald-400 mb-1';
        barEl.className = 'bg-emerald-400 h-full rounded-full transition-all duration-500';
        pctBadge.className = 'px-2.5 py-0.5 bg-amber-100 text-amber-800 rounded-full text-[11px] font-bold';
        noteRem.innerText = summary.totalBudget > 0
            ? `Tersisa ${Math.max(0, 100 - summary.budgetUsagePercent)}% dari Total Budget`
            : 'Klik Atur Budget Kategori untuk mengisi budget tiap kategori';
    }
}

// Render All UI Elements
function renderAll() {
    ensureCategoriesIntegrity();
    const summary = calculateSummary();

    // Update Title
    document.getElementById('projectNameDisplay').innerText = appState.projectName;

    // Update Budget Summary Row
    renderProjectBudgetSummary(summary);

    // Update Main KPIs
    document.getElementById('kpiInvestorTotal').innerText = formatRupiah(summary.totalInvestor);
    document.getElementById('kpiKasbonTotal').innerText = formatRupiah(summary.totalKasbon);
    document.getElementById('kpiPrincipalSisa').innerText = formatRupiah(summary.sisaPrincipal);
    document.getElementById('kpiExpensesTotal').innerText = formatRupiah(summary.totalExpenses);
    document.getElementById('kpiPelaksanaSisaCombined').innerHTML = `Sisa Kasbon Pelaksana: <span class="font-bold text-slate-900">${formatRupiah(summary.combinedPelaksanaSisa)}</span>`;

    // Principal Alert Card State
    const cardPrincipal = document.getElementById('kpiPrincipalCard');
    const badgePrincipal = document.getElementById('kpiPrincipalBadge');
    const elPrincipalSisa = document.getElementById('kpiPrincipalSisa');

    if (summary.sisaPrincipal < 0) {
        cardPrincipal.className = "bg-gradient-to-br from-rose-950 to-slate-900 text-white rounded-2xl p-5 border border-rose-800 shadow-md relative overflow-hidden";
        badgePrincipal.className = "px-2 py-0.5 bg-rose-500/30 text-rose-300 rounded text-[11px] font-bold";
        badgePrincipal.innerText = "DEFISIT / KURANG";
        elPrincipalSisa.className = "text-2xl font-black text-rose-400 mb-1";
    } else if (summary.sisaPrincipal < 10000000 && summary.totalInvestor > 0) {
        cardPrincipal.className = "bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-5 border border-amber-500/50 shadow-md relative overflow-hidden";
        badgePrincipal.className = "px-2 py-0.5 bg-amber-500/30 text-amber-300 rounded text-[11px] font-bold";
        badgePrincipal.innerText = "SALDO TIPIS";
        elPrincipalSisa.className = "text-2xl font-black text-amber-400 mb-1";
    } else {
        cardPrincipal.className = "bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-5 border border-slate-700 shadow-md relative overflow-hidden";
        badgePrincipal.className = "px-2 py-0.5 bg-emerald-500/30 text-emerald-300 rounded text-[11px] font-bold";
        badgePrincipal.innerText = "AMAN";
        elPrincipalSisa.className = "text-2xl font-black text-emerald-400 mb-1";
    }

    // Update Moh. Rengga Card
    document.getElementById('renggaKasbon').innerText = formatRupiah(summary.renggaKasbon);
    document.getElementById('renggaBiaya').innerText = formatRupiah(summary.renggaBiaya);
    document.getElementById('renggaSisa').innerText = formatRupiah(summary.renggaSisa);
    const rPercent = summary.renggaKasbon > 0 ? Math.min(100, Math.round((summary.renggaBiaya / summary.renggaKasbon) * 100)) : 0;
    document.getElementById('renggaPercent').innerText = `${rPercent}%`;
    document.getElementById('renggaBar').style.width = `${Math.min(100, rPercent)}%`;

    const badgeR = document.getElementById('badgeRengga');
    const elRenggaSisa = document.getElementById('renggaSisa');
    if (summary.renggaSisa < 0) {
        badgeR.className = "px-2.5 py-1 text-[11px] font-bold rounded-full bg-rose-100 text-rose-700 animate-pulse";
        badgeR.innerText = "Reimburse (Kurang Bayar)";
        elRenggaSisa.className = "text-base text-rose-600 font-extrabold";
    } else {
        badgeR.className = "px-2.5 py-1 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-700";
        badgeR.innerText = "Kasbon Sisa Aman";
        elRenggaSisa.className = "text-base text-emerald-600 font-bold";
    }

    // Update Moh. Ashabil Card
    document.getElementById('ashabilKasbon').innerText = formatRupiah(summary.ashabilKasbon);
    document.getElementById('ashabilBiaya').innerText = formatRupiah(summary.ashabilBiaya);
    document.getElementById('ashabilSisa').innerText = formatRupiah(summary.ashabilSisa);
    const aPercent = summary.ashabilKasbon > 0 ? Math.min(100, Math.round((summary.ashabilBiaya / summary.ashabilKasbon) * 100)) : 0;
    document.getElementById('ashabilPercent').innerText = `${aPercent}%`;
    document.getElementById('ashabilBar').style.width = `${Math.min(100, aPercent)}%`;

    const badgeA = document.getElementById('badgeAshabil');
    const elAshabilSisa = document.getElementById('ashabilSisa');
    if (summary.ashabilSisa < 0) {
        badgeA.className = "px-2.5 py-1 text-[11px] font-bold rounded-full bg-rose-100 text-rose-700 animate-pulse";
        badgeA.innerText = "Reimburse (Kurang Bayar)";
        elAshabilSisa.className = "text-base text-rose-600 font-extrabold";
    } else {
        badgeA.className = "px-2.5 py-1 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-700";
        badgeA.innerText = "Kasbon Sisa Aman";
        elAshabilSisa.className = "text-base text-emerald-600 font-bold";
    }

    // Update Counts View Buttons
    document.getElementById('countBiaya').innerText = appState.expenses.length;
    document.getElementById('countKasbon').innerText = appState.kasbonList.length;
    document.getElementById('countInvestor').innerText = appState.investorFunds.length;

    // Render Categories, Selectors, Tables & Chart
    renderCategoryList();
    renderCategorySelectors();
    renderExpensesTable();
    renderKasbonTable();
    renderInvestorTable();
    renderChart();

    // Perbarui daftar di modal Pengaturan Kategori jika sedang terbuka
    const settingsModal = document.getElementById('categorySettingsModal');
    if (settingsModal && !settingsModal.classList.contains('hidden')) {
        syncCategorySettingsModalUI();
        renderCategorySettingsList();
    }
}

// Hitung jumlah transaksi & total realisasi per nama kategori
function getCategoryStatsMap() {
    const stats = {};
    appState.expenses.forEach(exp => {
        const key = exp.category || UNCATEGORIZED_LABEL;
        if (!stats[key]) {
            stats[key] = { count: 0, total: 0 };
        }
        stats[key].count += 1;
        stats[key].total += getExpenseTotal(exp);
    });
    return stats;
}

// Urutkan kategori sesuai pengaturan (manual / name / amount_desc)
function getSortedCategories(list = appState.categories, sortByOverride = null) {
    const sortBy = sortByOverride || appState.categorySettings?.sortBy || 'manual';
    const copy = [...list];
    if (sortBy === 'name') {
        return copy.sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' }));
    }
    if (sortBy === 'amount_desc') {
        const stats = getCategoryStatsMap();
        return copy.sort((a, b) => (stats[b.name]?.total || 0) - (stats[a.name]?.total || 0));
    }
    return copy;
}

// Render Color Swatches di Modal Kategori
function renderColorSwatches() {
    const container = document.getElementById('catColorSwatches');
    container.innerHTML = PRESET_CATEGORY_COLORS.map(color => `
        <button type="button" data-swatch-color="${color}" onclick="selectPresetColor('${color}')" class="w-6 h-6 rounded-full border-2 border-white shadow-sm ring-1 ring-slate-200 transition transform hover:scale-110" style="background-color: ${color};" title="${color}"></button>
    `).join('');
}

function selectPresetColor(color) {
    document.getElementById('catColor').value = color;
    syncColorSwatchSelection(color);
}

function syncColorSwatchSelection(activeColor) {
    const normalized = String(activeColor || '').toLowerCase();
    document.querySelectorAll('#catColorSwatches button[data-swatch-color]').forEach(btn => {
        const isMatch = btn.dataset.swatchColor.toLowerCase() === normalized;
        btn.className = isMatch
            ? 'w-6 h-6 rounded-full border-2 border-white shadow-md ring-2 ring-slate-900 transition transform scale-110'
            : 'w-6 h-6 rounded-full border-2 border-white shadow-sm ring-1 ring-slate-200 transition transform hover:scale-110';
    });
}

// Render Daftar Kategori dengan Tombol Edit & Hapus di Setiap Kategori
function renderCategoryList() {
    const section = document.getElementById('categoryManagementSection');
    const showSection = appState.categorySettings?.showCategorySection !== false;
    if (section) {
        section.classList.toggle('hidden', !showSection);
    }

    const container = document.getElementById('categoryListContainer');
    const badge = document.getElementById('categoryCountBadge');
    const activeCount = appState.categories.filter(c => c.isActive !== false).length;
    badge.innerText = activeCount === appState.categories.length
        ? `${appState.categories.length} Kategori`
        : `${activeCount} Aktif / ${appState.categories.length} Total`;

    if (appState.categories.length === 0) {
        container.innerHTML = `
            <div class="col-span-full bg-slate-50 border border-dashed border-slate-300 rounded-xl p-6 text-center text-xs text-slate-500 space-y-2">
                <p>Belum ada kategori biaya yang tersedia.</p>
                <button type="button" onclick="openCategoryModal()" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold inline-flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-plus"></i> Tambah Kategori Pertama
                </button>
            </div>
        `;
        return;
    }

    const stats = getCategoryStatsMap();
    const alertPct = appState.categorySettings?.budgetAlertPercent || 80;
    const defaultCatName = appState.categorySettings?.defaultCategory || '';
    const sortedCategories = getSortedCategories();

    container.innerHTML = '';
    sortedCategories.forEach(cat => {
        const stat = stats[cat.name] || { count: 0, total: 0 };
        const totalNominal = stat.total;
        const descText = cat.desc && cat.desc !== cat.name ? cat.desc : '';
        const isInactive = cat.isActive === false;
        const isDefault = defaultCatName === cat.name;

        let budgetHtml = '';
        if (cat.budget > 0) {
            const pct = Math.round((totalNominal / cat.budget) * 100);
            const barWidth = Math.min(100, pct);
            let barColor = 'bg-emerald-500';
            let pctTextClass = 'text-emerald-700';
            let statusText = 'Aman';
            if (pct > 100) {
                barColor = 'bg-rose-500';
                pctTextClass = 'text-rose-700 font-bold';
                statusText = 'Melebihi Pagu';
            } else if (pct >= alertPct) {
                barColor = 'bg-amber-500';
                pctTextClass = 'text-amber-700 font-bold';
                statusText = 'Mendekati Batas';
            }
            budgetHtml = `
                <div class="mt-2 pt-2 border-t border-slate-200/80 space-y-1">
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-500">Anggaran: <span class="font-mono font-semibold text-slate-700">${formatRupiah(cat.budget)}</span></span>
                        <span class="${pctTextClass}">${pct}% (${statusText})</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-200 rounded-full overflow-hidden">
                        <div class="${barColor} h-full rounded-full transition-all" style="width: ${barWidth}%"></div>
                    </div>
                </div>
            `;
        }

        const card = document.createElement('div');
        card.className = `flex flex-col justify-between gap-2.5 p-3.5 rounded-xl border transition ${isInactive ? 'border-slate-200 bg-slate-100/70 opacity-75' : 'border-slate-200 bg-slate-50/70 hover:bg-white hover:shadow-sm'}`;
        card.innerHTML = `
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="w-3.5 h-3.5 rounded-full flex-shrink-0 mt-1 shadow-sm ring-2 ring-white" style="background-color: ${escapeHtml(cat.color)};"></span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <h3 class="font-bold text-sm text-slate-900 truncate" title="${escapeHtml(cat.name)}">${escapeHtml(cat.name)}</h3>
                            ${isDefault ? '<span class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 text-[10px] font-bold">Default</span>' : ''}
                            ${isInactive ? '<span class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 text-[10px] font-semibold">Nonaktif</span>' : ''}
                        </div>
                        ${descText ? `<p class="text-[11px] text-slate-500 truncate" title="${escapeHtml(descText)}">${escapeHtml(descText)}</p>` : ''}
                        <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 font-medium">
                            <span class="bg-white border border-slate-200 px-1.5 py-0.5 rounded text-slate-600">${stat.count} transaksi</span>
                            <span class="font-mono text-slate-700">${formatRupiah(totalNominal)}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button type="button" onclick="openCategoryModal('${escapeHtml(cat.id)}')" class="px-2.5 py-1.5 bg-white hover:bg-blue-50 text-blue-600 hover:text-blue-700 border border-slate-200 hover:border-blue-200 rounded-lg text-[11px] font-semibold transition flex items-center gap-1 shadow-sm" title="Edit Kategori">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Edit</span>
                    </button>
                    <button type="button" onclick="openDeleteCategoryModal('${escapeHtml(cat.id)}')" class="px-2.5 py-1.5 bg-white hover:bg-rose-50 text-rose-600 hover:text-rose-700 border border-slate-200 hover:border-rose-200 rounded-lg text-[11px] font-semibold transition flex items-center gap-1 shadow-sm" title="Hapus Kategori">
                        <i class="fa-solid fa-trash-can"></i>
                        <span>Hapus</span>
                    </button>
                </div>
            </div>
            ${budgetHtml}
        `;
        container.appendChild(card);
    });
}

// Render Filter Kategori & Panel Pilihan Kategori di Sebelah Kanan Form
function renderCategorySelectors() {
    const expInput = document.getElementById('expCategory');
    const filterSelect = document.getElementById('filterCategory');
    const prevFilterVal = filterSelect.value;

    if (selectedExpenseCategory && !appState.categories.some(c => c.name === selectedExpenseCategory && (editingExpenseId || c.isActive !== false))) {
        selectedExpenseCategory = '';
    }
    expInput.value = selectedExpenseCategory;

    renderExpenseRightCategoryList();

    // Dropdown Filter Tabel
    const sortedCategories = getSortedCategories();
    const hasUncategorized = appState.expenses.some(e => !e.category || e.category === UNCATEGORIZED_LABEL);

    filterSelect.innerHTML = '<option value="ALL">Semua Kategori</option>';
    if (hasUncategorized) {
        const opt = document.createElement('option');
        opt.value = UNCATEGORIZED_LABEL;
        opt.textContent = UNCATEGORIZED_LABEL;
        filterSelect.appendChild(opt);
    }
    sortedCategories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.name;
        opt.textContent = cat.isActive === false ? `${cat.name} (Nonaktif)` : cat.name;
        filterSelect.appendChild(opt);
    });
    if (
        prevFilterVal &&
        (prevFilterVal === 'ALL' ||
            (prevFilterVal === UNCATEGORIZED_LABEL && hasUncategorized) ||
            appState.categories.some(c => c.name === prevFilterVal))
    ) {
        filterSelect.value = prevFilterVal;
    } else {
        filterSelect.value = 'ALL';
    }
}

// Render Pilihan Kategori di Sebelah Kanan Form Input Pengeluaran
function renderExpenseRightCategoryList() {
    const listEl = document.getElementById('expenseRightCategoryList');
    const badgeEl = document.getElementById('selectedExpenseCategoryBadge');
    if (!listEl) return;

    const activeCategories = getSortedCategories().filter(c => c.isActive !== false);
    const stats = getCategoryStatsMap();

    // Update badge terpilih
    if (badgeEl) {
        if (selectedExpenseCategory) {
            const matched = appState.categories.find(c => c.name === selectedExpenseCategory);
            const dotColor = matched?.color || '#d97706';
            const inactiveSuffix = matched && matched.isActive === false ? ' (Nonaktif)' : '';
            badgeEl.innerHTML = `
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-amber-100 text-amber-900">
                    <span class="w-2 h-2 rounded-full" style="background-color: ${escapeHtml(dotColor)};"></span>
                    <span>${escapeHtml(selectedExpenseCategory + inactiveSuffix)}</span>
                </span>
            `;
        } else {
            badgeEl.innerHTML = `<span class="text-slate-600">Tentukan Setelah Input</span>`;
        }
    }

    listEl.innerHTML = '';

    // Opsi untuk menentukan kategori tepat setelah diinput di tabel
    const deferSelected = !selectedExpenseCategory;
    const deferBtn = document.createElement('button');
    deferBtn.type = 'button';
    deferBtn.setAttribute('onclick', 'clearExpenseCategorySelection()');
    deferBtn.className = deferSelected
        ? 'w-full text-left p-2.5 rounded-xl border-2 border-slate-700 bg-white shadow-sm transition flex items-center justify-between gap-2'
        : 'w-full text-left p-2.5 rounded-xl border border-dashed border-slate-300 bg-white/70 hover:bg-white transition flex items-center justify-between gap-2';
    deferBtn.innerHTML = `
        <div class="flex items-center gap-2 min-w-0">
            <span class="w-3 h-3 rounded-full bg-slate-300 flex-shrink-0"></span>
            <div class="min-w-0">
                <div class="font-semibold text-xs text-slate-800 truncate">Tentukan Setelah Input</div>
                <div class="text-[10px] text-slate-500 truncate">Pilih kategori langsung di tabel setelah simpan</div>
            </div>
        </div>
        <span class="w-4 h-4 rounded-full flex items-center justify-center flex-shrink-0 ${deferSelected ? 'bg-slate-800 text-white' : 'border border-slate-300 text-transparent'}">
            <i class="fa-solid fa-check text-[9px]"></i>
        </span>
    `;
    listEl.appendChild(deferBtn);

    if (activeCategories.length === 0) {
        const emptyNote = document.createElement('div');
        emptyNote.className = 'p-3 rounded-xl border border-dashed border-slate-300 bg-white text-center text-[11px] text-slate-500';
        emptyNote.innerHTML = 'Tidak ada kategori aktif. Klik <strong>+ Baru</strong> atau buka <strong>Pengaturan Kategori</strong>.';
        listEl.appendChild(emptyNote);
        return;
    }

    activeCategories.forEach(cat => {
        const isSelected = selectedExpenseCategory === cat.name;
        const descText = cat.desc && cat.desc !== cat.name ? cat.desc : '';
        const spent = stats[cat.name]?.total || 0;
        const budgetHint = cat.budget > 0
            ? `Sisa pagu: ${formatRupiah(cat.budget - spent)}`
            : descText;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('onclick', `selectExpenseCategory('${escapeHtml(cat.id)}')`);
        btn.className = isSelected
            ? 'w-full text-left p-2.5 rounded-xl border-2 border-amber-500 bg-amber-50 shadow-sm transition flex items-center justify-between gap-2'
            : 'w-full text-left p-2.5 rounded-xl border border-slate-200 bg-white hover:border-amber-300 hover:bg-amber-50/40 transition flex items-center justify-between gap-2';
        btn.innerHTML = `
            <div class="flex items-start gap-2.5 min-w-0">
                <span class="w-3 h-3 rounded-full flex-shrink-0 mt-1 shadow-sm" style="background-color: ${escapeHtml(cat.color)};"></span>
                <div class="min-w-0">
                    <div class="font-bold text-xs text-slate-900 truncate">${escapeHtml(cat.name)}</div>
                    ${budgetHint ? `<div class="text-[10px] text-slate-500 truncate">${escapeHtml(budgetHint)}</div>` : ''}
                </div>
            </div>
            <span class="w-4 h-4 rounded-full flex items-center justify-center flex-shrink-0 ${isSelected ? 'bg-amber-600 text-white' : 'border border-slate-300 text-transparent'}">
                <i class="fa-solid fa-check text-[9px]"></i>
            </span>
        `;
        listEl.appendChild(btn);
    });
}

function selectExpenseCategory(categoryId) {
    const cat = appState.categories.find(c => String(c.id) === String(categoryId) && c.isActive !== false);
    if (!cat) return;
    selectedExpenseCategory = cat.name;
    document.getElementById('expCategory').value = cat.name;
    renderExpenseRightCategoryList();
}

function clearExpenseCategorySelection() {
    selectedExpenseCategory = '';
    document.getElementById('expCategory').value = '';
    renderExpenseRightCategoryList();
}

// ==========================================
// PENGATURAN KATEGORI MODAL & ACTIONS
// ==========================================
function openCategorySettingsModal() {
    ensureCategoriesIntegrity();
    draftCategorySettings = { ...appState.categorySettings };
    draftCategoryMetaList = appState.categories.map(c => ({ ...c }));
    categorySettingsDirty = false;
    syncCategorySettingsModalUI();
    renderCategorySettingsList();
    updateSettingsTotalBudgetPreview();
    updateSettingsDirtyIndicator();
    const modal = document.getElementById('categorySettingsModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function updateSettingsTotalBudgetPreview() {
    const previewEl = document.getElementById('settingsTotalBudgetPreview');
    if (!previewEl) return;
    const sourceList = draftCategoryMetaList || appState.categories;
    const total = sourceList
        .filter(c => c.isActive !== false)
        .reduce((sum, c) => sum + (Number(c.budget) || 0), 0);
    previewEl.innerText = `Total Budget: ${formatRupiah(total)}`;
}

function onCategoryDraftBudgetChange(categoryId, value) {
    if (!draftCategoryMetaList) {
        draftCategoryMetaList = appState.categories.map(c => ({ ...c }));
    }
    const cat = draftCategoryMetaList.find(c => String(c.id) === String(categoryId));
    if (!cat) return;
    const parsed = parseFloat(value);
    cat.budget = Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
    categorySettingsDirty = true;
    updateSettingsTotalBudgetPreview();
    updateSettingsDirtyIndicator();
}

function closeCategorySettingsModal() {
    const modal = document.getElementById('categorySettingsModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    draftCategorySettings = null;
    draftCategoryMetaList = null;
    categorySettingsDirty = false;
}

function updateSettingsDirtyIndicator() {
    const badge = document.getElementById('settingsDirtyBadge');
    const saveBtn = document.getElementById('saveCategorySettingsBtn');
    if (badge) {
        if (categorySettingsDirty) {
            badge.className = 'text-[11px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full';
            badge.innerText = 'Ada perubahan belum disimpan';
        } else {
            badge.className = 'text-[11px] font-medium text-slate-500';
            badge.innerText = 'Klik Simpan Perubahan untuk menerapkan';
        }
    }
    if (saveBtn) {
        if (categorySettingsDirty) {
            saveBtn.className = 'px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-md ring-2 ring-emerald-300';
        } else {
            saveBtn.className = 'px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-sm';
        }
    }
}

function readCategorySettingsInputs() {
    const defaultVal = document.getElementById('settingDefaultCategory').value;
    const sortVal = document.getElementById('settingCategorySort').value;
    const alertRaw = parseInt(document.getElementById('settingBudgetAlertPercent').value, 10);
    const alertPct = Number.isFinite(alertRaw) ? Math.min(100, Math.max(1, alertRaw)) : 80;
    const showSection = document.getElementById('settingShowSection').checked;
    return {
        defaultCategory: defaultVal,
        sortBy: ['manual', 'name', 'amount_desc'].includes(sortVal) ? sortVal : 'manual',
        showCategorySection: showSection,
        budgetAlertPercent: alertPct
    };
}

function syncCategorySettingsModalUI() {
    const defaultSelect = document.getElementById('settingDefaultCategory');
    const sortSelect = document.getElementById('settingCategorySort');
    const alertInput = document.getElementById('settingBudgetAlertPercent');
    const showCheckbox = document.getElementById('settingShowSection');

    if (!defaultSelect) return;

    const activeSettings = draftCategorySettings || appState.categorySettings;
    const sourceCategories = draftCategoryMetaList || appState.categories;
    const activeCategories = sourceCategories.filter(c => c.isActive !== false);

    defaultSelect.innerHTML = '<option value="">Pilih Manual Setiap Input</option>';
    activeCategories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.name;
        opt.textContent = cat.name;
        defaultSelect.appendChild(opt);
    });

    defaultSelect.value = activeSettings.defaultCategory || '';
    sortSelect.value = activeSettings.sortBy || 'manual';
    alertInput.value = activeSettings.budgetAlertPercent || 80;
    showCheckbox.checked = activeSettings.showCategorySection !== false;
}

function onCategorySettingsFieldChange() {
    if (!draftCategorySettings) {
        draftCategorySettings = { ...appState.categorySettings };
    }
    draftCategorySettings = readCategorySettingsInputs();
    categorySettingsDirty = true;
    updateSettingsDirtyIndicator();
    renderCategorySettingsList();
}

function syncDraftCategorySettingsWithAppState() {
    if (!draftCategoryMetaList) return;
    const byId = new Map(appState.categories.map(c => [String(c.id), c]));
    const synced = [];
    draftCategoryMetaList.forEach(draftCat => {
        const liveCat = byId.get(String(draftCat.id));
        if (liveCat) {
            synced.push({ ...liveCat, isActive: draftCat.isActive });
            byId.delete(String(draftCat.id));
        }
    });
    byId.forEach(newLiveCat => synced.push({ ...newLiveCat }));
    draftCategoryMetaList = synced;

    if (draftCategorySettings && draftCategorySettings.defaultCategory) {
        const stillValid = draftCategoryMetaList.some(c => c.name === draftCategorySettings.defaultCategory && c.isActive !== false);
        if (!stillValid) {
            draftCategorySettings.defaultCategory = appState.categorySettings.defaultCategory || '';
        }
    }
    updateSettingsTotalBudgetPreview();
}

async function saveCategorySettingsChanges() {
    const nextSettings = readCategorySettingsInputs();
    const prevDefault = appState.categorySettings.defaultCategory;
    const sourceList = draftCategoryMetaList || appState.categories;

    const categoriesPayload = sourceList.map((cat, idx) => ({
        id: cat.id,
        budget: Math.max(0, Number(cat.budget) || 0),
        isActive: cat.isActive !== false,
        sortOrder: idx
    }));

    try {
        await apiRequest(ROUTES.categoriesSettingsBatch, 'PUT', {
            defaultCategory: nextSettings.defaultCategory,
            sortBy: nextSettings.sortBy,
            budgetAlertPercent: nextSettings.budgetAlertPercent,
            showCategorySection: nextSettings.showCategorySection,
            categories: categoriesPayload
        });
    } catch (_) {
        return;
    }

    const updatedDefault = appState.categorySettings.defaultCategory || '';
    if (updatedDefault && (!selectedExpenseCategory || selectedExpenseCategory === prevDefault)) {
        selectedExpenseCategory = updatedDefault;
        document.getElementById('expCategory').value = selectedExpenseCategory;
    } else if (!updatedDefault && prevDefault && selectedExpenseCategory === prevDefault) {
        selectedExpenseCategory = '';
        document.getElementById('expCategory').value = '';
    } else if (selectedExpenseCategory && !appState.categories.some(c => c.name === selectedExpenseCategory && (editingExpenseId || c.isActive !== false))) {
        selectedExpenseCategory = '';
        document.getElementById('expCategory').value = '';
    }

    closeCategorySettingsModal();
    renderAll();
    showToast('Perubahan pengaturan & budget kategori berhasil disimpan ke database!', 'success');
}

function renderCategorySettingsList() {
    const container = document.getElementById('categorySettingsListContainer');
    const orderNote = document.getElementById('settingsOrderNote');
    updateSettingsTotalBudgetPreview();
    if (!container) return;

    const activeSettings = draftCategorySettings || appState.categorySettings;
    const sourceCategories = draftCategoryMetaList || appState.categories;
    const sortMode = activeSettings?.sortBy || 'manual';
    const isManualSort = sortMode === 'manual';

    if (orderNote) {
        orderNote.innerText = isManualSort
            ? 'Atur budget tiap kategori, geser urutan, atau aktif/nonaktifkan kategori, lalu klik Simpan Perubahan.'
            : 'Mode pengurutan otomatis sedang aktif. Ubah "Urutan Tampilan Kategori" ke Urutan Kustom untuk menggeser urutan manual.';
    }

    const displayCategories = getSortedCategories(sourceCategories, sortMode);
    const stats = getCategoryStatsMap();

    if (displayCategories.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-xs text-slate-400 border border-dashed border-slate-300 rounded-xl">Belum ada kategori. Klik + Tambah Kategori untuk membuat baru.</div>';
        return;
    }

    container.innerHTML = '';
    displayCategories.forEach((cat, idx) => {
        const stat = stats[cat.name] || { count: 0, total: 0 };
        const isActive = cat.isActive !== false;
        const canMoveUp = isManualSort && idx > 0;
        const canMoveDown = isManualSort && idx < displayCategories.length - 1;

        const row = document.createElement('div');
        row.className = `flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border transition ${isActive ? 'bg-white border-slate-200' : 'bg-slate-100/80 border-slate-200 opacity-75'}`;
        row.innerHTML = `
            <div class="flex items-start gap-3 min-w-0">
                <div class="flex flex-col gap-0.5 flex-shrink-0 pt-0.5">
                    <button type="button" onclick="moveCategoryOrder('${escapeHtml(cat.id)}', -1)" ${canMoveUp ? '' : 'disabled'} class="w-6 h-5 rounded border border-slate-200 bg-slate-50 hover:bg-slate-200 text-slate-600 text-[10px] flex items-center justify-center transition disabled:opacity-30 disabled:cursor-not-allowed" title="Geser Naik">
                        <i class="fa-solid fa-chevron-up"></i>
                    </button>
                    <button type="button" onclick="moveCategoryOrder('${escapeHtml(cat.id)}', 1)" ${canMoveDown ? '' : 'disabled'} class="w-6 h-5 rounded border border-slate-200 bg-slate-50 hover:bg-slate-200 text-slate-600 text-[10px] flex items-center justify-center transition disabled:opacity-30 disabled:cursor-not-allowed" title="Geser Turun">
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                </div>
                <span class="w-3.5 h-3.5 rounded-full flex-shrink-0 mt-1.5 ring-2 ring-white shadow-sm" style="background-color: ${escapeHtml(cat.color)};"></span>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-xs sm:text-sm text-slate-900">${escapeHtml(cat.name)}</span>
                        ${activeSettings.defaultCategory === cat.name ? '<span class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 text-[10px] font-bold">Default</span>' : ''}
                        ${!isActive ? '<span class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 text-[10px] font-semibold">Nonaktif</span>' : ''}
                    </div>
                    <div class="text-[11px] text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5">
                        <span>${stat.count} transaksi</span>
                        <span>•</span>
                        <span>Terpakai: <strong class="text-slate-700 font-mono">${formatRupiah(stat.total)}</strong></span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 flex-shrink-0 self-end sm:self-center">
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100" title="Budget kategori (Rp)">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide whitespace-nowrap">Budget Rp</span>
                    <input type="number" min="0" step="10000" data-category-budget-id="${escapeHtml(cat.id)}" value="${cat.budget > 0 ? cat.budget : ''}" placeholder="0" oninput="onCategoryDraftBudgetChange('${escapeHtml(cat.id)}', this.value)" class="w-28 bg-transparent text-xs font-bold font-mono text-slate-800 text-right focus:outline-none">
                </div>
                <button type="button" onclick="toggleCategoryActive('${escapeHtml(cat.id)}')" class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition flex items-center gap-1 ${isActive ? 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-200 hover:bg-slate-300 text-slate-700 border-slate-300'}" title="Aktif/Nonaktifkan Kategori">
                    <i class="fa-solid ${isActive ? 'fa-toggle-on' : 'fa-toggle-off'}"></i>
                    <span>${isActive ? 'Aktif' : 'Nonaktif'}</span>
                </button>
                <button type="button" onclick="openCategoryModal('${escapeHtml(cat.id)}')" class="px-2.5 py-1.5 bg-white hover:bg-blue-50 text-blue-600 border border-slate-200 hover:border-blue-200 rounded-lg text-[11px] font-semibold transition flex items-center gap-1" title="Edit Kategori">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>Edit</span>
                </button>
                <button type="button" onclick="openDeleteCategoryModal('${escapeHtml(cat.id)}')" class="px-2.5 py-1.5 bg-white hover:bg-rose-50 text-rose-600 border border-slate-200 hover:border-rose-200 rounded-lg text-[11px] font-semibold transition flex items-center gap-1" title="Hapus Kategori">
                    <i class="fa-solid fa-trash-can"></i>
                    <span>Hapus</span>
                </button>
            </div>
        `;
        container.appendChild(row);
    });
}

function moveCategoryOrder(categoryId, direction) {
    const targetList = draftCategoryMetaList || appState.categories;
    const sortMode = (draftCategorySettings || appState.categorySettings)?.sortBy || 'manual';
    if (sortMode !== 'manual') return;

    const idx = targetList.findIndex(c => String(c.id) === String(categoryId));
    const targetIdx = idx + direction;
    if (idx === -1 || targetIdx < 0 || targetIdx >= targetList.length) return;

    const temp = targetList[idx];
    targetList[idx] = targetList[targetIdx];
    targetList[targetIdx] = temp;

    if (draftCategoryMetaList) {
        categorySettingsDirty = true;
        updateSettingsDirtyIndicator();
        renderCategorySettingsList();
    }
}

function toggleCategoryActive(categoryId) {
    const targetList = draftCategoryMetaList || appState.categories;
    const targetSettings = draftCategorySettings || appState.categorySettings;
    const cat = targetList.find(c => String(c.id) === String(categoryId));
    if (!cat) return;

    cat.isActive = !(cat.isActive !== false);
    if (!cat.isActive && targetSettings.defaultCategory === cat.name) {
        targetSettings.defaultCategory = '';
    }

    if (draftCategoryMetaList) {
        categorySettingsDirty = true;
        syncCategorySettingsModalUI();
        renderCategorySettingsList();
        updateSettingsDirtyIndicator();
    }
}

async function resetCategoriesToDefault() {
    const confirmed = confirm('Kembalikan daftar dan pengaturan kategori ke bawaan awal? Transaksi biaya yang menggunakan kategori di luar bawaan akan ditandai "Belum Dikategorikan".');
    if (!confirmed) return;

    try {
        await apiRequest(ROUTES.categoriesReset, 'POST');
    } catch (_) {
        return;
    }

    selectedExpenseCategory = '';
    document.getElementById('expCategory').value = '';

    if (draftCategoryMetaList !== null) {
        draftCategorySettings = { ...appState.categorySettings };
        draftCategoryMetaList = appState.categories.map(c => ({ ...c }));
        categorySettingsDirty = false;
        syncCategorySettingsModalUI();
        renderCategorySettingsList();
        updateSettingsTotalBudgetPreview();
        updateSettingsDirtyIndicator();
    }

    renderAll();
    showToast('Pengaturan dan daftar kategori dikembalikan ke bawaan di database.', 'success');
}

// Buka Modal Tambah / Edit Kategori
function openCategoryModal(categoryId = null, autoSelectInExpense = false) {
    selectNewCategoryInExpenseForm = autoSelectInExpense && !categoryId;

    const modal = document.getElementById('categoryModal');
    const titleEl = document.getElementById('categoryModalTitle');
    const submitBtn = document.getElementById('catSubmitBtn');
    const editIdInput = document.getElementById('catEditId');
    const nameInput = document.getElementById('catName');
    const descInput = document.getElementById('catDesc');
    const budgetInput = document.getElementById('catBudget');
    const activeInput = document.getElementById('catActive');
    const colorInput = document.getElementById('catColor');

    if (categoryId) {
        const cat = appState.categories.find(c => String(c.id) === String(categoryId));
        if (!cat) return;

        editIdInput.value = cat.id;
        nameInput.value = cat.name;
        descInput.value = cat.desc || '';
        budgetInput.value = cat.budget > 0 ? cat.budget : '';
        activeInput.checked = cat.isActive !== false;
        colorInput.value = cat.color || '#0284c7';
        syncColorSwatchSelection(colorInput.value);

        titleEl.innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-400"></i> <span>Edit Kategori</span>';
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Simpan Perubahan</span>';
    } else {
        editIdInput.value = '';
        nameInput.value = '';
        descInput.value = '';
        budgetInput.value = '';
        activeInput.checked = true;

        const usedColors = new Set(appState.categories.map(c => c.color.toLowerCase()));
        const nextColor = PRESET_CATEGORY_COLORS.find(c => !usedColors.has(c.toLowerCase()))
            || PRESET_CATEGORY_COLORS[appState.categories.length % PRESET_CATEGORY_COLORS.length];
        colorInput.value = nextColor;
        syncColorSwatchSelection(nextColor);

        titleEl.innerHTML = '<i class="fa-solid fa-tag text-blue-400"></i> <span>Tambah Kategori Baru</span>';
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> <span>Simpan Kategori</span>';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => nameInput.focus(), 50);
}

function closeCategoryModal() {
    const modal = document.getElementById('categoryModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    selectNewCategoryInExpenseForm = false;
}

// Submit Tambah / Edit Kategori (Simpan ke SQL via Laravel)
async function handleCategorySubmit(e) {
    e.preventDefault();
    const editId = document.getElementById('catEditId').value;
    const newName = document.getElementById('catName').value.trim();
    const newDesc = document.getElementById('catDesc').value.trim();
    const newBudget = Math.max(0, parseFloat(document.getElementById('catBudget').value) || 0);
    const newActive = document.getElementById('catActive').checked;
    const newColor = document.getElementById('catColor').value || '#0284c7';

    if (!newName || newName.toLowerCase() === UNCATEGORIZED_LABEL.toLowerCase()) {
        showToast('Masukkan nama kategori yang valid!', 'warning');
        return;
    }

    const duplicate = appState.categories.find(
        c => String(c.id) !== String(editId) && c.name.toLowerCase() === newName.toLowerCase()
    );
    if (duplicate) {
        showToast(`Kategori "${duplicate.name}" sudah ada!`, 'warning');
        return;
    }

    if (editId) {
        const target = appState.categories.find(c => String(c.id) === String(editId));
        if (!target) return;

        const oldName = target.name;
        const wasFilterSelected = document.getElementById('filterCategory').value === oldName;
        const wasExpCatSelected = selectedExpenseCategory === oldName;

        try {
            await apiRequest(`${ROUTES.categoriesStore}/${encodeURIComponent(editId)}`, 'PUT', {
                name: newName,
                desc: newDesc,
                color: newColor,
                budget: newBudget,
                isActive: newActive
            });
        } catch (_) {
            return;
        }

        if (oldName !== newName) {
            Object.keys(pendingExpenseCategories).forEach(expId => {
                if (pendingExpenseCategories[expId] === oldName) {
                    pendingExpenseCategories[expId] = newName;
                }
            });
            if (wasExpCatSelected) {
                selectedExpenseCategory = (newActive || editingExpenseId) ? newName : '';
                document.getElementById('expCategory').value = selectedExpenseCategory;
            }
        } else if (!newActive) {
            if (wasExpCatSelected && !editingExpenseId) {
                selectedExpenseCategory = '';
                document.getElementById('expCategory').value = '';
            }
        }

        syncDraftCategorySettingsWithAppState();
        renderAll();
        if (oldName !== newName && wasFilterSelected) {
            document.getElementById('filterCategory').value = newName;
            renderExpensesTable();
        }
        closeCategoryModal();
        showToast(`Kategori "${newName}" berhasil diperbarui di database!`, 'success');
    } else {
        let result;
        try {
            result = await apiRequest(ROUTES.categoriesStore, 'POST', {
                name: newName,
                desc: newDesc,
                color: newColor,
                budget: newBudget,
                isActive: newActive
            });
        } catch (_) {
            return;
        }

        if (selectNewCategoryInExpenseForm && newActive) {
            selectedExpenseCategory = result.categoryName || newName;
            document.getElementById('expCategory').value = selectedExpenseCategory;
        }

        syncDraftCategorySettingsWithAppState();
        renderAll();
        closeCategoryModal();
        showToast(`Kategori "${newName}" berhasil ditambahkan ke database!`, 'success');
    }
}

// Buka Modal Konfirmasi Hapus Kategori
function openDeleteCategoryModal(categoryId) {
    const cat = appState.categories.find(c => String(c.id) === String(categoryId));
    if (!cat) return;

    pendingDeleteCategoryId = String(cat.id);
    const usedExpenses = appState.expenses.filter(e => e.category === cat.name);
    const otherCategories = appState.categories.filter(c => String(c.id) !== String(cat.id));

    document.getElementById('deleteCatColorDot').style.backgroundColor = cat.color || '#64748b';
    document.getElementById('deleteCatNameDisplay').innerText = cat.name;
    document.getElementById('deleteCatDescDisplay').innerText = (cat.desc && cat.desc !== cat.name) ? cat.desc : 'Kategori biaya project';

    const msgEl = document.getElementById('deleteCatMessage');
    const reassignWrapper = document.getElementById('deleteCatReassignWrapper');
    const reassignSelect = document.getElementById('deleteCatReassignSelect');
    const blockedAlert = document.getElementById('deleteCatBlockedAlert');
    const confirmBtn = document.getElementById('confirmDeleteCatBtn');

    reassignWrapper.classList.add('hidden');
    blockedAlert.classList.add('hidden');
    confirmBtn.disabled = false;
    confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');

    if (usedExpenses.length === 0) {
        msgEl.innerHTML = `Apakah Anda yakin ingin menghapus kategori <strong>"${escapeHtml(cat.name)}"</strong>? Belum ada transaksi biaya yang menggunakan kategori ini.`;
        confirmBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> <span>Ya, Hapus</span>';
    } else if (otherCategories.length > 0) {
        msgEl.innerHTML = `Kategori <strong>"${escapeHtml(cat.name)}"</strong> saat ini digunakan pada <strong>${usedExpenses.length} transaksi biaya</strong>. Pilih kategori pengganti agar data transaksi tetap rapi:`;
        reassignSelect.innerHTML = otherCategories.map(c =>
            `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}</option>`
        ).join('');
        reassignWrapper.classList.remove('hidden');
        confirmBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> <span>Pindahkan & Hapus</span>';
    } else {
        msgEl.innerHTML = `Kategori <strong>"${escapeHtml(cat.name)}"</strong> adalah satu-satunya kategori yang tersedia dan sedang digunakan oleh <strong>${usedExpenses.length} transaksi biaya</strong>.`;
        blockedAlert.classList.remove('hidden');
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
        confirmBtn.innerHTML = '<i class="fa-solid fa-ban"></i> <span>Tidak Dapat Dihapus</span>';
    }

    const modal = document.getElementById('deleteCategoryModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeDeleteCategoryModal() {
    const modal = document.getElementById('deleteCategoryModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pendingDeleteCategoryId = null;
}

async function confirmDeleteCategory() {
    if (!pendingDeleteCategoryId) return;
    const cat = appState.categories.find(c => String(c.id) === String(pendingDeleteCategoryId));
    if (!cat) {
        closeDeleteCategoryModal();
        return;
    }

    let replacementName = '';
    let replacementId = null;
    const usedExpenses = appState.expenses.filter(e => e.category === cat.name);
    if (usedExpenses.length > 0) {
        replacementName = document.getElementById('deleteCatReassignSelect').value;
        if (!replacementName) {
            showToast('Pilih kategori pengganti terlebih dahulu!', 'warning');
            return;
        }
        const repCat = appState.categories.find(c => c.name === replacementName && String(c.id) !== String(cat.id));
        replacementId = repCat ? repCat.id : null;
    }

    try {
        await apiRequest(`${ROUTES.categoriesStore}/${encodeURIComponent(cat.id)}`, 'DELETE', {
            replacementCategoryId: replacementId,
            replacementCategoryName: replacementName
        });
    } catch (_) {
        return;
    }

    Object.keys(pendingExpenseCategories).forEach(expId => {
        if (pendingExpenseCategories[expId] === cat.name) {
            pendingExpenseCategories[expId] = replacementName || UNCATEGORIZED_LABEL;
        }
    });

    const filterEl = document.getElementById('filterCategory');
    if (filterEl.value === cat.name) {
        filterEl.value = 'ALL';
    }
    if (selectedExpenseCategory === cat.name) {
        selectedExpenseCategory = '';
        document.getElementById('expCategory').value = '';
    }

    syncDraftCategorySettingsWithAppState();
    renderAll();
    closeDeleteCategoryModal();
    showToast(`Kategori "${cat.name}" telah dihapus dari database.`, 'warning');
}

// Render Chart.js
function renderChart() {
    const canvas = document.getElementById('categoryChart');
    if (!canvas || typeof Chart === 'undefined') return;
    const ctx = canvas.getContext('2d');
    const sortedCategories = getSortedCategories();
    const stats = getCategoryStatsMap();
    const uncatTotal = stats[UNCATEGORIZED_LABEL]?.total || 0;

    let labels = sortedCategories.map(c => c.name);
    let colors = sortedCategories.map(c => c.color || '#64748b');
    let totals = sortedCategories.map(c => stats[c.name]?.total || 0);

    if (uncatTotal > 0) {
        labels.push(UNCATEGORIZED_LABEL);
        colors.push('#cbd5e1');
        totals.push(uncatTotal);
    }

    if (labels.length === 0) {
        labels = ['Belum ada kategori'];
        colors = ['#e2e8f0'];
        totals = [0];
    }

    if (categoryChartInstance) {
        categoryChartInstance.destroy();
    }

    categoryChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: totals,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: { size: 10, family: 'Inter' },
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.label}: ${formatRupiah(context.raw)}`;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

// Auto Calc Total Biaya di Form Tab 3
function calcTotalBiaya() {
    const qty = parseFloat(document.getElementById('expQty').value) || 0;
    const price = parseFloat(document.getElementById('expPrice').value) || 0;
    const total = qty * price;
    document.getElementById('expTotalDisplay').value = formatRupiah(total);
}

// Tab Form Switcher
function switchFormTab(tab) {
    document.getElementById('formInvestor').classList.add('hidden');
    document.getElementById('formKasbon').classList.add('hidden');
    document.getElementById('formBiaya').classList.add('hidden');

    document.getElementById('tabBtnInvestor').className = "px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition";
    document.getElementById('tabBtnKasbon').className = "px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition";
    document.getElementById('tabBtnBiaya').className = "px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition";

    if (tab === 'investor') {
        document.getElementById('formInvestor').classList.remove('hidden');
        document.getElementById('tabBtnInvestor').className = "px-3 py-1.5 rounded-lg text-xs font-semibold transition bg-blue-600 text-white shadow-sm";
    } else if (tab === 'kasbon') {
        document.getElementById('formKasbon').classList.remove('hidden');
        document.getElementById('tabBtnKasbon').className = "px-3 py-1.5 rounded-lg text-xs font-semibold transition bg-blue-600 text-white shadow-sm";
    } else if (tab === 'biaya') {
        document.getElementById('formBiaya').classList.remove('hidden');
        document.getElementById('tabBtnBiaya').className = "px-3 py-1.5 rounded-lg text-xs font-semibold transition bg-amber-600 text-white shadow-sm";
    }
}

// Tab View Table Switcher
function switchViewTab(view) {
    document.getElementById('viewExpensesTable').classList.add('hidden');
    document.getElementById('viewKasbonTable').classList.add('hidden');
    document.getElementById('viewInvestorTable').classList.add('hidden');
    document.getElementById('filterContainer').classList.add('hidden');

    document.getElementById('viewBtnExpenses').className = "px-3.5 py-1.5 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900";
    document.getElementById('viewBtnKasbon').className = "px-3.5 py-1.5 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900";
    document.getElementById('viewBtnInvestor').className = "px-3.5 py-1.5 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900";

    if (view === 'expenses') {
        document.getElementById('viewExpensesTable').classList.remove('hidden');
        document.getElementById('filterContainer').classList.remove('hidden');
        document.getElementById('viewBtnExpenses').className = "px-3.5 py-1.5 rounded-lg text-xs font-bold transition bg-white text-slate-900 shadow-sm";
    } else if (view === 'kasbon') {
        document.getElementById('viewKasbonTable').classList.remove('hidden');
        document.getElementById('viewBtnKasbon').className = "px-3.5 py-1.5 rounded-lg text-xs font-bold transition bg-white text-slate-900 shadow-sm";
    } else if (view === 'investor') {
        document.getElementById('viewInvestorTable').classList.remove('hidden');
        document.getElementById('viewBtnInvestor').className = "px-3.5 py-1.5 rounded-lg text-xs font-bold transition bg-white text-slate-900 shadow-sm";
    }
}

// Submit & Edit Handlers (Investor, Kasbon, Biaya Lapangan)
function setFormEditModeUI({ isEditing, bannerId, bannerTextId, bannerMsg, cancelBtnId, submitBtnId, editHtml, addHtml, addClass, editClass }) {
    const banner = document.getElementById(bannerId);
    const bannerText = document.getElementById(bannerTextId);
    const cancelBtn = document.getElementById(cancelBtnId);
    const submitBtn = document.getElementById(submitBtnId);

    if (banner) {
        banner.classList.toggle('hidden', !isEditing);
        banner.classList.toggle('flex', isEditing);
    }
    if (bannerText && isEditing && bannerMsg) {
        bannerText.innerText = bannerMsg;
    }
    if (cancelBtn) {
        cancelBtn.classList.toggle('hidden', !isEditing);
        cancelBtn.classList.toggle('inline-flex', isEditing);
    }
    if (submitBtn) {
        submitBtn.innerHTML = isEditing ? editHtml : addHtml;
        submitBtn.className = isEditing ? editClass : addClass;
    }
}

function scrollToTransactionForm() {
    const section = document.getElementById('transactionFormSection');
    if (section && typeof section.scrollIntoView === 'function') {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function applyDefaultExpenseCategory() {
    const defaultCat = (appState.categorySettings && appState.categorySettings.defaultCategory) || '';
    const hasActiveDefault = defaultCat && appState.categories.some(c => c.name === defaultCat && c.isActive !== false);
    selectedExpenseCategory = hasActiveDefault ? defaultCat : '';
    const hiddenCatEl = document.getElementById('expCategory');
    if (hiddenCatEl) hiddenCatEl.value = selectedExpenseCategory;
}

function startEditInvestor(id) {
    const item = appState.investorFunds.find(i => String(i.id) === String(id));
    if (!item) return;

    editingInvestorId = String(item.id);
    switchFormTab('investor');
    document.getElementById('invDate').value = item.date || '';
    document.getElementById('invInvestor').value = item.investor || '';
    document.getElementById('invAmount').value = item.amount ?? '';
    document.getElementById('invDesc').value = (item.desc && item.desc !== '-') ? item.desc : '';

    setFormEditModeUI({
        isEditing: true,
        bannerId: 'investorEditBanner',
        bannerTextId: 'investorEditBannerText',
        bannerMsg: `Mengedit Dana Investor "${item.investor}" (${formatRupiah(item.amount)}). Ubah lalu klik Simpan Perubahan.`,
        cancelBtnId: 'invCancelEditBtn',
        submitBtnId: 'invSubmitBtn',
        editHtml: '<i class="fa-solid fa-check"></i> <span>Simpan Perubahan</span>',
        addHtml: '<i class="fa-solid fa-plus-circle"></i> <span>Simpan Uang Masuk Investor</span>',
        addClass: 'px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm',
        editClass: 'px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm ring-2 ring-blue-300'
    });

    renderInvestorTable();
    scrollToTransactionForm();
    document.getElementById('invInvestor').focus();
}

function cancelInvestorEdit(shouldReset = true) {
    editingInvestorId = null;
    if (shouldReset) {
        const form = document.getElementById('formInvestor');
        if (form && typeof form.reset === 'function') form.reset();
        setDefaultDates();
    }
    setFormEditModeUI({
        isEditing: false,
        bannerId: 'investorEditBanner',
        bannerTextId: 'investorEditBannerText',
        cancelBtnId: 'invCancelEditBtn',
        submitBtnId: 'invSubmitBtn',
        editHtml: '<i class="fa-solid fa-check"></i> <span>Simpan Perubahan</span>',
        addHtml: '<i class="fa-solid fa-plus-circle"></i> <span>Simpan Uang Masuk Investor</span>',
        addClass: 'px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm',
        editClass: 'px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm ring-2 ring-blue-300'
    });
    renderInvestorTable();
}

async function handleInvestorSubmit(e) {
    e.preventDefault();
    const payload = {
        date: document.getElementById('invDate').value,
        investor: document.getElementById('invInvestor').value.trim(),
        amount: parseFloat(document.getElementById('invAmount').value),
        desc: document.getElementById('invDesc').value.trim() || '-'
    };

    if (editingInvestorId) {
        try {
            await apiRequest(`${ROUTES.investorsStore}/${encodeURIComponent(editingInvestorId)}`, 'PUT', payload);
        } catch (_) {
            return;
        }
        cancelInvestorEdit(true);
        renderAll();
        showToast('Perubahan data dana investor berhasil disimpan ke database!', 'success');
        return;
    }

    try {
        await apiRequest(ROUTES.investorsStore, 'POST', payload);
    } catch (_) {
        return;
    }

    renderAll();
    e.target.reset();
    setDefaultDates();
    showToast('Uang Masuk Investor Berhasil Dicatat ke Database!', 'success');
}

function startEditKasbon(id) {
    const item = appState.kasbonList.find(k => String(k.id) === String(id));
    if (!item) return;

    editingKasbonId = String(item.id);
    switchFormTab('kasbon');
    document.getElementById('kasDate').value = item.date || '';
    document.getElementById('kasPelaksana').value = item.pelaksana || 'Moh. Rengga';
    document.getElementById('kasAmount').value = item.amount ?? '';
    document.getElementById('kasDesc').value = (item.desc && item.desc !== '-') ? item.desc : '';

    setFormEditModeUI({
        isEditing: true,
        bannerId: 'kasbonEditBanner',
        bannerTextId: 'kasbonEditBannerText',
        bannerMsg: `Mengedit Pencairan Kasbon ${item.pelaksana} (${formatRupiah(item.amount)}). Ubah lalu klik Simpan Perubahan.`,
        cancelBtnId: 'kasCancelEditBtn',
        submitBtnId: 'kasSubmitBtn',
        editHtml: '<i class="fa-solid fa-check"></i> <span>Simpan Perubahan</span>',
        addHtml: '<i class="fa-solid fa-paper-plane"></i> <span>Simpan Pencairan Kasbon</span>',
        addClass: 'px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm',
        editClass: 'px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm ring-2 ring-emerald-300'
    });

    renderKasbonTable();
    scrollToTransactionForm();
    document.getElementById('kasAmount').focus();
}

function cancelKasbonEdit(shouldReset = true) {
    editingKasbonId = null;
    if (shouldReset) {
        const form = document.getElementById('formKasbon');
        if (form && typeof form.reset === 'function') form.reset();
        setDefaultDates();
    }
    setFormEditModeUI({
        isEditing: false,
        bannerId: 'kasbonEditBanner',
        bannerTextId: 'kasbonEditBannerText',
        cancelBtnId: 'kasCancelEditBtn',
        submitBtnId: 'kasSubmitBtn',
        editHtml: '<i class="fa-solid fa-check"></i> <span>Simpan Perubahan</span>',
        addHtml: '<i class="fa-solid fa-paper-plane"></i> <span>Simpan Pencairan Kasbon</span>',
        addClass: 'px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm',
        editClass: 'px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm ring-2 ring-emerald-300'
    });
    renderKasbonTable();
}

async function handleKasbonSubmit(e) {
    e.preventDefault();
    const payload = {
        date: document.getElementById('kasDate').value,
        pelaksana: document.getElementById('kasPelaksana').value,
        amount: parseFloat(document.getElementById('kasAmount').value),
        desc: document.getElementById('kasDesc').value.trim() || '-'
    };

    if (editingKasbonId) {
        try {
            await apiRequest(`${ROUTES.kasbonsStore}/${encodeURIComponent(editingKasbonId)}`, 'PUT', payload);
        } catch (_) {
            return;
        }
        cancelKasbonEdit(true);
        renderAll();
        showToast('Perubahan data kasbon berhasil disimpan ke database!', 'success');
        return;
    }

    try {
        await apiRequest(ROUTES.kasbonsStore, 'POST', payload);
    } catch (_) {
        return;
    }

    renderAll();
    e.target.reset();
    setDefaultDates();
    showToast(`Kasbon dicairkan ke ${payload.pelaksana}!`, 'success');
}

function startEditExpense(id) {
    const item = appState.expenses.find(e => String(e.id) === String(id));
    if (!item) return;

    editingExpenseId = String(item.id);
    switchFormTab('biaya');

    document.getElementById('expDate').value = item.date || '';
    document.getElementById('expPelaksana').value = item.pelaksana || 'Moh. Rengga';
    document.getElementById('expPayMethod').value = item.payMethod || 'Tunai Kasbon';
    document.getElementById('expTitle').value = item.title || '';
    document.getElementById('expQty').value = item.qty ?? 1;
    document.getElementById('expUnit').value = item.unit || 'sak';
    document.getElementById('expPrice').value = item.price ?? 0;
    document.getElementById('expVendor').value = (item.vendor && item.vendor !== '-') ? item.vendor : '';
    document.getElementById('expProof').value = (item.proof && item.proof !== '-') ? item.proof : '';
    document.getElementById('expValidation').value = item.validation || 'Valid';
    calcTotalBiaya();

    const hasCat = item.category && item.category !== UNCATEGORIZED_LABEL &&
        appState.categories.some(c => c.name === item.category);
    selectedExpenseCategory = hasCat ? item.category : '';
    const hiddenCatEl = document.getElementById('expCategory');
    if (hiddenCatEl) hiddenCatEl.value = selectedExpenseCategory;

    setFormEditModeUI({
        isEditing: true,
        bannerId: 'expenseEditBanner',
        bannerTextId: 'expenseEditBannerText',
        bannerMsg: `Mengedit Biaya "${item.title}" (${formatRupiah(getExpenseTotal(item))}). Ubah lalu klik Simpan Perubahan.`,
        cancelBtnId: 'expCancelEditBtn',
        submitBtnId: 'expSubmitBtn',
        editHtml: '<i class="fa-solid fa-check"></i> <span>Simpan Perubahan</span>',
        addHtml: '<i class="fa-solid fa-receipt"></i> <span>Simpan Pemakaian Biaya & Potong Kasbon</span>',
        addClass: 'px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 shadow-sm flex-shrink-0',
        editClass: 'px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 shadow-sm ring-2 ring-emerald-300 flex-shrink-0'
    });

    renderExpenseRightCategoryList();
    renderExpensesTable();
    scrollToTransactionForm();
    document.getElementById('expTitle').focus();
}

function cancelExpenseEdit(shouldReset = true) {
    editingExpenseId = null;
    if (shouldReset) {
        const form = document.getElementById('formBiaya');
        if (form && typeof form.reset === 'function') form.reset();
        setDefaultDates();
        document.getElementById('expTotalDisplay').value = "Rp 0";
        applyDefaultExpenseCategory();
    }
    setFormEditModeUI({
        isEditing: false,
        bannerId: 'expenseEditBanner',
        bannerTextId: 'expenseEditBannerText',
        cancelBtnId: 'expCancelEditBtn',
        submitBtnId: 'expSubmitBtn',
        editHtml: '<i class="fa-solid fa-check"></i> <span>Simpan Perubahan</span>',
        addHtml: '<i class="fa-solid fa-receipt"></i> <span>Simpan Pemakaian Biaya & Potong Kasbon</span>',
        addClass: 'px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 shadow-sm flex-shrink-0',
        editClass: 'px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 shadow-sm ring-2 ring-emerald-300 flex-shrink-0'
    });
    renderExpenseRightCategoryList();
    renderExpensesTable();
}

async function handleExpenseSubmit(e) {
    e.preventDefault();

    const hiddenCatEl = document.getElementById('expCategory');
    const rawCategory = (selectedExpenseCategory || (hiddenCatEl ? hiddenCatEl.value : '') || '').trim();
    const isValidCategory = rawCategory && appState.categories.some(c => c.name === rawCategory);
    const categoryValue = isValidCategory ? rawCategory : UNCATEGORIZED_LABEL;

    const qty = parseFloat(document.getElementById('expQty').value) || 0;
    const price = parseFloat(document.getElementById('expPrice').value) || 0;

    const payload = {
        date: document.getElementById('expDate').value,
        pelaksana: document.getElementById('expPelaksana').value,
        category: categoryValue,
        payMethod: document.getElementById('expPayMethod').value,
        title: document.getElementById('expTitle').value.trim(),
        qty: qty,
        unit: document.getElementById('expUnit').value,
        price: price,
        vendor: document.getElementById('expVendor').value.trim() || '-',
        proof: document.getElementById('expProof').value.trim() || '-',
        validation: document.getElementById('expValidation').value
    };

    if (editingExpenseId) {
        const editedId = editingExpenseId;
        try {
            await apiRequest(`${ROUTES.expensesStore}/${encodeURIComponent(editedId)}`, 'PUT', payload);
        } catch (_) {
            return;
        }
        delete pendingExpenseCategories[editedId];
        lastAddedExpenseId = editedId;
        cancelExpenseEdit(true);
        renderAll();

        if (categoryValue === UNCATEGORIZED_LABEL) {
            showToast('Perubahan disimpan. Anda dapat memilih kategorinya di tabel.', 'warning');
            return;
        }
        if (!notifyExpenseCategoryBudget(categoryValue, 'diperbarui')) {
            showToast(`Perubahan pemakaian biaya "${payload.title}" berhasil disimpan ke database!`, 'success');
        }
        return;
    }

    let result;
    try {
        result = await apiRequest(ROUTES.expensesStore, 'POST', payload);
    } catch (_) {
        return;
    }

    lastAddedExpenseId = String(result.id);
    e.target.reset();
    setDefaultDates();
    document.getElementById('expTotalDisplay').value = "Rp 0";
    applyDefaultExpenseCategory();

    renderAll();

    if (categoryValue === UNCATEGORIZED_LABEL) {
        showToast('Pengeluaran dicatat! Pilih kategorinya di tabel lalu klik Simpan Perubahan.', 'warning');
        return;
    }

    if (!notifyExpenseCategoryBudget(categoryValue, 'dicatat')) {
        showToast(`Pemakaian biaya berhasil dicatat ke kategori "${categoryValue}"!`, 'success');
    }
}

// Render Tables & Apply Filters
function applyFilters() {
    renderExpensesTable();
}

function renderExpensesTable() {
    const tbody = document.getElementById('expenseTableBody');
    tbody.innerHTML = '';

    const pelaksanaFilter = document.getElementById('filterPelaksana').value;
    const categoryFilter = document.getElementById('filterCategory').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const searchFilter = document.getElementById('filterSearch').value.toLowerCase();

    const filtered = appState.expenses.filter(item => {
        const matchP = pelaksanaFilter === 'ALL' || item.pelaksana === pelaksanaFilter;
        const matchC = categoryFilter === 'ALL' || item.category === categoryFilter;
        const matchS = statusFilter === 'ALL' || item.validation === statusFilter;
        const matchSearch = (item.title || '').toLowerCase().includes(searchFilter) ||
                            (item.vendor || '').toLowerCase().includes(searchFilter) ||
                            (item.proof || '').toLowerCase().includes(searchFilter);
        return matchP && matchC && matchS && matchSearch;
    });

    const colorByCategoryName = {};
    const sortedCategories = getSortedCategories();
    const knownCategoryNames = new Set(sortedCategories.map(c => c.name));
    appState.categories.forEach(c => {
        colorByCategoryName[c.name] = c.color || '#64748b';
    });

    let filteredTotal = 0;

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="12" class="p-6 text-center text-slate-400 italic">Belum ada rincian pemakaian biaya yang cocok dengan filter.</td></tr>`;
    } else {
        filtered.forEach((item, index) => {
            const rowTotal = getExpenseTotal(item);
            filteredTotal += rowTotal;

            // Pelaksana Color Badge
            const pBadge = item.pelaksana === 'Moh. Rengga'
                ? `<span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded">Rengga</span>`
                : `<span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 rounded">Ashabil</span>`;

            // Validation Status Badge
            let valBadge = '';
            if (item.validation === 'Valid') {
                valBadge = `<button onclick="toggleValidation('${escapeHtml(item.id)}')" title="Klik untuk ubah" class="bg-emerald-100 hover:bg-emerald-200 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px] transition"><i class="fa-solid fa-check mr-1"></i>Valid</button>`;
            } else if (item.validation === 'Perlu Nota') {
                valBadge = `<button onclick="toggleValidation('${escapeHtml(item.id)}')" title="Klik untuk ubah" class="bg-amber-100 hover:bg-amber-200 text-amber-800 font-bold px-2 py-0.5 rounded text-[10px] transition"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Perlu Nota</button>`;
            } else {
                valBadge = `<button onclick="toggleValidation('${escapeHtml(item.id)}')" title="Klik untuk ubah" class="bg-rose-100 hover:bg-rose-200 text-rose-800 font-bold px-2 py-0.5 rounded text-[10px] transition"><i class="fa-solid fa-xmark mr-1"></i>Revisi</button>`;
            }

            const itemId = String(item.id);
            const savedCat = (item.category || '').trim() || UNCATEGORIZED_LABEL;
            const hasPendingCat = Object.prototype.hasOwnProperty.call(pendingExpenseCategories, itemId);
            const displayCat = hasPendingCat ? pendingExpenseCategories[itemId] : savedCat;
            const isDirtyCat = hasPendingCat && displayCat !== savedCat;
            const isUncategorized = displayCat === UNCATEGORIZED_LABEL;
            const isJustAdded = itemId === String(lastAddedExpenseId);
            const isEditingRow = itemId === String(editingExpenseId);
            const catColor = isUncategorized ? '#f59e0b' : (colorByCategoryName[displayCat] || '#64748b');

            let catOptionsHtml = `<option value="${escapeHtml(UNCATEGORIZED_LABEL)}" ${isUncategorized ? 'selected' : ''}>${escapeHtml(UNCATEGORIZED_LABEL)}</option>`;
            sortedCategories.forEach(cat => {
                if (cat.isActive === false && displayCat !== cat.name) return;
                const optLabel = cat.isActive === false ? `${cat.name} (Nonaktif)` : cat.name;
                catOptionsHtml += `<option value="${escapeHtml(cat.name)}" ${displayCat === cat.name ? 'selected' : ''}>${escapeHtml(optLabel)}</option>`;
            });
            if (!isUncategorized && !knownCategoryNames.has(displayCat)) {
                catOptionsHtml += `<option value="${escapeHtml(displayCat)}" selected>${escapeHtml(displayCat)}</option>`;
            }

            const selectClass = isDirtyCat
                ? 'text-xs font-bold border-2 border-emerald-500 bg-white text-slate-900 rounded px-2 py-1 focus:ring-2 focus:ring-emerald-500 outline-none cursor-pointer max-w-[165px]'
                : (isUncategorized
                    ? 'text-xs font-bold border border-amber-400 bg-amber-50 text-amber-800 rounded px-2 py-1 focus:ring-2 focus:ring-amber-500 outline-none cursor-pointer max-w-[165px]'
                    : 'text-xs font-semibold border border-slate-200 bg-white text-slate-700 hover:border-slate-300 rounded px-2 py-1 focus:ring-2 focus:ring-emerald-500 outline-none cursor-pointer max-w-[165px]');

            const saveBtnClass = isDirtyCat
                ? 'px-2 py-1 rounded text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition inline-flex items-center gap-1 whitespace-nowrap'
                : 'px-2 py-1 rounded text-[11px] font-semibold bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed inline-flex items-center gap-1 whitespace-nowrap';
            const saveBtnLabel = isDirtyCat ? 'Simpan Perubahan' : 'Simpan';

            const statusTag = isJustAdded
                ? (savedCat === UNCATEGORIZED_LABEL
                    ? `<span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 whitespace-nowrap">Tentukan</span>`
                    : `<span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 whitespace-nowrap">Baru</span>`)
                : '';

            const tr = document.createElement('tr');
            tr.className = isEditingRow
                ? "bg-amber-100/70 transition"
                : (isDirtyCat
                    ? "bg-emerald-50/70 hover:bg-emerald-100/60 transition"
                    : (savedCat === UNCATEGORIZED_LABEL
                        ? "bg-amber-50/80 hover:bg-amber-100/70 transition"
                        : (isJustAdded ? "bg-emerald-50/70 hover:bg-emerald-100/60 transition" : "hover:bg-slate-50 transition")));
            tr.innerHTML = `
                <td class="p-3 text-center text-slate-400 font-mono">${index + 1}</td>
                <td class="p-3 whitespace-nowrap">${escapeHtml(item.date)}</td>
                <td class="p-3 whitespace-nowrap">${pBadge}</td>
                <td class="p-3 font-semibold text-slate-600 whitespace-nowrap">
                    <div class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${escapeHtml(catColor)};"></span>
                        <select onchange="onExpenseRowCategoryChange('${escapeHtml(itemId)}', this.value)" aria-label="Pilih kategori untuk ${escapeHtml(item.title)}" class="${selectClass}">
                            ${catOptionsHtml}
                        </select>
                        <button type="button" onclick="saveExpenseRowCategory('${escapeHtml(itemId)}')" ${isDirtyCat ? '' : 'disabled'} class="${saveBtnClass}" title="${isDirtyCat ? 'Klik untuk menyimpan perubahan kategori' : 'Pilih kategori baru terlebih dahulu'}">
                            <i class="fa-solid fa-check"></i>
                            <span>${saveBtnLabel}</span>
                        </button>
                        ${statusTag}
                    </div>
                </td>
                <td class="p-3 font-medium text-slate-900">${escapeHtml(item.title)}</td>
                <td class="p-3 text-center whitespace-nowrap font-mono">${escapeHtml(item.qty)} ${escapeHtml(item.unit)}</td>
                <td class="p-3 text-right font-mono">${formatRupiah(item.price)}</td>
                <td class="p-3 text-right font-bold font-mono text-slate-900">${formatRupiah(rowTotal)}</td>
                <td class="p-3 text-slate-600">${escapeHtml(item.vendor)}</td>
                <td class="p-3 text-slate-500 font-mono text-[11px]">${escapeHtml(item.proof)}</td>
                <td class="p-3 text-center whitespace-nowrap">${valBadge}</td>
                <td class="p-3 text-center whitespace-nowrap">
                    <div class="inline-flex items-center gap-1">
                        <button type="button" onclick="startEditExpense('${escapeHtml(itemId)}')" class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-1.5 rounded transition" title="Edit Pemakaian Biaya">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button" onclick="deleteExpense('${escapeHtml(itemId)}')" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded transition" title="Hapus Pemakaian Biaya">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('tableTotalExpenses').innerText = formatRupiah(filteredTotal);
}

function onExpenseRowCategoryChange(expenseId, selectedCategory) {
    const key = String(expenseId);
    const expense = appState.expenses.find(e => String(e.id) === key);
    if (!expense) return;
    const normalizedSelected = (selectedCategory || '').trim() || UNCATEGORIZED_LABEL;
    const savedCat = (expense.category || '').trim() || UNCATEGORIZED_LABEL;
    if (normalizedSelected === savedCat) {
        delete pendingExpenseCategories[key];
    } else {
        pendingExpenseCategories[key] = normalizedSelected;
    }
    renderExpensesTable();
}

async function saveExpenseRowCategory(expenseId) {
    const key = String(expenseId);
    const expense = appState.expenses.find(e => String(e.id) === key);
    if (!expense) return;
    if (!Object.prototype.hasOwnProperty.call(pendingExpenseCategories, key)) {
        showToast('Pilih kategori baru terlebih dahulu sebelum menyimpan.', 'info');
        return;
    }
    const nextCat = pendingExpenseCategories[key];
    await assignExpenseCategory(key, nextCat);
}

function notifyExpenseCategoryBudget(categoryValue, verbLabel = 'dicatat') {
    if (!categoryValue || categoryValue === UNCATEGORIZED_LABEL) return false;
    const catObj = appState.categories.find(c => c.name === categoryValue);
    const catBudget = Number(catObj && catObj.budget) || 0;
    if (catBudget <= 0) return false;

    const catTotalSpend = appState.expenses
        .filter(item => item.category === categoryValue)
        .reduce((sum, item) => sum + getExpenseTotal(item), 0);
    const alertPercent = Number(appState.categorySettings && appState.categorySettings.budgetAlertPercent) || 80;
    const usagePercent = (catTotalSpend / catBudget) * 100;

    if (catTotalSpend > catBudget) {
        showToast(`Pengeluaran ${verbLabel} ke "${categoryValue}". Peringatan: total (${formatRupiah(catTotalSpend)}) melebihi batas ${formatRupiah(catBudget)}!`, 'warning');
        return true;
    }
    if (usagePercent >= alertPercent) {
        showToast(`Pengeluaran ${verbLabel} ke "${categoryValue}" (pemakaian anggaran ${Math.round(usagePercent)}%).`, 'warning');
        return true;
    }
    return false;
}

async function assignExpenseCategory(expenseId, newCategory) {
    const key = String(expenseId);
    const expense = appState.expenses.find(e => String(e.id) === key);
    if (!expense) return;

    const normalized = (newCategory || '').trim() || UNCATEGORIZED_LABEL;
    const expTitle = expense.title;

    try {
        await apiRequest(`${ROUTES.expensesStore}/${encodeURIComponent(key)}/category`, 'PATCH', {
            category: normalized
        });
    } catch (_) {
        return;
    }

    delete pendingExpenseCategories[key];
    if (String(lastAddedExpenseId) === key && normalized !== UNCATEGORIZED_LABEL) {
        lastAddedExpenseId = null;
    }

    renderAll();

    if (normalized === UNCATEGORIZED_LABEL) {
        showToast(`Kategori "${expTitle}" diubah menjadi ${UNCATEGORIZED_LABEL}.`, 'warning');
        return;
    }
    if (!notifyExpenseCategoryBudget(normalized, 'dipindahkan')) {
        showToast(`"${expTitle}" masuk ke kategori "${normalized}".`, 'success');
    }
}

function renderKasbonTable() {
    const tbody = document.getElementById('kasbonTableBody');
    tbody.innerHTML = '';
    let total = 0;

    if (appState.kasbonList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="p-6 text-center text-slate-400 italic">Belum ada riwayat pencairan kasbon Principal.</td></tr>`;
    } else {
        appState.kasbonList.forEach((item, index) => {
            total += Number(item.amount) || 0;
            const pBadge = item.pelaksana === 'Moh. Rengga'
                ? `<span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded">Moh. Rengga</span>`
                : `<span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 rounded">Moh. Ashabil</span>`;

            const itemId = String(item.id);
            const isEditingRow = itemId === String(editingKasbonId);
            const tr = document.createElement('tr');
            tr.className = isEditingRow ? "bg-blue-50/80 transition" : "hover:bg-slate-50 transition";
            tr.innerHTML = `
                <td class="p-3 text-center text-slate-400 font-mono">${index + 1}</td>
                <td class="p-3 whitespace-nowrap">${escapeHtml(item.date)}</td>
                <td class="p-3">${pBadge}</td>
                <td class="p-3 font-medium text-slate-800">${escapeHtml(item.desc)}</td>
                <td class="p-3 text-right font-bold font-mono text-blue-700">${formatRupiah(item.amount)}</td>
                <td class="p-3 text-center whitespace-nowrap">
                    <div class="inline-flex items-center gap-1">
                        <button type="button" onclick="startEditKasbon('${escapeHtml(itemId)}')" class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-1.5 rounded transition" title="Edit Kasbon">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button" onclick="deleteKasbon('${escapeHtml(itemId)}')" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded transition" title="Hapus Kasbon">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('tableTotalKasbon').innerText = formatRupiah(total);
}

function renderInvestorTable() {
    const tbody = document.getElementById('investorTableBody');
    tbody.innerHTML = '';
    let total = 0;

    if (appState.investorFunds.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="p-6 text-center text-slate-400 italic">Belum ada riwayat uang masuk dari Investor.</td></tr>`;
    } else {
        appState.investorFunds.forEach((item, index) => {
            total += Number(item.amount) || 0;
            const itemId = String(item.id);
            const isEditingRow = itemId === String(editingInvestorId);
            const tr = document.createElement('tr');
            tr.className = isEditingRow ? "bg-blue-50/80 transition" : "hover:bg-slate-50 transition";
            tr.innerHTML = `
                <td class="p-3 text-center text-slate-400 font-mono">${index + 1}</td>
                <td class="p-3 whitespace-nowrap">${escapeHtml(item.date)}</td>
                <td class="p-3 font-bold text-slate-900">${escapeHtml(item.investor)}</td>
                <td class="p-3 font-medium text-slate-700">${escapeHtml(item.desc)}</td>
                <td class="p-3 text-right font-bold font-mono text-emerald-700">${formatRupiah(item.amount)}</td>
                <td class="p-3 text-center whitespace-nowrap">
                    <div class="inline-flex items-center gap-1">
                        <button type="button" onclick="startEditInvestor('${escapeHtml(itemId)}')" class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-1.5 rounded transition" title="Edit Dana Investor">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button" onclick="deleteInvestor('${escapeHtml(itemId)}')" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded transition" title="Hapus Data Investor">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('tableTotalInvestor').innerText = formatRupiah(total);
}

// Toggle Status Validasi Cepat
async function toggleValidation(id) {
    let result;
    try {
        result = await apiRequest(`${ROUTES.expensesStore}/${encodeURIComponent(id)}/validation`, 'PATCH');
    } catch (_) {
        return;
    }
    renderAll();
    showToast(`Status validasi diubah menjadi "${result.validation}"`, 'info');
}

// Actions Hapus Row
async function deleteExpense(id) {
    const key = String(id);
    try {
        await apiRequest(`${ROUTES.expensesStore}/${encodeURIComponent(key)}`, 'DELETE');
    } catch (_) {
        return;
    }
    delete pendingExpenseCategories[key];
    if (String(lastAddedExpenseId) === key) lastAddedExpenseId = null;
    if (String(editingExpenseId) === key) cancelExpenseEdit(true);
    renderAll();
    showToast('Pemakaian biaya telah dihapus dari database.', 'warning');
}

async function deleteKasbon(id) {
    const key = String(id);
    try {
        await apiRequest(`${ROUTES.kasbonsStore}/${encodeURIComponent(key)}`, 'DELETE');
    } catch (_) {
        return;
    }
    if (String(editingKasbonId) === key) cancelKasbonEdit(true);
    renderAll();
    showToast('Riwayat kasbon telah dihapus dari database.', 'warning');
}

async function deleteInvestor(id) {
    const key = String(id);
    try {
        await apiRequest(`${ROUTES.investorsStore}/${encodeURIComponent(key)}`, 'DELETE');
    } catch (_) {
        return;
    }
    if (String(editingInvestorId) === key) cancelInvestorEdit(true);
    renderAll();
    showToast('Riwayat dana investor telah dihapus dari database.', 'warning');
}

// Edit Project Name (Inline + Simpan Perubahan)
function editProjectName() {
    const displayEl = document.getElementById('projectNameDisplay');
    const editBtn = document.getElementById('editProjectNameBtn');
    const editBox = document.getElementById('projectNameEditBox');
    const inputEl = document.getElementById('projectNameInput');

    if (!editBox || !inputEl) return;
    inputEl.value = appState.projectName || '';
    if (displayEl) displayEl.classList.add('hidden');
    if (editBtn) editBtn.classList.add('hidden');
    editBox.classList.remove('hidden');
    editBox.classList.add('flex');
    inputEl.focus();
}

async function saveProjectNameEdit(e) {
    if (e) e.preventDefault();
    const inputEl = document.getElementById('projectNameInput');
    const newName = (inputEl ? inputEl.value : '').trim();
    if (!newName) {
        showToast('Nama project tidak boleh kosong!', 'warning');
        if (inputEl) inputEl.focus();
        return;
    }
    try {
        await apiRequest(ROUTES.projectUpdate, 'PATCH', { name: newName });
    } catch (_) {
        return;
    }
    cancelProjectNameEdit();
    renderAll();
    showToast('Perubahan nama project berhasil disimpan ke database.', 'success');
}

function cancelProjectNameEdit() {
    const displayEl = document.getElementById('projectNameDisplay');
    const editBtn = document.getElementById('editProjectNameBtn');
    const editBox = document.getElementById('projectNameEditBox');
    if (editBox) {
        editBox.classList.add('hidden');
        editBox.classList.remove('flex');
    }
    if (displayEl) displayEl.classList.remove('hidden');
    if (editBtn) editBtn.classList.remove('hidden');
}

function resetAllFormEditStates() {
    pendingExpenseCategories = {};
    lastAddedExpenseId = null;
    cancelInvestorEdit(true);
    cancelKasbonEdit(true);
    cancelExpenseEdit(true);
    cancelProjectNameEdit();
}

// Reset Data
async function confirmResetData() {
    const confirmed = confirm('Apakah Anda yakin ingin menghapus SELURUH data pencatatan project ini? Data tidak dapat dikembalikan.');
    if (!confirmed) return;

    try {
        await apiRequest(ROUTES.projectReset, 'POST');
    } catch (_) {
        return;
    }
    resetAllFormEditStates();
    renderAll();
    showToast('Seluruh data di database berhasil dikosongkan.', 'warning');
}

// Load Demo Data
async function loadDemoData(notify = true) {
    try {
        await apiRequest(ROUTES.projectDemo, 'POST');
    } catch (_) {
        return;
    }
    resetAllFormEditStates();
    renderAll();
    if (notify) showToast('Data simulasi/contoh berhasil dimuat ke database!', 'success');
}

// Export to CSV (Streaming via Laravel)
function exportToCSV() {
    if (appState.expenses.length === 0 && appState.kasbonList.length === 0 && appState.investorFunds.length === 0) {
        showToast('Tidak ada data untuk diexport!', 'warning');
        return;
    }

    window.location.href = ROUTES.exportCsv;
    showToast('File CSV berhasil di-download!', 'success');
}

// Toast Notification Function (No alert!)
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');

    let bg = 'bg-slate-900 text-white';
    let icon = 'fa-info-circle text-blue-400';

    if (type === 'success') {
        bg = 'bg-emerald-900 text-emerald-100 border border-emerald-700';
        icon = 'fa-circle-check text-emerald-400';
    } else if (type === 'warning') {
        bg = 'bg-amber-900 text-amber-100 border border-amber-700';
        icon = 'fa-triangle-exclamation text-amber-400';
    } else if (type === 'info') {
        bg = 'bg-blue-900 text-blue-100 border border-blue-700';
        icon = 'fa-circle-info text-blue-400';
    }

    toast.className = `${bg} px-4 py-3 rounded-xl shadow-xl text-xs font-semibold flex items-center gap-3 transition-all transform duration-300 pointer-events-auto border`;
    toast.innerHTML = `<i class="fa-solid ${icon} text-lg"></i> <span>${escapeHtml(message)}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-[-10px]');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
