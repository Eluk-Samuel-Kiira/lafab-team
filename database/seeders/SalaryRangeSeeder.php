<?php

namespace Database\Seeders;

use App\Models\Job\SalaryRange;
use Illuminate\Database\Seeder;

class SalaryRangeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Salary Range Seeder...');
        $this->command->newLine();

        // Seed Australia
        $this->seedAustralia();
        
        // Seed Rwanda
        $this->seedRwanda();
        
        // Seed Malawi
        $this->seedMalawi();

        $this->command->newLine();
        $this->command->info('✅ Salary Range Seeder completed successfully!');
        
        // Show summary
        $this->showSummary();
    }

    private function seedAustralia(): void
    {
        $this->command->info('🇦🇺 Seeding Australia (AUD)...');
        
        $ranges = [
            // Entry Level
            ['name' => 'Entry Level', 'min_salary' => 45000, 'max_salary' => 60000, 'sort_order' => 1],
            ['name' => 'Junior', 'min_salary' => 60000, 'max_salary' => 80000, 'sort_order' => 2],
            
            // Mid Level
            ['name' => 'Mid Level', 'min_salary' => 80000, 'max_salary' => 100000, 'sort_order' => 3],
            ['name' => 'Senior', 'min_salary' => 100000, 'max_salary' => 130000, 'sort_order' => 4],
            
            // Senior Level
            ['name' => 'Lead', 'min_salary' => 130000, 'max_salary' => 160000, 'sort_order' => 5],
            ['name' => 'Principal', 'min_salary' => 160000, 'max_salary' => 200000, 'sort_order' => 6],
            
            // Executive
            ['name' => 'Director', 'min_salary' => 200000, 'max_salary' => 250000, 'sort_order' => 7],
            ['name' => 'Executive', 'min_salary' => 250000, 'max_salary' => 350000, 'sort_order' => 8],
            
            // Specific Ranges
            ['name' => 'Under 50k', 'min_salary' => 0, 'max_salary' => 50000, 'sort_order' => 9],
            ['name' => '50k - 80k', 'min_salary' => 50000, 'max_salary' => 80000, 'sort_order' => 10],
            ['name' => '80k - 120k', 'min_salary' => 80000, 'max_salary' => 120000, 'sort_order' => 11],
            ['name' => '120k - 150k', 'min_salary' => 120000, 'max_salary' => 150000, 'sort_order' => 12],
            ['name' => '150k - 200k', 'min_salary' => 150000, 'max_salary' => 200000, 'sort_order' => 13],
            ['name' => '200k+', 'min_salary' => 200000, 'max_salary' => null, 'sort_order' => 14],
        ];

        $this->createSalaryRanges($ranges, 'AU', 'AUD');
        $this->command->info('✅ Australia seeded successfully!');
    }

    private function seedRwanda(): void
    {
        $this->command->info('🇷🇼 Seeding Rwanda (RWF)...');
        
        $ranges = [
            // Entry Level
            ['name' => 'Entry Level', 'min_salary' => 200000, 'max_salary' => 350000, 'sort_order' => 1],
            ['name' => 'Junior', 'min_salary' => 350000, 'max_salary' => 500000, 'sort_order' => 2],
            
            // Mid Level
            ['name' => 'Mid Level', 'min_salary' => 500000, 'max_salary' => 800000, 'sort_order' => 3],
            ['name' => 'Senior', 'min_salary' => 800000, 'max_salary' => 1200000, 'sort_order' => 4],
            
            // Senior Level
            ['name' => 'Lead', 'min_salary' => 1200000, 'max_salary' => 1800000, 'sort_order' => 5],
            ['name' => 'Principal', 'min_salary' => 1800000, 'max_salary' => 2500000, 'sort_order' => 6],
            
            // Executive
            ['name' => 'Director', 'min_salary' => 2500000, 'max_salary' => 4000000, 'sort_order' => 7],
            ['name' => 'Executive', 'min_salary' => 4000000, 'max_salary' => 6000000, 'sort_order' => 8],
            
            // Specific Ranges
            ['name' => 'Under 300k', 'min_salary' => 0, 'max_salary' => 300000, 'sort_order' => 9],
            ['name' => '300k - 500k', 'min_salary' => 300000, 'max_salary' => 500000, 'sort_order' => 10],
            ['name' => '500k - 800k', 'min_salary' => 500000, 'max_salary' => 800000, 'sort_order' => 11],
            ['name' => '800k - 1.2M', 'min_salary' => 800000, 'max_salary' => 1200000, 'sort_order' => 12],
            ['name' => '1.2M - 2M', 'min_salary' => 1200000, 'max_salary' => 2000000, 'sort_order' => 13],
            ['name' => '2M+', 'min_salary' => 2000000, 'max_salary' => null, 'sort_order' => 14],
        ];

        $this->createSalaryRanges($ranges, 'RW', 'RWF');
        $this->command->info('✅ Rwanda seeded successfully!');
    }

    private function seedMalawi(): void
    {
        $this->command->info('🇲🇼 Seeding Malawi (MWK)...');
        
        $ranges = [
            // Entry Level
            ['name' => 'Entry Level', 'min_salary' => 150000, 'max_salary' => 250000, 'sort_order' => 1],
            ['name' => 'Junior', 'min_salary' => 250000, 'max_salary' => 400000, 'sort_order' => 2],
            
            // Mid Level
            ['name' => 'Mid Level', 'min_salary' => 400000, 'max_salary' => 700000, 'sort_order' => 3],
            ['name' => 'Senior', 'min_salary' => 700000, 'max_salary' => 1000000, 'sort_order' => 4],
            
            // Senior Level
            ['name' => 'Lead', 'min_salary' => 1000000, 'max_salary' => 1500000, 'sort_order' => 5],
            ['name' => 'Principal', 'min_salary' => 1500000, 'max_salary' => 2000000, 'sort_order' => 6],
            
            // Executive
            ['name' => 'Director', 'min_salary' => 2000000, 'max_salary' => 3500000, 'sort_order' => 7],
            ['name' => 'Executive', 'min_salary' => 3500000, 'max_salary' => 5000000, 'sort_order' => 8],
            
            // Specific Ranges
            ['name' => 'Under 200k', 'min_salary' => 0, 'max_salary' => 200000, 'sort_order' => 9],
            ['name' => '200k - 400k', 'min_salary' => 200000, 'max_salary' => 400000, 'sort_order' => 10],
            ['name' => '400k - 700k', 'min_salary' => 400000, 'max_salary' => 700000, 'sort_order' => 11],
            ['name' => '700k - 1M', 'min_salary' => 700000, 'max_salary' => 1000000, 'sort_order' => 12],
            ['name' => '1M - 1.5M', 'min_salary' => 1000000, 'max_salary' => 1500000, 'sort_order' => 13],
            ['name' => '1.5M+', 'min_salary' => 1500000, 'max_salary' => null, 'sort_order' => 14],
        ];

        $this->createSalaryRanges($ranges, 'MW', 'MWK');
        $this->command->info('✅ Malawi seeded successfully!');
    }

    private function createSalaryRanges(array $ranges, string $countryCode, string $currency): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($ranges as $data) {
            $slug = \Illuminate\Support\Str::slug($data['name'] . '-' . $countryCode);
            
            // Check if already exists for this country
            $exists = SalaryRange::where('slug', $slug)
                ->where('country_code', $countryCode)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $metaTitle = "{$data['name']} Jobs in " . SalaryRange::getCountryName($countryCode);
            $metaDesc = "Find {$data['name']} jobs in " . SalaryRange::getCountryName($countryCode) . 
                        ". Browse positions paying " . SalaryRange::getCurrencyForCountry($countryCode) . 
                        " " . number_format($data['min_salary']) . " - " . 
                        ($data['max_salary'] ? number_format($data['max_salary']) : 'Unlimited');

            SalaryRange::create([
                'name' => $data['name'],
                'slug' => $slug,
                'min_salary' => $data['min_salary'],
                'max_salary' => $data['max_salary'],
                'currency' => $currency,
                'country_code' => $countryCode,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc,
                'is_active' => true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            
            $created++;
        }

        $this->command->line("  - Created: {$created}, Skipped: {$skipped}");
    }

    private function showSummary(): void
    {
        $this->command->newLine();
        $this->command->info('📊 Salary Range Summary:');
        $this->command->newLine();

        $summary = [];
        $countries = SalaryRange::getAvailableCountries();
        
        foreach (array_keys($countries) as $code) {
            $count = SalaryRange::where('country_code', $code)->count();
            $info = $countries[$code];
            $summary[] = [
                $info['flag'],
                $code,
                $info['name'],
                $count,
                $info['currency'],
            ];
        }

        $this->command->table(
            ['', 'Code', 'Country', 'Total Ranges', 'Currency'],
            $summary
        );
    }
}