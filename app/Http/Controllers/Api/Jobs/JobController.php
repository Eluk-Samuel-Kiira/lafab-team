<?php

namespace App\Http\Controllers\Api\Jobs;

use App\Http\Controllers\Controller;
use App\Models\Job\{ JobPost, Country };
use App\Models\Job\JobCategory;
use App\Models\Job\JobLocation;
use App\Models\Job\Company;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Get jobs with filters and pagination
     */
    public function index(Request $request)
    {
        // \Log::info('Yes Im here');
        $countryCode = $request->input('country_code', 'AU');
        $perPage = $request->input('per_page', 20);
        
        $query = JobPost::where('country_code', $countryCode)
            ->where('is_active', true)
            ->where('deadline', '>=', now());
        
        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('job_title', 'LIKE', "%{$search}%")
                  ->orWhere('job_description', 'LIKE', "%{$search}%");
            });
        }
        
        // Category filter
        if ($request->has('category_id')) {
            $query->where('job_category_id', $request->input('category_id'));
        }
        
        // Location filter
        if ($request->has('location_id')) {
            $query->where('job_location_id', $request->input('location_id'));
        }
        
        // Company filter
        if ($request->has('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }
        
        // Job type filter
        if ($request->has('job_type_id')) {
            $query->where('job_type_id', $request->input('job_type_id'));
        }
        
        // Experience level filter
        if ($request->has('experience_level_id')) {
            $query->where('experience_level_id', $request->input('experience_level_id'));
        }
        
        // Education level filter
        if ($request->has('education_level_id')) {
            $query->where('education_level_id', $request->input('education_level_id'));
        }
        
        // Salary range filter
        if ($request->has('min_salary')) {
            $query->where('salary_amount', '>=', $request->input('min_salary'));
        }
        if ($request->has('max_salary')) {
            $query->where('salary_amount', '<=', $request->input('max_salary'));
        }
        
        // Featured jobs
        if ($request->has('featured')) {
            $query->where('is_featured', true);
        }
        
        // Urgent jobs
        if ($request->has('urgent')) {
            $query->where('is_urgent', true);
        }
        
        // Simple jobs
        if ($request->has('simple_job')) {
            $query->where('is_simple_job', true);
        }
        
        // Quick gigs
        if ($request->has('quick_gig')) {
            $query->where('is_quick_gig', true);
        }
        
        $jobs = $query->orderBy('created_at', 'desc')
            ->with(['company', 'jobCategory', 'jobLocation', 'jobType'])
            ->paginate($perPage);

        // \Log::info($jobs);
        
        return response()->json([
            'success' => true,
            'data' => $jobs->items(),
            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'prev_page_url' => $jobs->previousPageUrl(),
                'next_page_url' => $jobs->nextPageUrl(),
            ]
        ]);
    }

    /**
     * Get a single job
     */
    public function show($id)
    {
        $job = JobPost::with([
            'company', 
            'jobCategory', 
            'jobLocation', 
            'jobType', 
            'experienceLevel', 
            'educationLevel',
            'salaryRange'
        ])->find($id);
        
        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $job
        ]);
    }

    /**
     * Get categories for dropdown
     */
    public function categories(Request $request)
    {
        $countryCode = $request->input('country_code', 'AU');
        
        $categories = JobCategory::where('country_code', $countryCode)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get locations for dropdown
     */
    public function locations(Request $request)
    {
        $countryCode = $request->input('country_code', 'AU');
        
        $locations = JobLocation::where('country_code', $countryCode)
            ->where('is_active', true)
            ->orderBy('district')
            ->get(['id', 'district', 'city', 'country_code']);
        
        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    /**
     * Get companies for dropdown
     */
    public function companies(Request $request)
    {
        $countryCode = $request->input('country_code', 'AU');
        
        $companies = Company::where('country_code', $countryCode)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        
        return response()->json([
            'success' => true,
            'data' => $companies
        ]);
    }
}