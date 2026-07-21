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


//Job Settings

Route::prefix('admin')->middleware(['auth'])->group(function () {
    // Salary Ranges - Resource routes with explicit methods
    Route::get('/salary-ranges', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'index'])
        ->name('admin.salary-ranges');
    Route::get('/salary-ranges/data', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'getData'])
        ->name('admin.salary-ranges.data');
    Route::get('/salary-ranges/{id}', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'show'])
        ->name('admin.salary-ranges.show');
    Route::post('/salary-ranges', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'store'])
        ->name('admin.salary-ranges.store');
    
    // ADD PUT METHOD SUPPORT
    Route::put('/salary-ranges/{id}', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'update'])
        ->name('admin.salary-ranges.update');
    
    // Also keep POST with _method=PUT for form spoofing
    Route::post('/salary-ranges/{id}', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'update'])
        ->name('admin.salary-ranges.update-post');
    
    Route::delete('/salary-ranges/{id}', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'destroy'])
        ->name('admin.salary-ranges.destroy');
    Route::post('/salary-ranges/{id}/toggle-status', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'toggleStatus'])
        ->name('admin.salary-ranges.toggle-status');
    Route::get('/salary-ranges/countries', [App\Http\Controllers\Job\Setting\SalaryRangeController::class, 'getCountries'])
        ->name('admin.salary-ranges.countries');
});


Route::prefix('admin')->middleware(['auth'])->group(function () {
    // Education Levels
    Route::get('/education-levels', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'index'])
        ->name('admin.education-levels');
    Route::get('/education-levels/data', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'getData'])
        ->name('admin.education-levels.data');
    Route::get('/education-levels/{id}', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'show'])
        ->name('admin.education-levels.show');
    Route::post('/education-levels', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'store'])
        ->name('admin.education-levels.store');
    Route::put('/education-levels/{id}', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'update'])
        ->name('admin.education-levels.update');
    Route::post('/education-levels/{id}', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'update'])
        ->name('admin.education-levels.update-post');
    Route::delete('/education-levels/{id}', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'destroy'])
        ->name('admin.education-levels.destroy');
    Route::post('/education-levels/{id}/toggle-status', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'toggleStatus'])
        ->name('admin.education-levels.toggle-status');
    Route::get('/education-levels/countries', [App\Http\Controllers\Job\Setting\EducationLevelController::class, 'getCountries'])
        ->name('admin.education-levels.countries');
});


// In routes/web.php
Route::prefix('admin')->middleware(['auth'])->group(function () {
    // Experience Levels
    Route::get('/experience-levels', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'index'])
        ->name('admin.experience-levels');
    Route::get('/experience-levels/data', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'getData'])
        ->name('admin.experience-levels.data');
    Route::get('/experience-levels/{id}', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'show'])
        ->name('admin.experience-levels.show');
    Route::post('/experience-levels', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'store'])
        ->name('admin.experience-levels.store');
    Route::put('/experience-levels/{id}', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'update'])
        ->name('admin.experience-levels.update');
    Route::post('/experience-levels/{id}', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'update'])
        ->name('admin.experience-levels.update-post');
    Route::delete('/experience-levels/{id}', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'destroy'])
        ->name('admin.experience-levels.destroy');
    Route::post('/experience-levels/{id}/toggle-status', [App\Http\Controllers\Job\Setting\ExperienceLevelController::class, 'toggleStatus'])
        ->name('admin.experience-levels.toggle-status');
});



Route::prefix('admin')->middleware(['auth'])->group(function () {
    // Job Types
    Route::get('/job-types', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'index'])
        ->name('admin.job-types');
    Route::get('/job-types/data', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'getData'])
        ->name('admin.job-types.data');
    Route::get('/job-types/{id}', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'show'])
        ->name('admin.job-types.show');
    Route::post('/job-types', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'store'])
        ->name('admin.job-types.store');
    Route::put('/job-types/{id}', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'update'])
        ->name('admin.job-types.update');
    Route::post('/job-types/{id}', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'update'])
        ->name('admin.job-types.update-post');
    Route::delete('/job-types/{id}', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'destroy'])
        ->name('admin.job-types.destroy');
    Route::post('/job-types/{id}/toggle-status', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'toggleStatus'])
        ->name('admin.job-types.toggle-status');
    Route::get('/job-types/icons', [App\Http\Controllers\Job\Setting\JobTypeController::class, 'getIcons'])
        ->name('admin.job-types.icons');
});



Route::prefix('admin')->middleware(['auth'])->group(function () {
    // Job Categories
    Route::get('/job-categories', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'index'])
        ->name('admin.job-categories');
    Route::get('/job-categories/data', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'getData'])
        ->name('admin.job-categories.data');
    Route::get('/job-categories/{id}', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'show'])
        ->name('admin.job-categories.show');
    Route::post('/job-categories', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'store'])
        ->name('admin.job-categories.store');
    Route::put('/job-categories/{id}', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'update'])
        ->name('admin.job-categories.update');
    Route::post('/job-categories/{id}', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'update'])
        ->name('admin.job-categories.update-post');
    Route::delete('/job-categories/{id}', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'destroy'])
        ->name('admin.job-categories.destroy');
    Route::post('/job-categories/{id}/toggle-status', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'toggleStatus'])
        ->name('admin.job-categories.toggle-status');
    Route::get('/job-categories/icons', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'getIcons'])
        ->name('admin.job-categories.icons');
    Route::get('/job-categories/colors', [App\Http\Controllers\Job\Setting\JobCategoryController::class, 'getColors'])
        ->name('admin.job-categories.colors');
});


Route::prefix('admin')->middleware(['auth'])->group(function () {
    // Industries
    Route::get('/industries', [App\Http\Controllers\Job\Setting\IndustryController::class, 'index'])
        ->name('admin.industries');
    Route::get('/industries/data', [App\Http\Controllers\Job\Setting\IndustryController::class, 'getData'])
        ->name('admin.industries.data');
    Route::get('/industries/{id}', [App\Http\Controllers\Job\Setting\IndustryController::class, 'show'])
        ->name('admin.industries.show');
    Route::post('/industries', [App\Http\Controllers\Job\Setting\IndustryController::class, 'store'])
        ->name('admin.industries.store');
    Route::put('/industries/{id}', [App\Http\Controllers\Job\Setting\IndustryController::class, 'update'])
        ->name('admin.industries.update');
    Route::post('/industries/{id}', [App\Http\Controllers\Job\Setting\IndustryController::class, 'update'])
        ->name('admin.industries.update-post');
    Route::delete('/industries/{id}', [App\Http\Controllers\Job\Setting\IndustryController::class, 'destroy'])
        ->name('admin.industries.destroy');
    Route::post('/industries/{id}/toggle-status', [App\Http\Controllers\Job\Setting\IndustryController::class, 'toggleStatus'])
        ->name('admin.industries.toggle-status');
    Route::get('/industries/icons', [App\Http\Controllers\Job\Setting\IndustryController::class, 'getIcons'])
        ->name('admin.industries.icons');
});