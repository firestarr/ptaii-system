<!-- src/views/accounting/CalculateDepreciation.vue -->
<template>
  <AppLayout>
    <div class="calculate-depreciation-page">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-calculator"></i>
              Calculate Asset Depreciation
            </h1>
            <p class="page-subtitle">Calculate and record depreciation for fixed assets</p>
          </div>
          <div class="header-actions">
            <router-link to="/accounting/asset-depreciations" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i>
              Back to List
            </router-link>
          </div>
        </div>
      </div>

      <div class="form-layout">
        <!-- Asset Selection Section -->
        <div class="form-section">
          <div class="section-header">
            <h2>
              <i class="fas fa-cube"></i>
              Select Asset
            </h2>
            <p>Choose the asset for depreciation calculation</p>
          </div>

          <div class="asset-selection">
            <div class="search-section">
              <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input
                  v-model="assetSearch"
                  @input="filterAssets"
                  type="text"
                  placeholder="Search assets by name or code..."
                  class="form-input search-input"
                />
              </div>
              <div class="filters">
                <select v-model="categoryFilter" @change="filterAssets" class="form-select">
                  <option value="">All Categories</option>
                  <option v-for="category in assetCategories" :key="category" :value="category">
                    {{ category }}
                  </option>
                </select>
                <select v-model="statusFilter" @change="filterAssets" class="form-select">
                  <option value="">All Status</option>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>
            </div>

            <div v-if="loadingAssets" class="loading-state">
              <div class="loading-spinner"></div>
              <p>Loading assets...</p>
            </div>

            <div v-else-if="filteredAssets.length === 0" class="empty-assets">
              <div class="empty-icon">
                <i class="fas fa-cube"></i>
              </div>
              <h3>No assets found</h3>
              <p>Try adjusting your search criteria or create a new asset first</p>
            </div>

            <div v-else class="assets-grid">
              <div
                v-for="asset in filteredAssets"
                :key="asset.asset_id"
                :class="['asset-card', { selected: selectedAsset?.asset_id === asset.asset_id, inactive: asset.status !== 'Active' }]"
                @click="selectAsset(asset)"
              >
                <div class="asset-header">
                  <div class="asset-info">
                    <h3>{{ asset.name }}</h3>
                    <span class="asset-code">{{ asset.asset_code }}</span>
                  </div>
                  <div class="asset-status">
                    <span :class="['status-badge', asset.status.toLowerCase()]">
                      {{ asset.status }}
                    </span>
                  </div>
                </div>

                <div class="asset-details">
                  <div class="detail-row">
                    <span class="label">Category:</span>
                    <span class="value">{{ asset.category }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="label">Acquisition Cost:</span>
                    <span class="value amount">${{ formatCurrency(asset.acquisition_cost) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="label">Current Value:</span>
                    <span class="value amount">${{ formatCurrency(asset.current_value) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="label">Depreciation Rate:</span>
                    <span class="value">{{ asset.depreciation_rate }}%</span>
                  </div>
                </div>

                <div v-if="asset.status !== 'Active'" class="inactive-overlay">
                  <i class="fas fa-ban"></i>
                  <span>Inactive Asset</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calculation Form Section -->
        <div v-if="selectedAsset" class="form-section calculation-section">
          <div class="section-header">
            <h2>
              <i class="fas fa-chart-line"></i>
              Depreciation Calculation
            </h2>
            <p>Configure depreciation parameters and preview calculation</p>
          </div>

          <div class="calculation-form">
            <div class="form-row">
              <div class="form-group">
                <label for="periodSelect">
                  <i class="fas fa-calendar"></i>
                  Accounting Period *
                </label>
                <select
                  id="periodSelect"
                  v-model="form.period_id"
                  @change="validateAndCalculate"
                  class="form-select"
                  required
                >
                  <option value="">Select Period</option>
                  <option
                    v-for="period in periods"
                    :key="period.period_id"
                    :value="period.period_id"
                  >
                    {{ period.period_name }} ({{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }})
                  </option>
                </select>
                <div v-if="errors.period_id" class="error-message">
                  {{ errors.period_id }}
                </div>
              </div>

              <div class="form-group">
                <label for="depreciationDate">
                  <i class="fas fa-calendar-day"></i>
                  Depreciation Date *
                </label>
                <input
                  id="depreciationDate"
                  v-model="form.depreciation_date"
                  @change="validateAndCalculate"
                  type="date"
                  class="form-input"
                  required
                />
                <div v-if="errors.depreciation_date" class="error-message">
                  {{ errors.depreciation_date }}
                </div>
              </div>
            </div>

            <div class="journal-entry-section">
              <div class="section-toggle">
                <label class="toggle-label">
                  <input
                    v-model="form.create_journal_entry"
                    type="checkbox"
                    class="toggle-input"
                  />
                  <span class="toggle-slider"></span>
                  Create Journal Entry Automatically
                </label>
                <p class="toggle-description">
                  Automatically create journal entries for depreciation expense and accumulated depreciation
                </p>
              </div>

              <div v-if="form.create_journal_entry" class="journal-accounts">
                <div class="form-row">
                  <div class="form-group">
                    <label for="expenseAccount">
                      <i class="fas fa-minus-circle"></i>
                      Depreciation Expense Account *
                    </label>
                    <select
                      id="expenseAccount"
                      v-model="form.depreciation_expense_account_id"
                      class="form-select"
                      required
                    >
                      <option value="">Select Expense Account</option>
                      <option
                        v-for="account in expenseAccounts"
                        :key="account.account_id"
                        :value="account.account_id"
                      >
                        {{ account.account_code }} - {{ account.account_name }}
                      </option>
                    </select>
                    <div v-if="errors.depreciation_expense_account_id" class="error-message">
                      {{ errors.depreciation_expense_account_id }}
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="accumulatedAccount">
                      <i class="fas fa-plus-circle"></i>
                      Accumulated Depreciation Account *
                    </label>
                    <select
                      id="accumulatedAccount"
                      v-model="form.accumulated_depreciation_account_id"
                      class="form-select"
                      required
                    >
                      <option value="">Select Accumulated Account</option>
                      <option
                        v-for="account in accumulatedAccounts"
                        :key="account.account_id"
                        :value="account.account_id"
                      >
                        {{ account.account_code }} - {{ account.account_name }}
                      </option>
                    </select>
                    <div v-if="errors.accumulated_depreciation_account_id" class="error-message">
                      {{ errors.accumulated_depreciation_account_id }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calculation Preview -->
        <div v-if="selectedAsset && calculation" class="preview-section">
          <div class="section-header">
            <h2>
              <i class="fas fa-eye"></i>
              Calculation Preview
            </h2>
            <p>Review the depreciation calculation before saving</p>
          </div>

          <div class="preview-content">
            <div class="asset-summary">
              <h3>{{ selectedAsset.name }}</h3>
              <span class="asset-code">{{ selectedAsset.asset_code }}</span>
            </div>

            <div class="calculation-details">
              <div class="calc-row">
                <span class="calc-label">Current Asset Value:</span>
                <span class="calc-value">${{ formatCurrency(selectedAsset.current_value) }}</span>
              </div>
              <div class="calc-row">
                <span class="calc-label">Depreciation Rate:</span>
                <span class="calc-value">{{ selectedAsset.depreciation_rate }}%</span>
              </div>
              <div class="calc-row highlighted">
                <span class="calc-label">Depreciation Amount:</span>
                <span class="calc-value amount">${{ formatCurrency(calculation.depreciation_amount) }}</span>
              </div>
              <div class="calc-row">
                <span class="calc-label">Previous Accumulated:</span>
                <span class="calc-value">${{ formatCurrency(calculation.previous_accumulated || 0) }}</span>
              </div>
              <div class="calc-row">
                <span class="calc-label">New Accumulated Total:</span>
                <span class="calc-value">${{ formatCurrency(calculation.accumulated_depreciation) }}</span>
              </div>
              <div class="calc-row highlighted">
                <span class="calc-label">Remaining Asset Value:</span>
                <span class="calc-value remaining">${{ formatCurrency(calculation.remaining_value) }}</span>
              </div>
            </div>

            <div class="progress-visualization">
              <div class="progress-header">
                <span>Depreciation Progress</span>
                <span class="progress-percentage">{{ calculation.depreciation_percentage }}%</span>
              </div>
              <div class="progress-bar">
                <div
                  class="progress-fill"
                  :style="{ width: calculation.depreciation_percentage + '%' }"
                ></div>
              </div>
              <div class="progress-labels">
                <span>Acquisition: ${{ formatCurrency(selectedAsset.acquisition_cost) }}</span>
                <span>Remaining: ${{ formatCurrency(calculation.remaining_value) }}</span>
              </div>
            </div>

            <div v-if="form.create_journal_entry" class="journal-preview">
              <h4>Journal Entry Preview</h4>
              <div class="journal-entries">
                <div class="journal-entry debit">
                  <div class="entry-header">
                    <i class="fas fa-plus"></i>
                    <span>Debit</span>
                  </div>
                  <div class="entry-details">
                    <span class="account">{{ getAccountName(form.depreciation_expense_account_id) }}</span>
                    <span class="amount">${{ formatCurrency(calculation.depreciation_amount) }}</span>
                  </div>
                </div>
                <div class="journal-entry credit">
                  <div class="entry-header">
                    <i class="fas fa-minus"></i>
                    <span>Credit</span>
                  </div>
                  <div class="entry-details">
                    <span class="account">{{ getAccountName(form.accumulated_depreciation_account_id) }}</span>
                    <span class="amount">${{ formatCurrency(calculation.depreciation_amount) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="action-buttons">
              <button
                @click="calculateDepreciation"
                :disabled="!canCalculate || calculating"
                class="btn btn-primary btn-large"
              >
                <i v-if="calculating" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-save"></i>
                {{ calculating ? 'Calculating...' : 'Calculate & Save Depreciation' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Success Modal -->
      <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
        <div class="modal-content success-modal" @click.stop>
          <div class="modal-header">
            <div class="success-icon">
              <i class="fas fa-check"></i>
            </div>
            <h3>Depreciation Calculated Successfully!</h3>
          </div>
          <div class="modal-body">
            <p>
              Depreciation for <strong>{{ selectedAsset?.name }}</strong> 
              has been calculated and recorded successfully.
            </p>
            <div class="success-details">
              <div class="detail-item">
                <span>Depreciation Amount:</span>
                <span class="amount">${{ formatCurrency(calculationResult?.depreciation_amount) }}</span>
              </div>
              <div class="detail-item">
                <span>New Asset Value:</span>
                <span class="amount">${{ formatCurrency(calculationResult?.remaining_value) }}</span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <router-link to="/accounting/asset-depreciations" class="btn btn-secondary">
              View All Depreciations
            </router-link>
            <button @click="resetForm" class="btn btn-primary">
              Calculate Another
            </button>
          </div>
        </div>
      </div>

      <!-- Error Modal -->
      <div v-if="showErrorModal" class="modal-overlay" @click="closeErrorModal">
        <div class="modal-content error-modal" @click.stop>
          <div class="modal-header">
            <div class="error-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Calculation Error</h3>
          </div>
          <div class="modal-body">
            <p>{{ errorMessage }}</p>
          </div>
          <div class="modal-footer">
            <button @click="closeErrorModal" class="btn btn-primary">
              Try Again
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'CalculateDepreciation',
  components: {
  },
  setup() {
    const router = useRouter()
    
    // Reactive state
    const loadingAssets = ref(false)
    const calculating = ref(false)
    const assets = ref([])
    const periods = ref([])
    const accounts = ref([])
    const selectedAsset = ref(null)
    const calculation = ref(null)
    const calculationResult = ref(null)
    
    // Search and filter
    const assetSearch = ref('')
    const categoryFilter = ref('')
    const statusFilter = ref('')
    
    // Form data
    const form = ref({
      period_id: '',
      depreciation_date: new Date().toISOString().split('T')[0],
      create_journal_entry: false,
      depreciation_expense_account_id: '',
      accumulated_depreciation_account_id: ''
    })
    
    // Validation
    const errors = ref({})
    
    // Modal states
    const showSuccessModal = ref(false)
    const showErrorModal = ref(false)
    const errorMessage = ref('')

    // Computed properties
    const filteredAssets = computed(() => {
      let filtered = assets.value

      if (assetSearch.value) {
        const search = assetSearch.value.toLowerCase()
        filtered = filtered.filter(asset =>
          asset.name.toLowerCase().includes(search) ||
          asset.asset_code.toLowerCase().includes(search)
        )
      }

      if (categoryFilter.value) {
        filtered = filtered.filter(asset => asset.category === categoryFilter.value)
      }

      if (statusFilter.value) {
        filtered = filtered.filter(asset => asset.status === statusFilter.value)
      }

      return filtered
    })

    const assetCategories = computed(() => {
      const categories = [...new Set(assets.value.map(asset => asset.category))]
      return categories.sort()
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

    const canCalculate = computed(() => {
      return selectedAsset.value &&
             form.value.period_id &&
             form.value.depreciation_date &&
             (!form.value.create_journal_entry || 
              (form.value.depreciation_expense_account_id && 
               form.value.accumulated_depreciation_account_id))
    })

    // Methods
    const fetchAssets = async () => {
      try {
        loadingAssets.value = true
        const response = await axios.get('/accounting/fixed-assets')
        assets.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching assets:', error)
      } finally {
        loadingAssets.value = false
      }
    }

    const fetchPeriods = async () => {
      try {
        const response = await axios.get('/accounting/accounting-periods')
        periods.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching periods:', error)
      }
    }

    const fetchAccounts = async () => {
      try {
        const response = await axios.get('/accounting/chart-of-accounts')
        accounts.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching accounts:', error)
      }
    }

    const filterAssets = () => {
      // Trigger reactive update
    }

    const selectAsset = (asset) => {
      if (asset.status !== 'Active') {
        showError('Cannot calculate depreciation for inactive assets')
        return
      }
      
      selectedAsset.value = asset
      validateAndCalculate()
    }

    const validateAndCalculate = () => {
      if (!selectedAsset.value || !form.value.period_id) {
        calculation.value = null
        return
      }

      // Clear previous errors
      errors.value = {}

      // Validate form
      if (!form.value.period_id) {
        errors.value.period_id = 'Please select an accounting period'
        return
      }

      if (!form.value.depreciation_date) {
        errors.value.depreciation_date = 'Please select a depreciation date'
        return
      }

      if (form.value.create_journal_entry) {
        if (!form.value.depreciation_expense_account_id) {
          errors.value.depreciation_expense_account_id = 'Please select depreciation expense account'
          return
        }
        if (!form.value.accumulated_depreciation_account_id) {
          errors.value.accumulated_depreciation_account_id = 'Please select accumulated depreciation account'
          return
        }
      }

      // Calculate preview
      calculatePreview()
    }

    const calculatePreview = () => {
      const asset = selectedAsset.value
      const depreciationAmount = (asset.current_value * asset.depreciation_rate) / 100
      const previousAccumulated = asset.acquisition_cost - asset.current_value
      const newAccumulated = previousAccumulated + depreciationAmount
      const remainingValue = Math.max(0, asset.acquisition_cost - newAccumulated)
      const depreciationPercentage = Math.min(100, (newAccumulated / asset.acquisition_cost) * 100)

      calculation.value = {
        depreciation_amount: Math.round(depreciationAmount * 100) / 100,
        previous_accumulated: previousAccumulated,
        accumulated_depreciation: newAccumulated,
        remaining_value: remainingValue,
        depreciation_percentage: Math.round(depreciationPercentage)
      }
    }

    const calculateDepreciation = async () => {
      if (!canCalculate.value) return

      try {
        calculating.value = true
        
        const response = await axios.post(
          `/accounting/fixed-assets/${selectedAsset.value.asset_id}/calculate-depreciation`,
          form.value
        )
        
        calculationResult.value = response.data.data
        showSuccessModal.value = true
        
      } catch (error) {
        console.error('Error calculating depreciation:', error)
        
        if (error.response?.data?.message) {
          showError(error.response.data.message)
        } else {
          showError('An error occurred while calculating depreciation. Please try again.')
        }
      } finally {
        calculating.value = false
      }
    }

    const getAccountName = (accountId) => {
      const account = accounts.value.find(a => a.account_id === accountId)
      return account ? `${account.account_code} - ${account.account_name}` : 'Unknown Account'
    }

    const showError = (message) => {
      errorMessage.value = message
      showErrorModal.value = true
    }

    const closeSuccessModal = () => {
      showSuccessModal.value = false
    }

    const closeErrorModal = () => {
      showErrorModal.value = false
      errorMessage.value = ''
    }

    const resetForm = () => {
      selectedAsset.value = null
      calculation.value = null
      calculationResult.value = null
      form.value = {
        period_id: '',
        depreciation_date: new Date().toISOString().split('T')[0],
        create_journal_entry: false,
        depreciation_expense_account_id: '',
        accumulated_depreciation_account_id: ''
      }
      errors.value = {}
      showSuccessModal.value = false
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

    // Watchers
    watch(() => form.value.create_journal_entry, (newValue) => {
      if (!newValue) {
        form.value.depreciation_expense_account_id = ''
        form.value.accumulated_depreciation_account_id = ''
        delete errors.value.depreciation_expense_account_id
        delete errors.value.accumulated_depreciation_account_id
      }
    })

    // Lifecycle
    onMounted(() => {
      Promise.all([
        fetchAssets(),
        fetchPeriods(),
        fetchAccounts()
      ])
    })

    return {
      loadingAssets,
      calculating,
      assets,
      periods,
      accounts,
      selectedAsset,
      calculation,
      calculationResult,
      assetSearch,
      categoryFilter,
      statusFilter,
      form,
      errors,
      showSuccessModal,
      showErrorModal,
      errorMessage,
      filteredAssets,
      assetCategories,
      expenseAccounts,
      accumulatedAccounts,
      canCalculate,
      filterAssets,
      selectAsset,
      validateAndCalculate,
      calculateDepreciation,
      getAccountName,
      closeSuccessModal,
      closeErrorModal,
      resetForm,
      formatCurrency,
      formatDate
    }
  }
}
</script>

<style scoped>
.calculate-depreciation-page {
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

/* Form Layout */
.form-layout {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.form-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.section-header {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.section-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.section-header i {
  margin-right: 0.5rem;
  color: #6366f1;
}

.section-header p {
  color: #6b7280;
  margin: 0;
}

/* Asset Selection */
.search-section {
  margin-bottom: 2rem;
}

.search-input-wrapper {
  position: relative;
  margin-bottom: 1rem;
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
}

.search-input {
  padding-left: 2.5rem;
}

.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.form-input,
.form-select {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Assets Grid */
.assets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.asset-card {
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  background: #fafbfc;
  position: relative;
}

.asset-card:hover {
  transform: translateY(-5px);
  border-color: #6366f1;
  box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15);
}

.asset-card.selected {
  border-color: #6366f1;
  background: #f0f4ff;
  box-shadow: 0 10px 25px rgba(99, 102, 241, 0.2);
}

.asset-card.inactive {
  opacity: 0.6;
  cursor: not-allowed;
}

.asset-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.asset-info h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.asset-code {
  background: #e0e7ff;
  color: #5b21b6;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.inactive {
  background: #fee2e2;
  color: #dc2626;
}

.asset-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-row .label {
  font-size: 0.85rem;
  color: #6b7280;
  font-weight: 500;
}

.detail-row .value {
  font-weight: 600;
  color: #1f2937;
}

.detail-row .value.amount {
  color: #059669;
}

.inactive-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #dc2626;
  font-weight: 600;
}

.inactive-overlay i {
  font-size: 2rem;
}

/* Calculation Form */
.calculation-section {
  background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
  border: 2px solid #c7d2fe;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 600;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.form-group i {
  color: #6366f1;
}

.error-message {
  color: #dc2626;
  font-size: 0.85rem;
  font-weight: 500;
}

/* Journal Entry Section */
.journal-entry-section {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 2px solid #e2e8f0;
}

.section-toggle {
  margin-bottom: 1.5rem;
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 1rem;
  cursor: pointer;
  font-weight: 600;
  color: #374151;
}

.toggle-input {
  display: none;
}

.toggle-slider {
  width: 50px;
  height: 24px;
  background: #e2e8f0;
  border-radius: 12px;
  position: relative;
  transition: background 0.3s ease;
}

.toggle-slider::before {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  transition: transform 0.3s ease;
}

.toggle-input:checked + .toggle-slider {
  background: #6366f1;
}

.toggle-input:checked + .toggle-slider::before {
  transform: translateX(26px);
}

.toggle-description {
  color: #6b7280;
  font-size: 0.9rem;
  margin-top: 0.5rem;
}

.journal-accounts {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

/* Preview Section */
.preview-section {
  background: linear-gradient(135deg, #ecfccb 0%, #d9f99d 100%);
  border: 2px solid #bef264;
}

.preview-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.asset-summary {
  text-align: center;
  padding: 1rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.asset-summary h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.calculation-details {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

.calc-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.calc-row:last-child {
  border-bottom: none;
}

.calc-row.highlighted {
  background: #f0f4ff;
  margin: 0 -1rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-weight: 600;
}

.calc-label {
  color: #374151;
  font-weight: 500;
}

.calc-value {
  font-weight: 600;
  color: #1f2937;
}

.calc-value.amount {
  color: #059669;
  font-size: 1.1rem;
}

.calc-value.remaining {
  color: #d97706;
  font-size: 1.1rem;
}

/* Progress Visualization */
.progress-visualization {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-weight: 600;
  color: #374151;
}

.progress-percentage {
  font-size: 1.25rem;
  color: #6366f1;
}

.progress-bar {
  height: 20px;
  background: #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 1rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  transition: width 1s ease;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #6b7280;
}

/* Journal Preview */
.journal-preview {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

.journal-preview h4 {
  margin-bottom: 1rem;
  color: #374151;
  font-weight: 600;
}

.journal-entries {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.journal-entry {
  padding: 1rem;
  border-radius: 8px;
  border: 2px solid;
}

.journal-entry.debit {
  border-color: #dc2626;
  background: #fef2f2;
}

.journal-entry.credit {
  border-color: #059669;
  background: #f0fdf4;
}

.entry-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.journal-entry.debit .entry-header {
  color: #dc2626;
}

.journal-entry.credit .entry-header {
  color: #059669;
}

.entry-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.entry-details .account {
  font-size: 0.9rem;
  color: #374151;
}

.entry-details .amount {
  font-weight: 700;
  font-size: 1.1rem;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  justify-content: center;
  padding-top: 1rem;
}

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

.btn-large {
  padding: 1rem 2rem;
  font-size: 1rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
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

/* Loading & Empty States */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: #6b7280;
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

.empty-assets {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.empty-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
  font-size: 2rem;
  color: #9ca3af;
}

.empty-assets h3 {
  font-size: 1.5rem;
  color: #374151;
  margin-bottom: 0.5rem;
}

.empty-assets p {
  color: #6b7280;
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
  max-width: 500px;
  width: 100%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.success-modal .modal-header {
  text-align: center;
  padding: 2rem 2rem 1rem 2rem;
}

.success-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  color: white;
  font-size: 2rem;
}

.error-modal .modal-header {
  text-align: center;
  padding: 2rem 2rem 1rem 2rem;
}

.error-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  color: white;
  font-size: 2rem;
}

.modal-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.modal-body {
  padding: 1rem 2rem;
  text-align: center;
}

.modal-body p {
  color: #374151;
  margin-bottom: 1rem;
  font-size: 1.1rem;
}

.success-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: #f0fdf4;
  border-radius: 8px;
  padding: 1rem;
  border: 1px solid #bbf7d0;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-item .amount {
  font-weight: 700;
  color: #059669;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 1rem 2rem 2rem 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .calculate-depreciation-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .filters {
    grid-template-columns: 1fr;
  }

  .assets-grid {
    grid-template-columns: 1fr;
  }

  .journal-entries {
    grid-template-columns: 1fr;
  }

  .calc-row.highlighted {
    margin: 0;
    padding: 0.75rem;
  }

  .progress-labels {
    flex-direction: column;
    gap: 0.5rem;
    text-align: center;
  }

  .modal-footer {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .form-section {
    padding: 1rem;
  }

  .asset-card {
    padding: 1rem;
  }

  .detail-row {
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
  }

  .calc-row {
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
    text-align: left;
  }
}
</style>