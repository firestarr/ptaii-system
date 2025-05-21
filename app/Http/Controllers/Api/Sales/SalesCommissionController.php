<?php

namespace App\Http\Controllers\Api\Sales;

use App\Models\Sales\SalesCommission;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SalesCommissionController extends Controller
{
    /**
     * Display a listing of the sales commissions.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $commissions = SalesCommission::with(['salesPerson', 'salesInvoice'])->get();
        return response()->json(['data' => $commissions], 200);
    }

    /**
     * Store a newly created sales commission in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sales_person_id' => 'required|exists:users,id',
            'invoice_id' => 'required|exists:SalesInvoice,invoice_id',
            'commission_amount' => 'required|numeric|min:0',
            'calculation_date' => 'required|date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $commission = SalesCommission::create($request->all());

        return response()->json(['data' => $commission, 'message' => 'Sales commission created successfully'], 201);
    }

    /**
     * Display the specified sales commission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $commission = SalesCommission::with(['salesPerson', 'salesInvoice.customer'])->find($id);
        
        if (!$commission) {
            return response()->json(['message' => 'Sales commission not found'], 404);
        }
        
        return response()->json(['data' => $commission], 200);
    }

    /**
     * Update the specified sales commission in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $commission = SalesCommission::find($id);
        
        if (!$commission) {
            return response()->json(['message' => 'Sales commission not found'], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'sales_person_id' => 'required|exists:users,id',
            'invoice_id' => 'required|exists:SalesInvoice,invoice_id',
            'commission_amount' => 'required|numeric|min:0',
            'calculation_date' => 'required|date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $commission->update($request->all());
        return response()->json(['data' => $commission, 'message' => 'Sales commission updated successfully'], 200);
    }

    /**
     * Remove the specified sales commission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $commission = SalesCommission::find($id);
        
        if (!$commission) {
            return response()->json(['message' => 'Sales commission not found'], 404);
        }
        
        // Check if commission can be deleted (not paid)
        if ($commission->status === 'Paid') {
            return response()->json(['message' => 'Cannot delete a paid commission'], 400);
        }
        
        $commission->delete();
        return response()->json(['message' => 'Sales commission deleted successfully'], 200);
    }

    /**
     * Calculate commissions for all sales in a date range.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function calculateCommissions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'commission_rate' => 'required|numeric|min:0|max:100',
'sales_person_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $query = SalesInvoice::whereBetween('invoice_date', [$request->start_date, $request->end_date])
                ->where('status', 'Paid')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('SalesCommission')
                        ->whereRaw('SalesCommission.invoice_id = SalesInvoice.invoice_id');
                });

            // If a specific sales person is provided
            if ($request->has('sales_person_id')) {
                // This assumes there's a sales_person_id field in the SalesInvoice table
                // If not, you would need to adjust this query based on your actual schema
                $query->where('sales_person_id', $request->sales_person_id);
            }

            $invoices = $query->get();

            $createdCommissions = [];

            foreach ($invoices as $invoice) {
                $commissionAmount = ($invoice->total_amount * $request->commission_rate) / 100;
                
                // This assumes there's a sales_person_id field in the SalesInvoice table
                // If not, you would need to determine the sales person some other way
                $salesPersonId = $request->sales_person_id ?? $invoice->sales_person_id;
                
                $commission = SalesCommission::create([
                    'sales_person_id' => $salesPersonId,
                    'invoice_id' => $invoice->invoice_id,
                    'commission_amount' => $commissionAmount,
                    'calculation_date' => now(),
                    'status' => 'Calculated'
                ]);
                
                $createdCommissions[] = $commission;
            }

            DB::commit();
            
            return response()->json([
                'data' => $createdCommissions, 
                'message' => count($createdCommissions) . ' commissions calculated successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to calculate commissions', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get commissions for a specific sales person.
     *
     * @param  int  $salesPersonId
     * @return \Illuminate\Http\Response
     */
    public function getSalesPersonCommissions($salesPersonId)
    {
        $salesPerson = User::find($salesPersonId);
        
        if (!$salesPerson) {
            return response()->json(['message' => 'Sales person not found'], 404);
        }
        
        $commissions = SalesCommission::with('salesInvoice.customer')
            ->where('sales_person_id', $salesPersonId)
            ->orderBy('calculation_date', 'desc')
            ->get();
        
        $total = $commissions->sum('commission_amount');
        $paid = $commissions->where('status', 'Paid')->sum('commission_amount');
        $pending = $commissions->where('status', 'Calculated')->sum('commission_amount');
        
        return response()->json([
            'data' => [
                'commissions' => $commissions,
                'summary' => [
                    'total_commission' => $total,
                    'paid_commission' => $paid,
                    'pending_commission' => $pending
                ]
            ]
        ], 200);
    }

    /**
     * Mark commissions as paid.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAsPaid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'required|exists:SalesCommission,commission_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $updatedCount = SalesCommission::whereIn('commission_id', $request->commission_ids)
                ->where('status', 'Calculated')
                ->update(['status' => 'Paid']);

            DB::commit();
            
            return response()->json(['message' => $updatedCount . ' commissions marked as paid'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to mark commissions as paid', 'error' => $e->getMessage()], 500);
        }
    }
}