<?php

use App\Services\ProjectFinanceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('finance:export-seeder {--path= : Path file JSON seeder tujuan}', function () {
    $service = app(ProjectFinanceService::class);
    $project = $service->getOrInitializeProject();
    $targetPath = $service->writeSeederSnapshot($project, $this->option('path') ?: null);
    $state = $service->getDashboardState($project);

    $this->info("Snapshot seeder berhasil disimpan ke: {$targetPath}");
    $this->line(sprintf(
        'Project: %s | Kategori: %d | Dana Investor: %d | Kasbon: %d | Pengeluaran: %d',
        $state['projectName'],
        count($state['categories']),
        count($state['investorFunds']),
        count($state['kasbonList']),
        count($state['expenses'])
    ));
})->purpose('Export data SQL lokal saat ini ke file snapshot seeder agar sinkron saat migrate --seed');

Artisan::command('finance:import-local {file : Path file JSON hasil export localStorage/snapshot} {--no-seeder : Jangan perbarui file project_seed.json}', function () {
    $file = (string) $this->argument('file');
    if (!File::isFile($file)) {
        $this->error("File tidak ditemukan: {$file}");
        return self::FAILURE;
    }

    $service = app(ProjectFinanceService::class);
    $decoded = $service->decodeSnapshotJson(File::get($file));
    if ($decoded === null) {
        $this->error('Isi file bukan JSON snapshot yang valid.');
        return self::FAILURE;
    }

    $project = $service->importSnapshot($decoded);
    $state = $service->getDashboardState($project);

    if (!$this->option('no-seeder')) {
        $targetPath = $service->writeSeederSnapshot($project);
        $this->info("Seeder diperbarui di: {$targetPath}");
    }

    $this->info("Import ke SQL selesai untuk project: {$state['projectName']}");
    $this->line(sprintf(
        'Kategori: %d | Dana Investor: %d | Kasbon: %d | Pengeluaran: %d',
        count($state['categories']),
        count($state['investorFunds']),
        count($state['kasbonList']),
        count($state['expenses'])
    ));

    return self::SUCCESS;
})->purpose('Import data lokal JSON (termasuk localStorage PROJECT_FINANCE_DATA_V1) ke SQL dan seeder');
