<!-- frontend/erp/src/views/sales/AIExcelForecastImport.vue -->
<template>
    <div class="ai-excel-import-container">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-robot mr-3"></i>
                AI Excel Forecast Import
            </h1>
            <p class="page-subtitle">
                Upload Excel files and let AI automatically extract sales forecast data
            </p>
            
            <!-- Template Download Section -->
            <div class="template-download-section">
                <h3><i class="fas fa-download"></i> Download Template</h3>
                <p>Start with our pre-formatted templates for the best AI recognition results</p>
                
                <div class="template-options">
                    <div v-for="template in availableTemplates" 
                         :key="template.type" 
                         class="template-card">
                        <div class="template-info">
                            <h4>{{ template.name }}</h4>
                            <p>{{ template.description }}</p>
                            <small class="text-muted">{{ template.recommended_for }}</small>
                        </div>
                        <button class="btn btn-outline-primary" 
                                @click="downloadTemplate(template.type)"
                                :disabled="isDownloadingTemplate">
                            <i class="fas fa-download"></i>
                            <span v-if="!isDownloadingTemplate">Download</span>
                            <span v-else>Downloading...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Upload Section -->
            <div class="upload-section">
                <div class="card gradient-border">
                    <div class="card-header">
                        <h3><i class="fas fa-upload"></i> Upload Excel File</h3>
                        <span class="badge badge-ai">AI Powered</span>
                    </div>
                    
                    <div class="card-body">
                        <!-- Customer Selection -->
                        <div class="form-group">
                            <label for="customer">Customer *</label>
                            <select v-model="formData.customer_id" 
                                    id="customer" 
                                    class="form-control"
                                    :disabled="isProcessing">
                                <option value="">Select Customer</option>
                                <option v-for="customer in customers" 
                                        :key="customer.customer_id" 
                                        :value="customer.customer_id">
                                    {{ customer.name }} ({{ customer.customer_code }})
                                </option>
                            </select>
                        </div>

                        <!-- Forecast Issue Date -->
                        <div class="form-group">
                            <label for="issueDate">Forecast Issue Date *</label>
                            <input type="date" 
                                   v-model="formData.forecast_issue_date" 
                                   id="issueDate"
                                   class="form-control"
                                   :disabled="isProcessing">
                        </div>

                        <!-- Duplicate Handling Section -->
                        <div class="duplicate-handling-section" v-if="duplicateAnalysis?.has_duplicates">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Duplicates Detected!</strong> 
                                Found {{ duplicateAnalysis.duplicate_count }} duplicate item-period combinations.
                            </div>
                            
                            <h4><i class="fas fa-layer-group"></i> How to Handle Duplicates?</h4>
                            <p class="text-muted">Choose how to handle duplicate entries for the same item and period:</p>
                            
                            <div class="duplicate-options">
                                <div v-for="option in duplicateHandlingOptions" 
                                     :key="option.value" 
                                     class="duplicate-option"
                                     :class="{ selected: formData.duplicate_handling === option.value }"
                                     @click="formData.duplicate_handling = option.value">
                                    <div class="option-icon">
                                        <i :class="option.icon"></i>
                                    </div>
                                    <div class="option-content">
                                        <h5>{{ option.label }}</h5>
                                        <p>{{ option.description }}</p>
                                        <small class="example">{{ option.example }}</small>
                                    </div>
                                    <div class="option-radio">
                                        <input type="radio" 
                                               :value="option.value" 
                                               v-model="formData.duplicate_handling"
                                               :id="'duplicate_' + option.value">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Duplicate Preview -->
                            <div class="duplicate-preview" v-if="duplicateAnalysis.duplicates.length > 0">
                                <h6><i class="fas fa-eye"></i> Duplicate Preview:</h6>
                                <div class="duplicate-examples">
                                    <div v-for="(duplicate, index) in duplicateAnalysis.duplicates.slice(0, 3)" 
                                         :key="index" 
                                         class="duplicate-example">
                                        <div class="duplicate-info">
                                            <strong>{{ duplicate.item_code }}</strong> - {{ duplicate.period }}
                                        </div>
                                        <div class="duplicate-values">
                                            Values: {{ duplicate.values.join(', ') }}
                                        </div>
                                        <div class="duplicate-result">
                                            Result: <span class="result-value">{{ calculateDuplicateResult(duplicate.values) }}</span>
                                        </div>
                                    </div>
                                    
                                    <div v-if="duplicateAnalysis.duplicates.length > 3" class="more-duplicates">
                                        ... and {{ duplicateAnalysis.duplicates.length - 3 }} more
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AI Settings -->
                        <div class="ai-settings">
                            <h4><i class="fas fa-cogs"></i> AI Settings</h4>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="aiModel">AI Model</label>
                                    <select v-model="formData.ai_model" 
                                            id="aiModel" 
                                            class="form-control"
                                            :disabled="isProcessing">
                                        <option value="llama3-8b-8192">Llama 3 8B (Fast)</option>
                                        <option value="mixtral-8x7b-32768">Mixtral 8x7B (Detailed)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <label for="confidenceThreshold">Auto-Save Threshold</label>
                                    <div class="input-group">
                                        <input type="range" 
                                               v-model="formData.confidence_threshold" 
                                               id="confidenceThreshold"
                                               min="0.1" 
                                               max="1.0" 
                                               step="0.1"
                                               class="form-range"
                                               :disabled="isProcessing">
                                        <span class="confidence-value">{{ Math.round(formData.confidence_threshold * 100) }}%</span>
                                    </div>
                                    <small class="text-muted">Forecasts will auto-save if AI confidence is above this threshold</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" 
                                           v-model="formData.auto_save" 
                                           class="custom-control-input" 
                                           id="autoSave"
                                           :disabled="isProcessing">
                                    <label class="custom-control-label" for="autoSave">
                                        Enable Auto-Save (when confidence is high)
                                    </label>
                                </div>
                            </div>

                            <!-- Add duplicate handling to AI settings if no duplicates detected yet -->
                            <div v-if="!duplicateAnalysis?.has_duplicates" class="form-group">
                                <label for="duplicateHandling">Duplicate Handling (if found)</label>
                                <select v-model="formData.duplicate_handling" 
                                        id="duplicateHandling" 
                                        class="form-control"
                                        :disabled="isProcessing">
                                    <option v-for="option in duplicateHandlingOptions" 
                                            :key="option.value" 
                                            :value="option.value">
                                        {{ option.label }} - {{ option.description }}
                                    </option>
                                </select>
                                <small class="text-muted">How to handle if duplicate item-period entries are found</small>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="file-upload-zone" 
                             :class="{ 'dragover': isDragOver, 'has-file': selectedFile, 'validated': fileValidation?.valid }"
                             @drop.prevent="handleDrop"
                             @dragover.prevent="isDragOver = true"
                             @dragleave.prevent="isDragOver = false">
                            
                            <div v-if="!selectedFile" class="upload-prompt">
                                <i class="fas fa-file-excel upload-icon"></i>
                                <h4>Drop Excel file here or click to browse</h4>
                                <p>Supports .xlsx and .xls files up to 10MB</p>
                                <button type="button" 
                                        class="btn btn-primary"
                                        @click="$refs.fileInput.click()"
                                        :disabled="isProcessing">
                                    <i class="fas fa-folder-open"></i> Browse Files
                                </button>
                            </div>

                            <div v-else class="file-selected">
                                <div class="file-info">
                                    <i class="fas fa-file-excel file-icon"></i>
                                    <div class="file-details">
                                        <h5>{{ selectedFile.name }}</h5>
                                        <p>{{ formatFileSize(selectedFile.size) }}</p>
                                        
                                        <!-- Validation Status -->
                                        <div v-if="fileValidation" class="validation-status">
                                            <div v-if="fileValidation.valid" class="validation-success">
                                                <i class="fas fa-check-circle"></i>
                                                File structure validated successfully
                                            </div>
                                            <div v-else class="validation-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                File has structure issues
                                            </div>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <button type="button" 
                                                class="btn btn-sm btn-info"
                                                @click="validateFile"
                                                :disabled="isValidating">
                                            <i class="fas fa-check"></i>
                                            <span v-if="!isValidating">Validate</span>
                                            <span v-else>Validating...</span>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeFile"
                                                :disabled="isProcessing">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Validation Results -->
                                <div v-if="fileValidation && !fileValidation.valid" class="validation-details">
                                    <div class="validation-issues">
                                        <h6><i class="fas fa-exclamation-triangle"></i> Issues Found:</h6>
                                        <ul>
                                            <li v-for="issue in fileValidation.details.issues" :key="issue">
                                                {{ issue }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="validation-suggestions">
                                        <h6><i class="fas fa-lightbulb"></i> Suggestions:</h6>
                                        <ul>
                                            <li v-for="suggestion in fileValidation.details.suggestions" :key="suggestion">
                                                {{ suggestion }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- File Preview -->
                                <div v-if="fileValidation?.preview" class="file-preview">
                                    <h6><i class="fas fa-eye"></i> Data Preview (First 5 rows):</h6>
                                    <div class="preview-table">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th v-for="(header, index) in Object.keys(fileValidation.preview[0] || {})" 
                                                        :key="index">
                                                        {{ header }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(row, index) in fileValidation.preview" :key="index">
                                                    <td v-for="(value, key) in row" :key="key">
                                                        {{ value }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="file" 
                               ref="fileInput" 
                               @change="handleFileSelect"
                               accept=".xlsx,.xls"
                               style="display: none">

                        <!-- Process Button -->
                        <div class="action-buttons">
                            <button type="button" 
                                    class="btn btn-ai btn-lg"
                                    @click="processWithAI"
                                    :disabled="!canProcess || isProcessing">
                                <i class="fas fa-robot"></i>
                                <span v-if="!isProcessing">Process with AI</span>
                                <span v-else>Processing...</span>
                            </button>
                            
                            <div v-if="fileValidation && !fileValidation.valid" class="process-warning">
                                <i class="fas fa-info-circle"></i>
                                File has validation issues. AI processing may be less accurate.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="results-section" v-if="showResults">
                <!-- Processing Status -->
                <div v-if="isProcessing" class="card processing-card">
                    <div class="card-body text-center">
                        <div class="processing-animation">
                            <div class="ai-brain">
                                <i class="fas fa-brain"></i>
                            </div>
                            <div class="processing-steps">
                                <div class="step" :class="{ active: currentStep >= 1 }">
                                    <i class="fas fa-file-upload"></i>
                                    <span>Reading Excel</span>
                                </div>
                                <div class="step" :class="{ active: currentStep >= 2 }">
                                    <i class="fas fa-robot"></i>
                                    <span>AI Analysis</span>
                                </div>
                                <div class="step" :class="{ active: currentStep >= 3 }">
                                    <i class="fas fa-check"></i>
                                    <span>Extraction Complete</span>
                                </div>
                            </div>
                        </div>
                        <h4>{{ processingMessage }}</h4>
                        <p class="text-muted">Please wait while AI analyzes your Excel file...</p>
                    </div>
                </div>

                <!-- AI Results -->
                <div v-if="aiResults && !isProcessing" class="card results-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> AI Extraction Results</h3>
                        <div class="confidence-badge" :class="confidenceLevel">
                            <i class="fas fa-brain"></i>
                            Confidence: {{ Math.round(aiResults.ai_confidence * 100) }}%
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Analysis Summary -->
                        <div class="analysis-summary">
                            <h5>Analysis Summary</h5>
                            <p>{{ aiResults.analysis || 'AI analysis completed successfully' }}</p>
                            
                            <div class="summary-stats">
                                <div class="stat">
                                    <span class="value">{{ aiResults.extracted_forecasts?.length || 0 }}</span>
                                    <span class="label">Items Found</span>
                                </div>
                                <div class="stat">
                                    <span class="value">{{ totalForecasts }}</span>
                                    <span class="label">Total Forecasts</span>
                                </div>
                                <div class="stat">
                                    <span class="value">{{ aiResults.ai_confidence ? Math.round(aiResults.ai_confidence * 100) : 0 }}%</span>
                                    <span class="label">Confidence</span>
                                </div>
                            </div>
                        </div>

                        <!-- Duplicate Processing Summary -->
                        <div v-if="aiResults.duplicate_info?.length > 0" class="duplicate-summary">
                            <h5><i class="fas fa-layer-group"></i> Duplicate Processing Summary</h5>
                            <div class="duplicate-stats">
                                <div class="stat">
                                    <span class="value">{{ aiResults.duplicate_info.length }}</span>
                                    <span class="label">Duplicates Processed</span>
                                </div>
                                <div class="stat">
                                    <span class="value">{{ formData.duplicate_handling.toUpperCase() }}</span>
                                    <span class="label">Method Used</span>
                                </div>
                            </div>
                            
                            <div class="duplicate-details">
                                <h6>Processed Duplicates:</h6>
                                <div class="duplicate-list">
                                    <div v-for="(dupInfo, index) in aiResults.duplicate_info.slice(0, showAllDuplicates ? aiResults.duplicate_info.length : 5)" 
                                         :key="index" 
                                         class="duplicate-item">
                                        <span class="item-period">{{ dupInfo.item_code }} - {{ formatPeriod(dupInfo.period) }}</span>
                                        <span class="original-values">{{ dupInfo.original_values.join(' + ') }}</span>
                                        <span class="arrow">→</span>
                                        <span class="final-value">{{ formatNumber(dupInfo.final_value) }}</span>
                                    </div>
                                    
                                    <div v-if="aiResults.duplicate_info.length > 5" class="show-more">
                                        <button class="btn btn-sm btn-link" @click="showAllDuplicates = !showAllDuplicates">
                                            {{ showAllDuplicates ? 'Show Less' : 'Show All (' + aiResults.duplicate_info.length + ')' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Identified Patterns -->
                        <div v-if="aiResults.identified_patterns?.length" class="patterns-section">
                            <h6><i class="fas fa-search"></i> Identified Patterns</h6>
                            <div class="pattern-tags">
                                <span v-for="pattern in aiResults.identified_patterns" 
                                      :key="pattern" 
                                      class="badge badge-success">
                                    {{ pattern }}
                                </span>
                            </div>
                        </div>

                        <!-- Potential Issues -->
                        <div v-if="aiResults.potential_issues?.length" class="issues-section">
                            <h6><i class="fas fa-exclamation-triangle"></i> Potential Issues</h6>
                            <div class="issue-tags">
                                <span v-for="issue in aiResults.potential_issues" 
                                      :key="issue" 
                                      class="badge badge-warning">
                                    {{ issue }}
                                </span>
                            </div>
                        </div>

                        <!-- Auto-Save Status -->
                        <div v-if="aiResults.auto_saved" class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Auto-Saved!</strong> {{ aiResults.saved_count }} forecasts were automatically saved.
                            <div v-if="aiResults.duplicate_info?.length > 0" class="duplicate-auto-save-info">
                                {{ aiResults.duplicate_info.length }} duplicates were processed using "{{ formData.duplicate_handling }}" method.
                            </div>
                        </div>

                        <!-- Manual Review Required -->
                        <div v-if="aiResults.requires_review" class="alert alert-info">
                            <i class="fas fa-eye"></i>
                            <strong>Review Required:</strong> Please review the extracted data before saving (confidence below threshold).
                        </div>
                    </div>
                </div>

                <!-- Extracted Data Preview -->
                <div v-if="aiResults?.extracted_forecasts?.length && !aiResults.auto_saved" class="card preview-card">
                    <div class="card-header">
                        <h3><i class="fas fa-table"></i> Extracted Forecasts</h3>
                        <button class="btn btn-success" 
                                @click="saveExtractedData"
                                :disabled="isSaving">
                            <i class="fas fa-save"></i>
                            <span v-if="!isSaving">Save All Forecasts</span>
                            <span v-else>Saving...</span>
                        </button>
                    </div>

                    <div class="card-body">
                        <div class="forecast-preview">
                            <div v-for="(forecast, index) in aiResults.extracted_forecasts" 
                                 :key="index" 
                                 class="forecast-item">
                                <div class="item-header">
                                    <h5>{{ forecast.item_code }}</h5>
                                    <span class="period-count">{{ Object.keys(forecast.periods).length }} periods</span>
                                </div>
                                
                                <div class="periods-grid">
                                    <div v-for="(quantity, period) in forecast.periods" 
                                         :key="period" 
                                         class="period-cell">
                                        <div class="period-label">{{ formatPeriod(period) }}</div>
                                        <div class="quantity-value">{{ formatNumber(quantity) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Display -->
                <div v-if="error" class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Error:</strong> {{ error }}
                </div>
            </div>
        </div>

        <!-- AI Processing History -->
        <div class="history-section">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent AI Imports</h3>
                    <button class="btn btn-outline-primary btn-sm" @click="loadAIHistory">
                        <i class="fas fa-refresh"></i> Refresh
                    </button>
                </div>
                
                <div class="card-body">
                    <div v-if="aiHistory.length === 0" class="text-center text-muted">
                        <i class="fas fa-robot fa-3x mb-3"></i>
                        <p>No AI imports yet. Upload your first Excel file above!</p>
                    </div>
                    
                    <div v-else class="history-list">
                        <div v-for="item in aiHistory" 
                             :key="item.forecast_id" 
                             class="history-item">
                            <div class="history-info">
                                <h6>{{ item.item.name }}</h6>
                                <p class="text-muted">
                                    Customer: {{ item.customer.name }} | 
                                    Period: {{ formatPeriod(item.forecast_period) }} |
                                    Quantity: {{ formatNumber(item.forecast_quantity) }}
                                </p>
                            </div>
                            <div class="history-meta">
                                <span class="badge badge-ai">{{ item.forecast_source }}</span>
                                <small class="text-muted">{{ formatDate(item.submission_date) }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Container -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div v-for="(toast, index) in toasts" 
                 :key="index"
                 class="toast show"
                 :class="toast.type">
                <div class="toast-header">
                    <i :class="getToastIcon(toast.type)"></i>
                    <strong class="me-auto">{{ getToastTitle(toast.type) }}</strong>
                    <button type="button" 
                            class="btn-close" 
                            @click="removeToast(index)"></button>
                </div>
                <div class="toast-body">
                    {{ toast.message }}
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'AIExcelForecastImport',
    data() {
        return {
            // Form data
            formData: {
                customer_id: '',
                forecast_issue_date: new Date().toISOString().split('T')[0],
                ai_model: 'llama3-8b-8192',
                confidence_threshold: 0.8,
                auto_save: false,
                duplicate_handling: 'last'
            },
            
            // File handling
            selectedFile: null,
            isDragOver: false,
            fileValidation: null,
            isValidating: false,
            
            // Processing state
            isProcessing: false,
            isSaving: false,
            isDownloadingTemplate: false,
            currentStep: 0,
            processingMessage: '',
            
            // Results
            showResults: false,
            aiResults: null,
            error: null,
            
            // Data
            customers: [],
            aiHistory: [],
            availableTemplates: [],
            duplicateHandlingOptions: [],
            
            // UI state
            showAllDuplicates: false,
            duplicateAnalysis: null,
            
            // Toast notifications
            toasts: []
        };
    },
    
    computed: {
        canProcess() {
            return this.selectedFile && 
                   this.formData.customer_id && 
                   this.formData.forecast_issue_date;
        },
        
        confidenceLevel() {
            if (!this.aiResults?.ai_confidence) return 'low';
            const confidence = this.aiResults.ai_confidence;
            if (confidence >= 0.8) return 'high';
            if (confidence >= 0.6) return 'medium';
            return 'low';
        },
        
        totalForecasts() {
            if (!this.aiResults?.extracted_forecasts) return 0;
            return this.aiResults.extracted_forecasts.reduce((total, forecast) => {
                return total + Object.keys(forecast.periods || {}).length;
            }, 0);
        }
    },
    
    async mounted() {
        await this.loadCustomers();
        await this.loadAIHistory();
        await this.loadAvailableTemplates();
        await this.loadDuplicateHandlingOptions();
    },
    
    methods: {
        // Load initial data
        async loadCustomers() {
            try {
                const response = await axios.get('/customers');
                this.customers = response.data.data || response.data;
            } catch (error) {
                console.error('Failed to load customers:', error);
                this.showToast('error', 'Failed to load customers');
            }
        },
        
        async loadAIHistory() {
            try {
                const response = await axios.get('/forecasts/ai-history');
                this.aiHistory = response.data.data || [];
            } catch (error) {
                console.error('Failed to load AI history:', error);
            }
        },
        
        async loadAvailableTemplates() {
            try {
                const response = await axios.get('/forecasts/excel-templates/types');
                this.availableTemplates = response.data.data || [];
            } catch (error) {
                console.error('Failed to load templates:', error);
                this.showToast('error', 'Failed to load templates');
            }
        },
        
        async loadDuplicateHandlingOptions() {
            try {
                const response = await axios.get('/forecasts/duplicate-handling-options');
                this.duplicateHandlingOptions = response.data.data || [];
                this.formData.duplicate_handling = response.data.default || 'last';
            } catch (error) {
                console.error('Failed to load duplicate handling options:', error);
                // Fallback options
                this.duplicateHandlingOptions = [
                    {
                        value: 'sum',
                        label: 'Sum All Values',
                        description: 'Add all duplicate values together',
                        example: 'Best for: Multiple shipments per period',
                        icon: 'fas fa-plus'
                    },
                    {
                        value: 'last',
                        label: 'Last Value (Default)',
                        description: 'Use the last occurrence',
                        example: 'Best for: Most recent estimates',
                        icon: 'fas fa-step-forward'
                    }
                ];
            }
        },
        
        // Template download
        async downloadTemplate(templateType) {
            try {
                this.isDownloadingTemplate = true;
                
                const response = await axios.get('/forecasts/excel-templates/download', {
                    params: { type: templateType },
                    responseType: 'blob'
                });
                
                // Create download link
                const blob = new Blob([response.data], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });
                
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `sales_forecast_template_${templateType}_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
                
                this.showToast('success', `Template downloaded: ${templateType}`);
                
            } catch (error) {
                console.error('Template download error:', error);
                this.showToast('error', 'Failed to download template');
            } finally {
                this.isDownloadingTemplate = false;
            }
        },
        
        // File handling
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.validateAndSetFile(file);
            }
        },
        
        handleDrop(event) {
            this.isDragOver = false;
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                this.validateAndSetFile(files[0]);
            }
        },
        
        validateAndSetFile(file) {
            // Validate file type
            const allowedTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel'
            ];
            
            if (!allowedTypes.includes(file.type) && 
                !file.name.match(/\.(xlsx|xls)$/i)) {
                this.showToast('error', 'Please select a valid Excel file (.xlsx or .xls)');
                return;
            }
            
            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                this.showToast('error', 'File size must be less than 10MB');
                return;
            }
            
            this.selectedFile = file;
            this.error = null;
            this.showResults = false;
            this.aiResults = null;
            this.fileValidation = null;
            this.duplicateAnalysis = null;
            
            // Auto-validate file
            setTimeout(() => {
                this.validateFile();
            }, 500);
        },
        
        removeFile() {
            this.selectedFile = null;
            this.$refs.fileInput.value = '';
            this.showResults = false;
            this.aiResults = null;
            this.error = null;
            this.fileValidation = null;
            this.duplicateAnalysis = null;
        },
        
        // File validation
        async validateFile() {
            if (!this.selectedFile) return;
            
            this.isValidating = true;
            this.fileValidation = null;
            this.duplicateAnalysis = null;
            
            try {
                const formData = new FormData();
                formData.append('excel_file', this.selectedFile);
                
                const response = await axios.post('/forecasts/excel-files/validate', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                });
                
                this.fileValidation = response.data;
                
                // Check for duplicates in validation response
                if (response.data.duplicate_analysis) {
                    this.duplicateAnalysis = response.data.duplicate_analysis;
                }
                
                if (this.fileValidation.valid) {
                    if (this.duplicateAnalysis?.has_duplicates) {
                        this.showToast('warning', `File validated with ${this.duplicateAnalysis.duplicate_count} duplicates found`);
                    } else {
                        this.showToast('success', 'File validation passed!');
                    }
                } else {
                    this.showToast('warning', 'File has validation issues. Please review.');
                }
                
            } catch (error) {
                console.error('Validation error:', error);
                this.showToast('error', 'Failed to validate file');
                this.fileValidation = {
                    valid: false,
                    message: 'Validation failed',
                    details: {
                        issues: ['Unable to validate file'],
                        suggestions: ['Try using one of our templates']
                    }
                };
            } finally {
                this.isValidating = false;
            }
        },
        
        // AI processing
        async processWithAI() {
            if (!this.canProcess) return;
            
            this.isProcessing = true;
            this.showResults = true;
            this.error = null;
            this.currentStep = 1;
            this.processingMessage = 'Reading Excel file...';
            
            try {
                const formData = new FormData();
                formData.append('excel_file', this.selectedFile);
                formData.append('customer_id', this.formData.customer_id);
                formData.append('forecast_issue_date', this.formData.forecast_issue_date);
                formData.append('ai_model', this.formData.ai_model);
                formData.append('confidence_threshold', this.formData.confidence_threshold);
                formData.append('auto_save', this.formData.auto_save);
                formData.append('duplicate_handling', this.formData.duplicate_handling);
                
                this.currentStep = 2;
                this.processingMessage = 'AI is analyzing your data...';
                
                // Use enhanced endpoint
                const response = await axios.post('/forecasts/import-excel-ai-enhanced', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    },
                    timeout: 120000
                });
                
                this.currentStep = 3;
                this.processingMessage = 'Extraction complete!';
                
                this.aiResults = response.data.data;
                
                // Update duplicate analysis if returned
                if (this.aiResults.duplicate_analysis) {
                    this.duplicateAnalysis = this.aiResults.duplicate_analysis;
                }
                
                if (this.aiResults.auto_saved) {
                    let message = `Successfully auto-saved ${this.aiResults.saved_count} forecasts!`;
                    if (this.aiResults.duplicate_info?.length > 0) {
                        message += ` (${this.aiResults.duplicate_info.length} duplicates processed)`;
                    }
                    this.showToast('success', message);
                    await this.loadAIHistory();
                } else {
                    this.showToast('info', 'AI extraction complete. Please review the data before saving.');
                }
                
            } catch (error) {
                console.error('AI processing error:', error);
                this.error = error.response?.data?.message || 'Failed to process Excel file with AI';
                this.showToast('error', this.error);
            } finally {
                this.isProcessing = false;
            }
        },
        
        // Save extracted data
        async saveExtractedData() {
            if (!this.aiResults?.extracted_forecasts?.length) return;
            
            this.isSaving = true;
            
            try {
                const response = await axios.post('/forecasts/save-ai-extracted', {
                    customer_id: this.formData.customer_id,
                    forecast_issue_date: this.formData.forecast_issue_date,
                    forecasts: this.aiResults.extracted_forecasts
                });
                
                this.showToast('success', `Successfully saved ${response.data.data.saved_count} forecasts!`);
                
                // Show any errors
                if (response.data.data.errors?.length > 0) {
                    this.showToast('warning', `${response.data.data.errors.length} items had errors`);
                }
                
                // Refresh history and reset form
                await this.loadAIHistory();
                this.resetForm();
                
            } catch (error) {
                console.error('Save error:', error);
                this.showToast('error', error.response?.data?.message || 'Failed to save forecasts');
            } finally {
                this.isSaving = false;
            }
        },
        
        resetForm() {
            this.selectedFile = null;
            this.$refs.fileInput.value = '';
            this.showResults = false;
            this.aiResults = null;
            this.error = null;
            this.currentStep = 0;
            this.fileValidation = null;
            this.duplicateAnalysis = null;
        },
        
        // Calculate duplicate result preview
        calculateDuplicateResult(values) {
            const numValues = values.map(v => parseFloat(v) || 0);
            
            switch (this.formData.duplicate_handling) {
                case 'sum':
                    return numValues.reduce((a, b) => a + b, 0);
                case 'average':
                    return (numValues.reduce((a, b) => a + b, 0) / numValues.length).toFixed(1);
                case 'max':
                    return Math.max(...numValues);
                case 'min':
                    return Math.min(...numValues);
                case 'first':
                    return numValues[0];
                case 'last':
                default:
                    return numValues[numValues.length - 1];
            }
        },
        
        // Utility methods
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        formatNumber(value) {
            return new Intl.NumberFormat().format(value);
        },
        
        formatPeriod(period) {
            try {
                const date = new Date(period + '-01');
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
            } catch {
                return period;
            }
        },
        
        formatDate(dateString) {
            try {
                return new Date(dateString).toLocaleDateString();
            } catch {
                return dateString;
            }
        },
        
        // Toast methods
        showToast(type, message) {
            const toast = { type, message, id: Date.now() };
            this.toasts.push(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                this.removeToast(this.toasts.indexOf(toast));
            }, 5000);
        },
        
        removeToast(index) {
            if (index > -1) {
                this.toasts.splice(index, 1);
            }
        },
        
        getToastIcon(type) {
            const icons = {
                success: 'fas fa-check-circle text-success',
                error: 'fas fa-exclamation-circle text-danger',
                warning: 'fas fa-exclamation-triangle text-warning',
                info: 'fas fa-info-circle text-info'
            };
            return icons[type] || icons.info;
        },
        
        getToastTitle(type) {
            const titles = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: 'Information'
            };
            return titles[type] || 'Notification';
        }
    }
};
</script>

<style scoped>
.ai-excel-import-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 1rem;
}

.page-subtitle {
    font-size: 1.2rem;
    color: #6c757d;
    margin: 0;
}

.template-download-section {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    padding: 2rem;
    border-radius: 16px;
    margin: 2rem 0;
    border: 1px solid #e0f2fe;
}

.template-download-section h3 {
    color: #0369a1;
    margin-bottom: 0.5rem;
}

.template-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.template-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.template-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.template-info h4 {
    margin: 0 0 0.5rem 0;
    color: #374151;
}

.template-info p {
    margin: 0 0 0.25rem 0;
    color: #6b7280;
    font-size: 0.9rem;
}

.template-info small {
    font-style: italic;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

.card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.gradient-border {
    position: relative;
    background: linear-gradient(white, white) padding-box,
                linear-gradient(135deg, #667eea, #764ba2) border-box;
    border: 2px solid transparent;
}

.card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #334155;
}

.card-body {
    padding: 2rem;
}

.badge-ai {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
    display: block;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.duplicate-handling-section {
    background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 10%, #fef3c7 100%);
    padding: 1.5rem;
    border-radius: 12px;
    margin: 1.5rem 0;
    border: 1px solid #fbbf24;
}

.duplicate-handling-section h4 {
    color: #92400e;
    margin-bottom: 0.5rem;
}

.duplicate-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.duplicate-option {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.duplicate-option:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.duplicate-option.selected {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
}

.option-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    flex-shrink: 0;
}

.duplicate-option.selected .option-icon {
    background: #3b82f6;
    color: white;
}

.option-content {
    flex: 1;
}

.option-content h5 {
    margin: 0 0 0.25rem 0;
    color: #374151;
    font-size: 0.95rem;
}

.option-content p {
    margin: 0 0 0.25rem 0;
    color: #6b7280;
    font-size: 0.85rem;
}

.option-content .example {
    color: #9ca3af;
    font-style: italic;
}

.option-radio {
    flex-shrink: 0;
}

.duplicate-preview {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
    border: 1px solid #e5e7eb;
}

.duplicate-preview h6 {
    margin-bottom: 0.75rem;
    color: #374151;
}

.duplicate-examples {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.duplicate-example {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
    padding: 0.5rem;
    background: #f9fafb;
    border-radius: 4px;
    font-size: 0.85rem;
}

.duplicate-info {
    font-weight: 500;
    color: #374151;
}

.duplicate-values {
    color: #6b7280;
}

.duplicate-result {
    color: #059669;
    font-weight: 500;
}

.result-value {
    background: #d1fae5;
    padding: 0.125rem 0.5rem;
    border-radius: 12px;
}

.more-duplicates {
    text-align: center;
    color: #6b7280;
    font-style: italic;
    padding: 0.5rem;
}

.ai-settings {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    padding: 1.5rem;
    border-radius: 12px;
    margin: 1.5rem 0;
}

.ai-settings h4 {
    color: #667eea;
    font-size: 1.1rem;
    margin-bottom: 1rem;
}

.form-range {
    width: 100%;
    margin: 0.5rem 0;
}

.confidence-value {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    margin-left: 1rem;
}

.file-upload-zone {
    border: 3px dashed #d1d5db;
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    transition: all 0.3s ease;
    margin: 2rem 0;
    background: #f9fafb;
}

.file-upload-zone.dragover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.file-upload-zone.has-file {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.05);
}

.file-upload-zone.validated {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.05);
}

.upload-icon {
    font-size: 4rem;
    color: #667eea;
    margin-bottom: 1rem;
}

.upload-prompt h4 {
    color: #374151;
    margin-bottom: 0.5rem;
}

.upload-prompt p {
    color: #6b7280;
    margin-bottom: 1.5rem;
}

.file-selected {
    display: flex;
    justify-content: center;
    flex-direction: column;
    align-items: center;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    margin-bottom: 1rem;
}

.file-icon {
    font-size: 2rem;
    color: #10b981;
}

.file-details h5 {
    margin: 0;
    color: #374151;
}

.file-details p {
    margin: 0;
    color: #6b7280;
    font-size: 0.9rem;
}

.file-actions {
    display: flex;
    gap: 0.5rem;
}

.validation-status {
    margin-top: 0.5rem;
}

.validation-success {
    color: #065f46;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.validation-warning {
    color: #92400e;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.validation-details {
    margin-top: 1rem;
    padding: 1rem;
    background: #fef3c7;
    border-radius: 6px;
    border: 1px solid #fbbf24;
    width: 100%;
}

.validation-issues, .validation-suggestions {
    margin-bottom: 1rem;
}

.validation-issues h6, .validation-suggestions h6 {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #92400e;
}

.validation-issues ul, .validation-suggestions ul {
    margin: 0;
    padding-left: 1.5rem;
    font-size: 0.85rem;
}

.file-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    width: 100%;
}

.file-preview h6 {
    margin-bottom: 0.75rem;
    color: #374151;
    font-size: 0.9rem;
}

.preview-table {
    max-height: 200px;
    overflow: auto;
}

.preview-table table {
    font-size: 0.8rem;
}

.preview-table th {
    background: #f3f4f6;
    color: #374151;
    font-weight: 600;
    padding: 0.5rem;
    white-space: nowrap;
}

.preview-table td {
    padding: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.action-buttons {
    text-align: center;
    margin-top: 2rem;
}

.btn-ai {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.btn-ai:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.btn-ai:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.process-warning {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-radius: 6px;
    color: #92400e;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.processing-card {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
}

.processing-animation {
    margin: 2rem 0;
}

.ai-brain {
    font-size: 4rem;
    color: #667eea;
    margin-bottom: 2rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
}

.processing-steps {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin: 2rem 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.4;
    transition: all 0.3s ease;
}

.step.active {
    opacity: 1;
    color: #667eea;
}

.step i {
    font-size: 1.5rem;
}

.confidence-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.confidence-badge.high {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.confidence-badge.medium {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.confidence-badge.low {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.analysis-summary {
    background: #f9fafb;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.stat {
    text-align: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.stat .value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #667eea;
}

.stat .label {
    display: block;
    font-size: 0.9rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.duplicate-summary {
    background: #f0f9ff;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1rem 0;
    border: 1px solid #0ea5e9;
}

.duplicate-summary h5 {
    color: #0369a1;
    margin-bottom: 1rem;
}

.duplicate-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.duplicate-stats .stat {
    text-align: center;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #bae6fd;
}

.duplicate-details {
    margin-top: 1rem;
}

.duplicate-details h6 {
    margin-bottom: 0.5rem;
    color: #0369a1;
}

.duplicate-list {
    background: white;
    border-radius: 6px;
    border: 1px solid #bae6fd;
    overflow: hidden;
}

.duplicate-item {
    display: grid;
    grid-template-columns: 2fr 2fr auto 1fr;
    gap: 0.5rem;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #f0f9ff;
    font-size: 0.85rem;
}

.duplicate-item:last-child {
    border-bottom: none;
}

.item-period {
    font-weight: 500;
    color: #374151;
}

.original-values {
    color: #6b7280;
    font-family: monospace;
}

.arrow {
    color: #3b82f6;
    font-weight: bold;
}

.final-value {
    color: #059669;
    font-weight: 500;
    background: #d1fae5;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.duplicate-auto-save-info {
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: #065f46;
}

.show-more {
    padding: 0.5rem;
    text-align: center;
    border-top: 1px solid #f0f9ff;
}

.patterns-section, .issues-section {
    margin: 1rem 0;
}

.pattern-tags, .issue-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.forecast-preview {
    max-height: 600px;
    overflow-y: auto;
}

.forecast-item {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.item-header h5 {
    margin: 0;
    color: #374151;
}

.period-count {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
}

.periods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
}

.period-cell {
    background: white;
    padding: 0.75rem;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    text-align: center;
}

.period-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.quantity-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
}

.history-section {
    margin-top: 3rem;
}

.history-list {
    max-height: 400px;
    overflow-y: auto;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.history-item:last-child {
    border-bottom: none;
}

.history-info h6 {
    margin: 0 0 0.25rem 0;
    color: #374151;
}

.history-info p {
    margin: 0;
    font-size: 0.9rem;
}

.history-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    color: #1e40af;
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #991b1b;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #92400e;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

/* Toast Styles */
.toast-container {
    z-index: 1055;
}

.toast {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    margin-bottom: 1rem;
    min-width: 300px;
}

.toast-header {
    background: #f9fafb;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.toast-body {
    padding: 1rem;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    margin-left: auto;
}

/* Responsive Design */
@media (max-width: 768px) {
    .ai-excel-import-container {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .content-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .template-options {
        grid-template-columns: 1fr;
    }
    
    .template-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .duplicate-options {
        grid-template-columns: 1fr;
    }
    
    .duplicate-option {
        flex-direction: column;
        text-align: center;
    }
    
    .processing-steps {
        flex-direction: column;
        gap: 1rem;
    }
    
    .summary-stats {
        grid-template-columns: 1fr;
    }
    
    .periods-grid {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
    
    .duplicate-example {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .duplicate-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
}
</style>