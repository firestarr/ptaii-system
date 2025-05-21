<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesForecast;
use App\Models\Sales\Customer;
use App\Models\Item;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesForecastController extends Controller
{
    /**
     * Display a listing of the sales forecasts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = SalesForecast::query();
        
        // Tambahkan filter
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        
        if ($request->has('forecast_period')) {
            $query->where('forecast_period', $request->forecast_period);
        }
        
        if ($request->has('forecast_source')) {
            $query->where('forecast_source', $request->forecast_source);
        }
        
        // Secara default hanya ambil versi terbaru
        if (!$request->has('all_versions') || $request->all_versions !== 'true') {
            $query->where('is_current_version', true);
        }
        
        $forecasts = $query->with(['customer', 'item'])->get();
        
        return response()->json(['data' => $forecasts], 200);
    }

    /**
     * Store a newly created sales forecast in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_period' => 'required|date',
            'forecast_quantity' => 'required|numeric|min:0',
            'actual_quantity' => 'nullable|numeric|min:0',
            'variance' => 'nullable|numeric',
            'forecast_source' => 'nullable|string|max:50',
            'confidence_level' => 'nullable|numeric|between:0,1',
            'forecast_issue_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Cek apakah forecast sudah ada
            $existingForecast = SalesForecast::where('item_id', $request->item_id)
                ->where('customer_id', $request->customer_id)
                ->where('forecast_period', $request->forecast_period)
                ->where('is_current_version', true)
                ->first();
                
            // Calculate variance if both forecast and actual quantities are provided
            $data = $request->all();
            if ($request->has('forecast_quantity') && $request->has('actual_quantity')) {
                $data['variance'] = $request->actual_quantity - $request->forecast_quantity;
            }

            // Set default values if not provided
            if (!isset($data['forecast_source'])) {
                $data['forecast_source'] = 'System-Manual';
            }
            
            if (!isset($data['confidence_level'])) {
                $data['confidence_level'] = 0.7;
            }
            
            // Set submission date to now
            $data['submission_date'] = now();
            
            if ($existingForecast) {
                // Nonaktifkan forecast lama
                $existingForecast->is_current_version = false;
                $existingForecast->save();
                
                // Buat forecast baru
                $data['previous_version_id'] = $existingForecast->forecast_id;
            }
            
            $data['is_current_version'] = true;
            
            $forecast = SalesForecast::create($data);
            
            DB::commit();

            return response()->json([
                'data' => $forecast,
                'message' => 'Sales forecast created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified sales forecast.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $forecast = SalesForecast::with(['customer', 'item'])->find($id);
        
        if (!$forecast) {
            return response()->json(['message' => 'Sales forecast not found'], 404);
        }
        
        return response()->json(['data' => $forecast], 200);
    }

    /**
     * Update the specified sales forecast in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $forecast = SalesForecast::find($id);
        
        if (!$forecast) {
            return response()->json(['message' => 'Sales forecast not found'], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_period' => 'required|date',
            'forecast_quantity' => 'required|numeric|min:0',
            'actual_quantity' => 'nullable|numeric|min:0',
            'forecast_source' => 'nullable|string|max:50',
            'confidence_level' => 'nullable|numeric|between:0,1',
            'forecast_issue_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Jika ini adalah versi terbaru, buat versi baru
            if ($forecast->is_current_version) {
                // Nonaktifkan versi lama
                $forecast->is_current_version = false;
                $forecast->save();
                
                // Calculate variance if both forecast and actual quantities are provided
                $data = $request->all();
                if ($request->has('forecast_quantity') && $request->has('actual_quantity')) {
                    $data['variance'] = $request->actual_quantity - $request->forecast_quantity;
                }
                
                // Buat versi baru
                $newForecast = SalesForecast::create([
                    'item_id' => $data['item_id'],
                    'customer_id' => $data['customer_id'],
                    'forecast_period' => $data['forecast_period'],
                    'forecast_quantity' => $data['forecast_quantity'],
                    'actual_quantity' => $data['actual_quantity'] ?? null,
                    'variance' => $data['variance'] ?? null,
                    'forecast_source' => $data['forecast_source'] ?? $forecast->forecast_source,
                    'confidence_level' => $data['confidence_level'] ?? $forecast->confidence_level,
                    'forecast_issue_date' => $data['forecast_issue_date'] ?? $forecast->forecast_issue_date,
                    'submission_date' => now(),
                    'is_current_version' => true,
                    'previous_version_id' => $forecast->forecast_id
                ]);
                
                DB::commit();
                
                return response()->json([
                    'data' => $newForecast,
                    'message' => 'Sales forecast updated successfully with new version'
                ], 200);
            } else {
                // Jika bukan versi terbaru, tidak boleh di-update
                DB::rollBack();
                return response()->json([
                    'message' => 'Cannot update non-current version of forecast'
                ], 422);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified sales forecast from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $forecast = SalesForecast::find($id);
        
        if (!$forecast) {
            return response()->json(['message' => 'Sales forecast not found'], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Jika ini adalah versi terbaru dan memiliki versi sebelumnya
            if ($forecast->is_current_version && $forecast->previous_version_id) {
                // Aktifkan versi sebelumnya
                $previousForecast = SalesForecast::find($forecast->previous_version_id);
                if ($previousForecast) {
                    $previousForecast->is_current_version = true;
                    $previousForecast->save();
                }
            }
            
            $forecast->delete();
            
            DB::commit();
            
            return response()->json(['message' => 'Sales forecast deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store forecasts from customer import.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importCustomerForecasts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'csv_file' => 'required|file|mimes:csv,txt',
            'fill_missing_periods' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $file = $request->file('csv_file');
            $csvData = array_map('str_getcsv', file($file->getPathname()));
            
            // Remove header row
            $headers = array_shift($csvData);
            
            // Normalize header names
            $normalizedHeaders = array_map(function($header) {
                return strtolower(trim($header));
            }, $headers);
            
            // Find required column indexes
            $itemCodeIndex = array_search('item_code', $normalizedHeaders);
            
            // Look for month columns (should be in format YYYY-MM)
            $monthColumns = [];
            foreach ($normalizedHeaders as $index => $header) {
                if (preg_match('/^\d{4}-\d{2}$/', $header)) {
                    $monthColumns[$index] = $header;
                }
            }
            
            if ($itemCodeIndex === false || empty($monthColumns)) {
                return response()->json([
                    'message' => 'CSV file must contain "item_code" column and at least one month column in YYYY-MM format'
                ], 422);
            }
            
            DB::beginTransaction();
            
            $savedCount = 0;
            $errorRows = [];
            
            foreach ($csvData as $rowIndex => $row) {
                $itemCode = trim($row[$itemCodeIndex]);
                
                // Find item by code
                $item = Item::where('item_code', $itemCode)->first();
                
                if (!$item) {
                    $errorRows[] = [
                        'row' => $rowIndex + 2, // +2 because of 0-index and header row
                        'item_code' => $itemCode,
                        'error' => 'Item not found'
                    ];
                    continue;
                }
                
                // Process each month column
                foreach ($monthColumns as $columnIndex => $monthStr) {
                    if (!isset($row[$columnIndex]) || $row[$columnIndex] === '') {
                        continue; // Skip empty values
                    }
                    
                    $quantity = (float) str_replace(',', '', $row[$columnIndex]);
                    
                    if ($quantity < 0) {
                        $errorRows[] = [
                            'row' => $rowIndex + 2,
                            'item_code' => $itemCode,
                            'month' => $monthStr,
                            'error' => 'Negative quantity'
                        ];
                        continue;
                    }
                    
                    // Create forecast period date (first day of month)
                    $forecastPeriod = $monthStr . '-01';
                    
                    // Check if forecast already exists
                    $existingForecast = SalesForecast::where('customer_id', $request->customer_id)
                        ->where('item_id', $item->item_id)
                        ->where('forecast_period', $forecastPeriod)
                        ->where('is_current_version', true)
                        ->first();
                        
                    if ($existingForecast) {
                        // Nonaktifkan forecast lama
                        $existingForecast->is_current_version = false;
                        $existingForecast->save();
                        
                        // Buat forecast baru
                        SalesForecast::create([
                            'customer_id' => $request->customer_id,
                            'item_id' => $item->item_id,
                            'forecast_period' => $forecastPeriod,
                            'forecast_quantity' => $quantity,
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'Customer',
                            'confidence_level' => 0.9,
                            'forecast_issue_date' => $request->forecast_issue_date,
                            'submission_date' => now(),
                            'is_current_version' => true,
                            'previous_version_id' => $existingForecast->forecast_id
                        ]);
                    } else {
                        // Create new forecast
                        SalesForecast::create([
                            'customer_id' => $request->customer_id,
                            'item_id' => $item->item_id,
                            'forecast_period' => $forecastPeriod,
                            'forecast_quantity' => $quantity,
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'Customer',
                            'confidence_level' => 0.9,
                            'forecast_issue_date' => $request->forecast_issue_date,
                            'submission_date' => now(),
                            'is_current_version' => true
                        ]);
                    }
                    
                    $savedCount++;
                }
            }
            
            // Fill missing periods if requested
            if ($request->input('fill_missing_periods', false)) {
                $this->fillMissingPeriods($request->customer_id);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => "Successfully imported {$savedCount} forecast entries",
                'errors' => count($errorRows) > 0 ? $errorRows : null
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to import forecasts', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate sales forecasts based on historical data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateForecasts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_period' => 'required|date',
            'end_period' => 'required|date|after:start_period',
            'customer_id' => 'nullable|exists:Customer,customer_id',
            'item_id' => 'nullable|exists:items,item_id',
            'method' => 'required|in:average,weighted,trend',
            'forecast_issue_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Use provided issue date or default to today
            $issueDate = $request->forecast_issue_date ?? now()->format('Y-m-d');

            // Get historical sales data
            $query = SalesInvoiceLine::join('SalesInvoice', 'SalesInvoiceLine.invoice_id', '=', 'SalesInvoice.invoice_id')
                ->where('SalesInvoice.status', 'Paid')
                ->select(
                    'SalesInvoiceLine.item_id',
                    'SalesInvoice.customer_id',
                    DB::raw('DATE_FORMAT(SalesInvoice.invoice_date, "%Y-%m-01") as period'),
                    DB::raw('SUM(SalesInvoiceLine.quantity) as total_quantity')
                )
                ->groupBy('SalesInvoiceLine.item_id', 'SalesInvoice.customer_id', 'period');

            // Apply filters if provided
            if ($request->has('customer_id')) {
                $query->where('SalesInvoice.customer_id', $request->customer_id);
            }

            if ($request->has('item_id')) {
                $query->where('SalesInvoiceLine.item_id', $request->item_id);
            }

            $historicalData = $query->get();

            // Group data by item and customer
            $groupedData = [];
            foreach ($historicalData as $record) {
                $key = $record->item_id . '-' . $record->customer_id;
                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'item_id' => $record->item_id,
                        'customer_id' => $record->customer_id,
                        'periods' => []
                    ];
                }
                $groupedData[$key]['periods'][$record->period] = $record->total_quantity;
            }

            $forecasts = [];

            // Generate forecasts for each item-customer pair
            foreach ($groupedData as $data) {
                $periods = $data['periods'];
                
                // Get start and end dates for forecasting
                $startDate = new \DateTime($request->start_period);
                $endDate = new \DateTime($request->end_period);
                $interval = new \DateInterval('P1M'); // 1 month interval
                
                $forecastPeriod = $startDate;
                
                while ($forecastPeriod <= $endDate) {
                    $periodKey = $forecastPeriod->format('Y-m-01');
                    
                    // Skip if there's already an existing forecast from customer
                    $existingForecast = SalesForecast::where('item_id', $data['item_id'])
                        ->where('customer_id', $data['customer_id'])
                        ->where('forecast_period', $periodKey)
                        ->where('is_current_version', true)
                        ->where('forecast_source', 'Customer')
                        ->first();
                    
                    // Only create if no customer-provided forecast exists
                    if (!$existingForecast) {
                        // Calculate forecast based on the selected method
                        $forecastQuantity = 0;
                        $confidenceLevel = 0.7; // Default confidence
                        $forecastSource = 'System-' . ucfirst($request->method);
                        
                        switch ($request->method) {
                            case 'average':
                                // Simple average of historical data
                                if (count($periods) > 0) {
                                    $forecastQuantity = array_sum($periods) / count($periods);
                                    $confidenceLevel = 0.6; // Lowest confidence for simple average
                                }
                                break;
                                
                            case 'weighted':
                                // Weighted average with more recent months having higher weights
                                $totalWeight = 0;
                                $weightedSum = 0;
                                $weight = 1;
                                
                                // Sort periods by date (oldest first)
                                ksort($periods);
                                
                                foreach ($periods as $period => $quantity) {
                                    $weightedSum += $quantity * $weight;
                                    $totalWeight += $weight;
                                    $weight++; // Increase weight for more recent periods
                                }
                                
                                if ($totalWeight > 0) {
                                    $forecastQuantity = $weightedSum / $totalWeight;
                                    $confidenceLevel = 0.7; // Medium confidence for weighted
                                }
                                break;
                                
                            case 'trend':
                                // Linear trend based on historical data
                                if (count($periods) >= 2) {
                                    // Sort periods by date
                                    ksort($periods);
                                    
                                    $x = [];
                                    $y = [];
                                    $i = 1;
                                    
                                    foreach ($periods as $period => $quantity) {
                                        $x[] = $i;
                                        $y[] = $quantity;
                                        $i++;
                                    }
                                    
                                    // Calculate linear regression
                                    $n = count($x);
                                    $sumX = array_sum($x);
                                    $sumY = array_sum($y);
                                    $sumXY = 0;
                                    $sumXX = 0;
                                    
                                    for ($j = 0; $j < $n; $j++) {
                                        $sumXY += ($x[$j] * $y[$j]);
                                        $sumXX += ($x[$j] * $x[$j]);
                                    }
                                    
                                    // Check for division by zero
                                    $divisor = ($n * $sumXX - $sumX * $sumX);
                                    if ($divisor != 0) {
                                        $slope = ($n * $sumXY - $sumX * $sumY) / $divisor;
                                        $intercept = ($sumY - $slope * $sumX) / $n;
                                        
                                        // Predict next value
                                        $forecastQuantity = $intercept + $slope * ($n + 1);
                                        $confidenceLevel = 0.75; // Higher confidence for trend-based
                                    }
                                }
                                break;
                        }
                        
                        // Ensure forecast quantity is not negative
                        $forecastQuantity = max(0, $forecastQuantity);
                        
                        // Check for an existing system-generated forecast
                        $existingSystemForecast = SalesForecast::where('item_id', $data['item_id'])
                            ->where('customer_id', $data['customer_id'])
                            ->where('forecast_period', $periodKey)
                            ->where('is_current_version', true)
                            ->whereNotIn('forecast_source', ['Customer'])
                            ->first();
                        
                        if ($existingSystemForecast) {
                            // Nonaktifkan forecast lama
                            $existingSystemForecast->is_current_version = false;
                            $existingSystemForecast->save();
                            
                            // Buat forecast baru
                            $forecast = SalesForecast::create([
                                'item_id' => $data['item_id'],
                                'customer_id' => $data['customer_id'],
                                'forecast_period' => $periodKey,
                                'forecast_quantity' => round($forecastQuantity, 2),
                                'actual_quantity' => null,
                                'variance' => null,
                                'forecast_source' => $forecastSource,
                                'confidence_level' => $confidenceLevel,
                                'forecast_issue_date' => $issueDate,
                                'submission_date' => now(),
                                'is_current_version' => true,
                                'previous_version_id' => $existingSystemForecast->forecast_id
                            ]);
                        } else {
                            // Create new forecast
                            $forecast = SalesForecast::create([
                                'item_id' => $data['item_id'],
                                'customer_id' => $data['customer_id'],
                                'forecast_period' => $periodKey,
                                'forecast_quantity' => round($forecastQuantity, 2),
                                'actual_quantity' => null,
                                'variance' => null,
                                'forecast_source' => $forecastSource,
                                'confidence_level' => $confidenceLevel,
                                'forecast_issue_date' => $issueDate,
                                'submission_date' => now(),
                                'is_current_version' => true
                            ]);
                        }
                        
                        $forecasts[] = $forecast;
                    }
                    
                    // Move to next period
                    $forecastPeriod->add($interval);
                }
            }

            DB::commit();
            
            return response()->json([
                'data' => $forecasts, 
                'message' => count($forecasts) . ' forecasts generated successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to generate forecasts', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update actual quantities in forecasts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateActuals(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'end_period' => 'required|date',
            'update_all' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get completed period forecasts
            $query = SalesForecast::where('forecast_period', '<=', $request->end_period)
                ->where('is_current_version', true);
            
            if (!$request->update_all) {
                // Only update forecasts with null actual_quantity
                $query->whereNull('actual_quantity');
            }
            
            $forecasts = $query->get();
            $updatedCount = 0;
            
            foreach ($forecasts as $forecast) {
                // Get actual sales quantities for the period
                $startOfMonth = new \DateTime($forecast->forecast_period);
                $endOfMonth = clone $startOfMonth;
                $endOfMonth->modify('last day of this month');
                
                $actualQuantity = SalesInvoiceLine::join('SalesInvoice', 'SalesInvoiceLine.invoice_id', '=', 'SalesInvoice.invoice_id')
                    ->where('SalesInvoiceLine.item_id', $forecast->item_id)
                    ->where('SalesInvoice.customer_id', $forecast->customer_id)
                    ->whereBetween('SalesInvoice.invoice_date', [
                        $startOfMonth->format('Y-m-d'),
                        $endOfMonth->format('Y-m-d')
                    ])
                    ->where('SalesInvoice.status', 'Paid')
                    ->sum('SalesInvoiceLine.quantity');
                
                // Create a new version with actuals
                $newForecast = SalesForecast::create([
                    'item_id' => $forecast->item_id,
                    'customer_id' => $forecast->customer_id,
                    'forecast_period' => $forecast->forecast_period,
                    'forecast_quantity' => $forecast->forecast_quantity,
                    'actual_quantity' => $actualQuantity,
                    'variance' => $actualQuantity - $forecast->forecast_quantity,
                    'forecast_source' => $forecast->forecast_source,
                    'confidence_level' => $forecast->confidence_level,
                    'forecast_issue_date' => $forecast->forecast_issue_date,
                    'submission_date' => now(),
                    'is_current_version' => true,
                    'previous_version_id' => $forecast->forecast_id
                ]);
                
                // Nonaktifkan forecast lama
                $forecast->is_current_version = false;
                $forecast->save();
                
                $updatedCount++;
            }

            DB::commit();
            
            return response()->json(['message' => $updatedCount . ' forecasts updated with actual quantities'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update actual quantities', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get forecast accuracy metrics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getForecastAccuracy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:Customer,customer_id',
            'item_id' => 'nullable|exists:items,item_id',
            'start_period' => 'required|date',
            'end_period' => 'required|date|after:start_period',
            'forecast_source' => 'nullable|string',
            'issue_date_start' => 'nullable|date',
            'issue_date_end' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Get forecasts with actual quantities
            $query = SalesForecast::whereBetween('forecast_period', [$request->start_period, $request->end_period])
                ->whereNotNull('actual_quantity')
                ->where('is_current_version', true);
            
            // Apply filters if provided
            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->has('item_id')) {
                $query->where('item_id', $request->item_id);
            }
            
            if ($request->has('forecast_source')) {
                $query->where('forecast_source', $request->forecast_source);
            }
            
            // Filter by issue date if provided
            if ($request->has('issue_date_start') && $request->has('issue_date_end')) {
                $query->whereBetween('forecast_issue_date', [$request->issue_date_start, $request->issue_date_end]);
            } else if ($request->has('issue_date_start')) {
                $query->where('forecast_issue_date', '>=', $request->issue_date_start);
            } else if ($request->has('issue_date_end')) {
                $query->where('forecast_issue_date', '<=', $request->issue_date_end);
            }
            
            $forecasts = $query->get();
            
            // Calculate accuracy metrics
            $totalForecasts = count($forecasts);
            
            if ($totalForecasts == 0) {
                return response()->json([
                    'message' => 'No forecasts found with actual quantities for the specified criteria'
                ], 404);
            }
            
            $totalForecastQuantity = $forecasts->sum('forecast_quantity');
            $totalActualQuantity = $forecasts->sum('actual_quantity');
            $totalAbsVariance = $forecasts->sum(function($forecast) {
                return abs($forecast->variance);
            });
            
            $mape = 0; // Mean Absolute Percentage Error
            $validForecastsForMAPE = 0;
            
            foreach ($forecasts as $forecast) {
                if ($forecast->actual_quantity > 0) {
                    $mape += abs($forecast->variance / $forecast->actual_quantity) * 100;
                    $validForecastsForMAPE++;
                }
            }
            
            if ($validForecastsForMAPE > 0) {
                $mape = $mape / $validForecastsForMAPE;
            }
            
            $bias = $totalForecastQuantity > 0 ? 
                (($totalActualQuantity - $totalForecastQuantity) / $totalForecastQuantity) * 100 : 0;
            
            $mad = $totalForecasts > 0 ? $totalAbsVariance / $totalForecasts : 0; // Mean Absolute Deviation
            
            // Group metrics by source
            $sourceMetrics = [];
            
            if (!$request->has('forecast_source')) {
                $bySource = $forecasts->groupBy('forecast_source');
                
                foreach ($bySource as $source => $sourceForecasts) {
                    $sourceTotal = count($sourceForecasts);
                    $sourceForecastQty = $sourceForecasts->sum('forecast_quantity');
                    $sourceActualQty = $sourceForecasts->sum('actual_quantity');
                    $sourceAbsVar = $sourceForecasts->sum(function($f) {
                        return abs($f->variance);
                    });
                    
                    $sourceMape = 0;
                    $validForMape = 0;
                    
                    foreach ($sourceForecasts as $f) {
                        if ($f->actual_quantity > 0) {
                            $sourceMape += abs($f->variance / $f->actual_quantity) * 100;
                            $validForMape++;
                        }
                    }
                    
                    if ($validForMape > 0) {
                        $sourceMape = $sourceMape / $validForMape;
                    }
                    
                    $sourceBias = $sourceForecastQty > 0 ? 
                        (($sourceActualQty - $sourceForecastQty) / $sourceForecastQty) * 100 : 0;
                    
                    $sourceMad = $sourceTotal > 0 ? $sourceAbsVar / $sourceTotal : 0;
                    
                    $sourceMetrics[$source] = [
                        'count' => $sourceTotal,
                        'mape' => round($sourceMape, 2),
                        'bias' => round($sourceBias, 2),
                        'mad' => round($sourceMad, 2)
                    ];
                }
            }
            
            // Group metrics by issue date
            $issueMetrics = [];
            
            if ($request->has('issue_date_start') || $request->has('issue_date_end')) {
                $byIssueDate = $forecasts->groupBy(function($forecast) {
                    return $forecast->forecast_issue_date->format('Y-m-d');
                });
                
                foreach ($byIssueDate as $issueDate => $issueDateForecasts) {
                    $issueTotal = count($issueDateForecasts);
                    $issueForecastQty = $issueDateForecasts->sum('forecast_quantity');
                    $issueActualQty = $issueDateForecasts->sum('actual_quantity');
                    $issueAbsVar = $issueDateForecasts->sum(function($f) {
                        return abs($f->variance);
                    });
                    
                    $issueMape = 0;
                    $validForMape = 0;
                    
                    foreach ($issueDateForecasts as $f) {
                        if ($f->actual_quantity > 0) {
                            $issueMape += abs($f->variance / $f->actual_quantity) * 100;
                            $validForMape++;
                        }
                    }
                    
                    if ($validForMape > 0) {
                        $issueMape = $issueMape / $validForMape;
                    }
                    
                    $issueBias = $issueForecastQty > 0 ? 
                        (($issueActualQty - $issueForecastQty) / $issueForecastQty) * 100 : 0;
                    
                    $issueMad = $issueTotal > 0 ? $issueAbsVar / $issueTotal : 0;
                    
                    $issueMetrics[$issueDate] = [
                        'count' => $issueTotal,
                        'mape' => round($issueMape, 2),
                        'bias' => round($issueBias, 2),
                        'mad' => round($issueMad, 2)
                    ];
                }
            }
            
            return response()->json([
                'data' => [
                    'total_forecasts' => $totalForecasts,
                    'total_forecast_quantity' => $totalForecastQuantity,
                    'total_actual_quantity' => $totalActualQuantity,
                    'mean_absolute_percentage_error' => round($mape, 2),
                    'bias_percentage' => round($bias, 2),
                    'mean_absolute_deviation' => round($mad, 2),
                    'by_source' => $sourceMetrics,
                    'by_issue_date' => $issueMetrics,
                    'forecasts' => $forecasts
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to get forecast accuracy', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get consolidated 6-month forecast view
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response  
     */
    public function getConsolidatedForecast(Request $request)
    {
       $validator = Validator::make($request->all(), [
           'start_month' => 'required|date',
           'customer_id' => 'nullable|exists:Customer,customer_id',
           'item_id' => 'nullable|exists:items,item_id',
           'issue_date' => 'nullable|date'
       ]);

       if ($validator->fails()) {
           return response()->json(['errors' => $validator->errors()], 422);
       }

       $startMonth = Carbon::parse($request->start_month)->startOfMonth();
       $endMonth = $startMonth->copy()->addMonths(5);
       
       $query = SalesForecast::whereBetween('forecast_period', [
           $startMonth->format('Y-m-d'), 
           $endMonth->format('Y-m-d')
       ])
       ->where('is_current_version', true);
       
       if ($request->has('customer_id')) {
           $query->where('customer_id', $request->customer_id);
       }
       
       if ($request->has('item_id')) {
           $query->where('item_id', $request->item_id);
       }
       
       if ($request->has('issue_date')) {
           $query->where('forecast_issue_date', $request->issue_date);
       }
       
       $forecasts = $query->with(['customer', 'item'])
           ->orderBy('customer_id')
           ->orderBy('item_id')
           ->orderBy('forecast_period')
           ->get();
       
       // Organize data by customer, item, and period
       $result = [];
       foreach ($forecasts as $forecast) {
           $customerId = $forecast->customer_id;
           $itemId = $forecast->item_id;
           $period = Carbon::parse($forecast->forecast_period)->format('Y-m');
           
           if (!isset($result[$customerId])) {
               $result[$customerId] = [
                   'customer_id' => $customerId,
                   'customer_name' => $forecast->customer->name,
                   'items' => []
               ];
           }
           
           if (!isset($result[$customerId]['items'][$itemId])) {
               $result[$customerId]['items'][$itemId] = [
                   'item_id' => $itemId,
                   'item_code' => $forecast->item->item_code,
                   'item_name' => $forecast->item->name,
                   'periods' => []
               ];
           }
           
           $result[$customerId]['items'][$itemId]['periods'][$period] = [
               'forecast_quantity' => $forecast->forecast_quantity,
               'actual_quantity' => $forecast->actual_quantity,
               'source' => $forecast->forecast_source,
               'confidence' => $forecast->confidence_level,
               'forecast_issue_date' => $forecast->forecast_issue_date ? Carbon::parse($forecast->forecast_issue_date)->format('Y-m-d') : null,
               'submission_date' => $forecast->submission_date ? Carbon::parse($forecast->submission_date)->format('Y-m-d') : null
           ];
       }
       
       // Create a full 6-month grid for each customer-item
       $monthsToShow = [];
       for ($i = 0; $i < 6; $i++) {
           $monthKey = $startMonth->copy()->addMonths($i)->format('Y-m');
           $monthsToShow[] = $monthKey;
       }
       
       // Ensure all months are present for each item
       foreach ($result as &$customer) {
           foreach ($customer['items'] as &$item) {
               foreach ($monthsToShow as $month) {
                   if (!isset($item['periods'][$month])) {
                       $item['periods'][$month] = [
                           'forecast_quantity' => 0,
                           'actual_quantity' => null,
                           'source' => null,
                           'confidence' => 0,
                           'forecast_issue_date' => null,
                           'submission_date' => null
                       ];
                   }
               }
               
               // Sort periods by date
               ksort($item['periods']);
               
               // Calculate totals for this item
               $item['total_forecast'] = array_sum(array_column($item['periods'], 'forecast_quantity'));
               
               // Convert periods to array for easier JSON serialization
               $periodsArray = [];
               foreach ($item['periods'] as $periodKey => $periodData) {
                   $periodsArray[] = array_merge(['period' => $periodKey], $periodData);
               }
               $item['periods'] = $periodsArray;
           }
           
           // Convert items associative array to indexed array
           $customer['items'] = array_values($customer['items']);
       }
       
       // Convert customers associative array to indexed array
       $result = array_values($result);
       
       return response()->json([
           'data' => $result,
           'period_range' => [
               'start' => $startMonth->format('Y-m-d'),
               'end' => $endMonth->format('Y-m-d'),
               'months' => $monthsToShow
           ]
       ], 200);
    }

    /**
     * Get forecast history for a specific item, customer, and period
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getForecastHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_period' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $history = SalesForecast::where('item_id', $request->item_id)
            ->where('customer_id', $request->customer_id)
            ->where('forecast_period', $request->forecast_period)
            ->with(['customer', 'item'])
            ->orderBy('forecast_issue_date', 'desc')
            ->orderBy('submission_date', 'desc')
            ->get();
            
        if ($history->isEmpty()) {
            return response()->json([
                'message' => 'No forecast history found for the specified criteria'
            ], 404);
        }
            
        return response()->json([
            'data' => $history,
            'message' => count($history) . ' forecast versions found'
        ], 200);
    }
    
    /**
     * Fill missing forecast periods for a customer
     *
     * @param  int  $customerId
     * @param  string  $method
     * @return void
     */
    private function fillMissingPeriods($customerId, $method = 'trend')
    {
        // Get the latest forecast period for this customer
        $latestForecast = SalesForecast::where('customer_id', $customerId)
            ->where('is_current_version', true)
            ->orderBy('forecast_period', 'desc')
            ->first();
        
        if (!$latestForecast) {
            return; // No forecasts yet
        }
        
        // Determine the target period (6 months from now)
        $targetPeriod = Carbon::now()->addMonths(6)->startOfMonth();
        $latestPeriod = Carbon::parse($latestForecast->forecast_period);
        
        // If latest period covers more than 6 months, we're good
        if ($latestPeriod->gte($targetPeriod)) {
            return;
        }
        
        // Get all items that customer has previously purchased or has forecast for
        $customerItems = SalesForecast::where('customer_id', $customerId)
            ->where('is_current_version', true)
            ->distinct()
            ->pluck('item_id')
            ->toArray();
            
        $purchasedItems = DB::table('SalesInvoiceLine')
            ->join('SalesInvoice', 'SalesInvoiceLine.invoice_id', '=', 'SalesInvoice.invoice_id')
            ->where('SalesInvoice.customer_id', $customerId)
            ->distinct()
            ->pluck('SalesInvoiceLine.item_id')
            ->toArray();
            
        $allItems = array_unique(array_merge($customerItems, $purchasedItems));
        
        // For each item, generate forecasts for missing periods
        foreach ($allItems as $itemId) {
            $itemLatestForecast = SalesForecast::where('customer_id', $customerId)
                ->where('item_id', $itemId)
                ->where('is_current_version', true)
                ->orderBy('forecast_period', 'desc')
                ->first();
            
            if (!$itemLatestForecast) {
                // If no forecast exists, create one based on historical data
                $this->createInitialForecast($customerId, $itemId, $method);
                continue;
            }
            
            $itemLatestPeriod = Carbon::parse($itemLatestForecast->forecast_period);
            $nextPeriod = $itemLatestPeriod->copy()->addMonth()->startOfMonth();
            
            // Generate forecasts from next period until target period
            while ($nextPeriod->lte($targetPeriod)) {
                // Check if forecast already exists
                $existingForecast = SalesForecast::where('customer_id', $customerId)
                    ->where('item_id', $itemId)
                    ->where('forecast_period', $nextPeriod->format('Y-m-d'))
                    ->where('is_current_version', true)
                    ->first();
                    
                if (!$existingForecast) {
                    // Calculate forecast using selected method
                    $forecastData = $this->calculateMethodForecast($customerId, $itemId, $nextPeriod->format('Y-m-d'), $method);
                    
                    SalesForecast::create([
                        'customer_id' => $customerId,
                        'item_id' => $itemId,
                        'forecast_period' => $nextPeriod->format('Y-m-d'),
                        'forecast_quantity' => $forecastData['quantity'],
                        'actual_quantity' => null,
                        'variance' => null,
                        'forecast_source' => 'System-' . ucfirst($method),
                        'confidence_level' => $forecastData['confidence'],
                        'forecast_issue_date' => now(),
                        'submission_date' => now(),
                        'is_current_version' => true
                    ]);
                }
                
                $nextPeriod->addMonth();
            }
        }
    }

    /**
     * Create initial forecast for an item with no previous forecasts
     *
     * @param  int  $customerId
     * @param  int  $itemId
     * @param  string  $method
     * @return void
     */
    private function createInitialForecast($customerId, $itemId, $method)
    {
        // Get historical sales data
        $historicalData = SalesInvoiceLine::join('SalesInvoice', 'SalesInvoiceLine.invoice_id', '=', 'SalesInvoice.invoice_id')
            ->where('SalesInvoice.customer_id', $customerId)
            ->where('SalesInvoiceLine.item_id', $itemId)
            ->where('SalesInvoice.status', 'Paid')
            ->select(
                DB::raw('DATE_FORMAT(SalesInvoice.invoice_date, "%Y-%m-01") as period'),
                DB::raw('SUM(SalesInvoiceLine.quantity) as total_quantity')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period')
            ->map(function ($item) {
                return $item->total_quantity;
            })
            ->toArray();
        
        if (empty($historicalData)) {
            return; // No historical data to base forecast on
        }
        
        // Generate forecasts for next 6 months
        $startPeriod = Carbon::now()->startOfMonth();
        $targetPeriod = Carbon::now()->addMonths(6)->startOfMonth();
        
        $currentPeriod = $startPeriod->copy();
        
        while ($currentPeriod->lte($targetPeriod)) {
            $periodKey = $currentPeriod->format('Y-m-d');
            
            // Check if forecast already exists
            $existingForecast = SalesForecast::where('customer_id', $customerId)
                ->where('item_id', $itemId)
                ->where('forecast_period', $periodKey)
                ->where('is_current_version', true)
                ->first();
                
            if (!$existingForecast) {
                $forecastData = $this->calculateMethodForecast(
                    $customerId, 
                    $itemId, 
                    $periodKey, 
                    $method, 
                    $historicalData
                );
                
                SalesForecast::create([
                    'customer_id' => $customerId,
                    'item_id' => $itemId,
                    'forecast_period' => $periodKey,
                    'forecast_quantity' => $forecastData['quantity'],
                    'actual_quantity' => null,
                    'variance' => null,
                    'forecast_source' => 'System-' . ucfirst($method),
                    'confidence_level' => $forecastData['confidence'],
                    'forecast_issue_date' => now(),
                    'submission_date' => now(),
                    'is_current_version' => true
                ]);
            }
            
            $currentPeriod->addMonth();
        }
    }

    /**
    * Calculate forecast based on selected method
    *
    * @param  int  $customerId
    * @param  int  $itemId
    * @param  string  $forecastPeriod
    * @param  string  $method
    * @param  array  $historicalData
    * @return array
    */
   private function calculateMethodForecast($customerId, $itemId, $forecastPeriod, $method = 'trend', $historicalData = null)
   {
       // Get historical data if not provided
       if ($historicalData === null) {
           $historicalData = $this->getHistoricalData($customerId, $itemId, $forecastPeriod);
       }
       
       $forecastQuantity = 0;
       $confidenceLevel = 0.6; // Default confidence
       
       // If we have no historical data, return zero forecast
       if (empty($historicalData)) {
           return [
               'quantity' => 0,
               'confidence' => 0.5
           ];
       }
       
       switch ($method) {
           case 'average':
               // Simple average of historical data
               $forecastQuantity = array_sum($historicalData) / count($historicalData);
               $confidenceLevel = 0.6;
               break;
               
           case 'weighted':
               // Weighted average with more recent months having higher weights
               $totalWeight = 0;
               $weightedSum = 0;
               $weight = 1;
               
               // Sort periods by date (oldest first)
               ksort($historicalData);
               
               foreach ($historicalData as $quantity) {
                   $weightedSum += $quantity * $weight;
                   $totalWeight += $weight;
                   $weight++; // Increase weight for more recent periods
               }
               
               if ($totalWeight > 0) {
                   $forecastQuantity = $weightedSum / $totalWeight;
                   $confidenceLevel = 0.7;
               }
               break;
               
           case 'trend':
               // Linear trend based on historical data
               if (count($historicalData) >= 2) {
                   // Sort periods by date
                   ksort($historicalData);
                   
                   $x = [];
                   $y = [];
                   $i = 1;
                   
                   foreach ($historicalData as $quantity) {
                       $x[] = $i;
                       $y[] = $quantity;
                       $i++;
                   }
                   
                   // Calculate linear regression
                   $n = count($x);
                   $sumX = array_sum($x);
                   $sumY = array_sum($y);
                   $sumXY = 0;
                   $sumXX = 0;
                   
                   for ($j = 0; $j < $n; $j++) {
                       $sumXY += ($x[$j] * $y[$j]);
                       $sumXX += ($x[$j] * $x[$j]);
                   }
                   
                   // Check for division by zero
                   $divisor = ($n * $sumXX - $sumX * $sumX);
                   if ($divisor != 0) {
                       $slope = ($n * $sumXY - $sumX * $sumY) / $divisor;
                       $intercept = ($sumY - $slope * $sumX) / $n;
                       
                       // Predict next value
                       $forecastQuantity = $intercept + $slope * ($n + 1);
                       $confidenceLevel = 0.75;
                   } else {
                       // Fallback to average if trend calculation fails
                       $forecastQuantity = array_sum($y) / count($y);
                   }
               } else {
                   // Not enough data for trend, use average
                   $forecastQuantity = array_sum($historicalData) / count($historicalData);
               }
               break;
               
           default:
               // Default to average
               $forecastQuantity = array_sum($historicalData) / count($historicalData);
       }
       
       // Ensure forecast quantity is not negative
       $forecastQuantity = max(0, round($forecastQuantity, 2));
       
       return [
           'quantity' => $forecastQuantity,
           'confidence' => $confidenceLevel
       ];
   }
   
   /**
    * Get historical sales data for a customer-item pair
    *
    * @param  int  $customerId
    * @param  int  $itemId
    * @param  string  $beforeDate
    * @return array
    */
   private function getHistoricalData($customerId, $itemId, $beforeDate)
   {
       // Get sales from last 12 months
       $beforePeriod = Carbon::parse($beforeDate)->startOfMonth();
       $fromPeriod = $beforePeriod->copy()->subMonths(12);
       
       $historicalSales = SalesInvoiceLine::join('SalesInvoice', 'SalesInvoiceLine.invoice_id', '=', 'SalesInvoice.invoice_id')
           ->where('SalesInvoice.customer_id', $customerId)
           ->where('SalesInvoiceLine.item_id', $itemId)
           ->whereBetween('SalesInvoice.invoice_date', [
               $fromPeriod->format('Y-m-d'),
               $beforePeriod->format('Y-m-d')
           ])
           ->where('SalesInvoice.status', 'Paid')
           ->select(
               DB::raw('DATE_FORMAT(SalesInvoice.invoice_date, "%Y-%m-01") as period'),
               DB::raw('SUM(SalesInvoiceLine.quantity) as total_quantity')
           )
           ->groupBy('period')
           ->orderBy('period')
           ->get()
           ->keyBy('period')
           ->map(function ($item) {
               return $item->total_quantity;
           })
           ->toArray();
           
       // Also get forecasts with actual quantities
       $pastForecasts = SalesForecast::where('customer_id', $customerId)
           ->where('item_id', $itemId)
           ->whereBetween('forecast_period', [
               $fromPeriod->format('Y-m-d'),
               $beforePeriod->format('Y-m-d')
           ])
           ->where('is_current_version', true)
           ->whereNotNull('actual_quantity')
           ->get()
           ->keyBy(function ($item) {
               return Carbon::parse($item->forecast_period)->format('Y-m-d');
           })
           ->map(function ($item) {
               return $item->actual_quantity;
           })
           ->toArray();
           
       // Merge and prioritize actual quantities from forecasts
       return array_merge($historicalSales, $pastForecasts);
   }
}