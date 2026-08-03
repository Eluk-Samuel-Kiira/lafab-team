<?php

namespace App\Models\Job;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class JobPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'job_posts';

    protected $fillable = [
        // Core Job Information
        'job_title',
        'slug',
        'job_description',
        'responsibilities',
        'skills',
        'qualifications',
        'deadline',
        'application_procedure',
        'email',
        'telephone',
        
        // Relationships
        'company_id',
        'job_category_id',
        'industry_id',
        'job_location_id',
        'job_type_id',
        'experience_level_id',
        'education_level_id',
        'salary_range_id',
        'poster_id',
        
        // Legacy Tracking
        'legacy_id',
        'legacy_company_id',
        'legacy_alias',
        'legacy_metadata',
        
        // Location Details
        'duty_station',
        'street_address',
        'city',
        'state',
        'country',
        'zipcode',
        
        // Salary Information
        'salary_amount',
        'currency',
        'payment_period',
        'job_source',
        'base_salary',
        'salary_range_from',
        'salary_range_to',
        
        // Job Specifications
        'location_type',
        'applicant_location_requirements',
        'work_hours',
        'employment_type',
        
        // SEO & AI Optimization
        'meta_title',
        'meta_description',
        'keywords',
        'canonical_url',
        'structured_data',
        'focus_keyphrase',
        'seo_synonyms',
        
        // Advanced SEO Features
        'is_pinged',
        'is_whatsapp_contact',
        'is_telephone_call',
        'last_pinged_at',
        'is_indexed',
        'last_indexed_at',
        'is_featured',
        'is_urgent',
        'is_active',
        'is_verified',
        'is_simple_job',
        'is_quick_gig',
        'view_count',
        'application_count',
        'click_count',
        
        // Application Requirements
        'is_cover_letter_required',
        'is_resume_required',
        'is_application_required',
        'is_academic_documents_required',
        
        // AI Optimization
        'ai_optimized_title',
        'ai_optimized_description',
        'ai_content_analysis',
        'seo_score',
        'content_quality_score',
        'search_terms',
        'competitor_analysis',
        'ai_recommendations',
        
        // Performance Tracking
        'search_impressions',
        'search_clicks',
        'click_through_rate',
        'google_rank',
        'ranking_keywords',
        
        // Social Signals
        'social_shares',
        'backlinks_count',
        'social_metrics',
        
        // Additional Legacy Fields
        'job_reference',
        'duration',
        'heighest_finished_education',
        'experience_months',
        
        // Timestamps
        'published_at',
        'published_until',
        'featured_until',
        'migrated_at',
    ];

    protected $casts = [
        'deadline' => 'date',
        'view_count' => 'integer',
        'application_count' => 'integer',
        'click_count' => 'integer',
        'salary_amount' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'is_pinged' => 'boolean',
        'is_indexed' => 'boolean',
        'is_simple_job' => 'boolean',
        'is_quick_gig' => 'boolean',
        'is_featured' => 'boolean',
        'is_urgent' => 'boolean',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'is_cover_letter_required' => 'boolean',
        'is_resume_required' => 'boolean',
        'is_academic_documents_required' => 'boolean',
        'is_application_required' => 'boolean',
        'is_whatsapp_contact' => 'boolean',
        'is_telephone_call' => 'boolean',
        'seo_score' => 'decimal:2',
        'content_quality_score' => 'decimal:2',
        'click_through_rate' => 'decimal:2',
        'published_at' => 'datetime',
        'featured_until' => 'datetime',
        'published_until' => 'datetime',
        'last_pinged_at' => 'datetime',
        'last_indexed_at' => 'datetime',
        'migrated_at' => 'datetime',
        'structured_data' => 'array',
        'search_terms' => 'array',
        'competitor_analysis' => 'array',
        'ranking_keywords' => 'array',
        'social_metrics' => 'array',
        'legacy_metadata' => 'array',
        'experience_months' => 'integer',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function jobCategory()
    {
        return $this->belongsTo(JobCategory::class);
    }

    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    public function jobLocation()
    {
        return $this->belongsTo(JobLocation::class, 'job_location_id');
    }

    public function jobType()
    {
        return $this->belongsTo(JobType::class);
    }

    public function experienceLevel()
    {
        return $this->belongsTo(ExperienceLevel::class);
    }

    public function educationLevel()
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function salaryRange()
    {
        return $this->belongsTo(SalaryRange::class);
    }

    public function poster()
    {
        return $this->belongsTo(\App\Models\User::class, 'poster_id');
    }

    public function applications()
    {
        return $this->hasMany(\App\Models\JobApplication::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('deadline', '>=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)
                    ->where(function($q) {
                        $q->whereNull('featured_until')
                          ->orWhere('featured_until', '>=', now());
                    });
    }

    public function scopeByLegacyId($query, $legacyId)
    {
        return $query->where('legacy_id', $legacyId);
    }

    public function scopeMigrated($query)
    {
        return $query->whereNotNull('migrated_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('migrated_at');
    }

    // Accessors
    public function getIsExpiredAttribute()
    {
        return $this->deadline && $this->deadline->isPast();
    }

    public function getStatusBadgeAttribute()
    {
        if ($this->is_active) {
            return '<span class="badge badge-light-success">Active</span>';
        }
        return '<span class="badge badge-light-danger">Inactive</span>';
    }

    public function getMigrationBadgeAttribute()
    {
        if ($this->migrated_at) {
            return '<span class="badge badge-light-success">Migrated</span>';
        }
        return '<span class="badge badge-light-warning">Pending</span>';
    }

    public function getDaysRemainingAttribute()
    {
        if (!$this->deadline) return null;
        return now()->diffInDays($this->deadline, false);
    }


    /**
     * Get the URL for this job post
     */
    public function getUrlAttribute(): string
    {
        $baseUrl = config('app.url');
        $country = $this->country_code ?? 'AU';
        
        // Get country domain if available
        $countryDomains = [
            'AU' => 'greataustraliajobs.com',
            'UG' => 'greatugandajobs.com',
            'KE' => 'greatkenyanjobs.com',
            'TZ' => 'greattanzaniajobs.com',
            'RW' => 'greatrwandajobs.com',
            'MW' => 'greatmalawijobs.com',
            'ZM' => 'greatzambiajobs.com',
            'SG' => 'greatsingaporejobs.com',
        ];

        $domain = $countryDomains[$country] ?? parse_url($baseUrl, PHP_URL_HOST);
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        
        // Use legacy URL structure if legacy_id exists
        if ($this->legacy_id) {
            return "{$scheme}://{$domain}/jobs/legacy/{$this->legacy_id}/{$this->slug}";
        }
        
        return "{$scheme}://{$domain}/jobs/{$this->slug}";
    }

    /**
     * Get country-specific URL
     */
    public function getCountryUrl(string $countryCode): string
    {
        $countryDomains = [
            'AU' => 'greataustraliajobs.com',
            'UG' => 'greatugandajobs.com',
            'KE' => 'greatkenyajobs.com',
            'TZ' => 'greattanzaniajobs.com',
            'RW' => 'greatrwandajobs.com',
            'MW' => 'greatmalawijobs.com',
            'ZM' => 'greatzambiajobs.com',
            'SG' => 'greatsingaporejobs.com',
        ];

        $domain = $countryDomains[$countryCode] ?? parse_url(config('app.url'), PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';
        
        if ($this->legacy_id) {
            return "{$scheme}://{$domain}/jobs/legacy/{$this->legacy_id}/{$this->slug}";
        }
        
        return "{$scheme}://{$domain}/jobs/{$this->slug}";
    }
}