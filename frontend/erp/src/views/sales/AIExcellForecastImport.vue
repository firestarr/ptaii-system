<template>
  <div class="ai-excel-forecast-container">
    <div class="main-wrapper">
      <!-- Header Section -->
      <div class="header-section">
        <div class="header-content">
          <h1 class="main-title">AI Excel Forecast Import</h1>
          <p class="main-subtitle">Upload customer forecast Excel files and let AI extract the data automatically</p>
        </div>
        <div class="header-decoration"></div>
      </div>

      <!-- Upload Form -->
      <div v-if="!previewData" class="upload-section">
        <div class="upload-card">
          <div class="card-header">
            <h2 class="card-title">Upload Forecast File</h2>
            <div class="title-underline"></div>
          </div>
          
          <form @submit.prevent="processFile" class="upload-form">
            <!-- Customer Selection -->
            <div class="form-group">
              <label class="form-label">
                <span class="label-text">Customer</span>
                <span class="label-required">*</span>
              </label>
              <div class="select-wrapper">
                <select
                  v-model="form.customer_id"
                  required
                  class="form-select"
                >
                  <option value="">Select Customer</option>
                  <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                    {{ customer.name }}
                  </option>
                </select>
                <div class="select-arrow">
                  <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                    <path d="M1 1.5L6 6.5L11 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
              </div>
            </div>

            <!-- Issue Date -->
            <div class="form-group">
              <label class="form-label">
                <span class="label-text">Forecast Issue Date</span>
                <span class="label-required">*</span>
              </label>
              <input
                type="date"
                v-model="form.forecast_issue_date"
                required
                class="form-input"
              />
            </div>

            <!-- File Upload -->
            <div class="form-group">
              <label class="form-label">
                <span class="label-text">Excel File</span>
                <span class="label-required">*</span>
              </label>
              <div class="file-upload-area" :class="{ 'has-file': selectedFile }">
                <input
                  type="file"
                  ref="fileInput"
                  @change="handleFileSelect"
                  accept=".xlsx,.xls"
                  class="file-input"
                />
                
                <div v-if="!selectedFile" class="upload-placeholder">
                  <div class="upload-icon">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                      <path d="M28 8H12C9.79086 8 8 9.79086 8 12V36C8 38.2091 9.79086 40 12 40H36C38.2091 40 40 38.2091 40 36V20M28 8L40 20M28 8V20H40" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M16 24H32M16 28H28M16 32H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </div>
                  <div class="upload-content">
                    <p class="upload-text">
                      <button type="button" @click="safeCall(() => $refs.fileInput && $refs.fileInput.click())" class="upload-button">
                        Click to upload
                      </button>
                      or drag and drop
                    </p>
                    <p class="upload-hint">Excel files (.xlsx, .xls) up to 10MB</p>
                  </div>
                </div>
                
                <div v-else class="file-selected">
                  <div class="file-info">
                    <div class="file-icon">
                      <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <path d="M20 4H8C6.89543 4 6 4.89543 6 6V26C6 27.1046 6.89543 28 8 28H24C25.1046 28 26 27.1046 26 26V10L20 4Z" fill="#10B981" stroke="#10B981" stroke-width="1.5"/>
                        <path d="M20 4V10H26" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M10 16H22M10 20H18" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                      </svg>
                    </div>
                    <div class="file-details">
                      <p class="file-name">{{ selectedFile.name }}</p>
                      <p class="file-size">{{ formatFileSize(selectedFile.size) }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="removeFile"
                    class="remove-file-btn"
                  >
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                      <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
              <button
                type="submit"
                :disabled="!selectedFile || !form.customer_id || loading"
                class="submit-btn"
                :class="{ 'loading': loading }"
              >
                <div class="btn-content">
                  <svg v-if="loading" class="loading-spinner" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
                    <path fill="currentColor" opacity="0.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                  </svg>
                  <span>{{ loading ? 'Processing with AI...' : 'Process with AI' }}</span>
                </div>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Preview Results -->
      <div v-if="previewData" class="preview-section">
        <!-- Summary Card -->
        <div class="summary-card">
          <div class="card-header">
            <h2 class="card-title">AI Extraction Results</h2>
            <button @click="resetForm" class="close-btn">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </button>
          </div>

          <!-- Supplier Info -->
          <div v-if="previewData.supplier_info" class="supplier-info">
            <h3 class="section-title">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" class="section-icon">
                <path d="M10 2L3 7V18H17V7L10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M8 18V12H12V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Supplier Information
            </h3>
            <div class="supplier-grid">
              <div class="supplier-item">
                <span class="supplier-label">Name:</span>
                <span class="supplier-value">{{ previewData.supplier_info.name || 'N/A' }}</span>
              </div>
              <div class="supplier-item">
                <span class="supplier-label">Code:</span>
                <span class="supplier-value">{{ previewData.supplier_info.code || 'N/A' }}</span>
              </div>
              <div class="supplier-item">
                <span class="supplier-label">Location:</span>
                <span class="supplier-value">{{ previewData.supplier_info.location || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Summary Stats -->
          <div class="stats-grid">
            <div class="stat-card valid">
              <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="stat-content">
                <div class="stat-number">{{ previewData.summary.valid_items }}</div>
                <div class="stat-label">Valid Items</div>
              </div>
            </div>
            
            <div class="stat-card total">
              <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M9 11H15M9 15H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L19.7071 9.70711C19.8946 9.89464 20 10.149 20 10.4142V19C20 20.1046 19.1046 21 18 21H17Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="stat-content">
                <div class="stat-number">{{ previewData.summary.total_forecasts }}</div>
                <div class="stat-label">Total Forecasts</div>
              </div>
            </div>
            
            <div class="stat-card periods">
              <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M8 2V6M16 2V6M3 10H21M5 4H19C20.1046 4 21 4.89543 21 6V20C21 21.1046 20.1046 22 19 22H5C3.89543 22 3 21.1046 3 20V6C3 4.89543 3.89543 4 5 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="stat-content">
                <div class="stat-number">{{ previewData.summary.periods_count }}</div>
                <div class="stat-label">Periods</div>
              </div>
            </div>
            
            <div class="stat-card warnings">
              <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64087 18.3024 1.55555 18.6453 1.57553 18.9892C1.59552 19.3331 1.72021 19.6648 1.93586 19.9472C2.15151 20.2296 2.44776 20.4503 2.78516 20.5858C3.12256 20.7214 3.48831 20.7667 3.85 20.72H20.15C20.5117 20.7667 20.8774 20.7214 21.2148 20.5858C21.5522 20.4503 21.8485 20.2296 22.0641 19.9472C22.2798 19.6648 22.4045 19.3331 22.4245 18.9892C22.4445 18.6453 22.3591 18.3024 22.18 18L13.71 3.86C13.5317 3.56611 13.2807 3.32312 12.9812 3.15448C12.6817 2.98585 12.3438 2.89725 12 2.89725C11.6562 2.89725 11.3183 2.98585 11.0188 3.15448C10.7193 3.32312 10.4683 3.56611 10.29 3.86V3.86Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="stat-content">
                <div class="stat-number">{{ previewData.warnings.length }}</div>
                <div class="stat-label">Warnings</div>
              </div>
            </div>
          </div>

          <!-- Forecast Periods -->
          <div v-if="previewData.forecast_periods.length > 0" class="periods-section">
            <h3 class="section-title">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" class="section-icon">
                <path d="M6.66667 1.66667V5M13.3333 1.66667V5M2.5 8.33333H17.5M4.16667 3.33333H15.8333C16.7538 3.33333 17.5 4.07952 17.5 5V16.6667C17.5 17.5871 16.7538 18.3333 15.8333 18.3333H4.16667C3.24619 18.3333 2.5 17.5871 2.5 16.6667V5C2.5 4.07952 3.24619 3.33333 4.16667 3.33333Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Forecast Periods
            </h3>
            <div class="periods-grid">
              <span 
                v-for="period in previewData.forecast_periods" 
                :key="period"
                class="period-tag"
              >
                {{ formatPeriod(period) }}
              </span>
            </div>
          </div>

          <!-- Warnings -->
          <div v-if="previewData.warnings.length > 0" class="alerts-section warnings-section">
            <h3 class="alert-title warning">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.19-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" fill="currentColor"/>
              </svg>
              Warnings
            </h3>
            <div class="alert-content warning">
              <ul class="alert-list">
                <li v-for="(warning, index) in previewData.warnings" :key="index" class="alert-item">
                  {{ warning }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Errors -->
          <div v-if="previewData.errors.length > 0" class="alerts-section errors-section">
            <h3 class="alert-title error">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18Z" fill="currentColor"/>
                <path d="M13 7L7 13M7 7L13 13" stroke="white" stroke-width="2" stroke-linecap="round"/>
              </svg>
              Errors
            </h3>
            <div class="alert-content error">
              <ul class="alert-list">
                <li v-for="(error, index) in previewData.errors" :key="index" class="alert-item">
                  {{ error }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="action-buttons">
            <button @click="resetForm" class="btn-secondary">
              Cancel
            </button>
            <button
              v-if="previewData.summary.valid_items > 0"
              @click="saveForecasts"
              :disabled="saving"
              class="btn-primary"
              :class="{ 'loading': saving }"
            >
              <div class="btn-content">
                <svg v-if="saving" class="loading-spinner" viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
                  <path fill="currentColor" opacity="0.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
                <span>{{ saving ? 'Saving...' : 'Save Forecasts' }}</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Items Table -->
        <div v-if="previewData.items.length > 0" class="table-section">
          <div class="table-header">
            <h3 class="table-title">Extracted Items & Forecasts</h3>
          </div>
          
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Item Code</th>
                  <th>Description</th>
                  <th>UOM</th>
                  <th>Forecasts</th>
                  <th>Total Qty</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in previewData.items" :key="item.item_id">
                  <td>
                    <div class="item-code">{{ item.item_code }}</div>
                  </td>
                  <td>
                    <div class="item-description">
                      <div class="item-name">{{ item.item_name }}</div>
                      <div class="item-desc">{{ item.description }}</div>
                    </div>
                  </td>
                  <td>
                    <span class="item-uom">{{ item.uom }}</span>
                  </td>
                  <td>
                    <div class="forecasts-list">
                      <div v-for="forecast in item.forecasts" :key="forecast.period" class="forecast-item">
                        <span class="forecast-period">{{ formatPeriod(forecast.period) }}:</span>
                        <span class="forecast-quantity">{{ formatNumber(forecast.quantity) }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="total-quantity">
                      {{ formatNumber(item.forecasts.reduce((sum, f) => sum + f.quantity, 0)) }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Error Message Fallback -->
      <div v-if="errorMessage" class="error-message">
        <div class="error-content">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="error-icon">
            <path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22Z" fill="currentColor"/>
            <path d="M15 9L9 15M9 9L15 15" stroke="white" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <div class="error-text">
            <h3 class="error-title">Error Occurred</h3>
            <p class="error-description">{{ errorMessage }}</p>
          </div>
          <button @click="errorMessage = ''" class="error-close">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Success Message -->
      <div v-if="successMessage" class="success-message">
        <div class="success-content">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="success-icon">
            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill="currentColor"/>
            <path d="M9 12L11 14L15 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <div class="success-text">
            <h3 class="success-title">Success!</h3>
            <p class="success-description">{{ successMessage }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AIExcelForecastImport',
  data() {
    return {
      customers: [],
      selectedFile: null,
      previewData: null,
      loading: false,
      saving: false,
      successMessage: '',
      errorMessage: '',
      resetTimer: null,
      form: {
        customer_id: '',
        forecast_issue_date: new Date().toISOString().slice(0, 10)
      }
    }
  },
  created() {
    try {
      // Initialize default form values safely
      if (!this.form.forecast_issue_date) {
        this.form.forecast_issue_date = new Date().toISOString().slice(0, 10)
      }
    } catch (error) {
      console.error('Error in created hook:', error)
    }
  },
  mounted() {
    try {
      this.loadCustomers()
    } catch (error) {
      console.error('Error in mounted hook:', error)
      try {
        if (this.$toast && typeof this.$toast.error === 'function') {
          this.$toast.error('Failed to initialize component. Please refresh the page.')
        }
      } catch (toastError) {
        console.error('Toast error in mounted:', toastError)
      }
    }
  },
  beforeUnmount() {
    try {
      // Clean up any timers or subscriptions
      if (this.resetTimer) {
        clearTimeout(this.resetTimer)
      }
    } catch (error) {
      console.error('Error in beforeUnmount hook:', error)
    }
  },
  errorCaptured(err, vm, info) {
    console.error('Component error captured:', err, 'VM:', vm, 'Info:', info)
    try {
      if (this.$toast && typeof this.$toast.error === 'function') {
        this.$toast.error('An unexpected error occurred. Please refresh the page.')
      }
    } catch (toastError) {
      console.error('Toast error in errorCaptured:', toastError)
    }
    return false
  },
  methods: {
    
    async loadCustomers() {
      try {
        const response = await axios.get('/customers')
        this.customers = response.data.data || response.data
      } catch (error) {
        console.error('Error loading customers:', error)
        
        // Handle different types of errors
        let errorMessage = 'Failed to load customers'
        
        try {
          if (error.response) {
            // Server responded with error status
            const responseData = error.response.data
            
            if (responseData && responseData.message) {
              errorMessage = responseData.message
            } else if (responseData && responseData.error) {
              errorMessage = responseData.error
            } else if (error.response.status === 401) {
              errorMessage = 'Authentication required. Please login again.'
            } else if (error.response.status === 403) {
              errorMessage = 'Access denied. You do not have permission to view customers.'
            } else if (error.response.status >= 500) {
              errorMessage = 'Server error. Please try again later.'
            }
          } else if (error.request) {
            // Network error
            errorMessage = 'Network error. Please check your connection and try again.'
          } else {
            // Other errors
            errorMessage = error.message || 'An unexpected error occurred while loading customers'
          }
        } catch (parseError) {
          console.error('Error parsing error response:', parseError)
          errorMessage = 'Failed to load customers'
        }
        
        // Safe toast call
        try {
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error(errorMessage)
          } else {
            console.error('Toast not available:', errorMessage)
            // Fallback: show in UI error state
            this.errorMessage = errorMessage
          }
        } catch (toastError) {
          console.error('Toast error:', toastError, 'Message:', errorMessage)
          // Fallback: show in UI error state
          this.errorMessage = errorMessage
        }
      }
    },

    handleFileSelect(event) {
      try {
        const file = event.target.files[0]
        if (file) {
          // Validate file type
          const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']
          if (!allowedTypes.includes(file.type)) {
            const errorMsg = 'Please select a valid Excel file (.xlsx or .xls)'
            if (this.$toast && typeof this.$toast.error === 'function') {
              this.$toast.error(errorMsg)
            } else {
              this.errorMessage = errorMsg
            }
            return
          }

          // Validate file size (10MB max)
          if (file.size > 10 * 1024 * 1024) {
            const errorMsg = 'File size must be less than 10MB'
            if (this.$toast && typeof this.$toast.error === 'function') {
              this.$toast.error(errorMsg)
            } else {
              this.errorMessage = errorMsg
            }
            return
          }

          this.selectedFile = file
          this.errorMessage = '' // Clear any previous errors
        }
      } catch (error) {
        console.error('Error handling file select:', error)
        const errorMsg = 'Error selecting file. Please try again.'
        try {
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error(errorMsg)
          } else {
            this.errorMessage = errorMsg
          }
        } catch (toastError) {
          console.error('Toast error in handleFileSelect:', toastError)
          this.errorMessage = errorMsg
        }
      }
    },

    removeFile() {
      try {
        this.selectedFile = null
        if (this.$refs.fileInput) {
          this.$refs.fileInput.value = ''
        }
      } catch (error) {
        console.error('Error removing file:', error)
        try {
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error('Error removing file. Please refresh the page.')
          }
        } catch (toastError) {
          console.error('Toast error in removeFile:', toastError)
        }
      }
    },

    async processFile() {
      try {
        if (!this.selectedFile || !this.form.customer_id) {
          const errorMsg = 'Please select a file and customer'
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error(errorMsg)
          } else {
            this.errorMessage = errorMsg
          }
          return
        }

        this.loading = true
        this.successMessage = ''
        this.errorMessage = '' // Clear any previous errors

        try {
          const formData = new FormData()
          formData.append('excel_file', this.selectedFile)
          formData.append('customer_id', this.form.customer_id)
          formData.append('forecast_issue_date', this.form.forecast_issue_date)
          formData.append('preview_only', 'true')

          const response = await axios.post('/ai-excel-forecast/process', formData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            }
          })

          if (response.data && response.data.success) {
            this.previewData = response.data.data
            if (this.$toast && typeof this.$toast.success === 'function') {
              this.$toast.success('File processed successfully by AI')
            }
          } else {
            // Handle API response errors
            const errorMessage = (response.data && (response.data.message || response.data.error)) || 'Failed to process file'
            if (this.$toast && typeof this.$toast.error === 'function') {
              this.$toast.error(errorMessage)
            }
          }
        } catch (error) {
          console.error('Error processing file:', error)
          
          // Handle different types of errors
          let errorMessage = 'Failed to process file with AI'
          
          try {
            if (error.response) {
              // Server responded with error status
              const responseData = error.response.data
              console.log('Response data:', responseData) // Debug log
              
              if (responseData && responseData.message) {
                errorMessage = responseData.message
              } else if (responseData && responseData.error) {
                // Parse Groq API error if needed
                let groqError = responseData.error
                if (typeof groqError === 'string' && groqError.includes('Groq API request failed:')) {
                  try {
                    // Extract the actual error message from Groq API
                    const groqErrorMatch = groqError.match(/"message":"([^"]+)"/);
                    if (groqErrorMatch && groqErrorMatch[1]) {
                      errorMessage = `AI Service Error: ${groqErrorMatch[1]}`
                    } else {
                      errorMessage = 'AI processing failed - Request too large or rate limit exceeded'
                    }
                  } catch (parseError) {
                    errorMessage = 'AI processing failed - Service temporarily unavailable'
                  }
                } else {
                  errorMessage = groqError
                }
              } else if (error.response.status === 413) {
                errorMessage = 'File too large. Please select a smaller file.'
              } else if (error.response.status === 429) {
                errorMessage = 'Rate limit exceeded. Please try again later.'
              } else if (error.response.status >= 500) {
                errorMessage = 'Server error. Please try again later.'
              }
            } else if (error.request) {
              // Network error
              errorMessage = 'Network error. Please check your connection and try again.'
            } else {
              // Other errors
              errorMessage = error.message || 'An unexpected error occurred'
            }
          } catch (parseError) {
            console.error('Error parsing error response:', parseError)
            errorMessage = 'Failed to process file with AI'
          }
          
          console.log('Final error message:', errorMessage) // Debug log
          
          try {
            if (this.$toast && typeof this.$toast.error === 'function') {
              this.$toast.error(errorMessage)
            } else {
              console.error('Toast not available:', errorMessage)
              // Fallback: show in UI error state
              this.errorMessage = errorMessage
            }
          } catch (toastError) {
            console.error('Toast error:', toastError, 'Message:', errorMessage)
            // Fallback: show in UI error state
            this.errorMessage = errorMessage
          }
        }
      } catch (outerError) {
        console.error('Outer error in processFile:', outerError)
        try {
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error('An unexpected error occurred. Please try again.')
          }
        } catch (toastError) {
          console.error('Toast error in outer catch:', toastError)
        }
      } finally {
        this.loading = false
      }
    },

    async saveForecasts() {
      try {
        if (!this.previewData || !this.previewData.items || !this.previewData.items.length) {
          const errorMsg = 'No valid forecast data to save'
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error(errorMsg)
          } else {
            this.errorMessage = errorMsg
          }
          return
        }

        this.saving = true
        this.errorMessage = '' // Clear any previous errors

        try {
          const response = await axios.post('/ai-excel-forecast/save', {
            customer_id: this.form.customer_id,
            forecast_issue_date: this.form.forecast_issue_date,
            items: this.previewData.items
          })

          if (response.data && response.data.success) {
            const data = response.data.data || {}
            this.successMessage = `Successfully saved ${data.total_saved || 0} forecasts (${data.new_forecasts || 0} new, ${data.updated_forecasts || 0} updated)`
            
            if (this.$toast && typeof this.$toast.success === 'function') {
              this.$toast.success('Forecasts saved successfully!')
            }
            
            // Reset form after delay
            this.resetTimer = setTimeout(() => {
              try {
                this.resetForm()
              } catch (error) {
                console.error('Error in delayed reset:', error)
              }
            }, 3000)
          } else {
            // Handle API response errors
            const errorMessage = (response.data && (response.data.message || response.data.error)) || 'Failed to save forecasts'
            if (this.$toast && typeof this.$toast.error === 'function') {
              this.$toast.error(errorMessage)
            }
          }
        } catch (error) {
          console.error('Error saving forecasts:', error)
          
          // Handle different types of errors
          let errorMessage = 'Failed to save forecasts'
          
          try {
            if (error.response) {
              // Server responded with error status
              const responseData = error.response.data
              console.log('Save response data:', responseData) // Debug log
              
              if (responseData && responseData.message) {
                errorMessage = responseData.message
              } else if (responseData && responseData.error) {
                // Parse any nested error messages
                let errorDetail = responseData.error
                if (typeof errorDetail === 'string' && errorDetail.includes('API request failed:')) {
                  try {
                    const errorMatch = errorDetail.match(/"message":"([^"]+)"/);
                    if (errorMatch && errorMatch[1]) {
                      errorMessage = `Save Error: ${errorMatch[1]}`
                    } else {
                      errorMessage = 'Failed to save forecasts - Service error'
                    }
                  } catch (parseError) {
                    errorMessage = 'Failed to save forecasts - Service temporarily unavailable'
                  }
                } else {
                  errorMessage = errorDetail
                }
              } else if (error.response.status === 413) {
                errorMessage = 'Data too large to save. Please reduce the number of forecasts.'
              } else if (error.response.status === 429) {
                errorMessage = 'Rate limit exceeded. Please try again later.'
              } else if (error.response.status >= 500) {
                errorMessage = 'Server error. Please try again later.'
              } else if (error.response.status === 422) {
                errorMessage = 'Invalid data format. Please check your forecasts and try again.'
              }
            } else if (error.request) {
              // Network error
              errorMessage = 'Network error. Please check your connection and try again.'
            } else {
              // Other errors
              errorMessage = error.message || 'An unexpected error occurred while saving'
            }
          } catch (parseError) {
            console.error('Error parsing save error response:', parseError)
            errorMessage = 'Failed to save forecasts'
          }
          
          console.log('Final save error message:', errorMessage) // Debug log
          
          try {
            if (this.$toast && typeof this.$toast.error === 'function') {
              this.$toast.error(errorMessage)
            } else {
              console.error('Toast not available:', errorMessage)
              // Fallback: show in UI error state
              this.errorMessage = errorMessage
            }
          } catch (toastError) {
            console.error('Toast error:', toastError, 'Message:', errorMessage)
            // Fallback: show in UI error state
            this.errorMessage = errorMessage
          }
        }
      } catch (outerError) {
        console.error('Outer error in saveForecasts:', outerError)
        try {
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error('An unexpected error occurred while saving. Please try again.')
          }
        } catch (toastError) {
          console.error('Toast error in outer catch:', toastError)
        }
      } finally {
        this.saving = false
      }
    },

    resetForm() {
      try {
        // Clear any existing timers
        if (this.resetTimer) {
          clearTimeout(this.resetTimer)
          this.resetTimer = null
        }
        
        this.selectedFile = null
        this.previewData = null
        this.successMessage = ''
        this.errorMessage = ''
        this.form.customer_id = ''
        this.form.forecast_issue_date = new Date().toISOString().slice(0, 10)
        if (this.$refs.fileInput) {
          this.$refs.fileInput.value = ''
        }
      } catch (error) {
        console.error('Error resetting form:', error)
        try {
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error('Error resetting form. Please refresh the page.')
          }
        } catch (toastError) {
          console.error('Toast error in resetForm:', toastError)
        }
      }
    },

    formatFileSize(bytes) {
      try {
        if (bytes === 0 || bytes === null || bytes === undefined) return '0 Bytes'
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB', 'GB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
      } catch (error) {
        console.error('Error formatting file size:', error)
        return 'Unknown size'
      }
    },

    formatPeriod(period) {
      try {
        if (!period) return 'Unknown period'
        return new Date(period).toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'short' 
        })
      } catch (e) {
        console.error('Error formatting period:', e)
        return period || 'Invalid date'
      }
    },

    formatNumber(number) {
      try {
        if (number === null || number === undefined || isNaN(number)) return '0'
        return new Intl.NumberFormat().format(number)
      } catch (error) {
        console.error('Error formatting number:', error)
        return String(number || '0')
      }
    },

    safeCall(fn) {
      try {
        if (typeof fn === 'function') {
          return fn()
        }
      } catch (error) {
        console.error('Error in safeCall:', error)
        try {
          if (this.$toast && typeof this.$toast.error === 'function') {
            this.$toast.error('An error occurred. Please try again.')
          }
        } catch (toastError) {
          console.error('Toast error in safeCall:', toastError)
        }
      }
    }
  }
}
</script>

<style scoped>
/* Container & Layout */
.ai-excel-forecast-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem 0;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.main-wrapper {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Header Section */
.header-section {
  position: relative;
  text-align: center;
  margin-bottom: 3rem;
  padding: 2rem 0;
}

.header-content {
  position: relative;
  z-index: 2;
}

.main-title {
  font-size: 3rem;
  font-weight: 800;
  color: #ffffff;
  margin-bottom: 1rem;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  letter-spacing: -0.02em;
}

.main-subtitle {
  font-size: 1.25rem;
  color: rgba(255, 255, 255, 0.9);
  max-width: 600px;
  margin: 0 auto;
  line-height: 1.6;
}

.header-decoration {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
  z-index: 1;
}

/* Upload Section */
.upload-section {
  margin-bottom: 3rem;
}

.upload-card {
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
  padding: 2.5rem;
  border: 1px solid rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(10px);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.upload-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.15);
}

.card-header {
  margin-bottom: 2.5rem;
  text-align: center;
}

.card-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 0.5rem;
}

.title-underline {
  width: 60px;
  height: 4px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  margin: 0 auto;
  border-radius: 2px;
}

/* Form Styles */
.upload-form {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  display: flex;
  align-items: center;
  margin-bottom: 0.75rem;
  font-weight: 600;
  color: #374151;
}

.label-text {
  font-size: 0.95rem;
}

.label-required {
  color: #ef4444;
  margin-left: 0.25rem;
}

.select-wrapper {
  position: relative;
}

.form-select, .form-input {
  width: 100%;
  padding: 1rem 1.25rem;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  font-size: 1rem;
  transition: all 0.3s ease;
  background: #ffffff;
}

.form-select:focus, .form-input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.select-arrow {
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  pointer-events: none;
}

/* File Upload */
.file-upload-area {
  border: 2px dashed #d1d5db;
  border-radius: 16px;
  padding: 2rem;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
  background: #fafafa;
  position: relative;
  overflow: hidden;
}

.file-upload-area:hover {
  border-color: #667eea;
  background: #f8faff;
}

.file-upload-area.has-file {
  border-color: #10b981;
  background: #f0fdf4;
}

.file-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.upload-icon {
  color: #9ca3af;
  transition: color 0.3s ease;
}

.file-upload-area:hover .upload-icon {
  color: #667eea;
}

.upload-content {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.upload-text {
  font-size: 1rem;
  color: #374151;
  margin: 0;
}

.upload-button {
  color: #667eea;
  font-weight: 600;
  text-decoration: none;
  transition: color 0.3s ease;
}

.upload-button:hover {
  color: #5a67d8;
  text-decoration: underline;
}

.upload-hint {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.file-selected {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #10b981;
}

.file-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.file-icon {
  flex-shrink: 0;
}

.file-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.file-name {
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.file-size {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.remove-file-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  background: #fee2e2;
  border: none;
  border-radius: 50%;
  color: #dc2626;
  cursor: pointer;
  transition: all 0.3s ease;
}

.remove-file-btn:hover {
  background: #fecaca;
  transform: scale(1.1);
}

/* Buttons */
.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}

.submit-btn, .btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 1rem 2rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #ffffff;
  border: none;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
  min-width: 180px;
  position: relative;
}

.submit-btn:hover, .btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
}

.submit-btn:disabled, .btn-primary:disabled {
  background: #9ca3af;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-secondary {
  padding: 1rem 2rem;
  background: #ffffff;
  color: #374151;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-right: 1rem;
}

.btn-secondary:hover {
  background: #f9fafb;
  border-color: #d1d5db;
}

.btn-content {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.loading-spinner {
  width: 20px;
  height: 20px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Preview Section */
.preview-section {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.summary-card {
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
  padding: 2.5rem;
  border: 1px solid rgba(255, 255, 255, 0.8);
}

.close-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  background: #fee2e2;
  border: none;
  border-radius: 50%;
  color: #dc2626;
  cursor: pointer;
  transition: all 0.3s ease;
}

.close-btn:hover {
  background: #fecaca;
  transform: scale(1.1);
}

/* Supplier Info */
.supplier-info {
  background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
  padding: 1.5rem;
  border-radius: 16px;
  margin-bottom: 2rem;
  border: 1px solid #bfdbfe;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e40af;
  margin-bottom: 1rem;
}

.section-icon {
  color: #3b82f6;
}

.supplier-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.supplier-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.supplier-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e40af;
}

.supplier-value {
  font-size: 1rem;
  color: #1f2937;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: #ffffff;
  padding: 1.5rem;
  border-radius: 16px;
  border: 2px solid transparent;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
}

.stat-card.valid {
  border-color: #10b981;
  background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
}

.stat-card.total {
  border-color: #3b82f6;
  background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
}

.stat-card.periods {
  border-color: #8b5cf6;
  background: linear-gradient(135deg, #f5f3ff 0%, #faf5ff 100%);
}

.stat-card.warnings {
  border-color: #f59e0b;
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}

.stat-icon {
  flex-shrink: 0;
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-card.valid .stat-icon {
  background: #10b981;
  color: #ffffff;
}

.stat-card.total .stat-icon {
  background: #3b82f6;
  color: #ffffff;
}

.stat-card.periods .stat-icon {
  background: #8b5cf6;
  color: #ffffff;
}

.stat-card.warnings .stat-icon {
  background: #f59e0b;
  color: #ffffff;
}

.stat-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.stat-number {
  font-size: 2rem;
  font-weight: 800;
  color: #1f2937;
  line-height: 1;
}

.stat-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Periods Section */
.periods-section {
  margin-bottom: 2rem;
}

.periods-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.period-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
  color: #1e40af;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
  border: 1px solid #bfdbfe;
  transition: all 0.3s ease;
}

.period-tag:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(30, 64, 175, 0.2);
}

/* Alerts */
.alerts-section {
  margin-bottom: 2rem;
}

.alert-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.alert-title.warning {
  color: #f59e0b;
}

.alert-title.error {
  color: #dc2626;
}

.alert-content {
  padding: 1.5rem;
  border-radius: 12px;
  border: 1px solid;
}

.alert-content.warning {
  background: #fffbeb;
  border-color: #fed7aa;
}

.alert-content.error {
  background: #fef2f2;
  border-color: #fecaca;
}

.alert-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.alert-item {
  position: relative;
  padding-left: 1rem;
  font-size: 0.875rem;
  line-height: 1.5;
}

.alert-item::before {
  content: '•';
  position: absolute;
  left: 0;
  font-weight: bold;
}

.warnings-section .alert-item {
  color: #92400e;
}

.warnings-section .alert-item::before {
  color: #f59e0b;
}

.errors-section .alert-item {
  color: #991b1b;
}

.errors-section .alert-item::before {
  color: #dc2626;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e5e7eb;
}

/* Table Section */
.table-section {
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.8);
}

.table-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 1.5rem 2rem;
}

.table-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #ffffff;
  margin: 0;
}

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  background: #f8fafc;
  padding: 1rem 1.5rem;
  text-align: left;
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #e5e7eb;
}

.data-table td {
  padding: 1.5rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: top;
}

.data-table tr:hover {
  background: #f8fafc;
}

.item-code {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1f2937;
  font-family: 'Monaco', 'Menlo', monospace;
}

.item-description {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.item-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1f2937;
}

.item-desc {
  font-size: 0.875rem;
  color: #6b7280;
}

.item-uom {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.75rem;
  background: #f3f4f6;
  color: #374151;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 500;
}

.forecasts-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.forecast-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.forecast-period {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.forecast-quantity {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1f2937;
  font-family: 'Monaco', 'Menlo', monospace;
}

.total-quantity {
  font-size: 1rem;
  font-weight: 700;
  color: #1f2937;
  text-align: right;
  font-family: 'Monaco', 'Menlo', monospace;
}

/* Error Message Fallback */
.error-message {
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  border: 1px solid #ef4444;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(239, 68, 68, 0.1);
}

.error-content {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.error-icon {
  flex-shrink: 0;
  color: #ef4444;
}

.error-text {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1;
}

.error-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #991b1b;
  margin: 0;
}

.error-description {
  font-size: 0.95rem;
  color: #dc2626;
  margin: 0;
  line-height: 1.5;
}

.error-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  background: #fee2e2;
  border: none;
  border-radius: 50%;
  color: #dc2626;
  cursor: pointer;
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.error-close:hover {
  background: #fecaca;
  transform: scale(1.1);
}

/* Success Message */
.success-message {
  background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
  border: 1px solid #10b981;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(16, 185, 129, 0.1);
}

.success-content {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.success-icon {
  flex-shrink: 0;
  color: #10b981;
}

.success-text {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.success-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #065f46;
  margin: 0;
}

.success-description {
  font-size: 0.95rem;
  color: #047857;
  margin: 0;
  line-height: 1.5;
}

/* Responsive Design */
@media (max-width: 768px) {
  .main-wrapper {
    padding: 0 1rem;
  }

  .main-title {
    font-size: 2rem;
  }

  .main-subtitle {
    font-size: 1.125rem;
  }

  .upload-card, .summary-card {
    padding: 1.5rem;
  }

  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
  }

  .stat-card {
    padding: 1rem;
  }

  .stat-number {
    font-size: 1.5rem;
  }

  .supplier-grid {
    grid-template-columns: 1fr;
  }

  .periods-grid {
    gap: 0.5rem;
  }

  .action-buttons {
    flex-direction: column;
  }

  .data-table {
    font-size: 0.875rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.75rem;
  }
}

@media (max-width: 480px) {
  .main-title {
    font-size: 1.75rem;
  }

  .main-subtitle {
    font-size: 1rem;
  }

  .upload-card, .summary-card {
    padding: 1rem;
  }

  .file-upload-area {
    padding: 1.5rem;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .table-container {
    font-size: 0.75rem;
  }
}

/* Print Styles */
@media print {
  .ai-excel-forecast-container {
    background: #ffffff !important;
  }

  .upload-section {
    display: none;
  }

  .action-buttons {
    display: none;
  }

  .close-btn {
    display: none;
  }
}
</style>