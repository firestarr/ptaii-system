<template>
  <div class="accounting-periods-list">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <h1 class="page-title">
            <i class="fas fa-calendar-alt"></i>
            Accounting Periods
          </h1>
          <p class="page-subtitle">Manage your accounting periods and fiscal years</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <button @click="openCreateModal" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add New Period
          </button>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card active">
        <div class="stat-icon">
          <i class="fas fa-play-circle"></i>
        </div>
        <div class="stat-content">
          <h3>{{ stats.active }}</h3>
          <p>Active Periods</p>
        </div>
      </div>
      <div class="stat-card closed">
        <div class="stat-icon">
          <i class="fas fa-lock"></i>
        </div>
        <div class="stat-content">
          <h3>{{ stats.closed }}</h3>
          <p>Closed Periods</p>
        </div>
      </div>
      <div class="stat-card total">
        <div class="stat-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
          <h3>{{ stats.total }}</h3>
          <p>Total Periods</p>
        </div>
      </div>
      <div class="stat-card current">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3>{{ currentPeriod?.period_name || 'None' }}</h3>
          <p>Current Period</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
      <div class="filters-container">
        <div class="filter-group">
          <label>Status</label>
          <select v-model="filters.status" @change="applyFilters" class="filter-select">
            <option value="">All Status</option>
            <option value="Open">Open</option>
            <option value="Closed">Closed</option>
            <option value="Locked">Locked</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Year</label>
          <select v-model="filters.year" @change="applyFilters" class="filter-select">
            <option value="">All Years</option>
            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Search</label>
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input 
              v-model="filters.search" 
              @input="applyFilters"
              type="text" 
              placeholder="Search periods..."
              class="search-input"
            >
          </div>
        </div>
      </div>
    </div>

    <!-- Periods List -->
    <div class="content-card">
      <div class="card-header">
        <h2>Accounting Periods</h2>
        <div class="view-controls">
          <button 
            @click="viewMode = 'grid'" 
            :class="['view-btn', { active: viewMode === 'grid' }]"
          >
            <i class="fas fa-th-large"></i>
          </button>
          <button 
            @click="viewMode = 'list'" 
            :class="['view-btn', { active: viewMode === 'list' }]"
          >
            <i class="fas fa-list"></i>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading accounting periods...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredPeriods.length === 0" class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h3>No Periods Found</h3>
        <p>{{ periods.length === 0 ? 'Create your first accounting period to get started.' : 'No periods match your current filters.' }}</p>
        <button v-if="periods.length === 0" @click="openCreateModal" class="btn btn-primary">
          <i class="fas fa-plus"></i>
          Create First Period
        </button>
      </div>

      <!-- Grid View -->
      <div v-else-if="viewMode === 'grid'" class="periods-grid">
        <div 
          v-for="period in filteredPeriods" 
          :key="period.period_id" 
          class="period-card"
          :class="getPeriodCardClass(period)"
        >
          <div class="period-header">
            <h3>{{ period.period_name }}</h3>
            <span class="status-badge" :class="period.status.toLowerCase()">
              {{ period.status }}
            </span>
          </div>
          <div class="period-dates">
            <div class="date-range">
              <i class="fas fa-calendar-alt"></i>
              <span>{{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}</span>
            </div>
            <div class="period-duration">
              <i class="fas fa-clock"></i>
              <span>{{ calculateDuration(period.start_date, period.end_date) }} days</span>
            </div>
          </div>
          <div class="period-actions">
            <button @click="viewPeriod(period)" class="btn btn-sm btn-outline">
              <i class="fas fa-eye"></i>
              View
            </button>
            <button @click="editPeriod(period)" class="btn btn-sm btn-outline" :disabled="period.status === 'Locked'">
              <i class="fas fa-edit"></i>
              Edit
            </button>
            <button @click="deletePeriod(period)" class="btn btn-sm btn-danger" :disabled="period.status !== 'Open'">
              <i class="fas fa-trash"></i>
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="periods-table">
        <table>
          <thead>
            <tr>
              <th @click="sortBy('period_name')" class="sortable">
                Period Name
                <i class="fas fa-sort" :class="getSortIcon('period_name')"></i>
              </th>
              <th @click="sortBy('start_date')" class="sortable">
                Start Date
                <i class="fas fa-sort" :class="getSortIcon('start_date')"></i>
              </th>
              <th @click="sortBy('end_date')" class="sortable">
                End Date
                <i class="fas fa-sort" :class="getSortIcon('end_date')"></i>
              </th>
              <th>Duration</th>
              <th @click="sortBy('status')" class="sortable">
                Status
                <i class="fas fa-sort" :class="getSortIcon('status')"></i>
              </th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="period in filteredPeriods" :key="period.period_id" :class="getRowClass(period)">
              <td>
                <div class="period-name">
                  <strong>{{ period.period_name }}</strong>
                </div>
              </td>
              <td>{{ formatDate(period.start_date) }}</td>
              <td>{{ formatDate(period.end_date) }}</td>
              <td>{{ calculateDuration(period.start_date, period.end_date) }} days</td>
              <td>
                <span class="status-badge" :class="period.status.toLowerCase()">
                  {{ period.status }}
                </span>
              </td>
              <td>
                <div class="action-buttons">
                  <button @click="viewPeriod(period)" class="btn btn-xs btn-outline" title="View Details">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button @click="editPeriod(period)" class="btn btn-xs btn-outline" :disabled="period.status === 'Locked'" title="Edit Period">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button @click="deletePeriod(period)" class="btn btn-xs btn-danger" :disabled="period.status !== 'Open'" title="Delete Period">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>{{ isEditing ? 'Edit' : 'Create' }} Accounting Period</h3>
          <button @click="closeModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form @submit.prevent="savePeriod">
          <div class="modal-body">
            <div class="form-group">
              <label for="period_name">Period Name *</label>
              <input 
                id="period_name"
                v-model="formData.period_name" 
                type="text" 
                required
                placeholder="e.g., January 2024"
                class="form-input"
              >
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="start_date">Start Date *</label>
                <input 
                  id="start_date"
                  v-model="formData.start_date" 
                  type="date" 
                  required
                  class="form-input"
                >
              </div>
              <div class="form-group">
                <label for="end_date">End Date *</label>
                <input 
                  id="end_date"
                  v-model="formData.end_date" 
                  type="date" 
                  required
                  class="form-input"
                >
              </div>
            </div>
            <div class="form-group">
              <label for="status">Status *</label>
              <select id="status" v-model="formData.status" required class="form-select">
                <option value="Open">Open</option>
                <option value="Closed">Closed</option>
                <option value="Locked">Locked</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <i v-if="saving" class="fas fa-spinner fa-spin"></i>
              {{ saving ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content delete-modal" @click.stop>
        <div class="modal-header">
          <h3>Confirm Delete</h3>
          <button @click="closeDeleteModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="delete-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Are you sure you want to delete the period <strong>{{ periodToDelete?.period_name }}</strong>?</p>
            <p class="warning-text">This action cannot be undone.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AccountingPeriodsListPage',
  data() {
    return {
      periods: [],
      filteredPeriods: [],
      currentPeriod: null,
      loading: false,
      saving: false,
      deleting: false,
      showModal: false,
      showDeleteModal: false,
      isEditing: false,
      viewMode: 'grid',
      sortField: 'start_date',
      sortDirection: 'desc',
      periodToDelete: null,
      
      filters: {
        status: '',
        year: '',
        search: ''
      },
      
      formData: {
        period_name: '',
        start_date: '',
        end_date: '',
        status: 'Open'
      },
      
      stats: {
        active: 0,
        closed: 0,
        total: 0
      }
    }
  },
  
  computed: {
    availableYears() {
      const years = [...new Set(this.periods.map(period => new Date(period.start_date).getFullYear()))]
      return years.sort((a, b) => b - a)
    }
  },
  
  async mounted() {
    await this.loadPeriods()
    await this.loadCurrentPeriod()
  },
  
  methods: {
    async loadPeriods() {
      try {
        this.loading = true
        const response = await axios.get('/accounting/accounting-periods')
        this.periods = response.data.data
        this.applyFilters()
        this.calculateStats()
      } catch (error) {
        console.error('Error loading periods:', error)
        this.$toast?.error('Failed to load accounting periods')
      } finally {
        this.loading = false
      }
    },
    
    async loadCurrentPeriod() {
      try {
        const response = await axios.get('/accounting/accounting-periods/current')
        this.currentPeriod = response.data.data
      } catch (error) {
        console.error('Error loading current period:', error)
      }
    },
    
    calculateStats() {
      this.stats.total = this.periods.length
      this.stats.active = this.periods.filter(p => p.status === 'Open').length
      this.stats.closed = this.periods.filter(p => p.status === 'Closed' || p.status === 'Locked').length
    },
    
    applyFilters() {
      let filtered = [...this.periods]
      
      if (this.filters.status) {
        filtered = filtered.filter(p => p.status === this.filters.status)
      }
      
      if (this.filters.year) {
        filtered = filtered.filter(p => new Date(p.start_date).getFullYear().toString() === this.filters.year)
      }
      
      if (this.filters.search) {
        const search = this.filters.search.toLowerCase()
        filtered = filtered.filter(p => 
          p.period_name.toLowerCase().includes(search)
        )
      }
      
      this.sortPeriods(filtered)
    },
    
    sortBy(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortField = field
        this.sortDirection = 'asc'
      }
      this.applyFilters()
    },
    
    sortPeriods(periods) {
      periods.sort((a, b) => {
        let aVal = a[this.sortField]
        let bVal = b[this.sortField]
        
        if (this.sortField.includes('date')) {
          aVal = new Date(aVal)
          bVal = new Date(bVal)
        }
        
        if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1
        if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1
        return 0
      })
      
      this.filteredPeriods = periods
    },
    
    getSortIcon(field) {
      if (this.sortField !== field) return ''
      return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    },
    
    openCreateModal() {
      this.isEditing = false
      this.formData = {
        period_name: '',
        start_date: '',
        end_date: '',
        status: 'Open'
      }
      this.showModal = true
    },
    
    editPeriod(period) {
      this.isEditing = true
      this.formData = {
        period_id: period.period_id,
        period_name: period.period_name,
        start_date: period.start_date,
        end_date: period.end_date,
        status: period.status
      }
      this.showModal = true
    },
    
    viewPeriod(period) {
      this.$router.push(`/accounting/periods/${period.period_id}`)
    },
    
    deletePeriod(period) {
      this.periodToDelete = period
      this.showDeleteModal = true
    },
    
    async savePeriod() {
      try {
        this.saving = true
        
        if (this.isEditing) {
          await axios.put(`/accounting/accounting-periods/${this.formData.period_id}`, this.formData)
          this.$toast?.success('Period updated successfully')
        } else {
          await axios.post('/accounting/accounting-periods', this.formData)
          this.$toast?.success('Period created successfully')
        }
        
        this.closeModal()
        await this.loadPeriods()
        await this.loadCurrentPeriod()
      } catch (error) {
        console.error('Error saving period:', error)
        this.$toast?.error(error.response?.data?.message || 'Failed to save period')
      } finally {
        this.saving = false
      }
    },
    
    async confirmDelete() {
      try {
        this.deleting = true
        await axios.delete(`/accounting/accounting-periods/${this.periodToDelete.period_id}`)
        this.$toast?.success('Period deleted successfully')
        this.closeDeleteModal()
        await this.loadPeriods()
        await this.loadCurrentPeriod()
      } catch (error) {
        console.error('Error deleting period:', error)
        this.$toast?.error(error.response?.data?.message || 'Failed to delete period')
      } finally {
        this.deleting = false
      }
    },
    
    closeModal() {
      this.showModal = false
      this.formData = {
        period_name: '',
        start_date: '',
        end_date: '',
        status: 'Open'
      }
    },
    
    closeDeleteModal() {
      this.showDeleteModal = false
      this.periodToDelete = null
    },
    
    async refreshData() {
      await this.loadPeriods()
      await this.loadCurrentPeriod()
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },
    
    calculateDuration(startDate, endDate) {
      const start = new Date(startDate)
      const end = new Date(endDate)
      const diffTime = Math.abs(end - start)
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
    },
    
    getPeriodCardClass(period) {
      const now = new Date()
      const start = new Date(period.start_date)
      const end = new Date(period.end_date)
      
      if (now >= start && now <= end && period.status === 'Open') {
        return 'current-period'
      }
      return period.status.toLowerCase()
    },
    
    getRowClass(period) {
      const now = new Date()
      const start = new Date(period.start_date)
      const end = new Date(period.end_date)
      
      if (now >= start && now <= end && period.status === 'Open') {
        return 'current-row'
      }
      return ''
    }
  }
}
</script>

<style scoped>
/* Main Layout */
.accounting-periods-list {
  padding: 1.5rem;
  background: #f8fafc;
  min-height: 100vh;
}

/* Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: #3b82f6;
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

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 1rem;
  border-left: 4px solid;
}

.stat-card.active {
  border-color: #10b981;
}

.stat-card.closed {
  border-color: #ef4444;
}

.stat-card.total {
  border-color: #3b82f6;
}

.stat-card.current {
  border-color: #f59e0b;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.stat-card.active .stat-icon {
  background: #dcfce7;
  color: #10b981;
}

.stat-card.closed .stat-icon {
  background: #fecaca;
  color: #ef4444;
}

.stat-card.total .stat-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.stat-card.current .stat-icon {
  background: #fef3c7;
  color: #f59e0b;
}

.stat-content h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.stat-content p {
  color: #64748b;
  margin: 0;
  font-size: 0.9rem;
}

/* Filters */
.filters-section {
  margin-bottom: 2rem;
}

.filters-container {
  display: flex;
  gap: 1.5rem;
  align-items: flex-end;
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-group label {
  font-weight: 500;
  color: #374151;
  font-size: 0.9rem;
}

.filter-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
  min-width: 120px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-box i {
  position: absolute;
  left: 0.75rem;
  color: #9ca3af;
}

.search-input {
  padding: 0.5rem 0.75rem 0.5rem 2.25rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
  min-width: 200px;
}

/* Content Card */
.content-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.card-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.view-controls {
  display: flex;
  gap: 0.5rem;
}

.view-btn {
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.view-btn.active {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

/* Loading & Empty States */
.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f4f6;
  border-top: 4px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.empty-state i {
  font-size: 3rem;
  color: #9ca3af;
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: #374151;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: #6b7280;
  margin-bottom: 1.5rem;
}

/* Grid View */
.periods-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
  padding: 1.5rem;
}

.period-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.5rem;
  transition: all 0.2s;
  cursor: pointer;
}

.period-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.period-card.current-period {
  border-color: #f59e0b;
  background: #fffbeb;
}

.period-card.closed {
  background: #fafafa;
}

.period-card.locked {
  background: #fef2f2;
  border-color: #fca5a5;
}

.period-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.period-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.period-dates {
  margin-bottom: 1.5rem;
}

.date-range, .period-duration {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
  color: #64748b;
}

.date-range i, .period-duration i {
  color: #9ca3af;
  width: 16px;
}

.period-actions {
  display: flex;
  gap: 0.5rem;
}

/* Table View */
.periods-table {
  overflow-x: auto;
}

.periods-table table {
  width: 100%;
  border-collapse: collapse;
}

.periods-table th {
  background: #f8fafc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #e5e7eb;
  cursor: pointer;
  transition: background-color 0.2s;
}

.periods-table th.sortable:hover {
  background: #f1f5f9;
}

.periods-table th i {
  margin-left: 0.5rem;
  color: #9ca3af;
}

.periods-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.periods-table tr:hover {
  background: #f8fafc;
}

.periods-table tr.current-row {
  background: #fffbeb;
}

.period-name {
  font-weight: 500;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

/* Status Badges */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-badge.open {
  background: #dcfce7;
  color: #166534;
}

.status-badge.closed {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge.locked {
  background: #fef3c7;
  color: #92400e;
}

/* Buttons */
.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #4b5563;
}

.btn-outline {
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-outline:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #9ca3af;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8rem;
}

.btn-xs {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
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
  border-radius: 12px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #9ca3af;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  transition: color 0.2s;
}

.modal-close:hover {
  color: #6b7280;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

/* Form */
.form-group {
  margin-bottom: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group label {
  display: block;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
}

.form-input, .form-select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
  transition: border-color 0.2s;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Delete Modal */
.delete-modal .modal-content {
  max-width: 400px;
}

.delete-warning {
  text-align: center;
}

.delete-warning i {
  font-size: 3rem;
  color: #ef4444;
  margin-bottom: 1rem;
}

.delete-warning p {
  margin-bottom: 0.5rem;
}

.warning-text {
  color: #6b7280;
  font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
  .accounting-periods-list {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1.5rem;
    align-items: stretch;
  }
  
  .header-actions {
    justify-content: stretch;
  }
  
  .header-actions .btn {
    flex: 1;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .filters-container {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .periods-grid {
    grid-template-columns: 1fr;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .modal-content {
    margin: 1rem;
    max-width: none;
  }
}
</style>