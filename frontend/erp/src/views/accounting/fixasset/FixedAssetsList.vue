<template>
  <AppLayout>
    <div class="fixed-assets-page">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-building"></i>
              Fixed Assets
            </h1>
            <p class="page-subtitle">Manage your company's fixed assets and track their depreciation</p>
          </div>
          <div class="header-actions">
            <button @click="generateReport" class="btn btn-outline">
              <i class="fas fa-file-pdf"></i>
              Generate Report
            </button>
            <button @click="createAsset" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              Add New Asset
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon active">
            <i class="fas fa-building"></i>
          </div>
          <div class="stat-content">
            <h3>{{ stats.totalAssets }}</h3>
            <p>Total Assets</p>
            <div class="stat-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>{{ stats.activeAssets }} Active</span>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon value">
            <i class="fas fa-dollar-sign"></i>
          </div>
          <div class="stat-content">
            <h3>${{ formatNumber(stats.totalValue) }}</h3>
            <p>Total Value</p>
            <div class="stat-trend neutral">
              <i class="fas fa-chart-line"></i>
              <span>Current Book Value</span>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon depreciation">
            <i class="fas fa-chart-line-down"></i>
          </div>
          <div class="stat-content">
            <h3>${{ formatNumber(stats.totalDepreciation) }}</h3>
            <p>Total Depreciation</p>
            <div class="stat-trend down">
              <i class="fas fa-arrow-down"></i>
              <span>Accumulated</span>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon categories">
            <i class="fas fa-layer-group"></i>
          </div>
          <div class="stat-content">
            <h3>{{ stats.categoriesCount }}</h3>
            <p>Categories</p>
            <div class="stat-trend neutral">
              <i class="fas fa-tags"></i>
              <span>Asset Types</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters-section">
        <div class="filters-container">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search assets by name or code..."
              @input="debounceSearch"
            >
          </div>
          
          <div class="filter-group">
            <select v-model="filters.category" @change="applyFilters" class="filter-select">
              <option value="">All Categories</option>
              <option v-for="category in categories" :key="category" :value="category">
                {{ category }}
              </option>
            </select>
            
            <select v-model="filters.status" @change="applyFilters" class="filter-select">
              <option value="">All Status</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="Disposed">Disposed</option>
              <option value="Under Maintenance">Under Maintenance</option>
            </select>
            
            <button @click="clearFilters" class="btn btn-outline btn-sm">
              <i class="fas fa-times"></i>
              Clear
            </button>
          </div>
        </div>
      </div>

      <!-- Assets Grid -->
      <div class="assets-container">
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading assets...</p>
        </div>

        <div v-else-if="assets.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-building"></i>
          </div>
          <h3>No assets found</h3>
          <p>{{ searchQuery ? 'No assets match your search criteria' : 'Start by adding your first fixed asset' }}</p>
          <button v-if="!searchQuery" @click="createAsset" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add First Asset
          </button>
        </div>

        <div v-else class="assets-grid">
          <div 
            v-for="asset in assets" 
            :key="asset.asset_id" 
            class="asset-card"
            @click="viewAsset(asset.asset_id)"
          >
            <div class="asset-header">
              <div class="asset-icon">
                <i :class="getAssetIcon(asset.category)"></i>
              </div>
              <div class="asset-status" :class="asset.status.toLowerCase().replace(' ', '-')">
                {{ asset.status }}
              </div>
            </div>
            
            <div class="asset-content">
              <h3 class="asset-name">{{ asset.name }}</h3>
              <p class="asset-code">{{ asset.asset_code }}</p>
              <p class="asset-category">{{ asset.category }}</p>
            </div>
            
            <div class="asset-details">
              <div class="detail-row">
                <span class="label">Acquisition Cost:</span>
                <span class="value">${{ formatNumber(asset.acquisition_cost) }}</span>
              </div>
              <div class="detail-row">
                <span class="label">Current Value:</span>
                <span class="value">${{ formatNumber(asset.current_value) }}</span>
              </div>
              <div class="detail-row">
                <span class="label">Depreciation Rate:</span>
                <span class="value">{{ asset.depreciation_rate }}%</span>
              </div>
              <div class="detail-row">
                <span class="label">Acquisition Date:</span>
                <span class="value">{{ formatDate(asset.acquisition_date) }}</span>
              </div>
            </div>
            
            <div class="asset-actions" @click.stop>
              <button @click="editAsset(asset.asset_id)" class="action-btn edit">
                <i class="fas fa-edit"></i>
              </button>
              <button @click="viewAsset(asset.asset_id)" class="action-btn view">
                <i class="fas fa-eye"></i>
              </button>
              <button @click="deleteAsset(asset)" class="action-btn delete">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="pagination-container">
          <div class="pagination-info">
            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} assets
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
              v-for="page in visiblePages" 
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
          <h3>Confirm Delete</h3>
          <button @click="closeDeleteModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete the asset <strong>{{ assetToDelete?.name }}</strong>?</p>
          <p class="warning-text">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button @click="closeDeleteModal" class="btn btn-outline">Cancel</button>
          <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-trash"></i>
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'FixedAssetsList',
  components: {
  },
  setup() {
    const router = useRouter()
    
    // Reactive data
    const assets = ref([])
    const loading = ref(false)
    const searchQuery = ref('')
    const showDeleteModal = ref(false)
    const assetToDelete = ref(null)
    const deleting = ref(false)
    
    const filters = reactive({
      category: '',
      status: ''
    })
    
    const pagination = reactive({
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 0,
      from: 0,
      to: 0
    })
    
    const stats = reactive({
      totalAssets: 0,
      activeAssets: 0,
      totalValue: 0,
      totalDepreciation: 0,
      categoriesCount: 0
    })
    
    const categories = ref([])
    let searchTimeout = null
    
    // Computed
    const visiblePages = computed(() => {
      const pages = []
      const start = Math.max(1, pagination.current_page - 2)
      const end = Math.min(pagination.last_page, pagination.current_page + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      return pages
    })
    
    // Methods
    const fetchAssets = async (page = 1) => {
      try {
        loading.value = true
        const params = {
          page,
          per_page: pagination.per_page,
          search: searchQuery.value,
          ...filters
        }
        
        const response = await axios.get('/accounting/fixed-assets', { params })
        const data = response.data
        
        assets.value = data.data
        Object.assign(pagination, {
          current_page: data.current_page,
          last_page: data.last_page,
          per_page: data.per_page,
          total: data.total,
          from: data.from,
          to: data.to
        })
        
        // Update stats
        calculateStats()
        
      } catch (error) {
        console.error('Error fetching assets:', error)
        // Show error toast/notification
      } finally {
        loading.value = false
      }
    }
    
    const calculateStats = () => {
      stats.totalAssets = assets.value.length
      stats.activeAssets = assets.value.filter(asset => asset.status === 'Active').length
      stats.totalValue = assets.value.reduce((sum, asset) => sum + parseFloat(asset.current_value || 0), 0)
      stats.totalDepreciation = assets.value.reduce((sum, asset) => 
        sum + (parseFloat(asset.acquisition_cost || 0) - parseFloat(asset.current_value || 0)), 0)
      
      const uniqueCategories = [...new Set(assets.value.map(asset => asset.category))]
      stats.categoriesCount = uniqueCategories.length
      categories.value = uniqueCategories
    }
    
    const debounceSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        fetchAssets(1)
      }, 500)
    }
    
    const applyFilters = () => {
      fetchAssets(1)
    }
    
    const clearFilters = () => {
      filters.category = ''
      filters.status = ''
      searchQuery.value = ''
      fetchAssets(1)
    }
    
    const changePage = (page) => {
      if (page >= 1 && page <= pagination.last_page) {
        fetchAssets(page)
      }
    }
    
    const createAsset = () => {
      router.push('/accounting/fixed-assets/create')
    }
    
    const editAsset = (id) => {
      router.push(`/accounting/fixed-assets/${id}/edit`)
    }
    
    const viewAsset = (id) => {
      router.push(`/accounting/fixed-assets/${id}`)
    }
    
    const generateReport = () => {
      router.push('/accounting/fixed-assets/report')
    }
    
    const deleteAsset = (asset) => {
      assetToDelete.value = asset
      showDeleteModal.value = true
    }
    
    const closeDeleteModal = () => {
      showDeleteModal.value = false
      assetToDelete.value = null
    }
    
    const confirmDelete = async () => {
      if (!assetToDelete.value) return
      
      try {
        deleting.value = true
        await axios.delete(`/accounting/fixed-assets/${assetToDelete.value.asset_id}`)
        
        // Remove from local array
        assets.value = assets.value.filter(asset => asset.asset_id !== assetToDelete.value.asset_id)
        calculateStats()
        
        closeDeleteModal()
        // Show success toast
      } catch (error) {
        console.error('Error deleting asset:', error)
        // Show error toast
      } finally {
        deleting.value = false
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
        month: 'short',
        day: 'numeric'
      })
    }
    
    // Lifecycle
    onMounted(() => {
      fetchAssets()
    })
    
    return {
      assets,
      loading,
      searchQuery,
      filters,
      pagination,
      stats,
      categories,
      showDeleteModal,
      assetToDelete,
      deleting,
      visiblePages,
      fetchAssets,
      debounceSearch,
      applyFilters,
      clearFilters,
      changePage,
      createAsset,
      editAsset,
      viewAsset,
      generateReport,
      deleteAsset,
      closeDeleteModal,
      confirmDelete,
      getAssetIcon,
      formatNumber,
      formatDate
    }
  }
}
</script>

<style scoped>
.fixed-assets-page {
  max-width: 1400px;
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

.title-section h1 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 2.5rem;
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

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
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

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
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
  flex-shrink: 0;
}

.stat-icon.active {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-icon.value {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-icon.depreciation {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-icon.categories {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.stat-content h3 {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.stat-content p {
  color: #64748b;
  font-size: 0.875rem;
  margin: 0 0 0.5rem 0;
}

.stat-trend {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.stat-trend.up {
  color: #059669;
}

.stat-trend.down {
  color: #dc2626;
}

.stat-trend.neutral {
  color: #64748b;
}

/* Filters */
.filters-section {
  margin-bottom: 2rem;
}

.filters-container {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 300px;
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

.filter-group {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.filter-select {
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  background: white;
  cursor: pointer;
  transition: all 0.3s ease;
}

.filter-select:focus {
  outline: none;
  border-color: #6366f1;
}

/* Assets Grid */
.assets-container {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}

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
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.assets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.asset-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.asset-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border-color: #6366f1;
}

.asset-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.asset-icon {
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

.asset-status {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
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

.asset-content {
  margin-bottom: 1rem;
}

.asset-name {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.asset-code {
  color: #6366f1;
  font-weight: 500;
  margin: 0 0 0.25rem 0;
}

.asset-category {
  color: #64748b;
  font-size: 0.875rem;
  margin: 0;
}

.asset-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.25rem 0;
}

.detail-row .label {
  color: #64748b;
  font-size: 0.875rem;
}

.detail-row .value {
  color: #1e293b;
  font-weight: 500;
}

.asset-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

.action-btn {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.action-btn.edit {
  background: #dbeafe;
  color: #1d4ed8;
}

.action-btn.edit:hover {
  background: #3b82f6;
  color: white;
}

.action-btn.view {
  background: #f0fdf4;
  color: #15803d;
}

.action-btn.view:hover {
  background: #22c55e;
  color: white;
}

.action-btn.delete {
  background: #fee2e2;
  color: #dc2626;
}

.action-btn.delete:hover {
  background: #ef4444;
  color: white;
}

/* Pagination */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.pagination-info {
  color: #64748b;
  font-size: 0.875rem;
}

.pagination-controls {
  display: flex;
  gap: 0.5rem;
}

.page-btn {
  width: 40px;
  height: 40px;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.page-btn:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
}

.page-btn.active {
  background: #6366f1;
  color: white;
  border-color: #6366f1;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
}

.modal-content {
  background: white;
  border-radius: 16px;
  padding: 0;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #1e293b;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: #64748b;
  padding: 0.25rem;
}

.modal-body {
  padding: 1.5rem;
}

.warning-text {
  color: #dc2626;
  font-size: 0.875rem;
  margin-top: 0.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

/* Responsive Design */
@media (max-width: 768px) {
  .fixed-assets-page {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-actions {
    order: -1;
    justify-content: flex-end;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .filters-container {
    flex-direction: column;
    align-items: stretch;
  }
  
  .search-box {
    min-width: auto;
  }
  
  .filter-group {
    justify-content: space-between;
  }
  
  .assets-grid {
    grid-template-columns: 1fr;
  }
  
  .pagination-container {
    flex-direction: column;
    gap: 1rem;
  }
}
</style>