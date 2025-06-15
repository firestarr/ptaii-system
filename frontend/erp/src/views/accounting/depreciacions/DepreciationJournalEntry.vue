<!-- src/views/accounting/DepreciationJournalEntry.vue -->
<template>
  <AppLayout>
    <div class="journal-entry-page">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-book"></i>
              Depreciation Journal Entry
            </h1>
            <p class="page-subtitle">View and manage journal entries for asset depreciation</p>
          </div>
          <div class="header-actions">
            <router-link to="/accounting/asset-depreciations" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i>
              Back to Depreciations
            </router-link>
            <button
              v-if="journalEntry && journalEntry.status === 'Draft'"
              @click="postJournalEntry"
              class="btn btn-success"
              :disabled="posting"
            >
              <i v-if="posting" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-check"></i>
              {{ posting ? 'Posting...' : 'Post Entry' }}
            </button>
            <button
              v-if="journalEntry"
              @click="printJournalEntry"
              class="btn btn-primary"
            >
              <i class="fas fa-print"></i>
              Print Entry
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner"></div>
        <p>Loading journal entry...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-container">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Error Loading Journal Entry</h3>
        <p>{{ error }}</p>
        <button @click="loadJournalEntry" class="btn btn-primary">
          <i class="fas fa-refresh"></i>
          Retry
        </button>
      </div>

      <!-- Journal Entry Content -->
      <div v-else-if="journalEntry" class="journal-content">
        <!-- Journal Entry Header -->
        <div class="journal-header-section">
          <div class="journal-card">
            <div class="journal-info-header">
              <div class="journal-main-info">
                <h2>{{ journalEntry.journal_number }}</h2>
                <div class="journal-meta">
                  <span class="entry-date">{{ formatDate(journalEntry.entry_date) }}</span>
                  <span :class="['status-badge', journalEntry.status.toLowerCase()]">
                    {{ journalEntry.status }}
                  </span>
                </div>
              </div>
              <div class="journal-actions">
                <div class="action-menu">
                  <button class="action-btn" @click="showActionsMenu = !showActionsMenu">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div v-if="showActionsMenu" class="actions-dropdown">
                    <button @click="editJournalEntry" class="dropdown-item">
                      <i class="fas fa-edit"></i>
                      Edit Entry
                    </button>
                    <button @click="duplicateJournalEntry" class="dropdown-item">
                      <i class="fas fa-copy"></i>
                      Duplicate Entry
                    </button>
                    <button @click="exportJournalEntry" class="dropdown-item">
                      <i class="fas fa-download"></i>
                      Export
                    </button>
                    <hr class="dropdown-divider">
                    <button
                      v-if="journalEntry.status === 'Posted'"
                      @click="reverseJournalEntry"
                      class="dropdown-item danger"
                    >
                      <i class="fas fa-undo"></i>
                      Reverse Entry
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="journal-description">
              <div class="description-content">
                <i class="fas fa-info-circle"></i>
                <span>{{ journalEntry.description }}</span>
              </div>
            </div>

            <div class="journal-details-grid">
              <div class="detail-card">
                <div class="detail-icon">
                  <i class="fas fa-calendar"></i>
                </div>
                <div class="detail-content">
                  <h4>Entry Date</h4>
                  <p>{{ formatDate(journalEntry.entry_date) }}</p>
                </div>
              </div>

              <div class="detail-card">
                <div class="detail-icon">
                  <i class="fas fa-hashtag"></i>
                </div>
                <div class="detail-content">
                  <h4>Journal Number</h4>
                  <p>{{ journalEntry.journal_number }}</p>
                </div>
              </div>

              <div class="detail-card">
                <div class="detail-icon">
                  <i class="fas fa-clock"></i>
                </div>
                <div class="detail-content">
                  <h4>Period</h4>
                  <p>{{ journalEntry.accounting_period?.period_name || 'N/A' }}</p>
                </div>
              </div>

              <div class="detail-card">
                <div class="detail-icon">
                  <i class="fas fa-link"></i>
                </div>
                <div class="detail-content">
                  <h4>Reference</h4>
                  <p>{{ journalEntry.reference_type }} #{{ journalEntry.reference_id }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Depreciation Context -->
        <div v-if="depreciation" class="depreciation-context">
          <div class="context-card">
            <div class="context-header">
              <h3>
                <i class="fas fa-cube"></i>
                Related Asset Depreciation
              </h3>
              <router-link
                :to="`/accounting/asset-depreciations/${depreciation.depreciation_id}`"
                class="view-link"
              >
                <i class="fas fa-external-link-alt"></i>
                View Details
              </router-link>
            </div>

            <div class="asset-summary">
              <div class="asset-info">
                <div class="asset-main">
                  <h4>{{ depreciation.fixed_asset?.name }}</h4>
                  <span class="asset-code">{{ depreciation.fixed_asset?.asset_code }}</span>
                </div>
                <div class="asset-amounts">
                  <div class="amount-item">
                    <span class="label">Depreciation Amount:</span>
                    <span class="value">${{ formatCurrency(depreciation.depreciation_amount) }}</span>
                  </div>
                  <div class="amount-item">
                    <span class="label">Accumulated Depreciation:</span>
                    <span class="value">${{ formatCurrency(depreciation.accumulated_depreciation) }}</span>
                  </div>
                  <div class="amount-item">
                    <span class="label">Remaining Value:</span>
                    <span class="value">${{ formatCurrency(depreciation.remaining_value) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Journal Entry Lines -->
        <div class="journal-lines-section">
          <div class="lines-header">
            <h3>
              <i class="fas fa-list"></i>
              Journal Entry Lines
            </h3>
            <div class="lines-summary">
              <div class="summary-item">
                <span class="summary-label">Total Debits:</span>
                <span class="summary-value debit">${{ formatCurrency(totalDebits) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Total Credits:</span>
                <span class="summary-value credit">${{ formatCurrency(totalCredits) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Balance:</span>
                <span :class="['summary-value', isBalanced ? 'balanced' : 'unbalanced']">
                  {{ isBalanced ? 'Balanced' : 'Unbalanced' }}
                </span>
              </div>
            </div>
          </div>

          <div class="journal-lines-table">
            <table class="lines-table">
              <thead>
                <tr>
                  <th>Account</th>
                  <th>Description</th>
                  <th class="amount-col">Debit</th>
                  <th class="amount-col">Credit</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="line in journalEntry.journal_entry_lines"
                  :key="line.line_id"
                  :class="['line-row', getLineType(line)]"
                >
                  <td class="account-cell">
                    <div class="account-info">
                      <div class="account-code">{{ line.chart_of_account?.account_code }}</div>
                      <div class="account-name">{{ line.chart_of_account?.account_name }}</div>
                      <div class="account-type">{{ line.chart_of_account?.account_type }}</div>
                    </div>
                  </td>
                  <td class="description-cell">
                    <div class="line-description">
                      {{ line.description }}
                    </div>
                  </td>
                  <td class="amount-cell debit">
                    <span v-if="line.debit_amount > 0" class="amount">
                      ${{ formatCurrency(line.debit_amount) }}
                    </span>
                    <span v-else class="amount-empty">-</span>
                  </td>
                  <td class="amount-cell credit">
                    <span v-if="line.credit_amount > 0" class="amount">
                      ${{ formatCurrency(line.credit_amount) }}
                    </span>
                    <span v-else class="amount-empty">-</span>
                  </td>
                  <td class="actions-cell">
                    <div class="line-actions">
                      <button
                        @click="viewAccountDetails(line.chart_of_account)"
                        class="action-btn view"
                        title="View Account"
                      >
                        <i class="fas fa-eye"></i>
                      </button>
                      <button
                        @click="viewAccountLedger(line.chart_of_account)"
                        class="action-btn ledger"
                        title="Account Ledger"
                      >
                        <i class="fas fa-book-open"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="totals-row">
                  <td colspan="2" class="totals-label">
                    <strong>Totals:</strong>
                  </td>
                  <td class="amount-cell total-debit">
                    <strong>${{ formatCurrency(totalDebits) }}</strong>
                  </td>
                  <td class="amount-cell total-credit">
                    <strong>${{ formatCurrency(totalCredits) }}</strong>
                  </td>
                  <td class="actions-cell">
                    <div class="balance-indicator">
                      <i :class="['fas', isBalanced ? 'fa-check-circle' : 'fa-exclamation-triangle']"></i>
                      <span>{{ isBalanced ? 'Balanced' : 'Unbalanced' }}</span>
                    </div>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Audit Trail -->
        <div class="audit-trail-section">
          <div class="audit-header">
            <h3>
              <i class="fas fa-history"></i>
              Audit Trail
            </h3>
            <button @click="refreshAuditTrail" class="btn btn-sm">
              <i class="fas fa-refresh"></i>
              Refresh
            </button>
          </div>

          <div class="audit-timeline">
            <div
              v-for="event in auditTrail"
              :key="event.id"
              class="timeline-item"
            >
              <div class="timeline-marker">
                <i :class="getAuditIcon(event.action)"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <span class="action-type">{{ event.action }}</span>
                  <span class="action-time">{{ formatDateTime(event.created_at) }}</span>
                </div>
                <div class="timeline-details">
                  <span class="user-info">by {{ event.user_name || 'System' }}</span>
                  <p v-if="event.description" class="event-description">{{ event.description }}</p>
                  <div v-if="event.changes" class="changes-summary">
                    <details>
                      <summary>View Changes</summary>
                      <pre>{{ JSON.stringify(event.changes, null, 2) }}</pre>
                    </details>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Related Entries -->
        <div v-if="relatedEntries.length > 0" class="related-entries-section">
          <div class="related-header">
            <h3>
              <i class="fas fa-link"></i>
              Related Journal Entries
            </h3>
          </div>

          <div class="related-entries-grid">
            <div
              v-for="entry in relatedEntries"
              :key="entry.journal_id"
              class="related-entry-card"
              @click="viewRelatedEntry(entry)"
            >
              <div class="entry-header">
                <div class="entry-number">{{ entry.journal_number }}</div>
                <span :class="['status-badge', entry.status.toLowerCase()]">
                  {{ entry.status }}
                </span>
              </div>
              <div class="entry-details">
                <div class="entry-date">{{ formatDate(entry.entry_date) }}</div>
                <div class="entry-description">{{ entry.description }}</div>
                <div class="entry-amount">
                  Total: ${{ formatCurrency(getEntryTotal(entry)) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No Journal Entry State -->
      <div v-else class="no-entry-state">
        <div class="no-entry-icon">
          <i class="fas fa-book-open"></i>
        </div>
        <h3>No Journal Entry Found</h3>
        <p>This depreciation does not have an associated journal entry.</p>
        <button @click="createJournalEntry" class="btn btn-primary">
          <i class="fas fa-plus"></i>
          Create Journal Entry
        </button>
      </div>

      <!-- Create Entry Modal -->
      <div v-if="showCreateModal" class="modal-overlay" @click="closeCreateModal">
        <div class="modal-content create-modal" @click.stop>
          <div class="modal-header">
            <h3>Create Journal Entry</h3>
            <button @click="closeCreateModal" class="close-btn">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="create-form">
              <div class="form-group">
                <label for="journalNumber">Journal Number</label>
                <input
                  id="journalNumber"
                  v-model="createForm.journal_number"
                  type="text"
                  class="form-input"
                  placeholder="Auto-generated"
                  readonly
                />
              </div>

              <div class="form-group">
                <label for="entryDate">Entry Date</label>
                <input
                  id="entryDate"
                  v-model="createForm.entry_date"
                  type="date"
                  class="form-input"
                  required
                />
              </div>

              <div class="form-group">
                <label for="description">Description</label>
                <textarea
                  id="description"
                  v-model="createForm.description"
                  class="form-textarea"
                  rows="3"
                  placeholder="Enter journal entry description..."
                  required
                ></textarea>
              </div>

              <div class="accounts-section">
                <h4>Journal Entry Lines</h4>
                
                <div class="account-line debit-line">
                  <h5>Debit Entry</h5>
                  <div class="line-form">
                    <div class="form-group">
                      <label>Account</label>
                      <select v-model="createForm.debit_account_id" class="form-select" required>
                        <option value="">Select Account</option>
                        <option
                          v-for="account in expenseAccounts"
                          :key="account.account_id"
                          :value="account.account_id"
                        >
                          {{ account.account_code }} - {{ account.account_name }}
                        </option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Amount</label>
                      <input
                        v-model="createForm.amount"
                        type="number"
                        step="0.01"
                        class="form-input"
                        placeholder="0.00"
                        required
                      />
                    </div>
                  </div>
                </div>

                <div class="account-line credit-line">
                  <h5>Credit Entry</h5>
                  <div class="line-form">
                    <div class="form-group">
                      <label>Account</label>
                      <select v-model="createForm.credit_account_id" class="form-select" required>
                        <option value="">Select Account</option>
                        <option
                          v-for="account in accumulatedAccounts"
                          :key="account.account_id"
                          :value="account.account_id"
                        >
                          {{ account.account_code }} - {{ account.account_name }}
                        </option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Amount</label>
                      <input
                        :value="createForm.amount"
                        type="number"
                        step="0.01"
                        class="form-input"
                        readonly
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="closeCreateModal" class="btn btn-secondary">Cancel</button>
            <button
              @click="submitCreateEntry"
              class="btn btn-primary"
              :disabled="creating || !isCreateFormValid"
            >
              <i v-if="creating" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-save"></i>
              {{ creating ? 'Creating...' : 'Create Entry' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Confirmation Modals -->
      <div v-if="showConfirmModal" class="modal-overlay" @click="closeConfirmModal">
        <div class="modal-content confirm-modal" @click.stop>
          <div class="modal-header">
            <div class="confirm-icon">
              <i :class="confirmModal.icon"></i>
            </div>
            <h3>{{ confirmModal.title }}</h3>
          </div>
          <div class="modal-body">
            <p>{{ confirmModal.message }}</p>
            <div v-if="confirmModal.warning" class="warning-note">
              <i class="fas fa-exclamation-triangle"></i>
              {{ confirmModal.warning }}
            </div>
          </div>
          <div class="modal-footer">
            <button @click="closeConfirmModal" class="btn btn-secondary">Cancel</button>
            <button
              @click="confirmModal.action"
              :class="['btn', confirmModal.type === 'danger' ? 'btn-danger' : 'btn-primary']"
              :disabled="confirmModal.processing"
            >
              <i v-if="confirmModal.processing" class="fas fa-spinner fa-spin"></i>
              <i v-else :class="confirmModal.icon"></i>
              {{ confirmModal.processing ? 'Processing...' : confirmModal.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'DepreciationJournalEntry',
  components: {
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    
    // Reactive state
    const loading = ref(false)
    const posting = ref(false)
    const creating = ref(false)
    const error = ref('')
    const journalEntry = ref(null)
    const depreciation = ref(null)
    const auditTrail = ref([])
    const relatedEntries = ref([])
    const accounts = ref([])
    
    // UI state
    const showActionsMenu = ref(false)
    const showCreateModal = ref(false)
    const showConfirmModal = ref(false)
    
    // Form data
    const createForm = ref({
      journal_number: '',
      entry_date: new Date().toISOString().split('T')[0],
      description: '',
      amount: 0,
      debit_account_id: '',
      credit_account_id: ''
    })
    
    const confirmModal = ref({
      title: '',
      message: '',
      warning: '',
      icon: '',
      type: 'primary',
      confirmText: '',
      action: null,
      processing: false
    })

    // Computed properties
    const totalDebits = computed(() => {
      if (!journalEntry.value?.journal_entry_lines) return 0
      return journalEntry.value.journal_entry_lines.reduce(
        (sum, line) => sum + (line.debit_amount || 0), 0
      )
    })

    const totalCredits = computed(() => {
      if (!journalEntry.value?.journal_entry_lines) return 0
      return journalEntry.value.journal_entry_lines.reduce(
        (sum, line) => sum + (line.credit_amount || 0), 0
      )
    })

    const isBalanced = computed(() => {
      return Math.abs(totalDebits.value - totalCredits.value) < 0.01
    })

    const expenseAccounts = computed(() => {
      return accounts.value.filter(account => 
        account.account_type === 'Expense' || 
        account.account_name.toLowerCase().includes('depreciation expense')
      )
    })

    const accumulatedAccounts = computed(() => {
      return accounts.value.filter(account => 
        account.account_type === 'Asset' && 
        account.account_name.toLowerCase().includes('accumulated depreciation')
      )
    })

    const isCreateFormValid = computed(() => {
      return createForm.value.entry_date &&
             createForm.value.description &&
             createForm.value.amount > 0 &&
             createForm.value.debit_account_id &&
             createForm.value.credit_account_id
    })

    // Methods
    const loadJournalEntry = async () => {
      try {
        loading.value = true
        error.value = ''
        
        const depreciationId = route.params.depreciationId
        
        // Load depreciation details first
        const depResponse = await axios.get(`/accounting/asset-depreciations/${depreciationId}`)
        depreciation.value = depResponse.data.data
        
        // Try to load associated journal entry
        try {
          const journalResponse = await axios.get(
            `/accounting/journal-entries?reference_type=AssetDepreciation&reference_id=${depreciationId}`
          )
          
          const entries = journalResponse.data.data || journalResponse.data
          if (entries.length > 0) {
            const entryId = entries[0].journal_id
            const entryResponse = await axios.get(`/accounting/journal-entries/${entryId}`)
            journalEntry.value = entryResponse.data.data
            
            // Load audit trail and related entries
            await Promise.all([
              loadAuditTrail(entryId),
              loadRelatedEntries(entryId)
            ])
          }
        } catch (journalError) {
          console.log('No journal entry found for this depreciation')
        }
        
      } catch (err) {
        console.error('Error loading data:', err)
        error.value = err.response?.data?.message || 'Failed to load journal entry data'
      } finally {
        loading.value = false
      }
    }

    const loadAuditTrail = async (journalId) => {
      try {
        const response = await axios.get(`/accounting/journal-entries/${journalId}/audit-trail`)
        auditTrail.value = response.data.data || []
      } catch (err) {
        console.error('Error loading audit trail:', err)
      }
    }

    const loadRelatedEntries = async (journalId) => {
      try {
        const response = await axios.get(`/accounting/journal-entries/${journalId}/related`)
        relatedEntries.value = response.data.data || []
      } catch (err) {
        console.error('Error loading related entries:', err)
      }
    }

    const loadAccounts = async () => {
      try {
        const response = await axios.get('/accounting/chart-of-accounts')
        accounts.value = response.data.data || response.data
      } catch (err) {
        console.error('Error loading accounts:', err)
      }
    }

    const getLineType = (line) => {
      if (line.debit_amount > 0) return 'debit-line'
      if (line.credit_amount > 0) return 'credit-line'
      return ''
    }

    const getAuditIcon = (action) => {
      const icons = {
        'Created': 'fas fa-plus-circle',
        'Updated': 'fas fa-edit',
        'Posted': 'fas fa-check-circle',
        'Reversed': 'fas fa-undo',
        'Deleted': 'fas fa-trash'
      }
      return icons[action] || 'fas fa-info-circle'
    }

    const getEntryTotal = (entry) => {
      if (!entry.journal_entry_lines) return 0
      return entry.journal_entry_lines.reduce(
        (sum, line) => sum + Math.max(line.debit_amount || 0, line.credit_amount || 0), 0
      )
    }

    const postJournalEntry = () => {
      showConfirmation({
        title: 'Post Journal Entry',
        message: 'Are you sure you want to post this journal entry? Once posted, it cannot be modified.',
        warning: 'This action cannot be undone.',
        icon: 'fas fa-check-circle',
        type: 'primary',
        confirmText: 'Post Entry',
        action: performPostEntry
      })
    }

    const performPostEntry = async () => {
      try {
        confirmModal.value.processing = true
        
        await axios.post(`/accounting/journal-entries/${journalEntry.value.journal_id}/post`)
        
        journalEntry.value.status = 'Posted'
        await loadAuditTrail(journalEntry.value.journal_id)
        
        closeConfirmModal()
      } catch (err) {
        console.error('Error posting entry:', err)
        error.value = err.response?.data?.message || 'Failed to post journal entry'
      } finally {
        confirmModal.value.processing = false
      }
    }

    const reverseJournalEntry = () => {
      showConfirmation({
        title: 'Reverse Journal Entry',
        message: 'This will create a reversing journal entry to cancel out this entry.',
        warning: 'This action will create a new journal entry with opposite amounts.',
        icon: 'fas fa-undo',
        type: 'danger',
        confirmText: 'Reverse Entry',
        action: performReverseEntry
      })
    }

    const performReverseEntry = async () => {
      try {
        confirmModal.value.processing = true
        
        const response = await axios.post(`/accounting/journal-entries/${journalEntry.value.journal_id}/reverse`)
        
        // Redirect to the new reversing entry
        router.push(`/accounting/journal-entries/${response.data.data.journal_id}`)
        
        closeConfirmModal()
      } catch (err) {
        console.error('Error reversing entry:', err)
        error.value = err.response?.data?.message || 'Failed to reverse journal entry'
      } finally {
        confirmModal.value.processing = false
      }
    }

    const createJournalEntry = () => {
      if (!depreciation.value) return
      
      createForm.value = {
        journal_number: `DEPR-${Date.now()}`,
        entry_date: new Date().toISOString().split('T')[0],
        description: `Depreciation for ${depreciation.value.fixed_asset?.name}`,
        amount: depreciation.value.depreciation_amount,
        debit_account_id: '',
        credit_account_id: ''
      }
      
      showCreateModal.value = true
    }

    const submitCreateEntry = async () => {
      try {
        creating.value = true
        
        const entryData = {
          journal_number: createForm.value.journal_number,
          entry_date: createForm.value.entry_date,
          description: createForm.value.description,
          reference_type: 'AssetDepreciation',
          reference_id: depreciation.value.depreciation_id,
          period_id: depreciation.value.period_id,
          status: 'Draft',
          journal_entry_lines: [
            {
              account_id: createForm.value.debit_account_id,
              debit_amount: createForm.value.amount,
              credit_amount: 0,
              description: `Depreciation expense for ${depreciation.value.fixed_asset?.name}`
            },
            {
              account_id: createForm.value.credit_account_id,
              debit_amount: 0,
              credit_amount: createForm.value.amount,
              description: `Accumulated depreciation for ${depreciation.value.fixed_asset?.name}`
            }
          ]
        }
        
        const response = await axios.post('/accounting/journal-entries', entryData)
        journalEntry.value = response.data.data
        
        closeCreateModal()
        await loadAuditTrail(journalEntry.value.journal_id)
        
      } catch (err) {
        console.error('Error creating entry:', err)
        error.value = err.response?.data?.message || 'Failed to create journal entry'
      } finally {
        creating.value = false
      }
    }

    const editJournalEntry = () => {
      router.push(`/accounting/journal-entries/${journalEntry.value.journal_id}/edit`)
    }

    const duplicateJournalEntry = () => {
      router.push(`/accounting/journal-entries/${journalEntry.value.journal_id}/duplicate`)
    }

    const exportJournalEntry = () => {
      window.open(`/api/accounting/journal-entries/${journalEntry.value.journal_id}/export`, '_blank')
    }

    const printJournalEntry = () => {
      window.print()
    }

    const viewAccountDetails = (account) => {
      router.push(`/accounting/chart-of-accounts/${account.account_id}`)
    }

    const viewAccountLedger = (account) => {
      router.push(`/accounting/general-ledger?account_id=${account.account_id}`)
    }

    const viewRelatedEntry = (entry) => {
      router.push(`/accounting/journal-entries/${entry.journal_id}`)
    }

    const refreshAuditTrail = () => {
      if (journalEntry.value) {
        loadAuditTrail(journalEntry.value.journal_id)
      }
    }

    const showConfirmation = (options) => {
      confirmModal.value = { ...options, processing: false }
      showConfirmModal.value = true
    }

    const closeConfirmModal = () => {
      showConfirmModal.value = false
      confirmModal.value = {
        title: '',
        message: '',
        warning: '',
        icon: '',
        type: 'primary',
        confirmText: '',
        action: null,
        processing: false
      }
    }

    const closeCreateModal = () => {
      showCreateModal.value = false
      createForm.value = {
        journal_number: '',
        entry_date: new Date().toISOString().split('T')[0],
        description: '',
        amount: 0,
        debit_account_id: '',
        credit_account_id: ''
      }
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount || 0)
    }

    const formatDate = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }

    const formatDateTime = (datetime) => {
      return new Date(datetime).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    // Click outside handler for actions menu
    const handleClickOutside = (event) => {
      if (!event.target.closest('.action-menu')) {
        showActionsMenu.value = false
      }
    }

    // Lifecycle
    onMounted(() => {
      Promise.all([
        loadJournalEntry(),
        loadAccounts()
      ])
      
      document.addEventListener('click', handleClickOutside)
    })

    onUnmounted(() => {
      document.removeEventListener('click', handleClickOutside)
    })

    return {
      loading,
      posting,
      creating,
      error,
      journalEntry,
      depreciation,
      auditTrail,
      relatedEntries,
      accounts,
      showActionsMenu,
      showCreateModal,
      showConfirmModal,
      createForm,
      confirmModal,
      totalDebits,
      totalCredits,
      isBalanced,
      expenseAccounts,
      accumulatedAccounts,
      isCreateFormValid,
      loadJournalEntry,
      getLineType,
      getAuditIcon,
      getEntryTotal,
      postJournalEntry,
      reverseJournalEntry,
      createJournalEntry,
      submitCreateEntry,
      editJournalEntry,
      duplicateJournalEntry,
      exportJournalEntry,
      printJournalEntry,
      viewAccountDetails,
      viewAccountLedger,
      viewRelatedEntry,
      refreshAuditTrail,
      closeConfirmModal,
      closeCreateModal,
      formatCurrency,
      formatDate,
      formatDateTime
    }
  }
}
</script>

<style scoped>
.journal-entry-page {
  padding: 2rem;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  min-height: 100vh;
}

/* Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  padding: 2rem;
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.title-section h1 {
  font-size: 2.5rem;
  font-weight: 700;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 0.5rem;
}

.title-section i {
  margin-right: 1rem;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

/* Journal Content */
.journal-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Journal Header Section */
.journal-header-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.journal-info-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.journal-main-info h2 {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.journal-meta {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.entry-date {
  color: #6b7280;
  font-size: 0.95rem;
  font-weight: 500;
}

.status-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
}

.status-badge.draft {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.posted {
  background: #dcfce7;
  color: #166534;
}

.status-badge.reversed {
  background: #fee2e2;
  color: #dc2626;
}

/* Action Menu */
.action-menu {
  position: relative;
}

.action-btn {
  width: 40px;
  height: 40px;
  border: 2px solid #e2e8f0;
  background: white;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  color: #6b7280;
}

.action-btn:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.actions-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  z-index: 100;
  min-width: 200px;
  padding: 0.5rem 0;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.75rem 1rem;
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  transition: background-color 0.2s ease;
  color: #374151;
}

.dropdown-item:hover {
  background: #f3f4f6;
}

.dropdown-item.danger {
  color: #dc2626;
}

.dropdown-item.danger:hover {
  background: #fef2f2;
}

.dropdown-divider {
  margin: 0.5rem 0;
  border: none;
  border-top: 1px solid #e2e8f0;
}

/* Journal Description */
.journal-description {
  margin-bottom: 2rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.description-content {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: #374151;
  font-style: italic;
}

.description-content i {
  color: #6366f1;
}

/* Journal Details Grid */
.journal-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.detail-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: #fafbfc;
  transition: transform 0.3s ease;
}

.detail-card:hover {
  transform: translateY(-3px);
}

.detail-icon {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.detail-content h4 {
  font-size: 0.9rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
  font-weight: 500;
}

.detail-content p {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

/* Depreciation Context */
.depreciation-context {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border-radius: 20px;
  padding: 2rem;
  border: 2px solid #bbf7d0;
}

.context-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.context-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
}

.context-header i {
  margin-right: 0.5rem;
  color: #10b981;
}

.view-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #10b981;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s ease;
}

.view-link:hover {
  color: #059669;
}

.asset-summary {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #bbf7d0;
}

.asset-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.asset-main h4 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.asset-code {
  background: #dbeafe;
  color: #1d4ed8;
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
}

.asset-amounts {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  text-align: right;
}

.amount-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.amount-item .label {
  color: #6b7280;
  font-weight: 500;
}

.amount-item .value {
  font-weight: 600;
  color: #10b981;
}

/* Journal Lines Section */
.journal-lines-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.lines-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.lines-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
}

.lines-header i {
  margin-right: 0.5rem;
  color: #6366f1;
}

.lines-summary {
  display: flex;
  gap: 2rem;
}

.summary-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.summary-label {
  font-size: 0.85rem;
  color: #6b7280;
  font-weight: 500;
}

.summary-value {
  font-size: 1.25rem;
  font-weight: 700;
}

.summary-value.debit {
  color: #dc2626;
}

.summary-value.credit {
  color: #059669;
}

.summary-value.balanced {
  color: #10b981;
}

.summary-value.unbalanced {
  color: #dc2626;
}

/* Journal Lines Table */
.journal-lines-table {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.lines-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.lines-table th {
  background: #f8fafc;
  color: #374151;
  font-weight: 600;
  padding: 1rem;
  text-align: left;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.9rem;
}

.lines-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.amount-col {
  text-align: right;
  width: 120px;
}

.line-row {
  transition: background-color 0.2s ease;
}

.line-row:hover {
  background: #f8fafc;
}

.line-row.debit-line {
  border-left: 4px solid #dc2626;
}

.line-row.credit-line {
  border-left: 4px solid #059669;
}

.account-cell {
  min-width: 250px;
}

.account-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.account-code {
  font-weight: 600;
  color: #1f2937;
  font-size: 0.9rem;
}

.account-name {
  color: #374151;
  font-size: 0.95rem;
}

.account-type {
  color: #6b7280;
  font-size: 0.8rem;
  background: #f3f4f6;
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  width: fit-content;
}

.description-cell {
  min-width: 200px;
}

.line-description {
  color: #374151;
  font-style: italic;
}

.amount-cell {
  text-align: right;
  font-weight: 600;
}

.amount-cell.debit .amount {
  color: #dc2626;
}

.amount-cell.credit .amount {
  color: #059669;
}

.amount-empty {
  color: #9ca3af;
}

.line-actions {
  display: flex;
  gap: 0.5rem;
}

.action-btn.view {
  background: #dbeafe;
  color: #1d4ed8;
}

.action-btn.ledger {
  background: #fef3c7;
  color: #d97706;
}

.totals-row {
  background: #f8fafc;
  border-top: 2px solid #e2e8f0;
  font-weight: 600;
}

.totals-label {
  color: #374151;
}

.total-debit {
  color: #dc2626;
}

.total-credit {
  color: #059669;
}

.balance-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
}

.balance-indicator .fa-check-circle {
  color: #10b981;
}

.balance-indicator .fa-exclamation-triangle {
  color: #f59e0b;
}

/* Audit Trail */
.audit-trail-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.audit-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.audit-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
}

.audit-header i {
  margin-right: 0.5rem;
  color: #6366f1;
}

.audit-timeline {
  position: relative;
}

.audit-timeline::before {
  content: '';
  position: absolute;
  left: 20px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}

.timeline-item {
  position: relative;
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.timeline-marker {
  width: 40px;
  height: 40px;
  background: white;
  border: 3px solid #6366f1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6366f1;
  font-size: 0.9rem;
  flex-shrink: 0;
  z-index: 1;
}

.timeline-content {
  flex: 1;
  background: #f8fafc;
  border-radius: 12px;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.action-type {
  font-weight: 600;
  color: #1f2937;
}

.action-time {
  color: #6b7280;
  font-size: 0.85rem;
}

.timeline-details {
  color: #6b7280;
  font-size: 0.9rem;
}

.user-info {
  font-weight: 500;
  color: #374151;
}

.event-description {
  margin: 0.5rem 0;
  color: #6b7280;
}

.changes-summary {
  margin-top: 0.5rem;
}

.changes-summary details {
  cursor: pointer;
}

.changes-summary summary {
  color: #6366f1;
  font-weight: 500;
}

.changes-summary pre {
  background: #f3f4f6;
  padding: 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  overflow-x: auto;
  margin-top: 0.5rem;
}

/* Related Entries */
.related-entries-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.related-header {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.related-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
}

.related-header i {
  margin-right: 0.5rem;
  color: #6366f1;
}

.related-entries-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.related-entry-card {
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.3s ease;
  background: #fafbfc;
}

.related-entry-card:hover {
  transform: translateY(-3px);
  border-color: #6366f1;
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
}

.entry-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.entry-number {
  font-weight: 600;
  color: #1f2937;
}

.entry-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.entry-date {
  color: #6b7280;
  font-size: 0.85rem;
}

.entry-description {
  color: #374151;
  font-size: 0.9rem;
}

.entry-amount {
  font-weight: 600;
  color: #059669;
}

/* Button Styles */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 12px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
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
  background: #f8fafc;
  color: #374151;
  border: 2px solid #e2e8f0;
}

.btn-secondary:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.btn-success:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Loading & Error States */
.loading-container,
.error-container,
.no-entry-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  background: white;
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 3px solid #e5e7eb;
  border-top: 3px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-icon,
.no-entry-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
  font-size: 2rem;
}

.error-icon {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  color: white;
}

.no-entry-icon {
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
  color: #9ca3af;
}

.error-container h3,
.no-entry-state h3 {
  font-size: 1.5rem;
  color: #374151;
  margin-bottom: 0.5rem;
}

.error-container p,
.no-entry-state p {
  color: #6b7280;
  margin-bottom: 2rem;
  font-size: 1.1rem;
}

/* Modal Styles */
.modal-overlay {
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
  padding: 1rem;
}

.modal-content {
  background: white;
  border-radius: 20px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 1.5rem 0 1.5rem;
}

.modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
}

.close-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: #f3f4f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.3s ease;
}

.close-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 0 1.5rem 1.5rem 1.5rem;
}

/* Create Modal Specific */
.create-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 600;
  color: #374151;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.accounts-section {
  border-top: 2px solid #f1f5f9;
  padding-top: 1.5rem;
}

.accounts-section h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
}

.account-line {
  margin-bottom: 1.5rem;
  padding: 1rem;
  border-radius: 8px;
  border: 2px solid;
}

.debit-line {
  border-color: #fecaca;
  background: #fef2f2;
}

.credit-line {
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.account-line h5 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: #374151;
}

.line-form {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1rem;
}

/* Confirm Modal Specific */
.confirm-modal .modal-header {
  text-align: center;
  padding: 2rem 2rem 1rem 2rem;
}

.confirm-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  color: white;
  font-size: 2rem;
}

.confirm-modal .modal-body {
  text-align: center;
  padding: 1rem 2rem;
}

.confirm-modal .modal-body p {
  color: #374151;
  margin-bottom: 1rem;
  font-size: 1.1rem;
}

.warning-note {
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #92400e;
  font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .journal-entry-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .header-actions {
    flex-direction: column;
  }

  .journal-info-header {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .journal-details-grid {
    grid-template-columns: 1fr;
  }

  .asset-info {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .lines-header {
    flex-direction: column;
    gap: 1rem;
  }

  .lines-summary {
    flex-direction: column;
    gap: 1rem;
  }

  .related-entries-grid {
    grid-template-columns: 1fr;
  }

  .line-form {
    grid-template-columns: 1fr;
  }

  .modal-footer {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .journal-header-section,
  .journal-lines-section,
  .audit-trail-section {
    padding: 1rem;
  }

  .detail-card {
    flex-direction: column;
    text-align: center;
  }

  .lines-table {
    font-size: 0.8rem;
  }

  .lines-table th,
  .lines-table td {
    padding: 0.5rem;
  }

  .timeline-item {
    flex-direction: column;
    gap: 0.5rem;
  }

  .timeline-marker {
    align-self: flex-start;
  }
}

/* Print Styles */
@media print {
  .page-header,
  .header-actions,
  .action-menu,
  .line-actions,
  .audit-trail-section,
  .related-entries-section {
    display: none !important;
  }

  .journal-entry-page {
    padding: 0;
    background: white;
  }

  .journal-content {
    gap: 1rem;
  }

  .journal-header-section,
  .journal-lines-section {
    box-shadow: none;
    border: 1px solid #000;
    page-break-inside: avoid;
  }
}
</style>