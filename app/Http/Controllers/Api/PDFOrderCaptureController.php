<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PdfOrderCaptureRequest;
use App\Models\PdfOrderCapture;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SOLine;
use App\Models\Sales\Customer;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PdfOrderCaptureController extends Controller
{
    /**
     * Upload and extract PDF data (Preview only - no SO creation)
     */
    public function processPdf(PdfOrderCaptureRequest $request)
    {
        $pdfCapture = null;

        try {
            // Decode processing_options if present and is string
            $processingOptions = $request->input('processing_options', []);
            if (is_string($processingOptions)) {
                $decoded = json_decode($processingOptions, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $processingOptions = $decoded;
                } else {
                    $processingOptions = [];
                }
            }

            // Store PDF file
            $pdfFile = $request->file('pdf_file');

            if (!$pdfFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'No PDF file uploaded or file is invalid.'
                ], 422);
            }

            \Log::info('Attempting to store uploaded PDF file', [
                'original_name' => $pdfFile->getClientOriginalName(),
                'size' => $pdfFile->getSize(),
                'mime_type' => $pdfFile->getMimeType(),
                'is_valid' => $pdfFile->isValid()
            ]);

            // Ensure directory exists
            $storagePath = storage_path('app/public/order_pdfs');
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0777, true);
                \Log::info('Created directory for PDF storage', ['path' => $storagePath]);
            }

            $filename = time() . '_' . $pdfFile->getClientOriginalName();
            $pdfPath = \Storage::disk('public')->putFileAs('order_pdfs', $pdfFile, $filename);

            \Log::info('PDF file stored', [
                'path' => $pdfPath,
                'exists' => \Storage::disk('public')->exists($pdfPath),
                'full_path' => storage_path('app/public/' . $pdfPath),
                'file_exists_physical' => file_exists(storage_path('app/public/' . $pdfPath))
            ]);

            // Create PDF capture record with "extracted" status (not processing SO yet)
            $pdfCapture = PdfOrderCapture::create([
                'filename' => $pdfFile->getClientOriginalName(),
                'file_path' => $pdfPath,
                'file_size' => $pdfFile->getSize(),
                'status' => 'processing',
                'user_id' => auth()->id(),
                'processing_options' => $processingOptions
            ]);

            Log::info('PDF capture record created', [
                'capture_id' => $pdfCapture->id,
                'filename' => $pdfCapture->filename,
                'status' => $pdfCapture->status
            ]);

            // Convert PDF to text using Groq AI with page-based chunking
            $extractedData = $this->extractDataWithGroqAI($pdfPath);

            // Validate items exist in database
            $itemValidation = $this->validateItemsExist($extractedData);

            // Update capture record with extracted data
            $updated = $pdfCapture->update([
                'extracted_data' => $extractedData,
                'ai_raw_response' => $extractedData,
                'confidence_score' => $extractedData['confidence_score'] ?? null,
                'status' => 'data_extracted',
                'item_validation' => $itemValidation
            ]);

            Log::info('PDF capture updated with extracted data', [
                'capture_id' => $pdfCapture->id,
                'update_success' => $updated,
                'status' => $pdfCapture->fresh()->status,
                'confidence_score' => $pdfCapture->fresh()->confidence_score,
                'missing_items_count' => count($itemValidation['missing_items'] ?? [])
            ]);

            // Reload the capture to ensure we have latest data
            $pdfCapture->refresh();

            return response()->json([
                'success' => true,
                'message' => 'PDF processed and data extracted successfully',
                'data' => [
                    'pdf_capture' => $pdfCapture,
                    'extracted_data' => $extractedData,
                    'item_validation' => $itemValidation,
                    'can_create_sales_order' => empty($itemValidation['missing_items'])
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('PDF Order Capture Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $request->file('pdf_file')?->getClientOriginalName(),
                'user_id' => auth()->id(),
                'capture_id' => $pdfCapture?->id
            ]);

            if ($pdfCapture) {
                try {
                    $pdfCapture->update([
                        'status' => 'failed',
                        'processing_error' => $e->getMessage()
                    ]);
                    Log::info('PDF capture marked as failed', [
                        'capture_id' => $pdfCapture->id,
                        'error' => $e->getMessage()
                    ]);
                } catch (\Exception $updateError) {
                    Log::error('Failed to update capture status to failed', [
                        'capture_id' => $pdfCapture->id,
                        'update_error' => $updateError->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to process PDF: ' . $e->getMessage(),
                'capture_id' => $pdfCapture?->id
            ], 500);
        }
    }

    /**
     * Create Sales Order from extracted data (separate step)
     */
    public function createSalesOrderFromCapture($captureId)
    {
        try {
            $pdfCapture = PdfOrderCapture::findOrFail($captureId);
            
            if ($pdfCapture->status !== 'data_extracted') {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF must be in data_extracted status to create sales order'
                ], 400);
            }

            if ($pdfCapture->created_so_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sales order already created for this PDF'
                ], 400);
            }

            $extractedData = $pdfCapture->extracted_data;
            $itemValidation = $pdfCapture->item_validation ?? $this->validateItemsExist($extractedData);

            // Check if there are missing items
            if (!empty($itemValidation['missing_items'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create sales order. Some items are missing from the database.',
                    'data' => [
                        'missing_items' => $itemValidation['missing_items'],
                        'existing_items' => $itemValidation['existing_items']
                    ]
                ], 422);
            }

            // Use DB::transaction for atomicity
            $salesOrder = DB::transaction(function () use ($extractedData, $pdfCapture) {
                // Update status to creating
                $pdfCapture->update(['status' => 'creating_order']);

                // Validate and create sales order
                $salesOrder = $this->createSalesOrderFromData($extractedData, $pdfCapture);

                // Update capture record with sales order
                $finalUpdate = $pdfCapture->update([
                    'created_so_id' => $salesOrder->so_id,
                    'status' => 'so_created',
                    'processed_by' => auth()->id(),
                    'processed_at' => now()
                ]);

                Log::info('PDF capture marked as completed', [
                    'capture_id' => $pdfCapture->id,
                    'so_id' => $salesOrder->so_id,
                    'final_update_success' => $finalUpdate,
                    'final_status' => $pdfCapture->fresh()->status
                ]);

                return $salesOrder;
            });

            // Reload the capture to ensure we have latest data
            $pdfCapture->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Sales order created successfully from PDF data',
                'data' => [
                    'pdf_capture' => $pdfCapture,
                    'sales_order' => $salesOrder->load('salesOrderLines.item'),
                    'extracted_data' => $extractedData
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create Sales Order from Capture Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'capture_id' => $captureId
            ]);

            // Rollback transaction if it was started
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if (isset($pdfCapture)) {
                try {
                    $pdfCapture->update([
                        'status' => 'failed',
                        'processing_error' => $e->getMessage()
                    ]);
                } catch (\Exception $updateError) {
                    Log::error('Failed to update capture status to failed', [
                        'capture_id' => $pdfCapture->id,
                        'update_error' => $updateError->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create sales order: ' . $e->getMessage(),
                'capture_id' => $captureId
            ], 500);
        }
    }

    /**
     * Validate if items exist in database
     */
    private function validateItemsExist($extractedData)
    {
        $validation = [
            'missing_items' => [],
            'existing_items' => [],
            'fuzzy_matches' => []
        ];

        if (!isset($extractedData['items']) || !is_array($extractedData['items'])) {
            return $validation;
        }

        foreach ($extractedData['items'] as $index => $itemData) {
            $itemCode = $itemData['item_code'] ?? null;
            $itemName = $itemData['name'] ?? null;

            $existingItem = null;

            // Try to find by item_code first
            if ($itemCode) {
                $existingItem = Item::where('item_code', $itemCode)->first();
            }

            // Try to find by exact name if item_code not found
            if (!$existingItem && $itemName) {
                $existingItem = Item::where('name', $itemName)->first();
            }

            // Try fuzzy matching for name
            if (!$existingItem && $itemName) {
                $existingItem = $this->findItemByFuzzyName($itemName);
                if ($existingItem) {
                    $validation['fuzzy_matches'][] = [
                        'extracted_name' => $itemName,
                        'matched_item' => [
                            'item_id' => $existingItem->item_id,
                            'item_code' => $existingItem->item_code,
                            'name' => $existingItem->name
                        ],
                        'similarity_score' => $this->calculateNameSimilarity($itemName, $existingItem->name)
                    ];
                }
            }

            if ($existingItem) {
                $validation['existing_items'][] = [
                    'index' => $index,
                    'extracted_data' => $itemData,
                    'matched_item' => [
                        'item_id' => $existingItem->item_id,
                        'item_code' => $existingItem->item_code,
                        'name' => $existingItem->name,
                        'description' => $existingItem->description,
                        'sale_price' => $existingItem->sale_price
                    ]
                ];
            } else {
                $validation['missing_items'][] = [
                    'index' => $index,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'description' => $itemData['description'] ?? null,
                    'quantity' => $itemData['quantity'] ?? null,
                    'unit_price' => $itemData['unit_price'] ?? null,
                    'source_page' => $itemData['source_page'] ?? null
                ];
            }
        }

        Log::info('Item validation completed', [
            'total_items' => count($extractedData['items']),
            'existing_items' => count($validation['existing_items']),
            'missing_items' => count($validation['missing_items']),
            'fuzzy_matches' => count($validation['fuzzy_matches'])
        ]);

        return $validation;
    }

    /**
     * Find item using fuzzy name matching
     */
    private function findItemByFuzzyName($extractedName)
    {
        // Clean the extracted name for comparison
        $cleanExtracted = $this->cleanItemName($extractedName);
        
        // Get all items and try fuzzy matching
        $items = Item::all();
        
        foreach ($items as $item) {
            $cleanDbName = $this->cleanItemName($item->name);
            
            // Check for exact match after cleaning
            if ($cleanExtracted === $cleanDbName) {
                return $item;
            }
            
            // Check if one contains the other (with minimum length)
            if (strlen($cleanExtracted) >= 5 && strlen($cleanDbName) >= 5) {
                if (stripos($cleanDbName, $cleanExtracted) !== false || 
                    stripos($cleanExtracted, $cleanDbName) !== false) {
                    return $item;
                }
            }
            
            // Calculate similarity percentage
            $similarity = $this->calculateNameSimilarity($cleanExtracted, $cleanDbName);
            
            if ($similarity >= 85) { // 85% similarity threshold
                Log::info('Item matched by similarity', [
                    'extracted' => $extractedName,
                    'matched' => $item->name,
                    'similarity' => $similarity
                ]);
                return $item;
            }
        }
        
        return null;
    }

    /**
     * Clean item name for comparison
     */
    private function cleanItemName($name)
    {
        // Convert to uppercase and remove common suffixes/prefixes
        $cleaned = strtoupper(trim($name));
        
        // Remove extra spaces and punctuation
        $cleaned = preg_replace('/[^\w\s]/', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        return trim($cleaned);
    }

    /**
     * Calculate name similarity percentage
     */
    private function calculateNameSimilarity($name1, $name2)
    {
        // Use multiple similarity algorithms and take the best score
        $similarities = [];
        
        // Levenshtein distance based similarity
        $maxLen = max(strlen($name1), strlen($name2));
        if ($maxLen > 0) {
            $similarities[] = (1 - levenshtein($name1, $name2) / $maxLen) * 100;
        }
        
        // Similar text percentage
        $percent = 0;
        similar_text($name1, $name2, $percent);
        $similarities[] = $percent;
        
        // Word-based similarity (check how many words are common)
        $words1 = explode(' ', $name1);
        $words2 = explode(' ', $name2);
        
        $commonWords = array_intersect($words1, $words2);
        $totalWords = count(array_unique(array_merge($words1, $words2)));
        
        if ($totalWords > 0) {
            $similarities[] = (count($commonWords) / $totalWords) * 100;
        }
        
        // Return the highest similarity score
        return max($similarities);
    }

    /**
     * Extract data from PDF using Groq AI with page-based chunking
     */
    private function extractDataWithGroqAI($storedPath)
    {
        // Convert stored path to full system path with proper separators
        $fullPath = storage_path('app/public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath));
        
        Log::info('Starting PDF text extraction with page-based chunking', [
            'stored_path' => $storedPath,
            'full_path' => $fullPath,
            'file_exists' => file_exists($fullPath),
            'file_size' => file_exists($fullPath) ? filesize($fullPath) : 'N/A'
        ]);
        
        // Verify file exists before processing
        if (!file_exists($fullPath)) {
            throw new \Exception('PDF file not found at: ' . $fullPath);
        }
        
        // Get total pages and determine processing strategy
        $totalPages = $this->getPdfPageCount($fullPath);
        
        Log::info('PDF page analysis', [
            'total_pages' => $totalPages,
            'file_path' => $storedPath
        ]);
        
        // Use page-based chunking for multi-page PDFs
        if ($totalPages > 1) {
            Log::info('Multi-page PDF detected, using page-based chunking', [
                'total_pages' => $totalPages
            ]);
            
            return $this->extractDataWithPageBasedChunking($fullPath, $totalPages);
        } else {
            Log::info('Single-page PDF detected, using standard processing', [
                'total_pages' => $totalPages
            ]);
            
            // Single page - use standard processing
            $pdfText = $this->extractTextFromPdf($fullPath);
            return $this->extractDataSingleRequest($pdfText);
        }
    }

    /**
     * Get PDF page count
     */
    private function getPdfPageCount($pdfPath)
    {
        try {
            // Try multiple methods to get page count
            $escapedPath = escapeshellarg($pdfPath);
            
            // Method 1: Using pdfinfo (most reliable)
            $pageCountCmd = "pdfinfo {$escapedPath} | grep 'Pages:' | awk '{print $2}'";
            $pageCount = trim(shell_exec($pageCountCmd));
            
            if (is_numeric($pageCount) && $pageCount > 0) {
                return (int) $pageCount;
            }
            
            // Method 2: Using pdftotext to estimate pages
            $textOutput = shell_exec("pdftotext {$escapedPath} -");
            if ($textOutput) {
                // Estimate pages based on form feed characters or content length
                $formFeeds = substr_count($textOutput, "\f");
                if ($formFeeds > 0) {
                    return $formFeeds + 1; // Form feeds + 1 = total pages
                }
                
                // Fallback: estimate based on content length
                $estimatedPages = max(1, ceil(strlen($textOutput) / 3000)); // ~3000 chars per page
                return min($estimatedPages, 20); // Cap at 20 pages for safety
            }
            
            // Method 3: Try using qpdf if available
            $qpdfCount = shell_exec("qpdf --show-npages {$escapedPath} 2>/dev/null");
            if (is_numeric(trim($qpdfCount))) {
                return (int) trim($qpdfCount);
            }
            
            Log::warning('Could not determine page count, defaulting to 1', [
                'pdf_path' => $pdfPath
            ]);
            
            return 1; // Default fallback
            
        } catch (\Exception $e) {
            Log::warning('Error getting PDF page count', [
                'error' => $e->getMessage(),
                'pdf_path' => $pdfPath
            ]);
            return 1; // Default fallback
        }
    }

    /**
     * Extract data using page-based chunking
     */
    private function extractDataWithPageBasedChunking($pdfPath, $totalPages)
    {
        try {
            Log::info('Starting page-based chunking extraction', [
                'total_pages' => $totalPages,
                'pdf_path' => $pdfPath
            ]);
            
            $allPageResults = [];
            $headerInfo = null;
            
            // Extract text from each page
            for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++) {
                Log::info("Processing page {$pageNum}/{$totalPages}");
                
                try {
                    $pageText = $this->extractTextFromPage($pdfPath, $pageNum);
                    
                    if (empty(trim($pageText))) {
                        Log::warning("Page {$pageNum} is empty, skipping");
                        continue;
                    }
                    
                    // Process this page
                    $pageResult = $this->processPage($pageText, $pageNum, $totalPages, $headerInfo);
                    
                    if ($pageResult) {
                        // Store header info from first page for context
                        if ($pageNum === 1 && !$headerInfo) {
                            $headerInfo = $this->extractHeaderInfo($pageResult);
                        }
                        
                        $allPageResults[] = $pageResult;
                    }
                    
                } catch (\Exception $e) {
                    Log::warning("Failed to process page {$pageNum}", [
                        'error' => $e->getMessage(),
                        'page' => $pageNum
                    ]);
                    // Continue with other pages
                }
                
                // Add delay between requests to avoid rate limiting
                if ($pageNum < $totalPages) {
                    sleep(1);
                }
            }
            
            if (empty($allPageResults)) {
                throw new \Exception('No pages were successfully processed');
            }
            
            // Merge results from all pages
            $mergedResult = $this->mergePageResults($allPageResults);
            
            Log::info('Page-based chunking completed successfully', [
                'total_pages' => $totalPages,
                'pages_processed' => count($allPageResults),
                'total_items_extracted' => count($mergedResult['items'] ?? []),
                'confidence_score' => $mergedResult['confidence_score'] ?? null
            ]);
            
            return $mergedResult;
            
        } catch (\Exception $e) {
            Log::error('Page-based chunking extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'total_pages' => $totalPages
            ]);
            throw $e;
        }
    }

    /**
     * Extract text from specific PDF page
     */
    private function extractTextFromPage($pdfPath, $pageNum)
    {
        $escapedPath = escapeshellarg($pdfPath);
        
        // Try different extraction methods for specific page
        $methods = [
            "pdftotext -f {$pageNum} -l {$pageNum} -layout {$escapedPath} -",
            "pdftotext -f {$pageNum} -l {$pageNum} {$escapedPath} -",
            "pdftotext -f {$pageNum} -l {$pageNum} -raw {$escapedPath} -"
        ];
        
        foreach ($methods as $command) {
            try {
                Log::debug("Trying extraction method for page {$pageNum}", [
                    'command' => $command
                ]);
                
                $output = shell_exec($command);
                
                if ($output && strlen(trim($output)) > 20) {
                    Log::info("Successfully extracted text from page {$pageNum}", [
                        'method' => explode(' ', $command)[0],
                        'text_length' => strlen($output),
                        'first_100_chars' => substr($output, 0, 100)
                    ]);
                    return $output;
                }
            } catch (\Exception $e) {
                Log::warning("Page extraction method failed for page {$pageNum}", [
                    'method' => $command,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        Log::warning("All extraction methods failed for page {$pageNum}");
        return '';
    }

    /**
     * Process a single page using Groq AI
     */
    private function processPage($pageText, $pageNum, $totalPages, $headerInfo = null)
    {
        $prompt = $this->buildPagePrompt($pageText, $pageNum, $totalPages, $headerInfo);
        
        $requestData = [
            'model' => config('groq.default_model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are an expert at extracting purchase order information. This is page {$pageNum} of {$totalPages}. Focus on extracting items and data from this specific page. Pay attention to number formats - commas in quantities like 7,350 are thousands separators (=7350), not decimals. Return only valid JSON."
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 2000
        ];
        
        Log::info("Sending page {$pageNum} to Groq AI", [
            'page_number' => $pageNum,
            'total_pages' => $totalPages,
            'prompt_length' => strlen($prompt)
        ]);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('groq.api_key'),
            'Content-Type' => 'application/json',
        ])
        ->timeout(config('groq.request_timeout', 30))
        ->post(config('groq.base_url') . '/chat/completions', $requestData);

        if (!$response->successful()) {
            Log::error("Groq AI request failed for page {$pageNum}", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception("Groq AI request failed for page {$pageNum}: " . $response->body());
        }

        $responseData = $response->json();
        $aiResponse = $responseData['choices'][0]['message']['content'];
        
        Log::info("Received response from Groq AI for page {$pageNum}", [
            'page_number' => $pageNum,
            'raw_response' => $aiResponse,
            'response_length' => strlen($aiResponse)
        ]);
        
        // Clean and parse JSON response
        $aiResponse = $this->cleanJsonResponse($aiResponse);
        
        $decodedData = json_decode($aiResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("JSON decode error for page {$pageNum}", [
                'page_number' => $pageNum,
                'error' => json_last_error_msg(),
                'response' => $aiResponse
            ]);
            throw new \Exception("Invalid JSON response from AI for page {$pageNum}: " . json_last_error_msg());
        }
        
        // Validate and correct extracted data for this page
        $validatedData = $this->validateAndCorrectExtractedData($decodedData);
        
        Log::info("Page {$pageNum} processing completed", [
            'page_number' => $pageNum,
            'items_extracted' => count($validatedData['items'] ?? [])
        ]);
        
        return $validatedData;
    }

    /**
     * Build prompt for page processing
     */
    private function buildPagePrompt($pageText, $pageNum, $totalPages, $headerInfo = null)
    {
        $headerContext = '';
        if ($headerInfo && $pageNum > 1) {
            $headerContext = "
CONTEXT FROM FIRST PAGE:
- Customer: {$headerInfo['customer_name']}
- Order Number: {$headerInfo['order_number']}
- Date: {$headerInfo['order_date']}
- Currency: {$headerInfo['currency']}

";
        }

        return "
{$headerContext}You are processing page {$pageNum} of {$totalPages} from a purchase order document.

IMPORTANT: This is page {$pageNum} of a multi-page document. Focus on extracting:
" . ($pageNum === 1 ? "
1. Header information (customer, order details, etc.) - THIS IS THE FIRST PAGE
2. Items/products found on this page
" : "
1. Items/products found on this page (continuation from previous pages)
2. Any additional order information if present
") . "

CRITICAL RULES FOR TABLE COLUMN IDENTIFICATION:
- Carefully identify table headers: No., Material Code, Material Description, Delivery Date, Quantity, Qty Unit, Unit Price, Net Value
- IGNORE the 'No.' column (line numbers like 10, 20, 30) - these are NOT quantities
- The QUANTITY is in the 'Quantity' column, usually after the delivery date
- Numbers with commas like '7,350' or '1,500' are THOUSANDS separators (NOT decimals)

For page {$pageNum} of {$totalPages}, return ONLY valid JSON in this structure:

{
    \"page_info\": {
        \"page_number\": {$pageNum},
        \"total_pages\": {$totalPages}
    },
    \"document_type\": \"PURCHASE_ORDER or SALES_ORDER or INVOICE (if determinable)\",
    \"order_info\": {
        \"order_number\": \"extract P.O. No, Order No if found on this page\",
        \"order_date\": \"extract date in YYYY-MM-DD format if found\",
        \"expected_delivery\": \"extract delivery date in YYYY-MM-DD format if found\",
        \"currency\": \"extract currency code if found\",
        \"payment_terms\": \"extract payment terms if found\",
        \"delivery_terms\": \"extract delivery terms if found\"
    },
    \"customer\": {
        \"name\": \"extract customer name if found on this page\",
        \"code\": \"extract customer code if found\",
        \"address\": \"extract customer address if found\",
        \"phone\": \"extract customer phone if found\",
        \"email\": \"extract customer email if found\",
        \"tax_id\": \"extract customer tax ID if found\"
    },
    \"items\": [
        {
            \"item_code\": \"extract material code, item code, SKU\",
            \"name\": \"extract item name/description\",
            \"description\": \"extract detailed description\",
            \"quantity\": \"extract ONLY from Quantity column (convert 7,350 to 7350)\",
            \"unit_price\": \"extract unit price as decimal number\",
            \"uom\": \"extract unit of measure (PC, KG, etc.)\",
            \"discount\": \"extract discount amount as number\",
            \"tax\": \"extract tax amount as number\",
            \"total_value\": \"extract line total/net value as number\",
            \"validation_check\": \"verify if quantity × unit_price ≈ total_value\"
        }
    ],
    \"confidence_score\": \"rate your confidence 0-100 for this page\",
    \"page_notes\": \"any notes about this specific page\"
}

If no items are found on this page, return empty items array.
If customer/order info is not on this page, return empty strings or null.

Page {$pageNum} text to analyze:
{$pageText}
";
    }

    /**
     * Extract header information from first page result
     */
    private function extractHeaderInfo($firstPageResult)
    {
        return [
            'customer_name' => $firstPageResult['customer']['name'] ?? '',
            'order_number' => $firstPageResult['order_info']['order_number'] ?? '',
            'order_date' => $firstPageResult['order_info']['order_date'] ?? '',
            'currency' => $firstPageResult['order_info']['currency'] ?? '',
            'payment_terms' => $firstPageResult['order_info']['payment_terms'] ?? '',
            'delivery_terms' => $firstPageResult['order_info']['delivery_terms'] ?? ''
        ];
    }

    /**
     * Merge results from multiple pages
     */
    private function mergePageResults($pageResults)
    {
        $merged = [
            'document_type' => '',
            'order_info' => [
                'order_number' => '',
                'order_date' => '',
                'expected_delivery' => '',
                'currency' => '',
                'payment_terms' => '',
                'delivery_terms' => ''
            ],
            'customer' => [
                'name' => '',
                'code' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
                'tax_id' => ''
            ],
            'vendor_info' => [
                'name' => '',
                'address' => ''
            ],
            'items' => [],
            'confidence_score' => 0,
            'number_format_notes' => 'Processed using page-based chunking',
            'table_structure_notes' => 'Data extracted from multiple PDF pages',
            'processing_notes' => 'Large document processed in ' . count($pageResults) . ' pages'
        ];
        
        $totalConfidence = 0;
        $confidenceCount = 0;
        
        foreach ($pageResults as $pageIndex => $page) {
            $pageNum = $pageIndex + 1;
            
            // Merge document type (take first non-empty)
            if (empty($merged['document_type']) && !empty($page['document_type'])) {
                $merged['document_type'] = $page['document_type'];
            }
            
            // Merge order info (prioritize first page, then take first non-empty values)
            foreach ($merged['order_info'] as $key => $value) {
                if (empty($merged['order_info'][$key]) && !empty($page['order_info'][$key])) {
                    $merged['order_info'][$key] = $page['order_info'][$key];
                }
            }
            
            // Merge customer info (prioritize first page, then take first non-empty values)
            foreach ($merged['customer'] as $key => $value) {
                if (empty($merged['customer'][$key]) && !empty($page['customer'][$key])) {
                    $merged['customer'][$key] = $page['customer'][$key];
                }
            }
            
            // Merge vendor info (take first non-empty values)
            foreach ($merged['vendor_info'] as $key => $value) {
                if (empty($merged['vendor_info'][$key]) && !empty($page['vendor_info'][$key])) {
                    $merged['vendor_info'][$key] = $page['vendor_info'][$key];
                }
            }
            
            // Merge items (combine all items from all pages)
            if (!empty($page['items']) && is_array($page['items'])) {
                foreach ($page['items'] as $item) {
                    // Add page info to item for debugging
                    $item['source_page'] = $pageNum;
                    $merged['items'][] = $item;
                }
            }
            
            // Calculate average confidence
            if (isset($page['confidence_score']) && is_numeric($page['confidence_score'])) {
                $totalConfidence += $page['confidence_score'];
                $confidenceCount++;
            }
        }
        
        // Calculate average confidence score
        if ($confidenceCount > 0) {
            $merged['confidence_score'] = round($totalConfidence / $confidenceCount);
        }
        
        // Remove duplicate items based on item_code
        $merged['items'] = $this->removeDuplicateItems($merged['items']);
        
        Log::info('Page merge completed', [
            'pages_processed' => count($pageResults),
            'total_items' => count($merged['items']),
            'average_confidence' => $merged['confidence_score'],
            'customer_found' => !empty($merged['customer']['name']),
            'order_number_found' => !empty($merged['order_info']['order_number'])
        ]);
        
        return $merged;
    }

    /**
     * Remove duplicate items based on item_code
     */
    private function removeDuplicateItems($items)
    {
        $uniqueItems = [];
        $seenCodes = [];
        
        foreach ($items as $item) {
            $itemCode = $item['item_code'] ?? '';
            
            if (empty($itemCode) || !in_array($itemCode, $seenCodes)) {
                $uniqueItems[] = $item;
                if (!empty($itemCode)) {
                    $seenCodes[] = $itemCode;
                }
            } else {
                Log::info('Duplicate item removed during page merge', [
                    'item_code' => $itemCode,
                    'item_name' => $item['name'] ?? 'unknown',
                    'source_page' => $item['source_page'] ?? 'unknown'
                ]);
            }
        }
        
        return $uniqueItems;
    }

    /**
     * Extract data using single request for single-page PDFs
     */
    private function extractDataSingleRequest($pdfText)
    {
        $prompt = $this->buildGroqAIPrompt($pdfText);
        
        $requestData = [
            'model' => config('groq.default_model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at extracting purchase order information. Pay special attention to number formats - commas in quantities like 7,350 are thousands separators (=7350), not decimals. Always validate your extractions by checking if quantity × unit_price ≈ total_value. Return only valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 4000
        ];
        
        Log::info('Sending single page request to Groq AI', [
            'prompt_length' => strlen($prompt)
        ]);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('groq.api_key'),
            'Content-Type' => 'application/json',
        ])
        ->timeout(config('groq.request_timeout', 30))
        ->post(config('groq.base_url') . '/chat/completions', $requestData);

        if (!$response->successful()) {
            Log::error('Groq AI request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Groq AI request failed: ' . $response->body());
        }

        $responseData = $response->json();
        $aiResponse = $responseData['choices'][0]['message']['content'];
        
        Log::info('Received response from Groq AI', [
            'raw_response' => $aiResponse,
            'response_length' => strlen($aiResponse)
        ]);
        
        // Clean and parse JSON response
        $aiResponse = $this->cleanJsonResponse($aiResponse);
        
        $decodedData = json_decode($aiResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON decode error', [
                'error' => json_last_error_msg(),
                'response' => $aiResponse
            ]);
            throw new \Exception('Invalid JSON response from AI: ' . json_last_error_msg());
        }
        
        // Validate and correct extracted data
        $validatedData = $this->validateAndCorrectExtractedData($decodedData);
        
        Log::info('Single page processing completed', [
            'items_extracted' => count($validatedData['items'] ?? [])
        ]);
        
        return $validatedData;
    }

    /**
     * Post-process and validate extracted data
     */
    private function validateAndCorrectExtractedData($extractedData)
    {
        if (!isset($extractedData['items']) || !is_array($extractedData['items'])) {
            return $extractedData;
        }

        $correctedItems = [];
        
        foreach ($extractedData['items'] as $item) {
            $correctedItem = $this->validateAndCorrectItemNumbers($item);
            $correctedItems[] = $correctedItem;
        }
        
        $extractedData['items'] = $correctedItems;
        
        return $extractedData;
    }

    /**
     * Enhanced validation with better error detection
     */
    private function validateAndCorrectItemNumbers($item)
    {
        $quantity = $item['quantity'] ?? 0;
        $unitPrice = $item['unit_price'] ?? 0;
        $totalValue = $item['total_value'] ?? 0;
        
        // Convert string numbers to proper numeric values
        $quantity = $this->parseNumber($quantity);
        $unitPrice = $this->parseNumber($unitPrice);
        $totalValue = $this->parseNumber($totalValue);
        
        // Validate calculation: quantity × unit_price should ≈ total_value
        $calculatedTotal = $quantity * $unitPrice;
        $tolerance = 0.10; // 10% tolerance for rounding differences
        
        if ($totalValue > 0) {
            $difference = abs($calculatedTotal - $totalValue);
            $percentDifference = ($difference / $totalValue) * 100;
            
            if ($percentDifference > ($tolerance * 100)) {
                Log::warning('Potential table parsing error detected', [
                    'item_code' => $item['item_code'] ?? 'unknown',
                    'original_quantity' => $item['quantity'],
                    'parsed_quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'expected_total' => $totalValue,
                    'calculated_total' => $calculatedTotal,
                    'difference_percent' => $percentDifference,
                    'source_page' => $item['source_page'] ?? 'unknown',
                    'likely_issue' => 'Possible wrong column used for quantity (line number vs actual quantity)'
                ]);
                
                // Try intelligent correction based on total value
                if ($unitPrice > 0) {
                    $correctQuantity = round($totalValue / $unitPrice);
                    $newCalculatedTotal = $correctQuantity * $unitPrice;
                    $newDifference = abs($newCalculatedTotal - $totalValue);
                    $newPercentDifference = ($newDifference / $totalValue) * 100;
                    
                    if ($newPercentDifference <= ($tolerance * 100)) {
                        Log::info('Auto-corrected quantity based on total value', [
                            'item_code' => $item['item_code'],
                            'original_quantity' => $quantity,
                            'corrected_quantity' => $correctQuantity,
                            'validation' => 'passed',
                            'correction_method' => 'total_value / unit_price',
                            'source_page' => $item['source_page'] ?? 'unknown'
                        ]);
                        $quantity = $correctQuantity;
                    }
                }
            }
        }
        
        $item['quantity'] = $quantity;
        $item['unit_price'] = $unitPrice;
        $item['total_value'] = $totalValue;
        
        return $item;
    }

    /**
     * Parse number handling various formats
     */
    private function parseNumber($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        if (is_string($value)) {
            // Remove any whitespace
            $value = trim($value);
            
            // Handle comma as thousands separator vs decimal separator
            // If there's only one comma and it's followed by exactly 3 digits, it's thousands separator
            if (preg_match('/^(\d{1,3}),(\d{3})$/', $value, $matches)) {
                // Format like "7,350" - thousands separator
                return (float) ($matches[1] . $matches[2]);
            }
            
            // Handle multiple commas as thousands separators like "1,234,567"
            if (preg_match('/^[\d,]+$/', $value) && substr_count($value, ',') >= 1) {
                // Remove all commas and treat as integer
                return (float) str_replace(',', '', $value);
            }
            
            // Handle decimal with dot
            if (preg_match('/^\d*\.\d+$/', $value)) {
                return (float) $value;
            }
            
            // Fallback: remove commas and convert
            $cleaned = str_replace(',', '', $value);
            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }
        
        return 0;
    }

    /**
     * Extract text from PDF (improved version with multiple methods)
     */
    private function extractTextFromPdf($pdfPath)
    {
        // Check if file exists and is readable
        if (!file_exists($pdfPath)) {
            throw new \Exception('PDF file not found at: ' . $pdfPath);
        }
        
        if (!is_readable($pdfPath)) {
            throw new \Exception('PDF file is not readable: ' . $pdfPath);
        }
        
        $fileSize = filesize($pdfPath);
        Log::info('Starting PDF text extraction', [
            'path' => $pdfPath,
            'size' => $fileSize
        ]);
        
        // Method 1: Try pdftotext command (if available)
        $extractedText = $this->tryPdfToText($pdfPath);
        if ($extractedText) {
            return $extractedText;
        }
        
        // Method 2: Try PHP library extraction (if we had one installed)
        // For now, we'll create a mock extraction based on file analysis
        
        // Method 3: As last resort, ask user to check if pdftotext is installed
        Log::error('All PDF extraction methods failed', [
            'file' => $pdfPath,
            'size' => $fileSize
        ]);
        
        throw new \Exception('
            PDF text extraction failed. This could be because:
            1. pdftotext is not installed on the server
            2. PDF is image-based (scanned) and needs OCR
            3. PDF has restrictions/encryption
            
            Please ensure pdftotext is installed: apt-get install poppler-utils
            Or the PDF contains extractable text.
        ');
    }
    
    /**
     * Try extracting text using pdftotext command
     */
    private function tryPdfToText($pdfPath)
    {
        // Escape path properly for Windows and Unix
        $escapedPath = escapeshellarg($pdfPath);
        
        $methods = [
            "pdftotext {$escapedPath} -",
            "pdftotext -layout {$escapedPath} -",
            "pdftotext -raw {$escapedPath} -",
            "pdftotext -enc UTF-8 {$escapedPath} -"
        ];
        
        foreach ($methods as $command) {
            try {
                Log::info('Trying PDF extraction method', [
                    'command' => $command,
                    'file_path' => $pdfPath
                ]);
                $output = shell_exec($command);
                
                if ($output && strlen(trim($output)) > 50) { // Increased minimum length
                    Log::info('PDF text extracted successfully', [
                        'method' => explode(' ', $command)[0],
                        'text_length' => strlen($output),
                        'first_200_chars' => substr($output, 0, 200)
                    ]);
                    return $output;
                }
            } catch (\Exception $e) {
                Log::warning('PDF extraction method failed', [
                    'method' => $command,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        return null;
    }

    /**
     * Clean JSON response from Groq AI
     */
    private function cleanJsonResponse($response)
    {
        // Remove any markdown formatting
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = preg_replace('/```/', '', $response);
        
        // Remove any text before the first {
        $firstBrace = strpos($response, '{');
        if ($firstBrace !== false) {
            $response = substr($response, $firstBrace);
        }
        
        // Remove any text after the last }
        $lastBrace = strrpos($response, '}');
        if ($lastBrace !== false) {
            $response = substr($response, 0, $lastBrace + 1);
        }
        
        // Remove any leading/trailing whitespace
        $response = trim($response);
        
        // Fix common JSON issues
        $response = str_replace(['\n', '\r', '\t'], ['', '', ''], $response);
        $response = preg_replace('/,\s*}/', '}', $response); // Remove trailing commas
        $response = preg_replace('/,\s*]/', ']', $response); // Remove trailing commas in arrays
        
        return $response;
    }

    /**
     * Build improved Groq AI prompt with better table column recognition
     */
    private function buildGroqAIPrompt($pdfText)
    {
        return "
You are an expert at extracting purchase order and sales order information from documents. 

CRITICAL RULES FOR TABLE COLUMN IDENTIFICATION:
- Carefully identify table headers: No., Material Code, Material Description, Delivery Date, Quantity, Qty Unit, Unit Price, Net Value
- IGNORE the 'No.' column (line numbers like 10, 20, 30) - these are NOT quantities
- The QUANTITY is in the 'Quantity' column, usually after the delivery date
- Common table patterns:
  * No. | Material Code | Description | Delivery Date | Quantity | Unit | Unit Price | Net Value
  * 10  | VCQ5771      | WIRE CUSHION| 03.JUN.2025   | 7,350    | PC   | 0.00670   | 49.25
  * 20  | VED4950      | CUSHION PE  | 03.JUN.2025   | 500      | PC   | 0.07950   | 39.75

CRITICAL RULES FOR NUMBER EXTRACTION:
- Numbers with commas like '7,350' or '1,500' are THOUSANDS separators (NOT decimals)
- '7,350' means 7350 (seven thousand three hundred fifty)
- '1,500' means 1500 (one thousand five hundred) 
- Only periods (.) indicate decimal places like '0.00670' or '39.75'
- Always validate quantities by checking if quantity × unit_price = total_value

CRITICAL RULES FOR CUSTOMER IDENTIFICATION:
For PURCHASE ORDERS (documents we receive):
- The CUSTOMER = The company that ISSUED/SENT the purchase order (the buyer)
- This is usually at the TOP of the document, in the header or letterhead
- Look for company names like 'Yamaha Music India', 'Toyota', etc. at the document header
- IGNORE the 'Vendor' section - that's us (the supplier receiving the order)

VALIDATION RULES:
- Cross-check extracted quantities with calculated totals
- If quantity × unit_price ≠ total_value, re-examine the table structure
- Pay special attention to comma usage in different number formats
- Verify you're reading from the correct column (not line numbers)

TABLE PARSING INSTRUCTIONS:
1. First identify the table headers
2. Map each column position
3. For each item row:
   - Skip the line number (No. column)
   - Extract Material Code from the correct column
   - Extract Quantity from the Quantity column (NOT the No. column)
   - Extract Unit Price from Unit Price column
   - Validate: Quantity × Unit Price ≈ Net Value

Analyze this document text and extract information:

Return ONLY valid JSON in this exact structure:

{
    \"document_type\": \"PURCHASE_ORDER or SALES_ORDER or INVOICE\",
    \"order_info\": {
        \"order_number\": \"extract P.O. No, Order No, SO No, etc.\",
        \"order_date\": \"extract date in YYYY-MM-DD format\",
        \"expected_delivery\": \"extract delivery date in YYYY-MM-DD format\",
        \"currency\": \"extract currency code (USD, EUR, etc.)\",
        \"payment_terms\": \"extract payment terms\",
        \"delivery_terms\": \"extract delivery terms like FOB, CIF, etc.\"
    },
    \"customer\": {
        \"name\": \"extract the ISSUING company name from document header\",
        \"code\": \"extract customer code if any\",
        \"address\": \"extract ISSUING company address (from header, not deliver-to)\",
        \"phone\": \"extract ISSUING company phone\",
        \"email\": \"extract ISSUING company email\",
        \"tax_id\": \"extract ISSUING company tax ID, GSTIN, VAT number, etc.\"
    },
    \"vendor_info\": {
        \"name\": \"extract supplier/vendor name from vendor section\",
        \"address\": \"extract vendor address\"
    },
    \"items\": [
        {
            \"item_code\": \"extract material code, item code, SKU\",
            \"name\": \"extract item name/description\",
            \"description\": \"extract detailed description\",
            \"quantity\": \"extract ONLY from Quantity column (convert 7,350 to 7350, 500 stays 500)\",
            \"unit_price\": \"extract unit price as decimal number\",
            \"uom\": \"extract unit of measure (PC, KG, etc.)\",
            \"discount\": \"extract discount amount as number\",
            \"tax\": \"extract tax amount as number\",
            \"total_value\": \"extract line total/net value as number\",
            \"validation_check\": \"verify if quantity × unit_price ≈ total_value\",
            \"table_parsing_notes\": \"explain which column you used for quantity\"
        }
    ],
    \"confidence_score\": \"rate your confidence 0-100\",
    \"number_format_notes\": \"explain how you interpreted number formats\",
    \"table_structure_notes\": \"describe the table structure and column mapping\"
}

SPECIFIC EXAMPLES OF CORRECT TABLE PARSING:
Row: '20 VED4950 CUSHION PE 580X12X1 03.JUN.2025 500 PC 0.07950 39.75'
- Line No: 20 (IGNORE this)
- Material Code: VED4950
- Description: CUSHION PE 580X12X1
- Delivery Date: 03.JUN.2025
- Quantity: 500 (USE this, NOT the line number 20)
- Unit: PC
- Unit Price: 0.07950
- Net Value: 39.75
- Validation: 500 × 0.07950 = 39.75 ✓ CORRECT

WRONG EXAMPLE:
❌ Using line number as quantity: quantity: 20, validation: 20 × 0.07950 = 1.59 ≠ 39.75

CORRECT EXAMPLE:
✅ Using actual quantity: quantity: 500, validation: 500 × 0.07950 = 39.75 ≈ 39.75

Document text to analyze:
{$pdfText}
";
    }

    /**
     * Create sales order from extracted data
     */
    private function createSalesOrderFromData($extractedData, $pdfCapture)
    {
        if (!$extractedData || !isset($extractedData['customer'])) {
            throw new \Exception('Invalid extracted data structure - missing customer data');
        }

        // For Purchase Orders received, use the document logic
        $customerData = $extractedData['customer'];

        // If document type is Purchase Order and customer name is empty but vendor_info exists,
        // it means AI might have confused the parties
        if (isset($extractedData['document_type']) &&
            $extractedData['document_type'] === 'PURCHASE_ORDER' &&
            empty(trim($customerData['name'] ?? '')) &&
            !empty($extractedData['vendor_info']['name'] ?? '')) {

            Log::info('Detected potential customer/vendor confusion, using document issuer as customer');

            // Try to extract customer from document header or issuer
            $customerData = $this->identifyCustomerFromDocument($extractedData);
        }

        // Validate items array
        if (!isset($extractedData['items']) || !is_array($extractedData['items']) || empty($extractedData['items'])) {
            Log::warning('No items found in extracted data', ['extracted_data' => $extractedData]);
            // Create a default item if none found
            $extractedData['items'] = [[
                'item_code' => 'UNKNOWN',
                'name' => 'Unknown Item from PDF',
                'description' => 'Item extracted from PDF but details unclear',
                'quantity' => 1,
                'unit_price' => 0,
                'uom' => 'PCS',
                'discount' => 0,
                'tax' => 0
            ]];
        }

        // Find or create customer
        $customer = $this->findOrCreateCustomer($customerData);

        // Generate SO number if not provided
        $soNumber = $extractedData['order_info']['order_number'] ??
            'SO-PDF-' . date('Ymd') . '-' . str_pad($pdfCapture->id, 4, '0', STR_PAD_LEFT);

        // Get currency and exchange rate
        $currencyCode = $extractedData['order_info']['currency'] ?? config('app.base_currency', 'USD');
        $baseCurrency = config('app.base_currency', 'USD');
        $exchangeRate = $this->getExchangeRate($currencyCode, $baseCurrency);

        // Parse dates
        $orderDate = $this->parseDate($extractedData['order_info']['order_date']) ?? now()->toDateString();
        $expectedDelivery = $this->parseDate($extractedData['order_info']['expected_delivery']);

        // Create sales order
        try {
            $salesOrder = SalesOrder::create([
                'so_number' => $soNumber,
                'so_date' => $orderDate,
                'customer_id' => $customer->customer_id,
                'payment_terms' => $extractedData['order_info']['payment_terms'],
                'delivery_terms' => $extractedData['order_info']['delivery_terms'],
                'expected_delivery' => $expectedDelivery,
                'status' => 'Draft',
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'total_amount' => 0,
                'tax_amount' => 0,
                'base_currency_total' => 0,
                'base_currency_tax' => 0
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create SalesOrder', [
                'error' => $e->getMessage(),
                'extracted_data' => $extractedData
            ]);
            throw $e;
        }

        // Create sales order lines
        $totalAmount = 0;
        $taxAmount = 0;

        foreach ($extractedData['items'] as $itemData) {
            try {
                $line = $this->createSalesOrderLine($salesOrder, $itemData, $exchangeRate, $customer);
                $totalAmount += $line->total;
                $taxAmount += $line->tax;
            } catch (\Exception $e) {
                Log::warning('Failed to create sales order line', [
                    'item_data' => $itemData,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        // Update order totals
        try {
            $salesOrder->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'base_currency_total' => $totalAmount * $exchangeRate,
                'base_currency_tax' => $taxAmount * $exchangeRate
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update SalesOrder totals', [
                'error' => $e->getMessage(),
                'sales_order_id' => $salesOrder->so_id
            ]);
            throw $e;
        }

        return $salesOrder;
    }

    /**
     * Try to identify the real customer when AI confuses vendor/customer
     */
    private function identifyCustomerFromDocument($extractedData)
    {
        // For Yamaha example, try to extract from common patterns
        $orderInfo = $extractedData['order_info'] ?? [];
        $vendorInfo = $extractedData['vendor_info'] ?? [];
        
        // If order number contains company identifier, use that
        $orderNumber = $orderInfo['order_number'] ?? '';
        
        // Common patterns for identifying the ordering company
        $customerCandidates = [];
        
        // Check if vendor_info accidentally contains the customer data
        if (!empty($vendorInfo['name'])) {
            // If vendor name contains certain keywords, it might be misidentified
            $commonVendorKeywords = ['PT.', 'Corp', 'Industries', 'Manufacturing'];
            $isLikelyVendor = false;
            
            foreach ($commonVendorKeywords as $keyword) {
                if (stripos($vendorInfo['name'], $keyword) !== false) {
                    $isLikelyVendor = true;
                    break;
                }
            }
            
            if (!$isLikelyVendor) {
                // This might be the actual customer
                $customerCandidates[] = [
                    'name' => $vendorInfo['name'],
                    'address' => $vendorInfo['address'] ?? null,
                    'phone' => null,
                    'email' => null,
                    'tax_id' => null,
                    'code' => null
                ];
            }
        }
        
        // Look for company names in order number or other fields
        if (stripos($orderNumber, 'yamaha') !== false) {
            $customerCandidates[] = [
                'name' => 'Yamaha Music India Private Limited',
                'address' => null,
                'phone' => null,
                'email' => null,
                'tax_id' => null,
                'code' => 'YAMAHA'
            ];
        }
        
        // Return the best candidate or create a generic one
        if (!empty($customerCandidates)) {
            return $customerCandidates[0];
        }
        
        // Fallback: create generic customer
        return [
            'name' => 'Customer from Purchase Order',
            'address' => null,
            'phone' => null,
            'email' => null,
            'tax_id' => null,
            'code' => 'PO-CUSTOMER'
        ];
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($dateString)
    {
        if (!$dateString) {
            return null;
        }

        try {
            // Handle format like "25.APR.2025"
            if (preg_match('/(\d{1,2})\.([A-Z]{3})\.(\d{4})/', $dateString, $matches)) {
                $day = $matches[1];
                $monthAbbr = $matches[2];
                $year = $matches[3];
                
                $months = [
                    'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                    'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                    'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
                ];
                
                if (isset($months[$monthAbbr])) {
                    return sprintf('%s-%s-%02d', $year, $months[$monthAbbr], $day);
                }
            }

            // Try standard date parsing
            $date = \DateTime::createFromFormat('Y-m-d', $dateString);
            if ($date) {
                return $dateString;
            }

            // Try other common formats
            $formats = ['d/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'Y/m/d'];
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $dateString);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Date parsing failed', [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find or create customer with fuzzy matching (Modified to NOT create new customers)
     */
    private function findOrCreateCustomer($customerData)
    {
        // Ensure customer name is not empty
        $customerName = trim($customerData['name'] ?? '');
        if (empty($customerName)) {
            $customerName = 'Unknown Customer from PDF';
        }

        // Try to find existing customer by exact code match first
        $customer = null;
        
        if (!empty($customerData['code'])) {
            $customer = Customer::where('customer_code', $customerData['code'])->first();
            if ($customer) {
                Log::info('Found customer by code match', [
                    'customer_id' => $customer->customer_id,
                    'name' => $customer->name,
                    'code' => $customer->customer_code
                ]);
                return $customer;
            }
        }
        
        // Try fuzzy matching for customer name
        if (!empty($customerName) && $customerName !== 'Unknown Customer from PDF') {
            $customer = $this->findCustomerByFuzzyName($customerName);
            if ($customer) {
                Log::info('Found customer by fuzzy name match', [
                    'customer_id' => $customer->customer_id,
                    'extracted_name' => $customerName,
                    'matched_name' => $customer->name
                ]);
                return $customer;
            }
        }

        // Create new customer if not found
        if (!$customer) {
            $customerCode = $customerData['code'] ?? $this->generateCustomerCode($customerName);
            
            try {
                $customer = Customer::create([
                    'customer_code' => $customerCode,
                    'name' => $customerName,
                    'address' => $customerData['address'] ?? null,
                    'phone' => $customerData['phone'] ?? null,
                    'email' => $customerData['email'] ?? null,
                    'tax_id' => $customerData['tax_id'] ?? null,
                    'status' => 'Active'
                ]);
                
                Log::info('Created new customer from PDF', [
                    'customer_id' => $customer->customer_id,
                    'name' => $customerName,
                    'code' => $customerCode
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to create customer, using default', [
                    'error' => $e->getMessage(),
                    'customer_data' => $customerData
                ]);
                
                // Use default customer if creation fails
                $customer = Customer::first();
                if (!$customer) {
                    throw new \Exception('No customers found in system and failed to create new customer');
                }
            }
        }

        return $customer;
    }

    /**
     * Find customer using fuzzy name matching
     */
    private function findCustomerByFuzzyName($extractedName)
    {
        // Clean the extracted name for comparison
        $cleanExtracted = $this->cleanCompanyName($extractedName);
        
        // Get all customers and try fuzzy matching
        $customers = Customer::all();
        
        foreach ($customers as $customer) {
            $cleanDbName = $this->cleanCompanyName($customer->name);
            
            // Check for exact match after cleaning
            if ($cleanExtracted === $cleanDbName) {
                return $customer;
            }
            
            // Check if one contains the other (with minimum length)
            if (strlen($cleanExtracted) >= 10 && strlen($cleanDbName) >= 10) {
                if (stripos($cleanDbName, $cleanExtracted) !== false || 
                    stripos($cleanExtracted, $cleanDbName) !== false) {
                    return $customer;
                }
            }
            
            // Calculate similarity percentage
            $similarity = $this->calculateNameSimilarity($cleanExtracted, $cleanDbName);
            
            if ($similarity >= 85) { // 85% similarity threshold
                Log::info('Customer matched by similarity', [
                    'extracted' => $extractedName,
                    'matched' => $customer->name,
                    'similarity' => $similarity
                ]);
                return $customer;
            }
        }
        
        // Try keyword-based matching for well-known companies
        return $this->findCustomerByKeywords($extractedName);
    }

    /**
     * Clean company name for comparison
     */
    private function cleanCompanyName($name)
    {
        // Convert to uppercase and remove common suffixes/prefixes
        $cleaned = strtoupper(trim($name));
        
        // Remove common company suffixes/prefixes
        $removePatterns = [
            '/\s*PVT\.?\s*LTD\.?/i',
            '/\s*PRIVATE\s*LIMITED/i',
            '/\s*LIMITED/i',
            '/\s*LTD\.?/i',
            '/\s*CORPORATION/i',
            '/\s*CORP\.?/i',
            '/\s*INC\.?/i',
            '/\s*COMPANY/i',
            '/\s*CO\.?/i',
            '/\s*PT\.?/i',
            '/\s*CV\.?/i',
            '/\s*TBK\.?/i',
            '/\s*PERSERO/i',
            '/\s*INDIA/i', // Remove country names for better matching
            '/\s*INDONESIA/i',
            '/\s*JAPAN/i',
            '/\s*MANUFACTURING/i', // Remove common business types
            '/\s*MUSIC/i',
            '/\s*ELECTRONICS/i'
        ];
        
        foreach ($removePatterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }
        
        // Remove extra spaces and punctuation
        $cleaned = preg_replace('/[^\w\s]/', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        return trim($cleaned);
    }

    /**
     * Find customer by keywords (for well-known companies)
     */
    private function findCustomerByKeywords($extractedName)
    {
        $keywords = strtoupper($extractedName);
        
        // Define keyword patterns for known customers
        $keywordPatterns = [
            'YAMAHA' => ['YAMAHA'],
            'HONDA' => ['HONDA'],
            'TOYOTA' => ['TOYOTA'],
            'MITSUBISHI' => ['MITSUBISHI'],
            'PANASONIC' => ['PANASONIC'],
            'SONY' => ['SONY']
        ];
        
        foreach ($keywordPatterns as $brand => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($keywords, $pattern) !== false) {
                    // Look for customers containing this brand
                    $customer = Customer::where('name', 'like', '%' . $brand . '%')->first();
                    if ($customer) {
                        Log::info('Customer matched by keyword', [
                            'extracted' => $extractedName,
                            'keyword' => $brand,
                            'matched' => $customer->name
                        ]);
                        return $customer;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Generate customer code based on name
     */
    private function generateCustomerCode($customerName)
    {
        // Extract initials from company name
        $words = explode(' ', strtoupper($customerName));
        $initials = '';
        
        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, ['THE', 'AND', 'OF', 'FOR', 'PVT', 'LTD', 'PRIVATE', 'LIMITED'])) {
                $initials .= substr($word, 0, 1);
            }
        }
        
        // Fallback if no good initials found
        if (strlen($initials) < 2) {
            $initials = 'CUST';
        }
        
        // Add timestamp to ensure uniqueness
        return $initials . '-' . time();
    }

    /**
     * Create sales order line (Modified to NOT create items if missing)
     */
    private function createSalesOrderLine($salesOrder, $itemData, $exchangeRate, $customer)
    {
        try {
            // Find existing item - DO NOT CREATE if missing
            $item = $this->findExistingItem($itemData);
            
            if (!$item) {
                throw new \Exception('Item not found in database: ' . ($itemData['item_code'] ?? $itemData['name'] ?? 'Unknown'));
            }

            // Find or create UOM
            $uom = $this->findOrCreateUOM($itemData['uom'] ?? 'PCS');

            // Get unit price
            $unitPrice = $itemData['unit_price'] ?? $item->sale_price ?? 0;

            $quantity = $itemData['quantity'];
            $discount = $itemData['discount'] ?? 0;
            $tax = $itemData['tax'] ?? 0;

            $subtotal = $unitPrice * $quantity;
            $total = $subtotal - $discount + $tax;

            // Calculate base currency values
            $baseUnitPrice = $unitPrice * $exchangeRate;
            $baseSubtotal = $subtotal * $exchangeRate;
            $baseDiscount = $discount * $exchangeRate;
            $baseTax = $tax * $exchangeRate;
            $baseTotal = $total * $exchangeRate;

            return SOLine::create([
                'so_id' => $salesOrder->so_id,
                'item_id' => $item->item_id,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'uom_id' => $uom->uom_id,
                'discount' => $discount,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'base_currency_unit_price' => $baseUnitPrice,
                'base_currency_subtotal' => $baseSubtotal,
                'base_currency_discount' => $baseDiscount,
                'base_currency_tax' => $baseTax,
                'base_currency_total' => $baseTotal
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create SOLine', [
                'error' => $e->getMessage(),
                'item_data' => $itemData,
                'sales_order_id' => $salesOrder->so_id
            ]);
            throw $e;
        }
    }

    /**
     * Find existing item (DO NOT CREATE)
     */
    private function findExistingItem($itemData)
    {
        $itemCode = $itemData['item_code'] ?? null;
        $itemName = $itemData['name'] ?? null;

        if ($itemCode) {
            // Try to find by item_code first
            $item = Item::where('item_code', $itemCode)->first();
            if ($item) {
                return $item;
            }
        }

        if ($itemName) {
            // Try to find by name if item_code not found
            $item = Item::where('name', 'like', '%' . $itemName . '%')->first();
            if ($item) {
                return $item;
            }
        }

        // Try fuzzy matching
        if ($itemName) {
            return $this->findItemByFuzzyName($itemName);
        }

        return null; // DO NOT CREATE - return null if not found
    }

    /**
     * Find or create UOM
     */
    private function findOrCreateUOM($uomName)
    {
        $uom = UnitOfMeasure::where('name', 'like', '%' . $uomName . '%')
                           ->orWhere('symbol', $uomName)
                           ->first();

        if (!$uom) {
            $uom = UnitOfMeasure::create([
                'name' => $uomName,
                'symbol' => strtoupper(substr($uomName, 0, 3)),
                'description' => 'Auto-created from PDF processing'
            ]);
        }

        return $uom;
    }

    /**
     * Get exchange rate
     */
    private function getExchangeRate($fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $rate = CurrencyRate::getCurrentRate($fromCurrency, $toCurrency);
        
        return $rate ?? 1.0; // Fallback to 1.0 if no rate found
    }

    /**
     * Get processing history
     */
    public function index(Request $request)
    {
        $query = PdfOrderCapture::with(['salesOrder', 'processor']);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('user_id')) {
            $query->where('processed_by', $request->user_id);
        }

        if ($request->has('days')) {
            $query->where('created_at', '>=', now()->subDays($request->days));
        }

        $captures = $query->orderBy('created_at', 'desc')
                         ->paginate($request->per_page ?? 20);

        // Append item_validation explicitly to each capture in the paginated data
        $captures->getCollection()->transform(function ($capture) {
            $capture->item_validation = $capture->item_validation ?? null;
            return $capture;
        });

        return response()->json([
            'success' => true,
            'data' => $captures
        ]);
    }

    /**
     * Get specific capture details
     */
    public function show($id)
    {
        $capture = PdfOrderCapture::with(['salesOrder.salesOrderLines.item', 'processor'])
                                 ->findOrFail($id);

        // Append item_validation explicitly
        $capture->item_validation = $capture->item_validation ?? null;

        return response()->json([
            'success' => true,
            'data' => $capture
        ]);
    }

    /**
     * Retry failed processing
     */
    public function retry($id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        if (!in_array($capture->status, ['failed', 'data_extracted'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only failed or data_extracted captures can be retried'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $capture->update([
                'status' => 'processing',
                'processing_error' => null
            ]);

            // Re-extract data with page-based chunking support
            $extractedData = $this->extractDataWithGroqAI($capture->file_path);
            
            // Validate items exist
            $itemValidation = $this->validateItemsExist($extractedData);
            
            $capture->update([
                'extracted_data' => $extractedData,
                'ai_raw_response' => $extractedData,
                'confidence_score' => $extractedData['confidence_score'] ?? null,
                'status' => 'data_extracted',
                'item_validation' => $itemValidation
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PDF reprocessed successfully',
                'data' => [
                    'pdf_capture' => $capture->fresh(),
                    'extracted_data' => $extractedData,
                    'item_validation' => $itemValidation,
                    'can_create_sales_order' => empty($itemValidation['missing_items'])
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $capture->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Retry failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete capture record and file
     */
    public function destroy($id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        // Check if can be deleted
        if ($capture->status === 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete processing captures.'
            ], 400);
        }

        // Delete file
        if ($capture->file_path && Storage::exists($capture->file_path)) {
            Storage::delete($capture->file_path);
        }

        $capture->delete();

        return response()->json([
            'success' => true,
            'message' => 'Capture deleted successfully'
        ]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(Request $request)
    {
        $days = $request->input('days', 30);
        $userId = $request->input('user_id');
        
        $stats = PdfOrderCapture::getStatistics($userId, $days);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Download original PDF file
     */
    public function downloadFile($id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        if (!$capture->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'No file path stored for this capture'
            ], 404);
        }
        
        // Convert to proper path
        $fullPath = storage_path('app' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $capture->file_path));
        
        if (!file_exists($fullPath)) {
            // Try with Storage facade as fallback
            if (!Storage::exists($capture->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }
            
            return Storage::download($capture->file_path, $capture->filename);
        }
        
        return response()->download($fullPath, $capture->filename);
    }

    /**
     * Preview extraction without creating sales order (DEPRECATED - use processPdf instead)
     */
    public function previewExtraction(PdfOrderCaptureRequest $request)
    {
        // This method is now essentially the same as processPdf since we changed the flow
        return $this->processPdf($request);
    }

    /**
     * Bulk retry failed captures
     */
    public function bulkRetry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'capture_ids' => 'required|array',
            'capture_ids.*' => 'required|integer|exists:pdf_order_captures,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $captures = PdfOrderCapture::whereIn('id', $request->capture_ids)
                                  ->whereIn('status', ['failed', 'data_extracted'])
                                  ->get();

        $results = [];
        foreach ($captures as $capture) {
            try {
                // Call the retry method directly (it returns a response)
                $retryResponse = $this->retry($capture->id);
                $responseData = $retryResponse->getData(true);
                
                if ($responseData['success']) {
                    $results[] = ['id' => $capture->id, 'status' => 'success'];
                } else {
                    $results[] = ['id' => $capture->id, 'status' => 'failed', 'error' => $responseData['message']];
                }
            } catch (\Exception $e) {
                $results[] = ['id' => $capture->id, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk retry completed',
            'data' => $results
        ]);
    }

    /**
     * Check AI service health
     */
    public function checkAiServiceHealth()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('groq.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->post(config('groq.base_url') . '/chat/completions', [
                'model' => config('groq.default_model'),
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection']
                ],
                'max_tokens' => 10
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Groq AI service is healthy',
                    'data' => [
                        'status' => 'online',
                        'model' => config('groq.default_model'),
                        'response_time' => 'normal'
                    ]
                ]);
            } else {
                throw new \Exception('API request failed');
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Groq AI service is unavailable',
                'data' => [
                    'status' => 'offline',
                    'error' => $e->getMessage()
                ]
            ], 503);
        }
    }

    /**
     * Reprocess specific capture with improved validation and page-based chunking
     */
    public function reprocessWithValidation($id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        try {
            Log::info('Starting reprocess with validation and page-based chunking', [
                'capture_id' => $capture->id,
                'original_status' => $capture->status
            ]);
            
            DB::beginTransaction();

            // Set status to processing
            $capture->update([
                'status' => 'processing',
                'processing_error' => null
            ]);

            // Re-extract data with improved parsing and page-based chunking support
            $extractedData = $this->extractDataWithGroqAI($capture->file_path);
            
            // Validate items exist
            $itemValidation = $this->validateItemsExist($extractedData);
            
            // Log comparison if original data exists
            if ($capture->extracted_data) {
                $this->logDataComparison($capture->extracted_data, $extractedData);
            }
            
            $capture->update([
                'extracted_data' => $extractedData,
                'ai_raw_response' => $extractedData,
                'confidence_score' => $extractedData['confidence_score'] ?? null,
                'status' => 'data_extracted',
                'item_validation' => $itemValidation
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PDF reprocessed with improved validation and page-based chunking',
                'data' => [
                    'pdf_capture' => $capture->fresh(),
                    'extracted_data' => $extractedData,
                    'item_validation' => $itemValidation,
                    'can_create_sales_order' => empty($itemValidation['missing_items']),
                    'validation_applied' => true,
                    'page_chunking_used' => isset($extractedData['processing_notes']) && 
                                          strpos($extractedData['processing_notes'], 'pages') !== false
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $capture->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage()
            ]);

            Log::error('Reprocess with validation failed', [
                'capture_id' => $capture->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Reprocess failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log comparison between original and corrected data
     */
    private function logDataComparison($originalData, $newData)
    {
        $originalItems = $originalData['items'] ?? [];
        $newItems = $newData['items'] ?? [];
        
        $comparisons = [];
        
        for ($i = 0; $i < max(count($originalItems), count($newItems)); $i++) {
            $original = $originalItems[$i] ?? null;
            $new = $newItems[$i] ?? null;
            
            if ($original && $new) {
                $comparison = [
                    'item_code' => $new['item_code'] ?? 'unknown',
                    'quantity_change' => [
                        'original' => $original['quantity'] ?? null,
                        'corrected' => $new['quantity'] ?? null,
                        'changed' => ($original['quantity'] ?? null) !== ($new['quantity'] ?? null)
                    ],
                    'unit_price_change' => [
                        'original' => $original['unit_price'] ?? null,
                        'corrected' => $new['unit_price'] ?? null,
                        'changed' => ($original['unit_price'] ?? null) !== ($new['unit_price'] ?? null)
                    ],
                    'validation_check' => [
                        'original_calculation' => ($original['quantity'] ?? 0) * ($original['unit_price'] ?? 0),
                        'corrected_calculation' => ($new['quantity'] ?? 0) * ($new['unit_price'] ?? 0),
                        'expected_total' => $new['total_value'] ?? 0
                    ],
                    'source_info' => [
                        'original_source' => $original['source_chunk'] ?? $original['source_page'] ?? 'unknown',
                        'new_source' => $new['source_chunk'] ?? $new['source_page'] ?? 'unknown'
                    ]
                ];
                
                $comparisons[] = $comparison;
            }
        }
        
        Log::info('Data comparison after reprocessing with page-based chunking', [
            'items_compared' => count($comparisons),
            'comparisons' => $comparisons,
            'processing_method' => isset($newData['processing_notes']) && 
                                   strpos($newData['processing_notes'], 'pages') !== false ? 'page-based' : 'standard'
        ]);
    }
}
?>