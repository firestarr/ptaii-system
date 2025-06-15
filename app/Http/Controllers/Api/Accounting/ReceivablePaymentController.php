<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\ReceivablePayment;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReceivablePaymentController extends Controller
{
    /**
     * Display a listing of receivable payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ReceivablePayment::with('customerReceivable.customer');
        
        // Filter by receivable
        if ($request->has('receivable_id')) {
            $query->where('receivable_id', $request->receivable_id);
        }
        
        // Filter by payment date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('payment_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by currency
        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }
        
        $payments = $query->orderBy('payment_date', 'desc')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($payments, 200);
    }

    /**
     * Store a newly created receivable payment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receivable_id' => 'required|exists:CustomerReceivable,receivable_id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required_if:currency,!=,USD|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'required|string|max:50',
            'create_journal_entry' => 'boolean',
            'cash_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id',
            'receivable_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id',
            'exchange_gain_loss_account_id' => 'required_if:currency,!=,USD|exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Get the receivable
        $receivable = CustomerReceivable::findOrFail($request->receivable_id);
        
        // Check if receivable is already paid
        if ($receivable->status === 'Paid') {
            return response()->json([
                'message' => 'This receivable has already been fully paid'
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Get exchange rate (if not provided and not in base currency)
            $exchangeRate = $request->exchange_rate;
            $baseCurrency = config('app.base_currency', 'USD');
            $receivableCurrency = $receivable->currency ?? $baseCurrency;
            
            if (!$exchangeRate && $request->currency !== $baseCurrency) {
                // Try to get from ExchangeRate model
                $rateRecord = ExchangeRate::where('from_currency', $request->currency)
                    ->where('to_currency', $baseCurrency)
                    ->where('rate_date', '<=', $request->payment_date)
                    ->orderBy('rate_date', 'desc')
                    ->first();
                
                if ($rateRecord) {
                    $exchangeRate = $rateRecord->rate;
                } else {
                    return response()->json([
                        'message' => 'Exchange rate not provided and no recent rate found'
                    ], 422);
                }
            }
            
            // If currency is the same as base, rate is 1
            if ($request->currency === $baseCurrency) {
                $exchangeRate = 1;
            }
            
            // Calculate base currency amount
            $baseCurrencyAmount = $request->amount;
            if ($request->currency !== $baseCurrency) {
                $baseCurrencyAmount = $request->amount * $exchangeRate;
            }
            
            // Calculate exchange rate difference if the payment currency is different from receivable currency
            $exchangeGainLoss = 0;
            if ($request->currency !== $receivableCurrency) {
                // Convert payment to receivable currency
                $receivableCurrencyAmount = $baseCurrencyAmount;
                
                if ($receivableCurrency !== $baseCurrency) {
                    // Get rate from receivable currency to base currency
                    $receivableToBaseRate = 1; // Default if same as base
                    
                    if ($receivableCurrency !== $baseCurrency) {
                        $receivableRateRecord = ExchangeRate::where('from_currency', $receivableCurrency)
                            ->where('to_currency', $baseCurrency)
                            ->where('rate_date', '<=', $request->payment_date)
                            ->orderBy('rate_date', 'desc')
                            ->first();
                            
                        if ($receivableRateRecord) {
                            $receivableToBaseRate = $receivableRateRecord->rate;
                        } else {
                            return response()->json([
                                'message' => 'Exchange rate for receivable currency not found'
                            ], 422);
                        }
                    }
                    
                    $receivableCurrencyAmount = $baseCurrencyAmount / $receivableToBaseRate;
                }
                
                // Calculate exchange gain/loss in base currency
                $originalBaseAmount = $request->amount * $exchangeRate;
                $exchangeGainLoss = $originalBaseAmount - $receivableCurrencyAmount;
            }
            
            // Check if payment amount is valid in receivable currency
            if ($baseCurrencyAmount > $receivable->balance) {
                return response()->json([
                    'message' => 'Payment amount cannot exceed the remaining balance of ' . $receivable->balance
                ], 422);
            }
            
            // Create payment
            $payment = ReceivablePayment::create([
                'receivable_id' => $request->receivable_id,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'exchange_rate' => $exchangeRate,
                'base_currency_amount' => $baseCurrencyAmount,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number
            ]);
            
            // Update receivable
            $receivable->paid_amount += $baseCurrencyAmount;
            $receivable->balance -= $baseCurrencyAmount;
            
            // Update status if fully paid
            if ($receivable->balance <= 0) {
                $receivable->status = 'Paid';
            }
            
            $receivable->save();
            
            // Create journal entry if requested
            if ($request->input('create_journal_entry', false)) {
                // Validate required account IDs
                if (!$request->has('cash_account_id') || !$request->has('receivable_account_id')) {
                    throw new \Exception('Cash and receivable account IDs are required');
                }
                
                // For foreign currency, exchange gain/loss account is required
                if ($request->currency !== $baseCurrency && !$request->has('exchange_gain_loss_account_id')) {
                    throw new \Exception('Exchange gain/loss account ID is required for foreign currency payments');
                }
                
                // Create journal entry
                $journalEntry = JournalEntry::create([
                    'journal_number' => 'RECPMT-' . date('YmdHis'),
                    'entry_date' => $request->payment_date,
                    'reference_type' => 'ReceivablePayment',
                    'reference_id' => $payment->payment_id,
                    'description' => 'Payment from ' . $receivable->customer->name . ' for invoice ' . $receivable->salesInvoice->invoice_number,
                    'period_id' => $this->getCurrentPeriodId(),
                    'status' => 'Posted'
                ]);
                
                // Create journal entry lines
                // Debit Cash/Bank
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $request->cash_account_id,
                    'debit_amount' => $baseCurrencyAmount,
                    'credit_amount' => 0,
                    'description' => 'Payment from ' . $receivable->customer->name,
                    'currency' => $request->currency,
                    'foreign_amount' => $request->currency !== $baseCurrency ? $request->amount : null
                ]);
                
                // Credit Accounts Receivable
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $request->receivable_account_id,
                    'debit_amount' => 0,
                    'credit_amount' => $baseCurrencyAmount,
                    'description' => 'Payment from ' . $receivable->customer->name,
                    'currency' => $receivableCurrency,
                    'foreign_amount' => $receivableCurrency !== $baseCurrency ? $baseCurrencyAmount / $exchangeRate : null
                ]);
                
                // Record exchange gain/loss if applicable
                if ($exchangeGainLoss != 0 && $request->has('exchange_gain_loss_account_id')) {
                    if ($exchangeGainLoss > 0) {
                        // Exchange gain (credit)
                        JournalEntryLine::create([
                            'journal_id' => $journalEntry->journal_id,
                            'account_id' => $request->exchange_gain_loss_account_id,
                            'debit_amount' => 0,
                            'credit_amount' => abs($exchangeGainLoss),
                            'description' => 'Exchange gain on payment from ' . $receivable->customer->name
                        ]);
                    } else {
                        // Exchange loss (debit)
                        JournalEntryLine::create([
                            'journal_id' => $journalEntry->journal_id,
                            'account_id' => $request->exchange_gain_loss_account_id,
                            'debit_amount' => abs($exchangeGainLoss),
                            'credit_amount' => 0,
                            'description' => 'Exchange loss on payment from ' . $receivable->customer->name
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'data' => $payment, 
                'message' => 'Receivable payment created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create receivable payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified receivable payment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $payment = ReceivablePayment::with('customerReceivable.customer')
            ->findOrFail($id);
        
        return response()->json(['data' => $payment], 200);
    }

    /**
     * Remove the specified receivable payment from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $payment = ReceivablePayment::findOrFail($id);
        $receivable = CustomerReceivable::findOrFail($payment->receivable_id);
        
        try {
            DB::beginTransaction();
            
            // Get base currency amount
            $baseCurrencyAmount = $payment->base_currency_amount ?? $payment->amount;
            
            // Update receivable
            $receivable->paid_amount -= $baseCurrencyAmount;
            $receivable->balance += $baseCurrencyAmount;
            
            // Update status
            if ($receivable->balance > 0) {
                $receivable->status = 'Open';
            }
            
            $receivable->save();
            
            // Find and delete any related journal entry
            $journalEntry = JournalEntry::where('reference_type', 'ReceivablePayment')
                ->where('reference_id', $payment->payment_id)
                ->first();
            
            if ($journalEntry) {
                // Delete journal entry lines
                JournalEntryLine::where('journal_id', $journalEntry->journal_id)->delete();
                
                // Delete journal entry
                $journalEntry->delete();
            }
            
            // Delete payment
            $payment->delete();
            
            DB::commit();
            
            return response()->json(['message' => 'Receivable payment deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete receivable payment: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Get currency exchange rates for a specific date.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getExchangeRates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'currency' => 'required|string|size:3'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        // If requesting base currency, rate is always 1
        if ($request->currency === $baseCurrency) {
            return response()->json([
                'data' => [
                    'currency' => $baseCurrency,
                    'date' => $request->date,
                    'rate' => 1
                ]
            ]);
        }
        
        // Get latest rate before or on the requested date
        $rate = ExchangeRate::where('from_currency', $request->currency)
            ->where('to_currency', $baseCurrency)
            ->where('rate_date', '<=', $request->date)
            ->orderBy('rate_date', 'desc')
            ->first();
        
        if (!$rate) {
            return response()->json([
                'message' => 'No exchange rate found for ' . $request->currency . ' on or before ' . $request->date
            ], 404);
        }
        
        return response()->json([
            'data' => [
                'currency' => $request->currency,
                'date' => $rate->rate_date,
                'rate' => $rate->rate
            ]
        ]);
    }
    
    /**
     * Helper method to get the current accounting period ID.
     *
     * @return int
     */
    private function getCurrentPeriodId()
    {
        $currentPeriod = DB::table('AccountingPeriod')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('status', 'Open')
            ->first();
        
        if (!$currentPeriod) {
            throw new \Exception('No active accounting period found for the current date');
        }
        
        return $currentPeriod->period_id;
    }
}