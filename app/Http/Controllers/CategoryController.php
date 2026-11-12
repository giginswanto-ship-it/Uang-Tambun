<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Services\ProjectFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function __construct(
        protected ProjectFinanceService $service
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'desc' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $name = trim($validated['name']);
        if ($name === '' || mb_strtolower($name) === mb_strtolower(ProjectFinanceService::UNCATEGORIZED_LABEL)) {
            return response()->json([
                'ok' => false,
                'message' => 'Masukkan nama kategori yang valid!',
            ], 422);
        }

        $project = $this->service->getOrInitializeProject();

        $duplicate = Category::where('project_id', $project->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($duplicate) {
            return response()->json([
                'ok' => false,
                'message' => "Kategori \"{$duplicate->name}\" sudah ada!",
            ], 422);
        }

        $nextOrder = (int) (Category::where('project_id', $project->id)->max('sort_order') ?? -1) + 1;

        $category = Category::create([
            'project_id' => $project->id,
            'name' => $name,
            'description' => trim((string) ($validated['desc'] ?? '')),
            'color' => trim((string) ($validated['color'] ?? '')) ?: '#0284c7',
            'budget' => max(0, (float) ($validated['budget'] ?? 0)),
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => $nextOrder,
        ]);

        return response()->json([
            'ok' => true,
            'categoryId' => (string) $category->id,
            'categoryName' => $category->name,
            'state' => $this->service->getDashboardState($project),
        ], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($category->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'desc' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $name = trim($validated['name']);
        if ($name === '' || mb_strtolower($name) === mb_strtolower(ProjectFinanceService::UNCATEGORIZED_LABEL)) {
            return response()->json([
                'ok' => false,
                'message' => 'Masukkan nama kategori yang valid!',
            ], 422);
        }

        $duplicate = Category::where('project_id', $project->id)
            ->where('id', '!=', $category->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($duplicate) {
            return response()->json([
                'ok' => false,
                'message' => "Kategori \"{$duplicate->name}\" sudah ada!",
            ], 422);
        }

        $isActive = (bool) ($validated['isActive'] ?? true);

        DB::transaction(function () use ($project, $category, $validated, $name, $isActive) {
            $category->update([
                'name' => $name,
                'description' => trim((string) ($validated['desc'] ?? '')),
                'color' => trim((string) ($validated['color'] ?? '')) ?: '#0284c7',
                'budget' => max(0, (float) ($validated['budget'] ?? 0)),
                'is_active' => $isActive,
            ]);

            if (!$isActive) {
                $setting = $this->service->ensureSettingsRow($project);
                if ($setting->default_category_id === $category->id) {
                    $setting->update(['default_category_id' => null]);
                }
            }
        });

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($category->project_id !== $project->id, 404);

        $usedCount = Expense::where('project_id', $project->id)
            ->where('category_id', $category->id)
            ->count();

        $replacement = null;
        if ($usedCount > 0) {
            $repId = $request->input('replacementCategoryId');
            $repName = trim((string) $request->input('replacementCategoryName', ''));

            if ($repId) {
                $replacement = Category::where('project_id', $project->id)
                    ->where('id', '!=', $category->id)
                    ->find($repId);
            } elseif ($repName !== '') {
                $replacement = Category::where('project_id', $project->id)
                    ->where('id', '!=', $category->id)
                    ->where('name', $repName)
                    ->first();
            }

            if (!$replacement) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Pilih kategori pengganti terlebih dahulu!',
                ], 422);
            }
        }

        DB::transaction(function () use ($project, $category, $replacement) {
            if ($replacement) {
                Expense::where('project_id', $project->id)
                    ->where('category_id', $category->id)
                    ->update(['category_id' => $replacement->id]);
            }

            $setting = $this->service->ensureSettingsRow($project);
            if ($setting->default_category_id === $category->id) {
                $setting->update(['default_category_id' => null]);
            }

            $category->delete();
        });

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function saveSettingsBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'defaultCategory' => ['nullable', 'string', 'max:50'],
            'sortBy' => ['required', 'in:manual,name,amount_desc'],
            'budgetAlertPercent' => ['required', 'integer', 'min:1', 'max:100'],
            'showCategorySection' => ['required', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*.id' => ['required'],
            'categories.*.budget' => ['required', 'numeric', 'min:0'],
            'categories.*.isActive' => ['required', 'boolean'],
            'categories.*.sortOrder' => ['nullable', 'integer', 'min:0'],
        ]);

        $project = $this->service->getOrInitializeProject();

        DB::transaction(function () use ($project, $validated) {
            if (!empty($validated['categories'])) {
                foreach ($validated['categories'] as $idx => $catInput) {
                    $category = Category::where('project_id', $project->id)->find($catInput['id']);
                    if ($category) {
                        $category->update([
                            'budget' => max(0, (float) $catInput['budget']),
                            'is_active' => (bool) $catInput['isActive'],
                            'sort_order' => isset($catInput['sortOrder']) ? (int) $catInput['sortOrder'] : $idx,
                        ]);
                    }
                }
            }

            $defaultCatName = trim((string) ($validated['defaultCategory'] ?? ''));
            $defaultCatId = null;
            if ($defaultCatName !== '') {
                $defaultCat = Category::where('project_id', $project->id)
                    ->where('is_active', true)
                    ->where('name', $defaultCatName)
                    ->first();
                $defaultCatId = $defaultCat?->id;
            }

            $setting = $this->service->ensureSettingsRow($project);
            $setting->update([
                'default_category_id' => $defaultCatId,
                'sort_by' => $validated['sortBy'],
                'budget_alert_percent' => (int) $validated['budgetAlertPercent'],
                'show_category_section' => (bool) $validated['showCategorySection'],
            ]);
        });

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function resetDefaults(): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        $this->service->resetCategoriesToDefault($project);

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }
}
