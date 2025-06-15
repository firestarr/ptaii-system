<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CustomerReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerReceivableController extends Controller
{
    /**
     * Display a listing of customer receivables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = CustomerReceivable::with(['customer', 'salesInvoice']);
        
        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by due date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('due_date', [$request->from_date, $request->to_date]);
        }
        
        $receivables = $query->orderBy('due_date')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($receivables, 200);
    }

    /**
     * Store a newly created customer receivable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'invoice_id' => 'required|exists:SalesInvoice,invoice_id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a receivable already exists for this invoice
        $exists = CustomerReceivable::where('invoice_id', $request->invoice_id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'A receivable already exists for this invoice'
            ], 422);
        }

        $receivable = CustomerReceivable::create([
            'customer_id' => $request->customer_id,
            'invoice_id' => $request->invoice_id,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
            'paid_amount' => 0,
            'balance' => $request->amount,
            'status' => $request->status
        ]);

        return response()->json([
            'data' => $receivable, 
            'message' => 'Customer receivable created successfully'
        ], 201);
    }

    /**
     * Display the specified customer receivable.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $receivable = CustomerReceivable::with([
            'customer', 
            'salesInvoice',
            'receivablePayments'
        ])->findOrFail($id);
        
        return response()->json(['data' => $receivable], 200);
    }

    /**
     * Update the specified customer receivable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $receivable = CustomerReceivable::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'due_date' => 'date',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Only allow updating due date and status
        $receivable->update($request->only(['due_date', 'status']));

        return response()->json([
            'data' => $receivable, 
            'message' => 'Customer receivable updated successfully'
        ], 200);
    }

    /**
     * Remove the specified customer receivable from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $receivable = CustomerReceivable::findOrFail($id);
        
        // Check if there are payments
        if ($receivable->receivablePayments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete receivable with associated payments'
            ], 422);
        }
        
        $receivable->delete();

        return response()->json(['message' => 'Customer receivable deleted successfully'], 200);
    }
    
    /**
     * Generate aging report for customer receivables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function aging(Request $request)
    {
        $today = now()->format('Y-m-d');
        
        $aging = DB::table('CustomerReceivable')
            ->join('Customer', 'CustomerReceivable.customer_id', '=', 'Customer.customer_id')
            ->select(
                'CustomerReceivable.customer_id',
                'Customer.name as customer_name',
                DB::raw('SUM(CASE WHEN DATEDIFF("' . $today . '", due_date) <= 0 THEN balance ELSE 0 END) as current_amount'),
                DB::raw('SUM(CASE WHEN DATEDIFF("' . $today . '", due_date) BETWEEN 1 AND 30 THEN balance ELSE 0 END) as days_1_30'),
                DB::raw('SUM(CASE WHEN DATEDIFF("' . $today . '", due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END) as days_31_60'),
                DB::raw('SUM(CASE WHEN DATEDIFF("' . $today . '", due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END) as days_61_90'),
                DB::raw('SUM(CASE WHEN DATEDIFF("' . $today . '", due_date) > 90 THEN balance ELSE 0 END) as days_over_90'),
                DB::raw('SUM(balance) as total_balance')
            )
            ->where('CustomerReceivable.status', '!=', 'Paid')
            ->groupBy('CustomerReceivable.customer_id', 'Customer.name')
            ->orderBy('Customer.name')
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