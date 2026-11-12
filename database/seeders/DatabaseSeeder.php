<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Services\ProjectFinanceService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ProjectFinanceService::class);
        $project = Project::query()->orderBy('id')->first()
            ?? Project::create(['name' => ProjectFinanceService::DEFAULT_PROJECT_NAME]);

        if (!$service->seedFromSnapshotFile($project)) {
            $service->loadDemoData($project);
        }
    }
}
