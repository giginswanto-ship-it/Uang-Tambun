<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->date('transaction_date');
            $table->string('pelaksana', 100);
            $table->string('payment_method', 60);
            $table->string('title', 255);
            $table->decimal('qty', 12, 2);
            $table->string('unit', 40);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('vendor', 150)->default('-');
            $table->string('proof_number', 150)->default('-');
            $table->string('validation_status', 30)->default('Valid');
            $table->timestamps();

            $table->index(['project_id', 'transaction_date']);
            $table->index(['project_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
