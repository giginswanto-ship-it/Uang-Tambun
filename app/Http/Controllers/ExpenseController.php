<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Services\ProjectFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        protected ProjectFinanceService $service
    ) {}

    protected function resolveCategoryId(int $projectId, ?string $categoryName): ?int
    {
        $trimmed = trim((string) ($categoryName ?? ''));
        if ($trimmed === '' || mb_strtolower($trimmed) === mb_strtolower(ProjectFinanceService::UNCATEGORIZED_LABEL)) {
            return null;
        }

        $category = Category::where('project_id', $projectId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmed)])
            ->first();

        return $category?->id;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'pelaksana' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'payMethod' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'proof' => ['nullable', 'string', 'max:150'],
            'validation' => ['required', 'in:Valid,Perlu Nota,Revisi'],
        ]);

        $project = $this->service->getOrInitializeProject();
        $categoryId = $this->resolveCategoryId($project->id, $validated['category'] ?? null);
        $qty = (float) $validated['qty'];
        $price = (float) $validated['price'];

        $expense = Expense::create([
            'project_id' => $project->id,
            'category_id' => $categoryId,
            'transaction_date' => $validated['date'],
            'pelaksana' => trim($validated['pelaksana']),
            'payment_method' => trim($validated['payMethod']),
            'title' => trim($validated['title']),
            'qty' => $qty,
            'unit' => trim($validated['unit']),
            'unit_price' => $price,
            'total_amount' => round($qty * $price, 2),
            'vendor' => trim((string) ($validated['vendor'] ?? '')) ?: '-',
            'proof_number' => trim((string) ($validated['proof'] ?? '')) ?: '-',
            'validation_status' => $validated['validation'],
        ]);

        $categoryValue = $categoryId
            ? ($expense->category?->name ?? ProjectFinanceService::UNCATEGORIZED_LABEL)
            : ProjectFinanceService::UNCATEGORIZED_LABEL;

        return response()->json([
            'ok' => true,
            'id' => (string) $expense->id,
            'category' => $categoryValue,
            'state' => $this->service->getDashboardState($project),
        ], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($expense->project_id !== $project->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'pelaksana' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'payMethod' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'proof' => ['nullable', 'string', 'max:150'],
            'validation' => ['required', 'in:Valid,Perlu Nota,Revisi'],
        ]);

        $categoryId = $this->resolveCategoryId($project->id, $validated['category'] ?? null);
        $qty = (float) $validated['qty'];
        $price = (float) $validated['price'];

        $expense->update([
            'category_id' => $categoryId,
            'transaction_date' => $validated['date'],
            'pelaksana' => trim($validated['pelaksana']),
            'payment_method' => trim($validated['payMethod']),
            'title' => trim($validated['title']),
            'qty' => $qty,
            'unit' => trim($validated['unit']),
            'unit_price' => $price,
            'total_amount' => round($qty * $price, 2),
            'vendor' => trim((string) ($validated['vendor'] ?? '')) ?: '-',
            'proof_number' => trim((string) ($validated['proof'] ?? '')) ?: '-',
            'validation_status' => $validated['validation'],
        ]);

        $expense->load('category');
        $categoryValue = $expense->category?->name ?? ProjectFinanceService::UNCATEGORIZED_LABEL;

        return response()->json([
            'ok' => true,
            'id' => (string) $expense->id,
            'category' => $categoryValue,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function updateCategory(Request $request, Expense $expense): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($expense->project_id !== $project->id, 404);

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $categoryId = $this->resolveCategoryId($project->id, $validated['category'] ?? null);
        $expense->update(['category_id' => $categoryId]);
        $expense->load('category');

        return response()->json([
            'ok' => true,
            'category' => $expense->category?->name ?? ProjectFinanceService::UNCATEGORIZED_LABEL,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function cycleValidation(Expense $expense): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($expense->project_id !== $project->id, 404);

        $next = match ($expense->validation_status) {
            'Valid' => 'Perlu Nota',
            'Perlu Nota' => 'Revisi',
            default => 'Valid',
        };

        $expense->update(['validation_status' => $next]);

        return response()->json([
            'ok' => true,
            'validation' => $next,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($expense->project_id !== $project->id, 404);

        $expense->delete();

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }
}
