<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\InvestorFund;
use App\Models\KasbonTransaction;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_dashboard_seeds_sql_tables_and_renders_design_system(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $response->assertSee('window.__INITIAL_STATE__', false);
        $response->assertSee('bg-slate-900', false);
        $response->assertSee('Atur Budget Kategori');
        $response->assertSee('Pengaturan Kategori');
        $response->assertSee('Masuk Kategori Mana?');

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseCount('project_category_settings', 1);
        $this->assertDatabaseCount('investor_funds', 2);
        $this->assertDatabaseCount('kasbon_transactions', 2);
        $this->assertDatabaseCount('expenses', 5);

        $stateRes = $this->getJson(route('api.state'));
        $stateRes->assertOk()->assertJsonPath('ok', true);
        $state = $stateRes->json('state');
        $this->assertEquals(
            200000000.0,
            array_sum(array_map(fn ($c) => $c['isActive'] ? $c['budget'] : 0, $state['categories']))
        );
    }

    public function test_project_name_update_and_reset_vs_demo_in_sql(): void
    {
        $this->get('/');

        $this->patchJson(route('api.project.update'), [
            'name' => 'Ruko Tambun Tahap II',
        ])->assertOk()->assertJsonPath('state.projectName', 'Ruko Tambun Tahap II');

        $this->assertDatabaseHas('projects', ['name' => 'Ruko Tambun Tahap II']);

        $this->postJson(route('api.project.reset'))
            ->assertOk()
            ->assertJsonCount(0, 'state.expenses')
            ->assertJsonCount(0, 'state.kasbonList')
            ->assertJsonCount(0, 'state.investorFunds')
            ->assertJsonCount(6, 'state.categories');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertEquals(0.0, (float) Category::sum('budget'));

        // Re-fetching state after reset stays empty (no auto demo re-seed)
        $this->getJson(route('api.state'))
            ->assertOk()
            ->assertJsonCount(0, 'state.expenses');

        $this->postJson(route('api.project.demo'))
            ->assertOk()
            ->assertJsonCount(5, 'state.expenses')
            ->assertJsonCount(2, 'state.investorFunds')
            ->assertJsonCount(2, 'state.kasbonList');

        $this->assertEquals(200000000.0, (float) Category::sum('budget'));
    }

    public function test_category_crud_batch_settings_reassignment_and_reset(): void
    {
        $this->get('/');

        // Reject duplicate case-insensitive category
        $this->postJson(route('api.categories.store'), [
            'name' => 'material',
            'budget' => 1000000,
        ])->assertStatus(422);

        // Create category
        $createRes = $this->postJson(route('api.categories.store'), [
            'name' => 'Finishing Interior',
            'desc' => 'Cat & Plafon',
            'color' => '#ef4444',
            'budget' => 25000000,
            'isActive' => true,
        ])->assertCreated();
        $newCatId = $createRes->json('categoryId');
        $this->assertDatabaseHas('categories', ['id' => $newCatId, 'name' => 'Finishing Interior']);

        // Batch settings update persists general settings + inline budgets + order + active flags atomically
        $categories = Category::orderBy('sort_order')->get();
        $reversed = $categories->reverse()->values();
        $payloadCats = $reversed->map(fn ($cat, $idx) => [
            'id' => (string) $cat->id,
            'budget' => $cat->name === 'Material' ? 10000000 : (float) $cat->budget,
            'isActive' => $cat->name !== 'Perizinan',
            'sortOrder' => $idx,
        ])->all();

        $batchRes = $this->putJson(route('api.categories.settings.batch'), [
            'defaultCategory' => 'Finishing Interior',
            'sortBy' => 'amount_desc',
            'budgetAlertPercent' => 75,
            'showCategorySection' => false,
            'categories' => $payloadCats,
        ])->assertOk();

        $batchRes->assertJsonPath('state.categorySettings.defaultCategory', 'Finishing Interior')
            ->assertJsonPath('state.categorySettings.sortBy', 'amount_desc')
            ->assertJsonPath('state.categorySettings.budgetAlertPercent', 75)
            ->assertJsonPath('state.categorySettings.showCategorySection', false);

        $this->assertDatabaseHas('categories', ['name' => 'Material', 'budget' => 10000000]);
        $this->assertDatabaseHas('categories', ['name' => 'Perizinan', 'is_active' => false]);

        // Rename category keeps linked expenses intact via SQL foreign key
        $material = Category::where('name', 'Material')->firstOrFail();
        $materialExpCount = Expense::where('category_id', $material->id)->count();
        $this->assertGreaterThan(0, $materialExpCount);

        $this->putJson("/api/categories/{$material->id}", [
            'name' => 'Bahan Bangunan Utama',
            'desc' => 'Semen, Besi, dll',
            'color' => '#0284c7',
            'budget' => 12000000,
            'isActive' => true,
        ])->assertOk();

        $this->assertEquals(
            $materialExpCount,
            Expense::where('category_id', $material->id)->count()
        );

        // Delete requires replacement if expenses exist
        $this->deleteJson("/api/categories/{$material->id}")->assertStatus(422);

        $this->deleteJson("/api/categories/{$material->id}", [
            'replacementCategoryId' => $newCatId,
        ])->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $material->id]);
        $this->assertDatabaseHas('expenses', ['category_id' => $newCatId]);

        // Reset categories to default marks orphaned custom category expense as uncategorized (null)
        $this->postJson(route('api.categories.reset'))
            ->assertOk()
            ->assertJsonCount(6, 'state.categories');

        $this->assertDatabaseHas('expenses', ['category_id' => null]);
    }

    public function test_investor_kasbon_and_expense_crud_and_inline_category_and_csv(): void
    {
        $this->get('/');

        // Investor Create -> Update -> Delete
        $invRes = $this->postJson(route('api.investors.store'), [
            'date' => '2026-11-12',
            'investor' => 'Bpk. Santoso',
            'amount' => 75000000,
            'desc' => 'Term 3',
        ])->assertCreated();
        $invId = $invRes->json('id');

        $this->putJson("/api/investors/{$invId}", [
            'date' => '2026-11-12',
            'investor' => 'Bpk. Santoso Updated',
            'amount' => 80000000,
            'desc' => 'Term 3 Revisi',
        ])->assertOk();
        $this->assertDatabaseHas('investor_funds', ['id' => $invId, 'amount' => 80000000]);

        $this->deleteJson("/api/investors/{$invId}")->assertOk();
        $this->assertNull(InvestorFund::find($invId));

        // Kasbon Create -> Update -> Delete
        $kasRes = $this->postJson(route('api.kasbons.store'), [
            'date' => '2026-11-12',
            'pelaksana' => 'Moh. Rengga',
            'amount' => 15000000,
            'desc' => 'Minggu 2',
        ])->assertCreated();
        $kasId = $kasRes->json('id');

        $this->putJson("/api/kasbons/{$kasId}", [
            'date' => '2026-11-12',
            'pelaksana' => 'Moh. Ashabil',
            'amount' => 18000000,
            'desc' => 'Minggu 2 Revisi',
        ])->assertOk();
        $this->assertDatabaseHas('kasbon_transactions', ['id' => $kasId, 'pelaksana' => 'Moh. Ashabil', 'amount' => 18000000]);

        $this->deleteJson("/api/kasbons/{$kasId}")->assertOk();
        $this->assertNull(KasbonTransaction::find($kasId));

        // Expense Create without category -> Inline Assign Category -> Update -> Validation Toggle -> Delete
        $expRes = $this->postJson(route('api.expenses.store'), [
            'date' => '2026-11-12',
            'pelaksana' => 'Moh. Rengga',
            'category' => 'Belum Dikategorikan',
            'payMethod' => 'Tunai Kasbon',
            'title' => 'Pasir Urug 2 Rit',
            'qty' => 2.5,
            'unit' => 'rit',
            'price' => 800000,
            'vendor' => 'CV Pasir Jaya',
            'proof' => 'SJ-001',
            'validation' => 'Valid',
        ])->assertCreated();
        $expId = $expRes->json('id');

        $this->assertDatabaseHas('expenses', [
            'id' => $expId,
            'category_id' => null,
            'total_amount' => 2000000,
        ]);

        // Inline category assign via Simpan Perubahan
        $materialCat = Category::where('name', 'Material')->firstOrFail();
        $this->patchJson("/api/expenses/{$expId}/category", [
            'category' => 'Material',
        ])->assertOk()->assertJsonPath('category', 'Material');

        $this->assertDatabaseHas('expenses', ['id' => $expId, 'category_id' => $materialCat->id]);

        // Edit full expense
        $this->putJson("/api/expenses/{$expId}", [
            'date' => '2026-11-12',
            'pelaksana' => 'Moh. Rengga',
            'category' => 'Sewa Alat',
            'payMethod' => 'Transfer Pelaksana',
            'title' => 'Pasir Urug Revisi',
            'qty' => 3,
            'unit' => 'rit',
            'price' => 900000,
            'vendor' => 'CV Pasir Jaya',
            'proof' => 'SJ-002',
            'validation' => 'Perlu Nota',
        ])->assertOk()->assertJsonPath('category', 'Sewa Alat');

        // Cycle validation
        $this->patchJson("/api/expenses/{$expId}/validation")
            ->assertOk()
            ->assertJsonPath('validation', 'Revisi');

        // CSV Export
        $csvRes = $this->get(route('export.csv'));
        $csvRes->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvRes->headers->get('content-type'));
        $csvBody = $csvRes->streamedContent();
        $this->assertStringContainsString('LAPORAN DANA MASUK INVESTOR', $csvBody);
        $this->assertStringContainsString('Pasir Urug Revisi', $csvBody);

        // Delete expense
        $this->deleteJson("/api/expenses/{$expId}")->assertOk();
        $this->assertNull(Expense::find($expId));
    }

    public function test_import_local_snapshot_and_seeder_sync_commands(): void
    {
        $this->get('/');

        $legacyLocalPayload = [
            'projectName' => 'Project Tambun Realisasi',
            'categories' => [
                ['id' => 'CAT-1', 'name' => 'Material Khusus', 'desc' => 'Besi & Semen', 'color' => '#0284c7', 'budget' => 95000000, 'isActive' => true],
                ['id' => 'CAT-2', 'name' => 'Jasa Tukang', 'desc' => 'Upah', 'color' => '#8b5cf6', 'budget' => 45000000, 'isActive' => true],
            ],
            'categorySettings' => [
                'defaultCategory' => 'Material Khusus',
                'sortBy' => 'amount_desc',
                'showCategorySection' => true,
                'budgetAlertPercent' => 85,
            ],
            'investorFunds' => [
                ['id' => 'INV-1', 'date' => '2026-11-10', 'investor' => 'Investor Lokal', 'amount' => 120000000, 'desc' => 'Setoran Awal'],
            ],
            'kasbonList' => [
                ['id' => 'KAS-1', 'date' => '2026-11-11', 'pelaksana' => 'Pak Budi', 'amount' => 30000000, 'desc' => 'Kasbon Material'],
            ],
            'expenses' => [
                [
                    'id' => 'EXP-1',
                    'date' => '2026-11-12',
                    'pelaksana' => 'Pak Budi',
                    'category' => 'Material Khusus',
                    'categoryId' => 'CAT-1',
                    'payMethod' => 'Tunai Kasbon',
                    'title' => 'Pembelian Besi Lokal',
                    'qty' => 20,
                    'unit' => 'btg',
                    'price' => 110000,
                    'total' => 2200000,
                    'vendor' => 'TB Tambun',
                    'proof' => 'NF-01',
                    'validation' => 'Valid',
                ],
            ],
            'syncSeeder' => false,
        ];

        $this->postJson(route('api.project.importLocal'), $legacyLocalPayload)
            ->assertOk()
            ->assertJsonPath('state.projectName', 'Project Tambun Realisasi')
            ->assertJsonPath('state.categorySettings.defaultCategory', 'Material Khusus')
            ->assertJsonPath('state.categorySettings.budgetAlertPercent', 85)
            ->assertJsonCount(2, 'state.categories')
            ->assertJsonCount(1, 'state.investorFunds')
            ->assertJsonCount(1, 'state.kasbonList')
            ->assertJsonCount(1, 'state.expenses');

        $this->assertDatabaseHas('projects', ['name' => 'Project Tambun Realisasi']);
        $this->assertDatabaseHas('expenses', ['title' => 'Pembelian Besi Lokal', 'total_amount' => 2200000]);

        $tempPath = storage_path('framework/testing/test_seed_snapshot.json');
        try {
            $this->artisan('finance:export-seeder', ['--path' => $tempPath])->assertExitCode(0);
            $this->assertFileExists($tempPath);

            // Reset DB then import back from temp snapshot file without touching tracked project_seed.json
            $this->postJson(route('api.project.reset'))->assertOk();
            $this->assertDatabaseCount('expenses', 0);

            $this->artisan('finance:import-local', ['file' => $tempPath, '--no-seeder' => true])->assertExitCode(0);
            $this->assertDatabaseHas('projects', ['name' => 'Project Tambun Realisasi']);
            $this->assertDatabaseHas('expenses', ['title' => 'Pembelian Besi Lokal']);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
