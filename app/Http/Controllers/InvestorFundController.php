<?php

namespace App\Http\Controllers;

use App\Models\InvestorFund;
use App\Services\ProjectFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestorFundController extends Controller
{
    public function __construct(
        protected ProjectFinanceService $service
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'investor' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'desc' => ['nullable', 'string', 'max:255'],
        ]);

        $project = $this->service->getOrInitializeProject();

        $item = InvestorFund::create([
            'project_id' => $project->id,
            'transaction_date' => $validated['date'],
            'investor_name' => trim($validated['investor']),
            'amount' => (float) $validated['amount'],
            'description' => trim((string) ($validated['desc'] ?? '')) ?: '-',
        ]);

        return response()->json([
            'ok' => true,
            'id' => (string) $item->id,
            'state' => $this->service->getDashboardState($project),
        ], 201);
    }

    public function update(Request $request, InvestorFund $investorFund): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($investorFund->project_id !== $project->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'investor' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'desc' => ['nullable', 'string', 'max:255'],
        ]);

        $investorFund->update([
            'transaction_date' => $validated['date'],
            'investor_name' => trim($validated['investor']),
            'amount' => (float) $validated['amount'],
            'description' => trim((string) ($validated['desc'] ?? '')) ?: '-',
        ]);

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function destroy(InvestorFund $investorFund): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        abort_if($investorFund->project_id !== $project->id, 404);

        $investorFund->delete();

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }
}
