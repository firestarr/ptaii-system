<template>
  <AppLayout>
    <div class="asset-report-page">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <div class="breadcrumb">
              <router-link to="/accounting/fixed-assets" class="breadcrumb-link">
                <i class="fas fa-building"></i>
                Fixed Assets
              </router-link>
              <i class="fas fa-chevron-right breadcrumb-separator"></i>
              <span class="breadcrumb-current">Asset Register Report</span>
            </div>
            <h1 class="page-title">
              <i class="fas fa-file-alt"></i>
              Fixed Asset Register Report
            </h1>
            <p class="page-subtitle">
              Comprehensive asset register with depreciation details and financial summary
            </p>
          </div>
          <div class="header-actions">
            <button @click="goBack" class="btn btn-outline">
              <i class="fas fa-arrow-left"></i>
              Back to Assets
            </button>
          </div>
        </div>
      </div>

      <!-- Report Controls -->
      <div class="report-controls">
        <div class="controls-grid">
          <!-- Date Range Filter -->
          <div class="control-group">
            <label class="control-label">Report Period</label>
            <div class="date-range">
              <input 
                v-model="filters.startDate" 
                type="date" 
                class="form-input"
                @change="generateReport"
              >
              <span class="date-separator">to</span>
              <input 
                v-model="filters.endDate" 
                type="date" 
                class="form-input"
                @change="generateReport"
              >
            </div>
          </div>

          <!-- Category Filter -->
          <div class="control-group">
            <label class="control-label">Category</label>
            <select v-model="filters.category" @change="generateReport" class="form-select">
              <option value="">All Categories</option>
              <option v-for="category in categories" :key="category" :value="category">
                {{ category }}
              </option>
            </select>
          </div>

          <!-- Status Filter -->
          <div class="control-group">
            <label class="control-label">Status</label>
            <select v-model="filters.status" @change="generateReport" class="form-select">
              <option value="">All Status</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="Disposed">Disposed</option>
              <option value="Under Maintenance">Under Maintenance</option>
            </select>
          </div>

          <!-- Export Actions -->
          <div class="control-group">
            <label class="control-label">Export Options</label>
            <div class="export-buttons">
              <button @click="exportPDF" class="btn btn-outline btn-sm">
                <i class="fas fa-file-pdf"></i>
                PDF
              </button>
              <button @click="exportExcel" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i>
                Excel
              </button>
              <button @click="printReport" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i>
                Print
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Report Summary -->
      <div class="report-summary">
        <div class="summary-grid">
          <div class="summary-card total">
            <div class="card-header">
              <h3>Total Assets</h3>
              <div class="card-icon">
                <i class="fas fa-building"></i>
              </div>
            </div>
            <div class="card-content">
              <p class="summary-value">{{ reportData.summary.totalAssets }}</p>
              <div class="summary-breakdown">
                <span class="breakdown-item">
                  <i class="fas fa-circle active"></i>
                  {{ reportData.summary.activeAssets }} Active
                </span>
                <span class="breakdown-item">
                  <i class="fas fa-circle inactive"></i>
                  {{ reportData.summary.inactiveAssets }} Inactive
                </span>
              </div>
            </div>
          </div>

          <div class="summary-card acquisition">
            <div class="card-header">
              <h3>Total Acquisition Cost</h3>
              <div class="card-icon">
                <i class="fas fa-dollar-sign"></i>
              </div>
            </div>
            <div class="card-content">
              <p class="summary-value">${{ formatNumber(reportData.summary.totalAcquisitionCost) }}</p>
              <div class="summary-trend">
                <span class="trend-label">Original investment value</span>
              </div>
            </div>
          </div>

          <div class="summary-card current">
            <div class="card-header">
              <h3>Current Book Value</h3>
              <div class="card-icon">
                <i class="fas fa-chart-line"></i>
              </div>
            </div>
            <div class="card-content">
              <p class="summary-value">${{ formatNumber(reportData.summary.totalCurrentValue) }}</p>
              <div class="summary-trend">
                <span class="trend-percentage">{{ currentValuePercentage }}%</span>
                <span class="trend-label">of original cost</span>
              </div>
            </div>
          </div>

          <div class="summary-card depreciation">
            <div class="card-header">
              <h3>Total Depreciation</h3>
              <div class="card-icon">
                <i class="fas fa-chart-line-down"></i>
              </div>
            </div>
            <div class="card-content">
              <p class="summary-value">${{ formatNumber(reportData.summary.totalDepreciation) }}</p>
              <div class="summary-trend">
                <span class="trend-percentage">{{ depreciationPercentage }}%</span>
                <span class="trend-label">depreciation rate</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="category-breakdown">
        <div class="section-header">
          <h2>
            <i class="fas fa-chart-pie"></i>
            Asset Breakdown by Category
          </h2>
        </div>
        <div class="breakdown-grid">
          <div 
            v-for="breakdown in reportData.categoryBreakdown" 
            :key="breakdown.category"
            class="breakdown-card"
          >
            <div class="breakdown-header">
              <div class="category-icon">
                <i :class="getAssetIcon(breakdown.category)"></i>
              </div>
              <div class="category-info">
                <h4>{{ breakdown.category }}</h4>
                <p>{{ breakdown.count }} asset{{ breakdown.count > 1 ? 's' : '' }}</p>
              </div>
            </div>
            <div class="breakdown-details">
              <div class="detail-row">
                <span class="detail-label">Acquisition Cost</span>
                <span class="detail-value">${{ formatNumber(breakdown.acquisitionCost) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Current Value</span>
                <span class="detail-value">${{ formatNumber(breakdown.currentValue) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Depreciation</span>
                <span class="detail-value depreciation-value">
                  ${{ formatNumber(breakdown.depreciation) }}
                </span>
              </div>
            </div>
            <div class="breakdown-progress">
              <div class="progress-bar">
                <div 
                  class="progress-fill"
                  :style="{ width: (breakdown.currentValue / breakdown.acquisitionCost * 100) + '%' }"
                ></div>
              </div>
              <span class="progress-label">
                {{ Math.round(breakdown.currentValue / breakdown.acquisitionCost * 100) }}% remaining value
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Detailed Asset Register -->
      <div class="asset-register">
        <div class="section-header">
          <h2>
            <i class="fas fa-list"></i>
            Detailed Asset Register
          </h2>
          <div class="section-actions">
            <div class="search-box">
              <i class="fas fa-search"></i>
              <input 
                v-model="searchQuery" 
                type="text" 
                placeholder="Search assets..."
                @input="filterAssets"
              >
            </div>
            <select v-model="sortBy" @change="sortAssets" class="sort-select">
              <option value="name">Sort by Name</option>
              <option value="asset_code">Sort by Code</option>
              <option value="category">Sort by Category</option>
              <option value="acquisition_date">Sort by Date</option>
              <option value="current_value">Sort by Value</option>
            </select>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Generating report...</p>
        </div>

        <!-- Asset Table -->
        <div v-else class="register-table-container">
          <table class="register-table">
            <thead>
              <tr>
                <th class="sortable" @click="setSortBy('asset_code')">
                  Asset Code
                  <i v-if="sortBy === 'asset_code'" :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                </th>
                <th class="sortable" @click="setSortBy('name')">
                  Asset Name
                  <i v-if="sortBy === 'name'" :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                </th>
                <th class="sortable" @click="setSortBy('category')">
                  Category
                  <i v-if="sortBy === 'category'" :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                </th>
                <th class="sortable" @click="setSortBy('acquisition_date')">
                  Acquisition Date
                  <i v-if="sortBy === 'acquisition_date'" :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                </th>
                <th class="sortable text-right" @click="setSortBy('acquisition_cost')">
                  Acquisition Cost
                  <i v-if="sortBy === 'acquisition_cost'" :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                </th>
                <th class="sortable text-right" @click="setSortBy('current_value')">
                  Current Value
                  <i v-if="sortBy === 'current_value'" :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                </th>
                <th class="text-right">Depreciation</th>
                <th class="text-center">Depreciation Rate</th>
                <th class="text-center">Status</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="asset in filteredAssets" :key="asset.asset_id" class="asset-row">
                <td class="asset-code">{{ asset.asset_code }}</td>
                <td class="asset-name">
                  <div class="name-cell">
                    <div class="asset-icon-mini">
                      <i :class="getAssetIcon(asset.category)"></i>
                    </div>
                    <span>{{ asset.name }}</span>
                  </div>
                </td>
                <td class="category">{{ asset.category }}</td>
                <td class="acquisition-date">{{ formatDate(asset.acquisition_date) }}</td>
                <td class="text-right acquisition-cost">${{ formatNumber(asset.acquisition_cost) }}</td>
                <td class="text-right current-value">${{ formatNumber(asset.current_value) }}</td>
                <td class="text-right depreciation-amount">
                  ${{ formatNumber(asset.acquisition_cost - asset.current_value) }}
                </td>
                <td class="text-center depreciation-rate">{{ asset.depreciation_rate }}%</td>
                <td class="text-center status">
                  <span class="status-badge" :class="asset.status.toLowerCase().replace(' ', '-')">
                    {{ asset.status }}
                  </span>
                </td>
                <td class="text-center actions">
                  <button @click="viewAsset(asset.asset_id)" class="action-btn view">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button @click="editAsset(asset.asset_id)" class="action-btn edit">
                    <i class="fas fa-edit"></i>
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="totals-row">
                <td colspan="4" class="totals-label">
                  <strong>TOTALS ({{ filteredAssets.length }} assets)</strong>
                </td>
                <td class="text-right">
                  <strong>${{ formatNumber(totalAcquisitionCost) }}</strong>
                </td>
                <td class="text-right">
                  <strong>${{ formatNumber(totalCurrentValue) }}</strong>
                </td>
                <td class="text-right">
                  <strong>${{ formatNumber(totalDepreciationAmount) }}</strong>
                </td>
                <td colspan="3"></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && filteredAssets.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-search"></i>
          </div>
          <h3>No Assets Found</h3>
          <p>No assets match your current filter criteria.</p>
          <button @click="clearFilters" class="btn btn-outline">
            <i class="fas fa-times"></i>
            Clear Filters
          </button>
        </div>
      </div>

      <!-- Report Footer -->
      <div class="report-footer">
        <div class="footer-content">
          <div class="footer-info">
            <p class="report-generated">
              Report generated on {{ formatDateTime(new Date()) }}
            </p>
            <p class="report-period">
              Period: {{ formatDate(filters.startDate) }} to {{ formatDate(filters.endDate) }}
            </p>
          </div>
          <div class="footer-logo">
            <div class="company-logo">
              <i class="fas fa-building"></i>
              <span>Asset Management System</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'FixedAssetReport',
  components: {
  },
  setup() {
    const router = useRouter()
    
    // Reactive data
    const loading = ref(false)
    const searchQuery = ref('')
    const sortBy = ref('name')
    const sortDirection = ref('asc')
    
    const filters = reactive({
      startDate: '',
      endDate: '',
      category: '',
      status: ''
    })
    
    const reportData = reactive({
      assets: [],
      summary: {
        totalAssets: 0,
        activeAssets: 0,
        inactiveAssets: 0,
        totalAcquisitionCost: 0,
        totalCurrentValue: 0,
        totalDepreciation: 0
      },
      categoryBreakdown: []
    })
    
    const categories = ref([])
    
    // Computed
    const filteredAssets = computed(() => {
      let assets = [...reportData.assets]
      
      // Apply search filter
      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        assets = assets.filter(asset => 
          asset.name.toLowerCase().includes(query) ||
          asset.asset_code.toLowerCase().includes(query) ||
          asset.category.toLowerCase().includes(query)
        )
      }
      
      // Apply sorting
      assets.sort((a, b) => {
        let aValue = a[sortBy.value]
        let bValue = b[sortBy.value]
        
        // Handle numeric sorting
        if (sortBy.value === 'acquisition_cost' || sortBy.value === 'current_value') {
          aValue = parseFloat(aValue) || 0
          bValue = parseFloat(bValue) || 0
        }
        
        // Handle date sorting
        if (sortBy.value === 'acquisition_date') {
          aValue = new Date(aValue)
          bValue = new Date(bValue)
        }
        
        // Handle string sorting
        if (typeof aValue === 'string') {
          aValue = aValue.toLowerCase()
          bValue = bValue.toLowerCase()
        }
        
        if (sortDirection.value === 'asc') {
          return aValue > bValue ? 1 : -1
        } else {
          return aValue < bValue ? 1 : -1
        }
      })
      
      return assets
    })
    
    const totalAcquisitionCost = computed(() => {
      return filteredAssets.value.reduce((sum, asset) => sum + parseFloat(asset.acquisition_cost || 0), 0)
    })
    
    const totalCurrentValue = computed(() => {
      return filteredAssets.value.reduce((sum, asset) => sum + parseFloat(asset.current_value || 0), 0)
    })
    
    const totalDepreciationAmount = computed(() => {
      return totalAcquisitionCost.value - totalCurrentValue.value
    })
    
    const currentValuePercentage = computed(() => {
      if (reportData.summary.totalAcquisitionCost === 0) return 0
      return Math.round((reportData.summary.totalCurrentValue / reportData.summary.totalAcquisitionCost) * 100)
    })
    
    const depreciationPercentage = computed(() => {
      if (reportData.summary.totalAcquisitionCost === 0) return 0
      return Math.round((reportData.summary.totalDepreciation / reportData.summary.totalAcquisitionCost) * 100)
    })
    
    // Methods
    const generateReport = async () => {
      try {
        loading.value = true
        
        const params = {
          start_date: filters.startDate,
          end_date: filters.endDate,
          category: filters.category,
          status: filters.status
        }
        
        const response = await axios.get('/accounting/fixed-assets', { params })
        const assets = response.data.data
        
        reportData.assets = assets
        calculateSummary(assets)
        calculateCategoryBreakdown(assets)
        
      } catch (error) {
        console.error('Error generating report:', error)
      } finally {
        loading.value = false
      }
    }
    
    const calculateSummary = (assets) => {
      reportData.summary.totalAssets = assets.length
      reportData.summary.activeAssets = assets.filter(asset => asset.status === 'Active').length
      reportData.summary.inactiveAssets = assets.filter(asset => asset.status !== 'Active').length
      reportData.summary.totalAcquisitionCost = assets.reduce((sum, asset) => sum + parseFloat(asset.acquisition_cost || 0), 0)
      reportData.summary.totalCurrentValue = assets.reduce((sum, asset) => sum + parseFloat(asset.current_value || 0), 0)
      reportData.summary.totalDepreciation = reportData.summary.totalAcquisitionCost - reportData.summary.totalCurrentValue
    }
    
    const calculateCategoryBreakdown = (assets) => {
      const breakdown = {}
      
      assets.forEach(asset => {
        if (!breakdown[asset.category]) {
          breakdown[asset.category] = {
            category: asset.category,
            count: 0,
            acquisitionCost: 0,
            currentValue: 0,
            depreciation: 0
          }
        }
        
        breakdown[asset.category].count++
        breakdown[asset.category].acquisitionCost += parseFloat(asset.acquisition_cost || 0)
        breakdown[asset.category].currentValue += parseFloat(asset.current_value || 0)
      })
      
      // Calculate depreciation for each category
      Object.keys(breakdown).forEach(category => {
        breakdown[category].depreciation = breakdown[category].acquisitionCost - breakdown[category].currentValue
      })
      
      reportData.categoryBreakdown = Object.values(breakdown)
      
      // Extract unique categories
      categories.value = [...new Set(assets.map(asset => asset.category))]
    }
    
    const setSortBy = (field) => {
      if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
      } else {
        sortBy.value = field
        sortDirection.value = 'asc'
      }
    }
    
    const sortAssets = () => {
      // Sorting is handled by the computed property
    }
    
    const filterAssets = () => {
      // Filtering is handled by the computed property
    }
    
    const clearFilters = () => {
      searchQuery.value = ''
      filters.category = ''
      filters.status = ''
    }
    
    const getAssetIcon = (category) => {
      const iconMap = {
        'Building': 'fas fa-building',
        'Equipment': 'fas fa-cogs',
        'Vehicle': 'fas fa-car',
        'Furniture': 'fas fa-chair',
        'Computer': 'fas fa-laptop',
        'Machinery': 'fas fa-industry',
        default: 'fas fa-cube'
      }
      return iconMap[category] || iconMap.default
    }
    
    const formatNumber = (value) => {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(value || 0)
    }
    
    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }
    
    const formatDateTime = (date) => {
      return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }
    
    const viewAsset = (id) => {
      router.push(`/accounting/fixed-assets/${id}`)
    }
    
    const editAsset = (id) => {
      router.push(`/accounting/fixed-assets/${id}/edit`)
    }
    
    const exportPDF = () => {
      // Implement PDF export functionality
      console.log('Exporting to PDF...')
    }
    
    const exportExcel = () => {
      // Implement Excel export functionality
      console.log('Exporting to Excel...')
    }
    
    const printReport = () => {
      window.print()
    }
    
    const goBack = () => {
      router.push('/accounting/fixed-assets')
    }
    
    // Initialize date filters
    const initializeFilters = () => {
      const now = new Date()
      const startOfYear = new Date(now.getFullYear(), 0, 1)
      
      filters.startDate = startOfYear.toISOString().split('T')[0]
      filters.endDate = now.toISOString().split('T')[0]
    }
    
    // Lifecycle
    onMounted(() => {
      initializeFilters()
      generateReport()
    })
    
    return {
      loading,
      searchQuery,
      sortBy,
      sortDirection,
      filters,
      reportData,
      categories,
      filteredAssets,
      totalAcquisitionCost,
      totalCurrentValue,
      totalDepreciationAmount,
      currentValuePercentage,
      depreciationPercentage,
      generateReport,
      setSortBy,
      sortAssets,
      filterAssets,
      clearFilters,
      getAssetIcon,
      formatNumber,
      formatDate,
      formatDateTime,
      viewAsset,
      editAsset,
      exportPDF,
      exportExcel,
      printReport,
      goBack
    }
  }
}
</script>

<style scoped>
.asset-report-page {
  max-width: 1600px;
  margin: 0 auto;
  padding: 2rem;
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

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.breadcrumb-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6366f1;
  text-decoration: none;
  transition: all 0.3s ease;
}

.breadcrumb-link:hover {
  color: #4f46e5;
}

.breadcrumb-separator {
  color: #94a3b8;
  font-size: 0.75rem;
}

.breadcrumb-current {
  color: #64748b;
  font-weight: 500;
}

.title-section h1 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 2.25rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.title-section h1 i {
  color: #6366f1;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

/* Buttons */
.btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 10px;
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-outline {
  background: white;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.btn-outline:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

/* Report Controls */
.report-controls {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
}

.controls-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  align-items: end;
}

.control-group {
  display: flex;
  flex-direction: column;
}

.control-label {
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.date-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-separator {
  color: #64748b;
  font-weight: 500;
}

.form-input,
.form-select {
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 1rem;
  transition: all 0.3s ease;
  background: white;
  flex: 1;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.export-buttons {
  display: flex;
  gap: 0.5rem;
}

/* Report Summary */
.report-summary {
  margin-bottom: 2rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.summary-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.summary-card.total::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.summary-card.acquisition::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.summary-card.current::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.summary-card.depreciation::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.summary-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.card-header h3 {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
  font-weight: 500;
}

.card-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.summary-card.total .card-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.summary-card.acquisition .card-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.summary-card.current .card-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.summary-card.depreciation .card-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.summary-value {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.summary-breakdown {
  display: flex;
  gap: 1rem;
}

.breakdown-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: #64748b;
}

.breakdown-item i.active {
  color: #10b981;
}

.breakdown-item i.inactive {
  color: #ef4444;
}

.summary-trend {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.trend-percentage {
  font-weight: 600;
  color: #6366f1;
  font-size: 0.875rem;
}

.trend-label {
  font-size: 0.75rem;
  color: #94a3b8;
}

/* Category Breakdown */
.category-breakdown {
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-header h2 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.section-header h2 i {
  color: #6366f1;
}

.breakdown-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1.5rem;
}

.breakdown-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  transition: all 0.3s ease;
}

.breakdown-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.breakdown-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.category-icon {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.category-info h4 {
  font-size: 1.125rem;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.category-info p {
  color: #64748b;
  font-size: 0.875rem;
  margin: 0;
}

.breakdown-details {
  margin-bottom: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  color: #64748b;
  font-size: 0.875rem;
}

.detail-value {
  color: #1e293b;
  font-weight: 500;
}

.detail-value.depreciation-value {
  color: #ef4444;
}

.breakdown-progress {
  margin-top: 1rem;
}

.progress-bar {
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 4px;
  transition: width 0.3s ease;
}

.progress-label {
  font-size: 0.75rem;
  color: #64748b;
}

/* Asset Register */
.asset-register {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 2rem;
}

.section-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.section-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.search-box {
  position: relative;
  flex: 1;
  max-width: 300px;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-box input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.search-box input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.sort-select {
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  background: white;
  cursor: pointer;
  transition: all 0.3s ease;
}

.sort-select:focus {
  outline: none;
  border-color: #6366f1;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: #64748b;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Register Table */
.register-table-container {
  overflow-x: auto;
}

.register-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.register-table th,
.register-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

.register-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #374151;
  position: sticky;
  top: 0;
  z-index: 10;
}

.register-table th.sortable {
  cursor: pointer;
  user-select: none;
  transition: all 0.3s ease;
}

.register-table th.sortable:hover {
  background: #f1f5f9;
  color: #6366f1;
}

.register-table th.sortable i {
  margin-left: 0.5rem;
  color: #6366f1;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.asset-row:hover {
  background: #f8fafc;
}

.asset-code {
  color: #6366f1;
  font-weight: 500;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.asset-icon-mini {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.875rem;
  flex-shrink: 0;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.active {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.inactive {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge.disposed {
  background: #f3f4f6;
  color: #374151;
}

.status-badge.under-maintenance {
  background: #fef3c7;
  color: #92400e;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  margin: 0 0.25rem;
}

.action-btn.view {
  background: #f0fdf4;
  color: #15803d;
}

.action-btn.view:hover {
  background: #22c55e;
  color: white;
}

.action-btn.edit {
  background: #dbeafe;
  color: #1d4ed8;
}

.action-btn.edit:hover {
  background: #3b82f6;
  color: white;
}

.totals-row {
  background: #f8fafc;
  font-weight: 600;
  border-top: 2px solid #e2e8f0;
}

.totals-label {
  color: #1e293b;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #64748b;
}

.empty-icon {
  font-size: 4rem;
  color: #cbd5e1;
  margin-bottom: 1rem;
}

.empty-state h3 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

/* Report Footer */
.report-footer {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 2rem;
  margin-top: 2rem;
}

.footer-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.footer-info p {
  margin: 0 0 0.25rem 0;
  color: #64748b;
  font-size: 0.875rem;
}

.company-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6366f1;
  font-weight: 600;
}

.company-logo i {
  font-size: 1.25rem;
}

/* Print Styles */
@media print {
  .page-header,
  .report-controls,
  .section-actions,
  .actions {
    display: none !important;
  }
  
  .asset-report-page {
    padding: 0;
    max-width: none;
  }
  
  .register-table {
    font-size: 0.75rem;
  }
  
  .register-table th,
  .register-table td {
    padding: 0.5rem;
  }
}

/* Responsive Design */
@media (max-width: 768px) {
  .asset-report-page {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .controls-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .breakdown-grid {
    grid-template-columns: 1fr;
  }
  
  .section-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .section-actions {
    justify-content: space-between;
  }
  
  .search-box {
    max-width: none;
  }
  
  .register-table-container {
    margin: -1rem;
    padding: 1rem;
  }
  
  .register-table {
    font-size: 0.75rem;
  }
  
  .register-table th,
  .register-table td {
    padding: 0.5rem;
  }
  
  .footer-content {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }
}
</style>