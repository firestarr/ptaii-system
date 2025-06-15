<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\VendorPayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    /**
     * Generate trial balance report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function trialBalance(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'include_zero_balances' => 'boolean'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        
        // Get all journal entries for the period that are posted
        $journalEntries = JournalEntry::where('period_id', $period->period_id)
            ->where('status', 'Posted')
            ->pluck('journal_id');
        
        // Calculate debits and credits for each account
        $accounts = DB::table('ChartOfAccount')
            ->leftJoin('JournalEntryLine', 'ChartOfAccount.account_id', '=', 'JournalEntryLine.account_id')
            ->leftJoin('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->select(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                'ChartOfAccount.account_type',
                DB::raw('SUM(IFNULL(JournalEntryLine.debit_amount, 0)) as total_debit'),
                DB::raw('SUM(IFNULL(JournalEntryLine.credit_amount, 0)) as total_credit')
            )
            ->whereIn('JournalEntry.journal_id', $journalEntries)
            ->where('JournalEntry.status', 'Posted')
            ->groupBy(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                'ChartOfAccount.account_type'
            );
        
        // Exclude accounts with zero balances if requested
        if (!$request->input('include_zero_balances', true)) {
            $accounts->havingRaw('SUM(IFNULL(JournalEntryLine.debit_amount, 0)) > 0 OR SUM(IFNULL(JournalEntryLine.credit_amount, 0)) > 0');
        }
        
        $accounts = $accounts->orderBy('ChartOfAccount.account_code')
            ->get();
        
        // Calculate balance for each account
        foreach ($accounts as $account) {
            $account->balance = $account->total_debit - $account->total_credit;
            
            // For balance sheet accounts, balances are inverted for certain types
            if (in_array($account->account_type, ['Liability', 'Equity', 'Revenue'])) {
                $account->balance = -$account->balance;
            }
        }
        
        // Calculate totals
        $totals = [
            'total_debit' => $accounts->sum('total_debit'),
            'total_credit' => $accounts->sum('total_credit'),
            'difference' => $accounts->sum('total_debit') - $accounts->sum('total_credit')
        ];
        
        return response()->json([
            'period' => $period,
            'accounts' => $accounts,
            'totals' => $totals
        ], 200);
    }
    
    /**
     * Generate income statement report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function incomeStatement(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'comparative' => 'boolean',
            'previous_period_id' => 'required_if:comparative,1|exists:AccountingPeriod,period_id'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        
        // Get revenue accounts
        $revenues = $this->getAccountBalancesByType('Revenue', $period->period_id);
        
        // Get expense accounts
        $expenses = $this->getAccountBalancesByType('Expense', $period->period_id);
        
        // Calculate totals
        $totalRevenue = $revenues->sum('balance');
        $totalExpenses = $expenses->sum('balance');
        $netIncome = $totalRevenue - $totalExpenses;
        
        // If comparative report is requested
        $comparativeData = null;
        if ($request->input('comparative', false) && $request->has('previous_period_id')) {
            $prevPeriod = AccountingPeriod::findOrFail($request->previous_period_id);
            
            // Get comparative data
            $prevRevenues = $this->getAccountBalancesByType('Revenue', $prevPeriod->period_id);
            $prevExpenses = $this->getAccountBalancesByType('Expense', $prevPeriod->period_id);
            
            $prevTotalRevenue = $prevRevenues->sum('balance');
            $prevTotalExpenses = $prevExpenses->sum('balance');
            $prevNetIncome = $prevTotalRevenue - $prevTotalExpenses;
            
            $comparativeData = [
                'period' => $prevPeriod,
                'revenues' => $prevRevenues,
                'expenses' => $prevExpenses,
                'total_revenue' => $prevTotalRevenue,
                'total_expenses' => $prevTotalExpenses,
                'net_income' => $prevNetIncome
            ];
        }
        
        return response()->json([
            'period' => $period,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => $netIncome,
            'comparative' => $comparativeData
        ], 200);
    }
    
    /**
     * Generate balance sheet report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function balanceSheet(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'comparative' => 'boolean',
            'previous_period_id' => 'required_if:comparative,1|exists:AccountingPeriod,period_id'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        
        // Get asset accounts
        $assets = $this->getAccountBalancesByType('Asset', $period->period_id);
        
        // Get liability accounts
        $liabilities = $this->getAccountBalancesByType('Liability', $period->period_id);
        
        // Get equity accounts
        $equity = $this->getAccountBalancesByType('Equity', $period->period_id);
        
        // Calculate retained earnings
        $retainedEarnings = $this->calculateRetainedEarnings($period->period_id);
        
        // Add retained earnings to equity
        $equity->push([
            'account_id' => null,
            'account_code' => null,
            'name' => 'Retained Earnings',
            'balance' => $retainedEarnings
        ]);
        
        // Calculate totals
        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquity = $equity->sum('balance');
        
        // If comparative report is requested
        $comparativeData = null;
        if ($request->input('comparative', false) && $request->has('previous_period_id')) {
            $prevPeriod = AccountingPeriod::findOrFail($request->previous_period_id);
            
            // Get comparative data
            $prevAssets = $this->getAccountBalancesByType('Asset', $prevPeriod->period_id);
            $prevLiabilities = $this->getAccountBalancesByType('Liability', $prevPeriod->period_id);
            $prevEquity = $this->getAccountBalancesByType('Equity', $prevPeriod->period_id);
            $prevRetainedEarnings = $this->calculateRetainedEarnings($prevPeriod->period_id);
            
            $prevEquity->push([
                'account_id' => null,
                'account_code' => null,
                'name' => 'Retained Earnings',
                'balance' => $prevRetainedEarnings
            ]);
            
            $prevTotalAssets = $prevAssets->sum('balance');
            $prevTotalLiabilities = $prevLiabilities->sum('balance');
            $prevTotalEquity = $prevEquity->sum('balance');
            
            $comparativeData = [
                'period' => $prevPeriod,
                'assets' => $prevAssets,
                'liabilities' => $prevLiabilities,
                'equity' => $prevEquity,
                'total_assets' => $prevTotalAssets,
                'total_liabilities' => $prevTotalLiabilities,
                'total_equity' => $prevTotalEquity
            ];
        }
        
        return response()->json([
            'period' => $period,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'comparative' => $comparativeData
        ], 200);
    }
    
    /**
     * Generate cash flow statement report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cashFlow(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id'
        ]);
        
        // This report is more complex and would need to analyze transactions
        // to determine cash flows from operating, investing, and financing activities
        // For now, return a placeholder response
        
        return response()->json([
            'message' => 'Cash flow statement functionality to be implemented'
        ], 200);
    }
    
    /**
     * Generate accounts receivable report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function accountsReceivable(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'as_of_date' => 'required|date'
        ]);
        
        $asOfDate = $request->as_of_date;
        
        $receivables = CustomerReceivable::with('customer')
            ->where('due_date', '<=', $asOfDate)
            ->where('status', '!=', 'Paid')
            ->orderBy('due_date')
            ->get();
        
        return response()->json([
            'as_of_date' => $asOfDate,
            'receivables' => $receivables,
            'total' => $receivables->sum('balance')
        ], 200);
    }
    
    /**
     * Generate accounts payable report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function accountsPayable(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'as_of_date' => 'required|date'
        ]);
        
        $asOfDate = $request->as_of_date;
        
        $payables = VendorPayable::with('vendor')
            ->where('due_date', '<=', $asOfDate)
            ->where('status', '!=', 'Paid')
            ->orderBy('due_date')
            ->get();
        
        return response()->json([
            'as_of_date' => $asOfDate,
            'payables' => $payables,
            'total' => $payables->sum('balance')
        ], 200);
    }
    
    /**
     * Helper method to get account balances by account type.
     *
     * @param  string  $accountType
     * @param  int  $periodId
     * @return \Illuminate\Support\Collection
     */
    private function getAccountBalancesByType($accountType, $periodId)
    {
        $journalEntries = JournalEntry::where('period_id', $periodId)
            ->where('status', 'Posted')
            ->pluck('journal_id');
        
        return DB::table('ChartOfAccount')
            ->leftJoin('JournalEntryLine', 'ChartOfAccount.account_id', '=', 'JournalEntryLine.account_id')
            ->leftJoin('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->select(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                DB::raw('SUM(IFNULL(JournalEntryLine.credit_amount, 0)) - SUM(IFNULL(JournalEntryLine.debit_amount, 0)) as balance')
            )
            ->where('ChartOfAccount.account_type', $accountType)
            ->whereIn('JournalEntry.journal_id', $journalEntries)
            ->where('JournalEntry.status', 'Posted')
            ->groupBy(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name'
            )
            ->havingRaw('balance <> 0')
            ->orderBy('ChartOfAccount.account_code')
            ->get();
    }
    
    /**
     * Helper method to calculate retained earnings.
     *
     * @param  int  $periodId
     * @return float
     */
    private function calculateRetainedEarnings($periodId)
    {
        // Get all periods up to and including the current one
        $period = AccountingPeriod::findOrFail($periodId);
        $periods = AccountingPeriod::where('end_date', '<=', $period->end_date)
            ->pluck('period_id');
        
        // Get all posted journal entries for these periods
        $journalEntries = JournalEntry::whereIn('period_id', $periods)
            ->where('status', 'Posted')
            ->pluck('journal_id');
        
        // Calculate net income (Revenue - Expenses)
        $revenueAccounts = ChartOfAccount::where('account_type', 'Revenue')
            ->pluck('account_id');
        
        $expenseAccounts = ChartOfAccount::where('account_type', 'Expense')
            ->pluck('account_id');
        
        $revenues = JournalEntryLine::whereIn('journal_id', $journalEntries)
            ->whereIn('account_id', $revenueAccounts)
            ->sum('credit_amount') - JournalEntryLine::whereIn('journal_id', $journalEntries)
            ->whereIn('account_id', $revenueAccounts)
            ->sum('debit_amount');
        
        $expenses = JournalEntryLine::whereIn('journal_id', $journalEntries)
            ->whereIn('account_id', $expenseAccounts)
            ->sum('debit_amount') - JournalEntryLine::whereIn('journal_id', $journalEntries)
            ->whereIn('account_id', $expenseAccounts)
            ->sum('credit_amount');
        
        return $revenues - $expenses;
    }
}