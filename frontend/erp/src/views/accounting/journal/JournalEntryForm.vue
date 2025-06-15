<!-- src/views/accounting/JournalEntryForm.vue -->
<template>
  <div class="journal-entry-form-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <router-link to="/accounting/journal-entries" class="back-button">
            <i class="fas fa-arrow-left"></i>
          </router-link>
          <div>
            <h1 class="page-title">
              <i class="fas fa-book"></i>
              {{ isEdit ? 'Edit Journal Entry' : 'Create Journal Entry' }}
            </h1>
            <p class="page-subtitle">
              {{ isEdit ? `Editing ${form.journal_number}` : 'Create a new accounting journal entry' }}
            </p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="resetForm" class="btn btn-secondary" type="button">
            <i class="fas fa-undo"></i>
            Reset
          </button>
          <button @click="saveDraft" class="btn btn-outline" type="button" :disabled="saving">
            <i class="fas fa-save" :class="{ 'fa-spin': saving }"></i>
            Save as Draft
          </button>
          <button @click="saveAndPost" class="btn btn-primary" type="button" :disabled="saving || !isBalanced">
            <i class="fas fa-check" :class="{ 'fa-spin': saving }"></i>
            Save & Post
          </button>
        </div>
      </div>
    </div>

    <!-- Balance Alert -->
    <div v-if="!isBalanced && lines.length > 0" class="balance-alert">
      <div class="alert-content">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
          <h4>Entry Not Balanced</h4>
          <p>Total debits ({{ formatCurrency(totalDebits) }}) must equal total credits ({{ formatCurrency(totalCredits) }})</p>
          <p class="difference">Difference: {{ formatCurrency(Math.abs(totalDebits - totalCredits)) }}</p>
        </div>
      </div>
    </div>

    <!-- Main Form -->
    <form @submit.prevent="saveDraft" class="journal-form">
      <!-- Entry Information -->
      <div class="form-section">
        <div class="section-header">
          <h3><i class="fas fa-info-circle"></i> Entry Information</h3>
        </div>
        <div class="section-content">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label required">Journal Number</label>
              <input
                type="text"
                v-model="form.journal_number"
                class="form-input"
                :class="{ 'error': errors.journal_number }"
                placeholder="Enter journal number"
                required
              />
              <span v-if="errors.journal_number" class="error-message">
                {{ errors.journal_number[0] }}
              </span>
            </div>

            <div class="form-group">
              <label class="form-label required">Entry Date</label>
              <input
                type="date"
                v-model="form.entry_date"
                class="form-input"
                :class="{ 'error': errors.entry_date }"
                required
              />
              <span v-if="errors.entry_date" class="error-message">
                {{ errors.entry_date[0] }}
              </span>
            </div>

            <div class="form-group">
              <label class="form-label required">Accounting Period</label>
              <select
                v-model="form.period_id"
                class="form-select"
                :class="{ 'error': errors.period_id }"
                required
              >
                <option value="">Select Period</option>
                <option v-for="period in periods" :key="period.period_id" :value="period.period_id">
                  {{ period.period_name }} ({{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }})
                </option>
              </select>
              <span v-if="errors.period_id" class="error-message">
                {{ errors.period_id[0] }}
              </span>
            </div>

            <div class="form-group">
              <label class="form-label">Reference Type</label>
              <select v-model="form.reference_type" class="form-select">
                <option value="">Select Reference Type</option>
                <option value="Invoice">Invoice</option>
                <option value="Payment">Payment</option>
                <option value="Receipt">Receipt</option>
                <option value="Transfer">Transfer</option>
                <option value="Adjustment">Adjustment</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="form-group" v-if="form.reference_type">
              <label class="form-label">Reference ID</label>
              <input
                type="text"
                v-model="form.reference_id"
                class="form-input"
                placeholder="Enter reference ID"
              />
            </div>

            <div class="form-group full-width">
              <label class="form-label">Description</label>
              <textarea
                v-model="form.description"
                class="form-textarea"
                :class="{ 'error': errors.description }"
                rows="3"
                placeholder="Enter journal entry description"
              ></textarea>
              <span v-if="errors.description" class="error-message">
                {{ errors.description[0] }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Journal Lines -->
      <div class="form-section">
        <div class="section-header">
          <h3><i class="fas fa-list"></i> Journal Lines</h3>
          <div class="balance-summary">
            <div class="balance-item">
              <span class="label">Total Debits:</span>
              <span class="amount debit">{{ formatCurrency(totalDebits) }}</span>
            </div>
            <div class="balance-item">
              <span class="label">Total Credits:</span>
              <span class="amount credit">{{ formatCurrency(totalCredits) }}</span>
            </div>
            <div class="balance-item" :class="{ 'balanced': isBalanced, 'unbalanced': !isBalanced }">
              <span class="label">Status:</span>
              <span class="status">
                <i :class="isBalanced ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
                {{ isBalanced ? 'Balanced' : 'Unbalanced' }}
              </span>
            </div>
          </div>
        </div>
        
        <div class="section-content">
          <!-- Lines Table -->
          <div class="lines-table-container">
            <table class="lines-table">
              <thead>
                <tr>
                  <th style="width: 40px">#</th>
                  <th style="width: 300px">Account</th>
                  <th style="width: 150px">Debit</th>
                  <th style="width: 150px">Credit</th>
                  <th>Description</th>
                  <th style="width: 80px">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in lines" :key="index" class="line-row">
                  <td class="line-number">{{ index + 1 }}</td>
                  <td class="account-cell">
                    <select
                      v-model="line.account_id"
                      class="line-select"
                      :class="{ 'error': lineErrors[index]?.account_id }"
                      @change="updateLineAccount(index, line.account_id)"
                    >
                      <option value="">Select Account</option>
                      <option v-for="account in accounts" :key="account.account_id" :value="account.account_id">
                        {{ account.account_code }} - {{ account.name }}
                      </option>
                    </select>
                    <span v-if="lineErrors[index]?.account_id" class="error-message small">
                      {{ lineErrors[index].account_id[0] }}
                    </span>
                  </td>
                  <td class="amount-cell">
                    <input
                      type="number"
                      v-model.number="line.debit_amount"
                      class="line-input amount-input"
                      :class="{ 'error': lineErrors[index]?.debit_amount }"
                      placeholder="0.00"
                      step="0.01"
                      min="0"
                      @input="updateDebitAmount(index, $event)"
                    />
                    <span v-if="lineErrors[index]?.debit_amount" class="error-message small">
                      {{ lineErrors[index].debit_amount[0] }}
                    </span>
                  </td>
                  <td class="amount-cell">
                    <input
                      type="number"
                      v-model.number="line.credit_amount"
                      class="line-input amount-input"
                      :class="{ 'error': lineErrors[index]?.credit_amount }"
                      placeholder="0.00"
                      step="0.01"
                      min="0"
                      @input="updateCreditAmount(index, $event)"
                    />
                    <span v-if="lineErrors[index]?.credit_amount" class="error-message small">
                      {{ lineErrors[index].credit_amount[0] }}
                    </span>
                  </td>
                  <td class="description-cell">
                    <input
                      type="text"
                      v-model="line.description"
                      class="line-input"
                      placeholder="Line description"
                    />
                  </td>
                  <td class="actions-cell">
                    <button
                      type="button"
                      @click="removeLine(index)"
                      class="btn-icon btn-delete"
                      title="Remove Line"
                      :disabled="lines.length <= 2"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Add Line Button -->
          <div class="add-line-section">
            <button type="button" @click="addLine" class="btn btn-outline">
              <i class="fas fa-plus"></i>
              Add Line
            </button>
            <button type="button" @click="addBalancingLine" class="btn btn-ghost" v-if="!isBalanced && lines.length > 0">
              <i class="fas fa-balance-scale"></i>
              Add Balancing Line
            </button>
          </div>

          <!-- Quick Templates -->
          <div class="templates-section">
            <h4>Quick Templates</h4>
            <div class="templates-grid">
              <button type="button" @click="applyTemplate('bank_deposit')" class="template-btn">
                <i class="fas fa-piggy-bank"></i>
                <span>Bank Deposit</span>
              </button>
              <button type="button" @click="applyTemplate('expense_payment')" class="template-btn">
                <i class="fas fa-receipt"></i>
                <span>Expense Payment</span>
              </button>
              <button type="button" @click="applyTemplate('asset_purchase')" class="template-btn">
                <i class="fas fa-laptop"></i>
                <span>Asset Purchase</span>
              </button>
              <button type="button" @click="applyTemplate('revenue_recognition')" class="template-btn">
                <i class="fas fa-chart-line"></i>
                <span>Revenue Recognition</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>

    <!-- Loading Overlay -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner"></div>
        <p>{{ isEdit ? 'Updating' : 'Creating' }} journal entry...</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'JournalEntryForm',
  props: {
    id: {
      type: [String, Number],
      default: null
    }
  },
  data() {
    return {
      loading: false,
      saving: false,
      periods: [],
      accounts: [],
      form: {
        journal_number: '',
        entry_date: new Date().toISOString().split('T')[0],
        reference_type: '',
        reference_id: '',
        description: '',
        period_id: '',
        status: 'Draft'
      },
      lines: [
        this.createEmptyLine(),
        this.createEmptyLine()
      ],
      errors: {},
      lineErrors: [],
      originalData: null
    }
  },
  computed: {
    isEdit() {
      return !!this.id
    },
    totalDebits() {
      return this.lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0)
    },
    totalCredits() {
      return this.lines.reduce((sum, line) => sum + (parseFloat(line.credit_amount) || 0), 0)
    },
    isBalanced() {
      const difference = Math.abs(this.totalDebits - this.totalCredits)
      return difference < 0.01 && this.totalDebits > 0
    }
  },
  async mounted() {
    await this.loadPeriods()
    await this.loadAccounts()
    
    if (this.isEdit) {
      await this.loadJournalEntry()
    } else {
      this.generateJournalNumber()
    }
  },
  methods: {
    createEmptyLine() {
      return {
        account_id: '',
        debit_amount: 0,
        credit_amount: 0,
        description: ''
      }
    },

    async loadPeriods() {
      try {
        const response = await axios.get('/accounting/accounting-periods')
        this.periods = response.data.data || response.data
      } catch (error) {
        this.$toast.error('Failed to load accounting periods')
      }
    },

    async loadAccounts() {
      try {
        const response = await axios.get('/accounting/chart-of-accounts')
        this.accounts = response.data.data || response.data
      } catch (error) {
        this.$toast.error('Failed to load chart of accounts')
      }
    },

    async loadJournalEntry() {
      this.loading = true
      try {
        const response = await axios.get(`/accounting/journal-entries/${this.id}`)
        const data = response.data.data
        
        this.form = {
          journal_number: data.journal_number,
          entry_date: data.entry_date,
          reference_type: data.reference_type || '',
          reference_id: data.reference_id || '',
          description: data.description || '',
          period_id: data.period_id,
          status: data.status
        }
        
        this.lines = data.journal_entry_lines.map(line => ({
          line_id: line.line_id,
          account_id: line.account_id,
          debit_amount: parseFloat(line.debit_amount) || 0,
          credit_amount: parseFloat(line.credit_amount) || 0,
          description: line.description || ''
        }))
        
        // Ensure at least 2 lines
        while (this.lines.length < 2) {
          this.lines.push(this.createEmptyLine())
        }
        
        this.originalData = JSON.parse(JSON.stringify({ form: this.form, lines: this.lines }))
        
      } catch (error) {
        this.$toast.error('Failed to load journal entry')
        this.$router.push('/accounting/journal-entries')
      } finally {
        this.loading = false
      }
    },

    async generateJournalNumber() {
      try {
        const today = new Date()
        const year = today.getFullYear()
        const month = String(today.getMonth() + 1).padStart(2, '0')
        const timestamp = Date.now().toString().slice(-6)
        
        this.form.journal_number = `JE-${year}${month}-${timestamp}`
      } catch (error) {
        console.error('Error generating journal number:', error)
      }
    },

    addLine() {
      this.lines.push(this.createEmptyLine())
    },

    removeLine(index) {
      if (this.lines.length > 2) {
        this.lines.splice(index, 1)
        this.lineErrors.splice(index, 1)
      }
    },

    addBalancingLine() {
      const difference = this.totalDebits - this.totalCredits
      if (Math.abs(difference) > 0.01) {
        const balancingLine = this.createEmptyLine()
        if (difference > 0) {
          balancingLine.credit_amount = Math.abs(difference)
        } else {
          balancingLine.debit_amount = Math.abs(difference)
        }
        balancingLine.description = 'Balancing entry'
        this.lines.push(balancingLine)
      }
    },

    updateLineAccount(index, accountId) {
      if (accountId) {
        const account = this.accounts.find(acc => acc.account_id == accountId)
        if (account && !this.lines[index].description) {
          this.lines[index].description = account.name
        }
      }
    },

    updateDebitAmount(index, event) {
      const amount = parseFloat(event.target.value) || 0
      if (amount > 0) {
        this.lines[index].credit_amount = 0
      }
    },

    updateCreditAmount(index, event) {
      const amount = parseFloat(event.target.value) || 0
      if (amount > 0) {
        this.lines[index].debit_amount = 0
      }
    },

    async saveDraft() {
      await this.save('Draft')
    },

    async saveAndPost() {
      if (!this.isBalanced) {
        this.$toast.error('Journal entry must be balanced before posting')
        return
      }
      await this.save('Posted')
    },

    async save(status) {
      this.saving = true
      this.errors = {}
      this.lineErrors = []

      try {
        const payload = {
          ...this.form,
          status,
          lines: this.lines.filter(line => 
            line.account_id && (line.debit_amount > 0 || line.credit_amount > 0)
          )
        }

        let response
        if (this.isEdit) {
          response = await axios.put(`/accounting/journal-entries/${this.id}`, payload)
        } else {
          response = await axios.post('/accounting/journal-entries', payload)
        }

        this.$toast.success(`Journal entry ${this.isEdit ? 'updated' : 'created'} successfully`)
        this.$router.push(`/accounting/journal-entries/${response.data.data.journal_id}`)
        
      } catch (error) {
        if (error.response?.status === 422) {
          this.errors = error.response.data.errors || {}
          
          if (error.response.data.line_errors) {
            this.lineErrors = error.response.data.line_errors
          }
          
          this.$toast.error('Please fix validation errors')
        } else {
          this.$toast.error(`Failed to ${this.isEdit ? 'update' : 'create'} journal entry`)
        }
        console.error('Save error:', error)
      } finally {
        this.saving = false
      }
    },

    resetForm() {
      if (this.isEdit && this.originalData) {
        this.form = JSON.parse(JSON.stringify(this.originalData.form))
        this.lines = JSON.parse(JSON.stringify(this.originalData.lines))
      } else {
        this.form = {
          journal_number: '',
          entry_date: new Date().toISOString().split('T')[0],
          reference_type: '',
          reference_id: '',
          description: '',
          period_id: '',
          status: 'Draft'
        }
        this.lines = [
          this.createEmptyLine(),
          this.createEmptyLine()
        ]
        this.generateJournalNumber()
      }
      this.errors = {}
      this.lineErrors = []
    },

    applyTemplate(templateType) {
      switch (templateType) {
        case 'bank_deposit':
          this.lines = [
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Bank Deposit' },
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Revenue/Income' }
          ]
          break
        case 'expense_payment':
          this.lines = [
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Expense Account' },
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Bank/Cash Payment' }
          ]
          break
        case 'asset_purchase':
          this.lines = [
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Asset Account' },
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Bank/Cash Payment' }
          ]
          break
        case 'revenue_recognition':
          this.lines = [
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Accounts Receivable' },
            { account_id: '', debit_amount: 0, credit_amount: 0, description: 'Revenue Account' }
          ]
          break
      }
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
    }
  }
}
</script>

<style scoped>
.journal-entry-form-container {
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

/* Balance Alert */
.balance-alert {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
  border: 2px solid rgba(245, 158, 11, 0.3);
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.alert-content {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.alert-content i {
  color: #d97706;
  font-size: 1.5rem;
  margin-top: 0.25rem;
}

.alert-content h4 {
  color: #92400e;
  margin: 0 0 0.5rem 0;
  font-size: 1.1rem;
}

.alert-content p {
  color: #a16207;
  margin: 0 0 0.25rem 0;
}

.difference {
  font-weight: 600;
  color: #92400e;
}

/* Form Sections */
.form-section {
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
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-header h3 i {
  color: #6366f1;
}

.balance-summary {
  display: flex;
  gap: 2rem;
  align-items: center;
}

.balance-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.balance-item .label {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.balance-item .amount {
  font-weight: 600;
  font-family: 'Courier New', monospace;
}

.balance-item .amount.debit {
  color: #dc2626;
}

.balance-item .amount.credit {
  color: #059669;
}

.balance-item.balanced .status {
  color: #059669;
}

.balance-item.unbalanced .status {
  color: #dc2626;
}

.balance-item .status {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-weight: 600;
  font-size: 0.875rem;
}

.section-content {
  padding: 1.5rem;
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.form-label.required::after {
  content: ' *';
  color: #dc2626;
}

.form-input, .form-select, .form-textarea {
  padding: 0.75rem;
  border: 2px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: white;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input.error, .form-select.error, .form-textarea.error {
  border-color: #dc2626;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.error-message {
  color: #dc2626;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.error-message.small {
  font-size: 0.7rem;
}

/* Lines Table */
.lines-table-container {
  overflow-x: auto;
  border: 2px solid var(--border-color);
  border-radius: 12px;
  margin-bottom: 1rem;
}

.lines-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
}

.lines-table th {
  background: var(--bg-tertiary);
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 2px solid var(--border-color);
  white-space: nowrap;
}

.lines-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: top;
}

.line-row:hover {
  background: var(--bg-tertiary);
}

.line-number {
  text-align: center;
  font-weight: 600;
  color: var(--text-secondary);
}

.line-select, .line-input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  font-size: 0.8rem;
  background: white;
}

.line-select:focus, .line-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.line-select.error, .line-input.error {
  border-color: #dc2626;
}

.amount-input {
  text-align: right;
  font-family: 'Courier New', monospace;
}

.account-cell, .amount-cell, .description-cell {
  position: relative;
}

.actions-cell {
  text-align: center;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-delete {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.btn-delete:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.2);
  transform: scale(1.05);
}

.btn-delete:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Add Line Section */
.add-line-section {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-bottom: 1.5rem;
}

/* Templates Section */
.templates-section {
  border-top: 1px solid var(--border-color);
  padding-top: 1.5rem;
}

.templates-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.templates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0.75rem;
}

.template-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  background: var(--bg-tertiary);
  border: 2px solid var(--border-color);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}

.template-btn:hover {
  border-color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
  transform: translateY(-2px);
}

.template-btn i {
  font-size: 1.5rem;
  color: #6366f1;
}

.template-btn span {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
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
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .journal-entry-form-container {
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
  
  .balance-summary {
    flex-direction: column;
    gap: 0.75rem;
    align-items: flex-start;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .lines-table-container {
    font-size: 0.8rem;
  }
  
  .templates-grid {
    grid-template-columns: 1fr;
  }
  
  .add-line-section {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>