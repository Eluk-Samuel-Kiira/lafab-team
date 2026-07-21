<?php

namespace App\Models\Job;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EducationLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'country_code',
        'meta_title',
        'meta_description',
        'is_active',
        'sort_order',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Country display names
    protected const COUNTRY_NAMES = [
        'AU' => 'Australia',
        'UG' => 'Uganda',
        'KE' => 'Kenya',
        'TZ' => 'Tanzania',
        'RW' => 'Rwanda',
        'ZA' => 'South Africa',
        'ZM' => 'Zambia',
        'MW' => 'Malawi',
        'SG' => 'Singapore',
    ];

    // Country flags
    protected const COUNTRY_FLAGS = [
        'AU' => '🇦🇺',
        'UG' => '🇺🇬',
        'KE' => '🇰🇪',
        'TZ' => '🇹🇿',
        'RW' => '🇷🇼',
        'ZA' => '🇿🇦',
        'ZM' => '🇿🇲',
        'MW' => '🇲🇼',
        'SG' => '🇸🇬',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($educationLevel) {
            if (empty($educationLevel->slug)) {
                $educationLevel->slug = Str::slug($educationLevel->name . '-' . $educationLevel->country_code);
                // Ensure uniqueness
                $slug = $educationLevel->slug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $educationLevel->slug . '-' . $counter++;
                }
                $educationLevel->slug = $slug;
            }
            
            if (empty($educationLevel->meta_title)) {
                $countryName = self::getCountryName($educationLevel->country_code);
                $educationLevel->meta_title = "{$educationLevel->name} Jobs in {$countryName} - Education Requirements";
            }
            
            if (empty($educationLevel->created_by) && auth()->check()) {
                $educationLevel->created_by = auth()->id();
            }
        });
    }

    /**
     * Get country name
     */
    public static function getCountryName(string $countryCode): string
    {
        return self::COUNTRY_NAMES[strtoupper($countryCode)] ?? $countryCode;
    }

    /**
     * Get country flag
     */
    public static function getCountryFlag(string $countryCode): string
    {
        return self::COUNTRY_FLAGS[strtoupper($countryCode)] ?? '🌍';
    }

    /**
     * Get all available countries
     */
    public static function getAvailableCountries(): array
    {
        $countries = [];
        foreach (self::COUNTRY_NAMES as $code => $name) {
            $countries[$code] = [
                'name' => $name,
                'flag' => self::COUNTRY_FLAGS[$code] ?? '🌍',
            ];
        }
        return $countries;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'LIKE', "%{$search}%")
                     ->orWhere('description', 'LIKE', "%{$search}%");
    }

    // Accessors
    public function getCountryNameAttribute()
    {
        return self::getCountryName($this->country_code);
    }

    public function getCountryFlagAttribute()
    {
        return self::getCountryFlag($this->country_code);
    }

    public function getDisplayNameAttribute()
    {
        $flag = $this->country_flag;
        return "{$flag} {$this->name} ({$this->country_code})";
    }

    public function getSeoAttributes()
    {
        $countryName = $this->country_name;
        return [
            'title' => $this->meta_title,
            'description' => $this->meta_description,
            'keywords' => "{$this->name} jobs {$countryName}, {$this->name} required, {$this->name} qualifications, education level {$countryName}"
        ];
    }

    public function getUrlAttribute()
    {
        return url("/{$this->country_code}/education/{$this->slug}");
    }

    // Relationships
    public function jobPosts()
    {
        return $this->hasMany(JobPost::class, 'education_level_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}