<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_category_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('sort_by', 32)->default('manual');
            $table->boolean('show_category_section')->default(true);
            $table->unsignedTinyInteger('budget_alert_percent')->default(80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_category_settings');
    }
};
