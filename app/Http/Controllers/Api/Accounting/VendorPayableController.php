<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\VendorPayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VendorPayableController extends Controller
{
    /**
     * Display a listing of vendor payables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = VendorPayable::with(['vendor', 'vendorInvoice']);
        
        // Filter by vendor
        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by due date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('due_date', [$request->from_date, $request->to_date]);
        }
        
        $payables = $query->orderBy('due_date')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($payables, 200);
    }

    /**
     * Store a newly created vendor payable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:Vendor,vendor_id',
            'invoice_id' => 'required|exists:VendorInvoice,invoice_id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a payable already exists for this invoice
        $exists = VendorPayable::where('invoice_id', $request->invoice_id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'A payable already exists for this invoice'
            ], 422);
        }

        $payable = VendorPayable::create([
            'vendor_id' => $request->vendor_id,
            'invoice_id' => $request->invoice_id,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
            'paid_amount' => 0,
            'balance' => $request->amount,
            'status' => $request->status
        ]);

        return response()->json([
            'data' => $payable, 
            'message' => 'Vendor payable created successfully'
        ], 201);
    }

    /**
     * Display the specified vendor payable.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $payable = VendorPayable::with([
            'vendor', 
            'vendorInvoice',
            'payablePayments'
        ])->findOrFail($id);
        
        return response()->json(['data' => $payable], 200);
    }

    /**
     * Update the specified vendor payable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $payable = VendorPayable::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'due_date' => 'date',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Only allow updating due date and status
        $payable->update($request->only(['due_date', 'status']));

        return response()->json([
            'data' => $payable, 
            'message' => 'Vendor payable updated successfully'
        ], 200);
    }

    /**
     * Remove the specified vendor payable from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $payable = VendorPayable::findOrFail($id);
        
        // Check if there are payments
        if ($payable->payablePayments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete payable with associated payments'
            ], 422);
        }
        
        $payable->delete();

        return response()->json(['message' => 'Vendor payable deleted successfully'], 200);
    }
    
    /**
     * Generate aging report for vendor payables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function aging(Request $request)
    {
        $aging = DB::table('vendor_payables')
            ->join('vendors', 'vendor_payables.vendor_id', '=', 'vendors.vendor_id')
            ->select(
                'vendor_payables.vendor_id',
                'vendors.name as vendor_name',
                DB::raw('SUM(CASE WHEN (CURRENT_DATE - due_date) <= 0 THEN balance ELSE 0 END) as current_amount'),
                DB::raw('SUM(CASE WHEN (CURRENT_DATE - due_date) BETWEEN 1 AND 30 THEN balance ELSE 0 END) as days_1_30'),
                DB::raw('SUM(CASE WHEN (CURRENT_DATE - due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END) as days_31_60'),
                DB::raw('SUM(CASE WHEN (CURRENT_DATE - due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END) as days_61_90'),
                DB::raw('SUM(CASE WHEN (CURRENT_DATE - due_date) > 90 THEN balance ELSE 0 END) as days_over_90'),
                DB::raw('SUM(balance) as total_balance')
            )
            ->where('vendor_payables.status', '!=', 'Paid')
            ->groupBy('vendor_payables.vendor_id', 'vendors.name')
            ->orderBy('vendors.name')
            ->get();
        
        $totals = [
            'current_amount' => $aging->sum('current_amount'),
            'days_1_30' => $aging->sum('days_1_30'),
            'days_31_60' => $aging->sum('days_31_60'),
            'days_61_90' => $aging->sum('days_61_90'),
            'days_over_90' => $aging->sum('days_over_90'),
            'total_balance' => $aging->sum('total_balance')
        ];
        
        return response()->json([
            'data' => $aging,
            'totals' => $totals
        ], 200);
    }
}