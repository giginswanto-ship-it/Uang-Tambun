    <!-- Modal Pengaturan Kategori -->
    <div id="categorySettingsModal" onclick="if(event.target === this) closeCategorySettingsModal()" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between flex-shrink-0">
                <div>
                    <h3 class="font-bold text-base flex items-center gap-2">
                        <i class="fa-solid fa-sliders text-blue-400"></i>
                        <span>Pengaturan Kategori Biaya</span>
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Atur urutan, anggaran per kategori, status aktif, dan perilaku pemilihan kategori</p>
                </div>
                <button type="button" onclick="closeCategorySettingsModal()" class="text-slate-400 hover:text-white transition p-1" title="Tutup">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="p-5 overflow-y-auto custom-scrollbar space-y-6 flex-grow">
                <!-- Pengaturan Umum Kategori -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-gear text-blue-600"></i> Pengaturan Umum Kategori
                        </h4>
                        <span id="settingsDirtyBadge" class="text-[11px] font-medium text-slate-500">Klik Simpan Perubahan untuk menerapkan</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="settingDefaultCategory" class="block text-xs font-semibold text-slate-700 mb-1">Kategori Default Form Biaya</label>
                            <select id="settingDefaultCategory" onchange="onCategorySettingsFieldChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs bg-white text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">Pilih Manual Setiap Input</option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">Otomatis terpilih saat membuka form atau selesai input biaya.</p>
                        </div>
                        <div>
                            <label for="settingCategorySort" class="block text-xs font-semibold text-slate-700 mb-1">Urutan Tampilan Kategori</label>
                            <select id="settingCategorySort" onchange="onCategorySettingsFieldChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs bg-white text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="manual">Urutan Kustom (Manual)</option>
                                <option value="name">Nama Kategori (A–Z)</option>
                                <option value="amount_desc">Realisasi Terbesar</option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">Berlaku di daftar kategori, pilihan input, filter, & grafik.</p>
                        </div>
                        <div>
                            <label for="settingBudgetAlertPercent" class="block text-xs font-semibold text-slate-700 mb-1">Ambang Peringatan Anggaran (%)</label>
                            <input type="number" id="settingBudgetAlertPercent" min="1" max="100" value="80" oninput="onCategorySettingsFieldChange()" onchange="onCategorySettingsFieldChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs bg-white text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <p class="text-[10px] text-slate-400 mt-1">Tandai kuning jika serapan anggaran mencapai persentase ini.</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2.5 text-xs font-medium text-slate-700 cursor-pointer pt-1">
                        <input type="checkbox" id="settingShowSection" checked onchange="onCategorySettingsFieldChange()" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>Tampilkan panel ringkasan & kelola kategori di halaman utama dashboard</span>
                    </label>
                </div>

                <!-- Daftar Pengaturan Per Kategori -->
                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                    <i class="fa-solid fa-list-check text-blue-600"></i> Konfigurasi & Budget Per Kategori
                                </h4>
                                <span id="settingsTotalBudgetPreview" class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[11px] font-bold">Total Budget: Rp 0</span>
                            </div>
                            <p id="settingsOrderNote" class="text-[11px] text-slate-500 mt-0.5">Atur urutan, status aktif, dan budget setiap kategori, lalu klik Simpan Perubahan.</p>
                        </div>
                        <button type="button" onclick="openCategoryModal()" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-sm self-start sm:self-auto">
                            <i class="fa-solid fa-plus"></i>
                            <span>Tambah Kategori</span>
                        </button>
                    </div>

                    <div id="categorySettingsListContainer" class="space-y-2.5">
                        <!-- Render via JS -->
                    </div>
                </div>
            </div>

            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 flex-shrink-0">
                <button type="button" onclick="resetCategoriesToDefault()" class="px-3.5 py-2 bg-white hover:bg-rose-50 text-rose-600 border border-slate-200 hover:border-rose-200 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span>Reset ke Kategori Bawaan</span>
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeCategorySettingsModal()" class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold transition">
                        Batal
                    </button>
                    <button type="button" id="saveCategorySettingsBtn" onclick="saveCategorySettingsChanges()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-check"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah / Edit Kategori -->
    <div id="categoryModal" onclick="if(event.target === this) closeCategoryModal()" class="fixed inset-0 z-[55] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between">
                <h3 id="categoryModalTitle" class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-tag text-blue-400"></i>
                    <span>Tambah Kategori Baru</span>
                </h3>
                <button type="button" onclick="closeCategoryModal()" class="text-slate-400 hover:text-white transition p-1" title="Tutup">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form id="categoryForm" onsubmit="handleCategorySubmit(event)" class="p-5 space-y-4">
                <input type="hidden" id="catEditId" value="">
                <div>
                    <label for="catName" class="block text-xs font-semibold text-slate-700 mb-1">Nama Kategori *</label>
                    <input type="text" id="catName" required maxlength="50" placeholder="Contoh: Pekerjaan Persiapan" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Digunakan pada form input biaya, filter tabel, dan grafik.</p>
                </div>
                <div>
                    <label for="catDesc" class="block text-xs font-semibold text-slate-700 mb-1">Keterangan / Detail (Opsional)</label>
                    <input type="text" id="catDesc" maxlength="80" placeholder="Contoh: Pembelian bahan bangunan utama" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label for="catBudget" class="block text-xs font-semibold text-slate-700 mb-1">Batas Anggaran / Budget Kategori (Rp, Opsional)</label>
                    <input type="number" id="catBudget" min="0" step="1000" placeholder="0 (Kosongkan jika tanpa batas anggaran)" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Isi nominal pagu anggaran untuk memantau persentase serapan biaya.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-2">Warna Penanda & Grafik</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <div id="catColorSwatches" class="flex flex-wrap items-center gap-2"></div>
                        <label class="flex items-center gap-1.5 text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded-lg cursor-pointer transition border border-slate-200">
                            <input type="color" id="catColor" value="#0284c7" oninput="syncColorSwatchSelection(this.value)" class="w-5 h-5 rounded cursor-pointer border-0 bg-transparent p-0">
                            <span>Custom</span>
                        </label>
                    </div>
                </div>
                <label class="flex items-center gap-2.5 text-xs font-medium text-slate-700 bg-slate-50 border border-slate-200 rounded-xl p-3 cursor-pointer">
                    <input type="checkbox" id="catActive" checked class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>Kategori Aktif (tampil dalam pilihan saat input pemakaian dana baru)</span>
                </label>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeCategoryModal()" class="px-4 py-2 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" id="catSubmitBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-check"></i>
                        <span>Simpan Kategori</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Kategori -->
    <div id="deleteCategoryModal" onclick="if(event.target === this) closeDeleteCategoryModal()" class="fixed inset-0 z-[55] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-rose-950 text-white px-5 py-4 flex items-center justify-between">
                <h3 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                    <span>Hapus Kategori Biaya</span>
                </h3>
                <button type="button" onclick="closeDeleteCategoryModal()" class="text-rose-200 hover:text-white transition p-1" title="Tutup">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div class="flex items-center gap-2.5 bg-slate-50 border border-slate-200 rounded-xl p-3">
                    <span id="deleteCatColorDot" class="w-3.5 h-3.5 rounded-full flex-shrink-0"></span>
                    <div>
                        <div id="deleteCatNameDisplay" class="font-bold text-sm text-slate-900"></div>
                        <div id="deleteCatDescDisplay" class="text-[11px] text-slate-500"></div>
                    </div>
                </div>

                <p id="deleteCatMessage" class="text-xs text-slate-600 leading-relaxed"></p>

                <div id="deleteCatReassignWrapper" class="hidden bg-amber-50 border border-amber-200 rounded-xl p-3 space-y-2">
                    <label for="deleteCatReassignSelect" class="block text-xs font-semibold text-amber-900">
                        Pindahkan transaksi ke kategori:
                    </label>
                    <select id="deleteCatReassignSelect" class="w-full px-3 py-2 border border-amber-300 rounded-lg text-xs bg-white text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"></select>
                </div>

                <div id="deleteCatBlockedAlert" class="hidden bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-800 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info mt-0.5 text-rose-600"></i>
                    <span>Tambahkan minimal satu kategori pengganti terlebih dahulu sebelum menghapus kategori terakhir yang masih memiliki transaksi.</span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeDeleteCategoryModal()" class="px-4 py-2 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="button" id="confirmDeleteCatBtn" onclick="confirmDeleteCategory()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-trash-can"></i>
                        <span>Ya, Hapus</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
