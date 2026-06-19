<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    /**
     * Display departments list.
     */
    public function index()
    {
        return view('admin.departments.index');
    }

    /**
     * Get departments data for DataTable.
     */
    public function getData(Request $request)
    {
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = 20;

        $query = Department::with('headOfDepartment');

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $departments = $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = [
            'current_page' => $departments->currentPage(),
            'data' => $departments->items(),
            'first_page_url' => $departments->url(1),
            'from' => $departments->firstItem(),
            'last_page' => $departments->lastPage(),
            'last_page_url' => $departments->url($departments->lastPage()),
            'next_page_url' => $departments->nextPageUrl(),
            'prev_page_url' => $departments->previousPageUrl(),
            'to' => $departments->lastItem(),
            'total' => $departments->total(),
            'per_page' => $perPage,
        ];

        return response()->json($data);
    }

    /**
     * Get all departments for dropdown.
     */
    public function getAll()
    {
        $departments = Department::active()->ordered()->get(['id', 'name', 'code']);
        return response()->json($departments);
    }

    /**
     * Get users for head of department dropdown.
     */
    public function getUsers()
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        return response()->json($users);
    }

    /**
     * Store a new department.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:departments',
            'code' => 'required|string|max:20|unique:departments',
            'email' => 'nullable|email',
        ]);

        $department = Department::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'icon' => $request->icon,
            'color' => $request->color,
            'head_of_department_id' => $request->head_of_department_id,
            'email' => $request->email,
            'phone' => $request->phone,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'department' => $department
        ]);
    }

    /**
     * Get department details for editing.
     */
    public function show($id)
    {
        $department = Department::with('headOfDepartment')->findOrFail($id);
        return response()->json($department);
    }

    /**
     * Update a department.
     */
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $id,
            'code' => 'required|string|max:20|unique:departments,code,' . $id,
            'email' => 'nullable|email',
        ]);

        $department->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'icon' => $request->icon,
            'color' => $request->color,
            'head_of_department_id' => $request->head_of_department_id,
            'email' => $request->email,
            'phone' => $request->phone,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully',
            'department' => $department
        ]);
    }

    /**
     * Delete a department.
     */
    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        
        // Check if department has users
        if ($department->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department. It has ' . $department->users()->count() . ' user(s) assigned.'
            ], 400);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully'
        ]);
    }

    /**
     * Toggle department status.
     */
    public function toggleStatus($id)
    {
        $department = Department::findOrFail($id);
        $department->is_active = !$department->is_active;
        $department->save();

        return response()->json([
            'success' => true,
            'message' => 'Department ' . ($department->is_active ? 'activated' : 'deactivated') . ' successfully',
            'is_active' => $department->is_active
        ]);
    }
}