<!-- src/views/inventory/StockAdjustmentApproval.vue -->
<template>
    <div class="adjustment-approval-page">
      <div class="page-header">
        <div class="header-content">
          <h2 class="page-title">
            Adjustment Approval <span class="adjustment-id">#{{ adjustment?.adjustment_id }}</span>
          </h2>
          <div class="header-status">
            <span 
              class="status-badge" 
              :class="'status-' + adjustment?.status"
            >
              {{ formatStatus(adjustment?.status) }}
            </span>
          </div>
        </div>
        
        <div class="breadcrumbs">
          <router-link to="/stock-adjustments" class="breadcrumb-item">
            <i class="fas fa-sliders-h mr-1"></i> Stock Adjustments
          </router-link>
          <span class="breadcrumb-separator">/</span>
          <router-link :to="`/stock-adjustments/${adjustmentId}`" class="breadcrumb-item">
            Detail
          </router-link>
          <span class="breadcrumb-separator">/</span>
          <span class="breadcrumb-item active">Approval</span>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading adjustment...</p>
      </div>
  
      <div v-else-if="error" class="error-container">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <p>{{ error }}</p>
        <router-link to="/stock-adjustments" class="btn btn-primary mt-3">
          <i class="fas fa-arrow-left mr-1"></i> Back to List
        </router-link>
      </div>
  
      <div v-else-if="!adjustment" class="not-found-container">
        <div class="not-found-icon">
          <i class="fas fa-search"></i>
        </div>
        <h3>Adjustment Not Found</h3>
        <p>The requested stock adjustment could not be found or has been deleted.</p>
        <router-link to="/stock-adjustments" class="btn btn-primary mt-3">
          <i class="fas fa-arrow-left mr-1"></i> Back to List
        </router-link>
      </div>
  
      <div v-else-if="adjustment.status !== 'pending'" class="already-processed-container">
        <div class="processed-icon" :class="adjustment.status === 'approved' ? 'approved' : 'rejected'">
          <i :class="adjustment.status === 'approved' ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
        </div>
        <h3>
          Adjustment Already {{ adjustment.status === 'approved' ? 'Approved' : 'Rejected' }}
        </h3>
        <p>
          This adjustment has already been {{ adjustment.status === 'approved' ? 'approved' : 'rejected' }}.
          No further action is required.
        </p>
        <router-link :to="`/stock-adjustments/${adjustmentId}`" class="btn btn-primary mt-3">
          <i class="fas fa-eye mr-1"></i> View Details
        </router-link>
      </div>
  
      <div v-else class="approval-container">
        <div class="approval-cards">
          <!-- Adjustment Summary Card -->
          <div class="approval-card">
            <div class="card-header">
              <h3 class="card-title">Adjustment Summary</h3>
            </div>
            <div class="card-body">
              <div class="info-grid">
                <div class="info-item">
                  <div class="info-label">Adjustment Date</div>
                  <div class="info-value">{{ formatDate(adjustment.adjustment_date) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Reference Document</div>
                  <div class="info-value">{{ adjustment.reference_document || 'N/A' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Total Items</div>
                  <div class="info-value">{{ adjustment.adjustment_lines?.length || 0 }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Total Variance</div>
                  <div 
                    class="info-value" 
                    :class="getVarianceClass(adjustment.total_variance)"
                  >
                    {{ formatVariance(adjustment.total_variance) }}
                  </div>
                </div>
                <div class="info-item full-width">
                  <div class="info-label">Adjustment Reason</div>
                  <div class="info-value reason-box">
                    {{ adjustment.adjustment_reason || 'No reason provided' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
  
          <!-- Approval Actions Card -->
          <div class="approval-card">
            <div class="card-header">
              <h3 class="card-title">Approval Actions</h3>
            </div>
            <div class="card-body">
              <div class="approval-form">
                <form @submit.prevent="approveAdjustment">
                  <div class="form-group">
                    <div class="checkbox-group">
                      <input 
                        type="checkbox" 
                        id="createAdjustment" 
                        v-model="approvalForm.create_adjustment"
                      />
                      <label for="createAdjustment">Create adjustment for inventory items</label>
                    </div>
                    <small class="form-text">
                      When checked, this will create stock adjustment transactions to update inventory levels.
                    </small>
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
                  
                  <div class="action-buttons">
                    <button 
                      type="button" 
                      class="btn btn-danger"
                      @click="showRejectModal = true"
                    >
                      <i class="fas fa-times mr-1"></i> Reject
                    </button>
                    
                    <button 
                      type="submit" 
                      class="btn btn-success"
                      :disabled="isProcessing || (approvalForm.create_adjustment && !approvalForm.adjustment_reason)"
                    >
                      <i v-if="isProcessing" class="fas fa-spinner fa-spin mr-2"></i>
                      <i v-else class="fas fa-check mr-1"></i>
                      Approve
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Adjustment Lines Card -->
        <div class="approval-card mt-4">
          <div class="card-header">
            <h3 class="card-title">Adjustment Items</h3>
            <div class="search-input">
              <i class="fas fa-search search-icon"></i>
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Search items..." 
                class="form-control search-control"
              />
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="adjustments-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Warehouse</th>
                    <th>Location</th>
                    <th class="text-right">Book Qty</th>
                    <th class="text-right">Adjusted Qty</th>
                    <th class="text-right">Variance</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in filteredLines" :key="line.line_id">
                    <td class="item-cell">
                      <div class="item-code">{{ line.item?.item_code }}</div>
                      <div class="item-name">{{ line.item?.name }}</div>
                    </td>
                    <td>{{ line.warehouse?.name }}</td>
                    <td>{{ line.location?.code || 'N/A' }}</td>
                    <td class="text-right">{{ line.book_quantity }}</td>
                    <td class="text-right">{{ line.adjusted_quantity }}</td>
                    <td 
                      :class="['text-right', getVarianceClass(line.variance)]"
                    >
                      {{ formatVariance(line.variance) }}
                    </td>
                    <td>
                      <span 
                        class="status-indicator" 
                        :class="getVarianceStatusClass(line.variance)"
                      ></span>
                      {{ getVarianceStatusText(line.variance) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
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
              <strong>ID #{{ adjustment?.adjustment_id }}</strong>.
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
              @click="rejectAdjustment" 
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
  import { ref, computed, onMounted } from 'vue';
  import { useRoute, useRouter } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'StockAdjustmentApproval',
    setup() {
      const route = useRoute();
      const router = useRouter();
      const adjustmentId = computed(() => route.params.id);
      
      // Data
      const adjustment = ref(null);
      const loading = ref(true);
      const error = ref(null);
      const searchQuery = ref('');
      
      // Form state
      const showRejectModal = ref(false);
      const isProcessing = ref(false);
      
      // Forms
      const approvalForm = ref({
        create_adjustment: true,
        adjustment_reason: ''
      });
      
      const rejectForm = ref({
        rejection_reason: ''
      });
      
      // Computed properties
      const filteredLines = computed(() => {
        if (!adjustment.value || !adjustment.value.adjustment_lines) return [];
        
        if (!searchQuery.value) return adjustment.value.adjustment_lines;
        
        const query = searchQuery.value.toLowerCase();
        return adjustment.value.adjustment_lines.filter(line => 
          (line.item?.name && line.item.name.toLowerCase().includes(query)) ||
          (line.item?.item_code && line.item.item_code.toLowerCase().includes(query)) ||
          (line.warehouse?.name && line.warehouse.name.toLowerCase().includes(query)) ||
          (line.location?.code && line.location.code.toLowerCase().includes(query))
        );
      });
      
      // Methods
      const fetchAdjustment = async () => {
        loading.value = true;
        error.value = null;
        
        try {
          const response = await axios.get(`/stock-adjustments/${adjustmentId.value}`);
          adjustment.value = response.data.data;
        } catch (err) {
          console.error('Error fetching adjustment:', err);
          error.value = 'Failed to load adjustment details. Please try again.';
        } finally {
          loading.value = false;
        }
      };
      
      const approveAdjustment = async () => {
        if (approvalForm.value.create_adjustment && !approvalForm.value.adjustment_reason) {
          alert('Please provide an adjustment reason.');
          return;
        }
        
        isProcessing.value = true;
        
        try {
          const payload = {
            create_adjustment: approvalForm.value.create_adjustment
          };
          
          if (approvalForm.value.create_adjustment) {
            payload.adjustment_reason = approvalForm.value.adjustment_reason;
          }
          
          await axios.post(`/stock-adjustments/${adjustmentId.value}/approve`, payload);
          
          // Navigate to detail view
          router.push(`/stock-adjustments/${adjustmentId.value}`);
          
          // Show success message
          alert('Adjustment approved successfully');
        } catch (err) {
          console.error('Error approving adjustment:', err);
          alert('Failed to approve adjustment. Please try again.');
        } finally {
          isProcessing.value = false;
        }
      };
      
      const rejectAdjustment = async () => {
        if (!rejectForm.value.rejection_reason) {
          alert('Please provide a rejection reason.');
          return;
        }
        
        isProcessing.value = true;
        
        try {
          await axios.post(`/stock-adjustments/${adjustmentId.value}/reject`, {
            rejection_reason: rejectForm.value.rejection_reason
          });
          
          // Navigate to detail view
          router.push(`/stock-adjustments/${adjustmentId.value}`);
          
          // Show success message
          alert('Adjustment rejected successfully');
        } catch (err) {
          console.error('Error rejecting adjustment:', err);
          alert('Failed to reject adjustment. Please try again.');
        } finally {
          isProcessing.value = false;
          showRejectModal.value = false;
        }
      };
      
      // Formatting functions
      const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'short', 
          day: 'numeric' 
        });
      };
      
      const formatStatus = (status) => {
        if (!status) return 'N/A';
        return status.charAt(0).toUpperCase() + status.slice(1);
      };
      
      const formatVariance = (variance) => {
        if (variance === undefined || variance === null) return '0';
        return variance > 0 ? `+${variance}` : variance;
      };
      
      const getVarianceClass = (variance) => {
        if (!variance) return '';
        
        if (variance > 0) {
          return 'variance-positive';
        } else if (variance < 0) {
          return 'variance-negative';
        }
        return '';
      };
      
      const getVarianceStatusClass = (variance) => {
        if (!variance) return 'no-change';
        
        if (variance > 0) {
          return 'increase';
        } else if (variance < 0) {
          return 'decrease';
        }
        return 'no-change';
      };
      
      const getVarianceStatusText = (variance) => {
        if (!variance) return 'No Change';
        
        if (variance > 0) {
          return 'Increase';
        } else if (variance < 0) {
          return 'Decrease';
        }
        return 'No Change';
      };
      
      onMounted(() => {
        fetchAdjustment();
      });
      
      return {
        adjustmentId,
        adjustment,
        loading,
        error,
        searchQuery,
        showRejectModal,
        isProcessing,
        approvalForm,
        rejectForm,
        filteredLines,
        fetchAdjustment,
        approveAdjustment,
        rejectAdjustment,
        formatDate,
        formatStatus,
        formatVariance,
        getVarianceClass,
        getVarianceStatusClass,
        getVarianceStatusText
      };
    }
  };
  </script>
  
  <style scoped>
  .adjustment-approval-page {
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
  
  .adjustment-id {
    color: var(--primary-color);
  }
  
  .header-status {
    display: flex;
    align-items: center;
  }
  
  .breadcrumbs {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
  }
  
  .breadcrumb-item {
    color: var(--primary-color);
  }
  
  .breadcrumb-item.active {
    color: var(--gray-600);
  }
  
  .breadcrumb-separator {
    margin: 0 0.5rem;
    color: var(--gray-400);
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
  
  .not-found-container {
    text-align: center;
    padding: 3rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .not-found-icon {
    font-size: 2.5rem;
    color: var(--gray-400);
    margin-bottom: 1rem;
  }
  
  .already-processed-container {
    text-align: center;
    padding: 3rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .processed-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
  }
  
  .processed-icon.approved {
    color: var(--success-color);
  }
  
  .processed-icon.rejected {
    color: var(--danger-color);
  }
  
  .approval-container {
    margin-bottom: 2rem;
  }
  
  .approval-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
  }
  
  .approval-card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
  }
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .card-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .card-body {
    padding: 1rem;
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
  
  .info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }
  
  .info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .info-item.full-width {
    grid-column: span 2;
  }
  
  .info-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--gray-500);
  }
  
  .info-value {
    color: var(--gray-800);
  }
  
  .reason-box {
    background-color: var(--gray-50);
    padding: 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    line-height: 1.5;
    min-height: 80px;
  }
  
  .approval-form {
    margin-top: 0.5rem;
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
  
  .form-text {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--gray-500);
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
  
  .checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .checkbox-group input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
  }
  
  .action-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 1.5rem;
  }
  
  .search-input {
    position: relative;
    width: 200px;
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
    font-size: 0.875rem;
  }
  
  .table-responsive {
    overflow-x: auto;
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
    font-weight: 500;
    color: var(--gray-600);
    font-size: 0.875rem;
  }
  
  .adjustments-table tr:last-child td {
    border-bottom: none;
  }
  
  .adjustments-table tr:hover td {
    background-color: var(--gray-50);
  }
  
  .text-right {
    text-align: right;
  }
  
  .item-cell {
    display: flex;
    flex-direction: column;
  }
  
  .item-code {
    font-size: 0.75rem;
    color: var(--gray-500);
  }
  
  .item-name {
    font-weight: 500;
    color: var(--gray-800);
  }
  
  .variance-positive {
    color: var(--success-color);
    font-weight: 500;
  }
  
  .variance-negative {
    color: var(--danger-color);
    font-weight: 500;
  }
  
  .status-indicator {
    display: inline-block;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    margin-right: 0.5rem;
  }
  
  .status-indicator.increase {
    background-color: var(--success-color);
  }
  
  .status-indicator.decrease {
    background-color: var(--danger-color);
  }
  
  .status-indicator.no-change {
    background-color: var(--gray-400);
  }
  
  .btn {
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
  }
  
  .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-800);
  }
  
  .btn-secondary:hover:not(:disabled) {
    background-color: var(--gray-300);
  }
  
  .btn-success {
    background-color: var(--success-color);
    color: white;
  }
  
  .btn-success:hover:not(:disabled) {
    background-color: var(--success-light);
  }
  
  .btn-danger {
    background-color: var(--danger-color);
    color: white;
  }
  
  .btn-danger:hover:not(:disabled) {
    background-color: var(--danger-light);
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
  
  .mr-1 {
    margin-right: 0.25rem;
  }
  
  .mr-2 {
    margin-right: 0.5rem;
  }
  
  .mt-3 {
    margin-top: 0.75rem;
  }
  
  .mt-4 {
    margin-top: 1rem;
  }
  
  @media (max-width: 768px) {
    .header-content {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .info-grid {
      grid-template-columns: 1fr;
    }
    
    .info-item.full-width {
      grid-column: span 1;
    }
    
    .action-buttons {
      flex-direction: column-reverse;
      gap: 0.75rem;
    }
    
    .action-buttons button {
      width: 100%;
    }
    
    .search-input {
      width: 100%;
    }
  }
  </style>