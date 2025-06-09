<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\CurrencyRate;
use App\Http\Requests\VendorRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendor::query();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 100);
        $vendors = $query->paginate($perPage);

        return response()->json(['data' => $vendors], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code',
            'name' => 'required|string|max:100',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'preferred_currency' => 'nullable|string|size:3',
            'payment_term' => ['nullable', 'integer', 'in:30,60,90'],
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Default payment term to 30 if not provided
        $data = $validator->validated();
        $data['payment_term'] = $data['payment_term'] ?? 30;
        
        // Add preferred_currency field with default if not provided
        if (!isset($data['preferred_currency'])) {
            $data['preferred_currency'] = config('app.base_currency', 'USD');
        }
        
        $vendor = Vendor::create($data);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Vendor created successfully',
            'data' => $vendor
        ], 201);
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['contracts', 'evaluations']);
        
        return response()->json([
            'status' => 'success',
            'data' => $vendor
        ]);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code,' . $vendor->vendor_id . ',vendor_id',
            'name' => 'required|string|max:100',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'preferred_currency' => 'nullable|string|size:3',
            'payment_term' => ['nullable', 'integer', 'in:30,60,90'],
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $vendor->update($validator->validated());
        
        return response()->json([
            'status' => 'success',
            'message' => 'Vendor updated successfully',
            'data' => $vendor
        ]);
    }

    public function destroy(Vendor $vendor)
    {
        // Check if vendor can be deleted
        $hasRelations = $vendor->purchaseOrders()->exists() || 
                        $vendor->quotations()->exists() || 
                        $vendor->invoices()->exists();
        
        if ($hasRelations) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vendor cannot be deleted as it has related records'
            ], 400);
        }
        
        $vendor->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Vendor deleted successfully'
        ]);
    }
    
    public function evaluations(Vendor $vendor)
    {
        $evaluations = $vendor->evaluations()->orderBy('evaluation_date', 'desc')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $evaluations
        ]);
    }
    
    public function purchaseOrders(Vendor $vendor)
    {
        $purchaseOrders = $vendor->purchaseOrders()
                                ->with('lines.item')
                                ->orderBy('po_date', 'desc')
                                ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $purchaseOrders
        ]);
    }
    
    /**
     * Get vendor transactions in specified currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Vendor  $vendor
     * @return \Illuminate\Http\Response
     */
    public function getTransactionsInCurrency(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|size:3',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $currency = $request->currency;
        $date = $request->date ?? now()->format('Y-m-d');
        
        // Get purchase orders
        $purchaseOrders = $vendor->purchaseOrders()
            ->with(['lines'])
            ->get()
            ->map(function($po) use ($currency, $date) {
                $amounts = $po->getAmountsInCurrency($currency, $date);
                return [
                    'po_id' => $po->po_id,
                    'po_number' => $po->po_number,
                    'po_date' => $po->po_date,
                    'original_currency' => $po->currency_code,
                    'display_currency' => $currency,
                    'total_amount' => $amounts['total_amount'],
                    'tax_amount' => $amounts['tax_amount']
                ];
            });
        
        // Get invoices
        $invoices = $vendor->invoices()
            ->with(['lines'])
            ->get()
            ->map(function($invoice) use ($currency, $date) {
                $amounts = $invoice->getAmountsInCurrency($currency, $date);
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'original_currency' => $invoice->currency_code,
                    'display_currency' => $currency,
                    'total_amount' => $amounts['total_amount'],
                    'tax_amount' => $amounts['tax_amount']
                ];
            });
        
        // Get payables
        $payables = $vendor->payables()
            ->get()
            ->map(function($payable) use ($currency, $date) {
                $amounts = $payable->getAmountsInCurrency($currency, $date);
                return [
                    'payable_id' => $payable->payable_id,
                    'invoice_number' => $payable->vendorInvoice->invoice_number,
                    'due_date' => $payable->due_date,
                    'original_currency' => $payable->currency_code,
                    'display_currency' => $currency,
                    'amount' => $amounts['amount'],
                    'paid_amount' => $amounts['paid_amount'],
                    'balance' => $amounts['balance']
                ];
            });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'vendor' => $vendor,
                'currency' => $currency,
                'purchase_orders' => $purchaseOrders,
                'invoices' => $invoices,
                'payables' => $payables
            ]
        ]);
    }
    
    /**
     * Update vendor's preferred currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Vendor  $vendor
     * @return \Illuminate\Http\Response
     */
    public function updatePreferredCurrency(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'preferred_currency' => 'required|string|size:3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if currency is valid by checking if exchange rate exists
        $baseCurrency = config('app.base_currency', 'USD');
        if ($request->preferred_currency !== $baseCurrency) {
            $rate = CurrencyRate::where(function($query) use ($request, $baseCurrency) {
                    $query->where('from_currency', $request->preferred_currency)
                          ->where('to_currency', $baseCurrency);
                })
                ->orWhere(function($query) use ($request, $baseCurrency) {
                    $query->where('from_currency', $baseCurrency)
                          ->where('to_currency', $request->preferred_currency);
                })
                ->where('is_active', true)
                ->first();
                
            if (!$rate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No exchange rate found for the specified currency'
                ], 422);
            }
        }
        
        $vendor->update([
            'preferred_currency' => $request->preferred_currency
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Vendor preferred currency updated successfully',
            'data' => $vendor
        ]);
    }

    /**
     * Update vendor's payment term.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Vendor  $vendor
     * @return \Illuminate\Http\Response
     */
    public function updatePaymentTerm(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'payment_term' => ['required', 'integer', 'in:30,60,90']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $vendor->update([
            'payment_term' => $request->payment_term
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Vendor payment term updated successfully',
            'data' => [
                'vendor_id' => $vendor->vendor_id,
                'name' => $vendor->name,
                'payment_term' => $vendor->payment_term,
                'payment_term_description' => $vendor->getPaymentTermDescriptionAttribute()
            ]
        ]);
    }
}