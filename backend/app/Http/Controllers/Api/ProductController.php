<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
        public function index(Request $request)
    {
        try {
            // Query products
            $query = Product::with('category');

            // Filter by category_id
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by availability
            if ($request->has('is_available')) {
                $query->where('is_available', $request->boolean('is_available'));
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'meta' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with('category')->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $product,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'category_id' => 'required|exists:categories,id',
                'image_url' => 'nullable|string',
                'is_available' => 'boolean',
                'stock_quantity' => 'required|integer|min:0',
            ]);

            // Create product
            $product = Product::create($validated);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Membuat Produk',
                'details' => ['product_id' => $product->id, 'name' => $product->name],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load('category'),
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
                'message' => 'Failed to create product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // Validate input
            $validated = $request->validate([
                'name' => 'string|max:255',
                'description' => 'nullable|string',
                'price' => 'numeric|min:0',
                'category_id' => 'exists:categories,id',
                'image_url' => 'nullable|string',
                'is_available' => 'boolean',
                'stock_quantity' => 'integer|min:0',
            ]);

            // Store old values for audit
            $oldValues = $product->only(array_keys($validated));

            // Update product
            $product->update($validated);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Memperbarui Produk',
                'details' => [
                    'product_id' => $product->id,
                    'old_values' => $oldValues,
                    'new_values' => $validated,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->load('category'),
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
                'message' => 'Failed to update product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // Audit log
            \App\Models\AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Menghapus Produk',
                'details' => ['product_id' => $product->id, 'name' => $product->name],
                'ip_address' => $request->ip(),
            ]);

            // Delete
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product: ' . $e->getMessage(),
            ], 500);
        }
    }
}
