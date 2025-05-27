<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class PdfOrderCaptureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'pdf_file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:10240' // 10MB max file size
            ],
            'auto_create_missing_data' => 'nullable|boolean',
            'preferred_currency' => 'nullable|string|size:3',
            'default_customer_id' => 'nullable|exists:Customer,customer_id',
            'processing_options' => 'nullable|array',
            'processing_options.extract_images' => 'nullable|boolean',
            'processing_options.use_ocr' => 'nullable|boolean',
            'processing_options.confidence_threshold' => 'nullable|numeric|min:0|max:100',
            'processing_options.auto_approve' => 'nullable|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'pdf_file.required' => 'PDF file is required.',
            'pdf_file.file' => 'The uploaded file must be a valid file.',
            'pdf_file.mimes' => 'Only PDF files are allowed.',
            'pdf_file.max' => 'PDF file size cannot exceed 10MB.',
            'preferred_currency.size' => 'Currency code must be exactly 3 characters.',
            'default_customer_id.exists' => 'The selected customer does not exist.',
            'processing_options.confidence_threshold.min' => 'Confidence threshold must be at least 0.',
            'processing_options.confidence_threshold.max' => 'Confidence threshold cannot exceed 100.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'pdf_file' => 'PDF file',
            'auto_create_missing_data' => 'auto create missing data',
            'preferred_currency' => 'preferred currency',
            'default_customer_id' => 'default customer',
            'processing_options.extract_images' => 'extract images option',
            'processing_options.use_ocr' => 'use OCR option',
            'processing_options.confidence_threshold' => 'confidence threshold',
            'processing_options.auto_approve' => 'auto approve option'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Custom validation for file content
            if ($this->hasFile('pdf_file')) {
                $file = $this->file('pdf_file');
                
                // Check if file is actually a PDF by examining magic bytes
                $handle = fopen($file->getPathname(), 'rb');
                $header = fread($handle, 4);
                fclose($handle);
                
                if (strpos($header, '%PDF') !== 0) {
                    $validator->errors()->add('pdf_file', 'The file does not appear to be a valid PDF.');
                }
                
                // Check if file is not corrupted (basic check)
                if ($file->getSize() < 100) {
                    $validator->errors()->add('pdf_file', 'The PDF file appears to be corrupted or too small.');
                }
            }
            
            // Validate confidence threshold logic
            if ($this->has('processing_options.confidence_threshold')) {
                $threshold = $this->input('processing_options.confidence_threshold');
                $autoApprove = $this->input('processing_options.auto_approve', false);
                
                if ($autoApprove && $threshold < 80) {
                    $validator->errors()->add(
                        'processing_options.confidence_threshold',
                        'Confidence threshold must be at least 80% when auto-approve is enabled.'
                    );
                }
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values for processing options
        $processingOptions = $this->input('processing_options', []);
        
        // If processingOptions is a string, decode it to array
        if (is_string($processingOptions)) {
            $decoded = json_decode($processingOptions, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $processingOptions = $decoded;
            } else {
                $processingOptions = [];
            }
        }
        
        $defaultOptions = [
            'extract_images' => false,
            'use_ocr' => true,
            'confidence_threshold' => 70,
            'auto_approve' => false
        ];
        
        $mergedOptions = array_merge($defaultOptions, $processingOptions);
        
        $this->merge([
            'processing_options' => $mergedOptions,
            'auto_create_missing_data' => $this->input('auto_create_missing_data', true),
            'preferred_currency' => strtoupper($this->input('preferred_currency', config('app.base_currency', 'USD')))
        ]);
    }

    /**
     * Get validated processing options with defaults.
     */
    public function getProcessingOptions(): array
    {
        return $this->input('processing_options', []);
    }

    /**
     * Check if auto creation of missing data is enabled.
     */
    public function shouldAutoCreateMissingData(): bool
    {
        return $this->input('auto_create_missing_data', true);
    }

    /**
     * Get the preferred currency for the order.
     */
    public function getPreferredCurrency(): string
    {
        return $this->input('preferred_currency', config('app.base_currency', 'USD'));
    }

    /**
     * Get the default customer ID if specified.
     */
    public function getDefaultCustomerId(): ?int
    {
        return $this->input('default_customer_id');
    }

    /**
     * Check if auto approval is enabled.
     */
    public function shouldAutoApprove(): bool
    {
        return $this->input('processing_options.auto_approve', false);
    }

    /**
     * Get the confidence threshold.
     */
    public function getConfidenceThreshold(): float
    {
        return (float) $this->input('processing_options.confidence_threshold', 70);
    }

    /**
     * Check if OCR should be used.
     */
    public function shouldUseOCR(): bool
    {
        return $this->input('processing_options.use_ocr', true);
    }

    /**
     * Check if images should be extracted.
     */
    public function shouldExtractImages(): bool
    {
        return $this->input('processing_options.extract_images', false);
    }
}