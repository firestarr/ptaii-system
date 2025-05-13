<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\GoodsReceiptLine;

class GoodsReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Add authorization logic if needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'receipt_date' => 'required|date',
            'vendor_id' => 'required|exists:vendors,vendor_id',
            'lines' => 'required|array|min:1',
            'lines.*.po_line_id' => 'required|exists:po_lines,line_id',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.received_quantity' => 'required|numeric|min:0.01',
            'lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'lines.*.batch_number' => 'nullable|string|max:50'
        ];
        
        // Additional custom validation for outstanding quantity
        if ($this->isMethod('post') || $this->isMethod('put') || $this->isMethod('patch')) {
            foreach ($this->input('lines', []) as $index => $line) {
                if (isset($line['po_line_id'])) {
                    $rules['lines.' . $index . '.received_quantity'] .= '|lte:' . $this->getOutstandingQuantity($line['po_line_id']);
                }
            }
        }
        
        return $rules;
    }
    
    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'lines.*.received_quantity.lte' => 'The received quantity for line :index exceeds the outstanding quantity.'
        ];
    }
    
    /**
     * Get the outstanding quantity for a PO line.
     *
     * @param int $poLineId
     * @return float
     */
    protected function getOutstandingQuantity($poLineId)
    {
        $excludeReceiptId = null;
        
        // If updating, exclude current receipt
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $excludeReceiptId = $this->route('goodsReceipt')->receipt_id;
        }
        
        return GoodsReceiptLine::getOutstandingQuantity($poLineId, $excludeReceiptId);
    }
}