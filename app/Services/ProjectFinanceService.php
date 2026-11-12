<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\InvestorFund;
use App\Models\KasbonTransaction;
use App\Models\Project;
use App\Models\ProjectCategorySetting;
use Illuminate\Support\Facades\DB;

class ProjectFinanceService
{
    public const DEFAULT_PROJECT_NAME = 'Project Pembangunan Ruko 3 Lantai';
    public const UNCATEGORIZED_LABEL = 'Belum Dikategorikan';

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
            $this->seedDemoDataIntoProject($project);

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
}
