<?php

namespace App\Services\Indexing;

use App\Models\Job\JobPost;
use App\Models\Job\Company;
use App\Models\Job\JobCategory;
use App\Models\Job\JobLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SitemapService
{
    // Country configurations with their frontend URLs
    private const COUNTRIES = [
        'AU' => [
            'code' => 'au',
            'domain' => 'greataustraliajobs.com',
            'frontend_url' => 'https://www.greataustraliajobs.com',
            'name' => 'Australia',
            'country_code' => 'AU',
            'enabled' => true,
        ],
        'UG' => [
            'code' => 'ug',
            'domain' => 'greatugandajobs.com',
            'frontend_url' => 'https://www.greatugandajobs.com',
            'name' => 'Uganda',
            'country_code' => 'UG',
            'enabled' => true,
        ],
        'KE' => [
            'code' => 'ke',
            'domain' => 'greatkenyanjobs.com',
            'frontend_url' => 'https://www.greatkenyanjobs.com',
            'name' => 'Kenya',
            'country_code' => 'KE',
            'enabled' => true,
        ],
        'TZ' => [
            'code' => 'tz',
            'domain' => 'greattanzaniajobs.com',
            'frontend_url' => 'https://www.greattanzaniajobs.com',
            'name' => 'Tanzania',
            'country_code' => 'TZ',
            'enabled' => true,
        ],
        'RW' => [
            'code' => 'rw',
            'domain' => 'greatrwandajobs.com',
            'frontend_url' => 'https://www.greatrwandajobs.com',
            'name' => 'Rwanda',
            'country_code' => 'RW',
            'enabled' => true,
        ],
        'MW' => [
            'code' => 'mw',
            'domain' => 'greatmalawijobs.com',
            'frontend_url' => 'https://www.greatmalawijobs.com',
            'name' => 'Malawi',
            'country_code' => 'MW',
            'enabled' => true,
        ],
        'ZM' => [
            'code' => 'zm',
            'domain' => 'greatzambiajobs.com',
            'frontend_url' => 'https://www.greatzambiajobs.com',
            'name' => 'Zambia',
            'country_code' => 'ZM',
            'enabled' => true,
        ],
        'SG' => [
            'code' => 'sg',
            'domain' => 'greatsingaporejobs.com',
            'frontend_url' => 'https://www.greatsingaporejobs.com',
            'name' => 'Singapore',
            'country_code' => 'SG',
            'enabled' => true,
        ],
    ];

    private string $publicDir;

    public function __construct()
    {
        $this->publicDir = public_path('sitemaps');
        
        if (!is_dir($this->publicDir)) {
            mkdir($this->publicDir, 0755, true);
        }
    }

    /**
     * Generate sitemap for all countries
     */
    public function generateAll(): array
    {
        $results = [];

        foreach (self::COUNTRIES as $code => $country) {
            if (!$country['enabled']) {
                continue;
            }
            $results[$code] = $this->generateCountrySitemap($code);
        }

        return $results;
    }

    /**
     * Generate sitemap for a specific country
     */
    public function generateCountrySitemap(string $countryCode): array
    {
        $country = self::COUNTRIES[$countryCode] ?? null;
        if (!$country || !$country['enabled']) {
            return ['error' => "Country {$countryCode} not supported or disabled"];
        }

        $frontendUrl = $country['frontend_url'];
        $countryCodeDb = $country['country_code'];
        $urls = [];

        // 1. Static pages for this country
        $urls = array_merge($urls, $this->getStaticUrls($frontendUrl));

        // 2. Job categories for this country
        $urls = array_merge($urls, $this->getCategoryUrls($frontendUrl, $countryCodeDb));

        // 3. Companies for this country
        $urls = array_merge($urls, $this->getCompanyUrls($frontendUrl, $countryCodeDb));

        // 4. Locations for this country
        $urls = array_merge($urls, $this->getLocationUrls($frontendUrl, $countryCodeDb));

        // 5. Job posts for this country
        $urls = array_merge($urls, $this->getJobUrls($frontendUrl, $countryCodeDb));

        // Remove duplicates and sort
        $urls = array_unique($urls, SORT_REGULAR);
        usort($urls, function($a, $b) {
            return strcmp($a['loc'], $b['loc']);
        });

        // Write to public/sitemaps/country_code/
        $countryDir = $this->publicDir . '/' . $country['code'];
        if (!is_dir($countryDir)) {
            mkdir($countryDir, 0755, true);
        }

        $filename = 'sitemap.xml';
        $filePath = $countryDir . '/' . $filename;
        $this->writeSitemap($filePath, $urls);

        $this->generateCountrySitemapIndex($country);

        return [
            'country' => $country['name'],
            'code' => $countryCode,
            'frontend_url' => $frontendUrl,
            'total_urls' => count($urls),
            'sitemap_path' => '/sitemaps/' . $country['code'] . '/sitemap.xml',
            'sitemap_index' => '/sitemaps/' . $country['code'] . '/sitemap_index.xml',
        ];
    }

    /**
     * Get static URLs for a frontend
     */
    private function getStaticUrls(string $frontendUrl): array
    {
        $urls = [];
        $staticPages = [
            ['url' => '/', 'freq' => 'daily', 'priority' => '1.0'],
            ['url' => '/jobs', 'freq' => 'hourly', 'priority' => '0.9'],
            ['url' => '/companies', 'freq' => 'daily', 'priority' => '0.8'],
            ['url' => '/employers/newest-jobs', 'freq' => 'daily', 'priority' => '0.8'],
            ['url' => '/employers/newest-jobs/job-categories/newest-jobs', 'freq' => 'daily', 'priority' => '0.7'], // Added this
            ['url' => '/about', 'freq' => 'monthly', 'priority' => '0.5'],
            ['url' => '/contact', 'freq' => 'monthly', 'priority' => '0.4'],
            ['url' => '/privacy-policy', 'freq' => 'monthly', 'priority' => '0.3'],
            ['url' => '/career-tips', 'freq' => 'weekly', 'priority' => '0.6'],
            ['url' => '/categories', 'freq' => 'daily', 'priority' => '0.7'],
            ['url' => '/jobs/newest-jobs', 'freq' => 'always', 'priority' => '0.9'],
            ['url' => '/jobs/weekly-jobs', 'freq' => 'always', 'priority' => '0.8'],
            ['url' => '/jobs/related-jobs', 'freq' => 'always', 'priority' => '0.8'],
            ['url' => '/jobseeker/job-alerts', 'freq' => 'weekly', 'priority' => '0.6'],
            ['url' => '/jobseeker/cv-expert-look', 'freq' => 'weekly', 'priority' => '0.5'],
            ['url' => '/employer/upload', 'freq' => 'weekly', 'priority' => '0.6'],
            ['url' => '/employer/service-pricing', 'freq' => 'monthly', 'priority' => '0.5'],
            ['url' => '/employer/employer-services', 'freq' => 'monthly', 'priority' => '0.5'],
            ['url' => '/info', 'freq' => 'monthly', 'priority' => '0.3'],
            ['url' => '/whatsapp', 'freq' => 'daily', 'priority' => '0.5'],
            ['url' => '/telegram', 'freq' => 'daily', 'priority' => '0.5'],
        ];

        foreach ($staticPages as $page) {
            $urls[] = $this->makeUrl(
                $frontendUrl . $page['url'],
                $page['freq'],
                $page['priority']
            );
        }

        return $urls;
    }

    /**
     * Get category URLs for a specific country
     * 
     * URL STRUCTURE:
     * - ALL categories: /employers/newest-jobs/job-categories/newest-jobs/{slug}
     *   Where {slug} is in format: category-administrative-jobs-in-2
     *   Example: /employers/newest-jobs/job-categories/newest-jobs/category-administrative-jobs-in-2
     */
    private function getCategoryUrls(string $frontendUrl, string $countryCode): array
    {
        $urls = [];
        
        $categories = JobCategory::where('is_active', true)
            ->where('country_code', $countryCode)
            ->withCount(['jobs' => function($q) use ($countryCode) {
                $q->where('is_active', true)
                ->where('deadline', '>=', now())
                ->where(function($sub) use ($countryCode) {
                    $sub->whereHas('jobLocation', function($loc) use ($countryCode) {
                        $loc->where('country_code', $countryCode);
                    })->orWhere('country_code', $countryCode);
                });
            }])
            ->having('jobs_count', '>', 0)
            ->get(['slug', 'updated_at', 'name']);

        foreach ($categories as $category) {
            // Check if slug already has 'category-' prefix
            $slug = $category->slug;
            if (!str_starts_with($slug, 'category-')) {
                $slug = 'category-' . $slug;
            }
            
            // Build the full URL with the new structure
            $url = $frontendUrl . '/employers/newest-jobs/job-categories/newest-jobs/' . $slug;
            
            $urls[] = $this->makeUrl(
                $url,
                'daily',
                '0.8',
                $category->updated_at?->toAtomString()
            );
        }

        return $urls;
    }

    /**
     * Get company URLs for a specific country
     * 
     * URL STRUCTURE:
     * - ALL companies: /employers/newest-jobs/company-{slug}
     *   Example: /employers/newest-jobs/company-hammondcare-5587
     *   Example: /employers/newest-jobs/company-perigon-group-pty-limited
     */
    private function getCompanyUrls(string $frontendUrl, string $countryCode): array
    {
        $urls = [];
        
        $companies = Company::where('is_active', true)
            ->where('country_code', $countryCode)
            ->withCount(['jobs' => function($q) use ($countryCode) {
                $q->where('is_active', true)
                ->where('deadline', '>=', now())
                ->where(function($sub) use ($countryCode) {
                    $sub->whereHas('jobLocation', function($loc) use ($countryCode) {
                        $loc->where('country_code', $countryCode);
                    })->orWhere('country_code', $countryCode);
                });
            }])
            ->having('jobs_count', '>', 0)
            ->get(['slug', 'updated_at', 'name', 'legacy_id']);

        foreach ($companies as $company) {
            // ✅ ALL companies use the same URL structure
            // Check if slug already has 'company-' prefix
            $slug = $company->slug;
            if (!str_starts_with($slug, 'company-')) {
                $slug = 'company-' . $slug;
            }
            
            $url = $frontendUrl . '/employers/newest-jobs/' . $slug;
            
            $urls[] = $this->makeUrl(
                $url,
                'weekly',
                '0.7',
                $company->updated_at?->toAtomString()
            );
        }

        return $urls;
    }

    /**
     * Get location URLs for a specific country
     */
    private function getLocationUrls(string $frontendUrl, string $countryCode): array
    {
        $urls = [];
        
        $locations = JobLocation::where('is_active', true)
            ->where('country_code', $countryCode)
            ->withCount(['jobPosts' => function($q) {
                $q->where('is_active', true)->where('deadline', '>=', now());
            }])
            ->having('job_posts_count', '>', 0)
            ->get(['slug', 'updated_at', 'district']);

        foreach ($locations as $location) {
            $urls[] = $this->makeUrl(
                $frontendUrl . '/jobs/location/' . $location->slug,
                'weekly',
                '0.7',
                $location->updated_at?->toAtomString()
            );
        }

        return $urls;
    }

    /**
     * Get job URLs for a specific country
     * Only include jobs that have been pinged (last_pinged_at is not null)
     */
    private function getJobUrls(string $frontendUrl, string $countryCode): array
    {
        $urls = [];
        
        // Get ONLY jobs that have been pinged (last_pinged_at is not null)
        $jobs = JobPost::where('is_active', true)
            ->where('deadline', '>=', now())
            ->whereNotNull('last_pinged_at')  // ONLY pinged jobs
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->where('country_code', $countryCode)
            ->get(['slug', 'updated_at', 'is_featured', 'is_urgent', 'legacy_id', 'id', 'created_at', 'published_at', 'published_until', 'last_pinged_at']);

        foreach ($jobs as $job) {
            // All jobs use the same URL structure
            $url = $frontendUrl . '/jobs/' . $job->slug;

            // Use last_pinged_at as the lastmod
            $lastmod = $job->last_pinged_at ?? $job->updated_at;
            
            // Use 'always' for featured/urgent jobs, 'weekly' for others
            $changefreq = ($job->is_featured || $job->is_urgent) ? 'always' : 'weekly';
            
            $urls[] = $this->makeUrl(
                $url,
                $changefreq,
                $job->is_featured ? '0.9' : '0.8',
                $lastmod?->toAtomString()
            );
        }

        return $urls;
    }

    /**
     * Generate sitemap index for a country
     */
    private function generateCountrySitemapIndex(array $country): void
    {
        $frontendUrl = $country['frontend_url'];
        $countryCode = $country['code'];
        $countryDir = $this->publicDir . '/' . $countryCode;
        $sitemaps = [];

        if (is_dir($countryDir)) {
            $files = scandir($countryDir);
            foreach ($files as $file) {
                if (str_starts_with($file, 'sitemap') && str_ends_with($file, '.xml') && $file !== 'sitemap_index.xml') {
                    $sitemaps[] = [
                        'loc' => $frontendUrl . '/sitemaps/' . $countryCode . '/' . $file,
                        'lastmod' => now()->toAtomString(),
                    ];
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($sitemaps as $sitemap) {
            $xml .= "  <sitemap>\n";
            $xml .= "    <loc>" . htmlspecialchars($sitemap['loc']) . "</loc>\n";
            $xml .= "    <lastmod>" . $sitemap['lastmod'] . "</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }
        
        $xml .= '</sitemapindex>';

        $filePath = $countryDir . '/sitemap_index.xml';
        file_put_contents($filePath, $xml);
    }

    /**
     * Create a URL entry for sitemap
     */
    private function makeUrl(string $loc, string $changefreq, string $priority, ?string $lastmod = null): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    /**
     * Write sitemap XML to file
     */
    private function writeSitemap(string $filePath, array $urls): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
            if (!empty($url['lastmod'])) {
                $xml .= "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
            }
            $xml .= "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
            $xml .= "    <priority>" . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        
        $xml .= '</urlset>';

        file_put_contents($filePath, $xml);
    }

    /**
     * Get sitemap statistics
     */
    public function getStats(): array
    {
        $stats = [
            'total_jobs' => JobPost::where('is_active', true)->where('deadline', '>=', now())->count(),
            'legacy_jobs' => JobPost::where('is_active', true)->where('deadline', '>=', now())->whereNotNull('legacy_id')->count(),
            'new_jobs' => JobPost::where('is_active', true)->where('deadline', '>=', now())->whereNull('legacy_id')->count(),
            'countries' => [],
        ];

        foreach (self::COUNTRIES as $code => $country) {
            if (!$country['enabled']) {
                continue;
            }
            
            $countryCode = $country['country_code'];
            $stats['countries'][$code] = [
                'name' => $country['name'],
                'domain' => $country['domain'],
                'frontend_url' => $country['frontend_url'],
                'jobs' => JobPost::where('is_active', true)
                    ->where('deadline', '>=', now())
                    ->where('country_code', $countryCode)
                    ->count(),
                'sitemap_exists' => is_dir($this->publicDir . '/' . $country['code']),
            ];
        }

        return $stats;
    }

    /**
     * Ping search engines for a specific country
     * 
     * Flow:
     * 1. Mark all unpinged jobs as pinged (update statuses)
     * 2. Regenerate sitemap with all pinged jobs
     * 3. Send ping to search engines with updated sitemap
     */
    public function pingSearchEngines(string $countryCode): array
    {
        $country = self::COUNTRIES[$countryCode] ?? null;
        if (!$country) {
            return ['error' => "Country {$countryCode} not supported"];
        }

        // Check if we're in production or local environment
        // $appEnv = config('app.env');
        // $isLocal = in_array($appEnv, ['local', 'development', 'testing']);
        
        // if ($isLocal) {
        //     Log::info("Skipping search engine ping in {$appEnv} environment for country {$countryCode}");
        //     return [
        //         'status' => 'skipped',
        //         'message' => "Ping skipped in {$appEnv} environment",
        //         'environment' => $appEnv,
        //         'country' => $countryCode,
        //     ];
        // }

        // ✅ STEP 1: Update ALL unpinged active jobs in this country
        try {
            $now = now();
            $fiftyYearsFromNow = $now->copy()->addYears(50);
            
            $updated = JobPost::where('is_active', true)
                ->where('country_code', $country['country_code'])
                ->where('deadline', '>=', now())
                ->whereNull('last_pinged_at')  // Only unpinged jobs
                ->update([
                    'last_pinged_at' => $now,
                    'is_pinged' => true,
                    'published_at' => $now,  // Set published_at when pinged
                    'published_until' => $fiftyYearsFromNow,  // 50 years from now
                ]);
            
            Log::info("Updated {$updated} unpinged jobs in country {$countryCode}");
        } catch (\Exception $e) {
            Log::error("Failed to update unpinged jobs: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to update jobs: ' . $e->getMessage(),
                'country' => $countryCode,
            ];
        }

        // ✅ STEP 2: Regenerate the sitemap for this country (includes newly pinged jobs)
        Log::info("Regenerating sitemap for country: {$countryCode}");
        $sitemapResult = $this->generateCountrySitemap($countryCode);
        
        if (isset($sitemapResult['error'])) {
            Log::error("Failed to generate sitemap: " . $sitemapResult['error']);
            return [
                'status' => 'error',
                'message' => 'Failed to generate sitemap: ' . $sitemapResult['error'],
                'country' => $countryCode,
            ];
        }

        // ✅ STEP 3: Ping search engines with the updated sitemap
        $sitemapUrl = urlencode($country['frontend_url'] . '/sitemaps/' . $country['code'] . '/sitemap_index.xml');
        $results = [];

        $engines = [
            'google' => "https://www.google.com/ping?sitemap={$sitemapUrl}",
            'bing' => "https://www.bing.com/ping?sitemap={$sitemapUrl}",
            'yandex' => "https://webmaster.yandex.com/ping?sitemap={$sitemapUrl}",
        ];

        foreach ($engines as $name => $url) {
            try {
                $response = Http::timeout(10)->get($url);
                $results[$name] = [
                    'success' => $response->successful(),
                    'status' => $response->status(),
                    'message' => $response->successful() ? 'Ping successful' : 'Ping failed',
                ];
                
                if ($response->successful()) {
                    Log::info("Successfully pinged {$name} for country {$countryCode}");
                } else {
                    Log::warning("Failed to ping {$name} for country {$countryCode}: Status " . $response->status());
                }
            } catch (\Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'status' => 'error',
                    'message' => 'Connection error: ' . $e->getMessage(),
                ];
                Log::warning("Search engine ping failed for {$name}: " . $e->getMessage());
            }
        }

        // Check if any ping was successful
        $pingSuccess = false;
        foreach ($results as $result) {
            if (isset($result['success']) && $result['success'] === true) {
                $pingSuccess = true;
                break;
            }
        }

        return [
            'status' => $pingSuccess ? 'success' : 'failed',
            'message' => $pingSuccess ? 'Sitemap generated and search engines notified' : 'Sitemap generated but search engine ping failed',
            'country' => $countryCode,
            'jobs_updated' => $updated ?? 0,
            'sitemap' => $sitemapResult,
            'ping_results' => $results,
        ];
    }


}