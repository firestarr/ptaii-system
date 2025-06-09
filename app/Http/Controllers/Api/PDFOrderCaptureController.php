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
     * Upload and process PDF to create sales order
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
            
            // Create PDF capture record FIRST (outside transaction for now)
            $pdfCapture = PdfOrderCapture::create([
                'filename' => $pdfFile->getClientOriginalName(),
                'file_path' => $pdfPath,
                'file_size' => $pdfFile->getSize(),
                'status' => 'processing', // Use string instead of constant for now
                'user_id' => auth()->id(),
                'processing_options' => $processingOptions
            ]);

            Log::info('PDF capture record created', [
                'capture_id' => $pdfCapture->id,
                'filename' => $pdfCapture->filename,
                'status' => $pdfCapture->status
            ]);

            // Convert PDF to text using Groq AI
            $extractedData = $this->extractDataWithGroqAI($pdfPath);
            
            // Update capture record with extracted data - use valid status
            $updated = $pdfCapture->update([
                'extracted_data' => $extractedData,
                'ai_raw_response' => $extractedData,
                'confidence_score' => $extractedData['confidence_score'] ?? null,
                'status' => 'data_extracted' // Use status that exists in database
            ]);

            Log::info('PDF capture updated with extracted data', [
                'capture_id' => $pdfCapture->id,
                'update_success' => $updated,
                'status' => $pdfCapture->fresh()->status,
                'confidence_score' => $pdfCapture->fresh()->confidence_score
            ]);

            // Start transaction for sales order creation
            DB::beginTransaction();

            // Validate and create sales order
            $salesOrder = $this->createSalesOrderFromData($extractedData, $pdfCapture);
            
            // Update capture record with sales order - use valid status
            $finalUpdate = $pdfCapture->update([
                'created_so_id' => $salesOrder->so_id,
                'status' => 'so_created', // Use status that exists in database
                'processed_by' => auth()->id(),
                'processed_at' => now()
            ]);

            Log::info('PDF capture marked as completed', [
                'capture_id' => $pdfCapture->id,
                'so_id' => $salesOrder->so_id,
                'final_update_success' => $finalUpdate,
                'final_status' => $pdfCapture->fresh()->status
            ]);

            DB::commit();

            // Reload the capture to ensure we have latest data
            $pdfCapture->refresh();

            return response()->json([
                'success' => true,
                'message' => 'PDF processed and sales order created successfully',
                'data' => [
                    'pdf_capture' => $pdfCapture,
                    'sales_order' => $salesOrder->load('salesOrderLines.item'),
                    'extracted_data' => $extractedData
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

            // Rollback transaction if it was started
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            if ($pdfCapture) {
                try {
                    $pdfCapture->update([
                        'status' => 'failed', // Use string status
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
     * Extract data from PDF using Groq AI
     */
    private function extractDataWithGroqAI($storedPath)
    {
        // Convert stored path to full system path with proper separators
        $fullPath = storage_path('app/public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath));
        
        Log::info('Starting PDF text extraction', [
            'stored_path' => $storedPath,
            'full_path' => $fullPath,
            'file_exists' => file_exists($fullPath),
            'file_size' => file_exists($fullPath) ? filesize($fullPath) : 'N/A'
        ]);
        
        // Verify file exists before processing
        if (!file_exists($fullPath)) {
            throw new \Exception('PDF file not found at: ' . $fullPath);
        }
        
        // Extract text from PDF
        $pdfText = $this->extractTextFromPdf($fullPath);
        
        // Log the extracted text for debugging
        Log::info('Extracted PDF text for AI processing', [
            'file_path' => $storedPath,
            'text_length' => strlen($pdfText),
            'text_preview' => substr($pdfText, 0, 500)
        ]);
        
        $prompt = $this->buildGroqAIPrompt($pdfText);
        
        $requestData = [
            'model' => config('groq.default_model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at extracting purchase order and sales order information from documents. Always respond with valid JSON only. No markdown formatting, no explanations, just pure JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 4000
        ];
        
        Log::info('Sending request to Groq AI', [
            'model' => $requestData['model'],
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
        
        Log::info('Cleaned AI response', [
            'cleaned_response' => $aiResponse
        ]);
        
        $decodedData = json_decode($aiResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON decode error', [
                'error' => json_last_error_msg(),
                'response' => $aiResponse
            ]);
            throw new \Exception('Invalid JSON response from AI: ' . json_last_error_msg());
        }
        
        Log::info('Successfully decoded AI response', [
            'decoded_data' => $decodedData
        ]);
        
        return $decodedData;
    }

    /**
     * Build Groq AI prompt for extracting sales order data
     */
    private function buildGroqAIPrompt($pdfText)
    {
        return "
You are an expert at extracting purchase order and sales order information from documents. 

CRITICAL RULES FOR CUSTOMER IDENTIFICATION:
For PURCHASE ORDERS (documents we receive):
- The CUSTOMER = The company that ISSUED/SENT the purchase order (the buyer)
- This is usually at the TOP of the document, in the header or letterhead
- Look for company names like 'Yamaha Music India', 'Toyota', etc. at the document header
- IGNORE the 'Vendor' section - that's us (the supplier receiving the order)
- IGNORE 'Deliver To' addresses if they contain supplier info

DOCUMENT HEADER ANALYSIS:
1. Find the company name at the very TOP of the document
2. This is typically the company issuing the purchase order
3. Extract their full company name, address, phone, email, tax ID

EXTRACTION RULES:
- Extract the ISSUING company as the customer (from document header)
- Extract order details (PO number, dates, terms)
- Extract items from tables with quantities and prices

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
            \"quantity\": \"extract quantity as number\",
            \"unit_price\": \"extract unit price as number\",
            \"uom\": \"extract unit of measure (PC, KG, etc.)\",
            \"discount\": \"extract discount amount as number\",
            \"tax\": \"extract tax amount as number\",
            \"total_value\": \"extract line total/net value as number\"
        }
    ],
    \"confidence_score\": \"rate your confidence 0-100\"
}

SPECIFIC EXAMPLE FOR THIS TYPE OF DOCUMENT:
- Document header shows: 'Yamaha Music India Private Limited' → This is the CUSTOMER
- Vendor section shows: 'PT. ARMSTRONG INDUSTRI INDONESIA' → This is the supplier (us)
- Customer = Yamaha (the company issuing the PO)
- Vendor = PT. Armstrong (the company receiving the PO)

IMPORTANT:
- Focus on the document HEADER/LETTERHEAD for customer identification
- Do NOT use vendor information as customer information
- Convert dates from '25.APR.2025' format to '2025-04-25'
- Extract ALL items from item tables
- Return only valid JSON, no explanations

Document text to analyze:
{$pdfText}
";
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

        // Fix missing commas between JSON key-value pairs by adding commas at line breaks where missing
        // This is a heuristic and may not cover all cases
        $response = preg_replace('/"\s*"\s*:/', '"," :', $response); // Fix adjacent quotes without comma
        $response = preg_replace('/"\s*"\s*,/', '"," ,', $response);
        $response = preg_replace('/"\s*"\s*}/', '"," }', $response);

        // Add commas between key-value pairs if missing (between closing quote and next opening quote)
        $response = preg_replace('/"(\s*)(")/', '",$2', $response);

        return $response;
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
            }
        }

        // Update order totals
        $salesOrder->update([
            'total_amount' => $totalAmount,
            'tax_amount' => $taxAmount,
            'base_currency_total' => $totalAmount * $exchangeRate,
            'base_currency_tax' => $taxAmount * $exchangeRate
        ]);

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
     * Find or create customer with fuzzy matching
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
     * Create sales order line
     */
    private function createSalesOrderLine($salesOrder, $itemData, $exchangeRate, $customer)
    {
        // Find or create item
        $item = $this->findOrCreateItem($itemData);
        
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
    }

    /**
     * Find or create item
     */
    private function findOrCreateItem($itemData)
    {
        // Try to find existing item
        $item = Item::where('name', 'like', '%' . $itemData['name'] . '%')
                   ->orWhere('item_code', $itemData['item_code'] ?? '')
                   ->first();

        if (!$item) {
            // Create new item
            $item = Item::create([
                'item_code' => $itemData['item_code'] ?? 'ITEM-' . time(),
                'name' => $itemData['name'],
                'description' => $itemData['description'],
                'is_sellable' => true,
                'sale_price' => $itemData['unit_price'] ?? 0,
                'sale_price_currency' => config('app.base_currency', 'USD')
            ]);
        }

        return $item;
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
        
        if ($capture->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Only failed captures can be retried'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $capture->update([
                'status' => 'processing',
                'processing_error' => null
            ]);

            // Re-extract data
            $extractedData = $this->extractDataWithGroqAI($capture->file_path);
            
            $capture->update([
                'extracted_data' => $extractedData,
                'ai_raw_response' => $extractedData,
                'confidence_score' => $extractedData['confidence_score'] ?? null,
                'status' => 'extracted'
            ]);

            // Create sales order
            $salesOrder = $this->createSalesOrderFromData($extractedData, $capture);
            
            $capture->update([
                'created_so_id' => $salesOrder->so_id,
                'status' => 'so_created',
                'processed_by' => auth()->id(),
                'processed_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PDF reprocessed successfully',
                'data' => [
                    'pdf_capture' => $capture,
                    'sales_order' => $salesOrder->load('salesOrderLines.item')
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
     * Preview extraction without creating sales order
     */
    public function previewExtraction(PdfOrderCaptureRequest $request)
    {
        try {
            // Store PDF file temporarily
            $pdfFile = $request->file('pdf_file');
            $filename = 'temp/' . time() . '_' . $pdfFile->getClientOriginalName();
            $storedPath = $pdfFile->storeAs('public', $filename);
            
            // Extract data using Groq AI
            $extractedData = $this->extractDataWithGroqAI($storedPath);
            
            // Clean up temporary file
            Storage::delete($storedPath);
            
            return response()->json([
                'success' => true,
                'message' => 'Data extracted successfully',
                'data' => [
                    'extracted_data' => $extractedData,
                    'confidence_score' => $extractedData['confidence_score'] ?? null
                ]
            ]);
            
        } catch (\Exception $e) {
            // Clean up temporary file on error
            if (isset($storedPath) && Storage::exists($storedPath)) {
                Storage::delete($storedPath);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data: ' . $e->getMessage()
            ], 500);
        }
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
                                  ->where('status', 'failed')
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
     * Debug PDF text extraction (for testing)
     */
    public function debugTextExtraction(Request $request)
    {
        try {
            $pdfFile = $request->file('pdf_file');
            
            if (!$pdfFile || $pdfFile->getMimeType() !== 'application/pdf') {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload a valid PDF file'
                ], 422);
            }
            
            // Store PDF file temporarily
            $filename = 'debug/' . time() . '_' . $pdfFile->getClientOriginalName();
            $pdfPath = $pdfFile->storeAs('public', $filename);
            $fullPath = storage_path('app/' . $pdfPath);
            
            // Check if pdftotext is available
            $pdfToTextCheck = shell_exec('which pdftotext 2>/dev/null');
            $pdfToTextInstalled = !empty(trim($pdfToTextCheck));
            
            // Try different extraction methods and capture results
            $extractionResults = [];
            
            if ($pdfToTextInstalled) {
                $methods = [
                    'default' => "pdftotext '{$fullPath}' -",
                    'layout' => "pdftotext -layout '{$fullPath}' -",
                    'raw' => "pdftotext -raw '{$fullPath}' -",
                    'utf8' => "pdftotext -enc UTF-8 '{$fullPath}' -"
                ];
                
                foreach ($methods as $methodName => $command) {
                    $output = shell_exec($command);
                    $extractionResults[$methodName] = [
                        'success' => !empty($output) && strlen(trim($output)) > 10,
                        'length' => $output ? strlen($output) : 0,
                        'first_200_chars' => $output ? substr($output, 0, 200) : null
                    ];
                }
            }
            
            // Try our main extraction method
            $finalResult = null;
            $finalError = null;
            
            try {
                $finalResult = $this->extractTextFromPdf($fullPath);
            } catch (\Exception $e) {
                $finalError = $e->getMessage();
            }
            
            // Clean up
            Storage::delete($pdfPath);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $pdfFile->getClientOriginalName(),
                    'file_size' => $pdfFile->getSize(),
                    'pdftotext_installed' => $pdfToTextInstalled,
                    'pdftotext_path' => trim($pdfToTextCheck ?: 'Not found'),
                    'extraction_methods' => $extractionResults,
                    'final_extraction' => [
                        'success' => $finalResult !== null,
                        'error' => $finalError,
                        'text_length' => $finalResult ? strlen($finalResult) : 0,
                        'text_preview' => $finalResult ? substr($finalResult, 0, 500) : null
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Text extraction debug failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Check system requirements for PDF processing
     */
    public function debugSystemRequirements()
    {
        try {
            // Check for pdftotext
            $pdfToText = shell_exec('which pdftotext 2>/dev/null');
            $pdfToTextVersion = shell_exec('pdftotext -v 2>&1');
            
            // Check PHP extensions
            $extensions = [
                'fileinfo' => extension_loaded('fileinfo'),
                'mbstring' => extension_loaded('mbstring'),
                'curl' => extension_loaded('curl')
            ];
            
            // Check writable directories
            $directories = [
                'storage/app/public' => is_writable(storage_path('app/public')),
                'storage/logs' => is_writable(storage_path('logs')),
                'temp' => is_writable(sys_get_temp_dir())
            ];
            
            // Check shell_exec availability
            $shellExecDisabled = in_array('shell_exec', explode(',', ini_get('disable_functions')));
            
            return response()->json([
                'success' => true,
                'data' => [
                    'pdf_tools' => [
                        'pdftotext_installed' => !empty(trim($pdfToText)),
                        'pdftotext_path' => trim($pdfToText ?: 'Not found'),
                        'pdftotext_version' => trim($pdfToTextVersion ?: 'N/A')
                    ],
                    'php_extensions' => $extensions,
                    'directories' => $directories,
                    'system' => [
                        'shell_exec_enabled' => !$shellExecDisabled,
                        'php_version' => PHP_VERSION,
                        'os' => PHP_OS,
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size')
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'System requirements check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug database table status
     */
    public function debugDatabaseStatus()
    {
        try {
            // Check if table exists
            $tableExists = DB::getSchemaBuilder()->hasTable('pdf_order_captures');
            
            // Get table columns if exists
            $columns = [];
            if ($tableExists) {
                $columns = DB::getSchemaBuilder()->getColumnListing('pdf_order_captures');
            }
            
            // Get recent records count
            $totalRecords = $tableExists ? PdfOrderCapture::count() : 0;
            $recentRecords = $tableExists ? PdfOrderCapture::orderBy('created_at', 'desc')->limit(5)->get() : [];
            
            // Get current constraints info
            $constraints = [];
            if ($tableExists) {
                try {
                    $constraints = DB::select("
                        SELECT constraint_name, constraint_type 
                        FROM information_schema.table_constraints 
                        WHERE table_name = 'pdf_order_captures'
                    ");
                } catch (\Exception $e) {
                    $constraints = ['error' => $e->getMessage()];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'table_exists' => $tableExists,
                    'columns' => $columns,
                    'total_records' => $totalRecords,
                    'recent_records' => $recentRecords,
                    'model_fillable' => (new PdfOrderCapture())->getFillable(),
                    'database_constraints' => $constraints,
                    'valid_statuses' => [
                        'uploaded', 'processing', 'data_extracted', 
                        'so_created', 'failed', 'cancelled'
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database debug failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Test customer matching
     */
    public function debugCustomerMatching(Request $request)
    {
        $extractedName = $request->input('name', 'Yamaha Music India');
        
        try {
            // Get all customers for reference
            $allCustomers = Customer::select('customer_id', 'customer_code', 'name')->get();
            
            // Test fuzzy matching
            $matchedCustomer = $this->findCustomerByFuzzyName($extractedName);
            
            // Test name cleaning
            $cleanedName = $this->cleanCompanyName($extractedName);
            
            // Test similarity scores with all customers
            $similarities = [];
            foreach ($allCustomers as $customer) {
                $cleanDbName = $this->cleanCompanyName($customer->name);
                $similarity = $this->calculateNameSimilarity($cleanedName, $cleanDbName);
                $similarities[] = [
                    'customer_id' => $customer->customer_id,
                    'customer_name' => $customer->name,
                    'cleaned_name' => $cleanDbName,
                    'similarity' => round($similarity, 2)
                ];
            }
            
            // Sort by similarity
            usort($similarities, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'extracted_name' => $extractedName,
                    'cleaned_name' => $cleanedName,
                    'matched_customer' => $matchedCustomer ? [
                        'id' => $matchedCustomer->customer_id,
                        'name' => $matchedCustomer->name,
                        'code' => $matchedCustomer->customer_code
                    ] : null,
                    'all_customers_count' => $allCustomers->count(),
                    'top_similarities' => array_slice($similarities, 0, 10)
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer matching debug failed: ' . $e->getMessage()
            ], 500);
        }
    }
}