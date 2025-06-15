<!-- src/views/accounting/DepreciationDetail.vue -->
<template>
  <AppLayout>
    <div class="depreciation-detail-page">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-chart-line"></i>
              Depreciation Details
            </h1>
            <p class="page-subtitle">View comprehensive asset depreciation information</p>
          </div>
          <div class="header-actions">
            <router-link to="/accounting/asset-depreciations" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i>
              Back to List
            </router-link>
            <div class="action-menu">
              <button class="btn btn-primary" @click="showActionsMenu = !showActionsMenu">
                <i class="fas fa-ellipsis-h"></i>
                Actions
              </button>
              <div v-if="showActionsMenu" class="actions-dropdown">
                <button
                  @click="viewSchedule"
                  class="dropdown-item"
                >
                  <i class="fas fa-calendar-alt"></i>
                  View Schedule
                </button>
                <button
                  @click="viewJournalEntry"
                  class="dropdown-item"
                >
                  <i class="fas fa-book"></i>
                  View Journal Entry
                </button>
                <button
                  @click="calculateNextPeriod"
                  class="dropdown-item"
                  :disabled="!canCalculateNext"
                >
                  <i class="fas fa-calculator"></i>
                  Calculate Next Period
                </button>
                <hr class="dropdown-divider">
                <button
                  @click="exportDetail"
                  class="dropdown-item"
                >
                  <i class="fas fa-download"></i>
                  Export Details
                </button>
                <button
                  @click="printDetail"
                  class="dropdown-item"
                >
                  <i class="fas fa-print"></i>
                  Print Details
                </button>
                <hr class="dropdown-divider">
                <button
                  v-if="canEdit"
                  @click="editDepreciation"
                  class="dropdown-item"
                >
                  <i class="fas fa-edit"></i>
                  Edit Depreciation
                </button>
                <button
                  v-if="canDelete"
                  @click="deleteDepreciation"
                  class="dropdown-item danger"
                >
                  <i class="fas fa-trash"></i>
                  Delete Depreciation
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner"></div>
        <p>Loading depreciation details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-container">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Error Loading Details</h3>
        <p>{{ error }}</p>
        <button @click="loadDepreciationDetail" class="btn btn-primary">
          <i class="fas fa-refresh"></i>
          Retry
        </button>
      </div>

      <!-- Main Content -->
      <div v-else-if="depreciation" class="detail-content">
        <!-- Asset Overview -->
        <div class="asset-overview-section">
          <div class="overview-card">
            <div class="asset-header">
              <div class="asset-main-info">
                <div class="asset-title">
                  <h2>{{ depreciation.fixed_asset?.name }}</h2>
                  <span class="asset-code">{{ depreciation.fixed_asset?.asset_code }}</span>
                  <span :class="['status-badge', depreciation.fixed_asset?.status?.toLowerCase()]">
                    {{ depreciation.fixed_asset?.status }}
                  </span>
                </div>
                <div class="asset-category">
                  <i class="fas fa-tag"></i>
                  <span>{{ depreciation.fixed_asset?.category }}</span>
                </div>
              </div>
              <div class="asset-visual">
                <div class="asset-icon">
                  <i :class="getAssetIcon(depreciation.fixed_asset?.category)"></i>
                </div>
              </div>
            </div>

            <div class="asset-details-grid">
              <div class="detail-item acquisition">
                <div class="detail-icon">
                  <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="detail-content">
                  <h4>Acquisition Cost</h4>
                  <p class="amount">${{ formatCurrency(depreciation.fixed_asset?.acquisition_cost) }}</p>
                  <small>{{ formatDate(depreciation.fixed_asset?.acquisition_date) }}</small>
                </div>
              </div>

              <div class="detail-item current">
                <div class="detail-icon">
                  <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="detail-content">
                  <h4>Current Book Value</h4>
                  <p class="amount">${{ formatCurrency(depreciation.fixed_asset?.current_value) }}</p>
                  <small>As of {{ formatDate(depreciation.depreciation_date) }}</small>
                </div>
              </div>

              <div class="detail-item rate">
                <div class="detail-icon">
                  <i class="fas fa-percentage"></i>
                </div>
                <div class="detail-content">
                  <h4>Depreciation Rate</h4>
                  <p class="amount">{{ depreciation.fixed_asset?.depreciation_rate }}%</p>
                  <small>Annual rate</small>
                </div>
              </div>

              <div class="detail-item progress">
                <div class="detail-icon">
                  <i class="fas fa-chart-pie"></i>
                </div>
                <div class="detail-content">
                  <h4>Depreciation Progress</h4>
                  <p class="amount">{{ depreciationPercentage }}%</p>
                  <small>{{ formatCurrency(totalDepreciated) }} depreciated</small>
                </div>
              </div>
            </div>

            <div class="progress-visualization">
              <div class="progress-header">
                <h4>Asset Value Progress</h4>
                <span class="progress-percentage">{{ depreciationPercentage }}% Depreciated</span>
              </div>
              <div class="progress-bar-container">
                <div class="progress-bar">
                  <div
                    class="progress-fill"
                    :style="{ width: depreciationPercentage + '%' }"
                  ></div>
                  <div class="progress-current-marker" :style="{ left: depreciationPercentage + '%' }">
                    <div class="marker-tooltip">
                      Current: ${{ formatCurrency(depreciation.fixed_asset?.current_value) }}
                    </div>
                  </div>
                </div>
                <div class="progress-labels">
                  <span class="label start">
                    <strong>$0</strong>
                    <small>Fully Depreciated</small>
                  </span>
                  <span class="label end">
                    <strong>${{ formatCurrency(depreciation.fixed_asset?.acquisition_cost) }}</strong>
                    <small>Acquisition Cost</small>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Depreciation Details -->
        <div class="depreciation-details-section">
          <div class="details-card">
            <div class="section-header">
              <h3>
                <i class="fas fa-calculator"></i>
                Depreciation Calculation Details
              </h3>
              <div class="calculation-period">
                <i class="fas fa-calendar"></i>
                <span>{{ depreciation.accounting_period?.period_name }}</span>
              </div>
            </div>

            <div class="calculation-breakdown">
              <div class="breakdown-row header">
                <span class="breakdown-label">Calculation Component</span>
                <span class="breakdown-value">Amount</span>
              </div>

              <div class="breakdown-row">
                <span class="breakdown-label">
                  <i class="fas fa-circle-dot"></i>
                  Opening Book Value
                </span>
                <span class="breakdown-value">${{ formatCurrency(openingValue) }}</span>
              </div>

              <div class="breakdown-row calculation">
                <span class="breakdown-label">
                  <i class="fas fa-calculator"></i>
                  Depreciation Rate ({{ depreciation.fixed_asset?.depreciation_rate }}%)
                </span>
                <span class="breakdown-value">× ${{ formatCurrency(depreciation.fixed_asset?.current_value) }}</span>
              </div>

              <div class="breakdown-row highlight">
                <span class="breakdown-label">
                  <i class="fas fa-arrow-down"></i>
                  <strong>Period Depreciation</strong>
                </span>
                <span class="breakdown-value primary">
                  <strong>${{ formatCurrency(depreciation.depreciation_amount) }}</strong>
                </span>
              </div>

              <div class="breakdown-row">
                <span class="breakdown-label">
                  <i class="fas fa-plus"></i>
                  Previous Accumulated Depreciation
                </span>
                <span class="breakdown-value">${{ formatCurrency(previousAccumulated) }}</span>
              </div>

              <div class="breakdown-row total">
                <span class="breakdown-label">
                  <i class="fas fa-equals"></i>
                  <strong>Total Accumulated Depreciation</strong>
                </span>
                <span class="breakdown-value secondary">
                  <strong>${{ formatCurrency(depreciation.accumulated_depreciation) }}</strong>
                </span>
              </div>

              <div class="breakdown-row final">
                <span class="breakdown-label">
                  <i class="fas fa-hand-holding-dollar"></i>
                  <strong>Remaining Book Value</strong>
                </span>
                <span class="breakdown-value remaining">
                  <strong>${{ formatCurrency(depreciation.remaining_value) }}</strong>
                </span>
              </div>
            </div>

            <div class="calculation-metadata">
              <div class="metadata-item">
                <span class="metadata-label">Calculation Date:</span>
                <span class="metadata-value">{{ formatDateTime(depreciation.depreciation_date) }}</span>
              </div>
              <div class="metadata-item">
                <span class="metadata-label">Calculation Method:</span>
                <span class="metadata-value">Straight Line</span>
              </div>
              <div class="metadata-item">
                <span class="metadata-label">Estimated Useful Life:</span>
                <span class="metadata-value">{{ estimatedUsefulLife }} years</span>
              </div>
              <div class="metadata-item">
                <span class="metadata-label">Years Remaining:</span>
                <span class="metadata-value">{{ yearsRemaining }} years</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Journal Entry Information -->
        <div class="journal-entry-section">
          <div class="journal-card">
            <div class="section-header">
              <h3>
                <i class="fas fa-book"></i>
                Related Journal Entry
              </h3>
              <div class="entry-actions">
                <button
                  v-if="journalEntry"
                  @click="viewJournalEntry"
                  class="btn btn-primary btn-sm"
                >
                  <i class="fas fa-external-link-alt"></i>
                  View Full Entry
                </button>
                <button
                  v-else
                  @click="createJournalEntry"
                  class="btn btn-success btn-sm"
                >
                  <i class="fas fa-plus"></i>
                  Create Entry
                </button>
              </div>
            </div>

            <div v-if="journalEntry" class="journal-content">
              <div class="entry-header">
                <div class="entry-info">
                  <h4>{{ journalEntry.journal_number }}</h4>
                  <span :class="['status-badge', journalEntry.status?.toLowerCase()]">
                    {{ journalEntry.status }}
                  </span>
                </div>
                <div class="entry-date">{{ formatDate(journalEntry.entry_date) }}</div>
              </div>

              <div class="entry-description">
                <i class="fas fa-quote-left"></i>
                <span>{{ journalEntry.description }}</span>
              </div>

              <div class="journal-lines">
                <div class="lines-header">
                  <h5>Journal Entry Lines</h5>
                  <div class="balance-indicator">
                    <i :class="['fas', isJournalBalanced ? 'fa-check-circle' : 'fa-exclamation-triangle']"></i>
                    <span>{{ isJournalBalanced ? 'Balanced' : 'Unbalanced' }}</span>
                  </div>
                </div>

                <div class="journal-lines-grid">
                  <div
                    v-for="line in journalEntry.journal_entry_lines"
                    :key="line.line_id"
                    :class="['journal-line', line.debit_amount > 0 ? 'debit' : 'credit']"
                  >
                    <div class="line-account">
                      <div class="account-code">{{ line.chart_of_account?.account_code }}</div>
                      <div class="account-name">{{ line.chart_of_account?.account_name }}</div>
                    </div>
                    <div class="line-amounts">
                      <div v-if="line.debit_amount > 0" class="debit-amount">
                        Dr. ${{ formatCurrency(line.debit_amount) }}
                      </div>
                      <div v-if="line.credit_amount > 0" class="credit-amount">
                        Cr. ${{ formatCurrency(line.credit_amount) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="no-journal-entry">
              <div class="no-entry-icon">
                <i class="fas fa-book-open"></i>
              </div>
              <h4>No Journal Entry</h4>
              <p>This depreciation calculation has not been posted to the general ledger yet.</p>
            </div>
          </div>
        </div>

        <!-- Historical Analysis -->
        <div class="historical-analysis-section">
          <div class="analysis-card">
            <div class="section-header">
              <h3>
                <i class="fas fa-chart-area"></i>
                Historical Analysis
              </h3>
              <div class="view-options">
                <button
                  @click="historicalView = 'chart'"
                  :class="['view-btn', { active: historicalView === 'chart' }]"
                >
                  <i class="fas fa-chart-line"></i>
                  Chart
                </button>
                <button
                  @click="historicalView = 'table'"
                  :class="['view-btn', { active: historicalView === 'table' }]"
                >
                  <i class="fas fa-table"></i>
                  Table
                </button>
              </div>
            </div>

            <!-- Chart View -->
            <div v-if="historicalView === 'chart'" class="chart-container">
              <div class="chart-header">
                <h4>Depreciation Trend</h4>
                <div class="chart-legend">
                  <div class="legend-item">
                    <span class="legend-color book-value"></span>
                    <span>Book Value</span>
                  </div>
                  <div class="legend-item">
                    <span class="legend-color accumulated"></span>
                    <span>Accumulated Depreciation</span>
                  </div>
                </div>
              </div>

              <div class="chart-area">
                <div class="chart-placeholder">
                  <div class="chart-lines">
                    <div
                      v-for="(point, index) in chartData"
                      :key="index"
                      class="chart-point"
                      :style="{
                        left: (index / (chartData.length - 1)) * 100 + '%',
                        bottom: (point.book_value / maxValue) * 100 + '%'
                      }"
                    >
                      <div class="point-tooltip">
                        <div class="tooltip-period">{{ point.period }}</div>
                        <div class="tooltip-values">
                          <div>Book Value: ${{ formatCurrency(point.book_value) }}</div>
                          <div>Accumulated: ${{ formatCurrency(point.accumulated) }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="chart-grid">
                    <div v-for="i in 5" :key="i" class="grid-line" :style="{ bottom: (i * 20) + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Table View -->
            <div v-else class="historical-table">
              <table class="history-table">
                <thead>
                  <tr>
                    <th>Period</th>
                    <th>Date</th>
                    <th>Opening Value</th>
                    <th>Depreciation</th>
                    <th>Accumulated</th>
                    <th>Closing Value</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="record in historicalData"
                    :key="record.depreciation_id"
                    :class="{ current: record.depreciation_id === depreciation.depreciation_id }"
                  >
                    <td>{{ record.accounting_period?.period_name }}</td>
                    <td>{{ formatDate(record.depreciation_date) }}</td>
                    <td class="amount">${{ formatCurrency(record.opening_value) }}</td>
                    <td class="amount depreciation">${{ formatCurrency(record.depreciation_amount) }}</td>
                    <td class="amount accumulated">${{ formatCurrency(record.accumulated_depreciation) }}</td>
                    <td class="amount remaining">${{ formatCurrency(record.remaining_value) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Future Projections -->
        <div class="projections-section">
          <div class="projections-card">
            <div class="section-header">
              <h3>
                <i class="fas fa-crystal-ball"></i>
                Future Projections
              </h3>
              <div class="projection-controls">
                <label for="projectionPeriods">Show next:</label>
                <select
                  id="projectionPeriods"
                  v-model="projectionPeriods"
                  @change="generateProjections"
                  class="form-select"
                >
                  <option value="3">3 Periods</option>
                  <option value="6">6 Periods</option>
                  <option value="12">12 Periods</option>
                  <option value="24">24 Periods</option>
                </select>
              </div>
            </div>

            <div class="projections-grid">
              <div
                v-for="projection in projections"
                :key="projection.period"
                class="projection-card"
              >
                <div class="projection-header">
                  <h5>{{ projection.period_name }}</h5>
                  <span class="projection-date">{{ formatDate(projection.projected_date) }}</span>
                </div>
                <div class="projection-amounts">
                  <div class="amount-item">
                    <span class="label">Depreciation:</span>
                    <span class="value">${{ formatCurrency(projection.depreciation_amount) }}</span>
                  </div>
                  <div class="amount-item">
                    <span class="label">Accumulated:</span>
                    <span class="value">${{ formatCurrency(projection.accumulated_depreciation) }}</span>
                  </div>
                  <div class="amount-item">
                    <span class="label">Book Value:</span>
                    <span class="value">${{ formatCurrency(projection.remaining_value) }}</span>
                  </div>
                </div>
                <div class="projection-note">
                  <i class="fas fa-info-circle"></i>
                  <span>Estimated projection</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h3>Confirm Deletion</h3>
            <button @click="closeDeleteModal" class="close-btn">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="warning-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p>
              Are you sure you want to delete this depreciation record?
            </p>
            <div class="deletion-details">
              <div class="detail-row">
                <span>Asset:</span>
                <span>{{ depreciation?.fixed_asset?.name }}</span>
              </div>
              <div class="detail-row">
                <span>Period:</span>
                <span>{{ depreciation?.accounting_period?.period_name }}</span>
              </div>
              <div class="detail-row">
                <span>Amount:</span>
                <span>${{ formatCurrency(depreciation?.depreciation_amount) }}</span>
              </div>
            </div>
            <div class="warning-note">
              <i class="fas fa-info-circle"></i>
              This action cannot be undone and will restore the asset's previous value.
            </div>
          </div>
          <div class="modal-footer">
            <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
            <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
              <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-trash"></i>
              {{ deleting ? 'Deleting...' : 'Delete' }}
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
  name: 'DepreciationDetail',
  components: {
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    
    // Reactive state
    const loading = ref(false)
    const deleting = ref(false)
    const error = ref('')
    const depreciation = ref(null)
    const journalEntry = ref(null)
    const historicalData = ref([])
    const projections = ref([])
    
    // UI state
    const showActionsMenu = ref(false)
    const showDeleteModal = ref(false)
    const historicalView = ref('chart')
    const projectionPeriods = ref(6)

    // Computed properties
    const depreciationPercentage = computed(() => {
      if (!depreciation.value?.fixed_asset?.acquisition_cost) return 0
      const percentage = (depreciation.value.accumulated_depreciation / depreciation.value.fixed_asset.acquisition_cost) * 100
      return Math.min(100, Math.round(percentage))
    })

    const totalDepreciated = computed(() => {
      return depreciation.value?.accumulated_depreciation || 0
    })

    const openingValue = computed(() => {
      return (depreciation.value?.fixed_asset?.acquisition_cost || 0) - 
             ((depreciation.value?.accumulated_depreciation || 0) - (depreciation.value?.depreciation_amount || 0))
    })

    const previousAccumulated = computed(() => {
      return (depreciation.value?.accumulated_depreciation || 0) - (depreciation.value?.depreciation_amount || 0)
    })

    const estimatedUsefulLife = computed(() => {
      if (!depreciation.value?.fixed_asset?.depreciation_rate) return 0
      return Math.round(100 / depreciation.value.fixed_asset.depreciation_rate)
    })

    const yearsRemaining = computed(() => {
      const remainingPercentage = 100 - depreciationPercentage.value
      if (!depreciation.value?.fixed_asset?.depreciation_rate) return 0
      return Math.ceil(remainingPercentage / depreciation.value.fixed_asset.depreciation_rate)
    })

    const canEdit = computed(() => {
      return depreciation.value && (!journalEntry.value || journalEntry.value.status === 'Draft')
    })

    const canDelete = computed(() => {
      return depreciation.value && (!journalEntry.value || journalEntry.value.status === 'Draft')
    })

    const canCalculateNext = computed(() => {
      return depreciation.value?.fixed_asset?.current_value > 0 && 
             depreciation.value?.fixed_asset?.status === 'Active'
    })

    const isJournalBalanced = computed(() => {
      if (!journalEntry.value?.journal_entry_lines) return true
      const totalDebits = journalEntry.value.journal_entry_lines.reduce((sum, line) => sum + (line.debit_amount || 0), 0)
      const totalCredits = journalEntry.value.journal_entry_lines.reduce((sum, line) => sum + (line.credit_amount || 0), 0)
      return Math.abs(totalDebits - totalCredits) < 0.01
    })

    const chartData = computed(() => {
      return historicalData.value.map(record => ({
        period: record.accounting_period?.period_name,
        book_value: record.remaining_value,
        accumulated: record.accumulated_depreciation
      }))
    })

    const maxValue = computed(() => {
      if (!depreciation.value?.fixed_asset?.acquisition_cost) return 1000
      return depreciation.value.fixed_asset.acquisition_cost
    })

    // Methods
    const loadDepreciationDetail = async () => {
      try {
        loading.value = true
        error.value = ''
        
        const id = route.params.id
        
        // Load depreciation details
        const response = await axios.get(`/accounting/asset-depreciations/${id}`)
        depreciation.value = response.data.data
        
        // Load related data
        await Promise.all([
          loadJournalEntry(),
          loadHistoricalData(),
          generateProjections()
        ])
        
      } catch (err) {
        console.error('Error loading depreciation:', err)
        error.value = err.response?.data?.message || 'Failed to load depreciation details'
      } finally {
        loading.value = false
      }
    }

    const loadJournalEntry = async () => {
      try {
        const response = await axios.get(
          `/accounting/journal-entries?reference_type=AssetDepreciation&reference_id=${depreciation.value.depreciation_id}`
        )
        
        const entries = response.data.data || response.data
        if (entries.length > 0) {
          const entryResponse = await axios.get(`/accounting/journal-entries/${entries[0].journal_id}`)
          journalEntry.value = entryResponse.data.data
        }
      } catch (err) {
        console.log('No journal entry found')
      }
    }

    const loadHistoricalData = async () => {
      try {
        const response = await axios.get(
          `/accounting/asset-depreciations?asset_id=${depreciation.value.asset_id}`
        )
        
        historicalData.value = (response.data.data || response.data)
          .sort((a, b) => new Date(a.depreciation_date) - new Date(b.depreciation_date))
          .map((record, index, array) => ({
            ...record,
            opening_value: index === 0 
              ? depreciation.value.fixed_asset.acquisition_cost 
              : array[index - 1].remaining_value
          }))
      } catch (err) {
        console.error('Error loading historical data:', err)
      }
    }

    const generateProjections = () => {
      if (!depreciation.value?.fixed_asset) return
      
      const projectionData = []
      let currentValue = depreciation.value.fixed_asset.current_value
      let accumulatedDepreciation = depreciation.value.accumulated_depreciation
      const depreciationRate = depreciation.value.fixed_asset.depreciation_rate / 100
      
      for (let i = 1; i <= projectionPeriods.value; i++) {
        const depreciationAmount = currentValue * depreciationRate
        const newAccumulated = accumulatedDepreciation + depreciationAmount
        const newBookValue = Math.max(0, depreciation.value.fixed_asset.acquisition_cost - newAccumulated)
        
        const futureDate = new Date()
        futureDate.setMonth(futureDate.getMonth() + i)
        
        projectionData.push({
          period: i,
          period_name: `Period ${i}`,
          projected_date: futureDate.toISOString(),
          depreciation_amount: depreciationAmount,
          accumulated_depreciation: newAccumulated,
          remaining_value: newBookValue
        })
        
        currentValue = newBookValue
        accumulatedDepreciation = newAccumulated
        
        if (newBookValue <= 0) break
      }
      
      projections.value = projectionData
    }

    const getAssetIcon = (category) => {
      const icons = {
        'Building': 'fas fa-building',
        'Vehicle': 'fas fa-car',
        'Equipment': 'fas fa-cogs',
        'Furniture': 'fas fa-chair',
        'Computer': 'fas fa-laptop',
        'Machinery': 'fas fa-industry'
      }
      return icons[category] || 'fas fa-cube'
    }

    const viewSchedule = () => {
      router.push(`/accounting/asset-depreciations/schedule/${depreciation.value.asset_id}`)
    }

    const viewJournalEntry = () => {
      if (journalEntry.value) {
        router.push(`/accounting/journal-entries/${journalEntry.value.journal_id}`)
      } else {
        router.push(`/accounting/asset-depreciations/journal/${depreciation.value.depreciation_id}`)
      }
    }

    const calculateNextPeriod = () => {
      router.push({
        path: '/accounting/depreciations/calculate',
        query: { asset_id: depreciation.value.asset_id }
      })
    }

    const editDepreciation = () => {
      router.push(`/accounting/asset-depreciations/${depreciation.value.depreciation_id}/edit`)
    }

    const deleteDepreciation = () => {
      showDeleteModal.value = true
    }

    const confirmDelete = async () => {
      try {
        deleting.value = true
        
        await axios.delete(`/accounting/asset-depreciations/${depreciation.value.depreciation_id}`)
        
        router.push({
          path: '/accounting/asset-depreciations',
          query: { message: 'Depreciation deleted successfully' }
        })
      } catch (err) {
        console.error('Error deleting depreciation:', err)
        error.value = err.response?.data?.message || 'Failed to delete depreciation'
      } finally {
        deleting.value = false
        showDeleteModal.value = false
      }
    }

    const closeDeleteModal = () => {
      showDeleteModal.value = false
    }

    const createJournalEntry = () => {
      router.push(`/accounting/asset-depreciations/journal/${depreciation.value.depreciation_id}`)
    }

    const exportDetail = () => {
      window.open(`/api/accounting/asset-depreciations/${depreciation.value.depreciation_id}/export`, '_blank')
    }

    const printDetail = () => {
      window.print()
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

    // Click outside handler
    const handleClickOutside = (event) => {
      if (!event.target.closest('.action-menu')) {
        showActionsMenu.value = false
      }
    }

    // Lifecycle
    onMounted(() => {
      loadDepreciationDetail()
      document.addEventListener('click', handleClickOutside)
    })

    onUnmounted(() => {
      document.removeEventListener('click', handleClickOutside)
    })

    return {
      loading,
      deleting,
      error,
      depreciation,
      journalEntry,
      historicalData,
      projections,
      showActionsMenu,
      showDeleteModal,
      historicalView,
      projectionPeriods,
      depreciationPercentage,
      totalDepreciated,
      openingValue,
      previousAccumulated,
      estimatedUsefulLife,
      yearsRemaining,
      canEdit,
      canDelete,
      canCalculateNext,
      isJournalBalanced,
      chartData,
      maxValue,
      loadDepreciationDetail,
      generateProjections,
      getAssetIcon,
      viewSchedule,
      viewJournalEntry,
      calculateNextPeriod,
      editDepreciation,
      deleteDepreciation,
      confirmDelete,
      closeDeleteModal,
      createJournalEntry,
      exportDetail,
      printDetail,
      formatCurrency,
      formatDate,
      formatDateTime
    }
  }
}
</script>

<style scoped>
.depreciation-detail-page {
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
  align-items: center;
}

/* Action Menu */
.action-menu {
  position: relative;
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
  margin-top: 0.5rem;
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

.dropdown-item:hover:not(:disabled) {
  background: #f3f4f6;
}

.dropdown-item:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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

/* Detail Content */
.detail-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Asset Overview */
.asset-overview-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.asset-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 2px solid #f1f5f9;
}

.asset-title h2 {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.asset-code {
  background: #e0e7ff;
  color: #5b21b6;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  margin-right: 1rem;
}

.status-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.85rem;
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

.asset-category {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6b7280;
  font-size: 0.95rem;
  margin-top: 0.5rem;
}

.asset-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2rem;
}

.asset-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 16px;
  border: 2px solid;
  transition: transform 0.3s ease;
}

.detail-item:hover {
  transform: translateY(-3px);
}

.detail-item.acquisition {
  border-color: #3b82f6;
  background: #eff6ff;
}

.detail-item.current {
  border-color: #10b981;
  background: #f0fdf4;
}

.detail-item.rate {
  border-color: #f59e0b;
  background: #fffbeb;
}

.detail-item.progress {
  border-color: #8b5cf6;
  background: #f3e8ff;
}

.detail-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.detail-item.acquisition .detail-icon {
  background: #3b82f6;
}

.detail-item.current .detail-icon {
  background: #10b981;
}

.detail-item.rate .detail-icon {
  background: #f59e0b;
}

.detail-item.progress .detail-icon {
  background: #8b5cf6;
}

.detail-content h4 {
  font-size: 0.9rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
  font-weight: 500;
}

.detail-content .amount {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
}

.detail-content small {
  color: #9ca3af;
  font-size: 0.8rem;
}

/* Progress Visualization */
.progress-visualization {
  background: #f8fafc;
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.progress-header h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #374151;
}

.progress-percentage {
  font-size: 1.5rem;
  font-weight: 700;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.progress-bar-container {
  margin-bottom: 1rem;
}

.progress-bar {
  position: relative;
  height: 20px;
  background: #e5e7eb;
  border-radius: 10px;
  overflow: visible;
  margin-bottom: 1rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  border-radius: 10px;
  transition: width 1s ease;
}

.progress-current-marker {
  position: absolute;
  top: -5px;
  width: 30px;
  height: 30px;
  background: #dc2626;
  border-radius: 50%;
  border: 3px solid white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  transform: translateX(-50%);
  cursor: pointer;
}

.marker-tooltip {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  margin-bottom: 5px;
}

.progress-current-marker:hover .marker-tooltip {
  opacity: 1;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
}

.label {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.label strong {
  font-weight: 600;
  color: #1f2937;
}

.label small {
  color: #6b7280;
  font-size: 0.75rem;
}

/* Depreciation Details */
.depreciation-details-section {
  background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
  border-radius: 20px;
  padding: 2rem;
  border: 2px solid #c7d2fe;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid rgba(255, 255, 255, 0.5);
}

.section-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
}

.section-header i {
  margin-right: 0.5rem;
  color: #6366f1;
}

.calculation-period {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: white;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  color: #6366f1;
  font-weight: 600;
}

.calculation-breakdown {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #c7d2fe;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.breakdown-row:last-child {
  border-bottom: none;
}

.breakdown-row.header {
  background: #f8fafc;
  margin: -1.5rem -1.5rem 1rem -1.5rem;
  padding: 1rem 1.5rem;
  border-bottom: 2px solid #e2e8f0;
  font-weight: 600;
  color: #374151;
}

.breakdown-row.calculation {
  background: #fef3c7;
  margin: 0 -1.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
}

.breakdown-row.highlight {
  background: #dbeafe;
  margin: 0 -1.5rem;
  padding: 1rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
}

.breakdown-row.total {
  background: #fef3c7;
  margin: 0 -1.5rem;
  padding: 1rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
}

.breakdown-row.final {
  background: #f0fdf4;
  margin: 0 -1.5rem 0 -1.5rem;
  padding: 1rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
}

.breakdown-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #374151;
}

.breakdown-value {
  font-weight: 600;
  color: #1f2937;
}

.breakdown-value.primary {
  color: #6366f1;
  font-size: 1.1rem;
}

.breakdown-value.secondary {
  color: #f59e0b;
  font-size: 1.1rem;
}

.breakdown-value.remaining {
  color: #10b981;
  font-size: 1.1rem;
}

.calculation-metadata {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 2px solid #f1f5f9;
}

.metadata-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.metadata-label {
  font-size: 0.85rem;
  color: #6b7280;
  font-weight: 500;
}

.metadata-value {
  font-weight: 600;
  color: #1f2937;
}

/* Journal Entry Section */
.journal-entry-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.entry-actions {
  display: flex;
  gap: 0.5rem;
}

.journal-content {
  background: #f8fafc;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

.entry-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.entry-info h4 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.entry-date {
  color: #6b7280;
  font-weight: 500;
}

.entry-description {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  padding: 1rem;
  background: white;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  color: #374151;
  font-style: italic;
}

.entry-description i {
  color: #6366f1;
}

.journal-lines {
  background: white;
  border-radius: 8px;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.lines-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f1f5f9;
}

.lines-header h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
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

.journal-lines-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.journal-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  border-radius: 8px;
  border: 2px solid;
}

.journal-line.debit {
  border-color: #fecaca;
  background: #fef2f2;
}

.journal-line.credit {
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.line-account {
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
  color: #6b7280;
  font-size: 0.85rem;
}

.line-amounts {
  text-align: right;
}

.debit-amount {
  color: #dc2626;
  font-weight: 600;
}

.credit-amount {
  color: #059669;
  font-weight: 600;
}

.no-journal-entry {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 2rem;
  text-align: center;
}

.no-entry-icon {
  width: 60px;
  height: 60px;
  background: #e5e7eb;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  color: #9ca3af;
  font-size: 1.5rem;
}

.no-journal-entry h4 {
  font-size: 1.1rem;
  color: #374151;
  margin-bottom: 0.5rem;
}

.no-journal-entry p {
  color: #6b7280;
  margin: 0;
}

/* Historical Analysis */
.historical-analysis-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.view-options {
  display: flex;
  gap: 0.5rem;
}

.view-btn {
  padding: 0.5rem 1rem;
  border: 2px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  color: #6b7280;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.view-btn:hover,
.view-btn.active {
  border-color: #6366f1;
  background: #6366f1;
  color: white;
}

/* Chart */
.chart-container {
  background: #f8fafc;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.chart-header h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #374151;
}

.chart-legend {
  display: flex;
  gap: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #6b7280;
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
}

.legend-color.book-value {
  background: #6366f1;
}

.legend-color.accumulated {
  background: #f59e0b;
}

.chart-area {
  height: 300px;
  position: relative;
}

.chart-placeholder {
  height: 100%;
  position: relative;
  background: white;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.chart-grid {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
}

.grid-line {
  position: absolute;
  left: 0;
  right: 0;
  height: 1px;
  background: #f1f5f9;
}

.chart-point {
  position: absolute;
  width: 10px;
  height: 10px;
  background: #6366f1;
  border-radius: 50%;
  transform: translate(-50%, 50%);
  cursor: pointer;
  transition: all 0.3s ease;
}

.chart-point:hover {
  width: 14px;
  height: 14px;
  background: #4f46e5;
}

.point-tooltip {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  margin-bottom: 5px;
}

.chart-point:hover .point-tooltip {
  opacity: 1;
}

/* Historical Table */
.historical-table {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.history-table th {
  background: #f8fafc;
  color: #374151;
  font-weight: 600;
  padding: 1rem;
  text-align: left;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.9rem;
}

.history-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.history-table tr.current {
  background: #f0f4ff;
  border-left: 4px solid #6366f1;
}

.history-table tr:hover {
  background: #f8fafc;
}

.amount {
  text-align: right;
  font-weight: 600;
}

.amount.depreciation {
  color: #dc2626;
}

.amount.accumulated {
  color: #f59e0b;
}

.amount.remaining {
  color: #10b981;
}

/* Projections */
.projections-section {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border-radius: 20px;
  padding: 2rem;
  border: 2px solid #bbf7d0;
}

.projection-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.projection-controls label {
  font-weight: 500;
  color: #374151;
}

.form-select {
  padding: 0.5rem 1rem;
  border: 2px solid #bbf7d0;
  border-radius: 8px;
  background: white;
  color: #374151;
  font-weight: 500;
}

.projections-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.projection-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 2px solid #bbf7d0;
  transition: transform 0.3s ease;
}

.projection-card:hover {
  transform: translateY(-3px);
}

.projection-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f1f5f9;
}

.projection-header h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #1f2937;
}

.projection-date {
  color: #6b7280;
  font-size: 0.85rem;
}

.projection-amounts {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.amount-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.amount-item .label {
  color: #6b7280;
  font-weight: 500;
}

.amount-item .value {
  font-weight: 600;
  color: #10b981;
}

.projection-note {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #fef3c7;
  padding: 0.5rem;
  border-radius: 6px;
  color: #92400e;
  font-size: 0.8rem;
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
.error-container {
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

.error-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
  color: white;
  font-size: 2rem;
}

.error-container h3 {
  font-size: 1.5rem;
  color: #374151;
  margin-bottom: 0.5rem;
}

.error-container p {
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
  max-width: 500px;
  width: 100%;
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
  text-align: center;
}

.warning-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  color: white;
  font-size: 1.5rem;
}

.modal-body p {
  color: #374151;
  margin-bottom: 1rem;
  font-size: 1.1rem;
}

.deletion-details {
  background: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
  margin: 1rem 0;
  text-align: left;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.detail-row:last-child {
  border-bottom: none;
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

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 0 1.5rem 1.5rem 1.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .depreciation-detail-page {
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

  .asset-header {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .asset-details-grid {
    grid-template-columns: 1fr;
  }

  .breakdown-row {
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
    text-align: left;
  }

  .calculation-metadata {
    grid-template-columns: 1fr;
  }

  .journal-line {
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
  }

  .projections-grid {
    grid-template-columns: 1fr;
  }

  .chart-area {
    height: 200px;
  }

  .modal-footer {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .detail-content {
    gap: 1rem;
  }

  .asset-overview-section,
  .depreciation-details-section,
  .journal-entry-section,
  .historical-analysis-section,
  .projections-section {
    padding: 1rem;
  }

  .detail-item {
    flex-direction: column;
    text-align: center;
  }

  .amount-item {
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
  }

  .history-table {
    font-size: 0.8rem;
  }

  .history-table th,
  .history-table td {
    padding: 0.5rem;
  }
}

/* Print Styles */
@media print {
  .page-header,
  .header-actions,
  .action-menu,
  .entry-actions,
  .view-options,
  .projection-controls {
    display: none !important;
  }

  .depreciation-detail-page {
    padding: 0;
    background: white;
  }

  .detail-content {
    gap: 1rem;
  }

  .asset-overview-section,
  .depreciation-details-section,
  .journal-entry-section,
  .historical-analysis-section,
  .projections-section {
    box-shadow: none;
    border: 1px solid #000;
    page-break-inside: avoid;
  }
}
</style>