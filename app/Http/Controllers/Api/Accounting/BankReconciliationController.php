<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankReconciliationController extends Controller
{
    /**
     * Display a listing of bank reconciliations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = BankReconciliation::with('bankAccount');
        
        // Filter by bank account
        if ($request->has('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $reconciliations = $query->orderBy('statement_date', 'desc')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($reconciliations, 200);
    }

    /**
     * Store a newly created bank reconciliation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_id' => 'required|exists:BankAccount,bank_id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric',
            'book_balance' => 'required|numeric',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a reconciliation already exists for this bank account and date
        $exists = BankReconciliation::where('bank_id', $request->bank_id)
            ->where('statement_date', $request->statement_date)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'A reconciliation already exists for this bank account and date'
            ], 422);
        }
        
        $reconciliation = BankReconciliation::create($request->all());

        return response()->json([
            'data' => $reconciliation, 
            'message' => 'Bank reconciliation created successfully'
        ], 201);
    }

    /**
     * Display the specified bank reconciliation.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $reconciliation = BankReconciliation::with([
            'bankAccount',
            'reconciliationLines'
        ])->findOrFail($id);
        
        return response()->json(['data' => $reconciliation], 200);
    }

    /**
     * Update the specified bank reconciliation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $reconciliation = BankReconciliation::findOrFail($id);
        
        // Don't allow updating finalized reconciliations
        if ($reconciliation->status === 'Finalized') {
            return response()->json([
                'message' => 'Cannot update a finalized bank reconciliation'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'statement_date' => 'date',
            'statement_balance' => 'numeric',
            'book_balance' => 'numeric',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Don't allow changing the status to Finalized through this endpoint
        if ($request->has('status') && $request->status === 'Finalized') {
            return response()->json([
                'message' => 'Use the finalize endpoint to finalize a reconciliation'
            ], 422);
        }
        
        $reconciliation->update($request->all());

        return response()->json([
            'data' => $reconciliation, 
            'message' => 'Bank reconciliation updated successfully'
        ], 200);
    }

    /**
     * Remove the specified bank reconciliation from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $reconciliation = BankReconciliation::findOrFail($id);
        
        // Don't allow deleting finalized reconciliations
        if ($reconciliation->status === 'Finalized') {
            return response()->json([
                'message' => 'Cannot delete a finalized bank reconciliation'
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete reconciliation lines
            BankReconciliationLine::where('reconciliation_id', $reconciliation->reconciliation_id)->delete();
            
            // Delete reconciliation
            $reconciliation->delete();
            
            DB::commit();
            
            return response()->json(['message' => 'Bank reconciliation deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete bank reconciliation: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Get all reconciliation lines for a bank reconciliation.
     *
     * @param  int  $reconciliationId
     * @return \Illuminate\Http\Response
     */
    public function lines($reconciliationId)
    {
        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        
        $lines = BankReconciliationLine::where('reconciliation_id', $reconciliationId)
            ->orderBy('transaction_date')
            ->get();
        
        return response()->json(['data' => $lines], 200);
    }
    
    /**
     * Store a newly created reconciliation line.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $reconciliationId
     * @return \Illuminate\Http\Response
     */
    public function storeLine(Request $request, $reconciliationId)
    {
        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        
        // Don't allow adding lines to finalized reconciliations
        if ($reconciliation->status === 'Finalized') {
            return response()->json([
                'message' => 'Cannot add lines to a finalized bank reconciliation'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'transaction_type' => 'required|string|max:50',
            'transaction_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric',
            'is_reconciled' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $line = BankReconciliationLine::create([
            'reconciliation_id' => $reconciliationId,
            'transaction_type' => $request->transaction_type,
            'transaction_id' => $request->transaction_id,
            'transaction_date' => $request->transaction_date,
            'amount' => $request->amount,
            'is_reconciled' => $request->input('is_reconciled', false)
        ]);
        
        return response()->json([
            'data' => $line, 
            'message' => 'Reconciliation line added successfully'
        ], 201);
    }
    
    /**
     * Update a reconciliation line.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $reconciliationId
     * @param  int  $lineId
     * @return \Illuminate\Http\Response
     */
    public function updateLine(Request $request, $reconciliationId, $lineId)
    {
        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        
        // Don't allow updating lines in finalized reconciliations
        if ($reconciliation->status === 'Finalized') {
            return response()->json([
                'message' => 'Cannot update lines in a finalized bank reconciliation'
            ], 422);
        }
        
        $line = BankReconciliationLine::where('reconciliation_id', $reconciliationId)
            ->where('line_id', $lineId)
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'transaction_type' => 'string|max:50',
            'transaction_id' => 'integer',
            'transaction_date' => 'date',
            'amount' => 'numeric',
            'is_reconciled' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $line->update($request->all());
        
        return response()->json([
            'data' => $line, 
            'message' => 'Reconciliation line updated successfully'
        ], 200);
    }
    
    /**
     * Delete a reconciliation line.
     *
     * @param  int  $reconciliationId
     * @param  int  $lineId
     * @return \Illuminate\Http\Response
     */
    public function destroyLine($reconciliationId, $lineId)
    {
        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        
        // Don't allow deleting lines from finalized reconciliations
        if ($reconciliation->status === 'Finalized') {
            return response()->json([
                'message' => 'Cannot delete lines from a finalized bank reconciliation'
            ], 422);
        }
        
        $line = BankReconciliationLine::where('reconciliation_id', $reconciliationId)
            ->where('line_id', $lineId)
            ->firstOrFail();
        
        $line->delete();
        
        return response()->json(['message' => 'Reconciliation line deleted successfully'], 200);
    }
    
    /**
     * Finalize a bank reconciliation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function finalize(Request $request, $id)
    {
        $reconciliation = BankReconciliation::findOrFail($id);
        
        // Don't allow finalizing if already finalized
        if ($reconciliation->status === 'Finalized') {
            return response()->json([
                'message' => 'Bank reconciliation is already finalized'
            ], 422);
        }
        
        // Get unreconciled lines
        $unreconciledLines = BankReconciliationLine::where('reconciliation_id', $id)
            ->where('is_reconciled', false)
            ->count();
        
        if ($unreconciledLines > 0) {
            return response()->json([
                'message' => 'Cannot finalize a reconciliation with unreconciled lines',
                'unreconciled_count' => $unreconciledLines
            ], 422);
        }
        
        // Get the total of reconciled items
        $reconciledTotal = BankReconciliationLine::where('reconciliation_id', $id)
            ->where('is_reconciled', true)
            ->sum('amount');
        
        // Calculate difference between book balance and reconciled total
        $difference = $reconciliation->book_balance - $reconciledTotal;
        
        // Allow a small rounding difference
        if (abs($difference) > 0.01) {
            return response()->json([
                'message' => 'Bank reconciliation does not balance',
                'book_balance' => $reconciliation->book_balance,
                'reconciled_total' => $reconciledTotal,
                'difference' => $difference
            ], 422);
        }
        
        // Update bank account current balance to match statement balance
        $bankAccount = BankAccount::findOrFail($reconciliation->bank_id);
        $bankAccount->current_balance = $reconciliation->statement_balance;
        $bankAccount->save();
        
        // Update reconciliation status
        $reconciliation->status = 'Finalized';
        $reconciliation->save();
        
        return response()->json([
            'data' => $reconciliation, 
            'message' => 'Bank reconciliation finalized successfully'
        ], 200);
    }
}