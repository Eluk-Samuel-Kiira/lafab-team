<?php

namespace App\Http\Controllers\Job\Migration;

use App\Http\Controllers\Controller;
use App\Models\Job\Company;
use App\Models\Industry;
use App\Models\JobLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{ DB, Log };
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompanyMigrationController extends Controller
{
    /**
     * Display migration interface.
     */
    public function index()
    {
        return view('migrations.companies');
    }

    /**
     * Get migration statistics.
     */
    public function getStatistics()
    {
        $stats = [
            'total' => Company::count(),
            'migrated' => Company::whereNotNull('migrated_at')->count(),
            'pending' => Company::whereNull('migrated_at')->count(),
            'active' => Company::where('is_active', true)->count(),
            'inactive' => Company::where('is_active', false)->count(),
            'verified' => Company::where('is_verified', true)->count(),
            'gold' => Company::where('is_gold', true)->count(),
            'featured' => Company::where('is_featured', true)->count(),
            'legacy' => Company::whereNotNull('legacy_id')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get list of companies for migration.
     */
    public function getCompanies(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $country = $request->get('country', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $query = Company::with('creator');

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('slug', 'like', '%' . $search . '%')
                  ->orWhere('legacy_alias', 'like', '%' . $search . '%')
                  ->orWhere('contact_email', 'like', '%' . $search . '%')
                  ->orWhere('legacy_id', $search);
            });
        }

        if ($status === 'migrated') {
            $query->whereNotNull('migrated_at');
        } elseif ($status === 'pending') {
            $query->whereNull('migrated_at');
        }

        if (!empty($country)) {
            $query->where('country_code', strtoupper($country));
        }

        $companies = $query->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($companies);
    }

    /**
     * Get a single company details.
     */
    public function show($id)
    {
        try {
            $company = Company::findOrFail($id);
            return response()->json($company);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        }
    }

    

    /**
     * Import companies from SQL file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'sql_file' => 'required|file|mimes:sql,txt|max:10240',
            'country_code' => 'required|string|size:2',
            'table_name' => 'required|string|max:100',
        ]);

        try {
            $file = $request->file('sql_file');
            $content = file_get_contents($file->getPathname());
            
            // Parse the SQL file to extract INSERT statements
            $inserts = $this->parseInsertStatements($content, $request->table_name);
            
            if (empty($inserts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No INSERT statements found for the specified table.'
                ], 400);
            }

            DB::beginTransaction();

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($inserts as $insertData) {
                $legacyId = $insertData['id'] ?? null;
                
                if (!$legacyId) {
                    $skipped++;
                    continue;
                }

                // Check if already exists
                $exists = Company::where('legacy_id', $legacyId)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $companyName = $this->cleanString($insertData['name'] ?? null);
                if (!$companyName) {
                    $skipped++;
                    continue;
                }

                $alias = $insertData['alias'] ?? Str::slug($companyName);
                $slug = $this->cleanString($alias);
                
                // Ensure unique slug
                if (Company::where('slug', $slug)->exists()) {
                    $slug = $slug . '-' . $legacyId;
                }

                // Sanitize dates
                $goldStartDate = $this->sanitizeDate($insertData['startgolddate'] ?? null);
                $goldEndDate = $this->sanitizeDate($insertData['endgolddate'] ?? null);
                $featuredStartDate = $this->sanitizeDate($insertData['startfeatureddate'] ?? null);
                $featuredEndDate = $this->sanitizeDate($insertData['endfeatureddate'] ?? null);

                $company = new Company();
                $company->forceFill([
                    'id' => $legacyId,
                    'name' => $companyName,
                    'slug' => $slug,
                    'logo' => $this->cleanString($insertData['logofilename'] ?? null),
                    'description' => $this->cleanHtml($insertData['description'] ?? null),
                    'website' => $this->cleanString($insertData['url'] ?? null),
                    'contact_name' => $this->cleanString($insertData['contactname'] ?? null),
                    'contact_email' => $this->cleanString($insertData['contactemail'] ?? null),
                    'contact_phone' => $this->cleanString($insertData['contactphone'] ?? null),
                    'address1' => $this->cleanString($insertData['address1'] ?? null),
                    'company_size' => $this->cleanString($insertData['companysize'] ?? null),
                    'legacy_id' => $legacyId,
                    'country_code' => strtoupper($request->country_code),
                    'legacy_alias' => $this->cleanString($alias),
                    'legacy_uid' => $this->cleanString($insertData['uid'] ?? null),
                    'legacy_metadata' => $insertData,
                    'is_active' => $this->sanitizeBoolean($insertData['status'] ?? 1),
                    'is_verified' => $this->sanitizeBoolean($insertData['status'] ?? 0),
                    'is_gold' => $this->sanitizeBoolean($insertData['isgoldcompany'] ?? 0),
                    'is_featured' => $this->sanitizeBoolean($insertData['isfeaturedcompany'] ?? 0),
                    'gold_start_date' => $goldStartDate,
                    'gold_end_date' => $goldEndDate,
                    'featured_start_date' => $featuredStartDate,
                    'featured_end_date' => $featuredEndDate,
                    'package_id' => $this->sanitizeInteger($insertData['packageid'] ?? null),
                    'payment_history_id' => $this->sanitizeInteger($insertData['paymenthistoryid'] ?? null),
                    'hits' => $this->sanitizeInteger($insertData['hits'] ?? 0),
                    'migrated_at' => now(),
                    'created_by' => auth()->id(),
                ])->save();

                $imported++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$imported} companies. Skipped {$skipped} duplicates.",
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sanitize date values.
     */
    private function sanitizeDate($value)
    {
        if (!$value) {
            return null;
        }

        // Clean the value
        $value = trim($value);
        
        // If it's '0000-00-00' or invalid, return null
        if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        // If it's just '1970-01-01' or '1970-01-01 00:00:00' or similar, return null
        if (strpos($value, '1970-01-01') === 0) {
            return null;
        }

        // If it's a numeric value like '2', return null
        if (is_numeric($value) && strlen($value) <= 4) {
            return null;
        }

        try {
            // Try to parse the date
            $date = Carbon::parse($value);
            
            // If the year is 1970 or earlier, return null (likely invalid data)
            if ($date->year <= 1970) {
                return null;
            }
            
            return $date;
        } catch (\Exception $e) {
            // If parsing fails, return null
            return null;
        }
    }

    /**
     * Sanitize boolean values.
     */
    private function sanitizeBoolean($value)
    {
        if ($value === null || $value === '') {
            return false;
        }
        
        // Handle string values
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on']);
        }
        
        return (bool) $value;
    }

    /**
     * Sanitize integer values.
     */
    private function sanitizeInteger($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // If it's a string with non-numeric characters, try to extract numeric part
        if (is_string($value) && !is_numeric($value)) {
            preg_match('/\d+/', $value, $matches);
            return $matches ? (int) $matches[0] : null;
        }
        
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Clean string values.
     */
    private function cleanString($value)
    {
        if (!$value) {
            return null;
        }
        
        // Trim and remove excessive whitespace
        $value = trim(preg_replace('/\s+/', ' ', $value));
        
        // If the string is just whitespace or empty, return null
        if (empty($value)) {
            return null;
        }
        
        return $value;
    }

    /**
     * Clean HTML content.
     */
    private function cleanHtml($content)
    {
        if (!$content) return null;
        
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Strip tags for plain text, but keep line breaks
        $content = strip_tags($content, '<p><br><ul><li><strong><em><h1><h2><h3><h4><div><span>');
        
        return $content;
    }


    /**
     * Parse INSERT statements from SQL file.
     */
    private function parseInsertStatements($content, $tableName)
    {
        $pattern = '/INSERT\s+INTO\s+`?' . preg_quote($tableName, '/') . '`?\s*(?:\(([^)]+)\))?\s*VALUES\s*(.+?);/is';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return [];
        }

        // Column mapping for the companies table
        $defaultColumns = [
            'id', 'uid', 'category', 'name', 'alias', 'url', 'logofilename', 'logoisfile',
            'logo', 'smalllogofilename', 'smalllogoisfile', 'smalllogo', 'aboutcompanyfilename',
            'aboutcompanyisfile', 'aboutcompanyfilesize', 'aboutcompany', 'contactname',
            'contactphone', 'companyfax', 'contactemail', 'since', 'companysize', 'income',
            'description', 'country', 'state', 'county', 'city', 'zipcode', 'address1',
            'address2', 'created', 'modified', 'hits', 'metadescription', 'metakeywords',
            'status', 'packageid', 'paymenthistoryid', 'isgoldcompany', 'startgolddate',
            'endgolddate', 'isfeaturedcompany', 'startfeatureddate', 'endfeatureddate',
            'notifications', 'serverstatus', 'serverid', 'params'
        ];

        $results = [];
        foreach ($matches as $match) {
            $columns = $defaultColumns;
            if (!empty($match[1])) {
                $columns = array_map(fn($c) => trim($c, " `\t\n"), explode(',', $match[1]));
            }

            preg_match_all('/\(((?:[^()\']|\'(?:[^\'\\\\]|\\\\.|\'\')*\')*)\)/s', $match[2], $rowMatches);

            foreach ($rowMatches[1] as $valuesPart) {
                $values = $this->splitValues($valuesPart);
                if (count($values) >= count($columns)) {
                    $row = [];
                    foreach ($columns as $index => $column) {
                        $value = $values[$index] ?? null;
                        if ($value !== null) {
                            $value = trim($value);
                            if (strtoupper($value) === 'NULL') {
                                $value = null;
                            } elseif (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
                                $value = substr($value, 1, -1);
                                $value = str_replace(["\\'", "''"], "'", $value);
                            }
                        }
                        $row[$column] = $value;
                    }
                    $results[] = $row;
                }
            }
        }

        return $results;
    }

    /**
     * Split SQL VALUES string into individual values.
     */
    private function splitValues($valuesPart)
    {
        $result = [];
        $current = '';
        $inQuotes = false;
        $openParentheses = 0;
        $len = strlen($valuesPart);

        for ($i = 0; $i < $len; $i++) {
            $char = $valuesPart[$i];
            
            if ($char === "'") {
                $inQuotes = !$inQuotes;
                $current .= $char;
            } elseif ($char === '(' && !$inQuotes) {
                $openParentheses++;
                $current .= $char;
            } elseif ($char === ')' && !$inQuotes) {
                $openParentheses--;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes && $openParentheses === 0) {
                $result[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        if (trim($current) !== '') {
            $result[] = trim($current);
        }

        return $result;
    }



    /**
     * Migrate a single company.
     */
    public function migrateSingle($id)
    {
        try {
            $company = Company::findOrFail($id);

            if ($company->migrated_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'This company has already been migrated.'
                ], 400);
            }

            $company->update([
                'migrated_at' => now(),
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Company marked as migrated successfully.',
                'company' => $company
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to migrate company: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk migrate companies.
     */
    public function bulkMigrate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:companies,id',
        ]);

        try {
            $count = Company::whereIn('id', $request->ids)
                ->whereNull('migrated_at')
                ->update([
                    'migrated_at' => now(),
                    'created_by' => auth()->id(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully migrated {$count} companies.",
                'count' => $count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk migration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback migration for a company.
     */
    public function rollback($id)
    {
        try {
            $company = Company::findOrFail($id);

            $company->update([
                'migrated_at' => null,
                'created_by' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Company migration rolled back successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a company.
     */
    public function update(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);
            
            $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:companies,slug,' . $id,
                'country_code' => 'required|string|size:2',
                'is_active' => 'sometimes|boolean',
                'is_verified' => 'sometimes|boolean',
            ]);

            $isActive = filter_var($request->input('is_active', false), FILTER_VALIDATE_BOOLEAN);
            $isVerified = filter_var($request->input('is_verified', false), FILTER_VALIDATE_BOOLEAN);

            $company->update([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'website' => $request->website,
                'contact_name' => $request->contact_name,
                'contact_email' => $request->contact_email,
                'contact_phone' => $request->contact_phone,
                'address1' => $request->address1,
                'company_size' => $request->company_size,
                'country_code' => strtoupper($request->country_code),
                'is_active' => $isActive,
                'is_verified' => $isVerified,
                'industry_id' => $request->industry_id,
                'location_id' => $request->location_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'company' => $company
            ]);
        } catch (\Exception $e) {
            Log::error('Company update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a company.
     */
    public function destroy($id)
    {
        try {
            $company = Company::findOrFail($id);
            
            // Check if company has related jobs
            // if ($company->jobs()->count() > 0) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Cannot delete company with associated jobs.'
            //     ], 400);
            // }
            
            $company->delete();

            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Company delete failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company: ' . $e->getMessage()
            ], 500);
        }
    }
}