<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Job\Migration\{ 
    DatabaseMigrationController,
};
use Illuminate\Support\Facades\Route;




// In routes/web.php
Route::middleware(['auth', 'superadmin'])->prefix('admin')->group(function () {
    // Database Migration
    Route::get('/migration', [DatabaseMigrationController::class, 'index'])->name('admin.migration.dashboard');
    Route::get('/migration/tables', [DatabaseMigrationController::class, 'getTables'])->name('admin.migration.tables');
    Route::get('/migration/stats', [DatabaseMigrationController::class, 'getStats'])->name('admin.migration.stats');
    Route::get('/migration/all-stats', [DatabaseMigrationController::class, 'getAllStats'])->name('admin.migration.all-stats');
    Route::get('/migration/progress', [DatabaseMigrationController::class, 'getProgress'])->name('admin.migration.progress');
    Route::get('/migration/summary', [DatabaseMigrationController::class, 'getSummary'])->name('admin.migration.summary');
    Route::get('/migration/table-config/{table}', [DatabaseMigrationController::class, 'getTableConfig'])->name('admin.migration.table-config');
    Route::get('/migration/test-connection', [DatabaseMigrationController::class, 'testConnection'])->name('admin.migration.test-connection');
    Route::get('/migration/debug-data', [DatabaseMigrationController::class, 'debugData'])->name('admin.migration.debug-data');
    Route::get('/migration/check-legacy', [DatabaseMigrationController::class, 'checkLegacyConnection'])->name('admin.migration.check-legacy');
    Route::post('/migration/migrate', [DatabaseMigrationController::class, 'migrate'])->name('admin.migration.migrate');
    Route::post('/migration/migrate-all', [DatabaseMigrationController::class, 'migrateAll'])->name('admin.migration.migrate-all');
    Route::post('/migration/reset', [DatabaseMigrationController::class, 'resetMigration'])->name('admin.migration.reset');
    Route::get('/migration/countries', [DatabaseMigrationController::class, 'getCountries'])->name('admin.migration.countries');
});