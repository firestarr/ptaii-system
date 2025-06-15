<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\TaxTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TaxTransactionController extends Controller
{
    /**
     * Display a listing of tax transactions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = TaxTransaction::query();
        
        // Filter by tax type
        if ($request->has('tax_type')) {
            $query->where('tax_type', $request->tax_type);
        }
        
        // Filter by tax code
        if ($request->has('tax_code')) {
            $query->where('tax_code', $request->tax_code);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
        }
        
        $transactions = $query->orderBy('transaction_date', 'desc')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($transactions, 200);
    }

    /**
     * Store a newly created tax transaction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tax_type' => 'required|string|max:50',
            'reference_type' => 'required|string|max:50',
            'reference_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'tax_amount' => 'required|numeric',
            'tax_code' => 'required|string|max:50',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = TaxTransaction::create($request->all());

        return response()->json([
            'data' => $transaction, 
            'message' => 'Tax transaction created successfully'
        ], 201);
    }

    /**
     * Display the specified tax transaction.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $transaction = TaxTransaction::findOrFail($id);
        
        return response()->json(['data' => $transaction], 200);
    }

    /**
     * Update the specified tax transaction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $transaction = TaxTransaction::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'tax_type' => 'string|max:50',
            'reference_type' => 'string|max:50',
            'reference_id' => 'integer',
            'transaction_date' => 'date',
            'tax_amount' => 'numeric',
            'tax_code' => 'string|max:50',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $transaction->update($request->all());

        return response()->json([
            'data' => $transaction, 
            'message' => 'Tax transaction updated successfully'
        ], 200);
    }

    /**
     * Remove the specified tax transaction from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $transaction = TaxTransaction::findOrFail($id);
        $transaction->delete();

        return response()->json(['message' => 'Tax transaction deleted successfully'], 200);
    }
    
    /**
     * Generate a tax summary report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function summary(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'tax_type' => 'nullable|string'
        ]);
        
        $query = DB::table('TaxTransaction')
            ->select(
                'tax_type',
                'tax_code',
                DB::raw('SUM(tax_amount) as total_amount'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->whereBetween('transaction_date', [$request->from_date, $request->to_date])
            ->groupBy('tax_type', 'tax_code');
        
        // Filter by tax type if provided
        if ($request->has('tax_type')) {
            $query->where('tax_type', $request->tax_type);
        }
        
        $summary = $query->orderBy('tax_type')
            ->orderBy('tax_code')
            ->get();
        
        // Calculate grand total
        $grandTotal = $summary->sum('total_amount');
        
        return response()->json([
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'summary' => $summary,
            'grand_total' => $grandTotal
        ], 200);
    }
}