<?php

namespace App\Http\Controllers;

use App\Models\KasbonTransaction;
use App\Services\ProjectFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KasbonTransactionController extends Controller
{
    public function __construct(
        protected ProjectFinanceService $service
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'pelaksana' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'desc' => ['nullable', 'string', 'max:255'],
        ]);

        $project = $this->service->getOrInitializeProject();

        $item = KasbonTransaction::create([
            'project_id' => $project->id,
            'transaction_date' => $validated['date'],
            'pelaksana' => trim($validated['pelaksana']),
            'amount' => (float) $validated['amount'],
            'description' => trim((string) ($validated['desc'] ?? '')) ?: '-',
        ]);

        return response()->json([
            'ok' => true,
            'id' => (string) $item->id,
            'state' => $this->service->getDashboardState($project),
        ], 201);
    }

    public function update(Request $request, KasbonTransaction $kasbonTransaction): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($kasbonTransaction->project_id !== $project->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'pelaksana' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'desc' => ['nullable', 'string', 'max:255'],
        ]);

        $kasbonTransaction->update([
            'transaction_date' => $validated['date'],
            'pelaksana' => trim($validated['pelaksana']),
            'amount' => (float) $validated['amount'],
            'description' => trim((string) ($validated['desc'] ?? '')) ?: '-',
        ]);

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function destroy(KasbonTransaction $kasbonTransaction): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($kasbonTransaction->project_id !== $project->id, 404);

        $kasbonTransaction->delete();

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }
}
