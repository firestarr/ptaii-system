<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\POLine;
use App\Http\Requests\GoodsReceiptRequest;
use App\Services\ReceiptNumberGenerator;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GoodsReceiptController extends Controller
{
    protected $receiptNumberGenerator;
    protected $stockService;
    
    public function __construct(ReceiptNumberGenerator $receiptNumberGenerator, StockService $stockService)
    {
        $this->receiptNumberGenerator = $receiptNumberGenerator;
        $this->stockService = $stockService;
    }
    
    /**
     * Display a listing of goods receipts.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = GoodsReceipt::with(['vendor']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        if ($request->has('po_id')) {
            $query->whereHas('lines', function($q) use ($request) {
                $q->where('po_id', $request->po_id);
            });
        }
        
        if ($request->has('date_from')) {
            $query->whereDate('receipt_date', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('receipt_date', '<=', $request->date_to);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('receipt_number', 'like', "%{$search}%");
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'receipt_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $goodsReceipts = $query->paginate($perPage);
        
        // Load PO summary for each receipt
        $goodsReceipts->each(function($receipt) {
            $receipt->po_numbers = $receipt->getPoNumbersAttribute();
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $goodsReceipts
        ]);
    }

    /**
     * Get available outstanding items from PO(s).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableItems(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'po_ids' => 'required|array',
            'po_ids.*' => 'exists:purchase_orders,po_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Get PO lines with outstanding quantities
        $poIds = $request->po_ids;
        $purchaseOrders = PurchaseOrder::whereIn('po_id', $poIds)
            ->with(['vendor', 'lines.item'])
            ->get();
            
        // Check if POs are from same vendor
        $vendorIds = $purchaseOrders->pluck('vendor_id')->unique();
        if ($vendorIds->count() > 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'All selected POs must be from the same vendor'
            ], 400);
        }
        
        // Gather all PO lines
        $poLinesData = [];
        foreach ($purchaseOrders as $po) {
            foreach ($po->lines as $poLine) {
                // Get received quantity for this PO line
                $receivedQty = GoodsReceiptLine::where('po_line_id', $poLine->line_id)
                    ->whereHas('goodsReceipt', function($query) {
                        $query->where('status', 'confirmed');
                    })
                    ->sum('received_quantity');
                    
                $outstandingQty = $poLine->quantity - $receivedQty;
                
                // Only include if there's outstanding quantity
                if ($outstandingQty > 0) {
                    $poLinesData[] = [
                        'po_id' => $po->po_id,
                        'po_number' => $po->po_number,
                        'po_line_id' => $poLine->line_id,
                        'item_id' => $poLine->item_id,
                        'item_code' => $poLine->item->item_code,
                        'item_name' => $poLine->item->name,
                        'ordered_quantity' => $poLine->quantity,
                        'received_quantity' => $receivedQty,
                        'outstanding_quantity' => $outstandingQty
                    ];
                }
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'vendor' => $purchaseOrders->first()->vendor,
                'po_lines' => $poLinesData
            ]
        ]);
    }

    /**
     * Store a newly created goods receipt in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validasi dasar
        $validator = Validator::make($request->all(), [
            'receipt_date' => 'required|date',
            'vendor_id' => 'required|exists:vendors,vendor_id',
            'lines' => 'required|array|min:1',
            'lines.*.po_line_id' => 'required|exists:po_lines,line_id',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.received_quantity' => 'required|numeric|min:0.01',
            'lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'lines.*.batch_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Verifikasi bahwa semua PO ada dalam status valid
            $poLineIds = collect($request->lines)->pluck('po_line_id')->unique();
            $poLines = POLine::whereIn('line_id', $poLineIds)->with('purchaseOrder')->get();
            
            // Grup POLine berdasarkan PO ID untuk memeriksa PO
            $poIds = $poLines->pluck('po_id')->unique();
            $purchaseOrders = PurchaseOrder::whereIn('po_id', $poIds)->get()->keyBy('po_id');
            
            // Periksa status semua PO
            foreach ($purchaseOrders as $po) {
                if (!in_array($po->status, ['sent', 'partial'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "PO #{$po->po_number} is not in sent or partial status"
                    ], 400);
                }
            }
            
            // Periksa vendor sama untuk semua PO
            $vendorIds = $purchaseOrders->pluck('vendor_id')->unique();
            if ($vendorIds->count() > 1 || $vendorIds->first() != $request->vendor_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'All selected POs must be from the same vendor as specified'
                ], 400);
            }
            
            // Periksa outstanding quantity untuk setiap baris
            foreach ($request->lines as $line) {
                $poLine = $poLines->firstWhere('line_id', $line['po_line_id']);
                
                if (!$poLine) {
                    continue; // Seharusnya tidak terjadi karena sudah divalidasi, tapi untuk aman
                }
                
                // Hitung jumlah yang sudah diterima sebelumnya
                $alreadyReceived = GoodsReceiptLine::where('po_line_id', $poLine->line_id)
                    ->whereHas('goodsReceipt', function($query) {
                        $query->where('status', 'confirmed');
                    })
                    ->sum('received_quantity');
                
                $outstandingQuantity = $poLine->quantity - $alreadyReceived;
                
                if ($line['received_quantity'] > $outstandingQuantity) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Received quantity for PO Line ID {$poLine->line_id} (Item: {$poLine->item->name}) exceeds outstanding quantity. Max allowed: {$outstandingQuantity}"
                    ], 400);
                }
            }
            
            // Generate receipt number
            $receiptNumber = $this->receiptNumberGenerator->generate();
            
            // Create goods receipt
            $goodsReceipt = GoodsReceipt::create([
                'receipt_number' => $receiptNumber,
                'receipt_date' => $request->receipt_date,
                'vendor_id' => $request->vendor_id,
                'status' => 'pending'
            ]);
            
            // Create receipt lines
            foreach ($request->lines as $line) {
                $poLine = $poLines->firstWhere('line_id', $line['po_line_id']);
                
                $goodsReceipt->lines()->create([
                    'po_line_id' => $line['po_line_id'],
                    'po_id' => $poLine->po_id,
                    'item_id' => $line['item_id'],
                    'received_quantity' => $line['received_quantity'],
                    'warehouse_id' => $line['warehouse_id'],
                    'batch_number' => $line['batch_number'] ?? null
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Goods Receipt created successfully',
                'data' => $goodsReceipt->load(['vendor', 'lines.item', 'lines.purchaseOrderLine'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Goods Receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified goods receipt.
     *
     * @param GoodsReceipt $goodsReceipt
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(GoodsReceipt $goodsReceipt)
    {
        // Load relasi yang dibutuhkan
        $goodsReceipt->load(['lines.item', 'lines.purchaseOrderLine', 'vendor']);
        
        // Tambahkan informasi outstanding untuk setiap baris
        $detailedLines = $goodsReceipt->lines->map(function($line) use ($goodsReceipt) {
            $poLine = $line->purchaseOrderLine;
            $po = $line->purchaseOrder;
            
            // Hitung total yang sudah diterima untuk baris PO ini (termasuk receipt lain)
            $totalReceived = GoodsReceiptLine::where('po_line_id', $line->po_line_id)
                ->whereHas('goodsReceipt', function($query) use ($goodsReceipt) {
                    $query->where('status', 'confirmed');
                    if ($goodsReceipt->status === 'confirmed') {
                        $query->where('receipt_id', '<=', $goodsReceipt->receipt_id);
                    } else {
                        $query->where('receipt_id', '<>', $goodsReceipt->receipt_id);
                    }
                })
                ->sum('received_quantity');
            
            // Hitung total yang sudah diterima sebelum receipt ini
            $previouslyReceived = GoodsReceiptLine::where('po_line_id', $line->po_line_id)
                ->whereHas('goodsReceipt', function($query) use ($goodsReceipt) {
                    $query->where('status', 'confirmed')
                        ->where('receipt_id', '<', $goodsReceipt->receipt_id);
                })
                ->sum('received_quantity');
            
            // Hitung outstanding setelah receipt ini
            $outstanding = $poLine->quantity - $totalReceived;
            if ($goodsReceipt->status !== 'confirmed') {
                $outstanding -= $line->received_quantity;
            }
            
            return [
                'line_id' => $line->line_id,
                'po_id' => $po->po_id,
                'po_number' => $po->po_number,
                'po_line_id' => $line->po_line_id,
                'item_id' => $line->item_id,
                'item_code' => $line->item->item_code,
                'item_name' => $line->item->name,
                'ordered_quantity' => $poLine->quantity,
                'previously_received' => $previouslyReceived,
                'received_quantity' => $line->received_quantity,
                'outstanding_quantity' => $outstanding,
                'warehouse_id' => $line->warehouse_id,
                'warehouse_name' => $line->warehouse->name,
                'batch_number' => $line->batch_number
            ];
        });
        
        // Tambahkan informasi PO
        $poSummary = $goodsReceipt->lines->pluck('po_id')
            ->unique()
            ->map(function($poId) use ($goodsReceipt) {
                $po = PurchaseOrder::find($poId);
                
                // Hitung progres penerimaan untuk seluruh PO
                $poLines = $po->lines;
                
                $totalOrdered = $poLines->sum('quantity');
                
                $totalReceived = GoodsReceiptLine::whereIn('po_line_id', $poLines->pluck('line_id'))
                    ->whereHas('goodsReceipt', function($query) {
                        $query->where('status', 'confirmed');
                    })
                    ->sum('received_quantity');
                    
                $poProgress = $totalOrdered > 0 ? round(($totalReceived / $totalOrdered) * 100, 2) : 0;
                
                return [
                    'po_id' => $po->po_id,
                    'po_number' => $po->po_number,
                    'po_date' => $po->po_date,
                    'total_ordered' => $totalOrdered,
                    'total_received' => $totalReceived,
                    'total_outstanding' => $totalOrdered - $totalReceived,
                    'progress_percentage' => $poProgress,
                    'status' => $po->status
                ];
            });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'receipt' => $goodsReceipt,
                'lines' => $detailedLines,
                'po_summary' => $poSummary
            ]
        ]);
    }

    /**
     * Update the specified goods receipt in storage.
     *
     * @param Request $request
     * @param GoodsReceipt $goodsReceipt
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, GoodsReceipt $goodsReceipt)
    {
        // Check if goods receipt can be updated (only pending status)
        if ($goodsReceipt->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending Goods Receipts can be updated'
            ], 400);
        }
        
        // Validasi dasar
        $validator = Validator::make($request->all(), [
            'receipt_date' => 'required|date',
            'vendor_id' => 'required|exists:vendors,vendor_id',
            'lines' => 'required|array|min:1',
            'lines.*.po_line_id' => 'required|exists:po_lines,line_id',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.received_quantity' => 'required|numeric|min:0.01',
            'lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'lines.*.batch_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Verifikasi bahwa semua PO ada dalam status valid
            $poLineIds = collect($request->lines)->pluck('po_line_id')->unique();
            $poLines = POLine::whereIn('line_id', $poLineIds)->with('purchaseOrder')->get();
            
            // Grup POLine berdasarkan PO ID untuk memeriksa PO
            $poIds = $poLines->pluck('po_id')->unique();
            $purchaseOrders = PurchaseOrder::whereIn('po_id', $poIds)->get()->keyBy('po_id');
            
            // Periksa status semua PO
            foreach ($purchaseOrders as $po) {
                if (!in_array($po->status, ['sent', 'partial'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "PO #{$po->po_number} is not in sent or partial status"
                    ], 400);
                }
            }
            
            // Periksa vendor sama untuk semua PO
            $vendorIds = $purchaseOrders->pluck('vendor_id')->unique();
            if ($vendorIds->count() > 1 || $vendorIds->first() != $request->vendor_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'All selected POs must be from the same vendor as specified'
                ], 400);
            }
            
            // Periksa outstanding quantity untuk setiap baris
            foreach ($request->lines as $line) {
                $poLine = $poLines->firstWhere('line_id', $line['po_line_id']);
                
                if (!$poLine) {
                    continue; // Seharusnya tidak terjadi karena sudah divalidasi, tapi untuk aman
                }
                
                // Hitung jumlah yang sudah diterima sebelumnya (tidak termasuk receipt ini)
                $alreadyReceived = GoodsReceiptLine::where('po_line_id', $poLine->line_id)
                    ->whereHas('goodsReceipt', function($query) use ($goodsReceipt) {
                        $query->where('status', 'confirmed')
                              ->where('receipt_id', '<>', $goodsReceipt->receipt_id);
                    })
                    ->sum('received_quantity');
                
                $outstandingQuantity = $poLine->quantity - $alreadyReceived;
                
                if ($line['received_quantity'] > $outstandingQuantity) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Received quantity for PO Line ID {$poLine->line_id} (Item: {$poLine->item->name}) exceeds outstanding quantity. Max allowed: {$outstandingQuantity}"
                    ], 400);
                }
            }
            
            // Update goods receipt
            $goodsReceipt->update([
                'receipt_date' => $request->receipt_date,
                'vendor_id' => $request->vendor_id
            ]);
            
            // Delete existing lines
            $goodsReceipt->lines()->delete();
            
            // Create new lines
            foreach ($request->lines as $line) {
                $poLine = $poLines->firstWhere('line_id', $line['po_line_id']);
                
                $goodsReceipt->lines()->create([
                    'po_line_id' => $line['po_line_id'],
                    'po_id' => $poLine->po_id,
                    'item_id' => $line['item_id'],
                    'received_quantity' => $line['received_quantity'],
                    'warehouse_id' => $line['warehouse_id'],
                    'batch_number' => $line['batch_number'] ?? null
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Goods Receipt updated successfully',
                'data' => $goodsReceipt->load(['vendor', 'lines.item', 'lines.purchaseOrderLine'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Goods Receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified goods receipt from storage.
     *
     * @param GoodsReceipt $goodsReceipt
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(GoodsReceipt $goodsReceipt)
    {
        // Check if goods receipt can be deleted (only pending status)
        if ($goodsReceipt->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending Goods Receipts can be deleted'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete receipt lines
            $goodsReceipt->lines()->delete();
            
            // Delete receipt
            $goodsReceipt->delete();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Goods Receipt deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete Goods Receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Confirm the specified goods receipt.
     *
     * @param GoodsReceipt $goodsReceipt
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(GoodsReceipt $goodsReceipt)
    {
        // Check if goods receipt can be confirmed (only pending status)
        if ($goodsReceipt->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending Goods Receipts can be confirmed'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Update goods receipt status
            $goodsReceipt->update(['status' => 'confirmed']);
            
            // Load lines for processing
            $goodsReceipt->load('lines');
            
            // Update stock levels
            foreach ($goodsReceipt->lines as $line) {
                $this->stockService->increaseStock(
                    $line->item_id,
                    $line->warehouse_id,
                    $line->received_quantity,
                    'goods_receipt',
                    $goodsReceipt->receipt_number,
                    $line->batch_number
                );
            }
            
            // Get unique PO IDs from receipt lines
            $poIds = $goodsReceipt->lines->pluck('po_id')->unique();
            
            // Update status for each PO
            foreach ($poIds as $poId) {
                $purchaseOrder = PurchaseOrder::find($poId);
                if ($purchaseOrder) {
                    $this->updatePurchaseOrderStatus($purchaseOrder);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Goods Receipt confirmed successfully',
                'data' => $goodsReceipt
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm Goods Receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a purchase order status based on goods receipts.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return void
     */
    protected function updatePurchaseOrderStatus(PurchaseOrder $purchaseOrder)
    {
        // Get all confirmed goods receipt lines for this PO
        $confirmedReceiptLines = GoodsReceiptLine::where('po_id', $purchaseOrder->po_id)
                                      ->whereHas('goodsReceipt', function($query) {
                                          $query->where('status', 'confirmed');
                                      })
                                      ->get();
        
        // Get all PO lines
        $poLines = $purchaseOrder->lines;
        
        // Check if all items have been fully received
        $allReceived = true;
        $anyReceived = false;
        
        foreach ($poLines as $poLine) {
            $receivedQty = 0;
            
            // Sum all received quantities for this PO line
            foreach ($confirmedReceiptLines as $receiptLine) {
                if ($receiptLine->po_line_id === $poLine->line_id) {
                    $receivedQty += $receiptLine->received_quantity;
                }
            }
            
            if ($receivedQty > 0) {
                $anyReceived = true;
            }
            
            if ($receivedQty < $poLine->quantity) {
                $allReceived = false;
            }
        }
        
        // Update PO status based on received quantities
        if ($allReceived) {
            $purchaseOrder->update(['status' => 'received']);
        } elseif ($anyReceived) {
            $purchaseOrder->update(['status' => 'partial']);
        }
    }
}