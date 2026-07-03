<?php

namespace App\Http\Controllers\Job\Migration;

use App\Http\Controllers\Controller;
use App\Models\Job\JobCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{ DB, Log };
use Illuminate\Support\Str;
use Carbon\Carbon;

class JobCategoryMigrationController extends Controller
{
    /**
     * Display migration interface.
     */
    public function index()
    {
        return view('migrations.job-categories');
    }

    /**
     * Get migration statistics.
     */
    public function getStatistics()
    {
        $stats = [
            'total' => JobCategory::count(),
            'migrated' => JobCategory::whereNotNull('migrated_at')->count(),
            'pending' => JobCategory::whereNull('migrated_at')->count(),
            'active' => JobCategory::where('is_active', true)->count(),
            'inactive' => JobCategory::where('is_active', false)->count(),
            'legacy' => JobCategory::whereNotNull('legacy_id')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get list of categories for migration.
     */
    public function getCategories(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $country = $request->get('country', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $query = JobCategory::with('migratedBy');

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('slug', 'like', '%' . $search . '%')
                  ->orWhere('legacy_alias', 'like', '%' . $search . '%')
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

        $categories = $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($categories);
    }

    /**
     * Get a single category details.
     */
    public function show($id)
    {
        try {
            $category = JobCategory::findOrFail($id);
            return response()->json($category);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
     * Import categories from SQL file.
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
                $exists = JobCategory::where('legacy_id', $legacyId)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $catTitle = $insertData['cat_title'] ?? $insertData['name'] ?? null;
                if (!$catTitle) {
                    $skipped++;
                    continue;
                }

                $slug = $insertData['alias'] ?? Str::slug($catTitle);
                
                // Ensure unique slug
                if (JobCategory::where('slug', $slug)->exists()) {
                    $slug = $slug . '-' . $legacyId;
                }

                $category = new JobCategory();
                $category->forceFill([
                    'id' => $legacyId, // preserve legacy PK exactly
                    'name' => $catTitle,
                    'slug' => $slug,
                    'description' => $insertData['cat_value'] ?? null,
                    'legacy_id' => $legacyId,
                    'country_code' => strtoupper($request->country_code),
                    'legacy_alias' => $insertData['alias'] ?? null,
                    'legacy_cat_value' => $insertData['cat_value'] ?? null,
                    'is_active' => isset($insertData['isactive']) ? (bool) $insertData['isactive'] : true,
                    'is_default' => isset($insertData['isdefault']) ? (bool) $insertData['isdefault'] : false,
                    'sort_order' => $insertData['ordering'] ?? 0,
                    'legacy_metadata' => $insertData,
                    'migrated_at' => now(),
                    'migrated_by' => auth()->id(),
                ])->save();

                $imported++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$imported} categories. Skipped {$skipped} duplicates.",
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Job category import failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Parse INSERT statements from SQL file.
     */
    private function parseInsertStatements($content, $tableName)
    {
        // Table name, OPTIONAL column list, VALUES, then everything up to the terminating ;
        $pattern = '/INSERT\s+INTO\s+`?' . preg_quote($tableName, '/') . '`?\s*(?:\(([^)]+)\))?\s*VALUES\s*(.+?);/is';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return [];
        }

        // Fallback columns if the dump has no explicit column list
        $defaultColumns = ['id', 'cat_value', 'cat_title', 'alias', 'isactive', 'isdefault', 'ordering', 'serverid'];

        $results = [];
        foreach ($matches as $match) {
            $columns = $defaultColumns;
            if (!empty($match[1])) {
                $columns = array_map(fn($c) => trim($c, " `\t\n"), explode(',', $match[1]));
            }

            // Split the VALUES block into individual row tuples: (...), (...), (...)
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
                                $value = str_replace(["\\'", "''"], "'", $value); // unescape quotes
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
     * Migrate a single category.
     */
    public function migrateSingle($id)
    {
        try {
            $category = JobCategory::findOrFail($id);

            if ($category->migrated_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'This category has already been migrated.'
                ], 400);
            }

            $category->update([
                'migrated_at' => now(),
                'migrated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category marked as migrated successfully.',
                'category' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to migrate category: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk migrate categories.
     */
    public function bulkMigrate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:job_categories,id',
        ]);

        try {
            $count = JobCategory::whereIn('id', $request->ids)
                ->whereNull('migrated_at')
                ->update([
                    'migrated_at' => now(),
                    'migrated_by' => auth()->id(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully migrated {$count} categories.",
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
     * Rollback migration for a category.
     */
    public function rollback($id)
    {
        try {
            $category = JobCategory::findOrFail($id);

            $category->update([
                'migrated_at' => null,
                'migrated_by' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category migration rolled back successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a category.
     */
    public function update(Request $request, $id)
    {
        try {
            $category = JobCategory::findOrFail($id);
            
            $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:job_categories,slug,' . $id,
                'country_code' => 'required|string|size:2',
                'is_active' => 'sometimes|boolean',
            ]);

            // Handle is_active properly
            $isActive = filter_var($request->input('is_active', false), FILTER_VALIDATE_BOOLEAN);

            $category->update([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'country_code' => strtoupper($request->country_code),
                'is_active' => $isActive,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'category' => $category
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Category update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a category.
     */
    public function destroy($id)
    {
        try {
            $category = JobCategory::findOrFail($id);
            
            // Check if category has related jobs
            // If you have a jobs relationship, check it here
            // if ($category->jobs()->count() > 0) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Cannot delete category with associated jobs.'
            //     ], 400);
            // }
            
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Category delete failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category: ' . $e->getMessage()
            ], 500);
        }
    }
}
