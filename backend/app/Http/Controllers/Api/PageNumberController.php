<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PagerNumber;
use Illuminate\Http\Request;

class PageNumberController extends Controller
{
    /**
     * Get all pager numbers
     * GET /api/v1/pagers
     */
    public function index(Request $request)
    {
        try {
            $query = PagerNumber::with('currentOrder');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by buzzer status
            if ($request->has('buzzer_status')) {
                $query->where('buzzer_status', $request->buzzer_status);
            }

            // Pagination
            $perPage = $request->get('per_page', 50);
            $pagers = $query->orderBy('number', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $pagers->items(),
                'meta' => [
                    'total' => $pagers->total(),
                    'per_page' => $pagers->perPage(),
                    'current_page' => $pagers->currentPage(),
                    'last_page' => $pagers->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pagers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single pager number
     * GET /api/v1/pagers/{id}
     */
    public function show($id)
    {
        try {
            $pager = PagerNumber::with('currentOrder')->find($id);

            if (!$pager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pager not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pager,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pager: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new pager (Manager only)
     * POST /api/v1/pagers
     */
    public function store(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'number' => 'required|integer|min:1|unique:pager_numbers,number',
            ]);

            // Create pager
            $pager = PagerNumber::create([
                'number' => $validated['number'],
                'status' => 'AVAILABLE',
                'buzzer_status' => 'PENDING',
            ]);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'CREATE_PAGER',
                'details' => ['pager_id' => $pager->id, 'number' => $pager->number],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pager created successfully',
                'data' => $pager,
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
                'message' => 'Failed to create pager: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function update(Request $request, $id)
    {
        try {
            $pager = PagerNumber::find($id);

            if (!$pager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pager not found',
                ], 404);
            }

            // Validate input
            $validated = $request->validate([
                'status' => 'in:AVAILABLE,IN_USE',
                'buzzer_status' => 'in:PENDING,RINGING,ACKNOWLEDGED',
                'current_order_id' => 'nullable|exists:orders,id',
            ]);

            // Store old values for audit
            $oldValues = $pager->only(array_keys($validated));

            // Update pager
            $pager->update($validated);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'UPDATE_PAGER',
                'details' => [
                    'pager_id' => $pager->id,
                    'pager_number' => $pager->number,
                    'old_values' => $oldValues,
                    'new_values' => $validated,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pager updated successfully',
                'data' => $pager->load('currentOrder'),
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
                'message' => 'Failed to update pager: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ring pager (Barista)
     * POST /api/v1/pagers/{id}/ring
     */
    public function ring(Request $request, $id)
    {
        try {
            $pager = PagerNumber::find($id);

            if (!$pager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pager not found',
                ], 404);
            }

            // Update pager status
            $pager->update(['buzzer_status' => 'RINGING']);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'RING_PAGER',
                'details' => [
                    'pager_id' => $pager->id,
                    'pager_number' => $pager->number,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pager ringing',
                'data' => $pager,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to ring pager: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Acknowledge pager ring (Customer)
     * POST /api/v1/pagers/{id}/acknowledge
     */
    public function acknowledge(Request $request, $id)
    {
        try {
            $pager = PagerNumber::find($id);

            if (!$pager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pager not found',
                ], 404);
            }

            // Update pager status
            $pager->update(['buzzer_status' => 'ACKNOWLEDGED']);

            return response()->json([
                'success' => true,
                'message' => 'Pager acknowledged',
                'data' => $pager,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge pager: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $pager = PagerNumber::find($id);

            if (!$pager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pager not found',
                ], 404);
            }

            // Constraint: Cannot delete pager yang sedang dipakai
            if ($pager->status === 'IN_USE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete pager that is currently in use',
                ], 422);
            }


            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'DELETE_PAGER',
                'details' => ['pager_id' => $pager->id, 'number' => $pager->number],
                'ip_address' => $request->ip(),
            ]);

            // Delete
            $pager->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pager deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete pager: ' . $e->getMessage(),
            ], 500);
        }
    }
}

