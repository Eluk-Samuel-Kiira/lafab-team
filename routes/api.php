<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Jobs\{ CountryController,CompanyController, JobController, 
    LocationController, CategoryController };

// ✅ TEST ROUTE
Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'Pong!',
        'timestamp' => now()
    ]);
});

// ✅ Protected routes with middleware
Route::middleware(['verifycountry'])->group(function () {
    
    // Country route
    Route::get('/countries/{code}', [CountryController::class, 'show']);
    
    // Job routes - All in one controller
    Route::get('/jobs', [JobController::class, 'index']);
    Route::get('/jobs/{id}', [JobController::class, 'show']);
    Route::get('/categories', [JobController::class, 'categories']);
    Route::get('/locations', [JobController::class, 'locations']);
    Route::get('/companies', [JobController::class, 'companies']);

    Route::post('jobs/{id}/track-application', [JobController::class, 'trackApplication']);



    // Company routes
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/featured', [CompanyController::class, 'featured']);
    Route::get('/companies/{id}', [CompanyController::class, 'show']);
    Route::get('/industries', [CompanyController::class, 'industries']);
    Route::get('/job-types', [JobController::class, 'jobTypes']);

    // Categories routes
    Route::get('/all_categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::get('/categories/{id}/jobs', [CategoryController::class, 'jobs']);

    // Location routes
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations/{identifier}', [LocationController::class, 'show']);
    Route::get('/locations/{identifier}/jobs', [LocationController::class, 'jobs']);
});

// Route to test middleware
Route::get('/test-auth', function () {
    return response()->json([
        'success' => true,
        'message' => 'Authentication successful!'
    ]);
})->middleware(['verifycountry']);