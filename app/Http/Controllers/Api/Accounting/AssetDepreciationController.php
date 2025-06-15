<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\AssetDepreciation;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\ournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssetDepreciationController extends Controller
{
    /**
     * Display a listing of asset depreciations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = AssetDepreciation::with(['fixedAsset', 'accountingPeriod']);
        
        // Filter by asset
        if ($request->has('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }
        
        // Filter by period
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }
        
        $depreciations = $query->orderBy('depreciation_date', 'desc')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($depreciations, 200);
    }

    /**
     * Store a newly created asset depreciation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:FixedAsset,asset_id',
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'depreciation_date' => 'required|date',
            'depreciation_amount' => 'required|numeric|min:0',
            'accumulated_depreciation' => 'required|numeric|min:0',
            'remaining_value' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a depreciation already exists for this asset and period
        $exists = AssetDepreciation::where('asset_id', $request->asset_id)
            ->where('period_id', $request->period_id)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'A depreciation already exists for this asset and period'
            ], 422);
        }
        
        $depreciation = AssetDepreciation::create($request->all());
        
        // Update fixed asset current value
        $asset = FixedAsset::findOrFail($request->asset_id);
        $asset->current_value = $request->remaining_value;
        $asset->save();

        return response()->json([
            'data' => $depreciation, 
            'message' => 'Asset depreciation created successfully'
        ], 201);
    }

    /**
     * Display the specified asset depreciation.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $depreciation = AssetDepreciation::with(['fixedAsset', 'accountingPeriod'])
            ->findOrFail($id);
        
        return response()->json(['data' => $depreciation], 200);
    }

    /**
     * Remove the specified asset depreciation from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $depreciation = AssetDepreciation::findOrFail($id);
        
        // Get the asset
        $asset = FixedAsset::findOrFail($depreciation->asset_id);
        
        // Check if this is the latest depreciation for the asset
        $latestDepreciation = AssetDepreciation::where('asset_id', $asset->asset_id)
            ->orderBy('depreciation_date', 'desc')
            ->first();
        
        if ($latestDepreciation->depreciation_id != $depreciation->depreciation_id) {
            return response()->json([
                'message' => 'Cannot delete depreciation. Only the most recent depreciation can be deleted.'
            ], 422);
        }
        
        // Get the previous depreciation to restore the asset value
        $previousDepreciation = AssetDepreciation::where('asset_id', $asset->asset_id)
            ->where('depreciation_date', '<', $depreciation->depreciation_date)
            ->orderBy('depreciation_date', 'desc')
            ->first();
        
        try {
            DB::beginTransaction();
            
            // Delete the depreciation
            $depreciation->delete();
            
            // Update the asset's current value
            if ($previousDepreciation) {
                $asset->current_value = $previousDepreciation->remaining_value;
            } else {
                // If no previous depreciation, restore to original cost
                $asset->current_value = $asset->acquisition_cost;
            }
            
            $asset->save();
            
            DB::commit();
            
            return response()->json(['message' => 'Asset depreciation deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete asset depreciation: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Calculate depreciation for a fixed asset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function calculateDepreciation(Request $request, $id)
    {
        // Validate request parameters
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'create_journal_entry' => 'boolean',
            'depreciation_expense_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id',
            'accumulated_depreciation_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id'
        ]);
        
        $asset = FixedAsset::findOrFail($id);
        $period = AccountingPeriod::findOrFail($request->period_id);
        
        // Check if depreciation already exists for this asset and period
        $exists = AssetDepreciation::where('asset_id', $asset->asset_id)
            ->where('period_id', $period->period_id)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'A depreciation already exists for this asset and period'
            ], 422);
        }
        
        // Check if asset is active
        if ($asset->status !== 'Active') {
            return response()->json([
                'message' => 'Cannot calculate depreciation for an inactive asset'
            ], 422);
        }
        
        // Calculate depreciation amount
        $depreciationAmount = ($asset->current_value * $asset->depreciation_rate) / 100;
        
        // Round to two decimal places
        $depreciationAmount = round($depreciationAmount, 2);
        
        // Get accumulated depreciation
        $accumulatedDepreciation = AssetDepreciation::where('asset_id', $asset->asset_id)
            ->sum('depreciation_amount') + $depreciationAmount;
        
        // Calculate remaining value
        $remainingValue = $asset->acquisition_cost - $accumulatedDepreciation;
        
        // Ensure remaining value doesn't go below zero
        if ($remainingValue < 0) {
            $remainingValue = 0;
            $depreciationAmount = $asset->current_value;
            $accumulatedDepreciation = $asset->acquisition_cost;
        }
        
        try {
            DB::beginTransaction();
            
            // Create asset depreciation
            $depreciation = AssetDepreciation::create([
                'asset_id' => $asset->asset_id,
                'period_id' => $period->period_id,
                'depreciation_date' => now(),
                'depreciation_amount' => $depreciationAmount,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'remaining_value' => $remainingValue
            ]);
            
            // Update asset current value
            $asset->current_value = $remainingValue;
            $asset->save();
            
            // Create journal entry if requested
            if ($request->input('create_journal_entry', false)) {
                // Validate required account IDs
                if (!$request->has('depreciation_expense_account_id') || !$request->has('accumulated_depreciation_account_id')) {
                    throw new \Exception('Depreciation expense and accumulated depreciation account IDs are required');
                }
                
                // Create journal entry
                $journalEntry = JournalEntry::create([
                    'journal_number' => 'DEPR-' . date('YmdHis'),
                    'entry_date' => now(),
                    'reference_type' => 'AssetDepreciation',
                    'reference_id' => $depreciation->depreciation_id,
                    'description' => 'Depreciation for ' . $asset->name,
                    'period_id' => $period->period_id,
                    'status' => 'Posted'
                ]);
                
                // Create journal entry lines
                // Debit Depreciation Expense
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $request->depreciation_expense_account_id,
                    'debit_amount' => $depreciationAmount,
                    'credit_amount' => 0,
                    'description' => 'Depreciation expense for ' . $asset->name
                ]);
                
                // Credit Accumulated Depreciation
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $request->accumulated_depreciation_account_id,
                    'debit_amount' => 0,
                    'credit_amount' => $depreciationAmount,
                    'description' => 'Accumulated depreciation for ' . $asset->name
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'data' => $depreciation,
                'message' => 'Asset depreciation calculated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to calculate depreciation: ' . $e->getMessage()], 500);
        }
    }
}