<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountingPeriodController extends Controller
{
    /**
     * Display a listing of the accounting periods.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $periods = AccountingPeriod::orderBy('start_date', 'desc')->get();
        
        return response()->json(['data' => $periods], 200);
    }

    /**
     * Store a newly created accounting period in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'period_name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check for overlapping periods
        $exists = AccountingPeriod::where(function($query) use ($request) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
        })->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'The specified date range overlaps with an existing accounting period'
            ], 422);
        }

        $period = AccountingPeriod::create($request->all());

        return response()->json(['data' => $period, 'message' => 'Accounting period created successfully'], 201);
    }

    /**
     * Display the specified accounting period.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $period = AccountingPeriod::findOrFail($id);
        
        return response()->json(['data' => $period], 200);
    }

    /**
     * Update the specified accounting period in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $period = AccountingPeriod::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'period_name' => 'string|max:50',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check for overlapping periods if dates are changed
        if ($request->has('start_date') || $request->has('end_date')) {
            $start = $request->start_date ?? $period->start_date;
            $end = $request->end_date ?? $period->end_date;
            
            $exists = AccountingPeriod::where('period_id', '!=', $id)
                ->where(function($query) use ($start, $end) {
                    $query->whereBetween('start_date', [$start, $end])
                          ->orWhereBetween('end_date', [$start, $end]);
                })->exists();
            
            if ($exists) {
                return response()->json([
                    'message' => 'The specified date range overlaps with an existing accounting period'
                ], 422);
            }
        }

        $period->update($request->all());

        return response()->json(['data' => $period, 'message' => 'Accounting period updated successfully'], 200);
    }

    /**
     * Remove the specified accounting period from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $period = AccountingPeriod::findOrFail($id);
        
        // Check if there are journal entries
        if ($period->journalEntries()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete period with associated journal entries'
            ], 422);
        }
        
        // Check if there are budgets
        if ($period->budgets()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete period with associated budgets'
            ], 422);
        }
        
        // Check if there are asset depreciations
        if ($period->assetDepreciations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete period with associated asset depreciations'
            ], 422);
        }
        
        $period->delete();

        return response()->json(['message' => 'Accounting period deleted successfully'], 200);
    }
    
    /**
     * Get the current active accounting period.
     *
     * @return \Illuminate\Http\Response
     */
    public function current()
    {
        $currentPeriod = AccountingPeriod::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('status', 'Open')
            ->first();
        
        if (!$currentPeriod) {
            return response()->json(['message' => 'No active accounting period found for the current date'], 404);
        }
        
        return response()->json(['data' => $currentPeriod], 200);
    }
}