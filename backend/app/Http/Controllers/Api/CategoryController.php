<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Category::with('products');

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Pagination
            $perPage = $request->get('per_page', 50);
            $categories = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $categories->items(),
                'meta' => [
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single category with products
     * GET /api/v1/categories/{id}
     */
    public function show($id)
    {
        try {
            $category = Category::with('products')->find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $category,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch category: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create category (Manager only)
     * POST /api/v1/categories
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name',
            ]);

            $category = Category::create($validated);

            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Membuat Category',
                'details' => ['category_id' => $category->id, 'name' => $category->name],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Membuat Kategori',
                'data' => $category,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update category (Manager only)
     * PUT /api/v1/categories/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori produk tidak ditemukan',
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name,' . $id,
            ]);

            // Store old values for audit
            $oldValues = $category->only(array_keys($validated));

            // Update category
            $category->update($validated);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Memperbarui kategori',
                'details' => [
                    'category_id' => $category->id,
                    'old_values' => $oldValues,
                    'new_values' => $validated,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Memperbarui Kategori',
                'data' => $category,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete category (Manager only)
     * DELETE /api/v1/categories/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan',
                ], 404);
            }

            // Check if category has products
            if ($category->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus kategori yang memiliki produk',
                ], 422);
            }

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Menghapus Category',
                'details' => ['category_id' => $category->id, 'name' => $category->name],
                'ip_address' => $request->ip(),
            ]);

            // Delete
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Menghapus Kategori',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category: ' . $e->getMessage(),
            ], 500);
        }
    }
}
