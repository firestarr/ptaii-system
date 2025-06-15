<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    /**
     * Display a listing of fixed assets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = FixedAsset::query();
        
        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $assets = $query->orderBy('name')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($assets, 200);
    }

    /**
     * Store a newly created fixed asset in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_code' => 'required|string|max:50|unique:FixedAsset',
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0',
            'depreciation_rate' => 'required|numeric|min:0|max:100',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = FixedAsset::create([
            'asset_code' => $request->asset_code,
            'name' => $request->name,
            'category' => $request->category,
            'acquisition_date' => $request->acquisition_date,
            'acquisition_cost' => $request->acquisition_cost,
            'current_value' => $request->acquisition_cost, // Initially same as acquisition cost
            'depreciation_rate' => $request->depreciation_rate,
            'status' => $request->status
        ]);

        return response()->json([
            'data' => $asset, 
            'message' => 'Fixed asset created successfully'
        ], 201);
    }

    /**
     * Display the specified fixed asset.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $asset = FixedAsset::with('assetDepreciations')
            ->findOrFail($id);
        
        return response()->json(['data' => $asset], 200);
    }

    /**
     * Update the specified fixed asset in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $asset = FixedAsset::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'asset_code' => 'string|max:50|unique:FixedAsset,asset_code,' . $id . ',asset_id',
            'name' => 'string|max:100',
            'category' => 'string|max:50',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'numeric|min:0',
            'current_value' => 'numeric|min:0',
            'depreciation_rate' => 'numeric|min:0|max:100',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // If there are existing depreciations, don't allow changing certain fields
        if ($asset->assetDepreciations()->count() > 0) {
            $protectedFields = ['acquisition_date', 'acquisition_cost', 'depreciation_rate'];
            
            foreach ($protectedFields as $field) {
                if ($request->has($field) && $request->$field != $asset->$field) {
                    return response()->json([
                        'message' => 'Cannot modify ' . $field . ' when depreciation has already been recorded'
                    ], 422);
                }
            }
        }
        
        $asset->update($request->all());

        return response()->json([
            'data' => $asset, 
            'message' => 'Fixed asset updated successfully'
        ], 200);
    }

    /**
     * Remove the specified fixed asset from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $asset = FixedAsset::findOrFail($id);
        
        // Check if there are depreciations
        if ($asset->assetDepreciations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete asset with recorded depreciation'
            ], 422);
        }
        
        $asset->delete();

        return response()->json(['message' => 'Fixed asset deleted successfully'], 200);
    }
}