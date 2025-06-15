<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JournalEntryController extends Controller
{
    /**
     * Display a listing of the journal entries.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = JournalEntry::with('accountingPeriod');
        
        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('entry_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by period
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $journalEntries = $query->orderBy('entry_date', 'desc')
            ->orderBy('journal_number', 'desc')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($journalEntries, 200);
    }

    /**
     * Store a newly created journal entry in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'journal_number' => 'required|string|max:50|unique:JournalEntry',
            'entry_date' => 'required|date',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'status' => 'required|string|max:50',
            'lines' => 'required|array|min:1',
            'lines.*.account_id' => 'required|exists:ChartOfAccount,account_id',
            'lines.*.debit_amount' => 'required_without:lines.*.credit_amount|numeric|min:0',
            'lines.*.credit_amount' => 'required_without:lines.*.debit_amount|numeric|min:0',
            'lines.*.description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Validate total debits = total credits
        $totalDebits = 0;
        $totalCredits = 0;
        foreach ($request->lines as $line) {
            $totalDebits += floatval($line['debit_amount'] ?? 0);
            $totalCredits += floatval($line['credit_amount'] ?? 0);
        }
        
        if (abs($totalDebits - $totalCredits) > 0.001) {
            return response()->json([
                'message' => 'Journal entry is not balanced. Total debits must equal total credits.'
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            // Create journal entry
            $journalEntry = JournalEntry::create([
                'journal_number' => $request->journal_number,
                'entry_date' => $request->entry_date,
                'reference_type' => $request->reference_type,
                'reference_id' => $request->reference_id,
                'description' => $request->description,
                'period_id' => $request->period_id,
                'status' => $request->status
            ]);
            
            // Create journal entry lines
            foreach ($request->lines as $line) {
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $line['account_id'],
                    'debit_amount' => $line['debit_amount'] ?? 0,
                    'credit_amount' => $line['credit_amount'] ?? 0,
                    'description' => $line['description'] ?? null
                ]);
            }
            
            DB::commit();
            
            $journalEntry->load('journalEntryLines.chartOfAccount');
            
            return response()->json([
                'data' => $journalEntry, 
                'message' => 'Journal entry created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create journal entry: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified journal entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $journalEntry = JournalEntry::with([
            'accountingPeriod',
            'journalEntryLines.chartOfAccount'
        ])->findOrFail($id);
        
        return response()->json(['data' => $journalEntry], 200);
    }

    /**
     * Update the specified journal entry in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $journalEntry = JournalEntry::findOrFail($id);
        
        // Don't allow updating posted entries
        if ($journalEntry->status === 'Posted') {
            return response()->json([
                'message' => 'Cannot update a posted journal entry'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'journal_number' => 'string|max:50|unique:JournalEntry,journal_number,' . $id . ',journal_id',
            'entry_date' => 'date',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'period_id' => 'exists:AccountingPeriod,period_id',
            'status' => 'string|max:50',
            'lines' => 'array',
            'lines.*.line_id' => 'nullable|exists:JournalEntryLine,line_id',
            'lines.*.account_id' => 'required|exists:ChartOfAccount,account_id',
            'lines.*.debit_amount' => 'required_without:lines.*.credit_amount|numeric|min:0',
            'lines.*.credit_amount' => 'required_without:lines.*.debit_amount|numeric|min:0',
            'lines.*.description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Validate total debits = total credits if lines are provided
        if ($request->has('lines')) {
            $totalDebits = 0;
            $totalCredits = 0;
            foreach ($request->lines as $line) {
                $totalDebits += floatval($line['debit_amount'] ?? 0);
                $totalCredits += floatval($line['credit_amount'] ?? 0);
            }
            
            if (abs($totalDebits - $totalCredits) > 0.001) {
                return response()->json([
                    'message' => 'Journal entry is not balanced. Total debits must equal total credits.'
                ], 422);
            }
        }

        try {
            DB::beginTransaction();
            
            // Update journal entry
            $journalEntry->update($request->except('lines'));
            
            // Update journal entry lines if provided
            if ($request->has('lines')) {
                // Delete existing lines
                JournalEntryLine::where('journal_id', $journalEntry->journal_id)->delete();
                
                // Create new lines
                foreach ($request->lines as $line) {
                    JournalEntryLine::create([
                        'journal_id' => $journalEntry->journal_id,
                        'account_id' => $line['account_id'],
                        'debit_amount' => $line['debit_amount'] ?? 0,
                        'credit_amount' => $line['credit_amount'] ?? 0,
                        'description' => $line['description'] ?? null
                    ]);
                }
            }
            
            DB::commit();
            
            $journalEntry->load('journalEntryLines.chartOfAccount');
            
            return response()->json([
                'data' => $journalEntry, 
                'message' => 'Journal entry updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update journal entry: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified journal entry from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $journalEntry = JournalEntry::findOrFail($id);
        
        // Don't allow deleting posted entries
        if ($journalEntry->status === 'Posted') {
            return response()->json([
                'message' => 'Cannot delete a posted journal entry'
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete journal entry lines
            JournalEntryLine::where('journal_id', $journalEntry->journal_id)->delete();
            
            // Delete journal entry
            $journalEntry->delete();
            
            DB::commit();
            
            return response()->json(['message' => 'Journal entry deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete journal entry: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Post the specified journal entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function post($id)
    {
        $journalEntry = JournalEntry::findOrFail($id);
        
        if ($journalEntry->status === 'Posted') {
            return response()->json(['message' => 'Journal entry is already posted'], 422);
        }
        
        $journalEntry->status = 'Posted';
        $journalEntry->save();
        
        return response()->json(['message' => 'Journal entry posted successfully'], 200);
    }
}