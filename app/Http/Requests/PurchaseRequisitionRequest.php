<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequisitionRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic if needed
    }

    public function rules()
    {
        return [
            'pr_date' => 'required|date',
            'requester_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.uom_id' => 'required|exists:unit_of_measures,uom_id',
            'lines.*.required_date' => 'nullable|date',
            'lines.*.notes' => 'nullable|string'
        ];
    }
}