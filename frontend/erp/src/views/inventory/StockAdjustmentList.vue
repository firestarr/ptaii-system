<!-- src/views/inventory/StockAdjustmentList.vue -->
<template>
    <div class="stock-adjustments-page">
      <div class="page-header">
        <div class="header-content">
          <h2 class="page-title">Stock Adjustments</h2>
          <button class="btn btn-primary" @click="createNewAdjustment">
            <i class="fas fa-plus-circle mr-2"></i> New Adjustment
          </button>
        </div>
        
        <div class="filters-container">
          <div class="search-input">
            <i class="fas fa-search search-icon"></i>
            <input 
              type="text" 
              v-model="filters.search" 
              placeholder="Search by reference..." 
              class="form-control search-control"
              @input="debouncedFetchAdjustments"
            />
          </div>
          <div class="filter-group">
            <label for="status-filter">Status:</label>
            <select 
              id="status-filter" 
              v-model="filters.status" 
              class="form-control"
              @change="fetchAdjustments"
            >
              <option value="">All Statuses</option>
              <option value="draft">Draft</option>
              <option value="pending">Pending Approval</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="date-range">Date Range:</label>
            <div class="date-range-inputs">
              <input 
                type="date" 
                v-model="filters.startDate" 
                class="form-control"
                @change="fetchAdjustments"
              />
              <span class="date-separator">to</span>
              <input 
                type="date" 
                v-model="filters.endDate" 
                class="form-control"
                @change="fetchAdjustments"
              />
            </div>
          </div>
          <button class="btn btn-secondary" @click="resetFilters">
            <i class="fas fa-times mr-1"></i> Clear Filters
          </button>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading adjustments...</p>
      </div>
  
      <div v-else-if="error" class="error-container">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <p>{{ error }}</p>
        <button @click="fetchAdjustments" class="btn btn-secondary">
          <i class="fas fa-sync"></i> Try Again
        </button>
      </div>
  
      <div v-else-if="adjustments.length === 0" class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-sliders-h"></i>
        </div>
        <h3>No stock adjustments found</h3>
        <p v-if="hasActiveFilters">
          No adjustments match your current filters. Try adjusting your search criteria or
          <button @click="resetFilters" class="btn-link">clear all filters</button>
        </p>
        <p v-else>
          Get started by creating your first stock adjustment.
        </p>
        <button class="btn btn-primary mt-3" @click="createNewAdjustment">
          <i class="fas fa-plus-circle mr-2"></i> Create Adjustment
        </button>
      </div>
  
      <div v-else class="adjustments-table-container">
        <table class="adjustments-table">
          <thead>
            <tr>
              <th @click="sortBy('adjustment_id')" class="sortable-header">
                ID
                <i v-if="sortField === 'adjustment_id'" 
                  :class="['fas', sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down']"></i>
              </th>
              <th @click="sortBy('adjustment_date')" class="sortable-header">
                Date
                <i v-if="sortField === 'adjustment_date'" 
                  :class="['fas', sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down']"></i>
              </th>
              <th>Reference</th>
              <th>Items</th>
              <th>Total Variance</th>
              <th @click="sortBy('status')" class="sortable-header">
                Status
                <i v-if="sortField === 'status'" 
                  :class="['fas', sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down']"></i>
              </th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="adjustment in adjustments" :key="adjustment.adjustment_id">
              <td>{{ adjustment.adjustment_id }}</td>
              <td>{{ formatDate(adjustment.adjustment_date) }}</td>
              <td>{{ adjustment.reference_document || '-' }}</td>
              <td class="text-center">{{ getItemCount(adjustment) }}</td>
              <td :class="getVarianceClass(adjustment)">
                {{ formatVariance(adjustment.total_variance) }}
              </td>
              <td>
                <span class="status-badge" :class="'status-' + adjustment.status">
                  {{ formatStatus(adjustment.status) }}
                </span>
              </td>
              <td class="actions-cell">
                <router-link 
                  :to="`/stock-adjustments/${adjustment.adjustment_id}`" 
                  class="btn-icon" 
                  title="View Details"
                >
                  <i class="fas fa-eye"></i>
                </router-link>
                
                <router-link 
                  v-if="adjustment.status === 'draft'"
                  :to="`/stock-adjustments/${adjustment.adjustment_id}/edit`" 
                  class="btn-icon" 
                  title="Edit Adjustment"
                >
                  <i class="fas fa-edit"></i>
                </router-link>
                
                <button 
                  v-if="adjustment.status === 'draft'"
                  @click="submitAdjustment(adjustment)" 
                  class="btn-icon" 
                  title="Submit for Approval"
                >
                  <i class="fas fa-paper-plane"></i>
                </button>
                
                <button 
                  v-if="adjustment.status === 'pending'"
                  @click="approveAdjustment(adjustment)" 
                  class="btn-icon" 
                  title="Approve Adjustment"
                >
                  <i class="fas fa-check"></i>
                </button>
                
                <button 
                  v-if="adjustment.status === 'pending'"
                  @click="rejectAdjustment(adjustment)" 
                  class="btn-icon" 
                  title="Reject Adjustment"
                >
                  <i class="fas fa-times"></i>
                </button>
                
                <button 
                  v-if="adjustment.status === 'draft'"
                  @click="confirmDelete(adjustment)" 
                  class="btn-icon btn-danger" 
                  title="Delete Adjustment"
                >
                  <i class="fas fa-trash-alt"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
  
        <div class="pagination-container">
          <div class="pagination-info">
            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} entries
          </div>
          <div class="pagination-controls">
            <button 
              class="pagination-btn" 
              :disabled="!pagination.prev_page_url"
              @click="goToPage(pagination.current_page - 1)"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
            <span class="pagination-pages">
              Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <button 
              class="pagination-btn" 
              :disabled="!pagination.next_page_url"
              @click="goToPage(pagination.current_page + 1)"
            >
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal for deletion -->
      <div v-if="showDeleteModal" class="modal-backdrop">
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h3>Delete Confirmation</h3>
            <button class="btn-close" @click="showDeleteModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Are you sure you want to delete this adjustment?
              <strong>ID #{{ adjustmentToDelete?.adjustment_id }}</strong>
            </p>
            <p class="text-danger">
              <i class="fas fa-exclamation-triangle mr-1"></i>
              This action cannot be undone.
            </p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showDeleteModal = false">
              Cancel
            </button>
            <button class="btn btn-danger" @click="deleteAdjustment" :disabled="isDeleting">
              <i v-if="isDeleting" class="fas fa-spinner fa-spin mr-2"></i>
              Delete
            </button>
          </div>
        </div>
      </div>
  
      <!-- Approval Modal -->
      <div v-if="showApprovalModal" class="modal-backdrop">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Approve Adjustment</h3>
            <button class="btn-close" @click="showApprovalModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>
              You are about to approve stock adjustment 
              <strong>ID #{{ adjustmentToAction?.adjustment_id }}</strong>.
            </p>
            <p>This will process the adjustment and update stock levels accordingly.</p>
            
            <div class="form-group">
              <div class="checkbox-group">
                <input 
                  type="checkbox" 
                  id="createAdjustment" 
                  v-model="approvalForm.create_adjustment"
                />
                <label for="createAdjustment">Create adjustment for inventory items</label>
              </div>
            </div>
            
            <div class="form-group" v-if="approvalForm.create_adjustment">
              <label for="adjustmentReason">Adjustment Reason</label>
              <textarea
                id="adjustmentReason"
                v-model="approvalForm.adjustment_reason"
                class="form-control"
                rows="3"
                placeholder="Provide a reason for this adjustment"
                required
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showApprovalModal = false">
              Cancel
            </button>
            <button 
              class="btn btn-success" 
              @click="confirmApprove" 
              :disabled="isProcessing || (approvalForm.create_adjustment && !approvalForm.adjustment_reason)"
            >
              <i v-if="isProcessing" class="fas fa-spinner fa-spin mr-2"></i>
              Approve
            </button>
          </div>
        </div>
      </div>
  
      <!-- Rejection Modal -->
      <div v-if="showRejectModal" class="modal-backdrop">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Reject Adjustment</h3>
            <button class="btn-close" @click="showRejectModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>
              You are about to reject stock adjustment 
              <strong>ID #{{ adjustmentToAction?.adjustment_id }}</strong>.
            </p>
            
            <div class="form-group">
              <label for="rejectionReason">Rejection Reason</label>
              <textarea
                id="rejectionReason"
                v-model="rejectForm.rejection_reason"
                class="form-control"
                rows="3"
                placeholder="Provide a reason for rejection"
                required
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showRejectModal = false">
              Cancel
            </button>
            <button 
              class="btn btn-danger" 
              @click="confirmReject" 
              :disabled="isProcessing || !rejectForm.rejection_reason"
            >
              <i v-if="isProcessing" class="fas fa-spinner fa-spin mr-2"></i>
              Reject
            </button>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import { ref, reactive, computed, onMounted } from 'vue';
  import { useRouter } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'StockAdjustmentList',
    setup() {
      const router = useRouter();
      
      // Data
      const adjustments = ref([]);
      const loading = ref(true);
      const error = ref(null);
      
      // Pagination
      const pagination = ref({
        current_page: 1,
        from: 0,
        to: 0,
        total: 0,
        last_page: 1,
        prev_page_url: null,
        next_page_url: null
      });
      
      // Filters
      const filters = reactive({
        search: '',
        status: '',
        startDate: '',
        endDate: '',
        page: 1
      });
      
      // Sorting
      const sortField = ref('adjustment_date');
      const sortDirection = ref('desc');
      
      // Modals
      const showDeleteModal = ref(false);
      const showApprovalModal = ref(false);
      const showRejectModal = ref(false);
      const adjustmentToDelete = ref(null);
      const adjustmentToAction = ref(null);
      const isDeleting = ref(false);
      const isProcessing = ref(false);
      
      // Forms
      const approvalForm = reactive({
        create_adjustment: true,
        adjustment_reason: ''
      });
      
      const rejectForm = reactive({
        rejection_reason: ''
      });
      
      // Computed properties
      const hasActiveFilters = computed(() => {
        return filters.search || filters.status || filters.startDate || filters.endDate;
      });
      
      // Methods
      const fetchAdjustments = async () => {
        loading.value = true;
        error.value = null;
        
        try {
          const response = await axios.get('/stock-adjustments', {
            params: {
              search: filters.search,
              status: filters.status,
              start_date: filters.startDate,
              end_date: filters.endDate,
              page: filters.page,
              sort_field: sortField.value,
              sort_direction: sortDirection.value
            }
          });
          
          adjustments.value = response.data.data;
          
          // Update pagination
          pagination.value = {
            current_page: response.data.meta.current_page,
            from: response.data.meta.from,
            to: response.data.meta.to,
            total: response.data.meta.total,
            last_page: response.data.meta.last_page,
            prev_page_url: response.data.links.prev,
            next_page_url: response.data.links.next
          };
        } catch (err) {
          console.error('Error fetching adjustments:', err);
          error.value = 'Failed to load stock adjustments. Please try again.';
        } finally {
          loading.value = false;
        }
      };
      
      // Create a debounced function for search
      let debounceTimer;
      const debouncedFetchAdjustments = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          fetchAdjustments();
        }, 300);
      };
      
      const resetFilters = () => {
        filters.search = '';
        filters.status = '';
        filters.startDate = '';
        filters.endDate = '';
        filters.page = 1;
        fetchAdjustments();
      };
      
      const sortBy = (field) => {
        if (sortField.value === field) {
          sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        } else {
          sortField.value = field;
          sortDirection.value = 'asc';
        }
        fetchAdjustments();
      };
      
      const goToPage = (page) => {
        filters.page = page;
        fetchAdjustments();
      };
      
      const createNewAdjustment = () => {
        router.push('/stock-adjustments/create');
      };
      
      const confirmDelete = (adjustment) => {
        adjustmentToDelete.value = adjustment;
        showDeleteModal.value = true;
      };
      
      const deleteAdjustment = async () => {
        if (!adjustmentToDelete.value) return;
        
        isDeleting.value = true;
        
        try {
          await axios.delete(`/stock-adjustments/${adjustmentToDelete.value.adjustment_id}`);
          showDeleteModal.value = false;
          
          // Remove item from list
          adjustments.value = adjustments.value.filter(
            a => a.adjustment_id !== adjustmentToDelete.value.adjustment_id
          );
          
          // Show success message
          alert('Stock adjustment deleted successfully');
        } catch (err) {
          console.error('Error deleting adjustment:', err);
          
          if (err.response && err.response.status === 422) {
            alert(err.response.data.message || 'Cannot delete this adjustment.');
          } else {
            alert('Failed to delete adjustment. Please try again.');
          }
        } finally {
          isDeleting.value = false;
          adjustmentToDelete.value = null;
        }
      };
      
      const submitAdjustment = async (adjustment) => {
        try {
          await axios.post(`/stock-adjustments/${adjustment.adjustment_id}/submit`);
          
          // Update the status in the local list
          const index = adjustments.value.findIndex(a => a.adjustment_id === adjustment.adjustment_id);
          if (index !== -1) {
            adjustments.value[index].status = 'pending';
          }
          
          alert('Adjustment submitted for approval');
        } catch (err) {
          console.error('Error submitting adjustment:', err);
          alert('Failed to submit adjustment. Please try again.');
        }
      };
      
      const approveAdjustment = (adjustment) => {
        adjustmentToAction.value = adjustment;
        approvalForm.create_adjustment = true;
        approvalForm.adjustment_reason = '';
        showApprovalModal.value = true;
      };
      
      const confirmApprove = async () => {
        if (!adjustmentToAction.value) return;
        
        isProcessing.value = true;
        
        try {
          const payload = {
            create_adjustment: approvalForm.create_adjustment
          };
          
          if (approvalForm.create_adjustment) {
            payload.adjustment_reason = approvalForm.adjustment_reason;
          }
          
          await axios.post(`/stock-adjustments/${adjustmentToAction.value.adjustment_id}/approve`, payload);
          
          // Update the status in the local list
          const index = adjustments.value.findIndex(a => a.adjustment_id === adjustmentToAction.value.adjustment_id);
          if (index !== -1) {
            adjustments.value[index].status = 'approved';
          }
          
          showApprovalModal.value = false;
          alert('Adjustment approved successfully');
        } catch (err) {
          console.error('Error approving adjustment:', err);
          alert('Failed to approve adjustment. Please try again.');
        } finally {
          isProcessing.value = false;
          adjustmentToAction.value = null;
        }
      };
      
      const rejectAdjustment = (adjustment) => {
        adjustmentToAction.value = adjustment;
        rejectForm.rejection_reason = '';
        showRejectModal.value = true;
      };
      
      const confirmReject = async () => {
        if (!adjustmentToAction.value) return;
        
        isProcessing.value = true;
        
        try {
          await axios.post(`/stock-adjustments/${adjustmentToAction.value.adjustment_id}/reject`, {
            rejection_reason: rejectForm.rejection_reason
          });
          
          // Update the status in the local list
          const index = adjustments.value.findIndex(a => a.adjustment_id === adjustmentToAction.value.adjustment_id);
          if (index !== -1) {
            adjustments.value[index].status = 'rejected';
          }
          
          showRejectModal.value = false;
          alert('Adjustment rejected successfully');
        } catch (err) {
          console.error('Error rejecting adjustment:', err);
          alert('Failed to reject adjustment. Please try again.');
        } finally {
          isProcessing.value = false;
          adjustmentToAction.value = null;
        }
      };
      
      // Formatting functions
      const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'short', 
          day: 'numeric' 
        });
      };
      
      const formatStatus = (status) => {
        if (!status) return '-';
        return status.charAt(0).toUpperCase() + status.slice(1);
      };
      
      const formatVariance = (variance) => {
        if (variance === undefined || variance === null) return '0';
        return variance > 0 ? `+${variance}` : variance;
      };
      
      const getVarianceClass = (adjustment) => {
        if (!adjustment.total_variance) return '';
        
        if (adjustment.total_variance > 0) {
          return 'variance-positive';
        } else if (adjustment.total_variance < 0) {
          return 'variance-negative';
        }
        return '';
      };
      
      const getItemCount = (adjustment) => {
        return adjustment.adjustment_lines ? adjustment.adjustment_lines.length : 0;
      };
      
      onMounted(() => {
        fetchAdjustments();
      });
      
      return {
        adjustments,
        loading,
        error,
        filters,
        pagination,
        sortField,
        sortDirection,
        showDeleteModal,
        showApprovalModal,
        showRejectModal,
        adjustmentToDelete,
        adjustmentToAction,
        isDeleting,
        isProcessing,
        approvalForm,
        rejectForm,
        hasActiveFilters,
        fetchAdjustments,
        debouncedFetchAdjustments,
        resetFilters,
        sortBy,
        goToPage,
        createNewAdjustment,
        confirmDelete,
        deleteAdjustment,
        submitAdjustment,
        approveAdjustment,
        confirmApprove,
        rejectAdjustment,
        confirmReject,
        formatDate,
        formatStatus,
        formatVariance,
        getVarianceClass,
        getItemCount
      };
    }
  };
  </script>
  
  <style scoped>
  .stock-adjustments-page {
    padding: 1rem;
  }
  
  .page-header {
    margin-bottom: 1.5rem;
  }
  
  .header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }
  
  .page-title {
    font-size: 1.75rem;
    margin: 0;
    color: var(--gray-800);
  }
  
  .filters-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
    background-color: white;
    padding: 1rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .search-input {
    position: relative;
    flex: 1;
    min-width: 200px;
  }
  
  .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
  }
  
  .search-control {
    padding-left: 2.25rem;
  }
  
  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .filter-group label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .date-range-inputs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .date-separator {
    color: var(--gray-500);
  }
  
  .loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .loading-spinner {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
  }
  
  .error-container {
    text-align: center;
    padding: 3rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .error-icon {
    font-size: 2.5rem;
    color: var(--danger-color);
    margin-bottom: 1rem;
  }
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    text-align: center;
  }
  
  .empty-icon {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
  }
  
  .empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--gray-700);
  }
  
  .empty-state p {
    color: var(--gray-600);
    margin-bottom: 0;
  }
  
  .btn-link {
    background: none;
    border: none;
    color: var(--primary-color);
    text-decoration: underline;
    padding: 0;
    cursor: pointer;
  }
  
  .adjustments-table-container {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
  }
  
  .adjustments-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .adjustments-table th,
  .adjustments-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .adjustments-table th {
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .sortable-header {
    cursor: pointer;
    user-select: none;
    position: relative;
  }
  
  .sortable-header i {
    margin-left: 0.5rem;
    font-size: 0.75rem;
  }
  
  .adjustments-table tr:hover td {
    background-color: var(--gray-50);
  }
  
  .text-center {
    text-align: center;
  }
  
  .variance-positive {
    color: var(--success-color);
    font-weight: 500;
  }
  
  .variance-negative {
    color: var(--danger-color);
    font-weight: 500;
  }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .status-draft {
    background-color: var(--gray-100);
    color: var(--gray-700);
  }
  
  .status-pending {
    background-color: var(--warning-bg);
    color: var(--warning-color);
  }
  
  .status-approved {
    background-color: var(--success-bg);
    color: var(--success-color);
  }
  
  .status-rejected {
    background-color: var(--danger-bg);
    color: var(--danger-color);
  }
  
  .status-completed {
    background-color: var(--primary-bg);
    color: var(--primary-color);
  }
  
  .actions-cell {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn-icon {
    background: none;
    border: none;
    color: var(--primary-color);
    padding: 0.5rem;
    border-radius: 0.25rem;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-100);
  }
  
  .btn-icon.btn-danger {
    color: var(--danger-color);
  }
  
  .btn-icon.btn-danger:hover {
    background-color: var(--danger-bg);
  }
  
  .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .pagination-info {
    color: var(--gray-600);
    font-size: 0.875rem;
  }
  
  .pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .pagination-pages {
    color: var(--gray-700);
    font-size: 0.875rem;
  }
  
  .pagination-btn {
    background-color: var(--gray-100);
    border: none;
    color: var(--gray-700);
    width: 2rem;
    height: 2rem;
    border-radius: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  
  .pagination-btn:hover:not(:disabled) {
    background-color: var(--gray-200);
  }
  
  .pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 95%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
  }
  
  .modal-sm {
    max-width: 400px;
  }
  
  .modal-header {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .btn-close {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    font-size: 1.25rem;
    line-height: 1;
    padding: 0.25rem;
  }
  
  .btn-close:hover {
    color: var(--gray-800);
  }
  
  .modal-body {
    padding: 1rem;
  }
  
  .modal-footer {
    padding: 1rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
  }
  
  .form-group {
    margin-bottom: 1rem;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .checkbox-group input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
  }
  
  .form-control {
    width: 100%;
    padding: 0.625rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    transition: border-color 0.2s;
  }
  
  .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .text-danger {
    color: var(--danger-color);
  }
  
  .mr-1 {
    margin-right: 0.25rem;
  }
  
  .mr-2 {
    margin-right: 0.5rem;
  }
  
  .mt-3 {
    margin-top: 0.75rem;
  }
  </style>