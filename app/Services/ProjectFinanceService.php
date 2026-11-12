<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\InvestorFund;
use App\Models\KasbonTransaction;
use App\Models\Project;
use App\Models\ProjectCategorySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProjectFinanceService
{
    public const DEFAULT_PROJECT_NAME = 'Project Pembangunan Ruko 3 Lantai';
    public const UNCATEGORIZED_LABEL = 'Belum Dikategorikan';
    public const SEED_SNAPSHOT_RELATIVE_PATH = 'seeders/data/project_seed.json';

    public const DEFAULT_CATEGORIES = [
        ['name' => 'Material', 'description' => 'Material / Bahan Bangunan', 'color' => '#0284c7'],
        ['name' => 'Tenaga Kerja', 'description' => 'Tenaga Kerja / Upah Tukang', 'color' => '#8b5cf6'],
        ['name' => 'Sewa Alat', 'description' => 'Sewa Alat & Mesin', 'color' => '#f59e0b'],
        ['name' => 'Transportasi & BBM', 'description' => 'Transportasi & BBM', 'color' => '#10b981'],
        ['name' => 'Perizinan', 'description' => 'Perizinan & Coordination', 'color' => '#ec4899'],
        ['name' => 'Operasional & Konsumsi', 'description' => 'Operasional & Konsumsi Lapangan', 'color' => '#64748b'],
    ];

    public const DEMO_CATEGORY_BUDGETS = [
        'Material' => 80000000,
        'Tenaga Kerja' => 60000000,
        'Sewa Alat' => 20000000,
        'Transportasi & BBM' => 15000000,
        'Perizinan' => 10000000,
        'Operasional & Konsumsi' => 15000000,
    ];

    public function getOrInitializeProject(): Project
    {
        $project = Project::query()->orderBy('id')->first();
        if ($project) {
            $this->ensureSettingsRow($project);
            return $project;
        }

        return DB::transaction(function () {
            $existing = Project::query()->orderBy('id')->first();
            if ($existing) {
                $this->ensureSettingsRow($existing);
                return $existing;
            }

            $project = Project::create(['name' => self::DEFAULT_PROJECT_NAME]);
            if (app()->runningUnitTests() || !$this->seedFromSnapshotFile($project)) {
                $this->seedDemoDataIntoProject($project);
            }

            return $project;
        });
    }

    public function ensureSettingsRow(Project $project): ProjectCategorySetting
    {
        return ProjectCategorySetting::firstOrCreate(
            ['project_id' => $project->id],
            [
                'default_category_id' => null,
                'sort_by' => 'manual',
                'show_category_section' => true,
                'budget_alert_percent' => 80,
            ]
        );
    }

    public function loadDemoData(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $this->seedDemoDataIntoProject($project);
        });
    }

    protected function seedDemoDataIntoProject(Project $project): void
    {
        $today = now()->toDateString();

        $project->update(['name' => self::DEFAULT_PROJECT_NAME]);

        $setting = $this->ensureSettingsRow($project);
        $setting->update([
            'default_category_id' => null,
            'sort_by' => 'manual',
            'show_category_section' => true,
            'budget_alert_percent' => 80,
        ]);

        Expense::where('project_id', $project->id)->delete();
        KasbonTransaction::where('project_id', $project->id)->delete();
        InvestorFund::where('project_id', $project->id)->delete();
        Category::where('project_id', $project->id)->delete();

        $catMap = [];
        foreach (self::DEFAULT_CATEGORIES as $idx => $cat) {
            $created = Category::create([
                'project_id' => $project->id,
                'name' => $cat['name'],
                'description' => $cat['description'],
                'color' => $cat['color'],
                'budget' => self::DEMO_CATEGORY_BUDGETS[$cat['name']] ?? 0,
                'is_active' => true,
                'sort_order' => $idx,
            ]);
            $catMap[$cat['name']] = $created->id;
        }

        // Insert reverse order so newest ID appears at top identically to JS unshift()
        $investors = [
            ['transaction_date' => $today, 'investor_name' => 'PT. Capital Utama (Bpk. Hendra)', 'amount' => 50000000, 'description' => 'Dana Tambahan Operasional Awal'],
            ['transaction_date' => $today, 'investor_name' => 'PT. Capital Utama (Bpk. Hendra)', 'amount' => 150000000, 'description' => 'Dana Modal Term 1 (DP Project)'],
        ];
        foreach ($investors as $inv) {
            InvestorFund::create(['project_id' => $project->id, ...$inv]);
        }

        $kasbons = [
            ['transaction_date' => $today, 'pelaksana' => 'Moh. Ashabil', 'amount' => 25000000, 'description' => 'Kasbon Pembayaran Upah Tukang & Transport'],
            ['transaction_date' => $today, 'pelaksana' => 'Moh. Rengga', 'amount' => 35000000, 'description' => 'Kasbon Belanja Bahan Bangunan Utama'],
        ];
        foreach ($kasbons as $kas) {
            KasbonTransaction::create(['project_id' => $project->id, ...$kas]);
        }

        $expenses = [
            ['category' => 'Operasional & Konsumsi', 'transaction_date' => $today, 'pelaksana' => 'Moh. Rengga', 'payment_method' => 'Tunai Kasbon', 'title' => 'Konsumsi Harian Pekerja & Air Mineral', 'qty' => 7, 'unit' => 'hari', 'unit_price' => 150000, 'total_amount' => 1050000, 'vendor' => 'Warung Bu Siti', 'proof_number' => 'Nota Manual #02', 'validation_status' => 'Valid'],
            ['category' => 'Transportasi & BBM', 'transaction_date' => $today, 'pelaksana' => 'Moh. Ashabil', 'payment_method' => 'Tunai Kasbon', 'title' => 'BBM Pickup & Sewa Mobil Logistik', 'qty' => 5, 'unit' => 'hari', 'unit_price' => 350000, 'total_amount' => 1750000, 'vendor' => 'Rental Transport', 'proof_number' => 'STR-0912', 'validation_status' => 'Perlu Nota'],
            ['category' => 'Tenaga Kerja', 'transaction_date' => $today, 'pelaksana' => 'Moh. Ashabil', 'payment_method' => 'Transfer Pelaksana', 'title' => 'Upah Minggu 1 Tukang & Kenek (6 Org)', 'qty' => 1, 'unit' => 'paket', 'unit_price' => 12500000, 'total_amount' => 12500000, 'vendor' => 'Mandor Supri', 'proof_number' => 'TRF-BCA-9821', 'validation_status' => 'Valid'],
            ['category' => 'Material', 'transaction_date' => $today, 'pelaksana' => 'Moh. Rengga', 'payment_method' => 'Tunai Kasbon', 'title' => 'Besi Beton 12mm Full', 'qty' => 150, 'unit' => 'pcs', 'unit_price' => 115000, 'total_amount' => 17250000, 'vendor' => 'Toko Besi Sentosa', 'proof_number' => 'INV/2026/004', 'validation_status' => 'Valid'],
            ['category' => 'Material', 'transaction_date' => $today, 'pelaksana' => 'Moh. Rengga', 'payment_method' => 'Tunai Kasbon', 'title' => 'Semen Tiga Roda 50kg', 'qty' => 100, 'unit' => 'sak', 'unit_price' => 65000, 'total_amount' => 6500000, 'vendor' => 'TB. Jaya Makmur', 'proof_number' => 'INV/2026/001', 'validation_status' => 'Valid'],
        ];

        foreach ($expenses as $exp) {
            $categoryId = $catMap[$exp['category']] ?? null;
            unset($exp['category']);
            Expense::create([
                'project_id' => $project->id,
                'category_id' => $categoryId,
                ...$exp,
            ]);
        }
    }

    public function resetAllData(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $project->update(['name' => self::DEFAULT_PROJECT_NAME]);

            $setting = $this->ensureSettingsRow($project);
            $setting->update([
                'default_category_id' => null,
                'sort_by' => 'manual',
                'show_category_section' => true,
                'budget_alert_percent' => 80,
            ]);

            Expense::where('project_id', $project->id)->delete();
            KasbonTransaction::where('project_id', $project->id)->delete();
            InvestorFund::where('project_id', $project->id)->delete();
            Category::where('project_id', $project->id)->delete();

            foreach (self::DEFAULT_CATEGORIES as $idx => $cat) {
                Category::create([
                    'project_id' => $project->id,
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'color' => $cat['color'],
                    'budget' => 0,
                    'is_active' => true,
                    'sort_order' => $idx,
                ]);
            }
        });
    }

    public function resetCategoriesToDefault(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $existingExpenses = Expense::with('category')
                ->where('project_id', $project->id)
                ->get();

            $expenseOldCatName = [];
            foreach ($existingExpenses as $exp) {
                $expenseOldCatName[$exp->id] = $exp->category?->name;
            }

            $setting = $this->ensureSettingsRow($project);
            $setting->update([
                'default_category_id' => null,
                'sort_by' => 'manual',
                'show_category_section' => true,
                'budget_alert_percent' => 80,
            ]);

            Expense::where('project_id', $project->id)->update(['category_id' => null]);
            Category::where('project_id', $project->id)->delete();

            $newMap = [];
            foreach (self::DEFAULT_CATEGORIES as $idx => $cat) {
                $created = Category::create([
                    'project_id' => $project->id,
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'color' => $cat['color'],
                    'budget' => 0,
                    'is_active' => true,
                    'sort_order' => $idx,
                ]);
                $newMap[mb_strtolower($created->name)] = $created->id;
            }

            foreach ($expenseOldCatName as $expenseId => $oldName) {
                if (!$oldName) {
                    continue;
                }
                $key = mb_strtolower(trim($oldName));
                if (isset($newMap[$key])) {
                    Expense::where('id', $expenseId)->update(['category_id' => $newMap[$key]]);
                }
            }
        });
    }

    public function getDashboardState(Project $project): array
    {
        $project->refresh();
        $setting = $this->ensureSettingsRow($project);
        $setting->load('defaultCategory');

        $categories = Category::where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $investors = InvestorFund::where('project_id', $project->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $kasbons = KasbonTransaction::where('project_id', $project->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $expenses = Expense::with('category')
            ->where('project_id', $project->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $defaultCategoryName = '';
        if ($setting->defaultCategory && $setting->defaultCategory->is_active) {
            $defaultCategoryName = $setting->defaultCategory->name;
        }

        return [
            'projectId' => (string) $project->id,
            'projectName' => $project->name,
            'categories' => $categories->map(fn (Category $cat) => [
                'id' => (string) $cat->id,
                'name' => $cat->name,
                'desc' => $cat->description ?? '',
                'color' => $cat->color,
                'budget' => (float) $cat->budget,
                'isActive' => (bool) $cat->is_active,
                'sortOrder' => (int) $cat->sort_order,
            ])->values()->all(),
            'categorySettings' => [
                'defaultCategory' => $defaultCategoryName,
                'sortBy' => in_array($setting->sort_by, ['manual', 'name', 'amount_desc'], true) ? $setting->sort_by : 'manual',
                'showCategorySection' => (bool) $setting->show_category_section,
                'budgetAlertPercent' => (int) $setting->budget_alert_percent,
            ],
            'investorFunds' => $investors->map(fn (InvestorFund $inv) => [
                'id' => (string) $inv->id,
                'date' => $inv->transaction_date?->format('Y-m-d') ?? '',
                'investor' => $inv->investor_name,
                'amount' => (float) $inv->amount,
                'desc' => $inv->description ?? '-',
            ])->values()->all(),
            'kasbonList' => $kasbons->map(fn (KasbonTransaction $kas) => [
                'id' => (string) $kas->id,
                'date' => $kas->transaction_date?->format('Y-m-d') ?? '',
                'pelaksana' => $kas->pelaksana,
                'amount' => (float) $kas->amount,
                'desc' => $kas->description ?? '-',
            ])->values()->all(),
            'expenses' => $expenses->map(fn (Expense $exp) => [
                'id' => (string) $exp->id,
                'date' => $exp->transaction_date?->format('Y-m-d') ?? '',
                'pelaksana' => $exp->pelaksana,
                'category' => $exp->category?->name ?? self::UNCATEGORIZED_LABEL,
                'categoryId' => $exp->category_id ? (string) $exp->category_id : null,
                'payMethod' => $exp->payment_method,
                'title' => $exp->title,
                'qty' => (float) $exp->qty,
                'unit' => $exp->unit,
                'price' => (float) $exp->unit_price,
                'total' => (float) $exp->total_amount,
                'vendor' => $exp->vendor ?? '-',
                'proof' => $exp->proof_number ?? '-',
                'validation' => $exp->validation_status ?? 'Valid',
            ])->values()->all(),
        ];
    }

    /**
     * Resolve target path for the tracked JSON seeder snapshot.
     */
    public function getSeederSnapshotPath(?string $path = null): string
    {
        return $path ?: database_path(self::SEED_SNAPSHOT_RELATIVE_PATH);
    }

    /**
     * Build a portable snapshot array from the current SQL project state.
     */
    public function exportSnapshot(?Project $project = null): array
    {
        $target = $project ?? $this->getOrInitializeProject();
        $state = $this->getDashboardState($target);

        return [
            'version' => 1,
            'exportedAt' => now()->toIso8601String(),
            'projectName' => $state['projectName'],
            'categories' => $state['categories'],
            'categorySettings' => $state['categorySettings'],
            'investorFunds' => $state['investorFunds'],
            'kasbonList' => $state['kasbonList'],
            'expenses' => $state['expenses'],
        ];
    }

    /**
     * Persist the current SQL project state to the tracked JSON seeder snapshot file.
     */
    public function writeSeederSnapshot(?Project $project = null, ?string $path = null): string
    {
        $targetPath = $this->getSeederSnapshotPath($path);
        File::ensureDirectoryExists(dirname($targetPath));

        $snapshot = $this->exportSnapshot($project);
        File::put(
            $targetPath,
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        return $targetPath;
    }

    /**
     * Seed a project from the tracked JSON snapshot file if present and valid.
     */
    public function seedFromSnapshotFile(Project $project, ?string $path = null): bool
    {
        $targetPath = $this->getSeederSnapshotPath($path);
        if (!File::isFile($targetPath)) {
            return false;
        }

        $decoded = $this->decodeSnapshotJson(File::get($targetPath));
        if ($decoded === null) {
            return false;
        }

        $this->importSnapshot($decoded, $project);

        return true;
    }

    /**
     * Decode raw JSON (including double-encoded localStorage strings) into a snapshot array.
     */
    public function decodeSnapshotJson(string $rawJson): ?array
    {
        $trimmed = trim($rawJson);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Import a project snapshot (Laravel snapshot or legacy localStorage state) into SQL atomically.
     */
    public function importSnapshot(array $payload, ?Project $project = null): Project
    {
        return DB::transaction(function () use ($payload, $project) {
            $target = $project ?? Project::query()->orderBy('id')->first();
            $projectName = trim((string) ($payload['projectName'] ?? $payload['name'] ?? self::DEFAULT_PROJECT_NAME));
            if ($projectName === '') {
                $projectName = self::DEFAULT_PROJECT_NAME;
            }

            if ($target) {
                $target->update(['name' => mb_substr($projectName, 0, 255)]);
            } else {
                $target = Project::create(['name' => mb_substr($projectName, 0, 255)]);
            }

            Expense::where('project_id', $target->id)->delete();
            KasbonTransaction::where('project_id', $target->id)->delete();
            InvestorFund::where('project_id', $target->id)->delete();
            Category::where('project_id', $target->id)->delete();

            $categoriesInput = is_array($payload['categories'] ?? null) ? $payload['categories'] : [];
            if (empty($categoriesInput)) {
                $categoriesInput = self::DEFAULT_CATEGORIES;
            }

            $catIdByName = [];
            $catIdByLegacyId = [];
            $seenNames = [];

            foreach ($categoriesInput as $idx => $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $name = trim((string) ($cat['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $name = mb_substr($name, 0, 50);
                $lower = mb_strtolower($name);
                if (isset($seenNames[$lower])) {
                    continue;
                }
                $seenNames[$lower] = true;

                $created = Category::create([
                    'project_id' => $target->id,
                    'name' => $name,
                    'description' => mb_substr((string) ($cat['desc'] ?? $cat['description'] ?? ''), 0, 80) ?: null,
                    'color' => mb_substr((string) ($cat['color'] ?? '#0284c7'), 0, 20) ?: '#0284c7',
                    'budget' => max(0, (float) ($cat['budget'] ?? 0)),
                    'is_active' => array_key_exists('isActive', $cat)
                        ? (bool) $cat['isActive']
                        : (bool) ($cat['is_active'] ?? true),
                    'sort_order' => (int) ($cat['sortOrder'] ?? $cat['sort_order'] ?? $idx),
                ]);

                $catIdByName[$lower] = $created->id;
                if (isset($cat['id']) && $cat['id'] !== '') {
                    $catIdByLegacyId[(string) $cat['id']] = $created->id;
                }
            }

            $settingsInput = is_array($payload['categorySettings'] ?? null) ? $payload['categorySettings'] : [];
            $defaultCatName = mb_strtolower(trim((string) ($settingsInput['defaultCategory'] ?? '')));
            $defaultCatId = $defaultCatName !== '' ? ($catIdByName[$defaultCatName] ?? null) : null;
            $sortBy = (string) ($settingsInput['sortBy'] ?? 'manual');
            if (!in_array($sortBy, ['manual', 'name', 'amount_desc'], true)) {
                $sortBy = 'manual';
            }
            $alertPct = max(10, min(100, (int) ($settingsInput['budgetAlertPercent'] ?? 80)));

            $setting = $this->ensureSettingsRow($target);
            $setting->update([
                'default_category_id' => $defaultCatId,
                'sort_by' => $sortBy,
                'show_category_section' => array_key_exists('showCategorySection', $settingsInput)
                    ? (bool) $settingsInput['showCategorySection']
                    : true,
                'budget_alert_percent' => $alertPct,
            ]);

            $investors = is_array($payload['investorFunds'] ?? null) ? $payload['investorFunds'] : [];
            foreach (array_reverse($investors) as $inv) {
                if (!is_array($inv)) {
                    continue;
                }
                $amount = max(0, (float) ($inv['amount'] ?? 0));
                $investorName = trim((string) ($inv['investor'] ?? $inv['investor_name'] ?? ''));
                if ($investorName === '' || $amount <= 0) {
                    continue;
                }

                InvestorFund::create([
                    'project_id' => $target->id,
                    'transaction_date' => $this->normalizeSnapshotDate($inv['date'] ?? $inv['transaction_date'] ?? null),
                    'investor_name' => mb_substr($investorName, 0, 150),
                    'amount' => $amount,
                    'description' => mb_substr((string) ($inv['desc'] ?? $inv['description'] ?? '-'), 0, 255) ?: '-',
                ]);
            }

            $kasbons = is_array($payload['kasbonList'] ?? null) ? $payload['kasbonList'] : [];
            foreach (array_reverse($kasbons) as $kas) {
                if (!is_array($kas)) {
                    continue;
                }
                $amount = max(0, (float) ($kas['amount'] ?? 0));
                $pelaksana = trim((string) ($kas['pelaksana'] ?? ''));
                if ($pelaksana === '' || $amount <= 0) {
                    continue;
                }

                KasbonTransaction::create([
                    'project_id' => $target->id,
                    'transaction_date' => $this->normalizeSnapshotDate($kas['date'] ?? $kas['transaction_date'] ?? null),
                    'pelaksana' => mb_substr($pelaksana, 0, 100),
                    'amount' => $amount,
                    'description' => mb_substr((string) ($kas['desc'] ?? $kas['description'] ?? '-'), 0, 255) ?: '-',
                ]);
            }

            $expenses = is_array($payload['expenses'] ?? null) ? $payload['expenses'] : [];
            foreach (array_reverse($expenses) as $exp) {
                if (!is_array($exp)) {
                    continue;
                }
                $title = trim((string) ($exp['title'] ?? ''));
                $pelaksana = trim((string) ($exp['pelaksana'] ?? ''));
                if ($title === '' || $pelaksana === '') {
                    continue;
                }

                $qty = max(0, (float) ($exp['qty'] ?? 0));
                $price = max(0, (float) ($exp['price'] ?? $exp['unit_price'] ?? 0));
                $total = isset($exp['total']) ? (float) $exp['total'] : round($qty * $price, 2);

                $catId = null;
                $legacyCatId = isset($exp['categoryId']) ? (string) $exp['categoryId'] : '';
                $catName = trim((string) ($exp['category'] ?? ''));
                if ($legacyCatId !== '' && isset($catIdByLegacyId[$legacyCatId])) {
                    $catId = $catIdByLegacyId[$legacyCatId];
                } elseif ($catName !== '' && mb_strtolower($catName) !== mb_strtolower(self::UNCATEGORIZED_LABEL)) {
                    $catId = $catIdByName[mb_strtolower($catName)] ?? null;
                }

                Expense::create([
                    'project_id' => $target->id,
                    'category_id' => $catId,
                    'transaction_date' => $this->normalizeSnapshotDate($exp['date'] ?? $exp['transaction_date'] ?? null),
                    'pelaksana' => mb_substr($pelaksana, 0, 100),
                    'payment_method' => mb_substr((string) ($exp['payMethod'] ?? $exp['payment_method'] ?? 'Tunai Kasbon'), 0, 60) ?: 'Tunai Kasbon',
                    'title' => mb_substr($title, 0, 255),
                    'qty' => $qty,
                    'unit' => mb_substr((string) ($exp['unit'] ?? 'unit'), 0, 40) ?: 'unit',
                    'unit_price' => $price,
                    'total_amount' => max(0, $total),
                    'vendor' => mb_substr((string) ($exp['vendor'] ?? '-'), 0, 150) ?: '-',
                    'proof_number' => mb_substr((string) ($exp['proof'] ?? $exp['proof_number'] ?? '-'), 0, 150) ?: '-',
                    'validation_status' => mb_substr((string) ($exp['validation'] ?? $exp['validation_status'] ?? 'Valid'), 0, 30) ?: 'Valid',
                ]);
            }

            return $target->fresh();
        });
    }

    private function normalizeSnapshotDate(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return now()->toDateString();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m)) {
            return $m[0];
        }
        $ts = strtotime($raw);

        return $ts !== false ? date('Y-m-d', $ts) : now()->toDateString();
    }
}
