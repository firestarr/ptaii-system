<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequisition;
use App\Models\VendorContract;
use App\Models\VendorQuotation;
use App\Models\VendorEvaluation;
use App\Models\RequestForQuotation;
use App\Models\RFQLine;
use App\Http\Requests\PurchaseRequisitionRequest;
use App\Services\PRNumberGenerator;
use App\Services\RFQNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseRequisitionController extends Controller
{
    protected $prNumberGenerator;
    protected $rfqNumberGenerator;
    
    public function __construct(PRNumberGenerator $prNumberGenerator, RFQNumberGenerator $rfqNumberGenerator)
    {
        $this->prNumberGenerator = $prNumberGenerator;
        $this->rfqNumberGenerator = $rfqNumberGenerator;
    }
    
    public function index(Request $request)
    {
        $query = PurchaseRequisition::with(['requester', 'lines.item', 'lines.unitOfMeasure']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('requester_id')) {
            $query->where('requester_id', $request->requester_id);
        }
        
        if ($request->has('date_from')) {
            $query->whereDate('pr_date', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('pr_date', '<=', $request->date_to);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('pr_number', 'like', "%{$search}%");
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'pr_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $purchaseRequisitions = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $purchaseRequisitions
        ]);
    }

    public function store(PurchaseRequisitionRequest $request)
    {
        try {
            DB::beginTransaction();
            
            // Generate PR number
            $prNumber = $this->prNumberGenerator->generate();
            
            // Create purchase requisition
            $pr = PurchaseRequisition::create([
                'pr_number' => $prNumber,
                'pr_date' => $request->pr_date,
                'requester_id' => $request->requester_id,
                'status' => 'draft',
                'notes' => $request->notes
            ]);
            
            // Create PR lines
            foreach ($request->lines as $line) {
                $pr->lines()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'uom_id' => $line['uom_id'],
                    'required_date' => $line['required_date'] ?? null,
                    'notes' => $line['notes'] ?? null
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Requisition created successfully',
                'data' => $pr->load(['requester', 'lines.item', 'lines.unitOfMeasure'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Purchase Requisition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(PurchaseRequisition $purchaseRequisition)
    {
        $purchaseRequisition->load(['requester', 'lines.item', 'lines.unitOfMeasure']);
        
        return response()->json([
            'status' => 'success',
            'data' => $purchaseRequisition
        ]);
    }

    public function update(PurchaseRequisitionRequest $request, PurchaseRequisition $purchaseRequisition)
    {
        // Check if PR can be updated (only draft and pending status)
        if (!in_array($purchaseRequisition->status, ['draft', 'pending'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Purchase Requisition cannot be updated in its current status'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Update PR details
            $purchaseRequisition->update([
                'pr_date' => $request->pr_date,
                'requester_id' => $request->requester_id,
                'notes' => $request->notes
            ]);
            
            // Update PR lines
            if ($request->has('lines')) {
                // Delete existing lines
                $purchaseRequisition->lines()->delete();
                
                // Create new lines
                foreach ($request->lines as $line) {
                    $purchaseRequisition->lines()->create([
                        'item_id' => $line['item_id'],
                        'quantity' => $line['quantity'],
                        'uom_id' => $line['uom_id'],
                        'required_date' => $line['required_date'] ?? null,
                        'notes' => $line['notes'] ?? null
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Requisition updated successfully',
                'data' => $purchaseRequisition->load(['requester', 'lines.item', 'lines.unitOfMeasure'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Purchase Requisition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(PurchaseRequisition $purchaseRequisition)
    {
        // Check if PR can be deleted (only draft status)
        if ($purchaseRequisition->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft Purchase Requisitions can be deleted'
            ], 400);
        }
        
        $purchaseRequisition->lines()->delete();
        $purchaseRequisition->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Purchase Requisition deleted successfully'
        ]);
    }
    
    public function updateStatus(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        $request->validate([
            'status' => 'required|in:draft,pending,approved,rejected,canceled,rfq_sent,po_created'
        ]);
        
        // Additional validations based on status transition
        $currentStatus = $purchaseRequisition->status;
        $newStatus = $request->status;
        
        $validTransitions = [
            'draft' => ['pending', 'canceled'],
            'pending' => ['approved', 'rejected', 'canceled'],
            'approved' => ['rfq_sent', 'po_created', 'canceled'],
            'rejected' => ['draft', 'canceled'],
            'rfq_sent' => ['po_created', 'canceled'],
            'po_created' => ['canceled'],
            'canceled' => []
        ];
        
        if (!in_array($newStatus, $validTransitions[$currentStatus])) {
            return response()->json([
                'status' => 'error',
                'message' => "Status cannot be changed from {$currentStatus} to {$newStatus}"
            ], 400);
        }
        
        $purchaseRequisition->update(['status' => $newStatus]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Purchase Requisition status updated successfully',
            'data' => $purchaseRequisition
        ]);
    }

    /**
     * Get vendor recommendations for PR items
     */
    public function getVendorRecommendations(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        // Check if PR is approved
        if ($purchaseRequisition->status !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only approved PR can get vendor recommendations'
            ], 400);
        }

        $recommendations = [];
        
        foreach ($purchaseRequisition->lines as $line) {
            $item = $line->item;
            $vendors = $this->getAvailableVendorsForItem($item, $line->quantity);
            
            $recommendations[] = [
                'pr_line_id' => $line->line_id,
                'item_id' => $item->item_id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'required_quantity' => $line->quantity,
                'required_date' => $line->required_date,
                'uom' => $line->unitOfMeasure->symbol,
                'available_vendors' => $vendors,
                'recommended_vendor' => $vendors[0] ?? null, // Best vendor
                'procurement_options' => $this->getProcurementOptions($item, $vendors)
            ];
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'pr' => $purchaseRequisition,
                'recommendations' => $recommendations,
                'summary' => $this->getRecommendationSummary($recommendations)
            ]
        ]);
    }

    /**
     * Create multi-vendor RFQ from PR
     */
    public function createMultiVendorRFQ(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        $validator = Validator::make($request->all(), [
            'vendor_ids' => 'required|array|min:2',
            'vendor_ids.*' => 'exists:vendors,vendor_id',
            'validity_days' => 'nullable|integer|min:1|max:90'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($purchaseRequisition->status !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only approved PR can create RFQ'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Generate RFQ number
            $rfqNumber = $this->rfqNumberGenerator->generate();
            $validityDays = $request->validity_days ?? 30;
            
            // Create RFQ
            $rfq = RequestForQuotation::create([
                'rfq_number' => $rfqNumber,
                'rfq_date' => now(),
                'validity_date' => now()->addDays($validityDays),
                'status' => 'draft',
                'reference_document' => "PR-{$purchaseRequisition->pr_number}",
                'notes' => "Multi-vendor RFQ from PR {$purchaseRequisition->pr_number}"
            ]);
            
            // Create RFQ lines from PR lines
            foreach ($purchaseRequisition->lines as $prLine) {
                $rfq->lines()->create([
                    'item_id' => $prLine->item_id,
                    'quantity' => $prLine->quantity,
                    'uom_id' => $prLine->uom_id,
                    'required_date' => $prLine->required_date
                ]);
            }
            
            // Send to multiple vendors (this could trigger email notifications)
            $sentCount = 0;
            foreach ($request->vendor_ids as $vendorId) {
                // Here you could implement actual RFQ sending mechanism
                // For now, we'll just record that it was sent
                $sentCount++;
            }
            
            $rfq->update(['status' => 'sent']);
            $purchaseRequisition->update(['status' => 'rfq_sent']);
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => "RFQ sent to {$sentCount} vendors successfully",
                'data' => [
                    'rfq' => $rfq->load(['lines.item', 'lines.unitOfMeasure']),
                    'vendor_count' => $sentCount
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create multi-vendor RFQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get procurement path analysis
     */
    public function getProcurementPath(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        $analysis = [];
        
        foreach ($purchaseRequisition->lines as $line) {
            $item = $line->item;
            $vendors = $this->getAvailableVendorsForItem($item, $line->quantity);
            
            $canCreatePODirectly = false;
            $reasons = [];
            $recommendedPath = 'rfq_required';
            
            if (!empty($vendors)) {
                $bestVendor = $vendors[0];
                
                if ($bestVendor['has_active_contract']) {
                    $canCreatePODirectly = true;
                    $reasons[] = 'Active vendor contract';
                    $recommendedPath = 'direct_po';
                } elseif ($bestVendor['has_valid_quotation']) {
                    $canCreatePODirectly = true;
                    $reasons[] = 'Valid quotation exists';
                    $recommendedPath = 'direct_po';
                } elseif ($bestVendor['unit_price'] > 0) {
                    $canCreatePODirectly = true;
                    $reasons[] = 'Item pricing available';
                    $recommendedPath = 'direct_po';
                }
            }
            
            if (!$canCreatePODirectly) {
                $reasons[] = 'No valid pricing found - RFQ required';
            }
            
            $analysis[] = [
                'item_id' => $item->item_id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'can_create_po_directly' => $canCreatePODirectly,
                'recommended_path' => $recommendedPath,
                'reasons' => $reasons,
                'vendor_options_count' => count($vendors),
                'best_vendor' => $vendors[0] ?? null
            ];
        }
        
        $overallRecommendation = collect($analysis)->every('can_create_po_directly') ? 'direct_po' : 'mixed';
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'pr' => $purchaseRequisition,
                'overall_recommendation' => $overallRecommendation,
                'items_analysis' => $analysis,
                'summary' => [
                    'total_items' => count($analysis),
                    'direct_po_possible' => collect($analysis)->where('can_create_po_directly', true)->count(),
                    'rfq_required' => collect($analysis)->where('can_create_po_directly', false)->count()
                ]
            ]
        ]);
    }

    /**
     * Get available vendors for an item
     */
    private function getAvailableVendorsForItem($item, $quantity)
    {
        $vendors = [];
        
        // Get all vendors yang punya pricing untuk item ini
        $vendorPrices = $item->prices()
            ->where('price_type', 'purchase')
            ->active()
            ->where('min_quantity', '<=', $quantity)
            ->with('vendor')
            ->get();
        
        foreach ($vendorPrices as $price) {
            $vendor = $price->vendor;
            
            if (!$vendor || $vendor->status !== 'active') {
                continue;
            }
            
            // Check lead time dari vendor
            $leadTime = $this->estimateLeadTime($vendor, $item);
            
            // Check vendor contract
            $hasActiveContract = VendorContract::where('vendor_id', $vendor->vendor_id)
                ->where('status', 'active')
                ->where('start_date', '<=', now())
                ->where(function($q) {
                    $q->where('end_date', '>=', now())->orWhereNull('end_date');
                })->exists();
            
            // Check valid quotation
            $validQuotation = VendorQuotation::where('vendor_id', $vendor->vendor_id)
                ->where('status', 'received')
                ->where('validity_date', '>=', now())
                ->whereHas('lines', function($q) use ($item) {
                    $q->where('item_id', $item->item_id);
                })->first();
            
            $vendors[] = [
                'vendor_id' => $vendor->vendor_id,
                'vendor_name' => $vendor->name,
                'vendor_code' => $vendor->vendor_code,
                'unit_price' => $price->price,
                'currency' => $price->currency_code ?? $vendor->preferred_currency ?? 'USD',
                'min_quantity' => $price->min_quantity,
                'estimated_lead_time_days' => $leadTime,
                'has_active_contract' => $hasActiveContract,
                'has_valid_quotation' => (bool) $validQuotation,
                'quotation_id' => $validQuotation?->quotation_id,
                'total_cost' => $price->price * $quantity,
                'vendor_rating' => $this->getVendorRating($vendor),
                'last_delivery_performance' => $this->getDeliveryPerformance($vendor),
                'payment_terms' => $vendor->payment_term . ' days',
                'preferred_currency' => $vendor->preferred_currency ?? 'USD'
            ];
        }
        
        // Sort by best combination of price, rating, and delivery performance
        usort($vendors, function($a, $b) {
            // Weighted scoring: 50% price, 30% rating, 20% delivery
            $scoreA = (1 / max($a['unit_price'], 0.01)) * 0.5 + 
                     ($a['vendor_rating'] / 10) * 0.3 + 
                     ($a['last_delivery_performance'] / 100) * 0.2;
                     
            $scoreB = (1 / max($b['unit_price'], 0.01)) * 0.5 + 
                     ($b['vendor_rating'] / 10) * 0.3 + 
                     ($b['last_delivery_performance'] / 100) * 0.2;
                     
            return $scoreB <=> $scoreA; // Descending order
        });
        
        return $vendors;
    }

    private function estimateLeadTime($vendor, $item)
    {
        // Basic lead time estimation - you can make this more sophisticated
        // by checking historical data, vendor performance, etc.
        return $vendor->payment_term ?? 30; // Default 30 days
    }

    private function getVendorRating($vendor)
    {
        // Get average vendor rating from evaluations
        $avgRating = VendorEvaluation::where('vendor_id', $vendor->vendor_id)
            ->where('evaluation_date', '>=', now()->subYear())
            ->avg('total_score');
            
        return round($avgRating ?? 5.0, 1); // Default 5.0 if no evaluations
    }

    private function getDeliveryPerformance($vendor)
    {
        // Calculate delivery performance percentage
        // This is a simplified version - you can enhance based on actual delivery data
        $evaluations = VendorEvaluation::where('vendor_id', $vendor->vendor_id)
            ->where('evaluation_date', '>=', now()->subYear())
            ->get();
            
        if ($evaluations->isEmpty()) {
            return 80.0; // Default performance
        }
        
        return round($evaluations->avg('delivery_score') * 10, 1); // Convert to percentage
    }

    private function getProcurementOptions($item, $vendors)
    {
        $options = [];
        
        if (empty($vendors)) {
            $options[] = [
                'type' => 'rfq_required',
                'description' => 'No vendors found with pricing - RFQ required',
                'priority' => 1
            ];
        } else {
            $bestVendor = $vendors[0];
            
            if ($bestVendor['has_active_contract']) {
                $options[] = [
                    'type' => 'direct_po',
                    'description' => 'Create PO directly using contract pricing',
                    'priority' => 1
                ];
            }
            
            if ($bestVendor['has_valid_quotation']) {
                $options[] = [
                    'type' => 'direct_po_quotation',
                    'description' => 'Create PO from existing valid quotation',
                    'priority' => 2
                ];
            }
            
            if (count($vendors) > 1) {
                $options[] = [
                    'type' => 'split_po',
                    'description' => 'Split between multiple vendors for best pricing',
                    'priority' => 3
                ];
            }
            
            $options[] = [
                'type' => 'multi_vendor_rfq',
                'description' => 'Send RFQ to multiple vendors for competitive pricing',
                'priority' => 4
            ];
        }
        
        return $options;
    }

    private function getRecommendationSummary($recommendations)
    {
        $totalItems = count($recommendations);
        $itemsWithVendors = collect($recommendations)->where('available_vendors', '!=', [])->count();
        $itemsWithMultipleVendors = collect($recommendations)
            ->where(function($item) {
                return count($item['available_vendors']) > 1;
            })->count();
        
        $avgVendorsPerItem = $totalItems > 0 ? 
            collect($recommendations)->sum(function($item) {
                return count($item['available_vendors']);
            }) / $totalItems : 0;
        
        return [
            'total_items' => $totalItems,
            'items_with_vendors' => $itemsWithVendors,
            'items_with_multiple_vendors' => $itemsWithMultipleVendors,
            'average_vendors_per_item' => round($avgVendorsPerItem, 1),
            'recommended_approach' => $itemsWithMultipleVendors > 0 ? 'multi_vendor_strategy' : 'single_vendor_strategy'
        ];
    }
}