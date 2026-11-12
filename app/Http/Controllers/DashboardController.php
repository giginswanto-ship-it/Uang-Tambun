<?php

namespace App\Http\Controllers;

use App\Services\ProjectFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected ProjectFinanceService $service
    ) {}

    public function index(): View
    {
        $project = $this->service->getOrInitializeProject();
        $initialState = $this->service->getDashboardState($project);

        return view('dashboard.index', [
            'initialState' => $initialState,
        ]);
    }

    public function state(): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function updateProject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Nama project tidak boleh kosong!',
            ], 422);
        }

        $project = $this->service->getOrInitializeProject();
        $project->update(['name' => $name]);

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function loadDemo(): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        $this->service->loadDemoData($project);

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function reset(): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        $this->service->resetAllData($project);

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function importLocal(Request $request): JsonResponse
    {
        $payload = $request->all();
        if (isset($payload['snapshot']) && is_string($payload['snapshot'])) {
            $decoded = $this->service->decodeSnapshotJson($payload['snapshot']);
            if ($decoded === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Format JSON data lokal tidak valid.',
                ], 422);
            }
            $payload = $decoded;
        }

        if (!is_array($payload) || (!isset($payload['categories']) && !isset($payload['expenses']) && !isset($payload['investorFunds']) && !isset($payload['kasbonList']))) {
            return response()->json([
                'ok' => false,
                'message' => 'Data snapshot lokal tidak lengkap.',
            ], 422);
        }

        $project = $this->service->getOrInitializeProject();
        $this->service->importSnapshot($payload, $project);

        $syncSeeder = $request->boolean('syncSeeder', true);
        if ($syncSeeder) {
            $this->service->writeSeederSnapshot($project);
        }

        return response()->json([
            'ok' => true,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function syncSeeder(): JsonResponse
    {
        $project = $this->service->getOrInitializeProject();
        $path = $this->service->writeSeederSnapshot($project);

        return response()->json([
            'ok' => true,
            'path' => $path,
            'state' => $this->service->getDashboardState($project),
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $project = $this->service->getOrInitializeProject();
        $state = $this->service->getDashboardState($project);

        $safeSlug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $state['projectName']) ?: 'Project';
        $filename = 'Laporan_Keuangan_' . trim($safeSlug, '_') . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($state) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");

            fwrite($out, "--- LAPORAN DANA MASUK INVESTOR ---\n");
            fputcsv($out, ['Tanggal', 'Nama Investor', 'Keterangan', 'Nominal Masuk (Rp)']);
            foreach ($state['investorFunds'] as $inv) {
                fputcsv($out, [$inv['date'], $inv['investor'], $inv['desc'], $inv['amount']]);
            }

            fwrite($out, "\n--- LAPORAN PENCAIRAN KASBON PRINCIPAL ---\n");
            fputcsv($out, ['Tanggal', 'Penerima Kasbon', 'Keperluan', 'Nominal Kasbon (Rp)']);
            foreach ($state['kasbonList'] as $kas) {
                fputcsv($out, [$kas['date'], $kas['pelaksana'], $kas['desc'], $kas['amount']]);
            }

            fwrite($out, "\n--- RINCIAN DETAIL PEMAKAIAN BIAYA LAPANGAN ---\n");
            fputcsv($out, [
                'Tanggal', 'Pelaksana', 'Kategori', 'Metode Bayar', 'Uraian Item',
                'Qty', 'Satuan', 'Harga Satuan (Rp)', 'Total Biaya (Rp)',
                'Vendor', 'No Bukti', 'Status Validasi',
            ]);
            foreach ($state['expenses'] as $exp) {
                fputcsv($out, [
                    $exp['date'],
                    $exp['pelaksana'],
                    $exp['category'],
                    $exp['payMethod'],
                    $exp['title'],
                    $exp['qty'],
                    $exp['unit'],
                    $exp['price'],
                    $exp['total'],
                    $exp['vendor'],
                    $exp['proof'],
                    $exp['validation'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
