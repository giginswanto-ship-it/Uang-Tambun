@extends('layouts.app')

@section('title', 'Dashboard Monitoring Keuangan Project')

@section('content')
    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-5 right-5 z-[60] flex flex-col gap-2 pointer-events-none"></div>

    @include('dashboard.partials.modals')

    <!-- Header & Navigation -->
    <header class="bg-gradient-to-r from-[#060e20]/95 via-[#0b1b3d]/95 to-[#071126]/95 text-white border-b border-blue-500/25 sticky top-0 z-30 shadow-2xl shadow-blue-950/50 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                <!-- Left Title & Editable Project Name -->
                <div class="flex items-center gap-3">
                    <div class="bg-gradient-to-br from-blue-600 to-indigo-600 p-2.5 rounded-xl shadow-lg shadow-blue-600/30 text-white border border-blue-400/30">
                        <i class="fa-solid fa-chart-line text-xl"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs uppercase tracking-wider font-bold text-sky-400">Sistem Kas & Kasbon Project</span>
                            <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-[10px] px-2 py-0.5 rounded-full font-medium">Live System</span>
                        </div>
                        <div class="flex items-center gap-2 group flex-wrap">
                            <h1 id="projectNameDisplay" class="text-xl sm:text-2xl font-black tracking-tight text-white">{{ $initialState['projectName'] }}</h1>
                            <button id="editProjectNameBtn" type="button" onclick="editProjectName()" class="text-slate-400 hover:text-white transition-colors text-sm opacity-70 group-hover:opacity-100" title="Ubah Nama Project">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <form id="projectNameEditBox" onsubmit="saveProjectNameEdit(event)" class="hidden items-center gap-1.5 flex-wrap">
                                <input type="text" id="projectNameInput" required class="px-2.5 py-1 rounded-lg bg-[#060e20] border border-blue-500/40 text-white text-sm font-semibold focus:ring-2 focus:ring-blue-400 focus:outline-none min-w-[220px]" placeholder="Masukkan nama project...">
                                <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Simpan Perubahan</span>
                                </button>
                                <button type="button" onclick="cancelProjectNameEdit()" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold transition border border-slate-700">
                                    Batal
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="openCategorySettingsModal()" class="px-3.5 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 hover:text-amber-200 border border-amber-500/40 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Pengaturan Kategori</span>
                    </button>
                    <button onclick="loadDemoData()" class="px-3.5 py-2 bg-blue-600/20 hover:bg-blue-600/30 text-blue-200 hover:text-white border border-blue-400/30 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-database"></i>
                        <span>Muat Data Contoh</span>
                    </button>
                    <button onclick="exportToCSV()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 shadow-md shadow-emerald-950/40 border border-emerald-400/30">
                        <i class="fa-solid fa-file-excel"></i>
                        <span>Export CSV</span>
                    </button>
                    <button onclick="confirmResetData()" class="px-3.5 py-2 bg-rose-950/70 hover:bg-rose-900 text-rose-300 border border-rose-700/50 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Reset Data</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 flex-grow w-full space-y-8">

        <!-- BAGIAN 1: Ringkasan KPI Kas Utama -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-vault text-sky-400"></i> Ringkasan Budget & Kas Principal Project
                </h2>
                <span class="text-xs text-blue-200/80">Real-time update dari mutasi kas & anggaran</span>
            </div>

            <!-- Baris Utama: Total Budget, Total Terpakai, Sisa Budget -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                <!-- Total Budget -->
                <div class="bg-gradient-to-br from-[#0a1836]/95 to-[#0e244d]/95 rounded-2xl p-5 border-2 border-blue-500/40 shadow-xl shadow-blue-950/40 relative overflow-hidden flex flex-col justify-between">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-bold uppercase text-sky-400 tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-wallet"></i> Total Budget
                        </span>
                        <button type="button" onclick="openCategorySettingsModal()" class="px-2.5 py-1 bg-blue-500/20 hover:bg-blue-500/30 text-blue-200 border border-blue-400/30 rounded-lg text-[11px] font-bold transition flex items-center gap-1" title="Atur Budget Setiap Kategori">
                            <i class="fa-solid fa-sliders"></i>
                            <span>Atur Budget Kategori</span>
                        </button>
                    </div>
                    <div id="kpiTotalBudget" class="text-2xl sm:text-3xl font-black text-white mb-1">Rp 0</div>
                    <p id="kpiBudgetSourceNote" class="text-xs text-blue-200/80">Akumulasi budget dari setiap kategori aktif</p>
                </div>

                <!-- Total Terpakai -->
                <div class="bg-gradient-to-br from-[#0a1836]/95 to-[#241a10]/95 rounded-2xl p-5 border-2 border-amber-500/40 shadow-xl shadow-amber-950/30 relative overflow-hidden flex flex-col justify-between">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-bold uppercase text-amber-400 tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-chart-pie"></i> Total Terpakai
                        </span>
                        <span id="kpiBudgetUsagePctBadge" class="px-2.5 py-0.5 bg-amber-500/20 text-amber-300 border border-amber-500/40 rounded-full text-[11px] font-bold">0%</span>
                    </div>
                    <div id="kpiBudgetUsed" class="text-2xl sm:text-3xl font-black text-amber-400 mb-1">Rp 0</div>
                    <p id="kpiBudgetUsedNote" class="text-xs text-blue-200/80">Total realisasi pengeluaran tercatat</p>
                </div>

                <!-- Sisa Budget -->
                <div id="kpiBudgetRemainingCard" class="bg-gradient-to-br from-[#0d224b] via-[#113165] to-[#07142d] text-white rounded-2xl p-5 border border-blue-400/40 shadow-2xl shadow-blue-950/60 relative overflow-hidden flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-xs font-bold uppercase text-blue-200 tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-piggy-bank"></i> Sisa Budget
                            </span>
                            <span id="kpiBudgetRemainingBadge" class="px-2 py-0.5 bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 rounded-full text-[11px] font-bold">TERSEDIA</span>
                        </div>
                        <div id="kpiBudgetRemaining" class="text-2xl sm:text-3xl font-black text-emerald-400 mb-1">Rp 0</div>
                    </div>
                    <div>
                        <div class="w-full bg-blue-950/80 border border-blue-900/50 h-2 rounded-full overflow-hidden mt-2 mb-1.5">
                            <div id="kpiBudgetProgressBar" class="bg-emerald-400 h-full rounded-full transition-all duration-500 shadow-sm" style="width: 0%"></div>
                        </div>
                        <p id="kpiBudgetRemainingNote" class="text-xs text-blue-200/90">Selisih Total Budget dikurangi Total Terpakai</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- KPI 1: Total Uang Masuk (Investor -> Principal) -->
                <div class="bg-gradient-to-br from-[#0a1836]/95 to-[#0e244d]/95 rounded-2xl p-5 border border-blue-500/25 shadow-xl relative overflow-hidden">
                    <div class="absolute -right-3 -bottom-3 text-emerald-500/10 pointer-events-none">
                        <i class="fa-solid fa-hand-holding-dollar text-7xl"></i>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase text-slate-300 tracking-wider">Total Masuk (Investor)</span>
                        <span class="p-2 bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 rounded-lg text-sm"><i class="fa-solid fa-arrow-down-left"></i></span>
                    </div>
                    <div id="kpiInvestorTotal" class="text-2xl font-black text-white mb-1">Rp 0</div>
                    <p class="text-xs text-blue-200/80 flex items-center gap-1">
                        <i class="fa-solid fa-circle-info text-sky-400"></i> Transfer Investor ke Principal
                    </p>
                </div>

                <!-- KPI 2: Total Kasbon Dikeluarkan -->
                <div class="bg-gradient-to-br from-[#0a1836]/95 to-[#0e244d]/95 rounded-2xl p-5 border border-blue-500/25 shadow-xl relative overflow-hidden">
                    <div class="absolute -right-3 -bottom-3 text-blue-500/10 pointer-events-none">
                        <i class="fa-solid fa-money-bill-transfer text-7xl"></i>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase text-slate-300 tracking-wider">Kasbon Dikeluarkan</span>
                        <span class="p-2 bg-blue-500/20 text-sky-400 border border-blue-500/40 rounded-lg text-sm"><i class="fa-solid fa-arrow-up-right"></i></span>
                    </div>
                    <div id="kpiKasbonTotal" class="text-2xl font-black text-white mb-1">Rp 0</div>
                    <p class="text-xs text-blue-200/80 flex items-center gap-1">
                        <i class="fa-solid fa-user-check text-sky-300"></i> Total ke Rengga & Ashabil
                    </p>
                </div>

                <!-- KPI 3: Sisa Kas di Tangan Principal (Highlight Utama) -->
                <div id="kpiPrincipalCard" class="bg-gradient-to-br from-[#0d224b] via-[#113165] to-[#07142d] text-white rounded-2xl p-5 border border-blue-400/40 shadow-xl relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase text-blue-200 tracking-wider">Kas di Tangan Principal</span>
                        <span id="kpiPrincipalBadge" class="px-2 py-0.5 bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 rounded text-[11px] font-bold">AMAN</span>
                    </div>
                    <div id="kpiPrincipalSisa" class="text-2xl font-black text-emerald-400 mb-1">Rp 0</div>
                    <p class="text-xs text-blue-200/90 flex items-center gap-1">
                        <i class="fa-solid fa-shield-halved text-emerald-400"></i> Uang Investor belum dikasbonkan
                    </p>
                </div>

                <!-- KPI 4: Total Terpakai vs Sisa Pelaksana -->
                <div class="bg-gradient-to-br from-[#0a1836]/95 to-[#0e244d]/95 rounded-2xl p-5 border border-blue-500/25 shadow-xl relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase text-slate-300 tracking-wider">Realisasi Biaya Lapangan</span>
                        <span class="p-2 bg-amber-500/20 text-amber-400 border border-amber-500/40 rounded-lg text-sm"><i class="fa-solid fa-receipt"></i></span>
                    </div>
                    <div id="kpiExpensesTotal" class="text-2xl font-black text-white mb-1">Rp 0</div>
                    <p id="kpiPelaksanaSisaCombined" class="text-xs text-blue-200/90 font-medium">
                        Sisa Kasbon Pelaksana: <span class="font-bold text-emerald-400">Rp 0</span>
                    </p>
                </div>

            </div>
        </section>

        <!-- BAGIAN 2: Kartu Kontrol Kasbon Per Pelaksana & Visual Grafik -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Kartu Pelaksana Rengga & Ashabil (7 cols) -->
            <div class="lg:col-span-7 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-users-gear text-sky-400"></i> Kontrol Kasbon Per Pelaksana
                    </h2>
                    <span class="text-xs text-blue-200/80">Pertanggungjawaban Uang Muka</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- Card Moh. Rengga -->
                    <div class="bg-[#0a1836]/90 rounded-2xl border border-blue-500/25 p-5 shadow-xl hover:border-blue-400/40 transition">
                        <div class="flex items-center justify-between pb-3 border-b border-blue-900/40 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-600/30 text-sky-300 border border-blue-400/30 flex items-center font-bold justify-center text-sm shadow-inner">
                                    MR
                                </div>
                                <div>
                                    <h3 class="font-bold text-white leading-snug">Moh. Rengga</h3>
                                    <p class="text-[11px] text-blue-200/80">Pelaksana Lapangan</p>
                                </div>
                            </div>
                            <span id="badgeRengga" class="px-2.5 py-1 text-[11px] font-semibold rounded-full bg-blue-950/60 text-blue-200 border border-blue-500/30">Normal</span>
                        </div>

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between text-slate-300">
                                <span>Kasbon Diterima:</span>
                                <span id="renggaKasbon" class="font-bold text-white">Rp 0</span>
                            </div>
                            <div class="flex justify-between text-slate-300">
                                <span>Total Belanja / Biaya:</span>
                                <span id="renggaBiaya" class="font-bold text-amber-400">Rp 0</span>
                            </div>
                            <div class="pt-2 border-t border-blue-900/40 flex justify-between items-center font-bold">
                                <span class="text-slate-100">Sisa Uang di Tangan:</span>
                                <span id="renggaSisa" class="text-base text-emerald-400 font-black">Rp 0</span>
                            </div>
                        </div>

                        <!-- Mini progress bar -->
                        <div class="mt-4">
                            <div class="flex justify-between text-[11px] text-blue-200/80 mb-1">
                                <span>Serapan Kasbon</span>
                                <span id="renggaPercent" class="font-bold text-sky-300">0%</span>
                            </div>
                            <div class="w-full bg-blue-950/80 border border-blue-900/50 h-2 rounded-full overflow-hidden">
                                <div id="renggaBar" class="bg-blue-500 h-full rounded-full transition-all duration-500 shadow-sm" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Moh. Ashabil -->
                    <div class="bg-[#0a1836]/90 rounded-2xl border border-blue-500/25 p-5 shadow-xl hover:border-blue-400/40 transition">
                        <div class="flex items-center justify-between pb-3 border-b border-blue-900/40 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-purple-600/30 text-purple-300 border border-purple-400/30 flex items-center font-bold justify-center text-sm shadow-inner">
                                    MA
                                </div>
                                <div>
                                    <h3 class="font-bold text-white leading-snug">Moh. Ashabil</h3>
                                    <p class="text-[11px] text-blue-200/80">Pelaksana Lapangan</p>
                                </div>
                            </div>
                            <span id="badgeAshabil" class="px-2.5 py-1 text-[11px] font-semibold rounded-full bg-purple-950/60 text-purple-200 border border-purple-500/30">Normal</span>
                        </div>

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between text-slate-300">
                                <span>Kasbon Diterima:</span>
                                <span id="ashabilKasbon" class="font-bold text-white">Rp 0</span>
                            </div>
                            <div class="flex justify-between text-slate-300">
                                <span>Total Belanja / Biaya:</span>
                                <span id="ashabilBiaya" class="font-bold text-amber-400">Rp 0</span>
                            </div>
                            <div class="pt-2 border-t border-blue-900/40 flex justify-between items-center font-bold">
                                <span class="text-slate-100">Sisa Uang di Tangan:</span>
                                <span id="ashabilSisa" class="text-base text-emerald-400 font-black">Rp 0</span>
                            </div>
                        </div>

                        <!-- Mini progress bar -->
                        <div class="mt-4">
                            <div class="flex justify-between text-[11px] text-blue-200/80 mb-1">
                                <span>Serapan Kasbon</span>
                                <span id="ashabilPercent" class="font-bold text-purple-300">0%</span>
                            </div>
                            <div class="w-full bg-blue-950/80 border border-blue-900/50 h-2 rounded-full overflow-hidden">
                                <div id="ashabilBar" class="bg-purple-500 h-full rounded-full transition-all duration-500 shadow-sm" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Visual Charts (5 cols) -->
            <div class="lg:col-span-5 bg-[#0a1836]/90 rounded-2xl border border-blue-500/25 p-5 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-chart-pie text-sky-400"></i> Alokasi Biaya Per Kategori
                        </h3>
                        <span class="text-xs text-blue-200/80">Total Pengeluaran</span>
                    </div>
                    <div class="relative h-48 w-full flex items-center justify-center">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-blue-900/40 text-center">
                    <p class="text-xs text-blue-200/80">Visualisasi mempermudah audit pos mana yang menyerap biaya paling dominan.</p>
                </div>
            </div>

        </section>

        <!-- BAGIAN 2B: Pengelolaan Kategori Biaya -->
        <section id="categoryManagementSection" class="bg-[#0a1836]/90 rounded-2xl border border-blue-500/25 shadow-xl overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-blue-900/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-tags text-sky-400"></i> Kategori Biaya Project
                        </h2>
                        <span id="categoryCountBadge" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-500/20 text-sky-300 border border-blue-400/30">
                            0 Kategori
                        </span>
                    </div>
                    <p class="text-xs text-blue-200/80 mt-0.5">Kelola kategori, pantau anggaran per pos pengeluaran, serta atur urutan & status aktif</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                    <button type="button" onclick="openCategorySettingsModal()" class="px-3.5 py-2 bg-blue-950/60 hover:bg-blue-900/70 text-slate-200 border border-blue-500/30 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-sliders text-sky-400"></i>
                        <span>Pengaturan</span>
                    </button>
                    <button type="button" onclick="openCategoryModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md shadow-blue-900/50">
                        <i class="fa-solid fa-plus"></i>
                        <span>Tambah Kategori</span>
                    </button>
                </div>
            </div>

            <div class="p-4 sm:p-5">
                <div id="categoryListContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Render via JS -->
                </div>
            </div>
        </section>

        <!-- BAGIAN 3: Form Input Transaksi Cepat (3 Tab) -->
        <section id="transactionFormSection" class="bg-[#0a1836]/90 rounded-2xl border border-blue-500/25 shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-[#060e20] to-[#0c1e40] text-white p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-blue-900/40">
                <div>
                    <h2 class="font-bold text-lg flex items-center gap-2 text-white">
                        <i class="fa-solid fa-pen-to-square text-sky-400"></i> Form Pencatatan Transaksi Keuangan
                    </h2>
                    <p class="text-xs text-blue-200/80">Pilih jenis transaksi untuk menambah atau menyimpan perubahan data</p>
                </div>

                <!-- Nav Tab Form -->
                <div class="flex bg-[#060e20] p-1 rounded-xl gap-1 border border-blue-900/40 self-start sm:self-auto">
                    <button type="button" onclick="switchFormTab('investor')" id="tabBtnInvestor" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition bg-blue-600 text-white shadow-sm">
                        <i class="fa-solid fa-hand-holding-dollar mr-1"></i> Dana Investor
                    </button>
                    <button type="button" onclick="switchFormTab('kasbon')" id="tabBtnKasbon" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition">
                        <i class="fa-solid fa-money-bill-wave mr-1"></i> Pencairan Kasbon
                    </button>
                    <button type="button" onclick="switchFormTab('biaya')" id="tabBtnBiaya" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition">
                        <i class="fa-solid fa-file-invoice-dollar mr-1"></i> Detail Biaya Lapangan
                    </button>
                </div>
            </div>

            <div class="p-5 sm:p-6">

                <!-- TAB 1: FORM DANA MASUK INVESTOR -->
                <form id="formInvestor" onsubmit="handleInvestorSubmit(event)" class="space-y-4">
                    <div id="investorEditBanner" class="hidden items-center justify-between gap-3 px-4 py-2.5 rounded-xl bg-blue-950/70 border border-blue-400/30 text-xs text-sky-200">
                        <div class="flex items-center gap-2 font-semibold">
                            <i class="fa-solid fa-pen-to-square text-sky-400"></i>
                            <span id="investorEditBannerText">Mode Edit Dana Investor — ubah data lalu klik Simpan Perubahan.</span>
                        </div>
                        <button type="button" onclick="cancelInvestorEdit()" class="px-2.5 py-1 bg-blue-900/40 hover:bg-blue-800/60 text-slate-200 border border-blue-400/30 rounded-lg font-semibold transition flex-shrink-0">
                            Batal Edit
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Tanggal Terima *</label>
                            <input type="date" id="invDate" required class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Investor *</label>
                            <input type="text" id="invInvestor" required placeholder="Contoh: Bpk. H. Ahmad" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nominal Uang Masuk (Rp) *</label>
                            <input type="number" id="invAmount" required min="1" placeholder="0" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Keterangan / Tahap Term</label>
                            <input type="text" id="invDesc" placeholder="Contoh: Modal Awal Term 1" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end items-center gap-2 pt-2">
                        <button type="button" id="invCancelEditBtn" onclick="cancelInvestorEdit()" class="hidden px-4 py-2.5 bg-blue-950/60 hover:bg-blue-900/60 text-slate-200 border border-blue-500/30 rounded-lg text-sm font-semibold transition items-center gap-1.5">
                            <i class="fa-solid fa-xmark"></i>
                            <span>Batal</span>
                        </button>
                        <button type="submit" id="invSubmitBtn" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm">
                            <i class="fa-solid fa-plus-circle"></i>
                            <span>Simpan Uang Masuk Investor</span>
                        </button>
                    </div>
                </form>

                <!-- TAB 2: FORM PENCAIRAN KASBON -->
                <form id="formKasbon" onsubmit="handleKasbonSubmit(event)" class="hidden space-y-4">
                    <div id="kasbonEditBanner" class="hidden items-center justify-between gap-3 px-4 py-2.5 rounded-xl bg-blue-950/70 border border-blue-400/30 text-xs text-sky-200">
                        <div class="flex items-center gap-2 font-semibold">
                            <i class="fa-solid fa-pen-to-square text-sky-400"></i>
                            <span id="kasbonEditBannerText">Mode Edit Pencairan Kasbon — ubah data lalu klik Simpan Perubahan.</span>
                        </div>
                        <button type="button" onclick="cancelKasbonEdit()" class="px-2.5 py-1 bg-blue-900/40 hover:bg-blue-800/60 text-slate-200 border border-blue-400/30 rounded-lg font-semibold transition flex-shrink-0">
                            Batal Edit
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Tanggal Kasbon *</label>
                            <input type="date" id="kasDate" required class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Penerima Kasbon *</label>
                            <select id="kasPelaksana" required class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                <option value="Moh. Rengga">Moh. Rengga</option>
                                <option value="Moh. Ashabil">Moh. Ashabil</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nominal Kasbon (Rp) *</label>
                            <input type="number" id="kasAmount" required min="1" placeholder="0" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Keperluan / Catatan</label>
                            <input type="text" id="kasDesc" placeholder="Contoh: Operasional Belanja Material Minggu 1" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end items-center gap-2 pt-2">
                        <button type="button" id="kasCancelEditBtn" onclick="cancelKasbonEdit()" class="hidden px-4 py-2.5 bg-blue-950/60 hover:bg-blue-900/60 text-slate-200 border border-blue-500/30 rounded-lg text-sm font-semibold transition items-center gap-1.5">
                            <i class="fa-solid fa-xmark"></i>
                            <span>Batal</span>
                        </button>
                        <button type="submit" id="kasSubmitBtn" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition flex items-center gap-2 shadow-sm">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Simpan Pencairan Kasbon</span>
                        </button>
                    </div>
                </form>

                <!-- TAB 3: FORM DETAIL PEMAKAIAN BIAYA LAPANGAN -->
                <form id="formBiaya" onsubmit="handleExpenseSubmit(event)" class="hidden">
                    <input type="hidden" id="expCategory" value="">

                    <div id="expenseEditBanner" class="hidden items-center justify-between gap-3 px-4 py-2.5 mb-4 rounded-xl bg-amber-950/70 border border-amber-400/30 text-xs text-amber-200">
                        <div class="flex items-center gap-2 font-semibold">
                            <i class="fa-solid fa-pen-to-square text-amber-400"></i>
                            <span id="expenseEditBannerText">Mode Edit Pemakaian Biaya — ubah data atau kategori lalu klik Simpan Perubahan.</span>
                        </div>
                        <button type="button" onclick="cancelExpenseEdit()" class="px-2.5 py-1 bg-amber-900/40 hover:bg-amber-800/60 text-amber-200 border border-amber-400/30 rounded-lg font-semibold transition flex-shrink-0">
                            Batal Edit
                        </button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
                        <!-- KIRI: INPUT PEMAKAIAN DANA (8 cols) -->
                        <div class="lg:col-span-8 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="expDate" class="block text-xs font-semibold text-slate-300 mb-1">Tanggal Biaya *</label>
                                    <input type="date" id="expDate" required class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label for="expPelaksana" class="block text-xs font-semibold text-slate-300 mb-1">Pelaksana (Pengguna Kasbon) *</label>
                                    <select id="expPelaksana" required class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                        <option value="Moh. Rengga">Moh. Rengga</option>
                                        <option value="Moh. Ashabil">Moh. Ashabil</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="expPayMethod" class="block text-xs font-semibold text-slate-300 mb-1">Metode Pembayaran *</label>
                                    <select id="expPayMethod" required class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                        <option value="Tunai Kasbon">Tunai Kasbon</option>
                                        <option value="Transfer Pelaksana">Transfer Rekening Pelaksana</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-4 bg-[#060e20]/80 p-4 rounded-xl border border-blue-900/40">
                                <div class="sm:col-span-2 lg:col-span-2">
                                    <label for="expTitle" class="block text-xs font-semibold text-slate-300 mb-1">Uraian Item / Keperluan *</label>
                                    <input type="text" id="expTitle" required placeholder="Contoh: Semen Tiga Roda 50kg" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                </div>
                                <div>
                                    <label for="expQty" class="block text-xs font-semibold text-slate-300 mb-1">Qty / Vol *</label>
                                    <input type="number" id="expQty" required min="0.01" step="any" placeholder="1" oninput="calcTotalBiaya()" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                </div>
                                <div>
                                    <label for="expUnit" class="block text-xs font-semibold text-slate-300 mb-1">Satuan *</label>
                                    <select id="expUnit" required class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                        <option value="sak">sak</option>
                                        <option value="pcs">pcs</option>
                                        <option value="rit">rit</option>
                                        <option value="hari">hari</option>
                                        <option value="meter">meter</option>
                                        <option value="paket">paket</option>
                                        <option value="ls">lump sum (ls)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="expPrice" class="block text-xs font-semibold text-slate-300 mb-1">Harga Satuan (Rp) *</label>
                                    <input type="number" id="expPrice" required min="0" placeholder="0" oninput="calcTotalBiaya()" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                </div>
                                <div>
                                    <label for="expTotalDisplay" class="block text-xs font-semibold text-sky-300 mb-1">Total Biaya (Otomatis)</label>
                                    <input type="text" id="expTotalDisplay" readonly value="Rp 0" class="w-full px-3 py-2 border border-sky-500/40 bg-sky-950/60 text-sky-200 font-bold rounded-lg text-sm outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="expVendor" class="block text-xs font-semibold text-slate-300 mb-1">Toko / Vendor / Penerima</label>
                                    <input type="text" id="expVendor" placeholder="Contoh: TB. Makmur Jaya" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label for="expProof" class="block text-xs font-semibold text-slate-300 mb-1">No. Nota / Bukti Transfer</label>
                                    <input type="text" id="expProof" placeholder="Contoh: INV/2026/089" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label for="expValidation" class="block text-xs font-semibold text-slate-300 mb-1">Status Validasi Principal</label>
                                    <select id="expValidation" class="w-full px-3 py-2 border border-blue-900/60 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-[#060e20]">
                                        <option value="Valid">Valid (Disetujui)</option>
                                        <option value="Perlu Nota">Perlu Lampiran Nota</option>
                                        <option value="Revisi">Perlu Revisi</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- KANAN: PILIH KATEGORI PENGELUARAN (4 cols) -->
                        <div class="lg:col-span-4 bg-[#060e20]/80 border border-blue-900/40 rounded-xl p-4 flex flex-col justify-between gap-3">
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                                            <i class="fa-solid fa-tags text-amber-400"></i> Masuk Kategori Mana?
                                        </h3>
                                        <p class="text-[11px] text-blue-200/70 mt-0.5">Pilih langsung di sini atau tentukan tepat setelah disimpan di tabel.</p>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <button type="button" onclick="openCategoryModal(null, true)" class="px-2 py-1 bg-blue-950/60 hover:bg-blue-900/60 text-sky-300 border border-blue-500/30 rounded-lg text-[11px] font-bold transition flex items-center gap-1" title="Tambah Kategori Baru">
                                            <i class="fa-solid fa-plus"></i>
                                            <span>Baru</span>
                                        </button>
                                        <button type="button" onclick="openCategorySettingsModal()" class="p-1.5 bg-blue-950/60 hover:bg-blue-900/60 text-slate-200 border border-blue-500/30 rounded-lg text-[11px] transition" title="Pengaturan Kategori">
                                            <i class="fa-solid fa-sliders"></i>
                                        </button>
                                    </div>
                                </div>

                                <div id="expenseRightCategoryList" class="space-y-2 max-h-60 overflow-y-auto custom-scrollbar pr-1">
                                    <!-- Render via JS -->
                                </div>
                            </div>

                            <div class="pt-2 border-t border-blue-900/40 flex items-center justify-between text-[11px]">
                                <span class="text-blue-200/70 font-medium">Terpilih:</span>
                                <span id="selectedExpenseCategoryBadge" class="font-bold text-slate-200">Tentukan Setelah Input</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 mt-4 border-t border-blue-900/40">
                        <p class="text-[11px] text-blue-200/80 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-info text-sky-400"></i>
                            <span>Kategori juga bisa langsung diubah tepat setelah data masuk di tabel <strong>Biaya Lapangan</strong> lalu klik <strong>Simpan Perubahan</strong>.</span>
                        </p>
                        <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                            <button type="button" id="expCancelEditBtn" onclick="cancelExpenseEdit()" class="hidden px-4 py-2.5 bg-blue-950/60 hover:bg-blue-900/60 text-slate-200 border border-blue-500/30 rounded-lg text-sm font-semibold transition items-center gap-1.5">
                                <i class="fa-solid fa-xmark"></i>
                                <span>Batal</span>
                            </button>
                            <button type="submit" id="expSubmitBtn" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 shadow-sm flex-shrink-0">
                                <i class="fa-solid fa-receipt"></i>
                                <span>Simpan Pemakaian Biaya & Potong Kasbon</span>
                            </button>
                        </div>
                    </div>
                </form>
                    </div>
                </form>

            </div>
        </section>

        <!-- BAGIAN 4: TABEL RINCIAN PEMAKAIAN BIAYA & MUTASI KAS -->
        <section class="bg-[#0a1836]/90 rounded-2xl border border-blue-500/25 shadow-xl overflow-hidden">

            <!-- Header Table Controls -->
            <div class="p-4 sm:p-5 border-b border-blue-900/40 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-table-list text-sky-400"></i> Rincian Pemakaian Biaya & Mutasi Kas
                        </h2>
                        <p class="text-xs text-blue-200/80">Transparansi audit dan pertanggungjawaban dana project</p>
                    </div>

                    <!-- Main View Mode Switcher -->
                    <div class="flex bg-[#050b18] p-1 rounded-xl gap-1 border border-blue-900/40">
                        <button onclick="switchViewTab('expenses')" id="viewBtnExpenses" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-900/50">
                            Biaya Lapangan (<span id="countBiaya">0</span>)
                        </button>
                        <button onclick="switchViewTab('kasbon')" id="viewBtnKasbon" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition text-blue-200 hover:text-white">
                            Riwayat Kasbon (<span id="countKasbon">0</span>)
                        </button>
                        <button onclick="switchViewTab('investor')" id="viewBtnInvestor" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition text-blue-200 hover:text-white">
                            Dana Investor (<span id="countInvestor">0</span>)
                        </button>
                    </div>
                </div>

                <!-- Filters (Tampil Hanya Saat View Biaya Lapangan Active) -->
                <div id="filterContainer" class="flex flex-wrap items-center gap-3 pt-2 border-t border-blue-900/40 text-xs">
                    <div class="flex items-center gap-1.5 font-semibold text-slate-200">
                        <i class="fa-solid fa-filter text-sky-400"></i> Filter Data:
                    </div>

                    <!-- Pelaksana Filter -->
                    <select id="filterPelaksana" onchange="applyFilters()" class="px-2.5 py-1.5 border border-blue-900/60 rounded-lg bg-[#060e20] text-white focus:outline-none focus:border-sky-400">
                        <option value="ALL">Semua Pelaksana</option>
                        <option value="Moh. Rengga">Moh. Rengga</option>
                        <option value="Moh. Ashabil">Moh. Ashabil</option>
                    </select>

                    <!-- Kategori Filter -->
                    <select id="filterCategory" onchange="applyFilters()" class="px-2.5 py-1.5 border border-blue-900/60 rounded-lg bg-[#060e20] text-white focus:outline-none focus:border-sky-400">
                        <option value="ALL">Semua Kategori</option>
                        <!-- Render via JS -->
                    </select>

                    <!-- Status Validasi Filter -->
                    <select id="filterStatus" onchange="applyFilters()" class="px-2.5 py-1.5 border border-blue-900/60 rounded-lg bg-[#060e20] text-white focus:outline-none focus:border-sky-400">
                        <option value="ALL">Semua Status Validasi</option>
                        <option value="Valid">Valid</option>
                        <option value="Perlu Nota">Perlu Nota</option>
                        <option value="Revisi">Revisi</option>
                    </select>

                    <!-- Search Input -->
                    <div class="relative flex-grow max-w-xs ml-auto">
                        <input type="text" id="filterSearch" oninput="applyFilters()" placeholder="Cari barang, vendor, nota..." class="w-full pl-8 pr-3 py-1.5 border border-blue-900/60 rounded-lg bg-[#060e20] text-white placeholder-slate-400 text-xs focus:outline-none focus:border-sky-400">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400"></i>
                    </div>
                </div>
            </div>

            <!-- VIEW 1: TABEL DETAIL PEMAKAIAN BIAYA LAPANGAN -->
            <div id="viewExpensesTable" class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-[#060d1e] border-b border-blue-900/60 text-sky-200 uppercase font-semibold">
                        <tr>
                            <th class="p-3 w-10 text-center">No</th>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Pelaksana</th>
                            <th class="p-3">Kategori</th>
                            <th class="p-3">Uraian Barang / Jasa</th>
                            <th class="p-3 text-center">Qty & Satuan</th>
                            <th class="p-3 text-right">Harga Satuan</th>
                            <th class="p-3 text-right">Total Biaya</th>
                            <th class="p-3">Vendor / Toko</th>
                            <th class="p-3">Bukti / Nota</th>
                            <th class="p-3 text-center">Validasi</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="expenseTableBody" class="divide-y divide-blue-950/60 text-slate-100">
                        <!-- Render via JS -->
                    </tbody>
                    <tfoot class="bg-[#060d1e] font-bold border-t-2 border-blue-900/80 text-white">
                        <tr>
                            <td colspan="7" class="p-3 text-right uppercase tracking-wider text-slate-200">Total Realisasi Terfilter:</td>
                            <td id="tableTotalExpenses" class="p-3 text-right text-sm text-amber-400 font-black">Rp 0</td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- VIEW 2: TABEL RIWAYAT KASBON PRINCIPAL -->
            <div id="viewKasbonTable" class="hidden overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-[#060d1e] border-b border-blue-900/60 text-sky-200 uppercase font-semibold">
                        <tr>
                            <th class="p-3 w-12 text-center">No</th>
                            <th class="p-3">Tanggal Pencairan</th>
                            <th class="p-3">Penerima Kasbon</th>
                            <th class="p-3">Keperluan / Catatan</th>
                            <th class="p-3 text-right">Nominal Kasbon (Rp)</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="kasbonTableBody" class="divide-y divide-blue-950/60 text-slate-100">
                        <!-- Render via JS -->
                    </tbody>
                    <tfoot class="bg-[#060d1e] font-bold border-t-2 border-blue-900/80 text-white">
                        <tr>
                            <td colspan="4" class="p-3 text-right uppercase text-slate-200">Total Kasbon Dicairkan:</td>
                            <td id="tableTotalKasbon" class="p-3 text-right text-sm text-sky-400 font-black">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- VIEW 3: TABEL DANA MASUK INVESTOR -->
            <div id="viewInvestorTable" class="hidden overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-[#060d1e] border-b border-blue-900/60 text-sky-200 uppercase font-semibold">
                        <tr>
                            <th class="p-3 w-12 text-center">No</th>
                            <th class="p-3">Tanggal Transfer</th>
                            <th class="p-3">Nama Investor</th>
                            <th class="p-3">Keterangan / Tahap</th>
                            <th class="p-3 text-right">Nominal Masuk (Rp)</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="investorTableBody" class="divide-y divide-blue-950/60 text-slate-100">
                        <!-- Render via JS -->
                    </tbody>
                    <tfoot class="bg-[#060d1e] font-bold border-t-2 border-blue-900/80 text-white">
                        <tr>
                            <td colspan="4" class="p-3 text-right uppercase text-slate-200">Total Dana Investor Masuk:</td>
                            <td id="tableTotalInvestor" class="p-3 text-right text-sm text-emerald-400 font-black">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </section>
    </main>

    <!-- Footer -->
    <footer class="mt-12 text-center text-xs text-blue-200/60">
        <p>Dashboard Monitoring Keuangan Project &copy; 2026 - Moh. Rengga &amp; Moh. Ashabil Field Accounting System</p>
    </footer>
@endsection

@push('scripts')
    <script>
        window.__INITIAL_STATE__ = @json($initialState);
        window.__DASHBOARD_CONFIG__ = {
            csrfToken: @json(csrf_token()),
            routes: {
                state: @json(route('api.state')),
                projectUpdate: @json(route('api.project.update')),
                projectDemo: @json(route('api.project.demo')),
                projectReset: @json(route('api.project.reset')),
                exportCsv: @json(route('export.csv')),
                categoriesStore: @json(route('api.categories.store')),
                categoriesSettingsBatch: @json(route('api.categories.settings.batch')),
                categoriesReset: @json(route('api.categories.reset')),
                investorsStore: @json(route('api.investors.store')),
                kasbonsStore: @json(route('api.kasbons.store')),
                expensesStore: @json(route('api.expenses.store'))
            }
        };
    </script>
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
