<?php
use Illuminate\Support\Facades\Route;


use App\Http\Controllers\Reports\{ ExpenseReportController };

// Expense Reports Routes
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/expense-reports', [ExpenseReportController::class, 'index'])->name('admin.expense-reports');
    Route::get('/expense-reports/summary', [ExpenseReportController::class, 'summary'])->name('admin.expense-reports.summary');
    Route::get('/expense-reports/category', [ExpenseReportController::class, 'byCategory'])->name('admin.expense-reports.category');
    Route::get('/expense-reports/vendor', [ExpenseReportController::class, 'byVendor'])->name('admin.expense-reports.vendor');
    Route::get('/expense-reports/employee', [ExpenseReportController::class, 'byEmployee'])->name('admin.expense-reports.employee');
    Route::get('/expense-reports/payment-method', [ExpenseReportController::class, 'byPaymentMethod'])->name('admin.expense-reports.payment-method');
    Route::get('/expense-reports/trends', [ExpenseReportController::class, 'trends'])->name('admin.expense-reports.trends');
    Route::get('/expense-reports/recurring', [ExpenseReportController::class, 'recurring'])->name('admin.expense-reports.recurring');
    Route::get('/expense-reports/tax', [ExpenseReportController::class, 'taxReport'])->name('admin.expense-reports.tax');
    Route::get('/expense-reports/budget', [ExpenseReportController::class, 'budgetVsActual'])->name('admin.expense-reports.budget');
    Route::get('/expense-reports/audit', [ExpenseReportController::class, 'audit'])->name('admin.expense-reports.audit');
    Route::get('/expense-reports/export/{type}', [ExpenseReportController::class, 'export'])->name('admin.expense-reports.export');
});