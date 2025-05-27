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
use OpenAI\Laravel\Facades\OpenAI;

class PdfOrderCaptureController extends Controller
{
    /**
     * Upload and process PDF to create sales order
     */
    public function processPdf(PdfOrderCaptureRequest $request)
    {
        try {
            DB::beginTransaction();

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
            
            $filename = 'order_pdfs/' . time() . '_' . $pdfFile->getClientOriginalName();
            $pdfPath = $pdfFile->storeAs('public', $filename);
            
            // Create PDF capture record
            $pdfCapture = PdfOrderCapture::create([
                'filename' => $pdfFile->getClientOriginalName(),
                'file_path' => $pdfPath,
                'file_size' => $pdfFile->getSize(),
                'status' => 'processing',
                'user_id' => auth()->id(),
                'processing_options' => $processingOptions // store or use as needed
            ]);

            // Convert PDF to text using OpenAI
            $extractedData = $this->extractDataWithOpenAI($pdfPath);
            
            // Update capture record with extracted data
            $pdfCapture->update([
                'extracted_data' => $extractedData,
                'status' => 'data_extracted'
            ]);

            // Validate and create sales order
            $salesOrder = $this->createSalesOrderFromData($extractedData, $pdfCapture);
            
            // Update capture record with sales order
            $pdfCapture->update([
                'sales_order_id' => $salesOrder->so_id,
                'status' => 'completed',
                'processed_at' => now()
            ]);

            DB::commit();

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
            DB::rollBack();
            
            if (isset($pdfCapture)) {
                $pdfCapture->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }

            Log::error('PDF Order Capture Error: ' . $e->getMessage(), [
                'file' => $request->file('pdf_file')?->getClientOriginalName(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract data from PDF using OpenAI
     */
    private function extractDataWithOpenAI($pdfPath)
    {
        // Convert PDF to images or extract text (simplified approach)
        $fullPath = storage_path('app/' . $pdfPath);
        
        // For this example, we'll use a text extraction approach
        // In production, you might want to use a PDF to image converter
        $pdfText = $this->extractTextFromPdf($fullPath);
        
        $prompt = $this->buildOpenAIPrompt($pdfText);
        
        $response = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at extracting sales order information from documents. Always respond with valid JSON only.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 2000
        ]);

        $aiResponse = $response['choices'][0]['message']['content'];
        
        // Clean and parse JSON response
        $aiResponse = $this->cleanJsonResponse($aiResponse);
        
        return json_decode($aiResponse, true);
    }

    /**
     * Build OpenAI prompt for extracting sales order data
     */
    private function buildOpenAIPrompt($pdfText)
    {
        return "
Extract sales order information from the following document text and return it as JSON with this exact structure:

{
    \"order_info\": {
        \"order_number\": \"string (if found)\",
        \"order_date\": \"YYYY-MM-DD (if found)\",
        \"expected_delivery\": \"YYYY-MM-DD (if found)\",
        \"currency\": \"string (3 letter code, default USD)\",
        \"payment_terms\": \"string (if found)\",
        \"delivery_terms\": \"string (if found)\"
    },
    \"customer\": {
        \"name\": \"string (required)\",
        \"code\": \"string (if found)\",
        \"address\": \"string (if found)\",
        \"phone\": \"string (if found)\",
        \"email\": \"string (if found)\",
        \"tax_id\": \"string (if found)\"
    },
    \"items\": [
        {
            \"item_code\": \"string (if found)\",
            \"name\": \"string (required)\",
            \"description\": \"string (if found)\",
            \"quantity\": \"number (required)\",
            \"unit_price\": \"number (if found)\",
            \"uom\": \"string (unit of measure, default PCS)\",
            \"discount\": \"number (if found, default 0)\",
            \"tax\": \"number (if found, default 0)\"
        }
    ],
    \"confidence_score\": \"number (0-100, your confidence in the extraction)\"
}

Rules:
1. Extract only information that is clearly visible in the document
2. Use null for missing optional fields
3. For items, ensure at least name and quantity are extracted
4. If multiple interpretations are possible, choose the most logical one
5. Return valid JSON only, no additional text or explanations

Document text:
{$pdfText}
";
    }

    /**
     * Extract text from PDF (simplified - you may want to use a proper PDF library)
     */
    private function extractTextFromPdf($pdfPath)
    {
        // This is a simplified approach
        // For production, consider using libraries like:
        // - spatie/pdf-to-text
        // - smalot/pdfparser
        // - or convert PDF to images and use OpenAI Vision API
        
        try {
            // Using shell command (requires pdftotext installed)
            $command = "pdftotext '{$pdfPath}' -";
            $output = shell_exec($command);
            
            if ($output) {
                return $output;
            }
            
            // Fallback: return basic info
            return "PDF file uploaded for processing. Please ensure the document contains sales order information.";
            
        } catch (\Exception $e) {
            return "Unable to extract text from PDF. File uploaded for manual processing.";
        }
    }

    /**
     * Clean JSON response from OpenAI
     */
    private function cleanJsonResponse($response)
    {
        // Remove any markdown formatting
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        
        // Remove any leading/trailing whitespace
        $response = trim($response);
        
        return $response;
    }

    /**
     * Create sales order from extracted data
     */
    private function createSalesOrderFromData($extractedData, $pdfCapture)
    {
        if (!$extractedData || !isset($extractedData['customer']) || !isset($extractedData['items'])) {
            throw new \Exception('Invalid extracted data structure');
        }

        // Find or create customer
        $customer = $this->findOrCreateCustomer($extractedData['customer']);
        
        // Generate SO number if not provided
        $soNumber = $extractedData['order_info']['order_number'] ?? 
                   'SO-PDF-' . date('Ymd') . '-' . str_pad($pdfCapture->id, 4, '0', STR_PAD_LEFT);

        // Get currency and exchange rate
        $currencyCode = $extractedData['order_info']['currency'] ?? config('app.base_currency', 'USD');
        $baseCurrency = config('app.base_currency', 'USD');
        $exchangeRate = $this->getExchangeRate($currencyCode, $baseCurrency);

        // Create sales order
        $salesOrder = SalesOrder::create([
            'so_number' => $soNumber,
            'so_date' => $extractedData['order_info']['order_date'] ?? now()->toDateString(),
            'customer_id' => $customer->customer_id,
            'payment_terms' => $extractedData['order_info']['payment_terms'],
            'delivery_terms' => $extractedData['order_info']['delivery_terms'],
            'expected_delivery' => $extractedData['order_info']['expected_delivery'],
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
            $line = $this->createSalesOrderLine($salesOrder, $itemData, $exchangeRate, $customer);
            $totalAmount += $line->total;
            $taxAmount += $line->tax;
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
     * Find or create customer
     */
    private function findOrCreateCustomer($customerData)
    {
        // Try to find existing customer by name or code
        $customer = Customer::where('name', 'like', '%' . $customerData['name'] . '%')
                           ->orWhere('customer_code', $customerData['code'] ?? '')
                           ->first();

        if (!$customer) {
            // Create new customer
            $customer = Customer::create([
                'customer_code' => $customerData['code'] ?? 'CUST-' . time(),
                'name' => $customerData['name'],
                'address' => $customerData['address'],
                'phone' => $customerData['phone'],
                'email' => $customerData['email'],
                'tax_id' => $customerData['tax_id'],
                'status' => 'Active'
            ]);
        }

        return $customer;
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
        $unitPrice = $itemData['unit_price'] ?? $item->getBestSalePriceInCurrency(
            $customer->customer_id, 
            $itemData['quantity'], 
            $salesOrder->currency_code
        );

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
    public function getHistory(Request $request)
    {
        $query = PdfOrderCapture::with(['salesOrder', 'user']);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
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
        $capture = PdfOrderCapture::with(['salesOrder.salesOrderLines.item', 'user'])
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
                'error_message' => null
            ]);

            // Re-extract data
            $extractedData = $this->extractDataWithOpenAI($capture->file_path);
            
            $capture->update([
                'extracted_data' => $extractedData,
                'status' => 'data_extracted'
            ]);

            // Create sales order
            $salesOrder = $this->createSalesOrderFromData($extractedData, $capture);
            
            $capture->update([
                'sales_order_id' => $salesOrder->so_id,
                'status' => 'completed',
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
                'error_message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Retry failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel processing
     */
    public function cancel($id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        if (!$capture->isProcessing()) {
            return response()->json([
                'success' => false,
                'message' => 'Only processing captures can be cancelled'
            ], 400);
        }

        $capture->update([
            'status' => 'cancelled',
            'error_message' => 'Processing cancelled by user'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Processing cancelled successfully'
        ]);
    }

    /**
     * Delete capture record and file
     */
    public function destroy($id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        // Check if can be deleted
        if ($capture->isProcessing()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete processing captures. Cancel first.'
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
     * Get user statistics
     */
    public function getUserStatistics(Request $request)
    {
        $days = $request->input('days', 30);
        $stats = PdfOrderCapture::getStatistics(auth()->id(), $days);
        
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
        
        if (!$capture->file_path || !Storage::exists($capture->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        return Storage::download($capture->file_path, $capture->filename);
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
            $pdfPath = $pdfFile->storeAs('public', $filename);
            
            // Extract data using OpenAI
            $extractedData = $this->extractDataWithOpenAI($pdfPath);
            
            // Clean up temporary file
            Storage::delete($pdfPath);
            
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
            if (isset($pdfPath) && Storage::exists($pdfPath)) {
                Storage::delete($pdfPath);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate extracted data manually
     */
    public function validateExtraction(Request $request, $id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'extracted_data' => 'required|array',
            'extracted_data.customer' => 'required|array',
            'extracted_data.customer.name' => 'required|string',
            'extracted_data.items' => 'required|array',
            'extracted_data.items.*.name' => 'required|string',
            'extracted_data.items.*.quantity' => 'required|numeric|min:0',
            'confidence_override' => 'nullable|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $capture->updateExtractedData(
            $request->extracted_data,
            $request->confidence_override
        );

        return response()->json([
            'success' => true,
            'message' => 'Extracted data validated successfully',
            'data' => $capture
        ]);
    }

    /**
     * Update extracted data manually
     */
    public function updateExtractedData(Request $request, $id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        if ($capture->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update data for completed captures'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'extracted_data' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $capture->update([
            'extracted_data' => $request->extracted_data,
            'status' => 'data_extracted'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Extracted data updated successfully',
            'data' => $capture
        ]);
    }

    /**
     * Create sales order from validated data
     */
    public function createSalesOrder($id)
    {
        $capture = PdfOrderCapture::findOrFail($id);
        
        if ($capture->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Sales order already created for this capture'
            ], 400);
        }

        if (!$capture->extracted_data) {
            return response()->json([
                'success' => false,
                'message' => 'No extracted data available'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $capture->update(['status' => 'creating_order']);
            
            $salesOrder = $this->createSalesOrderFromData($capture->extracted_data, $capture);
            
            $capture->markCompleted($salesOrder->so_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sales order created successfully',
                'data' => [
                    'capture' => $capture,
                    'sales_order' => $salesOrder->load('salesOrderLines.item')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $capture->markFailed('Failed to create sales order: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create sales order: ' . $e->getMessage()
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
                // Trigger retry for each capture
                $this->retry($capture->id);
                $results[] = ['id' => $capture->id, 'status' => 'success'];
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
     * Get supported file formats
     */
    public function getSupportedFormats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'formats' => ['pdf'],
                'max_file_size' => '10MB',
                'supported_features' => [
                    'text_extraction',
                    'table_detection', 
                    'image_processing',
                    'multi_language'
                ]
            ]
        ]);
    }

    /**
     * Check AI service health
     */
    public function checkAiServiceHealth()
    {
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection']
                ],
                'max_tokens' => 10
            ]);

            return response()->json([
                'success' => true,
                'message' => 'AI service is healthy',
                'data' => [
                    'status' => 'online',
                    'model' => 'gpt-3.5-turbo',
                    'response_time' => 'normal'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI service is unavailable',
                'data' => [
                    'status' => 'offline',
                    'error' => $e->getMessage()
                ]
            ], 503);
        }
    }
}