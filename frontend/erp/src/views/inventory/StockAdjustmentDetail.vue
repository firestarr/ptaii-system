<!-- src/views/inventory/StockAdjustmentDetail.vue -->
<template>
    <div class="adjustment-detail-page">
      <div class="page-header">
        <div class="header-content">
          <h2 class="page-title">
            Stock Adjustment <span class="adjustment-id">#{{ adjustment?.adjustment_id }}</span>
          </h2>
          <div class="header-actions">
            <router-link 
              v-if="adjustment?.status === 'draft'"
              :to="`/stock-adjustments/${adjustmentId}/edit`" 
              class="btn btn-secondary"
            >
              <i class="fas fa-edit mr-1"></i> Edit
            </router-link>
            
            <button 
              v-if="adjustment?.status === 'draft'"
              @click="confirmSubmit" 
              class="btn btn-primary"
            >
              <i class="fas fa-paper-plane mr-1"></i> Submit for Approval
            </button>
            
            <button 
              v-if="adjustment?.status === 'pending'"
              @click="showApprovalModal = true" 
              class="btn btn-success"
            >
              <i class="fas fa-check mr-1"></i> Approve
            </button>
            
            <button 
              v-if="adjustment?.status === 'pending'"
              @click="showRejectModal = true" 
              class="btn btn-danger"
            >
              <i class="fas fa-times mr-1"></i> Reject
            </button>
            
            <button
              v-if="adjustment?.status === 'draft'"
              @click="confirmDelete"
              class="btn btn-outline-danger"
            >
              <i class="fas fa-trash-alt mr-1"></i> Delete
            </button>
            
            <button 
              @click="printAdjustment" 
              class="btn btn-secondary"
            >
              <i class="fas fa-print mr-1"></i> Print
            </button>
          </div>
        </div>
        
        <div class="breadcrumbs">
          <router-link to="/stock-adjustments" class="breadcrumb-item">
            <i class="fas fa-sliders-h mr-1"></i> Stock Adjustments
          </router-link>
          <span class="breadcrumb-separator">/</span>
          <span class="breadcrumb-item active">Detail</span>
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
  
      <div v-else class="adjustment-detail-container">
        <div class="cards-row">
          <!-- Adjustment Information Card -->
          <div class="detail-card">
            <div class="card-header">
              <h3 class="card-title">Adjustment Information</h3>
              <span 
                class="status-badge" 
                :class="'status-' + adjustment.status"
              >
                {{ formatStatus(adjustment.status) }}
              </span>
            </div>
            <div class="card-body">
              <div class="info-grid">
                <div class="info-item">
                  <div class="info-label">ID</div>
                  <div class="info-value">{{ adjustment.adjustment_id }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Date</div>
                  <div class="info-value">{{ formatDate(adjustment.adjustment_date) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Reference Document</div>
                  <div class="info-value">{{ adjustment.reference_document || 'N/A' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Created At</div>
                  <div class="info-value">{{ formatDateTime(adjustment.created_at) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Updated At</div>
                  <div class="info-value">{{ formatDateTime(adjustment.updated_at) }}</div>
                </div>
                <div class="info-item full-width">
                  <div class="info-label">Adjustment Reason</div>
                  <div class="info-value">{{ adjustment.adjustment_reason || 'No reason provided' }}</div>
                </div>
              </div>
            </div>
          </div>
  
          <!-- Adjustment Summary Card -->
          <div class="detail-card">
            <div class="card-header">
              <h3 class="card-title">Summary</h3>
            </div>
            <div class="card-body">
              <div class="summary-stats">
                <div class="stat-item">
                  <div class="stat-value">{{ totalItems }}</div>
                  <div class="stat-label">Total Items</div>
                </div>
                <div class="stat-item">
                  <div 
                    class="stat-value" 
                    :class="getVarianceClass(adjustment.total_variance)"
                  >
                    {{ formatVariance(adjustment.total_variance) }}
                  </div>
                  <div class="stat-label">Total Variance</div>
                </div>
              </div>
              
              <div class="variance-breakdown">
                <h4 class="breakdown-title">Variance Breakdown</h4>
                <div class="breakdown-stats">
                  <div class="breakdown-item positive">
                    <div class="breakdown-label">
                      <i class="fas fa-arrow-up"></i> Positive
                    </div>
                    <div class="breakdown-value">
                      {{ positiveVarianceCount }} items, 
                      <span class="variance-value">+{{ positiveVarianceTotal }}</span>
                    </div>
                  </div>
                  <div class="breakdown-item negative">
                    <div class="breakdown-label">
                      <i class="fas fa-arrow-down"></i> Negative
                    </div>
                    <div class="breakdown-value">
                      {{ negativeVarianceCount }} items, 
                      <span class="variance-value">{{ negativeVarianceTotal }}</span>
                    </div>
                  </div>
                  <div class="breakdown-item no-change">
                    <div class="breakdown-label">
                      <i class="fas fa-minus"></i> No Change
                    </div>
                    <div class="breakdown-value">
                      {{ noChangeCount }} items
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Adjustment Lines Card -->
        <div class="detail-card mt-4">
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
  
        <!-- Approval History Card -->
        <div v-if="adjustment.status !== 'draft'" class="detail-card mt-4">
          <div class="card-header">
            <h3 class="card-title">Approval History</h3>
          </div>
          <div class="card-body">
            <div class="timeline">
              <div class="timeline-item">
                <div class="timeline-icon">
                  <i class="fas fa-file-alt"></i>
                </div>
                <div class="timeline-content">
                  <div class="timeline-title">Created</div>
                  <div class="timeline-date">{{ formatDateTime(adjustment.created_at) }}</div>
                  <div class="timeline-description">Adjustment was created</div>
                </div>
              </div>
              
              <div class="timeline-item">
                <div class="timeline-icon">
                  <i class="fas fa-paper-plane"></i>
                </div>
                <div class="timeline-content">
                  <div class="timeline-title">Submitted</div>
                  <div class="timeline-date">{{ formatDateTime(adjustment.updated_at) }}</div>
                  <div class="timeline-description">Submitted for approval</div>
                </div>
              </div>
              
              <div v-if="['approved', 'rejected'].includes(adjustment.status)" class="timeline-item">
                <div class="timeline-icon" :class="adjustment.status === 'approved' ? 'approved' : 'rejected'">
                  <i :class="adjustment.status === 'approved' ? 'fas fa-check' : 'fas fa-times'"></i>
                </div>
                <div class="timeline-content">
                  <div class="timeline-title">{{ adjustment.status === 'approved' ? 'Approved' : 'Rejected' }}</div>
                  <div class="timeline-date">{{ formatDateTime(adjustment.updated_at) }}</div>
                  <div class="timeline-description">
                    {{ adjustment.status === 'approved' ? 'Adjustment was approved and processed' : 'Adjustment was rejected' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Submit Confirmation Modal -->
      <div v-if="showSubmitModal" class="modal-backdrop">
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h3>Submit for Approval</h3>
            <button class="btn-close" @click="showSubmitModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Are you sure you want to submit this adjustment for approval?
              Once submitted, you won't be able to edit it further.
            </p>
          </div>
          <div class="modal-footer">
            <button 
              class="btn btn-secondary" 
              @click="showSubmitModal = false"
            >
              Cancel
            </button>
            <button 
              class="btn btn-primary" 
              @click="submitAdjustment"
              :disabled="isProcessing"
            >
              <i v-if="isProcessing" class="fas fa-spinner fa-spin mr-2"></i>
              Submit
            </button>
          </div>
        </div>
      </div>
  
      <!-- Delete Confirmation Modal -->
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
              <strong>ID #{{ adjustment?.adjustment_id }}</strong>
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
            <button class="btn btn-danger" @click="deleteAdjustment" :disabled="isProcessing">
              <i v-if="isProcessing" class="fas fa-spinner fa-spin mr-2"></i>
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
              <strong>ID #{{ adjustment?.adjustment_id }}</strong>.
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
              @click="approveAdjustment" 
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
    name: 'StockAdjustmentDetail',
    setup() {
      const route = useRoute();
      const router = useRouter();
      const adjustmentId = computed(() => route.params.id);
      
      // Data
      const adjustment = ref(null);
      const loading = ref(true);
      const error = ref(null);
      const searchQuery = ref('');
      
      // Modal state
      const showSubmitModal = ref(false);
      const showDeleteModal = ref(false);
      const showApprovalModal = ref(false);
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
      const totalItems = computed(() => {
        if (!adjustment.value || !adjustment.value.adjustment_lines) return 0;
        return adjustment.value.adjustment_lines.length;
      });
      
      const positiveVarianceCount = computed(() => {
        if (!adjustment.value || !adjustment.value.adjustment_lines) return 0;
        return adjustment.value.adjustment_lines.filter(line => line.variance > 0).length;
      });
      
      const negativeVarianceCount = computed(() => {
        if (!adjustment.value || !adjustment.value.adjustment_lines) return 0;
        return adjustment.value.adjustment_lines.filter(line => line.variance < 0).length;
      });
      
      const noChangeCount = computed(() => {
        if (!adjustment.value || !adjustment.value.adjustment_lines) return 0;
        return adjustment.value.adjustment_lines.filter(line => line.variance === 0).length;
      });
      
      const positiveVarianceTotal = computed(() => {
        if (!adjustment.value || !adjustment.value.adjustment_lines) return 0;
        return adjustment.value.adjustment_lines
          .filter(line => line.variance > 0)
          .reduce((sum, line) => sum + line.variance, 0);
      });
      
      const negativeVarianceTotal = computed(() => {
        if (!adjustment.value || !adjustment.value.adjustment_lines) return 0;
        return adjustment.value.adjustment_lines
          .filter(line => line.variance < 0)
          .reduce((sum, line) => sum + line.variance, 0);
      });
      
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
      
      const confirmSubmit = () => {
        showSubmitModal.value = true;
      };
      
      const submitAdjustment = async () => {
        isProcessing.value = true;
        
        try {
          await axios.post(`/stock-adjustments/${adjustmentId.value}/submit`);
          
          // Update the local state
          if (adjustment.value) {
            adjustment.value.status = 'pending';
          }
          
          showSubmitModal.value = false;
          
          // Show success message
          alert('Adjustment submitted for approval successfully');
        } catch (err) {
          console.error('Error submitting adjustment:', err);
          alert('Failed to submit adjustment. Please try again.');
        } finally {
          isProcessing.value = false;
        }
      };
      
      const confirmDelete = () => {
        showDeleteModal.value = true;
      };
      
      const deleteAdjustment = async () => {
        isProcessing.value = true;
        
        try {
          await axios.delete(`/stock-adjustments/${adjustmentId.value}`);
          
          showDeleteModal.value = false;
          
          // Navigate back to the list
          router.push('/stock-adjustments');
        } catch (err) {
          console.error('Error deleting adjustment:', err);
          
          if (err.response && err.response.status === 422) {
            alert(err.response.data.message || 'Cannot delete this adjustment.');
          } else {
            alert('Failed to delete adjustment. Please try again.');
          }
        } finally {
          isProcessing.value = false;
        }
      };
      
      const approveAdjustment = async () => {
        isProcessing.value = true;
        
        try {
          const payload = {
            create_adjustment: approvalForm.value.create_adjustment
          };
          
          if (approvalForm.value.create_adjustment) {
            payload.adjustment_reason = approvalForm.value.adjustment_reason;
          }
          
          await axios.post(`/stock-adjustments/${adjustmentId.value}/approve`, payload);
          
          // Update the local state
          if (adjustment.value) {
            adjustment.value.status = 'approved';
          }
          
          showApprovalModal.value = false;
          
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
        isProcessing.value = true;
        
        try {
          await axios.post(`/stock-adjustments/${adjustmentId.value}/reject`, {
            rejection_reason: rejectForm.value.rejection_reason
          });
          
          // Update the local state
          if (adjustment.value) {
            adjustment.value.status = 'rejected';
          }
          
          showRejectModal.value = false;
          
          // Show success message
          alert('Adjustment rejected successfully');
        } catch (err) {
          console.error('Error rejecting adjustment:', err);
          alert('Failed to reject adjustment. Please try again.');
        } finally {
          isProcessing.value = false;
        }
      };
      
      const printAdjustment = () => {
        window.print();
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
      
      const formatDateTime = (dateString) => {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'short', 
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
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
        showSubmitModal,
        showDeleteModal,
        showApprovalModal,
        showRejectModal,
        isProcessing,
        approvalForm,
        rejectForm,
        totalItems,
        positiveVarianceCount,
        negativeVarianceCount,
        noChangeCount,
        positiveVarianceTotal,
        negativeVarianceTotal,
        filteredLines,
        fetchAdjustment,
        confirmSubmit,
        submitAdjustment,
        confirmDelete,
        deleteAdjustment,
        approveAdjustment,
        rejectAdjustment,
        printAdjustment,
        formatDate,
        formatDateTime,
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
.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
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

/* For the print functionality */
@media print {
  .header-actions, 
  .search-input,
  .breadcrumbs,
  .modal-backdrop {
    display: none !important;
  }
  
  .page-header {
    margin-bottom: 1rem;
  }
  
  .detail-card, 
  .approval-card {
    break-inside: avoid;
    page-break-inside: avoid;
    box-shadow: none;
    border: 1px solid var(--gray-200);
    margin-bottom: 1rem;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
  
  body {
    font-size: 12pt;
  }
  
  .adjustments-table th,
  .adjustments-table td {
    padding: 0.5rem;
  }
}

/* Additional responsive styles for smaller screens */
@media (max-width: 576px) {
  .page-title {
    font-size: 1.5rem;
  }
  
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .search-input {
    margin-top: 0.5rem;
    width: 100%;
  }
  
  .variance-breakdown .breakdown-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }
  
  .timeline {
    padding-left: 1.5rem;
  }
  
  .timeline-icon {
    left: -1.25rem;
    width: 1.5rem;
    height: 1.5rem;
    font-size: 0.75rem;
  }
}

/* High contrast mode styles for accessibility */
@media (prefers-contrast: high) {
  .status-badge,
  .variance-positive,
  .variance-negative,
  .status-indicator {
    border: 1px solid currentColor;
  }
  
  .breakdown-item {
    border: 1px solid currentColor;
  }
  
  .timeline::before {
    background-color: var(--gray-700);
  }
}

/* For the status indicator in the adjustment lines */
.status-indicator {
  display: inline-block;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  margin-right: 0.5rem;
  vertical-align: middle;
}

/* Better visibility for hovering on clickable elements */
.btn:hover:not(:disabled),
.btn-icon:hover,
.btn-link:hover {
  transform: translateY(-1px);
}

/* Additional transition effects for better UX */
.card-header, 
.card-body, 
.status-badge, 
.timeline-icon,
.btn,
.btn-icon {
  transition: all 0.2s ease-in-out;
}

/* Additional classes for animation states */
.is-loading {
  opacity: 0.7;
  pointer-events: none;
}

/* For focus states to improve accessibility */
.btn:focus,
.form-control:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
}

.btn:focus:not(:focus-visible),
.form-control:focus:not(:focus-visible) {
  box-shadow: none;
}

.btn:focus-visible,
.form-control:focus-visible {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* Hover effects for table rows */
.adjustments-table tbody tr {
  transition: background-color 0.15s ease-in-out;
}

.adjustments-table tbody tr:hover {
  background-color: var(--gray-50);
}

/* Additional styles for empty state messaging */
.empty-state {
  padding: 3rem 1rem;
  text-align: center;
  background-color: var(--gray-50);
  border-radius: 0.5rem;
  margin: 1rem 0;
}

.empty-state-icon {
  font-size: 3rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

.empty-state-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: var(--gray-700);
}

.empty-state-description {
  color: var(--gray-600);
  max-width: 400px;
  margin: 0 auto;
}
</style>