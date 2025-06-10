<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesForecast;
use App\Models\Sales\Customer;
use App\Models\Item;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SOLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SalesForecastController extends Controller
{
    /**
     * Display a listing of sales forecasts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = SalesForecast::with(['customer', 'item'])
            ->where('is_current_version', true);

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by item
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        // Filter by period
        if ($request->has('period_from')) {
            $query->where('forecast_period', '>=', $request->period_from);
        }

        if ($request->has('period_to')) {
            $query->where('forecast_period', '<=', $request->period_to);
        }

        // Filter by source
        if ($request->has('forecast_source')) {
            $query->where('forecast_source', $request->forecast_source);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'forecast_period');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $forecasts = $query->paginate($perPage);

        return response()->json([
            'data' => $forecasts,
            'message' => 'Sales forecasts retrieved successfully'
        ], 200);
    }

    /**
     * Store a newly created sales forecast.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'item_id' => 'required|exists:Item,item_id',
            'forecast_period' => 'required|date',
            'forecast_quantity' => 'required|numeric|min:0',
            'forecast_source' => 'nullable|string|max:50',
            'confidence_level' => 'nullable|numeric|between:0,1',
            'forecast_issue_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Check if forecast already exists for this period
            $existingForecast = SalesForecast::where('customer_id', $request->customer_id)
                ->where('item_id', $request->item_id)
                ->where('forecast_period', $request->forecast_period)
                ->where('is_current_version', true)
                ->first();

            if ($existingForecast) {
                // Deactivate existing forecast
                $existingForecast->is_current_version = false;
                $existingForecast->save();

                // Create new version
                $forecast = SalesForecast::create([
                    'customer_id' => $request->customer_id,
                    'item_id' => $request->item_id,
                    'forecast_period' => $request->forecast_period,
                    'forecast_quantity' => $request->forecast_quantity,
                    'actual_quantity' => null,
                    'variance' => null,
                    'forecast_source' => $request->forecast_source ?? 'Manual',
                    'confidence_level' => $request->confidence_level ?? 1.0,
                    'forecast_issue_date' => $request->forecast_issue_date,
                    'submission_date' => now(),
                    'is_current_version' => true,
                    'previous_version_id' => $existingForecast->forecast_id
                ]);
            } else {
                // Create new forecast
                $forecast = SalesForecast::create([
                    'customer_id' => $request->customer_id,
                    'item_id' => $request->item_id,
                    'forecast_period' => $request->forecast_period,
                    'forecast_quantity' => $request->forecast_quantity,
                    'actual_quantity' => null,
                    'variance' => null,
                    'forecast_source' => $request->forecast_source ?? 'Manual',
                    'confidence_level' => $request->confidence_level ?? 1.0,
                    'forecast_issue_date' => $request->forecast_issue_date,
                    'submission_date' => now(),
                    'is_current_version' => true
                ]);
            }

            DB::commit();

            return response()->json([
                'data' => $forecast->load(['customer', 'item']),
                'message' => 'Sales forecast created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create sales forecast',
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

        return response()->json([
            'data' => $forecast,
            'message' => 'Sales forecast retrieved successfully'
        ], 200);
    }

    /**
     * Update the specified sales forecast.
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
            'forecast_quantity' => 'sometimes|required|numeric|min:0',
            'actual_quantity' => 'nullable|numeric|min:0',
            'forecast_source' => 'nullable|string|max:50',
            'confidence_level' => 'nullable|numeric|between:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Calculate variance if actual quantity is provided
            if ($request->has('actual_quantity') && $request->actual_quantity !== null) {
                $variance = $request->actual_quantity - $forecast->forecast_quantity;
                $forecast->variance = $variance;
            }

            $forecast->update($request->only([
                'forecast_quantity', 'actual_quantity', 'forecast_source', 'confidence_level'
            ]));

            return response()->json([
                'data' => $forecast->load(['customer', 'item']),
                'message' => 'Sales forecast updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update sales forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified sales forecast.
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
            $forecast->delete();
            return response()->json(['message' => 'Sales forecast deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete sales forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast accuracy analysis.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getForecastAccuracy(Request $request)
    {
        $query = SalesForecast::whereNotNull('actual_quantity')
            ->where('is_current_version', true);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('period_from')) {
            $query->where('forecast_period', '>=', $request->period_from);
        }

        if ($request->has('period_to')) {
            $query->where('forecast_period', '<=', $request->period_to);
        }

        $forecasts = $query->get();

        if ($forecasts->count() === 0) {
            return response()->json([
                'message' => 'No forecast data with actuals found for analysis'
            ], 404);
        }

        // Calculate accuracy metrics
        $totalForecasts = $forecasts->count();
        $totalAbsoluteError = $forecasts->sum(function($forecast) {
            return abs($forecast->variance);
        });
        $totalForecastQuantity = $forecasts->sum('forecast_quantity');
        $totalActualQuantity = $forecasts->sum('actual_quantity');

        $mape = $forecasts->filter(function($forecast) {
            return $forecast->actual_quantity > 0;
        })->avg(function($forecast) {
            return abs($forecast->variance / $forecast->actual_quantity) * 100;
        });

        $accuracy = [
            'total_forecasts' => $totalForecasts,
            'mean_absolute_error' => $totalAbsoluteError / $totalForecasts,
            'mean_absolute_percentage_error' => round($mape, 2),
            'total_forecast_quantity' => $totalForecastQuantity,
            'total_actual_quantity' => $totalActualQuantity,
            'overall_bias' => $totalActualQuantity - $totalForecastQuantity,
            'accuracy_percentage' => round((1 - ($mape / 100)) * 100, 2)
        ];

        return response()->json([
            'data' => $accuracy,
            'message' => 'Forecast accuracy analysis retrieved successfully'
        ], 200);
    }

    /**
     * Get consolidated forecast view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getConsolidatedForecast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:Customer,customer_id',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date',
            'group_by' => 'nullable|in:item,customer,period'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = SalesForecast::with(['customer', 'item'])
            ->where('is_current_version', true);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('period_from')) {
            $query->where('forecast_period', '>=', $request->period_from);
        }

        if ($request->has('period_to')) {
            $query->where('forecast_period', '<=', $request->period_to);
        }

        $forecasts = $query->get();
        $groupBy = $request->get('group_by', 'item');

        $consolidated = $forecasts->groupBy(function($forecast) use ($groupBy) {
            switch ($groupBy) {
                case 'customer':
                    return $forecast->customer->name;
                case 'period':
                    return $forecast->forecast_period;
                case 'item':
                default:
                    return $forecast->item->name;
            }
        })->map(function($group) {
            return [
                'total_quantity' => $group->sum('forecast_quantity'),
                'count' => $group->count(),
                'items' => $group->pluck('item.name')->unique()->values(),
                'periods' => $group->pluck('forecast_period')->unique()->sort()->values()
            ];
        });

        return response()->json([
            'data' => $consolidated,
            'group_by' => $groupBy,
            'message' => 'Consolidated forecast retrieved successfully'
        ], 200);
    }

    /**
     * Get forecast history and versions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getForecastHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:Customer,customer_id',
            'item_id' => 'nullable|exists:Item,item_id',
            'forecast_period' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = SalesForecast::with(['customer', 'item']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('forecast_period')) {
            $query->where('forecast_period', $request->forecast_period);
        }

        $forecasts = $query->orderBy('submission_date', 'desc')->get();

        // Group by item-period combination
        $history = $forecasts->groupBy(function($forecast) {
            return $forecast->item_id . '-' . $forecast->forecast_period;
        })->map(function($versions) {
            return [
                'item' => $versions->first()->item,
                'customer' => $versions->first()->customer,
                'forecast_period' => $versions->first()->forecast_period,
                'current_version' => $versions->where('is_current_version', true)->first(),
                'versions' => $versions->map(function($version) {
                    return [
                        'forecast_id' => $version->forecast_id,
                        'forecast_quantity' => $version->forecast_quantity,
                        'forecast_source' => $version->forecast_source,
                        'confidence_level' => $version->confidence_level,
                        'submission_date' => $version->submission_date,
                        'is_current_version' => $version->is_current_version
                    ];
                })
            ];
        });

        return response()->json([
            'data' => $history->values(),
            'message' => 'Forecast history retrieved successfully'
        ], 200);
    }

    /**
     * Import customer forecasts from CSV/Excel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importCustomerForecasts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $data = [];

            if ($file->getClientOriginalExtension() === 'csv') {
                $data = $this->readCsvFile($file);
            } else {
                $data = $this->readExcelFile($file->getRealPath());
            }

            $imported = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($data['data'] as $row) {
                try {
                    $item = Item::where('item_code', $row['item_code'])->first();
                    
                    if (!$item) {
                        $errors[] = "Item not found: {$row['item_code']}";
                        continue;
                    }

                    // Process each period
                    foreach ($row as $key => $value) {
                        if ($key !== 'item_code' && is_numeric($value) && $value > 0) {
                            $period = $this->normalizePeriodFormat($key);
                            
                            if ($period) {
                                SalesForecast::create([
                                    'customer_id' => $request->customer_id,
                                    'item_id' => $item->item_id,
                                    'forecast_period' => $period,
                                    'forecast_quantity' => $value,
                                    'forecast_source' => 'Customer Import',
                                    'confidence_level' => 1.0,
                                    'forecast_issue_date' => $request->forecast_issue_date,
                                    'submission_date' => now(),
                                    'is_current_version' => true
                                ]);
                                $imported++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error processing row: " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully imported {$imported} forecast entries",
                'imported_count' => $imported,
                'errors' => $errors
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to import forecasts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate forecasts based on historical data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateForecasts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'item_id' => 'nullable|exists:Item,item_id',
            'forecast_periods' => 'required|integer|min:1|max:12',
            'method' => 'required|in:moving_average,trend_analysis,seasonal'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Get historical sales data
            $historicalData = $this->getHistoricalSalesData(
                $request->customer_id,
                $request->item_id
            );

            if ($historicalData->count() < 3) {
                return response()->json([
                    'message' => 'Insufficient historical data for forecast generation'
                ], 400);
            }

            $generatedForecasts = [];
            $method = $request->method;

            // Generate forecasts based on selected method
            switch ($method) {
                case 'moving_average':
                    $generatedForecasts = $this->generateMovingAverageForecasts(
                        $historicalData, 
                        $request->forecast_periods
                    );
                    break;
                case 'trend_analysis':
                    $generatedForecasts = $this->generateTrendForecasts(
                        $historicalData, 
                        $request->forecast_periods
                    );
                    break;
                case 'seasonal':
                    $generatedForecasts = $this->generateSeasonalForecasts(
                        $historicalData, 
                        $request->forecast_periods
                    );
                    break;
            }

            return response()->json([
                'data' => $generatedForecasts,
                'method' => $method,
                'message' => 'Forecasts generated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate forecasts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update actual quantities and calculate variances.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateActuals(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'updates' => 'required|array',
            'updates.*.forecast_id' => 'required|exists:SalesForecast,forecast_id',
            'updates.*.actual_quantity' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $updated = 0;
            foreach ($request->updates as $update) {
                $forecast = SalesForecast::find($update['forecast_id']);
                if ($forecast) {
                    $variance = $update['actual_quantity'] - $forecast->forecast_quantity;
                    $forecast->update([
                        'actual_quantity' => $update['actual_quantity'],
                        'variance' => $variance
                    ]);
                    $updated++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully updated {$updated} forecast entries",
                'updated_count' => $updated
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update actuals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast trend analysis.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getForecastTrend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:Customer,customer_id',
            'item_id' => 'nullable|exists:Item,item_id',
            'periods' => 'nullable|integer|min:3|max:24'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $periods = $request->get('periods', 12);
        $endDate = now();
        $startDate = $endDate->copy()->subMonths($periods);

        $query = SalesForecast::with(['customer', 'item'])
            ->where('is_current_version', true)
            ->where('forecast_period', '>=', $startDate->format('Y-m-d'))
            ->where('forecast_period', '<=', $endDate->format('Y-m-d'));

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        $forecasts = $query->orderBy('forecast_period')->get();

        // Group by period
        $trendData = $forecasts->groupBy('forecast_period')->map(function($group, $period) {
            return [
                'period' => $period,
                'total_quantity' => $group->sum('forecast_quantity'),
                'count' => $group->count(),
                'average_confidence' => $group->avg('confidence_level')
            ];
        })->values();

        // Calculate trend
        $trend = $this->calculateTrend($trendData);

        return response()->json([
            'data' => $trendData,
            'trend' => $trend,
            'message' => 'Forecast trend analysis retrieved successfully'
        ], 200);
    }

    /**
     * Get volatility summary.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getVolatilitySummary(Request $request)
    {
        $query = SalesForecast::with(['customer', 'item'])
            ->where('is_current_version', true);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $forecasts = $query->get();

        // Group by item and calculate volatility
        $volatility = $forecasts->groupBy('item_id')->map(function($group) {
            $quantities = $group->pluck('forecast_quantity');
            $mean = $quantities->avg();
            $variance = $quantities->map(function($q) use ($mean) {
                return pow($q - $mean, 2);
            })->avg();
            $stdDev = sqrt($variance);
            $cv = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

            return [
                'item' => $group->first()->item,
                'forecast_count' => $group->count(),
                'mean_quantity' => round($mean, 2),
                'std_deviation' => round($stdDev, 2),
                'coefficient_of_variation' => round($cv, 2),
                'volatility_level' => $cv > 50 ? 'High' : ($cv > 25 ? 'Medium' : 'Low')
            ];
        })->sortByDesc('coefficient_of_variation')->values();

        return response()->json([
            'data' => $volatility,
            'message' => 'Volatility summary retrieved successfully'
        ], 200);
    }

    // ============================================================================
    // AI EXCEL IMPORT METHODS - NEW FEATURES
    // ============================================================================

    /**
     * Process Excel file with AI to extract forecast data
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importExcelWithAI(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            'ai_model' => 'nullable|string|in:llama3-8b-8192,mixtral-8x7b-32768',
            'confidence_threshold' => 'nullable|numeric|between:0.1,1.0',
            'auto_save' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Store the uploaded file
            $file = $request->file('excel_file');
            $filename = 'forecast_' . time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('forecast_uploads', $filename, 'local');

            // Read Excel file and convert to structured data
            $excelData = $this->readExcelFile(storage_path('app/' . $filePath));
            
            if (empty($excelData)) {
                return response()->json([
                    'message' => 'Could not read Excel file or file is empty',
                    'error' => 'Excel parsing failed'
                ], 422);
            }

            // Process with AI
            $aiModel = $request->input('ai_model', 'llama3-8b-8192');
            $aiResult = $this->processExcelWithGroqAI($excelData, $aiModel);

            if (!$aiResult['success']) {
                return response()->json([
                    'message' => 'AI processing failed',
                    'error' => $aiResult['error'],
                    'raw_data' => $excelData
                ], 500);
            }

            // Parse AI response to forecast data
            $extractedData = $this->parseAIResponseEnhanced($aiResult['data']);
            
            // Auto-save if requested and confidence is high enough
            $confidenceThreshold = $request->input('confidence_threshold', 0.8);
            $shouldAutoSave = $request->input('auto_save', false) && 
                            ($extractedData['confidence'] ?? 0) >= $confidenceThreshold;

            if ($shouldAutoSave) {
                $saveResult = $this->saveForecastData(
                    $extractedData['forecasts'],
                    $request->customer_id,
                    $request->forecast_issue_date
                );

                return response()->json([
                    'message' => 'Excel processed and forecasts saved automatically',
                    'data' => [
                        'ai_confidence' => $extractedData['confidence'],
                        'extracted_forecasts' => $extractedData['forecasts'],
                        'saved_count' => $saveResult['saved_count'],
                        'errors' => $saveResult['errors'],
                        'auto_saved' => true
                    ]
                ], 201);
            } else {
                // Return for manual review
                return response()->json([
                    'message' => 'Excel processed successfully, please review before saving',
                    'data' => [
                        'ai_confidence' => $extractedData['confidence'],
                        'extracted_forecasts' => $extractedData['forecasts'],
                        'requires_review' => true,
                        'auto_saved' => false,
                        'raw_excel_data' => $excelData
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Excel AI Import Error: ' . $e->getMessage(), [
                'file' => $request->file('excel_file')?->getClientOriginalName(),
                'customer_id' => $request->customer_id
            ]);

            return response()->json([
                'message' => 'Failed to process Excel file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enhanced AI import with duplicate handling
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importExcelWithAIEnhanced(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
            'ai_model' => 'nullable|string|in:llama3-8b-8192,mixtral-8x7b-32768',
            'confidence_threshold' => 'nullable|numeric|between:0.1,1.0',
            'auto_save' => 'boolean',
            'duplicate_handling' => 'nullable|string|in:sum,average,max,min,first,last'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Store the uploaded file
            $file = $request->file('excel_file');
            $filename = 'forecast_' . time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('forecast_uploads', $filename, 'local');

            // Read Excel file and convert to structured data
            $excelData = $this->readExcelFile(storage_path('app/' . $filePath));
            
            if (empty($excelData)) {
                return response()->json([
                    'message' => 'Could not read Excel file or file is empty',
                    'error' => 'Excel parsing failed'
                ], 422);
            }

            // Check for potential duplicates before AI processing
            $duplicateAnalysis = $this->analyzeDuplicates($excelData);

            // Process with AI
            $aiModel = $request->input('ai_model', 'llama3-8b-8192');
            $aiResult = $this->processExcelWithGroqAI($excelData, $aiModel);

            if (!$aiResult['success']) {
                return response()->json([
                    'message' => 'AI processing failed',
                    'error' => $aiResult['error'],
                    'raw_data' => $excelData
                ], 500);
            }

            // Parse AI response to forecast data
            $extractedData = $this->parseAIResponseEnhanced($aiResult['data']);
            
            // Add duplicate analysis to the response
            $extractedData['duplicate_analysis'] = $duplicateAnalysis;
            
            // Auto-save if requested and confidence is high enough
            $confidenceThreshold = $request->input('confidence_threshold', 0.8);
            $shouldAutoSave = $request->input('auto_save', false) && 
                            ($extractedData['confidence'] ?? 0) >= $confidenceThreshold;

            if ($shouldAutoSave) {
                $duplicateHandling = $request->input('duplicate_handling', 'last');
                $saveResult = $this->saveForecastDataWithDuplicateHandling(
                    $extractedData['forecasts'],
                    $request->customer_id,
                    $request->forecast_issue_date,
                    $duplicateHandling
                );

                return response()->json([
                    'message' => 'Excel processed and forecasts saved automatically',
                    'data' => [
                        'ai_confidence' => $extractedData['confidence'],
                        'extracted_forecasts' => $extractedData['forecasts'],
                        'saved_count' => $saveResult['saved_count'],
                        'errors' => $saveResult['errors'],
                        'duplicate_info' => $saveResult['duplicate_info'],
                        'duplicate_analysis' => $duplicateAnalysis,
                        'auto_saved' => true
                    ]
                ], 201);
            } else {
                // Return for manual review
                return response()->json([
                    'message' => 'Excel processed successfully, please review before saving',
                    'data' => [
                        'ai_confidence' => $extractedData['confidence'],
                        'extracted_forecasts' => $extractedData['forecasts'],
                        'duplicate_analysis' => $duplicateAnalysis,
                        'requires_review' => true,
                        'auto_saved' => false,
                        'raw_excel_data' => $excelData
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Excel AI Import Error: ' . $e->getMessage(), [
                'file' => $request->file('excel_file')?->getClientOriginalName(),
                'customer_id' => $request->customer_id
            ]);

            return response()->json([
                'message' => 'Failed to process Excel file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save AI-extracted forecast data after manual review
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function saveAIExtractedForecasts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'forecasts' => 'required|array|min:1',
            'forecasts.*.item_code' => 'required|string',
            'forecasts.*.periods' => 'required|array|min:1',
            'forecasts.*.periods.*' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->saveForecastData(
                $request->forecasts,
                $request->customer_id,
                $request->forecast_issue_date
            );

            return response()->json([
                'message' => "Successfully saved {$result['saved_count']} forecast entries",
                'data' => [
                    'saved_count' => $result['saved_count'],
                    'errors' => $result['errors']
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to save forecast data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI processing history for forecasts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getAIProcessingHistory(Request $request)
    {
        $query = SalesForecast::where('forecast_source', 'like', 'AI-%')
            ->where('is_current_version', true)
            ->with(['customer', 'item'])
            ->orderBy('submission_date', 'desc');

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $aiForecasts = $query->take(50)->get();

        return response()->json([
            'data' => $aiForecasts,
            'message' => 'AI processing history retrieved successfully'
        ], 200);
    }

    /**
     * Download Excel template for forecast import
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadExcelTemplate(Request $request)
    {
        try {
            $templateType = $request->input('type', 'standard'); // standard, quarterly, vertical
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('ERP System')
                ->setTitle('Sales Forecast Template')
                ->setDescription('Template for AI-powered forecast import');
            
            switch ($templateType) {
                case 'standard':
                    $this->createStandardTemplate($sheet);
                    break;
                case 'quarterly':
                    $this->createQuarterlyTemplate($sheet);
                    break;
                case 'vertical':
                    $this->createVerticalTemplate($sheet);
                    break;
                default:
                    $this->createStandardTemplate($sheet);
            }
            
            // Set column widths
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Create filename
            $filename = 'sales_forecast_template_' . $templateType . '_' . date('Y-m-d') . '.xlsx';
            
            // Prepare response
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($writer) {
                $writer->save('php://output');
            });
            
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'max-age=0');
            
            return $response;
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available template types
     *
     * @return \Illuminate\Http\Response
     */
    public function getTemplateTypes()
    {
        $templates = [
            [
                'type' => 'standard',
                'name' => 'Standard Monthly',
                'description' => '6-month horizontal layout with monthly columns',
                'recommended_for' => 'Most common forecast imports',
                'max_items' => 1000,
                'max_periods' => 12
            ],
            [
                'type' => 'quarterly',
                'name' => 'Quarterly',
                'description' => 'Quarterly forecast layout (Q1, Q2, Q3, Q4)',
                'recommended_for' => 'High-level business planning',
                'max_items' => 1000,
                'max_periods' => 8
            ],
            [
                'type' => 'vertical',
                'name' => 'Vertical Layout',
                'description' => 'One row per item-period combination',
                'recommended_for' => 'Large datasets with many periods',
                'max_items' => 10000,
                'max_periods' => 50
            ]
        ];
        
        return response()->json([
            'data' => $templates,
            'message' => 'Available template types retrieved successfully'
        ], 200);
    }

    /**
     * Enhanced validate Excel file before AI processing (with duplicate detection)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validateExcelFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('excel_file');
            $filePath = $file->store('temp_validation', 'local');
            
            // Read Excel file structure
            $excelData = $this->readExcelFile(storage_path('app/' . $filePath));
            
            if (empty($excelData)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Could not read Excel file or file is empty'
                ], 422);
            }
            
            // Validate structure
            $validation = $this->validateExcelStructure($excelData);
            
            // Analyze duplicates
            $duplicateAnalysis = $this->analyzeDuplicates($excelData);
            
            // Clean up temp file
            \Storage::disk('local')->delete($filePath);
            
            // Determine overall validity (duplicates don't make file invalid, just noteworthy)
            $overallValid = $validation['valid'];
            $overallMessage = $validation['message'];
            
            if ($duplicateAnalysis['has_duplicates']) {
                $overallMessage .= " Note: {$duplicateAnalysis['duplicate_count']} duplicate item-period combinations found.";
            }
            
            return response()->json([
                'valid' => $overallValid,
                'message' => $overallMessage,
                'details' => $validation['details'],
                'duplicate_analysis' => $duplicateAnalysis,
                'preview' => array_slice($excelData['data'], 0, 5), // First 5 rows
                'recommendations' => $this->getValidationRecommendations($validation, $duplicateAnalysis)
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Failed to validate Excel file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get duplicate handling options
     *
     * @return \Illuminate\Http\Response
     */
    public function getDuplicateHandlingOptions()
    {
        $options = [
            [
                'value' => 'sum',
                'label' => 'Sum All Values',
                'description' => 'Add all duplicate values together (200 + 150 = 350)',
                'example' => 'Best for: Multiple shipments per period',
                'icon' => 'fas fa-plus'
            ],
            [
                'value' => 'average',
                'label' => 'Average Values',
                'description' => 'Calculate average of duplicate values ((200 + 150) / 2 = 175)',
                'example' => 'Best for: Averaging multiple estimates',
                'icon' => 'fas fa-calculator'
            ],
            [
                'value' => 'max',
                'label' => 'Take Maximum',
                'description' => 'Use the highest value (max(200, 150) = 200)',
                'example' => 'Best for: Conservative forecasting',
                'icon' => 'fas fa-arrow-up'
            ],
            [
                'value' => 'min',
                'label' => 'Take Minimum',
                'description' => 'Use the lowest value (min(200, 150) = 150)',
                'example' => 'Best for: Conservative planning',
                'icon' => 'fas fa-arrow-down'
            ],
            [
                'value' => 'first',
                'label' => 'First Value',
                'description' => 'Use the first occurrence (200)',
                'example' => 'Best for: Prioritizing initial estimates',
                'icon' => 'fas fa-step-backward'
            ],
            [
                'value' => 'last',
                'label' => 'Last Value (Default)',
                'description' => 'Use the last occurrence (150)',
                'example' => 'Best for: Most recent estimates',
                'icon' => 'fas fa-step-forward'
            ]
        ];
        
        return response()->json([
            'data' => $options,
            'default' => 'last',
            'message' => 'Duplicate handling options retrieved successfully'
        ], 200);
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    /**
     * Read Excel file and convert to structured data
     *
     * @param  string  $filePath
     * @return array
     */
    private function readExcelFile($filePath)
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $data = [];
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            // Read headers (first row)
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellValue = $worksheet->getCellByColumnAndRow($col, 1)->getCalculatedValue();
                $headers[] = trim((string)$cellValue);
            }

            // Read data rows
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cell = $worksheet->getCellByColumnAndRow($col, $row);
                    $cellValue = $cell->getCalculatedValue();
                    
                    // Handle date cells
                    if (Date::isDateTime($cell)) {
                        $cellValue = Date::excelToDateTimeObject($cellValue)->format('Y-m-d');
                    }
                    
                    $rowData[] = $cellValue;
                }
                
                // Skip empty rows
                if (array_filter($rowData, function($value) { return !empty(trim($value)); })) {
                    $data[] = array_combine($headers, $rowData);
                }
            }

            return [
                'headers' => $headers,
                'data' => $data,
                'row_count' => count($data)
            ];

        } catch (\Exception $e) {
            Log::error('Excel reading error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Read CSV file
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return array
     */
    private function readCsvFile($file)
    {
        $data = [];
        $headers = [];
        
        if (($handle = fopen($file->getRealPath(), "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) === count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }
        
        return [
            'headers' => $headers,
            'data' => $data,
            'row_count' => count($data)
        ];
    }

    /**
     * Process Excel data with Groq AI
     *
     * @param  array  $excelData
     * @param  string  $model
     * @return array
     */
    private function processExcelWithGroqAI($excelData, $model = 'llama3-8b-8192')
    {
        try {
            $groqApiKey = env('GROQ_API_KEY');
            
            if (!$groqApiKey) {
                return [
                    'success' => false,
                    'error' => 'GROQ_API_KEY not configured'
                ];
            }

            // Prepare prompt for AI
            $prompt = $this->buildEnhancedExcelAnalysisPrompt($excelData);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $groqApiKey,
                'Content-Type' => 'application/json'
            ])->timeout(60)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert data analyst specializing in extracting sales forecast data from Excel files. Always respond with valid JSON format.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 4000
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Groq API request failed: ' . $response->body()
                ];
            }

            $responseData = $response->json();
            
            if (!isset($responseData['choices'][0]['message']['content'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid AI response format'
                ];
            }

            return [
                'success' => true,
                'data' => $responseData['choices'][0]['message']['content']
            ];

        } catch (\Exception $e) {
            Log::error('Groq AI processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build enhanced prompt for maximum Excel format flexibility
     *
     * @param  array  $excelData
     * @return string
     */
    private function buildEnhancedExcelAnalysisPrompt($excelData)
    {
        $headersStr = implode(', ', $excelData['headers']);
        $sampleData = array_slice($excelData['data'], 0, 10); // First 10 rows for better analysis
        
        $sampleDataStr = '';
        foreach ($sampleData as $index => $row) {
            $rowStr = '';
            foreach ($row as $key => $value) {
                $rowStr .= "{$key}: {$value}, ";
            }
            $sampleDataStr .= "Row " . ($index + 1) . ": " . rtrim($rowStr, ', ') . "\n";
        }

        return "
You are an expert data analyst specializing in extracting sales forecast data from ANY Excel format. 

EXCEL DATA TO ANALYZE:
HEADERS: {$headersStr}
TOTAL ROWS: {$excelData['row_count']}

SAMPLE DATA:
{$sampleDataStr}

YOUR MISSION: Extract forecast data with MAXIMUM FLEXIBILITY. Handle ANY format customers might send.

STEP 1 - SMART ITEM IDENTIFICATION:
Look for columns that could represent items/products using ANY of these patterns:
- Exact matches: 'Item Code', 'SKU', 'Product Code', 'Part Number', 'Material Code'
- Variations: 'item_code', 'item-code', 'ITEMCODE', 'ProductSKU', 'PartNo'  
- Languages: 'Kode Barang', '产品代码', 'Código Producto'
- Patterns: Any column with alphanumeric codes like 'ABC001', 'ITEM-123', 'P001'
- Mixed content: Extract codes from 'SKU: ABC001' or 'Product: ABC001 - Widget'

STEP 2 - FLEXIBLE DATE/PERIOD RECOGNITION:
Identify ANY time period columns:
- Standard: '2024-01', '2024-02', 'Jan-24', 'Feb-24'
- Full names: 'January 2024', 'February 2024'
- Quarterly: 'Q1-2024', 'Q2 2024', 'Quarter 1'
- Variations: '01/2024', '2024/01', 'Jan 24', 'JAN-2024'
- Languages: '一月', 'Enero', 'Janeiro'
- Custom: '2024-01 Forecast', 'Jan 2024 Demand'

STEP 3 - QUANTITY VALUE EXTRACTION:
Find numeric forecast values from ANY column:
- Direct: Numbers under period columns
- Named: 'Forecast', 'Quantity', 'Demand', 'Sales'
- Variations: 'Forecast Qty', 'Sales Forecast', 'Prediksi'
- Calculated: Extract from formulas like '=B2*1.2'
- Mixed: Numbers from 'Qty: 100' or '100 units'

STEP 4 - INTELLIGENT DATA CLEANING:
- Skip decorative/summary rows (TOTAL, SUMMARY, etc.)
- Handle merged cells by using first available value
- Convert text numbers to real numbers
- Normalize dates to YYYY-MM format
- Extract codes from mixed content
- Ignore empty/invalid rows

STEP 5 - PATTERN RECOGNITION:
- Detect horizontal layout (items in rows, periods in columns)
- Detect vertical layout (one row per item-period)
- Handle mixed layouts intelligently
- Process multiple data blocks if needed

ADVANCED HANDLING:
- Multiple values for same item-period: Report all values found
- Different languages: Recognize international headers
- Complex formatting: Extract data despite styling
- Formula cells: Use calculated values
- Irregular spacing: Handle inconsistent layouts

CONFIDENCE SCORING:
- 0.9+: Perfect structure, clear patterns
- 0.8-0.9: Good structure, minor ambiguities  
- 0.7-0.8: Acceptable structure, some unclear elements
- 0.6-0.7: Poor structure but data extractable
- <0.6: Very unclear, manual review needed

OUTPUT REQUIREMENTS:
Return ONLY valid JSON with this structure:
{
    \"confidence\": 0.95,
    \"analysis\": \"Found clear horizontal layout with item codes in column A and monthly forecasts in columns C-H. Recognized standard YYYY-MM date format.\",
    \"forecasts\": [
        {
            \"item_code\": \"ITEM001\",
            \"periods\": {
                \"2024-01\": 100,
                \"2024-02\": 150,
                \"2024-03\": 200
            }
        }
    ],
    \"identified_patterns\": [
        \"Horizontal layout detected\",
        \"Standard item codes in column A\", 
        \"YYYY-MM date format in headers\",
        \"Numeric quantities in data cells\"
    ],
    \"potential_issues\": [
        \"Some empty cells found\",
        \"Mixed data types in column F\"
    ],
    \"data_quality\": {
        \"total_items_found\": 25,
        \"total_periods_found\": 6,
        \"empty_cells_count\": 3,
        \"duplicate_combinations\": 2
    },
    \"format_analysis\": {
        \"layout_type\": \"horizontal\",
        \"item_column\": \"Item Code\",
        \"period_columns\": [\"2024-01\", \"2024-02\", \"2024-03\"],
        \"date_format\": \"YYYY-MM\",
        \"has_headers\": true,
        \"has_formulas\": false
    }
}

CRITICAL RULES:
1. BE MAXIMALLY FLEXIBLE - If humans can read it, you should extract it
2. ALWAYS return valid JSON - No markdown, no explanations outside JSON
3. EXTRACT EVERYTHING POSSIBLE - Don't skip data due to minor formatting issues
4. REPORT CONFIDENCE HONESTLY - Better to admit uncertainty than guess wrong
5. HANDLE DUPLICATES GRACEFULLY - Report all values found for same item-period
6. NORMALIZE OUTPUT - Convert all dates to YYYY-MM-DD format
7. QUALITY FOCUS - Prioritize data accuracy over quantity

Remember: Customers send REAL data with REAL problems. Your job is to extract maximum value from whatever format they provide while maintaining accuracy and providing honest confidence assessment.

PROCESS THE DATA NOW:
";
    }

    /**
     * Enhanced AI response parsing with better error handling
     *
     * @param  string  $aiResponse
     * @return array
     */
    private function parseAIResponseEnhanced($aiResponse)
    {
        try {
            // Multiple attempts to clean and parse JSON
            $attempts = [
                // Attempt 1: Direct parsing
                $aiResponse,
                // Attempt 2: Remove markdown
                preg_replace('/```json\s*/', '', preg_replace('/\s*```/', '', $aiResponse)),
                // Attempt 3: Extract JSON from mixed content
                $this->extractJsonFromText($aiResponse),
                // Attempt 4: Fix common JSON issues
                $this->fixCommonJsonIssues($aiResponse)
            ];

            foreach ($attempts as $attempt) {
                $cleaned = trim($attempt);
                if (empty($cleaned)) continue;
                
                $parsed = json_decode($cleaned, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['forecasts'])) {
                    // Validate and enhance the parsed data
                    return $this->validateAndEnhanceAIResponse($parsed);
                }
            }
            
            // If all attempts fail, return fallback
            throw new \Exception('Could not parse valid JSON from AI response');
            
        } catch (\Exception $e) {
            Log::error('AI response parsing failed', [
                'error' => $e->getMessage(),
                'response_preview' => substr($aiResponse, 0, 500)
            ]);
            
            // Return fallback response
            return [
                'confidence' => 0.1,
                'analysis' => 'AI response parsing failed. Manual review required.',
                'forecasts' => [],
                'identified_patterns' => [],
                'potential_issues' => ['AI response parsing failed: ' . $e->getMessage()],
                'data_quality' => [
                    'total_items_found' => 0,
                    'total_periods_found' => 0,
                    'empty_cells_count' => 0,
                    'duplicate_combinations' => 0
                ],
                'format_analysis' => [
                    'layout_type' => 'unknown',
                    'item_column' => null,
                    'period_columns' => [],
                    'date_format' => 'unknown',
                    'has_headers' => false,
                    'has_formulas' => false
                ]
            ];
        }
    }

    /**
     * Extract JSON from mixed text content
     *
     * @param  string  $text
     * @return string
     */
    private function extractJsonFromText($text)
    {
        // Find JSON object between { and }
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }
        
        return $text;
    }

    /**
     * Fix common JSON issues
     *
     * @param  string  $json
     * @return string
     */
    private function fixCommonJsonIssues($json)
    {
        // Fix trailing commas
        $json = preg_replace('/,\s*}/', '}', $json);
        $json = preg_replace('/,\s*]/', ']', $json);
        
        // Fix unescaped quotes in strings
        $json = preg_replace('/(["\'])(.*?)\1\s*:\s*(["\'])(.*?[^\\\\])\3/', '$1$2$1: $3' . addslashes('$4') . '$3', $json);
        
        // Fix single quotes
        $json = str_replace("'", '"', $json);
        
        return $json;
    }

    /**
     * Validate and enhance AI response
     *
     * @param  array  $parsed
     * @return array
     */
    private function validateAndEnhanceAIResponse($parsed)
    {
        // Ensure required fields exist
        $required = ['confidence', 'analysis', 'forecasts', 'identified_patterns', 'potential_issues'];
        foreach ($required as $field) {
            if (!isset($parsed[$field])) {
                $parsed[$field] = $this->getDefaultValue($field);
            }
        }
        
        // Validate and enhance forecasts
        if (isset($parsed['forecasts']) && is_array($parsed['forecasts'])) {
            foreach ($parsed['forecasts'] as &$forecast) {
                if (isset($forecast['periods'])) {
                    $normalizedPeriods = [];
                    foreach ($forecast['periods'] as $period => $quantity) {
                        $normalizedPeriod = $this->normalizePeriodFormat($period);
                        if ($normalizedPeriod && is_numeric($quantity) && $quantity >= 0) {
                            $normalizedPeriods[$normalizedPeriod] = (float)$quantity;
                        }
                    }
                    $forecast['periods'] = $normalizedPeriods;
                }
            }
        }
        
        // Ensure confidence is within valid range
        $parsed['confidence'] = max(0, min(1, (float)($parsed['confidence'] ?? 0.5)));
        
        // Add enhanced analysis if missing
        if (empty($parsed['data_quality'])) {
            $parsed['data_quality'] = $this->calculateDataQuality($parsed['forecasts']);
        }
        
        if (empty($parsed['format_analysis'])) {
            $parsed['format_analysis'] = $this->analyzeDataFormat($parsed['forecasts']);
        }
        
        return $parsed;
    }

    /**
     * Get default value for missing fields
     *
     * @param  string  $field
     * @return mixed
     */
    private function getDefaultValue($field)
    {
        $defaults = [
            'confidence' => 0.5,
            'analysis' => 'Data processed with standard analysis',
            'forecasts' => [],
            'identified_patterns' => ['Data extraction completed'],
            'potential_issues' => []
        ];
        
        return $defaults[$field] ?? null;
    }

    /**
     * Calculate data quality metrics
     *
     * @param  array  $forecasts
     * @return array
     */
    private function calculateDataQuality($forecasts)
    {
        $totalItems = count($forecasts);
        $totalPeriods = 0;
        $emptyCount = 0;
        
        foreach ($forecasts as $forecast) {
            if (isset($forecast['periods'])) {
                $periods = count($forecast['periods']);
                $totalPeriods = max($totalPeriods, $periods);
                
                foreach ($forecast['periods'] as $quantity) {
                    if (empty($quantity)) {
                        $emptyCount++;
                    }
                }
            }
        }
        
        return [
            'total_items_found' => $totalItems,
            'total_periods_found' => $totalPeriods,
            'empty_cells_count' => $emptyCount,
            'duplicate_combinations' => 0 // Will be calculated separately
        ];
    }

    /**
     * Analyze data format
     *
     * @param  array  $forecasts
     * @return array
     */
    private function analyzeDataFormat($forecasts)
    {
        $periodColumns = [];
        $dateFormats = [];
        
        foreach ($forecasts as $forecast) {
            if (isset($forecast['periods'])) {
                foreach (array_keys($forecast['periods']) as $period) {
                    $periodColumns[] = $period;
                    
                    // Detect date format
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
                        $dateFormats['YYYY-MM-DD'] = true;
                    } elseif (preg_match('/^\d{4}-\d{2}$/', $period)) {
                        $dateFormats['YYYY-MM'] = true;
                    }
                }
            }
        }
        
        return [
            'layout_type' => count($forecasts) > 0 ? 'horizontal' : 'unknown',
            'item_column' => 'auto-detected',
            'period_columns' => array_unique($periodColumns),
            'date_format' => !empty($dateFormats) ? array_keys($dateFormats)[0] : 'mixed',
            'has_headers' => true,
            'has_formulas' => false
        ];
    }

    /**
     * Normalize period format to YYYY-MM-01
     *
     * @param  string  $period
     * @return string|null
     */
    private function normalizePeriodFormat($period)
    {
        try {
            // Handle different formats
            $period = trim($period);
            
            // Already in YYYY-MM format
            if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                return $period . '-01';
            }
            
            // YYYY-MM-DD format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
                return substr($period, 0, 7) . '-01';
            }
            
            // Try to parse with Carbon
            try {
                $date = Carbon::parse($period);
                return $date->startOfMonth()->format('Y-m-d');
            } catch (\Exception $e) {
                // If Carbon fails, try manual parsing
                
                // MMM-YY format (Jan-24)
                if (preg_match('/^([A-Za-z]{3})-(\d{2})$/', $period, $matches)) {
                    $month = date('m', strtotime($matches[1] . ' 1'));
                    $year = '20' . $matches[2];
                    return $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
                }
                
                // YYYY/MM format
                if (preg_match('/^(\d{4})\/(\d{1,2})$/', $period, $matches)) {
                    return $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-01';
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::warning('Period normalization failed', ['period' => $period, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Save forecast data to database
     *
     * @param  array  $forecasts
     * @param  int  $customerId
     * @param  string  $issueDate
     * @return array
     */
    private function saveForecastData($forecasts, $customerId, $issueDate)
    {
        DB::beginTransaction();
        
        try {
            $savedCount = 0;
            $errorRows = [];

            foreach ($forecasts as $forecastData) {
                $itemCode = trim($forecastData['item_code']);
                
                // Find item by code
                $item = Item::where('item_code', $itemCode)->first();
                
                if (!$item) {
                    $errorRows[] = [
                        'item_code' => $itemCode,
                        'error' => 'Item not found'
                    ];
                    continue;
                }

                foreach ($forecastData['periods'] as $periodKey => $quantity) {
                    if ($quantity <= 0) {
                        continue;
                    }

                    // Check if forecast already exists
                    $existingForecast = SalesForecast::where('customer_id', $customerId)
                        ->where('item_id', $item->item_id)
                        ->where('forecast_period', $periodKey)
                        ->where('is_current_version', true)
                        ->first();

                    if ($existingForecast) {
                        // Deactivate old forecast
                        $existingForecast->is_current_version = false;
                        $existingForecast->save();

                        // Create new version
                        SalesForecast::create([
                            'customer_id' => $customerId,
                            'item_id' => $item->item_id,
                            'forecast_period' => $periodKey,
                            'forecast_quantity' => $quantity,
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'AI-Excel',
                            'confidence_level' => 0.85,
                            'forecast_issue_date' => $issueDate,
                            'submission_date' => now(),
                            'is_current_version' => true,
                            'previous_version_id' => $existingForecast->forecast_id
                        ]);
                    } else {
                        // Create new forecast
                        SalesForecast::create([
                            'customer_id' => $customerId,
                            'item_id' => $item->item_id,
                            'forecast_period' => $periodKey,
                            'forecast_quantity' => $quantity,
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'AI-Excel',
                            'confidence_level' => 0.85,
                            'forecast_issue_date' => $issueDate,
                            'submission_date' => now(),
                            'is_current_version' => true
                        ]);
                    }

                    $savedCount++;
                }
            }

            DB::commit();
            
            return [
                'saved_count' => $savedCount,
                'errors' => $errorRows
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Enhanced save forecast data with duplicate handling options
     *
     * @param  array  $forecasts
     * @param  int  $customerId
     * @param  string  $issueDate
     * @param  string  $duplicateHandling
     * @return array
     */
    private function saveForecastDataWithDuplicateHandling($forecasts, $customerId, $issueDate, $duplicateHandling = 'last')
    {
        DB::beginTransaction();
        
        try {
            $savedCount = 0;
            $errorRows = [];
            $duplicateInfo = [];

            // Process and consolidate data first
            $consolidatedForecasts = $this->consolidateDuplicateEntries($forecasts, $duplicateHandling);

            foreach ($consolidatedForecasts as $forecastData) {
                $itemCode = trim($forecastData['item_code']);
                
                // Find item by code
                $item = Item::where('item_code', $itemCode)->first();
                
                if (!$item) {
                    $errorRows[] = [
                        'item_code' => $itemCode,
                        'error' => 'Item not found',
                        'duplicate_info' => $forecastData['duplicate_info'] ?? null
                    ];
                    continue;
                }

                foreach ($forecastData['periods'] as $periodKey => $quantityData) {
                    if ($quantityData['final_quantity'] <= 0) {
                        continue;
                    }

                    // Track if this was a duplicate
                    if (isset($quantityData['duplicate_info'])) {
                        $duplicateInfo[] = [
                            'item_code' => $itemCode,
                            'period' => $periodKey,
                            'original_values' => $quantityData['duplicate_info']['original_values'],
                            'final_value' => $quantityData['final_quantity'],
                            'handling_method' => $duplicateHandling
                        ];
                    }

                    // Check if forecast already exists
                    $existingForecast = SalesForecast::where('customer_id', $customerId)
                        ->where('item_id', $item->item_id)
                        ->where('forecast_period', $periodKey)
                        ->where('is_current_version', true)
                        ->first();

                    if ($existingForecast) {
                        // Deactivate old forecast
                        $existingForecast->is_current_version = false;
                        $existingForecast->save();

                        // Create new version
                        SalesForecast::create([
                            'customer_id' => $customerId,
                            'item_id' => $item->item_id,
                            'forecast_period' => $periodKey,
                            'forecast_quantity' => $quantityData['final_quantity'],
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'AI-Excel',
                            'confidence_level' => 0.85,
                            'forecast_issue_date' => $issueDate,
                            'submission_date' => now(),
                            'is_current_version' => true,
                            'previous_version_id' => $existingForecast->forecast_id
                        ]);
                    } else {
                        // Create new forecast
                        SalesForecast::create([
                            'customer_id' => $customerId,
                            'item_id' => $item->item_id,
                            'forecast_period' => $periodKey,
                            'forecast_quantity' => $quantityData['final_quantity'],
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'AI-Excel',
                            'confidence_level' => 0.85,
                            'forecast_issue_date' => $issueDate,
                            'submission_date' => now(),
                            'is_current_version' => true
                        ]);
                    }

                    $savedCount++;
                }
            }

            DB::commit();
            
            return [
                'saved_count' => $savedCount,
                'errors' => $errorRows,
                'duplicate_info' => $duplicateInfo
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Consolidate duplicate entries based on handling method
     *
     * @param  array  $forecasts
     * @param  string  $duplicateHandling
     * @return array
     */
    private function consolidateDuplicateEntries($forecasts, $duplicateHandling)
    {
        $consolidated = [];
        
        foreach ($forecasts as $forecast) {
            $itemCode = $forecast['item_code'];
            
            if (!isset($consolidated[$itemCode])) {
                $consolidated[$itemCode] = [
                    'item_code' => $itemCode,
                    'periods' => []
                ];
            }
            
            foreach ($forecast['periods'] as $period => $quantity) {
                if (!isset($consolidated[$itemCode]['periods'][$period])) {
                    $consolidated[$itemCode]['periods'][$period] = [
                        'values' => [],
                        'final_quantity' => 0
                    ];
                }
                
                $consolidated[$itemCode]['periods'][$period]['values'][] = (float)$quantity;
            }
        }
        
        // Apply duplicate handling strategy
        foreach ($consolidated as &$item) {
            foreach ($item['periods'] as $period => &$periodData) {
                $values = $periodData['values'];
                
                if (count($values) > 1) {
                    // Multiple values found for same item-period
                    $originalValues = $values;
                    
                    switch ($duplicateHandling) {
                        case 'sum':
                            $finalQuantity = array_sum($values);
                            break;
                        case 'average':
                            $finalQuantity = array_sum($values) / count($values);
                            break;
                        case 'max':
                            $finalQuantity = max($values);
                            break;
                        case 'min':
                            $finalQuantity = min($values);
                            break;
                        case 'first':
                            $finalQuantity = $values[0];
                            break;
                        case 'last':
                        default:
                            $finalQuantity = end($values);
                            break;
                    }
                    
                    $periodData['final_quantity'] = $finalQuantity;
                    $periodData['duplicate_info'] = [
                        'original_values' => $originalValues,
                        'count' => count($values),
                        'method' => $duplicateHandling
                    ];
                } else {
                    $periodData['final_quantity'] = $values[0];
                }
            }
        }
        
        return array_values($consolidated);
    }

    /**
     * Analyze potential duplicates in Excel data
     *
     * @param  array  $excelData
     * @return array
     */
    private function analyzeDuplicates($excelData)
    {
        $itemPeriodCombinations = [];
        $duplicates = [];
        
        foreach ($excelData['data'] as $rowIndex => $row) {
            // Try to identify item code and periods
            $itemCode = '';
            $periods = [];
            
            foreach ($row as $header => $value) {
                $headerLower = strtolower(trim($header));
                
                // Check if this is an item identifier
                if (in_array($headerLower, ['item code', 'sku', 'product code', 'item_code', 'part number'])) {
                    $itemCode = trim($value);
                }
                
                // Check if this is a period column with value
                if (preg_match('/^\d{4}-\d{2}$|^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-\d{2}$|^Q[1-4]-\d{4}$/i', $header) && !empty($value)) {
                    $periods[] = [
                        'period' => $header,
                        'value' => $value
                    ];
                }
            }
            
            // Check for duplicates
            if (!empty($itemCode)) {
                foreach ($periods as $periodData) {
                    $combination = $itemCode . '|' . $periodData['period'];
                    
                    if (!isset($itemPeriodCombinations[$combination])) {
                        $itemPeriodCombinations[$combination] = [];
                    }
                    
                    $itemPeriodCombinations[$combination][] = [
                        'row' => $rowIndex + 2, // +2 because of 0-index and header row
                        'item_code' => $itemCode,
                        'period' => $periodData['period'],
                        'value' => $periodData['value']
                    ];
                }
            }
        }
        
        // Find actual duplicates
        foreach ($itemPeriodCombinations as $combination => $entries) {
            if (count($entries) > 1) {
                $duplicates[] = [
                    'item_code' => $entries[0]['item_code'],
                    'period' => $entries[0]['period'],
                    'entries' => $entries,
                    'values' => array_column($entries, 'value'),
                    'count' => count($entries)
                ];
            }
        }
        
        return [
            'has_duplicates' => count($duplicates) > 0,
            'duplicate_count' => count($duplicates),
            'duplicates' => $duplicates,
            'total_combinations' => count($itemPeriodCombinations)
        ];
    }

    /**
     * Validate Excel file structure
     */
    private function validateExcelStructure($excelData)
    {
        $issues = [];
        $suggestions = [];
        
        // Check if file has data
        if (empty($excelData['data'])) {
            return [
                'valid' => false,
                'message' => 'No data found in Excel file',
                'details' => ['issues' => ['File appears to be empty'], 'suggestions' => []]
            ];
        }
        
        // Check headers
        $headers = $excelData['headers'];
        $hasItemCode = false;
        $hasDateColumns = false;
        $datePattern = '/^\d{4}-\d{2}$|^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-\d{2}$|^Q[1-4]-\d{4}$/i';
        
        foreach ($headers as $header) {
            $header = strtolower(trim($header));
            
            // Check for item identification columns
            if (in_array($header, ['item code', 'sku', 'product code', 'item_code', 'part number'])) {
                $hasItemCode = true;
            }
            
            // Check for date columns
            if (preg_match($datePattern, $header)) {
                $hasDateColumns = true;
            }
        }
        
        if (!$hasItemCode) {
            $issues[] = 'No item identification column found (Item Code, SKU, etc.)';
            $suggestions[] = 'Add a column with header like "Item Code", "SKU", or "Product Code"';
        }
        
        if (!$hasDateColumns) {
            $issues[] = 'No date/period columns found';
            $suggestions[] = 'Add columns with date headers like "2024-01", "Jan-24", or "Q1-2024"';
        }
        
        // Check data quality
        $sampleRows = array_slice($excelData['data'], 0, 10);
        $emptyRows = 0;
        
        foreach ($sampleRows as $row) {
            $nonEmptyValues = array_filter($row, function($value) {
                return !empty(trim($value));
            });
            
            if (count($nonEmptyValues) < 2) {
                $emptyRows++;
            }
        }
        
        if ($emptyRows > 5) {
            $issues[] = 'Many rows appear to be empty or have minimal data';
            $suggestions[] = 'Remove empty rows and ensure each row has item code and forecast values';
        }
        
        // Overall validation
        $valid = count($issues) === 0;
        $message = $valid ? 
            'Excel file structure looks good for AI processing' : 
            'Excel file has some issues that may affect AI processing';
        
        return [
            'valid' => $valid,
            'message' => $message,
            'details' => [
                'issues' => $issues,
                'suggestions' => $suggestions,
                'row_count' => count($excelData['data']),
                'column_count' => count($headers),
                'has_item_code' => $hasItemCode,
                'has_date_columns' => $hasDateColumns
            ]
        ];
    }

    /**
     * Get validation recommendations based on validation and duplicate analysis
     *
     * @param  array  $validation
     * @param  array  $duplicateAnalysis
     * @return array
     */
    private function getValidationRecommendations($validation, $duplicateAnalysis)
    {
        $recommendations = [];
        
        // Structure recommendations
        if (!$validation['valid']) {
            $recommendations[] = [
                'type' => 'error',
                'title' => 'Structure Issues',
                'message' => 'Fix the structural issues first for better AI recognition',
                'actions' => $validation['details']['suggestions'] ?? []
            ];
        }
        
        // Duplicate recommendations
        if ($duplicateAnalysis['has_duplicates']) {
            $duplicateCount = $duplicateAnalysis['duplicate_count'];
            $totalCombinations = $duplicateAnalysis['total_combinations'];
            $duplicatePercentage = round(($duplicateCount / $totalCombinations) * 100, 1);
            
            if ($duplicatePercentage > 30) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'High Duplicate Rate',
                    'message' => "{$duplicatePercentage}% of your data has duplicates. Consider reviewing your Excel file structure.",
                    'actions' => [
                        'Review if multiple rows for same item-period are intentional',
                        'Consider consolidating data before upload',
                        'Choose appropriate duplicate handling method'
                    ]
                ];
            } else if ($duplicatePercentage > 10) {
                $recommendations[] = [
                    'type' => 'info',
                    'title' => 'Moderate Duplicates',
                    'message' => "{$duplicatePercentage}% of your data has duplicates. Choose how to handle them.",
                    'actions' => [
                        'Select appropriate duplicate handling method',
                        'Preview results before final save'
                    ]
                ];
            } else {
                $recommendations[] = [
                    'type' => 'success',
                    'title' => 'Few Duplicates',
                    'message' => "Only {$duplicatePercentage}% duplicates found. This is normal.",
                    'actions' => [
                        'Default "last value" handling should work fine',
                        'Or choose your preferred method'
                    ]
                ];
            }
            
            // Specific duplicate examples
            $topDuplicates = array_slice($duplicateAnalysis['duplicates'], 0, 3);
            if (!empty($topDuplicates)) {
                $examples = [];
                foreach ($topDuplicates as $dup) {
                    $examples[] = "{$dup['item_code']} {$dup['period']}: " . implode(', ', $dup['values']);
                }
                
                $recommendations[] = [
                    'type' => 'info',
                    'title' => 'Duplicate Examples',
                    'message' => 'Here are some examples of duplicates found:',
                    'actions' => $examples
                ];
            }
        } else {
            $recommendations[] = [
                'type' => 'success',
                'title' => 'No Duplicates',
                'message' => 'Great! No duplicate item-period combinations found.',
                'actions' => ['Your data is ready for AI processing']
            ];
        }
        
        // Data quality recommendations
        $rowCount = $validation['details']['row_count'] ?? 0;
        $columnCount = $validation['details']['column_count'] ?? 0;
        
        if ($rowCount > 1000) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Large Dataset',
                'message' => "{$rowCount} rows detected. Processing may take longer.",
                'actions' => [
                    'Consider breaking into smaller files if processing is slow',
                    'Ensure stable internet connection'
                ]
            ];
        }
        
        if ($columnCount > 20) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Many Columns',
                'message' => "{$columnCount} columns detected. AI works best with focused data.",
                'actions' => [
                    'Consider removing unnecessary columns',
                    'Keep only item codes and forecast periods'
                ]
            ];
        }
        
        // AI optimization recommendations
        if ($validation['valid'] && !$duplicateAnalysis['has_duplicates']) {
            $recommendations[] = [
                'type' => 'success',
                'title' => 'AI Ready',
                'message' => 'Your file is optimized for AI processing!',
                'actions' => [
                    'Expected high confidence score',
                    'Good candidate for auto-save'
                ]
            ];
        }
        
        return $recommendations;
    }

    /**
     * Create standard monthly template
     */
    private function createStandardTemplate($sheet)
    {
        // Headers
        $headers = ['Item Code', 'Item Name', '2024-01', '2024-02', '2024-03', '2024-04', '2024-05', '2024-06'];
        $sheet->fromArray([$headers], null, 'A1');
        
        // Sample data
        $sampleData = [
            ['ITEM001', 'Product Alpha', 100, 150, 200, 180, 220, 250],
            ['ITEM002', 'Product Beta', 50, 75, 80, 90, 95, 100],
            ['ITEM003', 'Product Gamma', 200, 180, 220, 240, 260, 280],
            ['ITEM004', 'Product Delta', 75, 85, 95, 105, 115, 125]
        ];
        
        $sheet->fromArray($sampleData, null, 'A2');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        
        // Add instructions
        $sheet->setCellValue('A7', 'Instructions:');
        $sheet->setCellValue('A8', '1. Replace sample data with your actual item codes and forecasts');
        $sheet->setCellValue('A9', '2. Ensure item codes match your system exactly');
        $sheet->setCellValue('A10', '3. Use YYYY-MM format for date columns');
        $sheet->setCellValue('A11', '4. Enter numeric values only for quantities');
        $sheet->setCellValue('A12', '5. Save as Excel format (.xlsx) before uploading');
        
        $sheet->getStyle('A7')->getFont()->setBold(true);
        $sheet->getStyle('A8:A12')->getFont()->setItalic(true);
    }

    /**
     * Create quarterly template
     */
    private function createQuarterlyTemplate($sheet)
    {
        // Headers
        $headers = ['Product SKU', 'Product Description', 'Q1-2024', 'Q2-2024', 'Q3-2024', 'Q4-2024'];
        $sheet->fromArray([$headers], null, 'A1');
        
        // Sample data
        $sampleData = [
            ['SKU001', 'Widget Standard', 300, 350, 400, 450],
            ['SKU002', 'Widget Premium', 150, 175, 200, 225],
            ['SKU003', 'Gadget Basic', 500, 550, 600, 650],
            ['SKU004', 'Gadget Advanced', 200, 220, 240, 260]
        ];
        
        $sheet->fromArray($sampleData, null, 'A2');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '70AD47']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        
        // Add instructions
        $sheet->setCellValue('A7', 'Quarterly Forecast Template');
        $sheet->setCellValue('A8', '- Use this template for quarterly forecasting');
        $sheet->setCellValue('A9', '- Format: Q1-YYYY, Q2-YYYY, etc.');
        $sheet->setCellValue('A10', '- Each quarter represents 3 months of forecast');
    }

    /**
     * Create vertical layout template
     */
    private function createVerticalTemplate($sheet)
    {
        // Headers
        $headers = ['Item Code', 'Period', 'Forecast Quantity'];
        $sheet->fromArray([$headers], null, 'A1');
        
        // Sample data (vertical layout)
        $sampleData = [
            ['ITEM001', '2024-01', 100],
            ['ITEM001', '2024-02', 150],
            ['ITEM001', '2024-03', 200],
            ['ITEM002', '2024-01', 50],
            ['ITEM002', '2024-02', 75],
            ['ITEM002', '2024-03', 80],
            ['ITEM003', '2024-01', 200],
            ['ITEM003', '2024-02', 180],
            ['ITEM003', '2024-03', 220]
        ];
        
        $sheet->fromArray($sampleData, null, 'A2');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => 'FFC000']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
        
        // Add instructions
        $sheet->setCellValue('A12', 'Vertical Layout Template');
        $sheet->setCellValue('A13', '- One row per item-period combination');
        $sheet->setCellValue('A14', '- Useful for large datasets with many periods');
        $sheet->setCellValue('A15', '- Easy to extend with additional columns');
    }

    // ============================================================================
    // HELPER METHODS FOR FORECASTING CALCULATIONS
    // ============================================================================

    /**
     * Get historical sales data for forecast generation
     *
     * @param  int  $customerId
     * @param  int|null  $itemId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getHistoricalSalesData($customerId, $itemId = null)
    {
        $query = SOLine::join('SalesOrder', 'SOLine.sales_order_id', '=', 'SalesOrder.sales_order_id')
            ->where('SalesOrder.customer_id', $customerId)
            ->where('SalesOrder.status', 'Completed')
            ->selectRaw('
                SOLine.item_id,
                DATE_FORMAT(SalesOrder.order_date, "%Y-%m") as period,
                SUM(SOLine.quantity) as total_quantity
            ')
            ->groupBy('SOLine.item_id', 'period')
            ->orderBy('period');

        if ($itemId) {
            $query->where('SOLine.item_id', $itemId);
        }

        return $query->get();
    }

    /**
     * Generate moving average forecasts
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $historicalData
     * @param  int  $periods
     * @return array
     */
    private function generateMovingAverageForecasts($historicalData, $periods)
    {
        $forecasts = [];
        $windowSize = 3; // Use 3-month moving average

        // Group by item
        $groupedData = $historicalData->groupBy('item_id');

        foreach ($groupedData as $itemId => $itemData) {
            $quantities = $itemData->pluck('total_quantity')->toArray();
            
            if (count($quantities) >= $windowSize) {
                // Calculate moving average for last window
                $lastValues = array_slice($quantities, -$windowSize);
                $average = array_sum($lastValues) / count($lastValues);

                // Generate forecasts for requested periods
                $item = Item::find($itemId);
                if ($item) {
                    $itemForecasts = [];
                    $baseDate = now()->startOfMonth();

                    for ($i = 1; $i <= $periods; $i++) {
                        $forecastDate = $baseDate->copy()->addMonths($i);
                        $itemForecasts[] = [
                            'item_code' => $item->item_code,
                            'item_name' => $item->name,
                            'period' => $forecastDate->format('Y-m-d'),
                            'forecast_quantity' => round($average, 0),
                            'method' => 'moving_average',
                            'confidence' => 0.7
                        ];
                    }
                    $forecasts[] = $itemForecasts;
                }
            }
        }

        return $forecasts;
    }

    /**
     * Generate trend-based forecasts
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $historicalData
     * @param  int  $periods
     * @return array
     */
    private function generateTrendForecasts($historicalData, $periods)
    {
        $forecasts = [];

        // Group by item
        $groupedData = $historicalData->groupBy('item_id');

        foreach ($groupedData as $itemId => $itemData) {
            $quantities = $itemData->pluck('total_quantity')->toArray();
            
            if (count($quantities) >= 3) {
                // Calculate linear trend
                $trend = $this->calculateLinearTrend($quantities);
                $lastValue = end($quantities);

                // Generate forecasts for requested periods
                $item = Item::find($itemId);
                if ($item) {
                    $itemForecasts = [];
                    $baseDate = now()->startOfMonth();

                    for ($i = 1; $i <= $periods; $i++) {
                        $forecastDate = $baseDate->copy()->addMonths($i);
                        $forecastValue = $lastValue + ($trend * $i);
                        $forecastValue = max(0, $forecastValue); // Ensure non-negative

                        $itemForecasts[] = [
                            'item_code' => $item->item_code,
                            'item_name' => $item->name,
                            'period' => $forecastDate->format('Y-m-d'),
                            'forecast_quantity' => round($forecastValue, 0),
                            'method' => 'trend_analysis',
                            'confidence' => 0.6
                        ];
                    }
                    $forecasts[] = $itemForecasts;
                }
            }
        }

        return $forecasts;
    }

    /**
     * Generate seasonal forecasts
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $historicalData
     * @param  int  $periods
     * @return array
     */
    private function generateSeasonalForecasts($historicalData, $periods)
    {
        $forecasts = [];

        // Group by item
        $groupedData = $historicalData->groupBy('item_id');

        foreach ($groupedData as $itemId => $itemData) {
            $quantities = $itemData->pluck('total_quantity')->toArray();
            
            if (count($quantities) >= 12) { // Need at least 1 year of data
                // Calculate seasonal factors
                $seasonalFactors = $this->calculateSeasonalFactors($quantities);
                $baseValue = array_sum($quantities) / count($quantities);

                // Generate forecasts for requested periods
                $item = Item::find($itemId);
                if ($item) {
                    $itemForecasts = [];
                    $baseDate = now()->startOfMonth();

                    for ($i = 1; $i <= $periods; $i++) {
                        $forecastDate = $baseDate->copy()->addMonths($i);
                        $month = $forecastDate->month;
                        $seasonalFactor = $seasonalFactors[$month - 1] ?? 1.0;
                        $forecastValue = $baseValue * $seasonalFactor;

                        $itemForecasts[] = [
                            'item_code' => $item->item_code,
                            'item_name' => $item->name,
                            'period' => $forecastDate->format('Y-m-d'),
                            'forecast_quantity' => round($forecastValue, 0),
                            'method' => 'seasonal',
                            'confidence' => 0.8
                        ];
                    }
                    $forecasts[] = $itemForecasts;
                }
            }
        }

        return $forecasts;
    }

    /**
     * Calculate linear trend from data points
     *
     * @param  array  $values
     * @return float
     */
    private function calculateLinearTrend($values)
    {
        $n = count($values);
        $x = range(1, $n);
        $y = $values;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        // Calculate slope (trend)
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        return $slope;
    }

    /**
     * Calculate seasonal factors
     *
     * @param  array  $values
     * @return array
     */
    private function calculateSeasonalFactors($values)
    {
        $factors = array_fill(0, 12, 1.0);
        $monthlyTotals = array_fill(0, 12, 0);
        $monthlyCounts = array_fill(0, 12, 0);

        // Group values by month (assuming sequential monthly data)
        for ($i = 0; $i < count($values); $i++) {
            $month = $i % 12;
            $monthlyTotals[$month] += $values[$i];
            $monthlyCounts[$month]++;
        }

        // Calculate average for each month
        $overall_average = array_sum($values) / count($values);
        
        for ($month = 0; $month < 12; $month++) {
            if ($monthlyCounts[$month] > 0) {
                $monthlyAverage = $monthlyTotals[$month] / $monthlyCounts[$month];
                $factors[$month] = $overall_average > 0 ? $monthlyAverage / $overall_average : 1.0;
            }
        }

        return $factors;
    }

    /**
     * Calculate trend from forecast data
     *
     * @param  \Illuminate\Support\Collection  $trendData
     * @return array
     */
    private function calculateTrend($trendData)
    {
        if ($trendData->count() < 2) {
            return [
                'direction' => 'insufficient_data',
                'slope' => 0,
                'growth_rate' => 0
            ];
        }

        $values = $trendData->pluck('total_quantity')->toArray();
        $slope = $this->calculateLinearTrend($values);
        
        $firstValue = $values[0];
        $lastValue = end($values);
        $growthRate = $firstValue > 0 ? (($lastValue - $firstValue) / $firstValue) * 100 : 0;

        $direction = 'stable';
        if ($slope > 0.1) {
            $direction = 'increasing';
        } elseif ($slope < -0.1) {
            $direction = 'decreasing';
        }

        return [
            'direction' => $direction,
            'slope' => round($slope, 2),
            'growth_rate' => round($growthRate, 2)
        ];
    }
}