<?php

namespace App\Http\Controllers\Job\JobIndex;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\JobIndex\JobPostRequest;
use App\Models\Job\JobPost;
use App\Models\Job\Company;
use App\Models\Job\JobCategory;
use App\Models\Job\Industry;
use App\Models\Job\JobLocation;
use App\Models\Job\JobType;
use App\Models\Job\ExperienceLevel;
use App\Models\Job\EducationLevel;
use App\Models\Job\SalaryRange;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobPostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('job.job-posting.ai-posting');
    }

    /**
     * Get data for DataTable.
     */
    public function getData(Request $request)
    {
        $search = $request->get('search', '');
        $country = $request->get('country', '');
        $status = $request->get('status', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $query = JobPost::with(['company', 'jobCategory', 'jobLocation', 'jobType', 'experienceLevel']);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('job_title', 'like', '%' . $search . '%')
                  ->orWhere('slug', 'like', '%' . $search . '%')
                  ->orWhere('job_description', 'like', '%' . $search . '%')
                  ->orWhere('legacy_alias', 'like', '%' . $search . '%')
                  ->orWhere('legacy_id', $search)
                  ->orWhere('id', $search);
            });
        }

        if (!empty($country)) {
            $query->where('country_code', $country);
        }

        if (!empty($status)) {
            if ($status === 'active') {
                $query->where('is_active', true)->where('deadline', '>=', now());
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'expired') {
                $query->where('deadline', '<', now());
            } elseif ($status === 'featured') {
                $query->where('is_featured', true);
            } elseif ($status === 'urgent') {
                $query->where('is_urgent', true);
            } elseif ($status === 'migrated') {
                $query->whereNotNull('migrated_at');
            } elseif ($status === 'pending') {
                $query->whereNull('migrated_at');
            }
        }

        $jobPosts = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $jobPosts->getCollection()->transform(function ($item) {
            $item->status_badge = $item->status_badge;
            $item->migration_badge = $item->migration_badge;
            $item->days_remaining = $item->days_remaining;
            $item->is_expired = $item->is_expired;
            return $item;
        });

        return response()->json($jobPosts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JobPostRequest $request)
    {
        try {
            $data = $request->validatedWithDefaults();
            
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['job_title']);
                $slug = $data['slug'];
                $counter = 1;
                while (JobPost::where('slug', $slug)->exists()) {
                    $slug = $data['slug'] . '-' . $counter++;
                }
                $data['slug'] = $slug;
            }

            // Set poster_id if not provided
            if (empty($data['poster_id'])) {
                $data['poster_id'] = auth()->id();
            }

            // If this is a legacy migration, set migrated_at
            if (!empty($data['legacy_id'])) {
                $data['migrated_at'] = now();
                // For legacy, we might want to set default values for required fields
                if (empty($data['is_active'])) {
                    $data['is_active'] = true;
                }
            }

            $jobPost = JobPost::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Job post created successfully!',
                'data' => $jobPost->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to create job post: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create job post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $jobPost = JobPost::with([
                'company', 
                'jobCategory', 
                'industry', 
                'jobLocation', 
                'jobType', 
                'experienceLevel', 
                'educationLevel', 
                'salaryRange',
                'poster'
            ])->findOrFail($id);
            
            return response()->json($jobPost);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job post not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(JobPostRequest $request, $id)
    {
        try {
            $jobPost = JobPost::findOrFail($id);
            $data = $request->validatedWithDefaults();
            
            // Generate slug if title changed and slug not provided
            if (isset($data['job_title']) && $jobPost->job_title !== $data['job_title'] && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['job_title']);
                $slug = $data['slug'];
                $counter = 1;
                while (JobPost::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $data['slug'] . '-' . $counter++;
                }
                $data['slug'] = $slug;
            }

            // If this is a legacy migration and not already migrated
            if (!empty($data['legacy_id']) && empty($jobPost->migrated_at)) {
                $data['migrated_at'] = now();
            }

            $jobPost->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Job post updated successfully!',
                'data' => $jobPost->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job post not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to update job post: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update job post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $jobPost = JobPost::findOrFail($id);
            
            if ($jobPost->applications()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete this job post because it has associated applications.'
                ], 400);
            }
            
            $jobPost->delete();

            return response()->json([
                'success' => true,
                'message' => 'Job post deleted successfully!'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to delete job post: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle status of the specified resource.
     */
    public function toggleStatus($id)
    {
        try {
            $jobPost = JobPost::findOrFail($id);
            $jobPost->is_active = !$jobPost->is_active;
            $jobPost->save();

            return response()->json([
                'success' => true,
                'message' => $jobPost->is_active ? 'Job post activated successfully!' : 'Job post deactivated successfully!',
                'is_active' => $jobPost->is_active
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to toggle status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured($id)
    {
        try {
            $jobPost = JobPost::findOrFail($id);
            $jobPost->is_featured = !$jobPost->is_featured;
            $jobPost->save();

            return response()->json([
                'success' => true,
                'message' => $jobPost->is_featured ? 'Job post featured successfully!' : 'Job post unfeatured!',
                'is_featured' => $jobPost->is_featured
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to toggle featured: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle featured: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get form data for dropdowns.
     */
    public function getFormData(Request $request)
    {
        $countryCode = $request->get('country', 'AU');
        
        $companies = Company::where('is_active', true)
            ->where('country_code', $countryCode)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        $jobCategories = JobCategory::where('is_active', true)
            ->where('country_code', $countryCode)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        $industries = Industry::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        $locations = JobLocation::where('is_active', true)
            ->where('country', $countryCode)
            ->orderBy('district')
            ->get(['id', 'district', 'city', 'country']);
            
        $jobTypes = JobType::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        $experienceLevels = ExperienceLevel::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'min_years', 'max_years']);
            
        $educationLevels = EducationLevel::where('is_active', true)
            ->where('country_code', $countryCode)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        $salaryRanges = SalaryRange::where('is_active', true)
            ->where('country_code', $countryCode)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'min_salary', 'max_salary', 'currency']);
            
        return response()->json([
            'success' => true,
            'companies' => $companies,
            'job_categories' => $jobCategories,
            'industries' => $industries,
            'locations' => $locations,
            'job_types' => $jobTypes,
            'experience_levels' => $experienceLevels,
            'education_levels' => $educationLevels,
            'salary_ranges' => $salaryRanges,
        ]);
    }

    /**
     * Feature a job post for a number of days.
     */
    public function feature(Request $request, $id)
    {
        try {
            $request->validate([
                'days' => 'required|integer|min:1|max:365',
            ]);

            $jobPost = JobPost::findOrFail($id);
            $days = (int) $request->input('days');
            
            $jobPost->is_featured = true;
            $jobPost->featured_until = now()->addDays($days);
            $jobPost->save();

            return response()->json([
                'success' => true,
                'message' => "Job post featured successfully until {$jobPost->featured_until->format('M d, Y')}!",
                'featured_until' => $jobPost->featured_until
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to feature job post: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to feature job post: ' . $e->getMessage()
            ], 500);
        }
    }
}