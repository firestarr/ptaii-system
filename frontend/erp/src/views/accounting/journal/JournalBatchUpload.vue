<!-- src/views/accounting/JournalBatchUpload.vue -->
<template>
  <div class="batch-upload-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <router-link to="/accounting/journal-entries" class="back-button">
            <i class="fas fa-arrow-left"></i>
          </router-link>
          <div>
            <h1 class="page-title">
              <i class="fas fa-upload"></i>
              Journal Batch Upload
            </h1>
            <p class="page-subtitle">Upload multiple journal entries from Excel or CSV files</p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="downloadTemplate" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Download Template
          </button>
          <button @click="showHelpModal = true" class="btn btn-secondary">
            <i class="fas fa-question-circle"></i>
            Help
          </button>
        </div>
      </div>
    </div>

    <!-- Upload Steps -->
    <div class="upload-steps">
      <div class="step" :class="{ active: currentStep >= 1, completed: currentStep > 1 }">
        <div class="step-number">1</div>
        <div class="step-content">
          <h3>Upload File</h3>
          <p>Select and upload your Excel or CSV file</p>
        </div>
      </div>
      <div class="step-separator"></div>
      <div class="step" :class="{ active: currentStep >= 2, completed: currentStep > 2 }">
        <div class="step-number">2</div>
        <div class="step-content">
          <h3>Validate Data</h3>
          <p>Review and validate the uploaded data</p>
        </div>
      </div>
      <div class="step-separator"></div>
      <div class="step" :class="{ active: currentStep >= 3, completed: currentStep > 3 }">
        <div class="step-number">3</div>
        <div class="step-content">
          <h3>Process & Import</h3>
          <p>Process and import journal entries</p>
        </div>
      </div>
    </div>

    <!-- Step 1: File Upload -->
    <div v-if="currentStep === 1" class="upload-section">
      <div class="upload-card">
        <div class="upload-header">
          <h3><i class="fas fa-cloud-upload-alt"></i> Upload Journal Entries File</h3>
          <p>Upload an Excel (.xlsx) or CSV file containing journal entry data</p>
        </div>
        
        <div class="upload-area" 
             :class="{ 'drag-over': isDragOver, 'has-file': selectedFile }"
             @dragover.prevent="isDragOver = true"
             @dragleave.prevent="isDragOver = false"
             @drop.prevent="handleFileDrop">
          
          <div v-if="!selectedFile" class="upload-content">
            <div class="upload-icon">
              <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h4>Drag & drop your file here</h4>
            <p>or click to browse files</p>
            <button @click="$refs.fileInput.click()" class="btn btn-primary">
              <i class="fas fa-folder-open"></i>
              Browse Files
            </button>
            <div class="file-types">
              <span class="file-type">Excel (.xlsx)</span>
              <span class="file-type">CSV (.csv)</span>
            </div>
          </div>
          
          <div v-else class="selected-file">
            <div class="file-icon">
              <i :class="getFileIcon(selectedFile.name)"></i>
            </div>
            <div class="file-info">
              <h4>{{ selectedFile.name }}</h4>
              <p>{{ formatFileSize(selectedFile.size) }} • {{ selectedFile.type || 'Unknown type' }}</p>
            </div>
            <button @click="removeFile" class="btn-remove">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <input 
            ref="fileInput" 
            type="file" 
            accept=".xlsx,.xls,.csv" 
            @change="handleFileSelect"
            style="display: none"
          />
        </div>

        <div class="upload-options">
          <div class="option-group">
            <label class="checkbox-wrapper">
              <input type="checkbox" v-model="uploadOptions.skipFirstRow" />
              <span class="checkmark"></span>
              <span class="label">Skip first row (header)</span>
            </label>
          </div>
          
          <div class="option-group">
            <label class="checkbox-wrapper">
              <input type="checkbox" v-model="uploadOptions.validateOnly" />
              <span class="checkmark"></span>
              <span class="label">Validate only (don't save)</span>
            </label>
          </div>
          
          <div class="option-group">
            <label for="delimiter">CSV Delimiter:</label>
            <select id="delimiter" v-model="uploadOptions.delimiter" class="form-select">
              <option value=",">Comma (,)</option>
              <option value=";">Semicolon (;)</option>
              <option value="\t">Tab</option>
              <option value="|">Pipe (|)</option>
            </select>
          </div>
        </div>

        <div class="upload-actions">
          <button 
            @click="processFile" 
            class="btn btn-primary" 
            :disabled="!selectedFile || processing"
          >
            <i class="fas fa-cog" :class="{ 'fa-spin': processing }"></i>
            {{ processing ? 'Processing...' : 'Process File' }}
          </button>
        </div>
      </div>

      <!-- File Requirements -->
      <div class="requirements-card">
        <h3><i class="fas fa-info-circle"></i> File Requirements</h3>
        <div class="requirements-grid">
          <div class="requirement-item">
            <h4>Required Columns</h4>
            <ul>
              <li>journal_number</li>
              <li>entry_date</li>
              <li>account_code</li>
              <li>debit_amount</li>
              <li>credit_amount</li>
              <li>description</li>
            </ul>
          </div>
          <div class="requirement-item">
            <h4>Optional Columns</h4>
            <ul>
              <li>reference_type</li>
              <li>reference_id</li>
              <li>line_description</li>
              <li>period_name</li>
            </ul>
          </div>
          <div class="requirement-item">
            <h4>Data Format</h4>
            <ul>
              <li>Dates: YYYY-MM-DD</li>
              <li>Numbers: 0.00 format</li>
              <li>Text: UTF-8 encoding</li>
              <li>Max file size: 10MB</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 2: Data Validation -->
    <div v-if="currentStep === 2" class="validation-section">
      <div class="validation-summary">
        <div class="summary-cards">
          <div class="summary-card success">
            <div class="card-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
              <h3>{{ validationResults.valid }}</h3>
              <p>Valid Entries</p>
            </div>
          </div>
          <div class="summary-card error">
            <div class="card-icon">
              <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="card-content">
              <h3>{{ validationResults.invalid }}</h3>
              <p>Invalid Entries</p>
            </div>
          </div>
          <div class="summary-card warning">
            <div class="card-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="card-content">
              <h3>{{ validationResults.warnings }}</h3>
              <p>Warnings</p>
            </div>
          </div>
          <div class="summary-card info">
            <div class="card-icon">
              <i class="fas fa-info-circle"></i>
            </div>
            <div class="card-content">
              <h3>{{ uploadedData.length }}</h3>
              <p>Total Rows</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Validation Errors -->
      <div v-if="validationErrors.length > 0" class="errors-section">
        <div class="section-header">
          <h3><i class="fas fa-exclamation-circle"></i> Validation Errors</h3>
          <button @click="showAllErrors = !showAllErrors" class="btn btn-ghost">
            {{ showAllErrors ? 'Hide' : 'Show All' }}
          </button>
        </div>
        <div class="errors-list" :class="{ 'collapsed': !showAllErrors }">
          <div 
            v-for="(error, index) in (showAllErrors ? validationErrors : validationErrors.slice(0, 5))" 
            :key="index"
            class="error-item"
          >
            <div class="error-icon">
              <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="error-content">
              <h4>Row {{ error.row }}: {{ error.message }}</h4>
              <p v-if="error.field">Field: {{ error.field }}</p>
              <div v-if="error.data" class="error-data">
                {{ JSON.stringify(error.data) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Data Preview -->
      <div class="preview-section">
        <div class="section-header">
          <h3><i class="fas fa-table"></i> Data Preview</h3>
          <div class="preview-filters">
            <select v-model="previewFilter" class="form-select">
              <option value="all">All Rows</option>
              <option value="valid">Valid Only</option>
              <option value="invalid">Invalid Only</option>
              <option value="warnings">With Warnings</option>
            </select>
          </div>
        </div>
        
        <div class="preview-table-container">
          <table class="preview-table">
            <thead>
              <tr>
                <th>Status</th>
                <th>Row</th>
                <th>Journal Number</th>
                <th>Entry Date</th>
                <th>Account Code</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Description</th>
                <th>Issues</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(row, index) in filteredPreviewData" 
                :key="index"
                class="preview-row"
                :class="{ 
                  'valid': row.isValid && !row.hasWarnings,
                  'invalid': !row.isValid,
                  'warning': row.hasWarnings
                }"
              >
                <td class="status-cell">
                  <span class="status-indicator" :class="getRowStatus(row)">
                    <i :class="getRowStatusIcon(row)"></i>
                  </span>
                </td>
                <td>{{ row.rowNumber }}</td>
                <td>{{ row.journal_number }}</td>
                <td>{{ row.entry_date }}</td>
                <td>{{ row.account_code }}</td>
                <td class="amount">{{ formatCurrency(row.debit_amount) }}</td>
                <td class="amount">{{ formatCurrency(row.credit_amount) }}</td>
                <td class="description">{{ truncateText(row.description, 30) }}</td>
                <td class="issues">
                  <div v-if="row.errors?.length > 0" class="issue-badges">
                    <span 
                      v-for="error in row.errors" 
                      :key="error"
                      class="issue-badge error"
                      :title="error"
                    >
                      <i class="fas fa-exclamation-circle"></i>
                    </span>
                  </div>
                  <div v-if="row.warnings?.length > 0" class="issue-badges">
                    <span 
                      v-for="warning in row.warnings" 
                      :key="warning"
                      class="issue-badge warning"
                      :title="warning"
                    >
                      <i class="fas fa-exclamation-triangle"></i>
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Validation Actions -->
      <div class="validation-actions">
        <button @click="goBack" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i>
          Back to Upload
        </button>
        <button 
          @click="downloadErrorReport" 
          class="btn btn-outline"
          v-if="validationErrors.length > 0"
        >
          <i class="fas fa-download"></i>
          Download Error Report
        </button>
        <button 
          @click="proceedToImport" 
          class="btn btn-primary"
          :disabled="validationResults.valid === 0"
        >
          <i class="fas fa-check"></i>
          Proceed with {{ validationResults.valid }} Valid Entries
        </button>
      </div>
    </div>

    <!-- Step 3: Import Process -->
    <div v-if="currentStep === 3" class="import-section">
      <div class="import-progress">
        <div class="progress-header">
          <h3><i class="fas fa-cogs"></i> Processing Journal Entries</h3>
          <p>Creating journal entries from validated data...</p>
        </div>
        
        <div class="progress-bar-container">
          <div class="progress-bar">
            <div 
              class="progress-fill" 
              :style="{ width: `${importProgress.percentage}%` }"
            ></div>
          </div>
          <div class="progress-text">
            {{ importProgress.current }} / {{ importProgress.total }} 
            ({{ importProgress.percentage }}%)
          </div>
        </div>

        <div class="progress-details">
          <div class="detail-item">
            <span class="label">Successfully Created:</span>
            <span class="value success">{{ importResults.success }}</span>
          </div>
          <div class="detail-item">
            <span class="label">Failed:</span>
            <span class="value error">{{ importResults.failed }}</span>
          </div>
          <div class="detail-item">
            <span class="label">Current Status:</span>
            <span class="value">{{ importStatus }}</span>
          </div>
        </div>
      </div>

      <!-- Import Results -->
      <div v-if="importComplete" class="import-results">
        <div class="results-summary">
          <div class="result-card" :class="importResults.success > 0 ? 'success' : ''">
            <div class="card-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
              <h3>{{ importResults.success }}</h3>
              <p>Successfully Imported</p>
            </div>
          </div>
          
          <div class="result-card" :class="importResults.failed > 0 ? 'error' : ''">
            <div class="card-icon">
              <i class="fas fa-times-circle"></i>
            </div>
            <div class="card-content">
              <h3>{{ importResults.failed }}</h3>
              <p>Failed to Import</p>
            </div>
          </div>
        </div>

        <!-- Import Errors -->
        <div v-if="importErrors.length > 0" class="import-errors">
          <h4><i class="fas fa-exclamation-triangle"></i> Import Errors</h4>
          <div class="error-list">
            <div v-for="(error, index) in importErrors" :key="index" class="import-error">
              <span class="error-row">Row {{ error.row }}:</span>
              <span class="error-message">{{ error.message }}</span>
            </div>
          </div>
        </div>

        <!-- Created Entries -->
        <div v-if="createdEntries.length > 0" class="created-entries">
          <h4><i class="fas fa-check"></i> Created Journal Entries</h4>
          <div class="entries-list">
            <router-link 
              v-for="entry in createdEntries" 
              :key="entry.journal_id"
              :to="`/accounting/journal-entries/${entry.journal_id}`"
              class="entry-link"
            >
              <span class="entry-number">{{ entry.journal_number }}</span>
              <span class="entry-date">{{ formatDate(entry.entry_date) }}</span>
              <span class="entry-amount">{{ formatCurrency(entry.total_amount) }}</span>
            </router-link>
          </div>
        </div>

        <!-- Final Actions -->
        <div class="final-actions">
          <button @click="startOver" class="btn btn-outline">
            <i class="fas fa-redo"></i>
            Upload Another File
          </button>
          <router-link to="/accounting/journal-entries" class="btn btn-primary">
            <i class="fas fa-list"></i>
            View All Journal Entries
          </router-link>
        </div>
      </div>
    </div>

    <!-- Help Modal -->
    <div v-if="showHelpModal" class="modal-overlay" @click="showHelpModal = false">
      <div class="modal-content help-modal" @click.stop>
        <div class="modal-header">
          <h3>Batch Upload Help</h3>
          <button @click="showHelpModal = false" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="help-section">
            <h4>File Format Requirements</h4>
            <p>Your file must contain the following columns:</p>
            <ul>
              <li><strong>journal_number</strong> - Unique identifier for each journal entry</li>
              <li><strong>entry_date</strong> - Date in YYYY-MM-DD format</li>
              <li><strong>account_code</strong> - Chart of accounts code</li>
              <li><strong>debit_amount</strong> - Debit amount (0 if credit)</li>
              <li><strong>credit_amount</strong> - Credit amount (0 if debit)</li>
              <li><strong>description</strong> - Journal entry description</li>
            </ul>
          </div>
          
          <div class="help-section">
            <h4>Data Rules</h4>
            <ul>
              <li>Each journal entry must have at least 2 lines</li>
              <li>Total debits must equal total credits for each entry</li>
              <li>Account codes must exist in the chart of accounts</li>
              <li>Amounts must be positive numbers</li>
              <li>Each line must have either debit OR credit amount (not both)</li>
            </ul>
          </div>
          
          <div class="help-section">
            <h4>Example Data</h4>
            <div class="example-table">
              <table>
                <thead>
                  <tr>
                    <th>journal_number</th>
                    <th>entry_date</th>
                    <th>account_code</th>
                    <th>debit_amount</th>
                    <th>credit_amount</th>
                    <th>description</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>JE-001</td>
                    <td>2024-01-15</td>
                    <td>1001</td>
                    <td>1000</td>
                    <td>0</td>
                    <td>Cash deposit</td>
                  </tr>
                  <tr>
                    <td>JE-001</td>
                    <td>2024-01-15</td>
                    <td>4001</td>
                    <td>0</td>
                    <td>1000</td>
                    <td>Revenue recognition</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showHelpModal = false" class="btn btn-primary">
            Got it
          </button>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="processing && currentStep === 1" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner"></div>
        <p>Processing file...</p>
        <div class="loading-details">
          {{ processingStatus }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import * as XLSX from 'xlsx'

export default {
  name: 'JournalBatchUpload',
  data() {
    return {
      currentStep: 1,
      selectedFile: null,
      isDragOver: false,
      processing: false,
      processingStatus: '',
      uploadOptions: {
        skipFirstRow: true,
        validateOnly: false,
        delimiter: ','
      },
      uploadedData: [],
      validationResults: {
        valid: 0,
        invalid: 0,
        warnings: 0
      },
      validationErrors: [],
      showAllErrors: false,
      previewFilter: 'all',
      importProgress: {
        current: 0,
        total: 0,
        percentage: 0
      },
      importResults: {
        success: 0,
        failed: 0
      },
      importErrors: [],
      createdEntries: [],
      importComplete: false,
      importStatus: '',
      showHelpModal: false,
      accounts: []
    }
  },
  computed: {
    filteredPreviewData() {
      let filtered = this.uploadedData
      
      switch (this.previewFilter) {
        case 'valid':
          filtered = this.uploadedData.filter(row => row.isValid && !row.hasWarnings)
          break
        case 'invalid':
          filtered = this.uploadedData.filter(row => !row.isValid)
          break
        case 'warnings':
          filtered = this.uploadedData.filter(row => row.hasWarnings)
          break
        default:
          filtered = this.uploadedData
      }
      
      return filtered.slice(0, 100) // Limit preview to 100 rows
    }
  },
  async mounted() {
    await this.loadAccounts()
  },
  methods: {
    async loadAccounts() {
      try {
        const response = await axios.get('/accounting/chart-of-accounts')
        this.accounts = response.data.data || response.data
      } catch (error) {
        this.$toast.error('Failed to load chart of accounts')
      }
    },

    handleFileDrop(event) {
      this.isDragOver = false
      const files = event.dataTransfer.files
      if (files.length > 0) {
        this.selectFile(files[0])
      }
    },

    handleFileSelect(event) {
      const files = event.target.files
      if (files.length > 0) {
        this.selectFile(files[0])
      }
    },

    selectFile(file) {
      const allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv'
      ]
      
      if (!allowedTypes.includes(file.type) && !file.name.endsWith('.csv')) {
        this.$toast.error('Please select an Excel (.xlsx) or CSV file')
        return
      }
      
      if (file.size > 10 * 1024 * 1024) { // 10MB limit
        this.$toast.error('File size must be less than 10MB')
        return
      }
      
      this.selectedFile = file
    },

    removeFile() {
      this.selectedFile = null
      this.$refs.fileInput.value = ''
    },

    getFileIcon(filename) {
      if (filename.endsWith('.xlsx') || filename.endsWith('.xls')) {
        return 'fas fa-file-excel'
      } else if (filename.endsWith('.csv')) {
        return 'fas fa-file-csv'
      }
      return 'fas fa-file'
    },

    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes'
      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB', 'GB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    },

    async processFile() {
      if (!this.selectedFile) return
      
      this.processing = true
      this.processingStatus = 'Reading file...'
      
      try {
        const data = await this.readFile(this.selectedFile)
        this.processingStatus = 'Parsing data...'
        
        const parsedData = await this.parseData(data)
        this.processingStatus = 'Validating data...'
        
        this.uploadedData = parsedData
        await this.validateData()
        
        this.currentStep = 2
        this.$toast.success('File processed successfully')
        
      } catch (error) {
        this.$toast.error('Error processing file: ' + error.message)
        console.error('File processing error:', error)
      } finally {
        this.processing = false
        this.processingStatus = ''
      }
    },

    async readFile(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader()
        
        reader.onload = (e) => {
          try {
            if (file.name.endsWith('.csv')) {
              resolve(e.target.result)
            } else {
              // Excel file
              const workbook = XLSX.read(e.target.result, { type: 'binary' })
              const sheetName = workbook.SheetNames[0]
              const worksheet = workbook.Sheets[sheetName]
              const csvData = XLSX.utils.sheet_to_csv(worksheet)
              resolve(csvData)
            }
          } catch (error) {
            reject(error)
          }
        }
        
        reader.onerror = () => reject(new Error('Failed to read file'))
        
        if (file.name.endsWith('.csv')) {
          reader.readAsText(file)
        } else {
          reader.readAsBinaryString(file)
        }
      })
    },

    async parseData(csvData) {
      const lines = csvData.split('\n').filter(line => line.trim())
      const delimiter = this.uploadOptions.delimiter
      
      if (lines.length === 0) {
        throw new Error('File is empty')
      }
      
      let startIndex = 0
      let headers = []
      
      if (this.uploadOptions.skipFirstRow) {
        headers = lines[0].split(delimiter).map(h => h.trim().replace(/"/g, ''))
        startIndex = 1
      } else {
        // Assume standard headers
        headers = ['journal_number', 'entry_date', 'account_code', 'debit_amount', 'credit_amount', 'description']
      }
      
      const requiredColumns = ['journal_number', 'entry_date', 'account_code', 'debit_amount', 'credit_amount', 'description']
      const missingColumns = requiredColumns.filter(col => !headers.includes(col))
      
      if (missingColumns.length > 0) {
        throw new Error(`Missing required columns: ${missingColumns.join(', ')}`)
      }
      
      const parsedData = []
      
      for (let i = startIndex; i < lines.length; i++) {
        const line = lines[i].trim()
        if (!line) continue
        
        const values = line.split(delimiter).map(v => v.trim().replace(/"/g, ''))
        const row = {}
        
        headers.forEach((header, index) => {
          row[header] = values[index] || ''
        })
        
        row.rowNumber = i + 1
        parsedData.push(row)
      }
      
      return parsedData
    },

    async validateData() {
      this.validationErrors = []
      this.validationResults = { valid: 0, invalid: 0, warnings: 0 }
      
      // Group by journal_number to validate entries
      const entriesMap = new Map()
      
      this.uploadedData.forEach((row, index) => {
        row.errors = []
        row.warnings = []
        row.isValid = true
        row.hasWarnings = false
        
        // Basic field validation
        if (!row.journal_number) {
          row.errors.push('Journal number is required')
        }
        
        if (!row.entry_date) {
          row.errors.push('Entry date is required')
        } else if (!this.isValidDate(row.entry_date)) {
          row.errors.push('Invalid date format')
        }
        
        if (!row.account_code) {
          row.errors.push('Account code is required')
        } else if (!this.isValidAccount(row.account_code)) {
          row.errors.push('Account code not found')
        }
        
        const debitAmount = parseFloat(row.debit_amount) || 0
        const creditAmount = parseFloat(row.credit_amount) || 0
        
        if (debitAmount < 0 || creditAmount < 0) {
          row.errors.push('Amounts cannot be negative')
        }
        
        if (debitAmount > 0 && creditAmount > 0) {
          row.errors.push('Line cannot have both debit and credit amounts')
        }
        
        if (debitAmount === 0 && creditAmount === 0) {
          row.errors.push('Line must have either debit or credit amount')
        }
        
        if (!row.description || row.description.length < 3) {
          row.warnings.push('Description is too short')
        }
        
        // Group by journal entry
        if (row.journal_number) {
          if (!entriesMap.has(row.journal_number)) {
            entriesMap.set(row.journal_number, [])
          }
          entriesMap.get(row.journal_number).push(row)
        }
        
        // Set flags
        row.isValid = row.errors.length === 0
        row.hasWarnings = row.warnings.length > 0
        
        if (!row.isValid) {
          this.validationResults.invalid++
          this.validationErrors.push({
            row: row.rowNumber,
            message: row.errors.join(', '),
            data: row
          })
        } else {
          this.validationResults.valid++
        }
        
        if (row.hasWarnings) {
          this.validationResults.warnings++
        }
      })
      
      // Validate journal entry balance
      entriesMap.forEach((lines, journalNumber) => {
        const totalDebits = lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0)
        const totalCredits = lines.reduce((sum, line) => sum + (parseFloat(line.credit_amount) || 0), 0)
        
        if (Math.abs(totalDebits - totalCredits) > 0.01) {
          lines.forEach(line => {
            line.errors.push('Journal entry is not balanced')
            line.isValid = false
          })
        }
        
        if (lines.length < 2) {
          lines.forEach(line => {
            line.warnings.push('Journal entry should have at least 2 lines')
            line.hasWarnings = true
          })
        }
      })
      
      // Recalculate validation results
      this.validationResults = { valid: 0, invalid: 0, warnings: 0 }
      this.uploadedData.forEach(row => {
        if (!row.isValid) {
          this.validationResults.invalid++
        } else {
          this.validationResults.valid++
        }
        
        if (row.hasWarnings) {
          this.validationResults.warnings++
        }
      })
    },

    isValidDate(dateString) {
      const regex = /^\d{4}-\d{2}-\d{2}$/
      if (!regex.test(dateString)) return false
      
      const date = new Date(dateString)
      return date instanceof Date && !isNaN(date)
    },

    isValidAccount(accountCode) {
      return this.accounts.some(account => account.account_code === accountCode)
    },

    getRowStatus(row) {
      if (!row.isValid) return 'invalid'
      if (row.hasWarnings) return 'warning'
      return 'valid'
    },

    getRowStatusIcon(row) {
      if (!row.isValid) return 'fas fa-times-circle'
      if (row.hasWarnings) return 'fas fa-exclamation-triangle'
      return 'fas fa-check-circle'
    },

    goBack() {
      this.currentStep = 1
    },

    downloadErrorReport() {
      const csv = this.generateErrorReportCSV()
      this.downloadCSV(csv, 'validation_errors.csv')
    },

    generateErrorReportCSV() {
      const headers = ['Row', 'Journal Number', 'Error Type', 'Message', 'Data']
      const rows = []
      
      this.uploadedData.forEach(row => {
        if (row.errors?.length > 0) {
          row.errors.forEach(error => {
            rows.push([
              row.rowNumber,
              row.journal_number,
              'Error',
              error,
              JSON.stringify(row)
            ])
          })
        }
        
        if (row.warnings?.length > 0) {
          row.warnings.forEach(warning => {
            rows.push([
              row.rowNumber,
              row.journal_number,
              'Warning',
              warning,
              JSON.stringify(row)
            ])
          })
        }
      })
      
      return [headers, ...rows].map(row => row.join(',')).join('\n')
    },

    downloadCSV(csv, filename) {
      const blob = new Blob([csv], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      link.click()
      window.URL.revokeObjectURL(url)
    },

    async proceedToImport() {
      this.currentStep = 3
      this.importComplete = false
      this.importResults = { success: 0, failed: 0 }
      this.importErrors = []
      this.createdEntries = []
      
      const validRows = this.uploadedData.filter(row => row.isValid)
      
      // Group by journal_number
      const entriesMap = new Map()
      validRows.forEach(row => {
        if (!entriesMap.has(row.journal_number)) {
          entriesMap.set(row.journal_number, [])
        }
        entriesMap.get(row.journal_number).push(row)
      })
      
      this.importProgress.total = entriesMap.size
      this.importProgress.current = 0
      
      // Process each journal entry
      for (const [journalNumber, lines] of entriesMap) {
        this.importStatus = `Processing ${journalNumber}...`
        
        try {
          const entryData = {
            journal_number: journalNumber,
            entry_date: lines[0].entry_date,
            description: lines[0].description,
            period_id: await this.getCurrentPeriodId(),
            status: 'Draft',
            lines: lines.map(line => ({
              account_id: this.getAccountId(line.account_code),
              debit_amount: parseFloat(line.debit_amount) || 0,
              credit_amount: parseFloat(line.credit_amount) || 0,
              description: line.line_description || line.description
            }))
          }
          
          const response = await axios.post('/accounting/journal-entries', entryData)
          
          this.importResults.success++
          this.createdEntries.push({
            journal_id: response.data.data.journal_id,
            journal_number: journalNumber,
            entry_date: lines[0].entry_date,
            total_amount: lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0)
          })
          
        } catch (error) {
          this.importResults.failed++
          this.importErrors.push({
            row: lines[0].rowNumber,
            message: error.response?.data?.message || error.message
          })
        }
        
        this.importProgress.current++
        this.importProgress.percentage = Math.round((this.importProgress.current / this.importProgress.total) * 100)
        
        // Small delay to show progress
        await new Promise(resolve => setTimeout(resolve, 100))
      }
      
      this.importComplete = true
      this.importStatus = 'Import completed'
      
      if (this.importResults.success > 0) {
        this.$toast.success(`Successfully imported ${this.importResults.success} journal entries`)
      }
      if (this.importResults.failed > 0) {
        this.$toast.error(`${this.importResults.failed} entries failed to import`)
      }
    },

    async getCurrentPeriodId() {
      try {
        const response = await axios.get('/accounting/accounting-periods/current')
        return response.data.data.period_id
      } catch (error) {
        // Fallback to first available period
        const response = await axios.get('/accounting/accounting-periods')
        const periods = response.data.data || response.data
        return periods.length > 0 ? periods[0].period_id : null
      }
    },

    getAccountId(accountCode) {
      const account = this.accounts.find(acc => acc.account_code === accountCode)
      return account ? account.account_id : null
    },

    startOver() {
      this.currentStep = 1
      this.selectedFile = null
      this.uploadedData = []
      this.validationResults = { valid: 0, invalid: 0, warnings: 0 }
      this.validationErrors = []
      this.importResults = { success: 0, failed: 0 }
      this.importErrors = []
      this.createdEntries = []
      this.importComplete = false
      this.$refs.fileInput.value = ''
    },

    downloadTemplate() {
      const templateData = [
        ['journal_number', 'entry_date', 'account_code', 'debit_amount', 'credit_amount', 'description', 'line_description'],
        ['JE-001', '2024-01-15', '1001', '1000', '0', 'Cash deposit', 'Bank deposit'],
        ['JE-001', '2024-01-15', '4001', '0', '1000', 'Cash deposit', 'Revenue recognition'],
        ['JE-002', '2024-01-16', '5001', '500', '0', 'Office supplies', 'Office expense'],
        ['JE-002', '2024-01-16', '1001', '0', '500', 'Office supplies', 'Cash payment']
      ]
      
      const csv = templateData.map(row => row.join(',')).join('\n')
      this.downloadCSV(csv, 'journal_entries_template.csv')
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },

    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    },

    truncateText(text, length) {
      if (!text) return ''
      return text.length > length ? text.substring(0, length) + '...' : text
    }
  }
}
</script>

<style scoped>
.batch-upload-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
  background: var(--bg-secondary);
  min-height: 100vh;
}

/* Page Header */
.page-header {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.title-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.back-button {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  text-decoration: none;
  transition: all 0.2s;
}

.back-button:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
  transform: translateX(-2px);
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: #6366f1;
}

.page-subtitle {
  color: var(--text-secondary);
  margin: 0;
  font-size: 0.9rem;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

/* Upload Steps */
.upload-steps {
  display: flex;
  align-items: center;
  background: white;
  padding: 2rem;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.step {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
  opacity: 0.5;
  transition: all 0.3s;
}

.step.active, .step.completed {
  opacity: 1;
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  transition: all 0.3s;
}

.step.active .step-number {
  background: #6366f1;
  color: white;
}

.step.completed .step-number {
  background: #059669;
  color: white;
}

.step-content h3 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.step-content p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.step-separator {
  flex: 0 0 50px;
  height: 2px;
  background: var(--border-color);
  margin: 0 1rem;
}

/* Upload Section */
.upload-card, .requirements-card {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.upload-header h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.upload-header p {
  margin: 0;
  color: var(--text-secondary);
}

.upload-area {
  border: 3px dashed var(--border-color);
  border-radius: 16px;
  padding: 3rem 2rem;
  text-align: center;
  transition: all 0.3s;
  margin: 2rem 0;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-area.drag-over {
  border-color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
}

.upload-area.has-file {
  border-style: solid;
  border-color: #059669;
  background: rgba(5, 150, 105, 0.05);
}

.upload-content {
  width: 100%;
}

.upload-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  font-size: 2rem;
  color: var(--text-muted);
}

.upload-content h4 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
  font-size: 1.25rem;
}

.upload-content p {
  margin: 0 0 2rem 0;
  color: var(--text-secondary);
}

.file-types {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-top: 1rem;
}

.file-type {
  background: var(--bg-tertiary);
  color: var(--text-secondary);
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 500;
}

.selected-file {
  display: flex;
  align-items: center;
  gap: 1rem;
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
}

.file-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  background: #059669;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.file-info {
  flex: 1;
}

.file-info h4 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.file-info p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.btn-remove {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.btn-remove:hover {
  background: rgba(239, 68, 68, 0.2);
  transform: scale(1.05);
}

.upload-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin: 2rem 0;
  padding: 1.5rem;
  background: var(--bg-tertiary);
  border-radius: 12px;
}

.option-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.option-group label {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.875rem;
}

.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-wrapper input[type="checkbox"] {
  display: none;
}

.checkmark {
  width: 20px;
  height: 20px;
  border: 2px solid var(--border-color);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  background: white;
}

.checkbox-wrapper input[type="checkbox"]:checked + .checkmark {
  background: #6366f1;
  border-color: #6366f1;
}

.checkbox-wrapper input[type="checkbox"]:checked + .checkmark::after {
  content: '✓';
  color: white;
  font-weight: bold;
  font-size: 0.8rem;
}

.checkbox-wrapper .label {
  font-size: 0.9rem;
  color: var(--text-primary);
  font-weight: 500;
}

.upload-actions {
  text-align: center;
}

/* Requirements */
.requirements-card h3 {
  margin: 0 0 1.5rem 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.requirements-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 2rem;
}

.requirement-item h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.requirement-item ul {
  margin: 0;
  padding-left: 1.5rem;
  color: var(--text-secondary);
}

.requirement-item li {
  margin-bottom: 0.5rem;
}

/* Validation Section */
.validation-summary {
  margin-bottom: 2rem;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  background: white;
  padding: 1.5rem;
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  border-left: 4px solid;
}

.summary-card.success {
  border-left-color: #059669;
}

.summary-card.error {
  border-left-color: #dc2626;
}

.summary-card.warning {
  border-left-color: #d97706;
}

.summary-card.info {
  border-left-color: #6366f1;
}

.card-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.summary-card.success .card-icon {
  background: rgba(5, 150, 105, 0.1);
  color: #059669;
}

.summary-card.error .card-icon {
  background: rgba(220, 38, 38, 0.1);
  color: #dc2626;
}

.summary-card.warning .card-icon {
  background: rgba(217, 119, 6, 0.1);
  color: #d97706;
}

.summary-card.info .card-icon {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.card-content h3 {
  margin: 0 0 0.25rem 0;
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
}

.card-content p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

/* Validation Errors */
.errors-section {
  background: white;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.section-header {
  background: var(--bg-tertiary);
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-header h3 {
  margin: 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.errors-list {
  max-height: 400px;
  overflow-y: auto;
  padding: 1.5rem;
}

.errors-list.collapsed {
  max-height: 300px;
}

.error-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 12px;
  margin-bottom: 1rem;
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.error-item:last-child {
  margin-bottom: 0;
}

.error-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.error-content {
  flex: 1;
}

.error-content h4 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.error-content p {
  margin: 0 0 0.5rem 0;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.error-data {
  font-family: 'Courier New', monospace;
  font-size: 0.8rem;
  color: var(--text-muted);
  background: var(--bg-tertiary);
  padding: 0.5rem;
  border-radius: 6px;
  overflow-x: auto;
}

/* Preview Section */
.preview-section {
  background: white;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.preview-filters {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.preview-table-container {
  overflow-x: auto;
  max-height: 500px;
  overflow-y: auto;
}

.preview-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.preview-table th {
  background: var(--bg-tertiary);
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 2px solid var(--border-color);
  position: sticky;
  top: 0;
  z-index: 1;
}

.preview-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.preview-row {
  transition: background-color 0.2s;
}

.preview-row:hover {
  background: var(--bg-tertiary);
}

.preview-row.valid {
  background: rgba(5, 150, 105, 0.05);
}

.preview-row.invalid {
  background: rgba(239, 68, 68, 0.05);
}

.preview-row.warning {
  background: rgba(245, 158, 11, 0.05);
}

.status-cell {
  text-align: center;
}

.status-indicator {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

.status-indicator.valid {
  background: rgba(5, 150, 105, 0.1);
  color: #059669;
}

.status-indicator.invalid {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.status-indicator.warning {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}

.amount {
  text-align: right;
  font-family: 'Courier New', monospace;
  font-weight: 600;
}

.description {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.issues {
  text-align: center;
}

.issue-badges {
  display: flex;
  gap: 0.25rem;
  justify-content: center;
}

.issue-badge {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
}

.issue-badge.error {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.issue-badge.warning {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}

/* Import Section */
.import-progress {
  background: white;
  padding: 2rem;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.progress-header {
  text-align: center;
  margin-bottom: 2rem;
}

.progress-header h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.progress-header p {
  margin: 0;
  color: var(--text-secondary);
}

.progress-bar-container {
  margin-bottom: 2rem;
}

.progress-bar {
  width: 100%;
  height: 12px;
  background: var(--bg-tertiary);
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  transition: width 0.3s ease;
}

.progress-text {
  text-align: center;
  color: var(--text-secondary);
  font-size: 0.9rem;
  font-weight: 500;
}

.progress-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--bg-tertiary);
  border-radius: 8px;
}

.detail-item .label {
  color: var(--text-secondary);
  font-weight: 500;
}

.detail-item .value {
  font-weight: 600;
  color: var(--text-primary);
}

.detail-item .value.success {
  color: #059669;
}

.detail-item .value.error {
  color: #dc2626;
}

/* Import Results */
.import-results {
  background: white;
  padding: 2rem;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.results-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.result-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  background: var(--bg-tertiary);
  border-radius: 16px;
  border-left: 4px solid var(--border-color);
}

.result-card.success {
  border-left-color: #059669;
}

.result-card.error {
  border-left-color: #dc2626;
}

.import-errors, .created-entries {
  margin-bottom: 2rem;
}

.import-errors h4, .created-entries h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.error-list {
  background: var(--bg-tertiary);
  border-radius: 12px;
  padding: 1rem;
  max-height: 200px;
  overflow-y: auto;
}

.import-error {
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  gap: 1rem;
}

.import-error:last-child {
  border-bottom: none;
}

.error-row {
  font-weight: 600;
  color: #dc2626;
  flex-shrink: 0;
}

.error-message {
  color: var(--text-secondary);
}

.entries-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1rem;
}

.entry-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--bg-tertiary);
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s;
  border: 2px solid transparent;
}

.entry-link:hover {
  border-color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
  transform: translateY(-2px);
}

.entry-number {
  font-weight: 600;
  color: #6366f1;
}

.entry-date {
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.entry-amount {
  font-weight: 600;
  color: var(--text-primary);
  font-family: 'Courier New', monospace;
}

.final-actions, .validation-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
}

.help-modal {
  max-width: 900px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  color: var(--text-primary);
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0.25rem;
  border-radius: 4px;
}

.btn-close:hover {
  color: var(--text-primary);
  background: var(--bg-tertiary);
}

.modal-body {
  padding: 1.5rem;
  max-height: 60vh;
  overflow-y: auto;
}

.help-section {
  margin-bottom: 2rem;
}

.help-section:last-child {
  margin-bottom: 0;
}

.help-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  font-size: 1.1rem;
}

.help-section ul {
  margin: 0;
  padding-left: 1.5rem;
  color: var(--text-secondary);
}

.help-section li {
  margin-bottom: 0.5rem;
}

.example-table {
  overflow-x: auto;
  background: var(--bg-tertiary);
  border-radius: 8px;
  padding: 1rem;
}

.example-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}

.example-table th,
.example-table td {
  padding: 0.5rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.example-table th {
  font-weight: 600;
  background: var(--bg-secondary);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Loading Overlay */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(248, 250, 252, 0.95);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  flex-direction: column;
}

.loading-content {
  text-align: center;
}

.loading-spinner {
  width: 60px;
  height: 60px;
  border: 4px solid rgba(99, 102, 241, 0.2);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s ease-in-out infinite;
  margin: 0 auto 1rem;
}

.loading-content p {
  color: var(--text-primary);
  font-weight: 500;
  font-size: 1.1rem;
  margin-bottom: 0.5rem;
}

.loading-details {
  color: var(--text-secondary);
  font-size: 0.9rem;
}

/* Form Elements */
.form-input, .form-select {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: white;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.875rem;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-secondary {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--bg-secondary);
}

.btn-outline {
  background: white;
  color: #6366f1;
  border: 2px solid #6366f1;
}

.btn-outline:hover:not(:disabled) {
  background: #6366f1;
  color: white;
}

.btn-ghost {
  background: transparent;
  color: var(--text-secondary);
}

.btn-ghost:hover:not(:disabled) {
  background: var(--bg-tertiary);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .batch-upload-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .title-section {
    align-items: flex-start;
  }
  
  .header-actions {
    justify-content: stretch;
  }
  
  .upload-steps {
    flex-direction: column;
    gap: 1rem;
  }
  
  .step-separator {
    flex: 0 0 2px;
    width: 50px;
    height: 2px;
    margin: 0 auto;
  }
  
  .upload-area {
    padding: 2rem 1rem;
  }
  
  .upload-options {
    grid-template-columns: 1fr;
  }
  
  .requirements-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-cards {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .preview-table-container {
    font-size: 0.8rem;
  }
  
  .final-actions, .validation-actions {
    flex-direction: column;
  }
  
  .results-summary {
    grid-template-columns: 1fr;
  }
  
  .entries-list {
    grid-template-columns: 1fr;
  }
  
  .progress-details {
    grid-template-columns: 1fr;
  }
}
</style>