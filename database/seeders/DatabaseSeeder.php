<?php

namespace Database\Seeders;

use App\Services\ProjectFinanceService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ProjectFinanceService::class);
        $project = $service->getOrInitializeProject();
        $service->loadDemoData($project);
    }
}
