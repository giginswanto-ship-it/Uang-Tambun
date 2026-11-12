<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvestorFundController;
use App\Http\Controllers\KasbonTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/export/csv', [DashboardController::class, 'exportCsv'])->name('export.csv');

Route::prefix('api')->group(function () {
    Route::get('/state', [DashboardController::class, 'state'])->name('api.state');
    Route::patch('/project', [DashboardController::class, 'updateProject'])->name('api.project.update');
    Route::post('/project/demo', [DashboardController::class, 'loadDemo'])->name('api.project.demo');
    Route::post('/project/reset', [DashboardController::class, 'reset'])->name('api.project.reset');

    Route::put('/categories/settings/batch', [CategoryController::class, 'saveSettingsBatch'])->name('api.categories.settings.batch');
    Route::post('/categories/reset', [CategoryController::class, 'resetDefaults'])->name('api.categories.reset');
    Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('api.categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.categories.destroy');

    Route::post('/investors', [InvestorFundController::class, 'store'])->name('api.investors.store');
    Route::put('/investors/{investorFund}', [InvestorFundController::class, 'update'])->name('api.investors.update');
    Route::delete('/investors/{investorFund}', [InvestorFundController::class, 'destroy'])->name('api.investors.destroy');

    Route::post('/kasbons', [KasbonTransactionController::class, 'store'])->name('api.kasbons.store');
    Route::put('/kasbons/{kasbonTransaction}', [KasbonTransactionController::class, 'update'])->name('api.kasbons.update');
    Route::delete('/kasbons/{kasbonTransaction}', [KasbonTransactionController::class, 'destroy'])->name('api.kasbons.destroy');

    Route::post('/expenses', [ExpenseController::class, 'store'])->name('api.expenses.store');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('api.expenses.update');
    Route::patch('/expenses/{expense}/category', [ExpenseController::class, 'updateCategory'])->name('api.expenses.category');
    Route::patch('/expenses/{expense}/validation', [ExpenseController::class, 'cycleValidation'])->name('api.expenses.validation');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('api.expenses.destroy');
});
