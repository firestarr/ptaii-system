<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic if needed
    }

    // Update VendorInvoiceRequest
    public function rules()
    {
        $rules = [
            'invoice_number' => 'required|string|max:50|unique:vendor_invoices,invoice_number',
            'invoice_date' => 'required|date',
            'receipt_ids' => 'required|array|min:1',
            'receipt_ids.*' => 'required|exists:goods_receipts,receipt_id',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
        ];
        
        // Add invoice_number validation only for new invoices
        if ($this->isMethod('post')) {
            $rules['invoice_number'] = 'required|string|max:50|unique:vendor_invoices,invoice_number';
        } elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['invoice_number'] = 'required|string|max:50|unique:vendor_invoices,invoice_number,' . $this->route('vendorInvoice')->invoice_id . ',invoice_id';
        }
        
        return $rules;
    }
}