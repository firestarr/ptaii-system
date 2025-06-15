<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    /**
     * Display a listing of bank accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bankAccounts = BankAccount::with('chartOfAccount')
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->get();
        
        return response()->json(['data' => $bankAccounts], 200);
    }

    /**
     * Store a newly created bank account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:100',
            'current_balance' => 'required|numeric',
            'gl_account_id' => 'required|exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Verify that the GL account is of type Asset
        $glAccount = ChartOfAccount::findOrFail($request->gl_account_id);
        if ($glAccount->account_type !== 'Asset') {
            return response()->json([
                'message' => 'The selected GL account must be of type Asset'
            ], 422);
        }

        $bankAccount = BankAccount::create($request->all());

        return response()->json([
            'data' => $bankAccount, 
            'message' => 'Bank account created successfully'
        ], 201);
    }

    /**
     * Display the specified bank account.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $bankAccount = BankAccount::with([
            'chartOfAccount',
            'bankReconciliations' => function($query) {
                $query->orderBy('statement_date', 'desc')->limit(5);
            }
        ])->findOrFail($id);
        
        return response()->json(['data' => $bankAccount], 200);
    }

    /**
     * Update the specified bank account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $bankAccount = BankAccount::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'bank_name' => 'string|max:100',
            'account_number' => 'string|max:50',
            'account_name' => 'string|max:100',
            'current_balance' => 'numeric',
            'gl_account_id' => 'exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Verify that the GL account is of type Asset if provided
        if ($request->has('gl_account_id')) {
            $glAccount = ChartOfAccount::findOrFail($request->gl_account_id);
            if ($glAccount->account_type !== 'Asset') {
                return response()->json([
                    'message' => 'The selected GL account must be of type Asset'
                ], 422);
            }
        }
        
        $bankAccount->update($request->all());

        return response()->json([
            'data' => $bankAccount, 
            'message' => 'Bank account updated successfully'
        ], 200);
    }

    /**
     * Remove the specified bank account from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $bankAccount = BankAccount::findOrFail($id);
        
        // Check if there are reconciliations
        if ($bankAccount->bankReconciliations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete bank account with associated reconciliations'
            ], 422);
        }
        
        $bankAccount->delete();

        return response()->json(['message' => 'Bank account deleted successfully'], 200);
    }
}