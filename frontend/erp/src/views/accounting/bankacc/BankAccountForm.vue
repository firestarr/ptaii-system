<template>
  <div class="bank-account-form-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <router-link to="/bank-accounts" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Bank Accounts
          </router-link>
          <h1 class="page-title">
            <i class="fas fa-university"></i>
            {{ isEdit ? 'Edit Bank Account' : 'Create New Bank Account' }}
          </h1>
          <p class="page-subtitle">
            {{ isEdit ? 'Update bank account information' : 'Add a new bank account to your organization' }}
          </p>
        </div>
        <div class="header-actions" v-if="isEdit">
          <router-link :to="`/bank-accounts/${accountId}`" class="btn btn-secondary">
            <i class="fas fa-eye"></i>
            View Details
          </router-link>
        </div>
      </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
      <form @submit.prevent="submitForm" class="bank-account-form">
        <!-- Bank Information Section -->
        <div class="form-section">
          <div class="section-header">
            <h3>
              <i class="fas fa-building"></i>
              Bank Information
            </h3>
            <p>Basic bank and account details</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label for="bank_name" class="form-label required">
                Bank Name
              </label>
              <input
                id="bank_name"
                v-model="form.bank_name"
                type="text"
                class="form-input"
                :class="{ 'error': errors.bank_name }"
                placeholder="e.g., Bank of America, Chase, Wells Fargo"
                maxlength="100"
                required
              >
              <div class="error-message" v-if="errors.bank_name">
                {{ errors.bank_name[0] }}
              </div>
            </div>

            <div class="form-group">
              <label for="account_number" class="form-label required">
                Account Number
              </label>
              <input
                id="account_number"
                v-model="form.account_number"
                type="text"
                class="form-input"
                :class="{ 'error': errors.account_number }"
                placeholder="Enter account number"
                maxlength="50"
                required
              >
              <div class="input-help">
                Your full account number (will be masked in lists for security)
              </div>
              <div class="error-message" v-if="errors.account_number">
                {{ errors.account_number[0] }}
              </div>
            </div>

            <div class="form-group full-width">
              <label for="account_name" class="form-label required">
                Account Name/Title
              </label>
              <input
                id="account_name"
                v-model="form.account_name"
                type="text"
                class="form-input"
                :class="{ 'error': errors.account_name }"
                placeholder="e.g., Main Operating Account, Payroll Account"
                maxlength="100"
                required
              >
              <div class="input-help">
                A descriptive name for this account
              </div>
              <div class="error-message" v-if="errors.account_name">
                {{ errors.account_name[0] }}
              </div>
            </div>
          </div>
        </div>

        <!-- Financial Information Section -->
        <div class="form-section">
          <div class="section-header">
            <h3>
              <i class="fas fa-dollar-sign"></i>
              Financial Information
            </h3>
            <p>Balance and GL account mapping</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label for="current_balance" class="form-label required">
                Current Balance
              </label>
              <div class="input-with-icon">
                <i class="fas fa-dollar-sign input-icon"></i>
                <input
                  id="current_balance"
                  v-model="form.current_balance"
                  type="number"
                  step="0.01"
                  class="form-input with-icon"
                  :class="{ 'error': errors.current_balance }"
                  placeholder="0.00"
                  required
                >
              </div>
              <div class="input-help">
                Enter the current balance as shown in your bank statement
              </div>
              <div class="error-message" v-if="errors.current_balance">
                {{ errors.current_balance[0] }}
              </div>
            </div>

            <div class="form-group">
              <label for="gl_account_id" class="form-label required">
                General Ledger Account
              </label>
              <div class="select-wrapper">
                <select
                  id="gl_account_id"
                  v-model="form.gl_account_id"
                  class="form-select"
                  :class="{ 'error': errors.gl_account_id }"
                  required
                  :disabled="loadingGLAccounts"
                >
                  <option value="">Select GL Account...</option>
                  <option
                    v-for="account in assetAccounts"
                    :key="account.account_id"
                    :value="account.account_id"
                  >
                    {{ account.account_code }} - {{ account.account_name }}
                  </option>
                </select>
                <i class="fas fa-chevron-down select-icon"></i>
              </div>
              <div class="input-help" v-if="loadingGLAccounts">
                Loading GL accounts...
              </div>
              <div class="input-help" v-else>
                Select the asset account that corresponds to this bank account
              </div>
              <div class="error-message" v-if="errors.gl_account_id">
                {{ errors.gl_account_id[0] }}
              </div>
            </div>
          </div>
        </div>

        <!-- Additional Information Section -->
        <div class="form-section">
          <div class="section-header">
            <h3>
              <i class="fas fa-info-circle"></i>
              Additional Information
            </h3>
            <p>Optional details and settings</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label for="bank_branch" class="form-label">
                Bank Branch
              </label>
              <input
                id="bank_branch"
                v-model="form.bank_branch"
                type="text"
                class="form-input"
                placeholder="e.g., Downtown Branch"
                maxlength="100"
              >
              <div class="input-help">
                Optional: Specific branch information
              </div>
            </div>

            <div class="form-group">
              <label for="routing_number" class="form-label">
                Routing Number
              </label>
              <input
                id="routing_number"
                v-model="form.routing_number"
                type="text"
                class="form-input"
                placeholder="e.g., 123456789"
                maxlength="9"
                pattern="[0-9]{9}"
              >
              <div class="input-help">
                Optional: 9-digit routing number
              </div>
            </div>

            <div class="form-group full-width">
              <label for="notes" class="form-label">
                Notes
              </label>
              <textarea
                id="notes"
                v-model="form.notes"
                class="form-textarea"
                rows="3"
                placeholder="Add any additional notes or comments about this account..."
                maxlength="500"
              ></textarea>
              <div class="character-count">
                {{ (form.notes || '').length }}/500 characters
              </div>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <div class="actions-left">
            <router-link to="/bank-accounts" class="btn btn-outline">
              <i class="fas fa-times"></i>
              Cancel
            </router-link>
          </div>
          <div class="actions-right">
            <button
              type="button"
              @click="saveAsDraft"
              class="btn btn-secondary"
              :disabled="submitting"
              v-if="!isEdit"
            >
              <i class="fas fa-save"></i>
              Save as Draft
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="submitting || !isFormValid"
            >
              <i class="fas fa-spinner fa-spin" v-if="submitting"></i>
              <i class="fas fa-check" v-else></i>
              {{ submitting ? 'Saving...' : (isEdit ? 'Update Account' : 'Create Account') }}
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Preview Card -->
    <div class="preview-card" v-if="form.bank_name || form.account_name">
      <div class="preview-header">
        <h3>
          <i class="fas fa-eye"></i>
          Preview
        </h3>
        <p>How this account will appear in the system</p>
      </div>
      
      <div class="account-preview">
        <div class="preview-icon">
          <i class="fas fa-university"></i>
        </div>
        <div class="preview-details">
          <h4>{{ form.bank_name || 'Bank Name' }}</h4>
          <p class="preview-account-name">{{ form.account_name || 'Account Name' }}</p>
          <p class="preview-account-number">
            {{ form.account_number ? `****${form.account_number.slice(-4)}` : '****XXXX' }}
          </p>
          <div class="preview-balance">
            <span class="balance-label">Balance:</span>
            <span class="balance-amount" :class="getBalanceClass(form.current_balance)">
              {{ formatCurrency(form.current_balance) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loadingAccount" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner"></div>
        <p>Loading account data...</p>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BankAccountForm',
  setup() {
    const router = useRouter()
    const route = useRoute()
    
    // Reactive data
    const form = ref({
      bank_name: '',
      account_number: '',
      account_name: '',
      current_balance: '',
      gl_account_id: '',
      bank_branch: '',
      routing_number: '',
      notes: ''
    })
    
    const errors = ref({})
    const submitting = ref(false)
    const loadingAccount = ref(false)
    const loadingGLAccounts = ref(false)
    const assetAccounts = ref([])
    
    // Computed properties
    const isEdit = computed(() => route.name === 'EditBankAccount')
    const accountId = computed(() => route.params.id)
    
    const isFormValid = computed(() => {
      return form.value.bank_name &&
             form.value.account_number &&
             form.value.account_name &&
             form.value.current_balance !== '' &&
             form.value.gl_account_id
    })

    // Methods
    const fetchGLAccounts = async () => {
      loadingGLAccounts.value = true
      try {
        const response = await axios.get('/accounting/chart-of-accounts', {
          params: {
            account_type: 'Asset',
            active: true
          }
        })
        assetAccounts.value = response.data.data || []
      } catch (error) {
        console.error('Error fetching GL accounts:', error)
        showNotification('Error fetching GL accounts', 'error')
      } finally {
        loadingGLAccounts.value = false
      }
    }

    const fetchAccount = async (id) => {
      loadingAccount.value = true
      try {
        const response = await axios.get(`/accounting/bank-accounts/${id}`)
        const account = response.data.data
        
        // Populate form with existing data
        Object.keys(form.value).forEach(key => {
          if (account[key] !== undefined) {
            form.value[key] = account[key]
          }
        })
      } catch (error) {
        console.error('Error fetching account:', error)
        showNotification('Error loading account data', 'error')
        router.push('/bank-accounts')
      } finally {
        loadingAccount.value = false
      }
    }

    const validateForm = () => {
      errors.value = {}
      
      if (!form.value.bank_name) {
        errors.value.bank_name = ['Bank name is required']
      }
      
      if (!form.value.account_number) {
        errors.value.account_number = ['Account number is required']
      }
      
      if (!form.value.account_name) {
        errors.value.account_name = ['Account name is required']
      }
      
      if (form.value.current_balance === '' || form.value.current_balance === null) {
        errors.value.current_balance = ['Current balance is required']
      }
      
      if (!form.value.gl_account_id) {
        errors.value.gl_account_id = ['GL account selection is required']
      }
      
      if (form.value.routing_number && form.value.routing_number.length !== 9) {
        errors.value.routing_number = ['Routing number must be 9 digits']
      }
      
      return Object.keys(errors.value).length === 0
    }

    const submitForm = async () => {
      if (!validateForm()) {
        showNotification('Please fix the errors before submitting', 'error')
        return
      }
      
      submitting.value = true
      errors.value = {}
      
      try {
        const url = isEdit.value 
          ? `/bank-accounts/${accountId.value}` 
          : '/bank-accounts'
        const method = isEdit.value ? 'put' : 'post'
        
        const response = await axios[method](url, form.value)
        
        showNotification(
          isEdit.value ? 'Bank account updated successfully' : 'Bank account created successfully',
          'success'
        )
        
        // Redirect to the account detail page
        const id = isEdit.value ? accountId.value : response.data.data.bank_id
        router.push(`/bank-accounts/${id}`)
        
      } catch (error) {
        console.error('Error submitting form:', error)
        
        if (error.response?.data?.errors) {
          errors.value = error.response.data.errors
        } else {
          showNotification(
            error.response?.data?.message || 'Error saving bank account',
            'error'
          )
        }
      } finally {
        submitting.value = false
      }
    }

    const saveAsDraft = async () => {
      // For now, just save with a draft status or similar logic
      form.value.status = 'draft'
      await submitForm()
    }

    const resetForm = () => {
      Object.keys(form.value).forEach(key => {
        form.value[key] = ''
      })
      errors.value = {}
    }

    // Utility methods
    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }

    const getBalanceClass = (balance) => {
      const amount = parseFloat(balance) || 0
      if (amount > 0) return 'positive'
      if (amount < 0) return 'negative'
      return 'zero'
    }

    const showNotification = (message, type = 'info') => {
      // Implement notification system here
      console.log(`${type.toUpperCase()}: ${message}`)
    }

    // Watchers
    watch(() => route.params.id, (newId) => {
      if (newId && isEdit.value) {
        fetchAccount(newId)
      }
    })

    // Lifecycle
    onMounted(() => {
      fetchGLAccounts()
      
      if (isEdit.value && accountId.value) {
        fetchAccount(accountId.value)
      }
    })

    return {
      form,
      errors,
      submitting,
      loadingAccount,
      loadingGLAccounts,
      assetAccounts,
      isEdit,
      accountId,
      isFormValid,
      submitForm,
      saveAsDraft,
      resetForm,
      formatCurrency,
      getBalanceClass
    }
  }
}
</script>

<style scoped>
.bank-account-form-container {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
}

/* Page Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.header-left {
  flex: 1;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--gray-600);
  text-decoration: none;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  transition: color 0.2s ease;
}

.back-link:hover {
  color: var(--primary-color);
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: var(--primary-color);
}

.page-subtitle {
  color: var(--gray-600);
  font-size: 1.1rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Main Layout */
.form-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.bank-account-form {
  padding: 2rem;
}

/* Form Sections */
.form-section {
  margin-bottom: 3rem;
}

.form-section:last-child {
  margin-bottom: 0;
}

.section-header {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--gray-100);
}

.section-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.section-header h3 i {
  color: var(--primary-color);
}

.section-header p {
  color: var(--gray-600);
  margin: 0;
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

/* Form Controls */
.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
}

.form-label.required::after {
  content: " *";
  color: var(--danger-color);
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid var(--gray-200);
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.2s ease;
  background: white;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-input.error,
.form-select.error,
.form-textarea.error {
  border-color: var(--danger-color);
}

.form-input.with-icon {
  padding-left: 2.5rem;
}

.input-with-icon {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  z-index: 1;
}

.select-wrapper {
  position: relative;
}

.select-icon {
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  pointer-events: none;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.input-help {
  font-size: 0.85rem;
  color: var(--gray-500);
  margin-top: 0.25rem;
}

.character-count {
  font-size: 0.85rem;
  color: var(--gray-500);
  margin-top: 0.25rem;
  text-align: right;
}

.error-message {
  color: var(--danger-color);
  font-size: 0.85rem;
  margin-top: 0.25rem;
  font-weight: 500;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 2rem;
  border-top: 2px solid var(--gray-100);
  margin-top: 2rem;
}

.actions-left,
.actions-right {
  display: flex;
  gap: 1rem;
}

/* Preview Card */
.preview-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.preview-header {
  padding: 1.5rem 1.5rem 1rem 1.5rem;
  border-bottom: 1px solid var(--gray-200);
}

.preview-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.preview-header h3 i {
  color: var(--primary-color);
}

.preview-header p {
  color: var(--gray-600);
  margin: 0;
  font-size: 0.9rem;
}

.account-preview {
  padding: 1.5rem;
  display: flex;
  gap: 1rem;
  align-items: center;
}

.preview-icon {
  width: 48px;
  height: 48px;
  background: var(--primary-color);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.preview-details {
  flex: 1;
}

.preview-details h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.preview-account-name {
  color: var(--gray-700);
  margin: 0 0 0.25rem 0;
  font-size: 0.95rem;
}

.preview-account-number {
  color: var(--gray-600);
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
  margin: 0 0 0.75rem 0;
}

.preview-balance {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.balance-label {
  font-size: 0.9rem;
  color: var(--gray-600);
}

.balance-amount {
  font-size: 1.1rem;
  font-weight: 600;
}

.balance-amount.positive {
  color: var(--success-color);
}

.balance-amount.negative {
  color: var(--danger-color);
}

.balance-amount.zero {
  color: var(--gray-500);
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.btn-secondary {
  background: var(--gray-100);
  color: var(--gray-700);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--gray-200);
}

.btn-outline {
  background: transparent;
  color: var(--gray-700);
  border: 2px solid var(--gray-200);
}

.btn-outline:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Loading Overlay */
.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.loading-content {
  text-align: center;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid var(--gray-200);
  border-top: 4px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem auto;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .bank-account-form-container {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
  }

  .header-actions {
    width: 100%;
    justify-content: stretch;
  }

  .bank-account-form {
    padding: 1.5rem;
  }

  .form-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .form-actions {
    flex-direction: column;
    gap: 1rem;
  }

  .actions-left,
  .actions-right {
    width: 100%;
    justify-content: stretch;
  }

  .account-preview {
    flex-direction: column;
    text-align: center;
  }

  .preview-balance {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .page-title {
    font-size: 1.5rem;
  }

  .section-header h3 {
    font-size: 1.1rem;
  }

  .preview-card {
    margin-top: 1rem;
  }
}
</style>