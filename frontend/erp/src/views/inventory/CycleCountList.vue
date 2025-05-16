<!-- src/views/inventory/CycleCountList.vue -->
<template>
    <div class="cycle-count-list">
      <div class="page-header">
        <h1>Cycle Counting</h1>
        <div class="page-actions">
          <router-link to="/cycle-counts/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Count
          </router-link>
          <router-link to="/cycle-counts/generate" class="btn btn-secondary ml-2">
            <i class="fas fa-tasks"></i> Generate Count Tasks
          </router-link>
        </div>
      </div>
  
      <!-- Filters -->
      <div class="filter-card card mb-4">
        <div class="card-body">
          <div class="row">
            <div class="col-md-3 mb-3">
              <label class="form-label">Item</label>
              <select v-model="filters.item_id" class="form-select">
                <option value="">All Items</option>
                <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                  {{ item.item_code }} - {{ item.name }}
                </option>
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Warehouse</label>
              <select v-model="filters.warehouse_id" class="form-select">
                <option value="">All Warehouses</option>
                <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                  {{ warehouse.name }}
                </option>
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Status</label>
              <select v-model="filters.status" class="form-select">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="completed">Completed</option>
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Date Range</label>
              <div class="input-group">
                <input type="date" v-model="filters.start_date" class="form-control" placeholder="Start Date">
                <span class="input-group-text">to</span>
                <input type="date" v-model="filters.end_date" class="form-control" placeholder="End Date">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-12 text-end">
              <button @click="loadCycleCounts" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
              </button>
              <button @click="resetFilters" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-undo"></i> Reset
              </button>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Results Table -->
      <div class="card">
        <div class="card-body">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading cycle counts...</p>
          </div>
  
          <div v-else-if="cycleCounts.length === 0" class="text-center py-5">
            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
            <h3>No Cycle Counts Found</h3>
            <p class="text-muted">Try adjusting your filters or create a new cycle count.</p>
          </div>
  
          <div v-else class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Item</th>
                  <th>Warehouse</th>
                  <th>Book Qty</th>
                  <th>Actual Qty</th>
                  <th>Variance</th>
                  <th>Variance %</th>
                  <th>Count Date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="count in cycleCounts" :key="count.count_id">
                  <td>{{ count.count_id }}</td>
                  <td>
                    <div class="d-flex align-items-center">
                      <span class="me-2">{{ count.item?.item_code }}</span>
                      <span class="text-muted small">{{ count.item?.name }}</span>
                    </div>
                  </td>
                  <td>{{ count.warehouse?.name }}</td>
                  <td>{{ count.book_quantity }}</td>
                  <td>{{ count.actual_quantity }}</td>
                  <td :class="getVarianceClass(count.variance)">{{ count.variance }}</td>
                  <td :class="getVarianceClass(getVariancePercentage(count))">
                    {{ getVariancePercentage(count) }}%
                  </td>
                  <td>{{ formatDate(count.count_date) }}</td>
                  <td>
                    <span :class="getStatusBadgeClass(count.status)" class="badge">
                      {{ count.status.toUpperCase() }}
                    </span>
                  </td>
                  <td>
                    <div class="btn-group">
                      <router-link :to="`/cycle-counts/${count.count_id}`" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                      </router-link>
                      <button v-if="count.status === 'draft'" 
                        @click="navigateToEdit(count.count_id)" 
                        class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button v-if="count.status === 'draft'" 
                        @click="submitCount(count)" 
                        class="btn btn-sm btn-outline-success">
                        <i class="fas fa-paper-plane"></i>
                      </button>
                      <button v-if="count.status === 'pending'" 
                        @click="navigateToApproval(count.count_id)" 
                        class="btn btn-sm btn-outline-info">
                        <i class="fas fa-check-circle"></i>
                      </button>
                      <button v-if="count.status === 'draft'" 
                        @click="confirmDelete(count)" 
                        class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
  
      <!-- Pagination -->
      <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="pagination-info">
          Showing {{ (currentPage - 1) * perPage + 1 }} to 
          {{ Math.min(currentPage * perPage, totalCounts) }} of {{ totalCounts }} items
        </div>
        <div class="pagination">
          <button 
            class="btn btn-outline-secondary me-2" 
            :disabled="currentPage === 1"
            @click="changePage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i>
          </button>
          
          <button 
            v-for="page in displayedPages" 
            :key="page" 
            class="btn me-2" 
            :class="page === currentPage ? 'btn-primary' : 'btn-outline-secondary'"
            @click="changePage(page)"
          >
            {{ page }}
          </button>
          
          <button 
            class="btn btn-outline-secondary" 
            :disabled="currentPage === totalPages"
            @click="changePage(currentPage + 1)"
          >
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
      <div v-if="showConfirmModal" class="modal-backdrop" @click="closeModal"></div>
      <div v-if="showConfirmModal" class="modal" tabindex="-1" style="display: block;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">{{ confirmModalTitle }}</h5>
              <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <p>{{ confirmModalMessage }}</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
              <button type="button" class="btn btn-primary" @click="confirmAction">Confirm</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'CycleCountList',
    data() {
      return {
        cycleCounts: [],
        items: [],
        warehouses: [],
        loading: false,
        filters: {
          item_id: '',
          warehouse_id: '',
          status: '',
          start_date: '',
          end_date: ''
        },
        perPage: 10,
        currentPage: 1,
        totalCounts: 0,
        totalPages: 1,
        showConfirmModal: false,
        confirmModalTitle: '',
        confirmModalMessage: '',
        confirmActionCallback: null,
        selectedCount: null
      };
    },
    computed: {
      displayedPages() {
        const total = this.totalPages;
        const current = this.currentPage;
        const pages = [];
        
        if (total <= 7) {
          // Show all pages if 7 or fewer
          for (let i = 1; i <= total; i++) {
            pages.push(i);
          }
        } else {
          // Always include first page
          pages.push(1);
          
          // Show ellipsis if current page is more than 3
          if (current > 3) {
            pages.push('...');
          }
          
          // Add pages around current page
          const startPage = Math.max(2, current - 1);
          const endPage = Math.min(total - 1, current + 1);
          
          for (let i = startPage; i <= endPage; i++) {
            pages.push(i);
          }
          
          // Show ellipsis if current page is less than total - 2
          if (current < total - 2) {
            pages.push('...');
          }
          
          // Always include last page
          if (total > 1) {
            pages.push(total);
          }
        }
        
        return pages;
      }
    },
    mounted() {
      this.loadItems();
      this.loadWarehouses();
      this.loadCycleCounts();
    },
    methods: {
      async loadItems() {
        try {
          const response = await axios.get('/items');
          this.items = response.data.data || [];
        } catch (error) {
          console.error('Error loading items:', error);
        }
      },
      async loadWarehouses() {
        try {
          const response = await axios.get('/warehouses');
          this.warehouses = response.data.data || [];
        } catch (error) {
          console.error('Error loading warehouses:', error);
        }
      },
      async loadCycleCounts() {
        this.loading = true;
        try {
          const queryParams = new URLSearchParams();
          
          // Add filters
          if (this.filters.item_id) queryParams.append('item_id', this.filters.item_id);
          if (this.filters.warehouse_id) queryParams.append('warehouse_id', this.filters.warehouse_id);
          if (this.filters.status) queryParams.append('status', this.filters.status);
          if (this.filters.start_date) queryParams.append('start_date', this.filters.start_date);
          if (this.filters.end_date) queryParams.append('end_date', this.filters.end_date);
          
          // Add pagination
          queryParams.append('page', this.currentPage);
          queryParams.append('per_page', this.perPage);
          
          const response = await axios.get(`/cycle-counts?${queryParams.toString()}`);
          
          if (response.data.success) {
            this.cycleCounts = response.data.data.data || [];
            this.totalCounts = response.data.data.total || 0;
            this.totalPages = response.data.data.last_page || 1;
            this.currentPage = response.data.data.current_page || 1;
          } else {
            console.error('API returned error:', response.data.message);
          }
        } catch (error) {
          console.error('Error loading cycle counts:', error);
        } finally {
          this.loading = false;
        }
      },
      resetFilters() {
        this.filters = {
          item_id: '',
          warehouse_id: '',
          status: '',
          start_date: '',
          end_date: ''
        };
        this.currentPage = 1;
        this.loadCycleCounts();
      },
      changePage(page) {
        if (page === '...') return;
        this.currentPage = parseInt(page);
        this.loadCycleCounts();
      },
      getVarianceClass(variance) {
        if (!variance) return '';
        
        if (parseFloat(variance) > 0) {
          return 'text-success';
        } else if (parseFloat(variance) < 0) {
          return 'text-danger';
        }
        return '';
      },
      getVariancePercentage(count) {
        if (!count.book_quantity || count.book_quantity === 0) {
          return count.variance ? '100.00' : '0.00';
        }
        
        const percentage = (count.variance / count.book_quantity) * 100;
        return percentage.toFixed(2);
      },
      getStatusBadgeClass(status) {
        switch (status) {
          case 'draft':
            return 'bg-secondary';
          case 'pending':
            return 'bg-warning text-dark';
          case 'approved':
            return 'bg-success';
          case 'rejected':
            return 'bg-danger';
          case 'completed':
            return 'bg-info';
          default:
            return 'bg-secondary';
        }
      },
      formatDate(dateString) {
        if (!dateString) return '-';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      },
      navigateToEdit(countId) {
        this.$router.push(`/cycle-counts/${countId}/edit`);
      },
      navigateToApproval(countId) {
        this.$router.push(`/cycle-counts/${countId}/approve`);
      },
      submitCount(count) {
        this.confirmModalTitle = 'Submit Cycle Count';
        this.confirmModalMessage = `Are you sure you want to submit cycle count #${count.count_id} for approval?`;
        this.confirmActionCallback = () => this.doSubmitCount(count);
        this.selectedCount = count;
        this.showConfirmModal = true;
      },
      async doSubmitCount(count) {
        try {
          const response = await axios.post(`/cycle-counts/${count.count_id}/submit`);
          
          if (response.data.success) {
            this.showSuccessToast('Cycle count submitted for approval.');
            this.loadCycleCounts();
          } else {
            this.showErrorToast('Failed to submit cycle count: ' + response.data.message);
          }
        } catch (error) {
          this.showErrorToast('Error submitting cycle count: ' + this.getErrorMessage(error));
        }
        this.closeModal();
      },
      confirmDelete(count) {
        this.confirmModalTitle = 'Delete Cycle Count';
        this.confirmModalMessage = `Are you sure you want to delete cycle count #${count.count_id}? This action cannot be undone.`;
        this.confirmActionCallback = () => this.doDeleteCount(count);
        this.selectedCount = count;
        this.showConfirmModal = true;
      },
      async doDeleteCount(count) {
        try {
          const response = await axios.delete(`/cycle-counts/${count.count_id}`);
          
          if (response.data.success) {
            this.showSuccessToast('Cycle count deleted successfully.');
            this.loadCycleCounts();
          } else {
            this.showErrorToast('Failed to delete cycle count: ' + response.data.message);
          }
        } catch (error) {
          this.showErrorToast('Error deleting cycle count: ' + this.getErrorMessage(error));
        }
        this.closeModal();
      },
      confirmAction() {
        if (typeof this.confirmActionCallback === 'function') {
          this.confirmActionCallback();
        }
      },
      closeModal() {
        this.showConfirmModal = false;
        this.confirmModalTitle = '';
        this.confirmModalMessage = '';
        this.confirmActionCallback = null;
        this.selectedCount = null;
      },
      showSuccessToast(message) {
        // You can replace this with your preferred toast implementation
        alert(message);
      },
      showErrorToast(message) {
        // You can replace this with your preferred toast implementation
        alert(message);
      },
      getErrorMessage(error) {
        if (error.response && error.response.data && error.response.data.message) {
          return error.response.data.message;
        }
        return error.message || 'Unknown error occurred';
      }
    }
  };
  </script>
  
  <style scoped>
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .filter-card {
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
  }
  
  .form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
  }
  
  .badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
    border-radius: 0.25rem;
    text-transform: capitalize;
  }
  
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1050;
  }
  
  .modal {
    z-index: 1055;
  }
  </style>