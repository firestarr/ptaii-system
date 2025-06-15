<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\VendorPayable;
use App\Models\Accounting\PayablePayment;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayablePaymentController extends Controller
{
    /**
     * Display a listing of payable payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = PayablePayment::with('vendorPayable.vendor');
        
        // Filter by payable
        if ($request->has('payable_id')) {
            $query->where('payable_id', $request->payable_id);
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
     * Store a newly created payable payment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payable_id' => 'required|exists:VendorPayable,payable_id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required_if:currency,!=,USD|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'required|string|max:50',
            'create_journal_entry' => 'boolean',
            'cash_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id',
            'payable_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id',
            'exchange_gain_loss_account_id' => 'required_if:currency,!=,USD|exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Get the payable
        $payable = VendorPayable::findOrFail($request->payable_id);
        
        // Check if payable is already paid
        if ($payable->status === 'Paid') {
            return response()->json([
                'message' => 'This payable has already been fully paid'
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Get exchange rate (if not provided and not in base currency)
            $exchangeRate = $request->exchange_rate;
            $baseCurrency = config('app.base_currency', 'USD');
            $payableCurrency = $payable->currency ?? $baseCurrency;
            
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
            
            // Calculate exchange rate difference if the payment currency is different from payable currency
            $exchangeGainLoss = 0;
            if ($request->currency !== $payableCurrency) {
                // Convert payment to payable currency
                $payableCurrencyAmount = $baseCurrencyAmount;
                
                if ($payableCurrency !== $baseCurrency) {
                    // Get rate from payable currency to base currency
                    $payableToBaseRate = 1; // Default if same as base
                    
                    if ($payableCurrency !== $baseCurrency) {
                        $payableRateRecord = ExchangeRate::where('from_currency', $payableCurrency)
                            ->where('to_currency', $baseCurrency)
                            ->where('rate_date', '<=', $request->payment_date)
                            ->orderBy('rate_date', 'desc')
                            ->first();
                            
                        if ($payableRateRecord) {
                            $payableToBaseRate = $payableRateRecord->rate;
                        } else {
                            return response()->json([
                                'message' => 'Exchange rate for payable currency not found'
                            ], 422);
                        }
                    }
                    
                    $payableCurrencyAmount = $baseCurrencyAmount / $payableToBaseRate;
                }
                
                // Calculate exchange gain/loss in base currency
                $originalBaseAmount = $request->amount * $exchangeRate;
                $exchangeGainLoss = $originalBaseAmount - $payableCurrencyAmount;
            }
            
            // Check if payment amount is valid in payable currency
            if ($baseCurrencyAmount > $payable->balance) {
                return response()->json([
                    'message' => 'Payment amount cannot exceed the remaining balance of ' . $payable->balance
                ], 422);
            }
            
            // Create payment
            $payment = PayablePayment::create([
                'payable_id' => $request->payable_id,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'exchange_rate' => $exchangeRate,
                'base_currency_amount' => $baseCurrencyAmount,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number
            ]);
            
            // Update payable
            $payable->paid_amount += $baseCurrencyAmount;
            $payable->balance -= $baseCurrencyAmount;
            
            // Update status if fully paid
            if ($payable->balance <= 0) {
                $payable->status = 'Paid';
            }
            
            $payable->save();
            
            // Create journal entry if requested
            if ($request->input('create_journal_entry', false)) {
                // Validate required account IDs
                if (!$request->has('cash_account_id') || !$request->has('payable_account_id')) {
                    throw new \Exception('Cash and payable account IDs are required');
                }
                
                // For foreign currency, exchange gain/loss account is required
                if ($request->currency !== $baseCurrency && !$request->has('exchange_gain_loss_account_id')) {
                    throw new \Exception('Exchange gain/loss account ID is required for foreign currency payments');
                }
                
                // Create journal entry
                $journalEntry = JournalEntry::create([
                    'journal_number' => 'PAYPMT-' . date('YmdHis'),
                    'entry_date' => $request->payment_date,
                    'reference_type' => 'PayablePayment',
                    'reference_id' => $payment->payment_id,
                    'description' => 'Payment to ' . $payable->vendor->name . ' for invoice ' . $payable->vendorInvoice->invoice_number,
                    'period_id' => $this->getCurrentPeriodId(),
                    'status' => 'Posted'
                ]);
                
                // Create journal entry lines
                // Debit Accounts Payable
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $request->payable_account_id,
                    'debit_amount' => $baseCurrencyAmount,
                    'credit_amount' => 0,
                    'description' => 'Payment to ' . $payable->vendor->name,
                    'currency' => $payableCurrency,
                    'foreign_amount' => $payableCurrency !== $baseCurrency ? $request->amount : null
                ]);
                
                // Credit Cash/Bank
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $request->cash_account_id,
                    'debit_amount' => 0,
                    'credit_amount' => $baseCurrencyAmount,
                    'description' => 'Payment to ' . $payable->vendor->name,
                    'currency' => $request->currency,
                    'foreign_amount' => $request->currency !== $baseCurrency ? $request->amount : null
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
                            'description' => 'Exchange gain on payment to ' . $payable->vendor->name
                        ]);
                    } else {
                        // Exchange loss (debit)
                        JournalEntryLine::create([
                            'journal_id' => $journalEntry->journal_id,
                            'account_id' => $request->exchange_gain_loss_account_id,
                            'debit_amount' => abs($exchangeGainLoss),
                            'credit_amount' => 0,
                            'description' => 'Exchange loss on payment to ' . $payable->vendor->name
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'data' => $payment, 
                'message' => 'Payable payment created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create payable payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified payable payment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $payment = PayablePayment::with('vendorPayable.vendor')
            ->findOrFail($id);
        
        return response()->json(['data' => $payment], 200);
    }

    /**
     * Remove the specified payable payment from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $payment = PayablePayment::findOrFail($id);
        $payable = VendorPayable::findOrFail($payment->payable_id);
        
        try {
            DB::beginTransaction();
            
            // Get base currency amount
            $baseCurrencyAmount = $payment->base_currency_amount ?? $payment->amount;
            
            // Update payable
            $payable->paid_amount -= $baseCurrencyAmount;
            $payable->balance += $baseCurrencyAmount;
            
            // Update status
            if ($payable->balance > 0) {
                $payable->status = 'Open';
            }
            
            $payable->save();
            
            // Find and delete any related journal entry
            $journalEntry = JournalEntry::where('reference_type', 'PayablePayment')
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
            
            return response()->json(['message' => 'Payable payment deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete payable payment: ' . $e->getMessage()], 500);
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