<?php

namespace App\Models\Job;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SalaryRange extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'min_salary',
        'max_salary',
        'currency',
        'country_code',
        'meta_title',
        'meta_description',
        'is_active',
        'sort_order',
        'created_by'
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Country currency mapping
    protected const COUNTRY_CURRENCY = [
        'AU' => 'AUD',
        'UG' => 'UGX',
        'KE' => 'KES',
        'TZ' => 'TZS',
        'RW' => 'RWF',
        'ZA' => 'ZAR',
        'ZM' => 'ZMW',
        'MW' => 'MWK',
        'SG' => 'SGD',
        'NG' => 'NGN',
        'GH' => 'GHS',
        'ET' => 'ETB',
        'EG' => 'EGP',
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

        static::creating(function ($salaryRange) {
            if (empty($salaryRange->slug)) {
                $salaryRange->slug = Str::slug($salaryRange->name);
                // Ensure uniqueness
                $slug = $salaryRange->slug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $salaryRange->slug . '-' . $counter++;
                }
                $salaryRange->slug = $slug;
            }
            
            if (empty($salaryRange->created_by) && auth()->check()) {
                $salaryRange->created_by = auth()->id();
            }
            
            // Set default currency based on country if not set
            if (empty($salaryRange->currency) && !empty($salaryRange->country_code)) {
                $salaryRange->currency = self::getCurrencyForCountry($salaryRange->country_code);
            }
        });

        static::updating(function ($salaryRange) {
            // Update currency if country changes and currency is not manually set
            if ($salaryRange->isDirty('country_code') && !$salaryRange->isDirty('currency')) {
                $salaryRange->currency = self::getCurrencyForCountry($salaryRange->country_code);
            }
        });
    }

    /**
     * Get currency for a given country code
     */
    public static function getCurrencyForCountry(string $countryCode): string
    {
        return self::COUNTRY_CURRENCY[strtoupper($countryCode)] ?? 'USD';
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
        foreach (self::COUNTRY_CURRENCY as $code => $currency) {
            $countries[$code] = [
                'name' => self::COUNTRY_NAMES[$code] ?? $code,
                'flag' => self::COUNTRY_FLAGS[$code] ?? '🌍',
                'currency' => $currency,
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

    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeMinSalary($query, $amount)
    {
        return $query->where('min_salary', '>=', $amount);
    }

    public function scopeMaxSalary($query, $amount)
    {
        return $query->where('max_salary', '<=', $amount);
    }

    public function scopeSalaryBetween($query, $min, $max)
    {
        return $query->where('min_salary', '>=', $min)
                     ->where('max_salary', '<=', $max);
    }

    // Accessors
    public function getDisplayNameAttribute()
    {
        if ($this->min_salary && $this->max_salary) {
            $flag = self::getCountryFlag($this->country_code);
            return "{$flag} {$this->name} ({$this->currency})";
        }
        return $this->name;
    }

    public function getFormattedRangeAttribute()
    {
        if ($this->min_salary && $this->max_salary) {
            $symbol = $this->getCurrencySymbol($this->currency);
            return "{$symbol}{$this->min_salary} - {$symbol}{$this->max_salary} {$this->currency}";
        } elseif ($this->min_salary) {
            $symbol = $this->getCurrencySymbol($this->currency);
            return "{$symbol}{$this->min_salary}+ {$this->currency}";
        } elseif ($this->max_salary) {
            $symbol = $this->getCurrencySymbol($this->currency);
            return "Up to {$symbol}{$this->max_salary} {$this->currency}";
        }
        return "Negotiable";
    }

    public function getCountryNameAttribute()
    {
        return self::getCountryName($this->country_code);
    }

    public function getCountryFlagAttribute()
    {
        return self::getCountryFlag($this->country_code);
    }

    public function getSeoAttributes()
    {
        $countryName = $this->country_name;
        return [
            'title' => $this->meta_title ?? "{$this->name} Jobs in {$countryName}",
            'description' => $this->meta_description ?? "Find jobs paying {$this->name} in {$countryName}. Browse career opportunities with salaries ranging from {$this->formatted_range}.",
            'keywords' => "{$this->name} jobs, {$this->name} salary, jobs in {$countryName}, {$this->currency} jobs"
        ];
    }

    public function getUrlAttribute()
    {
        return url("/{$this->country_code}/salary/{$this->slug}");
    }

    /**
     * Get currency symbol
     */
    private function getCurrencySymbol($currency)
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => 'A$',
            'UGX' => 'USh',
            'KES' => 'KSh',
            'TZS' => 'TSh',
            'RWF' => 'FRw',
            'ZAR' => 'R',
            'ZMW' => 'ZK',
            'MWK' => 'MK',
            'SGD' => 'S$',
            'NGN' => '₦',
            'GHS' => 'GH₵',
            'ETB' => 'Br',
            'EGP' => 'E£',
        ];
        return $symbols[$currency] ?? $currency;
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobPosts()
    {
        return $this->hasMany(JobPost::class, 'salary_range_id');
    }
}