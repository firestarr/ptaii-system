<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesForecast;
use App\Models\Sales\Customer;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class AIExcelForecastController extends Controller
{
    /**
     * Process Excel file with AI and preview extraction
     */
    public function processExcelWithAI(Request $request)
    {
        // Preprocess preview_only to convert string "true"/"false" to boolean true/false
        if ($request->has('preview_only')) {
            $previewOnlyInput = $request->input('preview_only');
            if (is_string($previewOnlyInput)) {
                if (strtolower($previewOnlyInput) === 'true') {
                    $request->merge(['preview_only' => true]);
                } elseif (strtolower($previewOnlyInput) === 'false') {
                    $request->merge(['preview_only' => false]);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'preview_only' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('excel_file');
            $previewOnly = $request->input('preview_only', true);
            
            // Load spreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Convert to array for processing
            $data = $worksheet->toArray(null, true, true, true);
            
            // Log processing info
            Log::info('Excel Processing Started', [
                'total_rows' => count($data),
                'customer_id' => $request->customer_id,
                'preview_only' => $previewOnly
            ]);
            
            // First try direct processing without AI for this structured format
            $directExtraction = $this->directExcelExtractionImproved($data);
            
            if ($directExtraction['success'] && !empty($directExtraction['data']['items'])) {
                $extractedData = $directExtraction['data'];
                Log::info('Direct extraction successful', [
                    'items_extracted' => count($extractedData['items'])
                ]);
            } else {
                // Fallback to AI processing if direct extraction fails
                Log::info('Direct extraction failed, trying AI', [
                    'error' => $directExtraction['error'] ?? 'Unknown error'
                ]);
                
                $processedData = $this->preprocessExcelDataImproved($data, 200); // Increased limit
                $aiResponse = $this->processWithGroqAI($processedData);
                
                if (!$aiResponse['success']) {
                    return response()->json([
                        'message' => 'Both direct extraction and AI processing failed',
                        'direct_error' => $directExtraction['error'] ?? 'No direct extraction error',
                        'ai_error' => $aiResponse['error'],
                        'debug_info' => [
                            'total_rows_in_excel' => count($data),
                            'processed_rows' => count($processedData),
                            'direct_debug' => $directExtraction['debug'] ?? null
                        ]
                    ], 500);
                }
                
                $extractedData = $aiResponse['data'];
                Log::info('AI extraction successful', [
                    'items_extracted' => count($extractedData['items'] ?? [])
                ]);
            }
            
            // Validate and prepare forecast data
            $validationResult = $this->validateExtractedData($extractedData, $request->customer_id);
            
            if ($previewOnly) {
                return response()->json([
                    'success' => true,
                    'message' => 'Excel processed successfully',
                    'data' => [
                        'supplier_info' => $extractedData['supplier_info'] ?? null,
                        'forecast_periods' => $extractedData['forecast_periods'] ?? [],
                        'items' => $validationResult['valid_items'],
                        'warnings' => $validationResult['warnings'],
                        'errors' => $validationResult['errors'],
                        'summary' => [
                            'total_items' => count($extractedData['items'] ?? []),
                            'valid_items' => count($validationResult['valid_items']),
                            'total_forecasts' => $validationResult['total_forecasts'],
                            'periods_count' => count($extractedData['forecast_periods'] ?? [])
                        ],
                        'debug_info' => $directExtraction['debug'] ?? null
                    ]
                ], 200);
            }
            
            // Save to database if not preview only
            if ($validationResult['has_valid_data']) {
                $savedResult = $this->saveToDatabase(
                    $validationResult['valid_items'],
                    $request->customer_id,
                    $request->forecast_issue_date
                );
                
                return response()->json([
                    'success' => true,
                    'message' => 'Forecasts saved successfully',
                    'data' => $savedResult
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid forecast data found',
                    'errors' => $validationResult['errors']
                ], 422);
            }
            
        } catch (\Exception $e) {
            Log::error('Excel processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Excel file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * IMPROVED: Direct Excel extraction for structured purchase order forecast format
     */
    private function directExcelExtractionImproved($data)
    {
        try {
            $result = [
                'supplier_info' => null,
                'forecast_periods' => [],
                'items' => []
            ];
            
            $headerRowIndex = null;
            $monthColumns = [];
            $debugInfo = [
                'total_rows' => count($data),
                'processed_rows' => 0,
                'skipped_rows' => 0,
                'invalid_material_codes' => [],
                'header_search_log' => [],
                'month_columns_log' => []
            ];
            
            // Find supplier info and header row
            foreach ($data as $rowIndex => $row) {
                if (!is_array($row)) continue;
                
                $rowText = strtolower(implode(' ', array_filter($row)));
                $debugInfo['header_search_log'][] = "Row {$rowIndex}: " . substr($rowText, 0, 100);
                
                // Extract supplier info - more flexible patterns
                if (!$result['supplier_info']) {
                    if (strpos($rowText, 'supplier') !== false || 
                        strpos($rowText, 'armstrong') !== false ||
                        strpos($rowText, 'yamaha') !== false ||
                        strpos($rowText, 'vendor') !== false) {
                        $result['supplier_info'] = [
                            'name' => $this->extractSupplierName($row),
                            'code' => $this->extractSupplierCode($row)
                        ];
                    }
                }
                
                // IMPROVED: Find header row with more flexible matching
                $hasMaterialColumn = (strpos($rowText, 'material') !== false || 
                                    strpos($rowText, 'item') !== false || 
                                    strpos($rowText, 'part') !== false ||
                                    strpos($rowText, 'code') !== false);
                                    
                $hasDescColumn = (strpos($rowText, 'description') !== false || 
                                strpos($rowText, 'desc') !== false ||
                                strpos($rowText, 'name') !== false);
                
                if ($hasMaterialColumn && $hasDescColumn) {
                    $headerRowIndex = $rowIndex;
                    
                    // Extract month columns
                    foreach ($row as $colIndex => $cell) {
                        if ($this->isMonthHeaderImproved($cell)) {
                            $convertedDate = $this->convertMonthToDateImproved($cell);
                            if ($convertedDate) {
                                $monthColumns[$colIndex] = $convertedDate;
                                $debugInfo['month_columns_log'][] = "Col {$colIndex}: '{$cell}' -> {$convertedDate}";
                            }
                        }
                    }
                    $result['forecast_periods'] = array_values(array_unique($monthColumns));
                    break;
                }
            }
            
            if ($headerRowIndex === null) {
                return [
                    'success' => false,
                    'error' => 'Could not find header row. Searched for: material/item/part/code + description/desc/name',
                    'debug' => $debugInfo
                ];
            }
            
            if (empty($monthColumns)) {
                return [
                    'success' => false,
                    'error' => 'Could not find month columns in header row',
                    'debug' => $debugInfo
                ];
            }
            
            // IMPROVED: Extract material data with comprehensive logging
            $debugInfo['loop_details'] = [];
            $debugInfo['empty_rows'] = 0;
            $debugInfo['no_forecast_rows'] = 0;
            $debugInfo['total_loops'] = 0;
            
            for ($i = $headerRowIndex + 1; $i < count($data); $i++) {
                $debugInfo['total_loops']++;
                $row = $data[$i];
                
                if (!is_array($row)) {
                    $debugInfo['loop_details'][] = "Row {$i}: Not array, skipped";
                    continue;
                }
                
                $materialCode = trim($row['A'] ?? '');
                $description = trim($row['B'] ?? '');
                $uom = trim($row['C'] ?? '');
                
                // Log every row for debugging
                if ($debugInfo['total_loops'] <= 50) { // Log first 50 for debugging
                    $debugInfo['loop_details'][] = "Row {$i}: Code='{$materialCode}', Desc='{$description}'";
                }
                
                // Skip completely empty rows
                if (empty($materialCode) && empty($description)) {
                    $debugInfo['empty_rows']++;
                    continue;
                }
                
                // IMPROVED: More flexible material code validation
                if (empty($materialCode)) {
                    $debugInfo['skipped_rows']++;
                    $debugInfo['loop_details'][] = "Row {$i}: Empty material code, skipped";
                    continue;
                }
                
                if (!$this->isValidMaterialCode($materialCode)) {
                    $debugInfo['invalid_material_codes'][] = $materialCode;
                    $debugInfo['skipped_rows']++;
                    $debugInfo['loop_details'][] = "Row {$i}: Invalid material code '{$materialCode}', skipped";
                    continue;
                }
                
                // Extract forecasts with detailed logging
                $forecasts = [];
                $forecastDetails = [];
                foreach ($monthColumns as $colIndex => $dateString) {
                    $quantity = $row[$colIndex] ?? '';
                    $forecastDetails[] = "Col {$colIndex}({$dateString}): '{$quantity}'";
                    
                    // FIXED: Clean and validate numeric values
                    $cleanQuantity = $this->cleanNumericValue($quantity);
                    if ($cleanQuantity !== null && $cleanQuantity > 0) {
                        $forecasts[$dateString] = $cleanQuantity;
                    }
                }
                
                // Log forecast details for first few items
                if ($debugInfo['total_loops'] <= 10) {
                    $debugInfo['loop_details'][] = "Row {$i} forecasts: " . implode(', ', $forecastDetails);
                }
                
                // Include items even without forecasts for debugging
                if (!empty($forecasts)) {
                    $result['items'][] = [
                        'material_code' => $materialCode,
                        'description' => $description,
                        'uom' => $uom,
                        'forecasts' => $forecasts
                    ];
                    $debugInfo['processed_rows']++;
                } else {
                    $debugInfo['no_forecast_rows']++;
                    if ($debugInfo['no_forecast_rows'] <= 10) { // Log first 10 items without forecasts
                        $debugInfo['loop_details'][] = "Row {$i}: '{$materialCode}' has no forecast data";
                    }
                }
            }
            
            return [
                'success' => true,
                'data' => $result,
                'debug' => $debugInfo
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Direct extraction failed: ' . $e->getMessage(),
                'debug' => $debugInfo ?? []
            ];
        }
    }
    
    /**
     * FIXED: Clean numeric value from string (handle commas, spaces, etc.)
     */
    private function cleanNumericValue($value)
    {
        if (empty($value) || $value === '-' || $value === '' || $value === null) {
            return null;
        }
        
        // Convert to string first
        $value = (string)$value;
        
        // Remove common non-numeric characters but keep decimal points
        $cleaned = preg_replace('/[^\d\.\-]/', '', $value);
        
        // Check if it's a valid number after cleaning
        if (is_numeric($cleaned)) {
            return (float)$cleaned;
        }
        
        return null;
    }
    
    /**
     * IMPROVED: More flexible material code validation
     */
    private function isValidMaterialCode($code)
    {
        if (empty($code) || strlen($code) < 3) {
            return false;
        }
        
        // Accept various patterns:
        // - VDH7740 (original pattern)
        // - V-DH-7740 (with dashes)
        // - VDH_7740 (with underscores)
        // - vdh7740 (lowercase)
        // - VDH7740A (with suffix letter)
        // - 12345ABC (numeric start)
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_\.]*[A-Za-z0-9]$/', $code) || 
               preg_match('/^[A-Za-z0-9]{3,}$/', $code);
    }
    
    /**
     * Extract supplier name from row
     */
    private function extractSupplierName($row)
    {
        foreach ($row as $cell) {
            if (is_string($cell) && (
                strpos(strtoupper($cell), 'ARMSTRONG') !== false ||
                strpos(strtoupper($cell), 'YAMAHA') !== false ||
                (strlen(trim($cell)) > 5 && preg_match('/^[A-Z\s&\.]+$/', strtoupper($cell)))
            )) {
                return trim($cell);
            }
        }
        return null;
    }
    
    /**
     * Extract supplier code from row
     */
    private function extractSupplierCode($row)
    {
        foreach ($row as $cell) {
            if (is_string($cell) && (
                preg_match('/^[A-Z]\d{5}$/', trim($cell)) || // u90015 pattern
                preg_match('/^[A-Z]{2,}\d{3,}$/', strtoupper(trim($cell))) // General supplier code
            )) {
                return trim($cell);
            }
        }
        return null;
    }
    
    /**
     * IMPROVED: More flexible month header detection
     */
    private function isMonthHeaderImproved($cell)
    {
        if (!is_string($cell)) return false;
        
        $cell = trim($cell);
        if (empty($cell)) return false;
        
        // Match various month patterns:
        // - Apr-25, May-25 (original)
        // - Apr 25, May 25 (with space)
        // - April-2025, May-2025 (full names)
        // - 04/25, 05/25 (numeric)
        // - Apr'25, May'25 (with apostrophe)
        $patterns = [
            '/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s\-\']?\d{2,4}$/i',
            '/^(January|February|March|April|May|June|July|August|September|October|November|December)[\s\-\']?\d{2,4}$/i',
            '/^\d{1,2}[\s\/\-\.]\d{2,4}$/',
            '/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s\-\']\d{2}$/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cell)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * IMPROVED: More flexible month to date conversion
     */
    private function convertMonthToDateImproved($monthHeader)
    {
        $monthHeader = trim($monthHeader);
        
        try {
            // Handle Month-YY or Month YY format
            if (preg_match('/^([A-Za-z]{3,})[\s\-\']?(\d{2,4})$/', $monthHeader, $matches)) {
                $month = $matches[1];
                $year = $matches[2];
                
                // Convert 2-digit year to 4-digit
                if (strlen($year) == 2) {
                    $yearInt = intval($year);
                    // Assume 00-30 = 2000-2030, 31-99 = 1931-1999
                    $year = $yearInt <= 30 ? '20' . $year : '19' . $year;
                }
                
                // Try short month name first
                try {
                    $date = Carbon::createFromFormat('M Y', $month . ' ' . $year);
                    return $date->format('Y-m-01');
                } catch (\Exception $e) {
                    // Try full month name
                    try {
                        $date = Carbon::createFromFormat('F Y', $month . ' ' . $year);
                        return $date->format('Y-m-01');
                    } catch (\Exception $e2) {
                        // Manual month mapping as fallback
                        $monthMap = [
                            'jan' => '01', 'january' => '01',
                            'feb' => '02', 'february' => '02',
                            'mar' => '03', 'march' => '03',
                            'apr' => '04', 'april' => '04',
                            'may' => '05',
                            'jun' => '06', 'june' => '06',
                            'jul' => '07', 'july' => '07',
                            'aug' => '08', 'august' => '08',
                            'sep' => '09', 'september' => '09',
                            'oct' => '10', 'october' => '10',
                            'nov' => '11', 'november' => '11',
                            'dec' => '12', 'december' => '12'
                        ];
                        
                        $monthLower = strtolower($month);
                        if (isset($monthMap[$monthLower])) {
                            return $year . '-' . $monthMap[$monthLower] . '-01';
                        }
                    }
                }
            }
            
            // Handle numeric format (MM/YY or MM-YY)
            if (preg_match('/^(\d{1,2})[\s\/\-\.](\d{2,4})$/', $monthHeader, $matches)) {
                $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $year = $matches[2];
                
                if (strlen($year) == 2) {
                    $yearInt = intval($year);
                    $year = $yearInt <= 30 ? '20' . $year : '19' . $year;
                }
                
                return $year . '-' . $month . '-01';
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to convert month header', [
                'header' => $monthHeader,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * IMPROVED: Preprocessing with configurable limits
     */
    private function preprocessExcelDataImproved($data, $maxRows = 200)
    {
        $processed = [];
        $contextRows = 0;
        
        foreach ($data as $rowIndex => $row) {
            if (!is_array($row)) continue;
            
            // Clean row and keep non-empty values
            $cleanRow = [];
            foreach ($row as $colIndex => $cell) {
                if ($cell !== null && $cell !== '') {
                    $cleanRow[$colIndex] = $cell;
                }
            }
            
            if (empty($cleanRow)) continue;
            
            $rowText = strtolower(implode(' ', $cleanRow));
            
            // Include rows that might contain important information
            $isImportant = (
                strpos($rowText, 'purchase') !== false ||
                strpos($rowText, 'forecast') !== false ||
                strpos($rowText, 'supplier') !== false ||
                strpos($rowText, 'vendor') !== false ||
                strpos($rowText, 'material') !== false ||
                strpos($rowText, 'item') !== false ||
                strpos($rowText, 'part') !== false ||
                strpos($rowText, 'code') !== false ||
                strpos($rowText, 'description') !== false ||
                strpos($rowText, 'desc') !== false ||
                strpos($rowText, 'name') !== false ||
                strpos($rowText, 'uom') !== false ||
                strpos($rowText, 'unit') !== false ||
                preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[\s\-\']?\d{2,4}\b/', $rowText) ||
                preg_match('/^[A-Za-z]{2,}\d+/', trim($cleanRow[array_keys($cleanRow)[0]] ?? '')) || // Material codes
                count($cleanRow) > 5 // Rows with many columns might be data
            );
            
            if ($isImportant) {
                $processed[$rowIndex] = $cleanRow;
                $contextRows++;
            }
            
            // Configurable limit
            if ($contextRows >= $maxRows) break;
        }
        
        return $processed;
    }
    
    /**
     * Process data with Groq AI (improved prompt)
     */
    private function processWithGroqAI($data)
    {
        try {
            $groqApiKey = env('GROQ_API_KEY');
            
            if (!$groqApiKey) {
                return [
                    'success' => false,
                    'error' => 'Groq API key not configured'
                ];
            }
            
            $model = 'llama3-8b-8192';
            $prompt = $this->buildImprovedGroqPrompt($data);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $groqApiKey,
                'Content-Type' => 'application/json'
            ])->timeout(60)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert at extracting purchase order forecast data from Excel files. Focus on finding material codes, descriptions, UOM, and monthly quantity forecasts. Return only valid JSON.'
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
            $content = $responseData['choices'][0]['message']['content'] ?? '';
            
            // Clean JSON response
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*$/', '', $content);
            $content = trim($content);
            
            $extractedData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response from AI: ' . json_last_error_msg()
                ];
            }
            
            return [
                'success' => true,
                'data' => $extractedData
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Groq AI processing error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Build improved prompt for Groq AI
     */
    private function buildImprovedGroqPrompt($data)
    {
        $dataString = json_encode($data, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
This is a Purchase Order Forecast Excel file. Extract the data and return JSON in this exact format:

{
  "supplier_info": {
    "name": "ARMSTRONG" or supplier name found,
    "code": "u90015" or supplier code found
  },
  "forecast_periods": ["2025-04-01", "2025-05-01", "2025-06-01"],
  "items": [
    {
      "material_code": "VAP4460",
      "description": "BATI NONWOVEN CLOTH 00122",
      "uom": "PC",
      "forecasts": {
        "2025-04-01": 22000,
        "2025-05-01": 22000,
        "2025-06-01": 26000
      }
    }
  ]
}

IMPORTANT EXTRACTION RULES:
1. Find supplier info at the top (look for "ARMSTRONG", "YAMAHA" or codes like "u90015")
2. Find month headers like "Apr-25", "Apr 25", "April-2025" and convert to "2025-04-01" format
3. Find material codes starting with letters like "VAP4460", "VDH7740", "VEG2610" 
4. Get descriptions from the column next to material codes
5. Get UOM (usually "PC", "PCS", "EACH")
6. Extract numeric quantities for each month (ignore empty cells)
7. Only include items that have material codes and at least one quantity > 0
8. Accept material codes with various formats (letters, numbers, dashes, underscores)

Excel Data:
$dataString
PROMPT;
    }
    
    /**
     * DEBUG: Method untuk debug ekstraksi Excel yang lebih detail
     */
    public function debugExcelExtractionDetailed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray(null, true, true, true);
            
            // Find header row
            $headerRowIndex = null;
            $monthColumns = [];
            
            foreach ($data as $rowIndex => $row) {
                if (!is_array($row)) continue;
                
                $rowText = strtolower(implode(' ', array_filter($row)));
                $hasMaterial = (strpos($rowText, 'material') !== false || 
                              strpos($rowText, 'item') !== false || 
                              strpos($rowText, 'part') !== false ||
                              strpos($rowText, 'code') !== false);
                              
                $hasDesc = (strpos($rowText, 'description') !== false || 
                          strpos($rowText, 'desc') !== false ||
                          strpos($rowText, 'name') !== false);
                          
                if ($hasMaterial && $hasDesc) {
                    $headerRowIndex = $rowIndex;
                    
                    // Extract month columns
                    foreach ($row as $colIndex => $cell) {
                        if ($this->isMonthHeaderImproved($cell)) {
                            $convertedDate = $this->convertMonthToDateImproved($cell);
                            if ($convertedDate) {
                                $monthColumns[$colIndex] = $convertedDate;
                            }
                        }
                    }
                    break;
                }
            }
            
            // Analyze ALL rows after header
            $analysis = [
                'excel_info' => [
                    'total_rows' => count($data),
                    'header_row_index' => $headerRowIndex,
                    'month_columns_count' => count($monthColumns),
                    'month_columns' => $monthColumns
                ],
                'all_material_rows' => [],
                'statistics' => [
                    'total_rows_analyzed' => 0,
                    'rows_with_material_code' => 0,
                    'rows_with_valid_material_code' => 0,
                    'rows_with_forecasts' => 0,
                    'empty_rows' => 0,
                    'rows_in_database' => 0
                ]
            ];
            
            if ($headerRowIndex !== null) {
                for ($i = $headerRowIndex + 1; $i < count($data); $i++) {
                    $analysis['statistics']['total_rows_analyzed']++;
                    $row = $data[$i];
                    
                    if (!is_array($row)) continue;
                    
                    $materialCode = trim($row['A'] ?? '');
                    $description = trim($row['B'] ?? '');
                    $uom = trim($row['C'] ?? '');
                    
                    // Skip completely empty rows
                    if (empty($materialCode) && empty($description)) {
                        $analysis['statistics']['empty_rows']++;
                        continue;
                    }
                    
                    $rowAnalysis = [
                        'excel_row' => $i,
                        'material_code' => $materialCode,
                        'description' => $description,
                        'uom' => $uom,
                        'has_material_code' => !empty($materialCode),
                        'is_valid_material_code' => false,
                        'in_database' => false,
                        'forecast_count' => 0,
                        'forecasts' => []
                    ];
                    
                    if (!empty($materialCode)) {
                        $analysis['statistics']['rows_with_material_code']++;
                        $rowAnalysis['is_valid_material_code'] = $this->isValidMaterialCode($materialCode);
                        
                        if ($rowAnalysis['is_valid_material_code']) {
                            $analysis['statistics']['rows_with_valid_material_code']++;
                            
                            // Check if exists in database
                            $item = Item::where('item_code', $materialCode)
                                       ->orWhere('item_code', strtoupper($materialCode))
                                       ->orWhere('item_code', strtolower($materialCode))
                                       ->first();
                            
                            if ($item) {
                                $rowAnalysis['in_database'] = true;
                                $rowAnalysis['item_id'] = $item->item_id;
                                $rowAnalysis['item_name'] = $item->name;
                                $analysis['statistics']['rows_in_database']++;
                            }
                            
                            // Extract forecasts
                            foreach ($monthColumns as $colIndex => $dateString) {
                                $quantity = $row[$colIndex] ?? '';
                                // FIXED: Use same cleaning function for consistency
                                $cleanQuantity = $this->cleanNumericValue($quantity);
                                if ($cleanQuantity !== null && $cleanQuantity > 0) {
                                    $rowAnalysis['forecasts'][$dateString] = $cleanQuantity;
                                    $rowAnalysis['forecast_count']++;
                                }
                            }
                            
                            if ($rowAnalysis['forecast_count'] > 0) {
                                $analysis['statistics']['rows_with_forecasts']++;
                            }
                        }
                    }
                    
                    $analysis['all_material_rows'][] = $rowAnalysis;
                    
                    // Limit to prevent memory issues, but show more data
                    if (count($analysis['all_material_rows']) >= 150) {
                        $analysis['note'] = 'Analysis limited to first 150 rows to prevent memory issues';
                        break;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'analysis' => $analysis
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    /**
     * Save AI extracted forecasts to database
     */
    public function saveExtractedForecasts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,item_id',
            'items.*.forecasts' => 'required|array',
            'items.*.forecasts.*.period' => 'required|date',
            'items.*.forecasts.*.quantity' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $savedCount = 0;
            $updatedCount = 0;

            foreach ($request->items as $itemData) {
                foreach ($itemData['forecasts'] as $forecastData) {
                    $existingForecast = SalesForecast::where('customer_id', $request->customer_id)
                        ->where('item_id', $itemData['item_id'])
                        ->where('forecast_period', $forecastData['period'])
                        ->where('is_current_version', true)
                        ->first();

                    if ($existingForecast) {
                        $existingForecast->is_current_version = false;
                        $existingForecast->save();

                        SalesForecast::create([
                            'customer_id' => $request->customer_id,
                            'item_id' => $itemData['item_id'],
                            'forecast_period' => $forecastData['period'],
                            'forecast_quantity' => $forecastData['quantity'],
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'Customer-AI',
                            'confidence_level' => 0.95,
                            'forecast_issue_date' => $request->forecast_issue_date,
                            'submission_date' => now(),
                            'is_current_version' => true,
                            'previous_version_id' => $existingForecast->forecast_id
                        ]);
                        
                        $updatedCount++;
                    } else {
                        SalesForecast::create([
                            'customer_id' => $request->customer_id,
                            'item_id' => $itemData['item_id'],
                            'forecast_period' => $forecastData['period'],
                            'forecast_quantity' => $forecastData['quantity'],
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'Customer-AI',
                            'confidence_level' => 0.95,
                            'forecast_issue_date' => $request->forecast_issue_date,
                            'submission_date' => now(),
                            'is_current_version' => true
                        ]);
                        
                        $savedCount++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Forecasts saved successfully',
                'data' => [
                    'new_forecasts' => $savedCount,
                    'updated_forecasts' => $updatedCount,
                    'total_saved' => $savedCount + $updatedCount
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save forecasts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get AI processing history
     */
    public function getProcessingHistory(Request $request)
    {
        $query = SalesForecast::where('forecast_source', 'Customer-AI')
            ->where('is_current_version', true)
            ->with(['customer', 'item']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('start_date')) {
            $query->where('forecast_issue_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('forecast_issue_date', '<=', $request->end_date);
        }

        $forecasts = $query->orderBy('submission_date', 'desc')->paginate(50);

        return response()->json($forecasts, 200);
    }
    
    /**
     * Validate extracted data against database
     */
    private function validateExtractedData($extractedData, $customerId)
    {
        $validItems = [];
        $warnings = [];
        $errors = [];
        $totalForecasts = 0;
        
        if (!isset($extractedData['items']) || !is_array($extractedData['items'])) {
            $errors[] = 'No items found in extracted data';
            return [
                'valid_items' => [],
                'warnings' => $warnings,
                'errors' => $errors,
                'has_valid_data' => false,
                'total_forecasts' => 0
            ];
        }
        
        foreach ($extractedData['items'] as $itemData) {
            $materialCode = $itemData['material_code'] ?? null;
            
            if (!$materialCode) {
                $warnings[] = 'Item without material code skipped';
                continue;
            }
            
            // Find item in database with more flexible matching
            $item = Item::where('item_code', $materialCode)
                       ->orWhere('item_code', strtoupper($materialCode))
                       ->orWhere('item_code', strtolower($materialCode))
                       ->first();
            
            if (!$item) {
                $warnings[] = "Item '{$materialCode}' not found in database";
                continue;
            }
            
            // Validate forecasts
            $validForecasts = [];
            $forecasts = $itemData['forecasts'] ?? [];
            
            foreach ($forecasts as $period => $quantity) {
                if (!$this->isValidDateFormat($period)) {
                    $warnings[] = "Invalid period format '{$period}' for item {$materialCode}";
                    continue;
                }
                
                // FIXED: Use same cleaning function
                $numericQuantity = $this->cleanNumericValue($quantity);
                
                if ($numericQuantity === null) {
                    continue; // Skip invalid quantities
                }
                
                if ($numericQuantity < 0) {
                    $warnings[] = "Negative quantity for {$materialCode} in {$period}, set to 0";
                    $numericQuantity = 0;
                }
                
                if ($numericQuantity > 0) {
                    $validForecasts[] = [
                        'period' => $period,
                        'quantity' => $numericQuantity
                    ];
                    $totalForecasts++;
                }
            }
            
            if (!empty($validForecasts)) {
                $validItems[] = [
                    'item_id' => $item->item_id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->name,
                    'description' => $itemData['description'] ?? '',
                    'uom' => $itemData['uom'] ?? '',
                    'forecasts' => $validForecasts
                ];
            }
        }
        
        return [
            'valid_items' => $validItems,
            'warnings' => $warnings,
            'errors' => $errors,
            'has_valid_data' => !empty($validItems),
            'total_forecasts' => $totalForecasts
        ];
    }
    
    /**
     * Save forecast data to database
     */
    private function saveToDatabase($validItems, $customerId, $issueDate)
    {
        DB::beginTransaction();
        
        try {
            $savedCount = 0;
            $updatedCount = 0;
            
            foreach ($validItems as $itemData) {
                foreach ($itemData['forecasts'] as $forecastData) {
                    $existingForecast = SalesForecast::where('customer_id', $customerId)
                        ->where('item_id', $itemData['item_id'])
                        ->where('forecast_period', $forecastData['period'])
                        ->where('is_current_version', true)
                        ->first();
                    
                    if ($existingForecast) {
                        $existingForecast->is_current_version = false;
                        $existingForecast->save();
                        
                        SalesForecast::create([
                            'customer_id' => $customerId,
                            'item_id' => $itemData['item_id'],
                            'forecast_period' => $forecastData['period'],
                            'forecast_quantity' => $forecastData['quantity'],
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'Customer-AI',
                            'confidence_level' => 0.95,
                            'forecast_issue_date' => $issueDate,
                            'submission_date' => now(),
                            'is_current_version' => true,
                            'previous_version_id' => $existingForecast->forecast_id
                        ]);
                        
                        $updatedCount++;
                    } else {
                        SalesForecast::create([
                            'customer_id' => $customerId,
                            'item_id' => $itemData['item_id'],
                            'forecast_period' => $forecastData['period'],
                            'forecast_quantity' => $forecastData['quantity'],
                            'actual_quantity' => null,
                            'variance' => null,
                            'forecast_source' => 'Customer-AI',
                            'confidence_level' => 0.95,
                            'forecast_issue_date' => $issueDate,
                            'submission_date' => now(),
                            'is_current_version' => true
                        ]);
                        
                        $savedCount++;
                    }
                }
            }
            
            DB::commit();
            
            return [
                'new_forecasts' => $savedCount,
                'updated_forecasts' => $updatedCount,
                'total_saved' => $savedCount + $updatedCount,
                'items_processed' => count($validItems)
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Process Excel file with AI and preview extraction (dengan option bypass database check)
     */
    public function processExcelWithAIBypass(Request $request)
    {
        // Same as processExcelWithAI but with bypass option
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
            'customer_id' => 'required|exists:Customer,customer_id',
            'forecast_issue_date' => 'required|date',
            'preview_only' => 'boolean',
            'bypass_database_check' => 'boolean' // NEW: bypass database validation
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('excel_file');
            $previewOnly = $request->input('preview_only', true);
            $bypassDbCheck = $request->input('bypass_database_check', false);
            
            // Load spreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray(null, true, true, true);
            
            // Direct extraction
            $directExtraction = $this->directExcelExtractionImproved($data);
            
            if ($directExtraction['success'] && !empty($directExtraction['data']['items'])) {
                $extractedData = $directExtraction['data'];
                
                // Validate with option to bypass database check
                if ($bypassDbCheck) {
                    $validationResult = $this->validateExtractedDataBypass($extractedData, $request->customer_id);
                } else {
                    $validationResult = $this->validateExtractedData($extractedData, $request->customer_id);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Excel processed successfully' . ($bypassDbCheck ? ' (Database check bypassed)' : ''),
                    'data' => [
                        'supplier_info' => $extractedData['supplier_info'] ?? null,
                        'forecast_periods' => $extractedData['forecast_periods'] ?? [],
                        'items' => $validationResult['valid_items'],
                        'warnings' => $validationResult['warnings'],
                        'errors' => $validationResult['errors'],
                        'summary' => [
                            'total_items' => count($extractedData['items'] ?? []),
                            'valid_items' => count($validationResult['valid_items']),
                            'total_forecasts' => $validationResult['total_forecasts'],
                            'periods_count' => count($extractedData['forecast_periods'] ?? [])
                        ],
                        'debug_info' => $directExtraction['debug'] ?? null
                    ]
                ], 200);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data',
                'debug_info' => $directExtraction['debug'] ?? null
            ], 422);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Excel file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Validate extracted data WITHOUT database check (untuk testing)
     */
    private function validateExtractedDataBypass($extractedData, $customerId)
    {
        $validItems = [];
        $warnings = [];
        $errors = [];
        $totalForecasts = 0;
        
        if (!isset($extractedData['items']) || !is_array($extractedData['items'])) {
            $errors[] = 'No items found in extracted data';
            return [
                'valid_items' => [],
                'warnings' => $warnings,
                'errors' => $errors,
                'has_valid_data' => false,
                'total_forecasts' => 0
            ];
        }
        
        foreach ($extractedData['items'] as $itemData) {
            $materialCode = $itemData['material_code'] ?? null;
            
            if (!$materialCode) {
                $warnings[] = 'Item without material code skipped';
                continue;
            }
            
            // Validate forecasts
            $validForecasts = [];
            $forecasts = $itemData['forecasts'] ?? [];
            
            foreach ($forecasts as $period => $quantity) {
                if (!$this->isValidDateFormat($period)) {
                    $warnings[] = "Invalid period format '{$period}' for item {$materialCode}";
                    continue;
                }
                
                // FIXED: Use same cleaning function
                $numericQuantity = $this->cleanNumericValue($quantity);
                
                if ($numericQuantity === null) {
                    continue; // Skip invalid quantities
                }
                
                if ($numericQuantity < 0) {
                    $warnings[] = "Negative quantity for {$materialCode} in {$period}, set to 0";
                    $numericQuantity = 0;
                }
                
                if ($numericQuantity > 0) {
                    $validForecasts[] = [
                        'period' => $period,
                        'quantity' => $numericQuantity
                    ];
                    $totalForecasts++;
                }
            }
            
            if (!empty($validForecasts)) {
                $validItems[] = [
                    'item_id' => null, // Bypass database check
                    'item_code' => $materialCode,
                    'item_name' => $itemData['description'] ?? '',
                    'description' => $itemData['description'] ?? '',
                    'uom' => $itemData['uom'] ?? '',
                    'forecasts' => $validForecasts,
                    'note' => 'Database check bypassed'
                ];
            }
        }
        
        return [
            'valid_items' => $validItems,
            'warnings' => $warnings,
            'errors' => $errors,
            'has_valid_data' => !empty($validItems),
            'total_forecasts' => $totalForecasts
        ];
    }
    
    /**
     * DEBUG: Test numeric cleaning function
     */
    public function testNumericCleaning(Request $request)
    {
        $testValues = [
            '22,000',
            '5,000',
            '1,200.50',
            '-',
            '',
            null,
            '123',
            '123.45',
            'abc',
            '12,345.67',
            '  1,000  ',
            '0',
            '-500'
        ];
        
        $results = [];
        foreach ($testValues as $value) {
            $results[] = [
                'original' => $value,
                'cleaned' => $this->cleanNumericValue($value),
                'is_numeric_original' => is_numeric($value),
                'type' => gettype($value)
            ];
        }
        
        return response()->json([
            'success' => true,
            'test_results' => $results
        ], 200);
    }
    
    /**
     * Validate date format
     */
    private function isValidDateFormat($date)
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}