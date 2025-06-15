<template>
  <div class="bank-reconciliation-form">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <button @click="goBack" class="btn-back">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="page-title">
              <i class="fas fa-balance-scale"></i>
              {{ isEditMode ? 'Edit' : 'Create' }} Bank Reconciliation
            </h1>
            <p class="page-subtitle">
              {{ isEditMode ? 'Update reconciliation details' : 'Create a new bank reconciliation' }}
            </p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="saveDraft" class="btn-secondary" :disabled="saving">
            <i class="fas fa-save" :class="{ 'fa-spin': saving }"></i>
            Save as Draft
          </button>
          <button @click="saveAndContinue" class="btn-primary" :disabled="saving || !isFormValid">
            <i class="fas fa-arrow-right"></i>
            {{ isEditMode ? 'Update & Continue' : 'Create & Continue' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
      <form @submit.prevent="saveAndContinue" class="reconciliation-form">
        <!-- Basic Information Card -->
        <div class="form-card">
          <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
            <p>Configure the basic reconciliation settings</p>
          </div>
          <div class="card-body">
            <div class="form-grid">
              <div class="form-group">
                <label for="bank_id" class="form-label required">Bank Account</label>
                <select 
                  id="bank_id"
                  v-model="form.bank_id" 
                  class="form-input"
                  :class="{ 'error': errors.bank_id }"
                  :disabled="isEditMode"
                  @change="onBankAccountChange"
                >
                  <option value="">Select a bank account</option>
                  <option 
                    v-for="bank in bankAccounts" 
                    :key="bank.bank_id" 
                    :value="bank.bank_id"
                  >
                    {{ bank.account_name }} - {{ bank.account_number }}
                  </option>
                </select>
                <span v-if="errors.bank_id" class="error-message">{{ errors.bank_id }}</span>
              </div>

              <div class="form-group">
                <label for="statement_date" class="form-label required">Statement Date</label>
                <input 
                  id="statement_date"
                  v-model="form.statement_date" 
                  type="date" 
                  class="form-input"
                  :class="{ 'error': errors.statement_date }"
                  @change="validateForm"
                />
                <span v-if="errors.statement_date" class="error-message">{{ errors.statement_date }}</span>
              </div>

              <div class="form-group">
                <label for="statement_balance" class="form-label required">Statement Balance</label>
                <div class="input-with-icon">
                  <i class="fas fa-dollar-sign"></i>
                  <input 
                    id="statement_balance"
                    v-model.number="form.statement_balance" 
                    type="number" 
                    step="0.01" 
                    class="form-input"
                    :class="{ 'error': errors.statement_balance }"
                    placeholder="0.00"
                    @input="calculateVariance"
                  />
                </div>
                <span v-if="errors.statement_balance" class="error-message">{{ errors.statement_balance }}</span>
              </div>

              <div class="form-group">
                <label for="book_balance" class="form-label required">Book Balance</label>
                <div class="input-with-icon">
                  <i class="fas fa-book"></i>
                  <input 
                    id="book_balance"
                    v-model.number="form.book_balance" 
                    type="number" 
                    step="0.01" 
                    class="form-input"
                    :class="{ 'error': errors.book_balance }"
                    placeholder="0.00"
                    @input="calculateVariance"
                  />
                </div>
                <span v-if="errors.book_balance" class="error-message">{{ errors.book_balance }}</span>
                <small class="form-help">
                  <i class="fas fa-info-circle"></i>
                  Current book balance as of {{ formatDate(form.statement_date) || 'statement date' }}
                </small>
              </div>
            </div>
          </div>
        </div>

        <!-- Balance Summary Card -->
        <div class="form-card summary-card">
          <div class="card-header">
            <h3><i class="fas fa-calculator"></i> Balance Summary</h3>
            <p>Review the reconciliation summary</p>
          </div>
          <div class="card-body">
            <div class="balance-grid">
              <div class="balance-item">
                <div class="balance-label">Statement Balance</div>
                <div class="balance-value statement">
                  {{ formatCurrency(form.statement_balance) }}
                </div>
              </div>
              <div class="balance-item">
                <div class="balance-label">Book Balance</div>
                <div class="balance-value book">
                  {{ formatCurrency(form.book_balance) }}
                </div>
              </div>
              <div class="balance-item variance">
                <div class="balance-label">Variance</div>
                <div class="balance-value" :class="getVarianceClass(variance)">
                  {{ formatCurrency(variance) }}
                </div>
              </div>
            </div>
            
            <div v-if="Math.abs(variance) > 0.01" class="variance-alert">
              <i class="fas fa-exclamation-triangle"></i>
              <div>
                <strong>Variance Detected</strong>
                <p>There is a {{ formatCurrency(Math.abs(variance)) }} {{ variance > 0 ? 'overage' : 'shortage' }} that needs to be reconciled.</p>
              </div>
            </div>
            
            <div v-else-if="variance === 0 && form.statement_balance && form.book_balance" class="balanced-alert">
              <i class="fas fa-check-circle"></i>
              <div>
                <strong>Balanced</strong>
                <p>The statement and book balances match perfectly.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Additional Information Card -->
        <div class="form-card">
          <div class="card-header">
            <h3><i class="fas fa-clipboard-list"></i> Additional Information</h3>
            <p>Optional details and notes</p>
          </div>
          <div class="card-body">
            <div class="form-grid">
              <div class="form-group">
                <label for="status" class="form-label">Status</label>
                <select 
                  id="status"
                  v-model="form.status" 
                  class="form-input"
                  :disabled="isEditMode && form.status === 'Finalized'"
                >
                  <option value="Draft">Draft</option>
                  <option value="In Progress">In Progress</option>
                  <option v-if="isEditMode" value="Finalized" :disabled="true">Finalized</option>
                </select>
                <small class="form-help">
                  <i class="fas fa-info-circle"></i>
                  Status can be changed during reconciliation process
                </small>
              </div>

              <div class="form-group">
                <label for="reconciliation_period" class="form-label">Reconciliation Period</label>
                <input 
                  id="reconciliation_period"
                  v-model="form.reconciliation_period" 
                  type="text" 
                  class="form-input"
                  placeholder="e.g., January 2025"
                  readonly
                />
              </div>
            </div>

            <div class="form-group">
              <label for="notes" class="form-label">Notes</label>
              <textarea 
                id="notes"
                v-model="form.notes" 
                class="form-textarea"
                rows="4"
                placeholder="Add any relevant notes or comments about this reconciliation..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Bank Account Details (if selected) -->
        <div v-if="selectedBankAccount" class="form-card">
          <div class="card-header">
            <h3><i class="fas fa-university"></i> Bank Account Details</h3>
            <p>Information about the selected bank account</p>
          </div>
          <div class="card-body">
            <div class="bank-details-grid">
              <div class="detail-item">
                <span class="detail-label">Account Name</span>
                <span class="detail-value">{{ selectedBankAccount.account_name }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Account Number</span>
                <span class="detail-value">{{ selectedBankAccount.account_number }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Bank Name</span>
                <span class="detail-value">{{ selectedBankAccount.bank_name || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Account Type</span>
                <span class="detail-value">{{ selectedBankAccount.account_type || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Currency</span>
                <span class="detail-value">{{ selectedBankAccount.currency || 'USD' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Current Balance</span>
                <span class="detail-value balance">{{ formatCurrency(selectedBankAccount.current_balance) }}</span>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Validation Summary -->
    <div v-if="Object.keys(errors).length > 0" class="validation-summary">
      <div class="validation-header">
        <i class="fas fa-exclamation-circle"></i>
        <h4>Please fix the following errors:</h4>
      </div>
      <ul class="validation-list">
        <li v-for="(error, field) in errors" :key="field">{{ error }}</li>
      </ul>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>{{ loadingMessage }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BankReconciliationForm',
  data() {
    return {
      loading: false,
      saving: false,
      loadingMessage: '',
      isEditMode: false,
      reconciliationId: null,
      form: {
        bank_id: '',
        statement_date: '',
        statement_balance: null,
        book_balance: null,
        status: 'Draft',
        notes: '',
        reconciliation_period: ''
      },
      originalForm: {},
      errors: {},
      bankAccounts: [],
      selectedBankAccount: null
    }
  },
  computed: {
    variance() {
      const statement = parseFloat(this.form.statement_balance) || 0
      const book = parseFloat(this.form.book_balance) || 0
      return statement - book
    },
    isFormValid() {
      return this.form.bank_id && 
             this.form.statement_date && 
             this.form.statement_balance !== null && 
             this.form.book_balance !== null &&
             Object.keys(this.errors).length === 0
    }
  },
  watch: {
    'form.statement_date'(newDate) {
      if (newDate) {
        this.updateReconciliationPeriod(newDate)
      }
    }
  },
  async mounted() {
    await this.initializeForm()
  },
  methods: {
    async initializeForm() {
      this.loading = true
      this.loadingMessage = 'Loading form data...'
      
      try {
        // Check if we're in edit mode
        this.reconciliationId = this.$route.params.id
        this.isEditMode = !!this.reconciliationId

        // Load bank accounts
        await this.loadBankAccounts()

        if (this.isEditMode) {
          await this.loadReconciliation()
        } else {
          // Set default values for new reconciliation
          this.form.statement_date = new Date().toISOString().split('T')[0]
          this.updateReconciliationPeriod(this.form.statement_date)
        }

        // Store original form state for change detection
        this.originalForm = { ...this.form }
      } catch (error) {
        console.error('Error initializing form:', error)
        this.$toast?.error('Failed to load form data')
      } finally {
        this.loading = false
      }
    },

    async loadBankAccounts() {
      try {
        const response = await axios.get('/accounting/bank-accounts')
        this.bankAccounts = response.data.data || response.data
      } catch (error) {
        console.error('Error loading bank accounts:', error)
        this.$toast?.error('Failed to load bank accounts')
      }
    },

    async loadReconciliation() {
      this.loadingMessage = 'Loading reconciliation data...'
      try {
        const response = await axios.get(`/accounting/bank-reconciliations/${this.reconciliationId}`)
        const reconciliation = response.data.data
        
        this.form = {
          bank_id: reconciliation.bank_id,
          statement_date: reconciliation.statement_date,
          statement_balance: reconciliation.statement_balance,
          book_balance: reconciliation.book_balance,
          status: reconciliation.status,
          notes: reconciliation.notes || '',
          reconciliation_period: reconciliation.reconciliation_period || ''
        }

        // Set selected bank account
        this.onBankAccountChange()
        
        // Update reconciliation period if not set
        if (!this.form.reconciliation_period && this.form.statement_date) {
          this.updateReconciliationPeriod(this.form.statement_date)
        }
      } catch (error) {
        console.error('Error loading reconciliation:', error)
        this.$toast?.error('Failed to load reconciliation data')
        this.goBack()
      }
    },

    onBankAccountChange() {
      if (this.form.bank_id) {
        this.selectedBankAccount = this.bankAccounts.find(
          bank => bank.bank_id == this.form.bank_id
        )
        
        // Auto-fill book balance if available and not in edit mode
        if (!this.isEditMode && this.selectedBankAccount?.current_balance) {
          this.form.book_balance = this.selectedBankAccount.current_balance
          this.calculateVariance()
        }
      } else {
        this.selectedBankAccount = null
      }
      this.validateForm()
    },

    updateReconciliationPeriod(date) {
      if (date) {
        const dateObj = new Date(date)
        const options = { year: 'numeric', month: 'long' }
        this.form.reconciliation_period = dateObj.toLocaleDateString('en-US', options)
      }
    },

    calculateVariance() {
      // Variance is calculated in computed property
      this.validateForm()
    },

    validateForm() {
      this.errors = {}

      // Required field validation
      if (!this.form.bank_id) {
        this.errors.bank_id = 'Bank account is required'
      }

      if (!this.form.statement_date) {
        this.errors.statement_date = 'Statement date is required'
      }

      if (this.form.statement_balance === null || this.form.statement_balance === '') {
        this.errors.statement_balance = 'Statement balance is required'
      }

      if (this.form.book_balance === null || this.form.book_balance === '') {
        this.errors.book_balance = 'Book balance is required'
      }

      // Date validation
      if (this.form.statement_date) {
        const statementDate = new Date(this.form.statement_date)
        const today = new Date()
        if (statementDate > today) {
          this.errors.statement_date = 'Statement date cannot be in the future'
        }
      }

      // Check for existing reconciliation (only for new reconciliations)
      if (!this.isEditMode && this.form.bank_id && this.form.statement_date) {
        this.checkExistingReconciliation()
      }
    },

    async checkExistingReconciliation() {
      try {
        const response = await axios.get('/accounting/bank-reconciliations', {
          params: {
            bank_id: this.form.bank_id,
            statement_date: this.form.statement_date
          }
        })
        
        if (response.data.data && response.data.data.length > 0) {
          this.errors.statement_date = 'A reconciliation already exists for this bank account and date'
        }
      } catch (error) {
        // Ignore validation errors for this check
      }
    },

    async saveDraft() {
      this.form.status = 'Draft'
      await this.saveReconciliation(false)
    },

    async saveAndContinue() {
      if (!this.isFormValid) {
        this.validateForm()
        return
      }

      if (this.form.status === 'Draft') {
        this.form.status = 'In Progress'
      }

      const success = await this.saveReconciliation(true)
      if (success) {
        // Navigate to reconciliation detail page
        const id = this.reconciliationId || this.lastCreatedId
        this.$router.push(`/accounting/bank-reconciliations/${id}`)
      }
    },

    async saveReconciliation(showSuccessMessage = true) {
      this.saving = true
      
      try {
        let response
        if (this.isEditMode) {
          response = await axios.put(`/accounting/bank-reconciliations/${this.reconciliationId}`, this.form)
        } else {
          response = await axios.post('/accounting/bank-reconciliations', this.form)
          this.lastCreatedId = response.data.data.reconciliation_id
        }

        if (showSuccessMessage) {
          this.$toast?.success(
            this.isEditMode 
              ? 'Reconciliation updated successfully' 
              : 'Reconciliation created successfully'
          )
        }

        // Update original form state
        this.originalForm = { ...this.form }
        
        return true
      } catch (error) {
        console.error('Error saving reconciliation:', error)
        
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors
        } else {
          this.$toast?.error(
            error.response?.data?.message || 
            'Failed to save reconciliation'
          )
        }
        return false
      } finally {
        this.saving = false
      }
    },

    goBack() {
      // Check if form has unsaved changes
      const hasChanges = JSON.stringify(this.form) !== JSON.stringify(this.originalForm)
      
      if (hasChanges) {
        if (confirm('You have unsaved changes. Are you sure you want to leave?')) {
          this.$router.go(-1)
        }
      } else {
        this.$router.go(-1)
      }
    },

    formatDate(date) {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    },

    formatCurrency(amount) {
      if (amount === null || amount === undefined || isNaN(amount)) return '$0.00'
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    },

    getVarianceClass(variance) {
      if (Math.abs(variance) < 0.01) return 'variance-zero'
      return variance > 0 ? 'variance-positive' : 'variance-negative'
    }
  }
}
</script>

<style scoped>
/* CSS Variables */
:root {
  --primary-color: #2563eb;
  --primary-dark: #1d4ed8;
  --success-color: #059669;
  --warning-color: #d97706;
  --danger-color: #dc2626;
  --gray-50: #f8fafc;
  --gray-100: #f1f5f9;
  --gray-200: #e2e8f0;
  --gray-300: #cbd5e1;
  --gray-400: #94a3b8;
  --gray-500: #64748b;
  --gray-600: #475569;
  --gray-700: #334155;
  --gray-800: #1e293b;
  --gray-900: #0f172a;
  --white: #ffffff;
  --border-radius: 12px;
  --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  --box-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.1);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.bank-reconciliation-form {
  min-height: 100vh;
  background: var(--gray-50);
  padding: 2rem;
}

/* Page Header */
.page-header {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
  position: sticky;
  top: 2rem;
  z-index: 100;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.title-section {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.btn-back {
  width: 3rem;
  height: 3rem;
  border: 2px solid var(--gray-200);
  background: var(--white);
  color: var(--gray-600);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: var(--transition);
  font-size: 1rem;
}

.btn-back:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
  color: var(--gray-800);
  transform: translateX(-2px);
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: var(--primary-color);
}

.page-subtitle {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Buttons */
.btn-primary, .btn-secondary {
  padding: 0.75rem 1.5rem;
  border-radius: var(--border-radius);
  font-weight: 600;
  font-size: 0.875rem;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn-primary {
  background: var(--primary-color);
  color: var(--white);
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.btn-primary:disabled {
  background: var(--gray-300);
  color: var(--gray-500);
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-secondary {
  background: var(--gray-200);
  color: var(--gray-700);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--gray-300);
  transform: translateY(-1px);
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Form Container */
.form-container {
  max-width: 1200px;
  margin: 0 auto;
}

.reconciliation-form {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Form Cards */
.form-card {
  background: var(--white);
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  overflow: hidden;
  transition: var(--transition);
}

.form-card:hover {
  box-shadow: var(--box-shadow-lg);
}

.card-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--gray-200);
  background: var(--gray-50);
}

.card-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-header h3 i {
  color: var(--primary-color);
}

.card-header p {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin: 0;
}

.card-body {
  padding: 2rem;
}

/* Form Groups */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-label {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--gray-700);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.form-label.required::after {
  content: '*';
  color: var(--danger-color);
  margin-left: 0.25rem;
}

.form-input, .form-textarea {
  padding: 0.75rem 1rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  transition: var(--transition);
  background: var(--white);
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-input.error, .form-textarea.error {
  border-color: var(--danger-color);
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.form-input:disabled {
  background: var(--gray-100);
  color: var(--gray-500);
  cursor: not-allowed;
}

.input-with-icon {
  position: relative;
}

.input-with-icon i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  z-index: 1;
}

.input-with-icon .form-input {
  padding-left: 2.5rem;
}

.form-help {
  font-size: 0.75rem;
  color: var(--gray-500);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.error-message {
  color: var(--danger-color);
  font-size: 0.75rem;
  font-weight: 500;
}

/* Summary Card */
.summary-card .card-body {
  padding: 1.5rem 2rem;
}

.balance-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
  margin-bottom: 1.5rem;
}

.balance-item {
  text-align: center;
  padding: 1rem;
  border-radius: var(--border-radius);
  background: var(--gray-50);
  transition: var(--transition);
}

.balance-item:hover {
  background: var(--gray-100);
  transform: translateY(-2px);
}

.balance-item.variance {
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.balance-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-600);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.5rem;
}

.balance-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--gray-900);
}

.balance-value.statement {
  color: var(--primary-color);
}

.balance-value.book {
  color: var(--success-color);
}

.variance-zero {
  color: var(--gray-600);
}

.variance-positive {
  color: var(--success-color);
}

.variance-negative {
  color: var(--danger-color);
}

/* Alerts */
.variance-alert, .balanced-alert {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem;
  border-radius: var(--border-radius);
  margin-top: 1rem;
}

.variance-alert {
  background: rgba(217, 119, 6, 0.1);
  border: 1px solid rgba(217, 119, 6, 0.2);
  color: var(--warning-color);
}

.balanced-alert {
  background: rgba(5, 150, 105, 0.1);
  border: 1px solid rgba(5, 150, 105, 0.2);
  color: var(--success-color);
}

.variance-alert i, .balanced-alert i {
  font-size: 1.25rem;
  margin-top: 0.125rem;
  flex-shrink: 0;
}

.variance-alert strong, .balanced-alert strong {
  display: block;
  margin-bottom: 0.25rem;
}

.variance-alert p, .balanced-alert p {
  margin: 0;
  font-size: 0.875rem;
  opacity: 0.8;
}

/* Bank Details */
.bank-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--gray-50);
  border-radius: var(--border-radius);
}

.detail-label {
  font-weight: 600;
  color: var(--gray-600);
  font-size: 0.875rem;
}

.detail-value {
  font-weight: 500;
  color: var(--gray-900);
  text-align: right;
}

.detail-value.balance {
  color: var(--primary-color);
  font-weight: 700;
}

/* Validation Summary */
.validation-summary {
  background: rgba(220, 38, 38, 0.1);
  border: 1px solid rgba(220, 38, 38, 0.2);
  border-radius: var(--border-radius);
  padding: 1.5rem;
  margin-top: 2rem;
}

.validation-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.validation-header i {
  color: var(--danger-color);
  font-size: 1.25rem;
}

.validation-header h4 {
  color: var(--danger-color);
  margin: 0;
  font-size: 1rem;
}

.validation-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.validation-list li {
  color: var(--danger-color);
  padding: 0.25rem 0;
  font-size: 0.875rem;
}

.validation-list li::before {
  content: '•';
  margin-right: 0.5rem;
  font-weight: bold;
}

/* Loading Overlay */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.loading-content {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  text-align: center;
  box-shadow: var(--box-shadow-lg);
}

.loading-spinner {
  font-size: 2rem;
  color: var(--primary-color);
  margin-bottom: 1rem;
}

.loading-content p {
  color: var(--gray-600);
  margin: 0;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .bank-reconciliation-form {
    padding: 1rem;
  }

  .page-header {
    top: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: center;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .balance-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .bank-details-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .title-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .page-title {
    font-size: 1.5rem;
  }

  .card-body {
    padding: 1.5rem;
  }

  .detail-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .detail-value {
    text-align: left;
  }
}
</style>