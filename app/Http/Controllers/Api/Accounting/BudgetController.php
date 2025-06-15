<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Budget;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    /**
     * Display a listing of budgets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Budget::with(['chartOfAccount', 'accountingPeriod']);
        
        // Filter by account
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        
        // Filter by period
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }
        
        $budgets = $query->orderBy('period_id')
            ->orderBy('account_id')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($budgets, 200);
    }

    /**
     * Store a newly created budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:ChartOfAccount,account_id',
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'budgeted_amount' => 'required|numeric',
            'actual_amount' => 'nullable|numeric',
            'variance' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a budget already exists for this account and period
        $exists = Budget::where('account_id', $request->account_id)
            ->where('period_id', $request->period_id)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'A budget already exists for this account and period'
            ], 422);
        }
        
        // Initialize variance if actual amount is provided
        $variance = null;
        if ($request->has('actual_amount') && $request->actual_amount !== null) {
            $variance = $request->actual_amount - $request->budgeted_amount;
        }
        
        $budget = Budget::create([
            'account_id' => $request->account_id,
            'period_id' => $request->period_id,
            'budgeted_amount' => $request->budgeted_amount,
            'actual_amount' => $request->actual_amount,
            'variance' => $variance
        ]);

        return response()->json([
            'data' => $budget, 
            'message' => 'Budget created successfully'
        ], 201);
    }

    /**
     * Display the specified budget.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $budget = Budget::with(['chartOfAccount', 'accountingPeriod'])
            ->findOrFail($id);
        
        return response()->json(['data' => $budget], 200);
    }

    /**
     * Update the specified budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $budget = Budget::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'budgeted_amount' => 'numeric',
            'actual_amount' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $data = [];
        
        // Update budgeted amount if provided
        if ($request->has('budgeted_amount')) {
            $data['budgeted_amount'] = $request->budgeted_amount;
        }
        
        // Update actual amount if provided
        if ($request->has('actual_amount')) {
            $data['actual_amount'] = $request->actual_amount;
        }
        
        // Calculate variance if both budgeted and actual amounts are available
        if (isset($data['budgeted_amount']) || isset($data['actual_amount'])) {
            $budgetedAmount = $data['budgeted_amount'] ?? $budget->budgeted_amount;
            $actualAmount = $data['actual_amount'] ?? $budget->actual_amount;
            
            if ($actualAmount !== null) {
                $data['variance'] = $actualAmount - $budgetedAmount;
            }
        }
        
        $budget->update($data);

        return response()->json([
            'data' => $budget, 
            'message' => 'Budget updated successfully'
        ], 200);
    }

    /**
     * Remove the specified budget from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $budget = Budget::findOrFail($id);
        $budget->delete();

        return response()->json(['message' => 'Budget deleted successfully'], 200);
    }
    
    /**
     * Generate a budget variance report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function varianceReport(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'account_type' => 'nullable|string'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        
        $query = DB::table('Budget')
            ->join('ChartOfAccount', 'Budget.account_id', '=', 'ChartOfAccount.account_id')
            ->select(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                'ChartOfAccount.account_type',
                'Budget.budgeted_amount',
                'Budget.actual_amount',
                'Budget.variance',
                DB::raw('CASE WHEN Budget.budgeted_amount = 0 THEN NULL ELSE (Budget.variance / Budget.budgeted_amount) * 100 END as variance_percentage')
            )
            ->where('Budget.period_id', $period->period_id);
        
        // Filter by account type if provided
        if ($request->has('account_type')) {
            $query->where('ChartOfAccount.account_type', $request->account_type);
        }
        
        $budgets = $query->orderBy('ChartOfAccount.account_code')
            ->get();
        
        // Calculate totals by account type
        $totals = [];
        foreach ($budgets->groupBy('account_type') as $accountType => $accounts) {
            $totals[$accountType] = [
                'budgeted_amount' => $accounts->sum('budgeted_amount'),
                'actual_amount' => $accounts->sum('actual_amount'),
                'variance' => $accounts->sum('variance')
            ];
            
            // Calculate variance percentage
            if ($totals[$accountType]['budgeted_amount'] != 0) {
                $totals[$accountType]['variance_percentage'] = 
                    ($totals[$accountType]['variance'] / $totals[$accountType]['budgeted_amount']) * 100;
            } else {
                $totals[$accountType]['variance_percentage'] = null;
            }
        }
        
        return response()->json([
            'period' => $period,
            'budgets' => $budgets,
            'totals' => $totals
        ], 200);
    }
}