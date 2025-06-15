<!-- src/views/accounting/DepreciationsList.vue -->
<template>
  <AppLayout>
    <div class="depreciations-page">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-chart-line"></i>
              Asset Depreciations
            </h1>
            <p class="page-subtitle">Manage and monitor asset depreciation records</p>
          </div>
          <div class="header-actions">
            <router-link to="/accounting/depreciations/calculate" class="btn btn-primary">
              <i class="fas fa-calculator"></i>
              Calculate Depreciation
            </router-link>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="filters-section">
        <div class="filter-card">
          <div class="filter-row">
            <div class="filter-group">
              <label for="assetFilter">Filter by Asset</label>
              <select
                id="assetFilter"
                v-model="filters.asset_id"
                @change="applyFilters"
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
              <label for="periodFilter">Filter by Period</label>
              <select
                id="periodFilter"
                v-model="filters.period_id"
                @change="applyFilters"
                class="form-select"
              >
                <option value="">All Periods</option>
                <option
                  v-for="period in periods"
                  :key="period.period_id"
                  :value="period.period_id"
                >
                  {{ period.period_name }}
                </option>
              </select>
            </div>

            <div class="filter-group">
              <label for="searchInput">Search</label>
              <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input
                  id="searchInput"
                  v-model="searchQuery"
                  @input="handleSearch"
                  type="text"
                  placeholder="Search by asset name..."
                  class="form-input search-input"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card total">
          <div class="stat-icon">
            <i class="fas fa-list-alt"></i>
          </div>
          <div class="stat-content">
            <h3>{{ totalDepreciations }}</h3>
            <p>Total Depreciations</p>
          </div>
        </div>

        <div class="stat-card amount">
          <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
          </div>
          <div class="stat-content">
            <h3>${{ formatCurrency(totalDepreciationAmount) }}</h3>
            <p>Total Depreciation Amount</p>
          </div>
        </div>

        <div class="stat-card accumulated">
          <div class="stat-icon">
            <i class="fas fa-chart-pie"></i>
          </div>
          <div class="stat-content">
            <h3>${{ formatCurrency(totalAccumulated) }}</h3>
            <p>Total Accumulated</p>
          </div>
        </div>
      </div>

      <!-- Depreciations List -->
      <div class="content-section">
        <div class="content-header">
          <h2>Depreciation Records</h2>
          <div class="view-options">
            <button
              @click="viewMode = 'card'"
              :class="['view-btn', { active: viewMode === 'card' }]"
            >
              <i class="fas fa-th-large"></i>
            </button>
            <button
              @click="viewMode = 'table'"
              :class="['view-btn', { active: viewMode === 'table' }]"
            >
              <i class="fas fa-table"></i>
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
          <div class="loading-spinner"></div>
          <p>Loading depreciations...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="depreciations.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h3>No depreciations found</h3>
          <p>Start by calculating depreciation for your assets</p>
          <router-link to="/accounting/depreciations/calculate" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Calculate First Depreciation
          </router-link>
        </div>

        <!-- Card View -->
        <div v-else-if="viewMode === 'card'" class="cards-grid">
          <div
            v-for="depreciation in depreciations"
            :key="depreciation.depreciation_id"
            class="depreciation-card"
            @click="viewDetails(depreciation)"
          >
            <div class="card-header">
              <div class="asset-info">
                <h3>{{ depreciation.fixed_asset?.name }}</h3>
                <span class="asset-code">{{ depreciation.fixed_asset?.asset_code }}</span>
              </div>
              <div class="card-actions">
                <button
                  @click.stop="viewSchedule(depreciation.asset_id)"
                  class="action-btn schedule"
                  title="View Schedule"
                >
                  <i class="fas fa-calendar-alt"></i>
                </button>
                <button
                  @click.stop="viewJournalEntry(depreciation)"
                  class="action-btn journal"
                  title="View Journal Entry"
                >
                  <i class="fas fa-book"></i>
                </button>
                <button
                  @click.stop="deleteDepreciation(depreciation)"
                  class="action-btn delete"
                  title="Delete"
                >
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>

            <div class="card-body">
              <div class="info-grid">
                <div class="info-item">
                  <span class="label">Period</span>
                  <span class="value">{{ depreciation.accounting_period?.period_name }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Date</span>
                  <span class="value">{{ formatDate(depreciation.depreciation_date) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Depreciation Amount</span>
                  <span class="value amount">${{ formatCurrency(depreciation.depreciation_amount) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Accumulated</span>
                  <span class="value">${{ formatCurrency(depreciation.accumulated_depreciation) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Remaining Value</span>
                  <span class="value remaining">${{ formatCurrency(depreciation.remaining_value) }}</span>
                </div>
              </div>
            </div>

            <div class="card-footer">
              <div class="progress-section">
                <div class="progress-label">
                  <span>Depreciation Progress</span>
                  <span>{{ getDepreciationPercentage(depreciation) }}%</span>
                </div>
                <div class="progress-bar">
                  <div
                    class="progress-fill"
                    :style="{ width: getDepreciationPercentage(depreciation) + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Table View -->
        <div v-else class="table-container">
          <table class="depreciations-table">
            <thead>
              <tr>
                <th>Asset</th>
                <th>Period</th>
                <th>Date</th>
                <th>Depreciation Amount</th>
                <th>Accumulated</th>
                <th>Remaining Value</th>
                <th>Progress</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="depreciation in depreciations"
                :key="depreciation.depreciation_id"
                class="table-row"
                @click="viewDetails(depreciation)"
              >
                <td class="asset-cell">
                  <div class="asset-info">
                    <strong>{{ depreciation.fixed_asset?.name }}</strong>
                    <small>{{ depreciation.fixed_asset?.asset_code }}</small>
                  </div>
                </td>
                <td>{{ depreciation.accounting_period?.period_name }}</td>
                <td>{{ formatDate(depreciation.depreciation_date) }}</td>
                <td class="amount-cell">${{ formatCurrency(depreciation.depreciation_amount) }}</td>
                <td class="amount-cell">${{ formatCurrency(depreciation.accumulated_depreciation) }}</td>
                <td class="amount-cell remaining">${{ formatCurrency(depreciation.remaining_value) }}</td>
                <td class="progress-cell">
                  <div class="mini-progress">
                    <div
                      class="mini-progress-fill"
                      :style="{ width: getDepreciationPercentage(depreciation) + '%' }"
                    ></div>
                  </div>
                  <span class="progress-text">{{ getDepreciationPercentage(depreciation) }}%</span>
                </td>
                <td class="actions-cell" @click.stop>
                  <div class="table-actions">
                    <button
                      @click="viewSchedule(depreciation.asset_id)"
                      class="action-btn schedule"
                      title="View Schedule"
                    >
                      <i class="fas fa-calendar-alt"></i>
                    </button>
                    <button
                      @click="viewJournalEntry(depreciation)"
                      class="action-btn journal"
                      title="View Journal Entry"
                    >
                      <i class="fas fa-book"></i>
                    </button>
                    <button
                      @click="deleteDepreciation(depreciation)"
                      class="action-btn delete"
                      title="Delete"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="pagination-container">
          <div class="pagination-info">
            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
          </div>
          <div class="pagination-controls">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
              class="page-btn"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
            <button
              v-for="page in paginationPages"
              :key="page"
              @click="changePage(page)"
              :class="['page-btn', { active: page === pagination.current_page }]"
            >
              {{ page }}
            </button>
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.last_page"
              class="page-btn"
            >
              <i class="fas fa-chevron-right"></i>
            </button>
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
            Are you sure you want to delete this depreciation record for
            <strong>{{ selectedDepreciation?.fixed_asset?.name }}</strong>?
          </p>
          <div class="warning-note">
            <i class="fas fa-info-circle"></i>
            This action cannot be undone and will restore the asset's previous value.
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
            <i class="fas fa-trash"></i>
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'DepreciationsList',
  components: {
  },
  setup() {
    const router = useRouter()
    
    // Reactive state
    const loading = ref(false)
    const deleting = ref(false)
    const depreciations = ref([])
    const assets = ref([])
    const periods = ref([])
    const viewMode = ref('card')
    const searchQuery = ref('')
    const showDeleteModal = ref(false)
    const selectedDepreciation = ref(null)
    
    // Filters
    const filters = ref({
      asset_id: '',
      period_id: ''
    })
    
    // Pagination
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0
    })

    // Computed properties
    const totalDepreciations = computed(() => depreciations.value.length)
    
    const totalDepreciationAmount = computed(() => {
      return depreciations.value.reduce((sum, dep) => sum + (dep.depreciation_amount || 0), 0)
    })
    
    const totalAccumulated = computed(() => {
      return depreciations.value.reduce((sum, dep) => sum + (dep.accumulated_depreciation || 0), 0)
    })

    const paginationPages = computed(() => {
      const pages = []
      const current = pagination.value.current_page
      const last = pagination.value.last_page
      
      for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i)
      }
      
      return pages
    })

    // Methods
    const fetchDepreciations = async (page = 1) => {
      try {
        loading.value = true
        const params = {
          page,
          per_page: pagination.value.per_page,
          ...filters.value
        }
        
        if (searchQuery.value) {
          params.search = searchQuery.value
        }

        const response = await axios.get('/accounting/asset-depreciations', { params })
        
        depreciations.value = response.data.data
        pagination.value = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to
        }
      } catch (error) {
        console.error('Error fetching depreciations:', error)
        // Handle error notification
      } finally {
        loading.value = false
      }
    }

    const fetchAssets = async () => {
      try {
        const response = await axios.get('/accounting/fixed-assets')
        assets.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching assets:', error)
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

    const applyFilters = () => {
      fetchDepreciations(1)
    }

    const handleSearch = () => {
      // Debounce search
      clearTimeout(window.searchTimeout)
      window.searchTimeout = setTimeout(() => {
        fetchDepreciations(1)
      }, 500)
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        fetchDepreciations(page)
      }
    }

    const viewDetails = (depreciation) => {
      router.push(`/accounting/asset-depreciations/${depreciation.depreciation_id}`)
    }

    const viewSchedule = (assetId) => {
      router.push(`/accounting/asset-depreciations/schedule/${assetId}`)
    }

    const viewJournalEntry = (depreciation) => {
      router.push(`/accounting/asset-depreciations/journal/${depreciation.depreciation_id}`)
    }

    const deleteDepreciation = (depreciation) => {
      selectedDepreciation.value = depreciation
      showDeleteModal.value = true
    }

    const closeDeleteModal = () => {
      showDeleteModal.value = false
      selectedDepreciation.value = null
    }

    const confirmDelete = async () => {
      try {
        deleting.value = true
        await axios.delete(`/accounting/asset-depreciations/${selectedDepreciation.value.depreciation_id}`)
        
        // Remove from local list
        const index = depreciations.value.findIndex(
          d => d.depreciation_id === selectedDepreciation.value.depreciation_id
        )
        if (index > -1) {
          depreciations.value.splice(index, 1)
        }
        
        closeDeleteModal()
        // Show success notification
      } catch (error) {
        console.error('Error deleting depreciation:', error)
        // Handle error notification
      } finally {
        deleting.value = false
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

    const getDepreciationPercentage = (depreciation) => {
      if (!depreciation.fixed_asset?.acquisition_cost) return 0
      const percentage = (depreciation.accumulated_depreciation / depreciation.fixed_asset.acquisition_cost) * 100
      return Math.min(100, Math.round(percentage))
    }

    // Lifecycle
    onMounted(() => {
      Promise.all([
        fetchDepreciations(),
        fetchAssets(),
        fetchPeriods()
      ])
    })

    return {
      loading,
      deleting,
      depreciations,
      assets,
      periods,
      viewMode,
      searchQuery,
      filters,
      pagination,
      showDeleteModal,
      selectedDepreciation,
      totalDepreciations,
      totalDepreciationAmount,
      totalAccumulated,
      paginationPages,
      fetchDepreciations,
      applyFilters,
      handleSearch,
      changePage,
      viewDetails,
      viewSchedule,
      viewJournalEntry,
      deleteDepreciation,
      closeDeleteModal,
      confirmDelete,
      formatCurrency,
      formatDate,
      getDepreciationPercentage
    }
  }
}
</script>

<style scoped>
.depreciations-page {
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

.form-select,
.form-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white;
}

.form-select:focus,
.form-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-input-wrapper {
  position: relative;
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

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  transition: transform 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
}

.stat-card.total .stat-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.stat-card.amount .stat-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.accumulated .stat-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-content h3 {
  font-size: 1.875rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
}

.stat-content p {
  color: #6b7280;
  margin: 0;
}

/* Content Section */
.content-section {
  background: white;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.content-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
}

.view-options {
  display: flex;
  gap: 0.5rem;
}

.view-btn {
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

.view-btn:hover,
.view-btn.active {
  border-color: #6366f1;
  background: #6366f1;
  color: white;
}

/* Cards Grid */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 1.5rem;
}

.depreciation-card {
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  background: #fafbfc;
}

.depreciation-card:hover {
  transform: translateY(-5px);
  border-color: #6366f1;
  box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15);
}

.card-header {
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

.card-actions {
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

.action-btn.schedule {
  background: #dbeafe;
  color: #1d4ed8;
}

.action-btn.journal {
  background: #fef3c7;
  color: #d97706;
}

.action-btn.delete {
  background: #fee2e2;
  color: #dc2626;
}

.action-btn:hover {
  transform: scale(1.1);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-item .label {
  font-size: 0.8rem;
  color: #6b7280;
  font-weight: 500;
}

.info-item .value {
  font-weight: 600;
  color: #1f2937;
}

.info-item .value.amount {
  color: #059669;
}

.info-item .value.remaining {
  color: #d97706;
}

/* Progress Section */
.progress-section {
  margin-top: 1rem;
}

.progress-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
  color: #374151;
}

.progress-bar {
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  transition: width 0.8s ease;
}

/* Table View */
.table-container {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.depreciations-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.depreciations-table th {
  background: #f8fafc;
  color: #374151;
  font-weight: 600;
  padding: 1rem;
  text-align: left;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.9rem;
}

.depreciations-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.table-row {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.table-row:hover {
  background: #f8fafc;
}

.asset-cell {
  min-width: 200px;
}

.asset-cell .asset-info strong {
  display: block;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.asset-cell .asset-info small {
  color: #6b7280;
  background: #f3f4f6;
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-size: 0.75rem;
}

.amount-cell {
  font-weight: 600;
  color: #059669;
}

.amount-cell.remaining {
  color: #d97706;
}

.progress-cell {
  min-width: 120px;
}

.mini-progress {
  width: 60px;
  height: 6px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 0.25rem;
}

.mini-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  transition: width 0.8s ease;
}

.progress-text {
  font-size: 0.75rem;
  color: #6b7280;
  font-weight: 500;
}

.table-actions {
  display: flex;
  gap: 0.5rem;
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

.btn-primary:hover {
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

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
}

/* Pagination */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 2px solid #f1f5f9;
}

.pagination-info {
  color: #6b7280;
  font-size: 0.9rem;
}

.pagination-controls {
  display: flex;
  gap: 0.5rem;
}

.page-btn {
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
  font-weight: 500;
}

.page-btn:hover:not(:disabled),
.page-btn.active {
  border-color: #6366f1;
  background: #6366f1;
  color: white;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
  margin-bottom: 2rem;
  font-size: 1.1rem;
}

/* Modal */
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
  .depreciations-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .filter-row {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .cards-grid {
    grid-template-columns: 1fr;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .pagination-container {
    flex-direction: column;
    gap: 1rem;
  }

  .table-container {
    font-size: 0.8rem;
  }

  .depreciations-table th,
  .depreciations-table td {
    padding: 0.5rem;
  }
}
</style>