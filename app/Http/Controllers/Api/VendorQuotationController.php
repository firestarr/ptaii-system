<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VendorQuotation;
use App\Models\RequestForQuotation;
use App\Http\Requests\VendorQuotationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorQuotationController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorQuotation::with(['vendor', 'requestForQuotation']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        if ($request->has('rfq_id')) {
            $query->where('rfq_id', $request->rfq_id);
        }
        
        if ($request->has('date_from')) {
            $query->whereDate('quotation_date', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('quotation_date', '<=', $request->date_to);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'quotation_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $vendorQuotations = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $vendorQuotations
        ]);
    }

    public function store(VendorQuotationRequest $request)
    {
        // Check if RFQ exists and is in sent status
        $rfq = RequestForQuotation::findOrFail($request->rfq_id);
        if ($rfq->status !== 'sent') {
            return response()->json([
                'status' => 'error',
                'message' => 'Vendor quotations can only be created for RFQs in sent status'
            ], 400);
        }
        
        // Check if vendor quotation already exists for this vendor and RFQ
        $exists = VendorQuotation::where('rfq_id', $request->rfq_id)
                                 ->where('vendor_id', $request->vendor_id)
                                 ->exists();
        
        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'A quotation from this vendor for this RFQ already exists'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Create vendor quotation
            $vendorQuotation = VendorQuotation::create([
                'rfq_id' => $request->rfq_id,
                'vendor_id' => $request->vendor_id,
                'quotation_date' => $request->quotation_date,
                'validity_date' => $request->validity_date,
                'status' => 'received'
            ]);
            
            // Create quotation lines
            foreach ($request->lines as $line) {
                $vendorQuotation->lines()->create([
                    'item_id' => $line['item_id'],
                    'unit_price' => $line['unit_price'],
                    'uom_id' => $line['uom_id'],
                    'quantity' => $line['quantity'],
                    'delivery_date' => $line['delivery_date'] ?? null
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor Quotation created successfully',
                'data' => $vendorQuotation->load(['vendor', 'requestForQuotation', 'lines.item', 'lines.unitOfMeasure'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Vendor Quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createFromRFQ(Request $request)
    {
        $request->validate([
            'rfq_id' => 'required|integer|exists:request_for_quotations,rfq_id',
            'vendor_ids' => 'required|array|min:1',
            'vendor_ids.*' => 'integer|exists:vendors,vendor_id'
        ]);

        $rfq = RequestForQuotation::findOrFail($request->rfq_id);

        if ($rfq->status !== 'sent') {
            return response()->json([
                'status' => 'error',
                'message' => 'Vendor quotations can only be created for RFQs in sent status'
            ], 400);
        }

        $createdQuotations = [];

        DB::beginTransaction();

        try {
            foreach ($request->vendor_ids as $vendorId) {
                // Check if vendor quotation already exists for this vendor and RFQ
                $exists = VendorQuotation::where('rfq_id', $request->rfq_id)
                                         ->where('vendor_id', $vendorId)
                                         ->exists();

                if ($exists) {
                    continue; // Skip existing quotations
                }

                $vendorQuotation = VendorQuotation::create([
                    'rfq_id' => $request->rfq_id,
                    'vendor_id' => $vendorId,
                    'quotation_date' => now(),
                    'validity_date' => null,
                    'status' => 'received'
                ]);

                // Copy lines from RFQ to vendor quotation lines
                foreach ($rfq->lines as $line) {
                    $vendorQuotation->lines()->create([
                        'item_id' => $line->item_id,
                        'unit_price' => 0,
                        'uom_id' => $line->uom_id,
                        'quantity' => $line->quantity,
                        'delivery_date' => $line->required_date
                    ]);
                }

                $createdQuotations[] = $vendorQuotation;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor Quotations created successfully',
                'data' => $createdQuotations
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Vendor Quotations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(VendorQuotation $vendorQuotation)
    {
        $vendorQuotation->load(['vendor', 'requestForQuotation', 'lines.item', 'lines.unitOfMeasure']);
        
        return response()->json([
            'status' => 'success',
            'data' => $vendorQuotation
        ]);
    }

    public function update(VendorQuotationRequest $request, VendorQuotation $vendorQuotation)
    {
        // Check if quotation can be updated (only received status)
        if ($vendorQuotation->status !== 'received') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only received quotations can be updated'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Update quotation details
            $vendorQuotation->update([
                'quotation_date' => $request->quotation_date,
                'validity_date' => $request->validity_date
            ]);
            
            // Update quotation lines
            if ($request->has('lines')) {
                // Delete existing lines
                $vendorQuotation->lines()->delete();
                
                // Create new lines
                foreach ($request->lines as $line) {
                    $vendorQuotation->lines()->create([
                        'item_id' => $line['item_id'],
                        'unit_price' => $line['unit_price'],
                        'uom_id' => $line['uom_id'],
                        'quantity' => $line['quantity'],
                        'delivery_date' => $line['delivery_date'] ?? null
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor Quotation updated successfully',
                'data' => $vendorQuotation->load(['vendor', 'requestForQuotation', 'lines.item', 'lines.unitOfMeasure'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Vendor Quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(VendorQuotation $vendorQuotation)
    {
        // Check if quotation can be deleted (only received status)
        if ($vendorQuotation->status !== 'received') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only received quotations can be deleted'
            ], 400);
        }
        
        $vendorQuotation->lines()->delete();
        $vendorQuotation->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Vendor Quotation deleted successfully'
        ]);
    }
    
    public function updateStatus(Request $request, VendorQuotation $vendorQuotation)
    {
        $request->validate([
            'status' => 'required|in:received,accepted,rejected'
        ]);
        
        // Additional validations based on status transition
        $currentStatus = $vendorQuotation->status;
        $newStatus = $request->status;
        
        $validTransitions = [
            'received' => ['accepted', 'rejected'],
            'accepted' => ['rejected'],
            'rejected' => ['accepted']
        ];
        
        if (!in_array($newStatus, $validTransitions[$currentStatus])) {
            return response()->json([
                'status' => 'error',
                'message' => "Status cannot be changed from {$currentStatus} to {$newStatus}"
            ], 400);
        }
        
        $vendorQuotation->update(['status' => $newStatus]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Vendor Quotation status updated successfully',
            'data' => $vendorQuotation
        ]);
    }
}