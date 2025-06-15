<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the chart of accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $accounts = ChartOfAccount::with('parentAccount')
            ->orderBy('account_code')
            ->get();
            
        return response()->json(['data' => $accounts], 200);
    }

    /**
     * Store a newly created account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_code' => 'required|string|max:50|unique:ChartOfAccount',
            'name' => 'required|string|max:100',
            'account_type' => 'required|string|max:50',
            'is_active' => 'boolean',
            'parent_account_id' => 'nullable|exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $account = ChartOfAccount::create($request->all());

        return response()->json(['data' => $account, 'message' => 'Account created successfully'], 201);
    }

    /**
     * Display the specified account.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $account = ChartOfAccount::with(['parentAccount', 'childAccounts'])
            ->findOrFail($id);
            
        return response()->json(['data' => $account], 200);
    }

    /**
     * Update the specified account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $account = ChartOfAccount::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'account_code' => 'string|max:50|unique:ChartOfAccount,account_code,' . $id . ',account_id',
            'name' => 'string|max:100',
            'account_type' => 'string|max:50',
            'is_active' => 'boolean',
            'parent_account_id' => 'nullable|exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $account->update($request->all());

        return response()->json(['data' => $account, 'message' => 'Account updated successfully'], 200);
    }

    /**
     * Remove the specified account from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $account = ChartOfAccount::findOrFail($id);
        
        // Check if there are child accounts
        if ($account->childAccounts()->count() > 0) {
            return response()->json(['message' => 'Cannot delete account with child accounts'], 422);
        }
        
        // Check if there are journal entries
        if ($account->journalEntryLines()->count() > 0) {
            return response()->json(['message' => 'Cannot delete account with associated journal entries'], 422);
        }
        
        $account->delete();

        return response()->json(['message' => 'Account deleted successfully'], 200);
    }
    
    /**
     * Get a hierarchical structure of the chart of accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function hierarchy()
    {
        // Get root level accounts (accounts with no parent)
        $rootAccounts = ChartOfAccount::whereNull('parent_account_id')
            ->with('childAccounts')
            ->orderBy('account_code')
            ->get();
            
        return response()->json(['data' => $rootAccounts], 200);
    }
}