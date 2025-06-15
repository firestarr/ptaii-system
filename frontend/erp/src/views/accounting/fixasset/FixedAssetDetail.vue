<template>
  <AppLayout>
    <div class="asset-detail-page">
      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading asset details...</p>
      </div>

      <!-- Asset Detail Content -->
      <div v-else-if="asset" class="asset-detail-content">
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
                <span class="breadcrumb-current">{{ asset.name }}</span>
              </div>
              <div class="asset-title">
                <div class="asset-icon">
                  <i :class="getAssetIcon(asset.category)"></i>
                </div>
                <div class="title-info">
                  <h1 class="page-title">{{ asset.name }}</h1>
                  <div class="asset-meta">
                    <span class="asset-code">{{ asset.asset_code }}</span>
                    <span class="asset-status" :class="asset.status.toLowerCase().replace(' ', '-')">
                      {{ asset.status }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="header-actions">
              <button @click="calculateDepreciation" class="btn btn-outline">
                <i class="fas fa-calculator"></i>
                Calculate Depreciation
              </button>
              <button @click="editAsset" class="btn btn-outline">
                <i class="fas fa-edit"></i>
                Edit Asset
              </button>
              <button @click="goBack" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Back to List
              </button>
            </div>
          </div>
        </div>

        <!-- Asset Overview Cards -->
        <div class="overview-section">
          <div class="overview-grid">
            <div class="overview-card acquisition">
              <div class="card-icon">
                <i class="fas fa-shopping-cart"></i>
              </div>
              <div class="card-content">
                <h3>Acquisition Cost</h3>
                <p class="card-value">${{ formatNumber(asset.acquisition_cost) }}</p>
                <span class="card-date">{{ formatDate(asset.acquisition_date) }}</span>
              </div>
            </div>

            <div class="overview-card current">
              <div class="card-icon">
                <i class="fas fa-dollar-sign"></i>
              </div>
              <div class="card-content">
                <h3>Current Book Value</h3>
                <p class="card-value">${{ formatNumber(asset.current_value) }}</p>
                <span class="card-subtitle">After depreciation</span>
              </div>
            </div>

            <div class="overview-card depreciation">
              <div class="card-icon">
                <i class="fas fa-chart-line-down"></i>
              </div>
              <div class="card-content">
                <h3>Total Depreciation</h3>
                <p class="card-value">${{ formatNumber(totalDepreciation) }}</p>
                <span class="card-subtitle">{{ asset.depreciation_rate }}% annual rate</span>
              </div>
            </div>

            <div class="overview-card age">
              <div class="card-icon">
                <i class="fas fa-calendar-alt"></i>
              </div>
              <div class="card-content">
                <h3>Asset Age</h3>
                <p class="card-value">{{ assetAge }}</p>
                <span class="card-subtitle">Since acquisition</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Asset Information Tabs -->
        <div class="tabs-section">
          <div class="tabs-nav">
            <button 
              v-for="tab in tabs" 
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="['tab-btn', { active: activeTab === tab.id }]"
            >
              <i :class="tab.icon"></i>
              {{ tab.label }}
            </button>
          </div>

          <div class="tabs-content">
            <!-- Basic Information Tab -->
            <div v-if="activeTab === 'basic'" class="tab-panel">
              <div class="info-grid">
                <div class="info-section">
                  <h3>Asset Details</h3>
                  <div class="info-list">
                    <div class="info-item">
                      <span class="info-label">Asset Code</span>
                      <span class="info-value">{{ asset.asset_code }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Asset Name</span>
                      <span class="info-value">{{ asset.name }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Category</span>
                      <span class="info-value">{{ asset.category }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Status</span>
                      <span class="info-value">
                        <span class="status-badge" :class="asset.status.toLowerCase().replace(' ', '-')">
                          {{ asset.status }}
                        </span>
                      </span>
                    </div>
                  </div>
                </div>

                <div class="info-section">
                  <h3>Financial Information</h3>
                  <div class="info-list">
                    <div class="info-item">
                      <span class="info-label">Acquisition Date</span>
                      <span class="info-value">{{ formatDate(asset.acquisition_date) }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Acquisition Cost</span>
                      <span class="info-value">${{ formatNumber(asset.acquisition_cost) }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Current Book Value</span>
                      <span class="info-value">${{ formatNumber(asset.current_value) }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Depreciation Rate</span>
                      <span class="info-value">{{ asset.depreciation_rate }}% per year</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Depreciation History Tab -->
            <div v-if="activeTab === 'depreciation'" class="tab-panel">
              <div class="depreciation-section">
                <div class="section-header">
                  <h3>Depreciation History</h3>
                  <button @click="calculateDepreciation" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i>
                    Calculate New Depreciation
                  </button>
                </div>

                <div v-if="depreciations.length === 0" class="empty-state">
                  <div class="empty-icon">
                    <i class="fas fa-chart-line"></i>
                  </div>
                  <h4>No Depreciation Records</h4>
                  <p>No depreciation has been calculated for this asset yet.</p>
                  <button @click="calculateDepreciation" class="btn btn-primary">
                    <i class="fas fa-calculator"></i>
                    Calculate First Depreciation
                  </button>
                </div>

                <div v-else class="depreciation-timeline">
                  <div 
                    v-for="depreciation in depreciations" 
                    :key="depreciation.depreciation_id"
                    class="timeline-item"
                  >
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <h4>{{ formatDate(depreciation.depreciation_date) }}</h4>
                        <span class="period-info">{{ getPeriodInfo(depreciation) }}</span>
                      </div>
                      <div class="timeline-details">
                        <div class="detail-item">
                          <span class="detail-label">Depreciation Amount</span>
                          <span class="detail-value amount">${{ formatNumber(depreciation.depreciation_amount) }}</span>
                        </div>
                        <div class="detail-item">
                          <span class="detail-label">Accumulated Depreciation</span>
                          <span class="detail-value">${{ formatNumber(depreciation.accumulated_depreciation) }}</span>
                        </div>
                        <div class="detail-item">
                          <span class="detail-label">Remaining Value</span>
                          <span class="detail-value">${{ formatNumber(depreciation.remaining_value) }}</span>
                        </div>
                      </div>
                      <div class="timeline-actions">
                        <button @click="viewDepreciationDetail(depreciation)" class="action-btn">
                          <i class="fas fa-eye"></i>
                          View Details
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Analytics Tab -->
            <div v-if="activeTab === 'analytics'" class="tab-panel">
              <div class="analytics-section">
                <div class="analytics-grid">
                  <div class="analytics-card">
                    <h3>Depreciation Trend</h3>
                    <div class="chart-placeholder">
                      <i class="fas fa-chart-line"></i>
                      <p>Chart showing depreciation over time</p>
                    </div>
                  </div>

                  <div class="analytics-card">
                    <h3>Value Comparison</h3>
                    <div class="value-comparison">
                      <div class="comparison-item">
                        <span class="comparison-label">Original Value</span>
                        <div class="comparison-bar">
                          <div class="bar-fill original" style="width: 100%"></div>
                          <span class="bar-value">${{ formatNumber(asset.acquisition_cost) }}</span>
                        </div>
                      </div>
                      <div class="comparison-item">
                        <span class="comparison-label">Current Value</span>
                        <div class="comparison-bar">
                          <div class="bar-fill current" :style="{ width: currentValuePercentage + '%' }"></div>
                          <span class="bar-value">${{ formatNumber(asset.current_value) }}</span>
                        </div>
                      </div>
                      <div class="comparison-item">
                        <span class="comparison-label">Depreciated Amount</span>
                        <div class="comparison-bar">
                          <div class="bar-fill depreciated" :style="{ width: depreciationPercentage + '%' }"></div>
                          <span class="bar-value">${{ formatNumber(totalDepreciation) }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="analytics-card">
                    <h3>Future Projections</h3>
                    <div class="projections">
                      <div class="projection-item">
                        <span class="projection-label">Estimated remaining life</span>
                        <span class="projection-value">{{ estimatedRemainingLife }} years</span>
                      </div>
                      <div class="projection-item">
                        <span class="projection-label">Projected annual depreciation</span>
                        <span class="projection-value">${{ formatNumber(projectedAnnualDepreciation) }}</span>
                      </div>
                      <div class="projection-item">
                        <span class="projection-label">Expected fully depreciated date</span>
                        <span class="projection-value">{{ projectedFullDepreciationDate }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else class="error-state">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Asset Not Found</h3>
        <p>The requested asset could not be found.</p>
        <button @click="goBack" class="btn btn-primary">
          <i class="fas fa-arrow-left"></i>
          Back to List
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'FixedAssetDetail',
  components: {
  },
  setup() {
    const router = useRouter()
    const route = useRoute()
    
    // Reactive data
    const loading = ref(false)
    const asset = ref(null)
    const depreciations = ref([])
    const activeTab = ref('basic')
    
    const tabs = ref([
      { id: 'basic', label: 'Basic Info', icon: 'fas fa-info-circle' },
      { id: 'depreciation', label: 'Depreciation History', icon: 'fas fa-chart-line' },
      { id: 'analytics', label: 'Analytics', icon: 'fas fa-analytics' }
    ])
    
    // Computed
    const totalDepreciation = computed(() => {
      if (!asset.value) return 0
      return asset.value.acquisition_cost - asset.value.current_value
    })
    
    const assetAge = computed(() => {
      if (!asset.value?.acquisition_date) return '0 years'
      
      const acquisitionDate = new Date(asset.value.acquisition_date)
      const now = new Date()
      const diffTime = Math.abs(now - acquisitionDate)
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      const years = Math.floor(diffDays / 365)
      const months = Math.floor((diffDays % 365) / 30)
      
      if (years > 0) {
        return `${years} year${years > 1 ? 's' : ''} ${months > 0 ? `${months} month${months > 1 ? 's' : ''}` : ''}`
      } else if (months > 0) {
        return `${months} month${months > 1 ? 's' : ''}`
      } else {
        return `${diffDays} day${diffDays > 1 ? 's' : ''}`
      }
    })
    
    const currentValuePercentage = computed(() => {
      if (!asset.value) return 0
      return (asset.value.current_value / asset.value.acquisition_cost) * 100
    })
    
    const depreciationPercentage = computed(() => {
      if (!asset.value) return 0
      return (totalDepreciation.value / asset.value.acquisition_cost) * 100
    })
    
    const estimatedRemainingLife = computed(() => {
      if (!asset.value || asset.value.depreciation_rate === 0) return 0
      const remainingPercentage = currentValuePercentage.value
      return Math.ceil(remainingPercentage / asset.value.depreciation_rate)
    })
    
    const projectedAnnualDepreciation = computed(() => {
      if (!asset.value) return 0
      return (asset.value.current_value * asset.value.depreciation_rate) / 100
    })
    
    const projectedFullDepreciationDate = computed(() => {
      if (!asset.value || estimatedRemainingLife.value === 0) return 'Already fully depreciated'
      
      const currentDate = new Date()
      const projectedDate = new Date(currentDate.getFullYear() + estimatedRemainingLife.value, currentDate.getMonth(), currentDate.getDate())
      return formatDate(projectedDate.toISOString().split('T')[0])
    })
    
    // Methods
    const fetchAsset = async () => {
      try {
        loading.value = true
        const response = await axios.get(`/accounting/fixed-assets/${route.params.id}`)
        asset.value = response.data.data
        
        // Extract depreciation history if available
        if (asset.value.asset_depreciations) {
          depreciations.value = asset.value.asset_depreciations.sort((a, b) => 
            new Date(b.depreciation_date) - new Date(a.depreciation_date)
          )
        }
        
      } catch (error) {
        console.error('Error fetching asset:', error)
        asset.value = null
      } finally {
        loading.value = false
      }
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
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    }
    
    const getPeriodInfo = (depreciation) => {
      if (depreciation.accounting_period) {
        return `Period: ${depreciation.accounting_period.period_name}`
      }
      return 'Manual Entry'
    }
    
    const editAsset = () => {
      router.push(`/accounting/fixed-assets/${route.params.id}/edit`)
    }
    
    const calculateDepreciation = () => {
      // This would open a modal or navigate to depreciation calculation page
      console.log('Calculate depreciation for asset:', asset.value.asset_id)
    }
    
    const viewDepreciationDetail = (depreciation) => {
      // This would open a modal with detailed depreciation information
      console.log('View depreciation detail:', depreciation)
    }
    
    const goBack = () => {
      router.push('/accounting/fixed-assets')
    }
    
    // Lifecycle
    onMounted(() => {
      fetchAsset()
    })
    
    return {
      loading,
      asset,
      depreciations,
      activeTab,
      tabs,
      totalDepreciation,
      assetAge,
      currentValuePercentage,
      depreciationPercentage,
      estimatedRemainingLife,
      projectedAnnualDepreciation,
      projectedFullDepreciationDate,
      getAssetIcon,
      formatNumber,
      formatDate,
      getPeriodInfo,
      editAsset,
      calculateDepreciation,
      viewDepreciationDetail,
      goBack
    }
  }
}
</script>

<style scoped>
.asset-detail-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

/* Loading and Error States */
.loading-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: #64748b;
  text-align: center;
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

.error-icon {
  font-size: 4rem;
  color: #ef4444;
  margin-bottom: 1rem;
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

.asset-title {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.asset-icon {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2rem;
  flex-shrink: 0;
}

.title-info {
  flex: 1;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.asset-meta {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.asset-code {
  color: #6366f1;
  font-weight: 600;
  font-size: 1.1rem;
}

.asset-status {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 500;
  text-transform: uppercase;
}

.asset-status.active {
  background: #d1fae5;
  color: #065f46;
}

.asset-status.inactive {
  background: #fee2e2;
  color: #991b1b;
}

.asset-status.disposed {
  background: #f3f4f6;
  color: #374151;
}

.asset-status.under-maintenance {
  background: #fef3c7;
  color: #92400e;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
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

/* Overview Section */
.overview-section {
  margin-bottom: 2rem;
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.overview-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.overview-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.overview-card.acquisition::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.overview-card.current::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.overview-card.depreciation::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.overview-card.age::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.overview-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.card-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.overview-card.acquisition .card-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.overview-card.current .card-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.overview-card.depreciation .card-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.overview-card.age .card-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.card-content h3 {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0 0 0.5rem 0;
  font-weight: 500;
}

.card-value {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.card-date,
.card-subtitle {
  font-size: 0.75rem;
  color: #94a3b8;
  margin: 0;
}

/* Tabs Section */
.tabs-section {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.tabs-nav {
  display: flex;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.5rem;
  border: none;
  background: none;
  cursor: pointer;
  font-weight: 500;
  color: #64748b;
  transition: all 0.3s ease;
  border-bottom: 3px solid transparent;
}

.tab-btn:hover {
  color: #6366f1;
  background: white;
}

.tab-btn.active {
  color: #6366f1;
  background: white;
  border-bottom-color: #6366f1;
}

.tabs-content {
  padding: 2rem;
}

/* Basic Info Tab */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 2rem;
}

.info-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1rem 0;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.info-label {
  color: #64748b;
  font-weight: 500;
}

.info-value {
  color: #1e293b;
  font-weight: 600;
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

/* Depreciation Tab */
.depreciation-section {
  margin-top: 2rem;  
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.section-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

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

.empty-state h4 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.depreciation-timeline {
  position: relative;
  padding-left: 2rem;
}

.depreciation-timeline::before {
  content: '';
  position: absolute;
  left: 15px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}

.timeline-item {
  position: relative;
  margin-bottom: 2rem;
}

.timeline-marker {
  position: absolute;
  left: -1.75rem;
  top: 0.5rem;
  width: 12px;
  height: 12px;
  background: #6366f1;
  border-radius: 50%;
  border: 3px solid white;
  box-shadow: 0 0 0 3px #e2e8f0;
}

.timeline-content {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.timeline-header h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.period-info {
  color: #6366f1;
  font-size: 0.875rem;
  font-weight: 500;
}

.timeline-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  color: #64748b;
  font-size: 0.875rem;
}

.detail-value {
  color: #1e293b;
  font-weight: 600;
  font-size: 1.1rem;
}

.detail-value.amount {
  color: #ef4444;
}

.timeline-actions {
  display: flex;
  justify-content: flex-end;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  color: #64748b;
  cursor: pointer;
  transition: all 0.3s ease;
}

.action-btn:hover {
  border-color: #6366f1;
  color: #6366f1;
}

/* Analytics Tab */
.analytics-section {
  margin-top: 2rem;
}

.analytics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 2rem;
}

.analytics-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
}

.analytics-card h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1rem 0;
}

.chart-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: #94a3b8;
  text-align: center;
}

.chart-placeholder i {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.value-comparison {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.comparison-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.comparison-label {
  display: block;
  color: #64748b;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.comparison-bar {
  position: relative;
  height: 40px;
  background: #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.bar-fill.original {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.bar-fill.current {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.bar-fill.depreciated {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.bar-value {
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: white;
  font-weight: 600;
  font-size: 0.875rem;
}

.projections {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.projection-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.projection-label {
  color: #64748b;
  font-size: 0.875rem;
}

.projection-value {
  color: #1e293b;
  font-weight: 600;
}

/* Responsive Design */
@media (max-width: 768px) {
  .asset-detail-page {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .asset-title {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .header-actions {
    order: -1;
    justify-content: flex-end;
  }
  
  .overview-grid {
    grid-template-columns: 1fr;
  }
  
  .tabs-nav {
    flex-direction: column;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .timeline-details {
    grid-template-columns: 1fr;
  }
  
  .analytics-grid {
    grid-template-columns: 1fr;
  }
  
  .page-title {
    font-size: 2rem;
  }
}
</style>