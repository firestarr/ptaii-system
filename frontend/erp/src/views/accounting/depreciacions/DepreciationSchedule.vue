<!-- src/views/accounting/DepreciationSchedule.vue -->
<template>
  <AppLayout>
    <div class="depreciation-schedule-page">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-calendar-alt"></i>
              Depreciation Schedule
            </h1>
            <p class="page-subtitle">View complete depreciation schedule for assets</p>
          </div>
          <div class="header-actions">
            <router-link to="/accounting/asset-depreciations" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i>
              Back to Depreciations
            </router-link>
            <button @click="exportSchedule" class="btn btn-success">
              <i class="fas fa-download"></i>
              Export Schedule
            </button>
          </div>
        </div>
      </div>

      <!-- Asset Selection & Filters -->
      <div class="filters-section">
        <div class="filter-card">
          <div class="filter-header">
            <h3>
              <i class="fas fa-filter"></i>
              Schedule Filters
            </h3>
          </div>
          
          <div class="filter-content">
            <div class="filter-row">
              <div class="filter-group">
                <label for="assetSelect">Select Asset</label>
                <select
                  id="assetSelect"
                  v-model="selectedAssetId"
                  @change="loadSchedule"
                  class="form-select"
                >
                  <option value="">All Assets</option>
                  <option
                    v-for="asset in assets"
                    :key="asset.asset_id"
                    :value="asset.asset_id"
                  >
                    {{ asset.name }} ({{ asset.asset_code }})
                  </option>
                </select>
              </div>

              <div class="filter-group">
                <label for="yearFilter">Year</label>
                <select
                  id="yearFilter"
                  v-model="selectedYear"
                  @change="loadSchedule"
                  class="form-select"
                >
                  <option value="">All Years</option>
                  <option
                    v-for="year in availableYears"
                    :key="year"
                    :value="year"
                  >
                    {{ year }}
                  </option>
                </select>
              </div>

              <div class="filter-group">
                <label for="viewType">View Type</label>
                <select
                  id="viewType"
                  v-model="viewType"
                  @change="refreshView"
                  class="form-select"
                >
                  <option value="summary">Summary View</option>
                  <option value="detailed">Detailed View</option>
                  <option value="projection">Future Projection</option>
                </select>
              </div>

              <div class="filter-group">
                <label for="statusFilter">Status</label>
                <select
                  id="statusFilter"
                  v-model="statusFilter"
                  @change="loadSchedule"
                  class="form-select"
                >
                  <option value="">All Status</option>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                  <option value="Fully Depreciated">Fully Depreciated</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Asset Overview (when single asset selected) -->
      <div v-if="selectedAsset" class="asset-overview">
        <div class="overview-card">
          <div class="asset-header">
            <div class="asset-main-info">
              <h2>{{ selectedAsset.name }}</h2>
              <span class="asset-code">{{ selectedAsset.asset_code }}</span>
              <span :class="['status-badge', selectedAsset.status.toLowerCase()]">
                {{ selectedAsset.status }}
              </span>
            </div>
            <div class="asset-image">
              <div class="asset-icon">
                <i class="fas fa-cube"></i>
              </div>
            </div>
          </div>

          <div class="asset-details-grid">
            <div class="detail-card acquisition">
              <div class="detail-icon">
                <i class="fas fa-shopping-cart"></i>
              </div>
              <div class="detail-content">
                <h4>Acquisition Cost</h4>
                <p class="amount">${{ formatCurrency(selectedAsset.acquisition_cost) }}</p>
                <small>{{ formatDate(selectedAsset.acquisition_date) }}</small>
              </div>
            </div>

            <div class="detail-card current">
              <div class="detail-icon">
                <i class="fas fa-dollar-sign"></i>
              </div>
              <div class="detail-content">
                <h4>Current Value</h4>
                <p class="amount">${{ formatCurrency(selectedAsset.current_value) }}</p>
                <small>As of today</small>
              </div>
            </div>

            <div class="detail-card rate">
              <div class="detail-icon">
                <i class="fas fa-percentage"></i>
              </div>
              <div class="detail-content">
                <h4>Depreciation Rate</h4>
                <p class="amount">{{ selectedAsset.depreciation_rate }}%</p>
                <small>Annual rate</small>
              </div>
            </div>

            <div class="detail-card accumulated">
              <div class="detail-icon">
                <i class="fas fa-chart-pie"></i>
              </div>
              <div class="detail-content">
                <h4>Total Depreciated</h4>
                <p class="amount">${{ formatCurrency(totalDepreciated) }}</p>
                <small>{{ depreciationProgress }}% complete</small>
              </div>
            </div>
          </div>

          <!-- Depreciation Progress Visualization -->
          <div class="progress-section">
            <div class="progress-header">
              <h4>Depreciation Progress</h4>
              <span class="progress-percentage">{{ depreciationProgress }}%</span>
            </div>
            <div class="progress-bar-container">
              <div class="progress-bar">
                <div
                  class="progress-fill"
                  :style="{ width: depreciationProgress + '%' }"
                ></div>
              </div>
              <div class="progress-markers">
                <span class="marker start">$0</span>
                <span class="marker end">${{ formatCurrency(selectedAsset.acquisition_cost) }}</span>
              </div>
            </div>
            <div class="progress-details">
              <div class="progress-item">
                <span class="label">Remaining Value:</span>
                <span class="value">${{ formatCurrency(selectedAsset.current_value) }}</span>
              </div>
              <div class="progress-item">
                <span class="label">Estimated Years Remaining:</span>
                <span class="value">{{ estimatedYearsRemaining }} years</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Schedule Content -->
      <div class="schedule-content">
        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
          <div class="loading-spinner"></div>
          <p>Loading depreciation schedule...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="scheduleData.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <h3>No Schedule Data Found</h3>
          <p>
            {{ selectedAssetId ? 'No depreciation records found for this asset' : 'Select an asset to view its depreciation schedule' }}
          </p>
        </div>

        <!-- Summary View -->
        <div v-else-if="viewType === 'summary'" class="summary-view">
          <div class="view-header">
            <h3>Schedule Summary</h3>
            <div class="summary-stats">
              <div class="stat">
                <span class="stat-label">Total Periods:</span>
                <span class="stat-value">{{ scheduleData.length }}</span>
              </div>
              <div class="stat">
                <span class="stat-label">Total Depreciation:</span>
                <span class="stat-value">${{ formatCurrency(totalScheduledDepreciation) }}</span>
              </div>
            </div>
          </div>

          <div class="summary-grid">
            <div
              v-for="item in scheduleData"
              :key="item.depreciation_id || item.period_id"
              :class="['summary-card', { projected: !item.depreciation_id }]"
            >
              <div class="card-header">
                <div class="period-info">
                  <h4>{{ item.period_name || `Period ${item.period_number}` }}</h4>
                  <span class="period-date">{{ formatDate(item.depreciation_date || item.period_start) }}</span>
                </div>
                <div class="card-status">
                  <span :class="['status-indicator', item.depreciation_id ? 'recorded' : 'projected']">
                    {{ item.depreciation_id ? 'Recorded' : 'Projected' }}
                  </span>
                </div>
              </div>

              <div class="card-amounts">
                <div class="amount-item primary">
                  <span class="amount-label">Depreciation</span>
                  <span class="amount-value">${{ formatCurrency(item.depreciation_amount) }}</span>
                </div>
                <div class="amount-item">
                  <span class="amount-label">Accumulated</span>
                  <span class="amount-value">${{ formatCurrency(item.accumulated_depreciation) }}</span>
                </div>
                <div class="amount-item">
                  <span class="amount-label">Remaining</span>
                  <span class="amount-value">${{ formatCurrency(item.remaining_value) }}</span>
                </div>
              </div>

              <div v-if="!item.depreciation_id" class="projection-note">
                <i class="fas fa-info-circle"></i>
                <span>Estimated based on current depreciation rate</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Detailed View -->
        <div v-else-if="viewType === 'detailed'" class="detailed-view">
          <div class="view-header">
            <h3>Detailed Schedule</h3>
            <div class="view-options">
              <button
                @click="groupBy = 'year'"
                :class="['group-btn', { active: groupBy === 'year' }]"
              >
                <i class="fas fa-calendar"></i>
                Group by Year
              </button>
              <button
                @click="groupBy = 'asset'"
                :class="['group-btn', { active: groupBy === 'asset' }]"
              >
                <i class="fas fa-cube"></i>
                Group by Asset
              </button>
            </div>
          </div>

          <div class="detailed-table-container">
            <table class="schedule-table">
              <thead>
                <tr>
                  <th v-if="!selectedAssetId">Asset</th>
                  <th>Period</th>
                  <th>Date</th>
                  <th>Opening Value</th>
                  <th>Depreciation</th>
                  <th>Accumulated</th>
                  <th>Closing Value</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <template v-if="groupBy === 'year'">
                  <template v-for="(yearGroup, year) in groupedScheduleData" :key="year">
                    <tr class="group-header">
                      <td :colspan="selectedAssetId ? 8 : 9" class="group-title">
                        <i class="fas fa-calendar"></i>
                        Year {{ year }}
                        <span class="group-summary">
                          ({{ yearGroup.length }} periods, 
                          ${{ formatCurrency(getYearTotal(yearGroup)) }} total depreciation)
                        </span>
                      </td>
                    </tr>
                    <tr
                      v-for="item in yearGroup"
                      :key="item.depreciation_id || `${item.asset_id}-${item.period_id}`"
                      :class="['schedule-row', { projected: !item.depreciation_id }]"
                    >
                      <td v-if="!selectedAssetId" class="asset-cell">
                        <div class="asset-info">
                          <strong>{{ item.asset_name }}</strong>
                          <small>{{ item.asset_code }}</small>
                        </div>
                      </td>
                      <td>{{ item.period_name || `Period ${item.period_number}` }}</td>
                      <td>{{ formatDate(item.depreciation_date || item.period_start) }}</td>
                      <td class="amount-cell">${{ formatCurrency(item.opening_value) }}</td>
                      <td class="amount-cell primary">${{ formatCurrency(item.depreciation_amount) }}</td>
                      <td class="amount-cell">${{ formatCurrency(item.accumulated_depreciation) }}</td>
                      <td class="amount-cell">${{ formatCurrency(item.remaining_value) }}</td>
                      <td>
                        <span :class="['status-badge', item.depreciation_id ? 'recorded' : 'projected']">
                          {{ item.depreciation_id ? 'Recorded' : 'Projected' }}
                        </span>
                      </td>
                      <td class="actions-cell">
                        <div class="action-buttons">
                          <button
                            v-if="item.depreciation_id"
                            @click="viewDepreciationDetail(item)"
                            class="action-btn view"
                            title="View Details"
                          >
                            <i class="fas fa-eye"></i>
                          </button>
                          <button
                            v-if="item.depreciation_id"
                            @click="viewJournalEntry(item)"
                            class="action-btn journal"
                            title="View Journal Entry"
                          >
                            <i class="fas fa-book"></i>
                          </button>
                          <button
                            v-if="!item.depreciation_id && item.can_calculate"
                            @click="calculateDepreciation(item)"
                            class="action-btn calculate"
                            title="Calculate Depreciation"
                          >
                            <i class="fas fa-calculator"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Projection View -->
        <div v-else-if="viewType === 'projection'" class="projection-view">
          <div class="view-header">
            <h3>Future Depreciation Projection</h3>
            <div class="projection-controls">
              <div class="control-group">
                <label for="projectionYears">Years to Project:</label>
                <select
                  id="projectionYears"
                  v-model="projectionYears"
                  @change="generateProjection"
                  class="form-select"
                >
                  <option value="1">1 Year</option>
                  <option value="2">2 Years</option>
                  <option value="3">3 Years</option>
                  <option value="5">5 Years</option>
                  <option value="10">10 Years</option>
                </select>
              </div>
            </div>
          </div>

          <div class="projection-content">
            <!-- Projection Chart -->
            <div class="chart-container">
              <div class="chart-header">
                <h4>Depreciation Trend</h4>
                <div class="chart-legend">
                  <div class="legend-item">
                    <span class="legend-color recorded"></span>
                    <span>Recorded</span>
                  </div>
                  <div class="legend-item">
                    <span class="legend-color projected"></span>
                    <span>Projected</span>
                  </div>
                </div>
              </div>
              <div class="chart-area">
                <div class="chart-placeholder">
                  <div class="chart-bars">
                    <div
                      v-for="(item, index) in projectionData"
                      :key="index"
                      class="chart-bar"
                      :style="{ height: (item.depreciation_amount / maxDepreciation * 100) + '%' }"
                      :class="{ projected: !item.depreciation_id }"
                    >
                      <div class="bar-tooltip">
                        <div class="tooltip-period">{{ item.period_name || `Year ${item.year}` }}</div>
                        <div class="tooltip-amount">${{ formatCurrency(item.depreciation_amount) }}</div>
                      </div>
                    </div>
                  </div>
                  <div class="chart-axis">
                    <span
                      v-for="(item, index) in projectionData"
                      :key="index"
                      class="axis-label"
                    >
                      {{ item.year }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Projection Summary -->
            <div class="projection-summary">
              <h4>Projection Summary</h4>
              <div class="summary-stats-grid">
                <div class="summary-stat">
                  <div class="stat-icon">
                    <i class="fas fa-calendar-plus"></i>
                  </div>
                  <div class="stat-content">
                    <h5>Total Future Depreciation</h5>
                    <p>${{ formatCurrency(totalFutureDepreciation) }}</p>
                  </div>
                </div>
                <div class="summary-stat">
                  <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                  </div>
                  <div class="stat-content">
                    <h5>Years to Full Depreciation</h5>
                    <p>{{ yearsToFullDepreciation }} years</p>
                  </div>
                </div>
                <div class="summary-stat">
                  <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                  </div>
                  <div class="stat-content">
                    <h5>Average Annual Depreciation</h5>
                    <p>${{ formatCurrency(averageAnnualDepreciation) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Export Modal -->
      <div v-if="showExportModal" class="modal-overlay" @click="closeExportModal">
        <div class="modal-content export-modal" @click.stop>
          <div class="modal-header">
            <h3>Export Depreciation Schedule</h3>
            <button @click="closeExportModal" class="close-btn">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="export-options">
              <div class="option-group">
                <label>Export Format:</label>
                <div class="radio-group">
                  <label class="radio-option">
                    <input v-model="exportFormat" type="radio" value="excel" />
                    <span class="radio-custom"></span>
                    <i class="fas fa-file-excel"></i>
                    Excel (.xlsx)
                  </label>
                  <label class="radio-option">
                    <input v-model="exportFormat" type="radio" value="pdf" />
                    <span class="radio-custom"></span>
                    <i class="fas fa-file-pdf"></i>
                    PDF Report
                  </label>
                  <label class="radio-option">
                    <input v-model="exportFormat" type="radio" value="csv" />
                    <span class="radio-custom"></span>
                    <i class="fas fa-file-csv"></i>
                    CSV Data
                  </label>
                </div>
              </div>

              <div class="option-group">
                <label>Include:</label>
                <div class="checkbox-group">
                  <label class="checkbox-option">
                    <input v-model="exportOptions.includeProjections" type="checkbox" />
                    <span class="checkbox-custom"></span>
                    Future Projections
                  </label>
                  <label class="checkbox-option">
                    <input v-model="exportOptions.includeCharts" type="checkbox" />
                    <span class="checkbox-custom"></span>
                    Charts & Visualizations
                  </label>
                  <label class="checkbox-option">
                    <input v-model="exportOptions.includeAssetDetails" type="checkbox" />
                    <span class="checkbox-custom"></span>
                    Asset Details
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="closeExportModal" class="btn btn-secondary">Cancel</button>
            <button @click="performExport" class="btn btn-primary" :disabled="exporting">
              <i v-if="exporting" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-download"></i>
              {{ exporting ? 'Exporting...' : 'Export' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'DepreciationSchedule',
  components: {
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    
    // Reactive state
    const loading = ref(false)
    const exporting = ref(false)
    const assets = ref([])
    const scheduleData = ref([])
    const projectionData = ref([])
    const selectedAssetId = ref(route.params.assetId || '')
    const selectedAsset = ref(null)
    const selectedYear = ref('')
    const statusFilter = ref('')
    const viewType = ref('summary')
    const groupBy = ref('year')
    const projectionYears = ref(3)
    
    // Modal states
    const showExportModal = ref(false)
    const exportFormat = ref('excel')
    const exportOptions = ref({
      includeProjections: true,
      includeCharts: true,
      includeAssetDetails: true
    })

    // Computed properties
    const availableYears = computed(() => {
      const years = new Set()
      scheduleData.value.forEach(item => {
        if (item.depreciation_date) {
          years.add(new Date(item.depreciation_date).getFullYear())
        }
      })
      return Array.from(years).sort((a, b) => b - a)
    })

    const totalDepreciated = computed(() => {
      if (!selectedAsset.value) return 0
      return selectedAsset.value.acquisition_cost - selectedAsset.value.current_value
    })

    const depreciationProgress = computed(() => {
      if (!selectedAsset.value || !selectedAsset.value.acquisition_cost) return 0
      return Math.round((totalDepreciated.value / selectedAsset.value.acquisition_cost) * 100)
    })

    const estimatedYearsRemaining = computed(() => {
      if (!selectedAsset.value || selectedAsset.value.depreciation_rate === 0) return 0
      const remainingPercentage = 100 - depreciationProgress.value
      return Math.ceil(remainingPercentage / selectedAsset.value.depreciation_rate)
    })

    const totalScheduledDepreciation = computed(() => {
      return scheduleData.value.reduce((sum, item) => sum + (item.depreciation_amount || 0), 0)
    })

    const groupedScheduleData = computed(() => {
      if (groupBy.value === 'year') {
        const groups = {}
        scheduleData.value.forEach(item => {
          const year = item.depreciation_date ? 
            new Date(item.depreciation_date).getFullYear() : 
            new Date().getFullYear()
          if (!groups[year]) groups[year] = []
          groups[year].push(item)
        })
        return groups
      }
      return {}
    })

    const maxDepreciation = computed(() => {
      return Math.max(...projectionData.value.map(item => item.depreciation_amount || 0))
    })

    const totalFutureDepreciation = computed(() => {
      return projectionData.value
        .filter(item => !item.depreciation_id)
        .reduce((sum, item) => sum + (item.depreciation_amount || 0), 0)
    })

    const yearsToFullDepreciation = computed(() => {
      if (!selectedAsset.value) return 0
      return estimatedYearsRemaining.value
    })

    const averageAnnualDepreciation = computed(() => {
      if (!selectedAsset.value) return 0
      return (selectedAsset.value.current_value * selectedAsset.value.depreciation_rate) / 100
    })

    // Methods
    const fetchAssets = async () => {
      try {
        const response = await axios.get('/accounting/fixed-assets')
        assets.value = response.data.data || response.data
        
        if (selectedAssetId.value) {
          selectedAsset.value = assets.value.find(a => a.asset_id == selectedAssetId.value)
        }
      } catch (error) {
        console.error('Error fetching assets:', error)
      }
    }

    const loadSchedule = async () => {
      try {
        loading.value = true
        
        const params = {}
        if (selectedAssetId.value) params.asset_id = selectedAssetId.value
        if (selectedYear.value) params.year = selectedYear.value
        if (statusFilter.value) params.status = statusFilter.value

        const response = await axios.get('/accounting/asset-depreciations', { params })
        scheduleData.value = response.data.data || response.data

        // If single asset selected, load asset details
        if (selectedAssetId.value && !selectedAsset.value) {
          const assetResponse = await axios.get(`/accounting/fixed-assets/${selectedAssetId.value}`)
          selectedAsset.value = assetResponse.data.data
        }

        // Generate projections for current view
        if (viewType.value === 'projection') {
          generateProjection()
        }
        
      } catch (error) {
        console.error('Error loading schedule:', error)
      } finally {
        loading.value = false
      }
    }

    const generateProjection = () => {
      if (!selectedAsset.value) return

      const projected = []
      const currentYear = new Date().getFullYear()
      
      for (let i = 1; i <= projectionYears.value; i++) {
        const year = currentYear + i
        const depreciationAmount = (selectedAsset.value.current_value * selectedAsset.value.depreciation_rate) / 100
        
        projected.push({
          year,
          period_name: `Year ${year}`,
          depreciation_amount: depreciationAmount,
          accumulated_depreciation: totalDepreciated.value + (depreciationAmount * i),
          remaining_value: Math.max(0, selectedAsset.value.current_value - (depreciationAmount * i)),
          depreciation_id: null // Indicates projection
        })
      }

      // Combine actual data with projections
      const actualData = scheduleData.value.map(item => ({
        ...item,
        year: new Date(item.depreciation_date).getFullYear()
      }))

      projectionData.value = [...actualData, ...projected]
    }

    const refreshView = () => {
      if (viewType.value === 'projection') {
        generateProjection()
      }
    }

    const getYearTotal = (yearData) => {
      return yearData.reduce((sum, item) => sum + (item.depreciation_amount || 0), 0)
    }

    const viewDepreciationDetail = (depreciation) => {
      router.push(`/accounting/asset-depreciations/${depreciation.depreciation_id}`)
    }

    const viewJournalEntry = (depreciation) => {
      router.push(`/accounting/asset-depreciations/journal/${depreciation.depreciation_id}`)
    }

    const calculateDepreciation = (item) => {
      router.push({
        path: '/accounting/depreciations/calculate',
        query: { asset_id: item.asset_id, period_id: item.period_id }
      })
    }

    const exportSchedule = () => {
      showExportModal.value = true
    }

    const closeExportModal = () => {
      showExportModal.value = false
    }

    const performExport = async () => {
      try {
        exporting.value = true
        
        const exportData = {
          format: exportFormat.value,
          options: exportOptions.value,
          assetId: selectedAssetId.value,
          year: selectedYear.value,
          viewType: viewType.value
        }

        const response = await axios.post('/accounting/asset-depreciations/export', exportData, {
          responseType: 'blob'
        })

        // Create download link
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        
        const filename = `depreciation-schedule-${new Date().toISOString().split('T')[0]}.${
          exportFormat.value === 'excel' ? 'xlsx' : exportFormat.value
        }`
        
        link.setAttribute('download', filename)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)

        closeExportModal()
        
      } catch (error) {
        console.error('Error exporting schedule:', error)
      } finally {
        exporting.value = false
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

    // Watchers
    watch(selectedAssetId, (newId) => {
      if (newId) {
        selectedAsset.value = assets.value.find(a => a.asset_id == newId)
        loadSchedule()
      } else {
        selectedAsset.value = null
      }
    })

    // Lifecycle
    onMounted(() => {
      Promise.all([
        fetchAssets(),
        loadSchedule()
      ])
    })

    return {
      loading,
      exporting,
      assets,
      scheduleData,
      projectionData,
      selectedAssetId,
      selectedAsset,
      selectedYear,
      statusFilter,
      viewType,
      groupBy,
      projectionYears,
      showExportModal,
      exportFormat,
      exportOptions,
      availableYears,
      totalDepreciated,
      depreciationProgress,
      estimatedYearsRemaining,
      totalScheduledDepreciation,
      groupedScheduleData,
      maxDepreciation,
      totalFutureDepreciation,
      yearsToFullDepreciation,
      averageAnnualDepreciation,
      loadSchedule,
      generateProjection,
      refreshView,
      getYearTotal,
      viewDepreciationDetail,
      viewJournalEntry,
      calculateDepreciation,
      exportSchedule,
      closeExportModal,
      performExport,
      formatCurrency,
      formatDate
    }
  }
}
</script>

<style scoped>
.depreciation-schedule-page {
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

/* Filters */
.filters-section {
  margin-bottom: 2rem;
}

.filter-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
}

.filter-header {
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.filter-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
}

.filter-header i {
  margin-right: 0.5rem;
  color: #6366f1;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.filter-group label {
  display: block;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.form-select {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white;
}

.form-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Asset Overview */
.asset-overview {
  margin-bottom: 2rem;
}

.overview-card {
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

.asset-main-info h2 {
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

.detail-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 16px;
  border: 2px solid;
  transition: transform 0.3s ease;
}

.detail-card:hover {
  transform: translateY(-3px);
}

.detail-card.acquisition {
  border-color: #3b82f6;
  background: #eff6ff;
}

.detail-card.current {
  border-color: #10b981;
  background: #f0fdf4;
}

.detail-card.rate {
  border-color: #f59e0b;
  background: #fffbeb;
}

.detail-card.accumulated {
  border-color: #ef4444;
  background: #fef2f2;
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

.detail-card.acquisition .detail-icon {
  background: #3b82f6;
}

.detail-card.current .detail-icon {
  background: #10b981;
}

.detail-card.rate .detail-icon {
  background: #f59e0b;
}

.detail-card.accumulated .detail-icon {
  background: #ef4444;
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

/* Progress Section */
.progress-section {
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
  height: 20px;
  background: #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  transition: width 1s ease;
}

.progress-markers {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  color: #6b7280;
}

.progress-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.progress-item {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: white;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.progress-item .label {
  color: #6b7280;
  font-weight: 500;
}

.progress-item .value {
  font-weight: 600;
  color: #1f2937;
}

/* Schedule Content */
.schedule-content {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

/* View Headers */
.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.view-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
}

.summary-stats {
  display: flex;
  gap: 2rem;
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.stat-label {
  font-size: 0.85rem;
  color: #6b7280;
  font-weight: 500;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1f2937;
}

.view-options {
  display: flex;
  gap: 0.5rem;
}

.group-btn {
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

.group-btn:hover,
.group-btn.active {
  border-color: #6366f1;
  background: #6366f1;
  color: white;
}

/* Summary View */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  transition: all 0.3s ease;
  background: #fafbfc;
}

.summary-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.summary-card.projected {
  border-color: #f59e0b;
  background: #fffbeb;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.period-info h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.period-date {
  color: #6b7280;
  font-size: 0.85rem;
}

.status-indicator {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-indicator.recorded {
  background: #dcfce7;
  color: #166534;
}

.status-indicator.projected {
  background: #fef3c7;
  color: #92400e;
}

.card-amounts {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.amount-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.amount-item:last-child {
  border-bottom: none;
}

.amount-item.primary {
  background: #f0f4ff;
  margin: 0 -1rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-weight: 600;
}

.amount-label {
  color: #6b7280;
  font-weight: 500;
  font-size: 0.9rem;
}

.amount-value {
  font-weight: 600;
  color: #1f2937;
}

.projection-note {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1rem;
  padding: 0.75rem;
  background: #fef3c7;
  border-radius: 8px;
  color: #92400e;
  font-size: 0.85rem;
}

/* Detailed Table */
.detailed-table-container {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.schedule-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.schedule-table th {
  background: #f8fafc;
  color: #374151;
  font-weight: 600;
  padding: 1rem;
  text-align: left;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.9rem;
}

.schedule-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.group-header td {
  background: #f0f4ff;
  font-weight: 600;
  color: #6366f1;
  border-top: 2px solid #c7d2fe;
  border-bottom: 2px solid #c7d2fe;
}

.group-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.group-summary {
  color: #6b7280;
  font-weight: 400;
  font-size: 0.9rem;
}

.schedule-row {
  transition: background-color 0.2s ease;
}

.schedule-row:hover {
  background: #f8fafc;
}

.schedule-row.projected {
  background: #fffbeb;
}

.asset-cell {
  min-width: 180px;
}

.asset-info strong {
  display: block;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.asset-info small {
  color: #6b7280;
  background: #f3f4f6;
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-size: 0.75rem;
}

.amount-cell {
  font-weight: 600;
  color: #059669;
  text-align: right;
}

.amount-cell.primary {
  color: #6366f1;
  background: #f0f4ff;
  font-weight: 700;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.8rem;
}

.action-btn.view {
  background: #dbeafe;
  color: #1d4ed8;
}

.action-btn.journal {
  background: #fef3c7;
  color: #d97706;
}

.action-btn.calculate {
  background: #dcfce7;
  color: #166534;
}

.action-btn:hover {
  transform: scale(1.1);
}

/* Projection View */
.projection-controls {
  display: flex;
  gap: 1rem;
}

.control-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.control-group label {
  font-weight: 500;
  color: #374151;
}

.projection-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Chart */
.chart-container {
  background: #f8fafc;
  border-radius: 16px;
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

.legend-color.recorded {
  background: #6366f1;
}

.legend-color.projected {
  background: #f59e0b;
}

.chart-area {
  height: 300px;
  display: flex;
  flex-direction: column;
}

.chart-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.chart-bars {
  flex: 1;
  display: flex;
  align-items: end;
  gap: 8px;
  padding: 1rem 0;
}

.chart-bar {
  flex: 1;
  background: #6366f1;
  border-radius: 4px 4px 0 0;
  min-height: 20px;
  position: relative;
  transition: all 0.3s ease;
  cursor: pointer;
}

.chart-bar.projected {
  background: #f59e0b;
}

.chart-bar:hover {
  opacity: 0.8;
}

.bar-tooltip {
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
}

.chart-bar:hover .bar-tooltip {
  opacity: 1;
}

.chart-axis {
  display: flex;
  justify-content: space-between;
  padding-top: 0.5rem;
  border-top: 1px solid #e2e8f0;
}

.axis-label {
  font-size: 0.8rem;
  color: #6b7280;
  text-align: center;
  flex: 1;
}

/* Projection Summary */
.projection-summary {
  background: #f0fdf4;
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid #bbf7d0;
}

.projection-summary h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
}

.summary-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.summary-stat {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #bbf7d0;
}

.summary-stat .stat-icon {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.summary-stat .stat-content h5 {
  font-size: 0.9rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
  font-weight: 500;
}

.summary-stat .stat-content p {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
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

.btn-success:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

/* Loading & Empty States */
.loading-container {
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

.empty-state {
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

.empty-state h3 {
  font-size: 1.5rem;
  color: #374151;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: #6b7280;
  font-size: 1.1rem;
}

/* Export Modal */
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
}

.export-options {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.option-group label {
  display: block;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.75rem;
}

.radio-group,
.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.radio-option,
.checkbox-option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  padding: 0.75rem;
  border-radius: 8px;
  border: 2px solid #e2e8f0;
  transition: all 0.3s ease;
}

.radio-option:hover,
.checkbox-option:hover {
  border-color: #6366f1;
  background: #f0f4ff;
}

.radio-option input,
.checkbox-option input {
  display: none;
}

.radio-custom,
.checkbox-custom {
  width: 20px;
  height: 20px;
  border: 2px solid #e2e8f0;
  border-radius: 50%;
  position: relative;
}

.checkbox-custom {
  border-radius: 4px;
}

.radio-option input:checked + .radio-custom,
.checkbox-option input:checked + .checkbox-custom {
  border-color: #6366f1;
  background: #6366f1;
}

.radio-option input:checked + .radio-custom::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 8px;
  height: 8px;
  background: white;
  border-radius: 50%;
}

.checkbox-option input:checked + .checkbox-custom::after {
  content: '✓';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: white;
  font-size: 12px;
  font-weight: bold;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 0 1.5rem 1.5rem 1.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .depreciation-schedule-page {
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

  .filter-row {
    grid-template-columns: 1fr;
  }

  .asset-header {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .asset-details-grid {
    grid-template-columns: 1fr;
  }

  .progress-details {
    grid-template-columns: 1fr;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .summary-stats {
    flex-direction: column;
    gap: 1rem;
  }

  .view-options {
    flex-direction: column;
  }

  .chart-bars {
    gap: 4px;
  }

  .summary-stats-grid {
    grid-template-columns: 1fr;
  }

  .projection-controls {
    flex-direction: column;
  }

  .radio-group,
  .checkbox-group {
    gap: 0.5rem;
  }

  .modal-footer {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .schedule-content {
    padding: 1rem;
  }

  .asset-main-info h2 {
    font-size: 1.5rem;
  }

  .detail-card {
    flex-direction: column;
    text-align: center;
  }

  .amount-item.primary {
    margin: 0;
    padding: 0.75rem;
  }

  .chart-area {
    height: 200px;
  }

  .detailed-table-container {
    font-size: 0.8rem;
  }

  .schedule-table th,
  .schedule-table td {
    padding: 0.5rem;
  }
}
</style>