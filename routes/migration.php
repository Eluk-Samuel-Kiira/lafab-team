<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Job\Migration\{ 
    JobCategoryMigrationController,
    CompanyMigrationController,

    };
use Illuminate\Support\Facades\Route;


// Job Categories Migration Routes
Route::middleware(['auth', 'superadmin'])->prefix('admin')->group(function () {
    Route::get('/job-categories/migration', [JobCategoryMigrationController::class, 'index'])
        ->name('admin.job-categories.migration');
    Route::get('/job-categories/migration/stats', [JobCategoryMigrationController::class, 'getStatistics'])
        ->name('admin.job-categories.migration.stats');
    Route::get('/job-categories/migration/data', [JobCategoryMigrationController::class, 'getCategories'])
        ->name('admin.job-categories.migration.data');
    Route::get('/job-categories/migration/{id}', [JobCategoryMigrationController::class, 'show'])
        ->name('admin.job-categories.migration.show');
    Route::post('/job-categories/migration/import', [JobCategoryMigrationController::class, 'import'])
        ->name('admin.job-categories.migration.import');
    Route::post('/job-categories/migration/{id}/migrate', [JobCategoryMigrationController::class, 'migrateSingle'])
        ->name('admin.job-categories.migration.single');
    Route::post('/job-categories/migration/bulk', [JobCategoryMigrationController::class, 'bulkMigrate'])
        ->name('admin.job-categories.migration.bulk');
    Route::post('/job-categories/migration/{id}/rollback', [JobCategoryMigrationController::class, 'rollback'])
        ->name('admin.job-categories.migration.rollback');
    Route::put('/job-categories/migration/{id}', [JobCategoryMigrationController::class, 'update'])
        ->name('admin.job-categories.migration.update');
    Route::delete('/job-categories/migration/{id}', [JobCategoryMigrationController::class, 'destroy'])
        ->name('admin.job-categories.migration.destroy');
});



// Companies Migration Routes
Route::middleware(['auth', 'superadmin'])->prefix('admin')->group(function () {
    Route::get('/companies/migration', [CompanyMigrationController::class, 'index'])
        ->name('admin.companies.migration');
    Route::get('/companies/migration/stats', [CompanyMigrationController::class, 'getStatistics'])
        ->name('admin.companies.migration.stats');
    Route::get('/companies/migration/data', [CompanyMigrationController::class, 'getCompanies'])
        ->name('admin.companies.migration.data');
    Route::get('/companies/migration/{id}', [CompanyMigrationController::class, 'show'])
        ->name('admin.companies.migration.show');
    Route::post('/companies/migration/import', [CompanyMigrationController::class, 'import'])
        ->name('admin.companies.migration.import');
    Route::post('/companies/migration/{id}/migrate', [CompanyMigrationController::class, 'migrateSingle'])
        ->name('admin.companies.migration.single');
    Route::post('/companies/migration/bulk', [CompanyMigrationController::class, 'bulkMigrate'])
        ->name('admin.companies.migration.bulk');
    Route::post('/companies/migration/{id}/rollback', [CompanyMigrationController::class, 'rollback'])
        ->name('admin.companies.migration.rollback');
    Route::put('/companies/migration/{id}', [CompanyMigrationController::class, 'update'])
        ->name('admin.companies.migration.update');
    Route::delete('/companies/migration/{id}', [CompanyMigrationController::class, 'destroy'])
        ->name('admin.companies.migration.destroy');
});