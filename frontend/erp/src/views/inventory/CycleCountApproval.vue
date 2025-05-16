<!-- src/views/inventory/CycleCountApproval.vue -->
<template>
    <div class="cycle-count-approval">
      <div class="page-header">
        <h1>Approve Cycle Count</h1>
        <div class="page-actions">
          <router-link :to="`/cycle-counts/${id}`" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Detail
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading cycle count data...</p>
      </div>
  
      <div v-else>
        <!-- Status Badge -->
        <div class="alert alert-warning mb-4">
          <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
            <div>
              <h5 class="alert-heading mb-1">Pending Approval</h5>
              <p class="mb-0">
                This cycle count is waiting for your approval. Please review the count results before approving or rejecting.
              </p>
            </div>
          </div>
        </div>
  
        <!-- Main Info Card -->
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header">
                <h4 class="card-title mb-0">Count Information</h4>
              </div>
              <div class="card-body">
                <dl class="row">
                  <dt class="col-sm-4">Count ID</dt>
                  <dd class="col-sm-8">#{{ cycleCount.count_id }}</dd>
                  
                  <dt class="col-sm-4">Count Date</dt>
                  <dd class="col-sm-8">{{ formatDate(cycleCount.count_date) }}</dd>
                  
                  <dt class="col-sm-4">Warehouse</dt>
                  <dd class="col-sm-8">{{ cycleCount.warehouse?.name || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Created By</dt>
                  <dd class="col-sm-8">{{ cycleCount.user?.name || 'Unknown User' }}</dd>
                  
                  <dt class="col-sm-4">Submitted On</dt>
                  <dd class="col-sm-8">{{ formatDateTime(cycleCount.updated_at) }}</dd>
                </dl>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header">
                <h4 class="card-title mb-0">Item Information</h4>
              </div>
              <div class="card-body">
                <dl class="row mb-0">
                  <dt class="col-sm-4">Item Code</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.item_code || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Item Name</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.name || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Category</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.category?.name || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">UOM</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.unit_of_measure?.name || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Current Stock</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.current_stock || '0' }}</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Count Results Card -->
        <div class="card mb-4">
          <div class="card-header">
            <h4 class="card-title mb-0">Count Results</h4>
          </div>
          <div class="card-body">
            <div class="row count-result-container">
              <div class="col-md-4">
                <div class="count-result-card">
                  <div class="count-result-label">Book Quantity</div>
                  <div class="count-result-value">{{ cycleCount.book_quantity }}</div>
                  <div class="count-result-subtitle">Expected quantity in system</div>
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="count-result-card">
                  <div class="count-result-label">Actual Quantity</div>
                  <div class="count-result-value">{{ cycleCount.actual_quantity }}</div>
                  <div class="count-result-subtitle">Counted physical quantity</div>
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="count-result-card" :class="getVarianceCardClass()">
                  <div class="count-result-label">Variance</div>
                  <div class="count-result-value">
                    <i class="fas" :class="getVarianceIcon()"></i>
                    {{ cycleCount.variance }}
                    <span class="variance-percentage">({{ variancePercentage }}%)</span>
                  </div>
                  <div class="count-result-subtitle">
                    {{ getVarianceDescription() }}
                  </div>
                </div>
              </div>
            </div>
            
            <div class="alert mt-4" :class="getVarianceAlertClass()" v-if="showVarianceAlert">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <span><strong>Note:</strong> {{ varianceAlertMessage }}</span>
            </div>
          </div>
        </div>
  
        <!-- Adjustment Options -->
        <div class="card mb-4">
          <div class="card-header">
            <h4 class="card-title mb-0">Stock Adjustment Options</h4>
          </div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input 
                class="form-check-input" 
                type="checkbox" 
                id="createAdjustment" 
                v-model="form.create_adjustment"
              >
              <label class="form-check-label" for="createAdjustment">
                Create stock adjustment to reconcile variance
              </label>
            </div>
            
            <div v-if="form.create_adjustment">
              <div class="mb-3">
                <label for="adjustmentReason" class="form-label">Adjustment Reason</label>
                <select 
                  id="adjustmentReason" 
                  v-model="form.adjustment_reason" 
                  class="form-select"
                  :required="form.create_adjustment"
                >
                  <option value="">Select Reason</option>
                  <option value="Cycle Count Reconciliation">Cycle Count Reconciliation</option>
                  <option value="Physical Count Difference">Physical Count Difference</option>
                  <option value="Inventory Correction">Inventory Correction</option>
                  <option value="Damaged Goods">Damaged Goods</option>
                  <option value="System Error">System Error</option>
                  <option value="Other">Other</option>
                </select>
                <div v-if="errors.adjustment_reason" class="text-danger mt-1">
                  {{ errors.adjustment_reason }}
                </div>
              </div>
              
              <div v-if="form.adjustment_reason === 'Other'" class="mb-3">
                <label for="otherReason" class="form-label">Specify Other Reason</label>
                <input 
                  type="text" 
                  id="otherReason" 
                  v-model="form.other_reason" 
                  class="form-control"
                  :required="form.adjustment_reason === 'Other'"
                >
              </div>
              
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <span>
                  <strong>Note:</strong> Approving with stock adjustment will update the inventory level of 
                  <strong>{{ cycleCount.item?.name }}</strong> in 
                  <strong>{{ cycleCount.warehouse?.name }}</strong> from 
                  <strong>{{ cycleCount.book_quantity }}</strong> to 
                  <strong>{{ cycleCount.actual_quantity }}</strong>.
                </span>
              </div>
            </div>
            
            <div v-else class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <span>
                <strong>Warning:</strong> Approving without stock adjustment will only mark the cycle count as approved but won't change the inventory levels.
              </span>
            </div>
          </div>
        </div>
  
        <!-- Approval Actions -->
        <div class="card mb-4">
          <div class="card-header">
            <h4 class="card-title mb-0">Approval Decision</h4>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="approvalNotes" class="form-label">Notes (Optional)</label>
              <textarea 
                id="approvalNotes" 
                v-model="form.notes" 
                class="form-control" 
                rows="3"
                placeholder="Add any notes regarding your decision"
              ></textarea>
            </div>
            
            <div class="d-flex justify-content-end mt-4">
              <button @click="confirmReject" class="btn btn-danger me-2">
                <i class="fas fa-times-circle"></i> Reject Count
              </button>
              <button @click="confirmApprove" class="btn btn-success" :disabled="isSubmitting">
                <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                <i v-else class="fas fa-check-circle"></i> Approve Count
              </button>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
      <div v-if="showConfirmModal" class="modal-backdrop" @click="closeModal"></div>
      <div v-if="showConfirmModal" class="modal" tabindex="-1" style="display: block;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header" :class="confirmModalType === 'approve' ? 'bg-success text-white' : 'bg-danger text-white'">
              <h5 class="modal-title">{{ confirmModalTitle }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <p>{{ confirmModalMessage }}</p>
              
              <div v-if="confirmModalType === 'approve' && form.create_adjustment" class="alert alert-warning mt-3">
                <strong>Important:</strong> This will create a stock adjustment to reconcile the variance. The current stock level will be updated from {{ cycleCount.book_quantity }} to {{ cycleCount.actual_quantity }}.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
              <button 
                type="button" 
                :class="confirmModalType === 'approve' ? 'btn btn-success' : 'btn btn-danger'"
                @click="confirmAction"
              >
                {{ confirmModalType === 'approve' ? 'Approve' : 'Reject' }}
              </button>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Success Modal -->
      <div v-if="showSuccessModal" class="modal-backdrop"></div>
      <div v-if="showSuccessModal" class="modal" tabindex="-1" style="display: block;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title">Success!</h5>
              <button type="button" class="btn-close btn-close-white" @click="closeSuccessModal"></button>
            </div>
            <div class="modal-body">
              <div class="text-center mb-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3">{{ successTitle }}</h4>
                <p>{{ successMessage }}</p>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-primary" @click="viewCycleCount">
                View Cycle Count
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'CycleCountApproval',
    props: {
      id: {
        type: [String, Number],
        required: true
      }
    },
    data() {
      return {
        cycleCount: {},
        loading: true,
        isSubmitting: false,
        form: {
          create_adjustment: true,
          adjustment_reason: 'Cycle Count Reconciliation',
          other_reason: '',
          notes: ''
        },
        errors: {},
        showConfirmModal: false,
        confirmModalTitle: '',
        confirmModalMessage: '',
        confirmModalType: '',
        confirmActionCallback: null,
        showSuccessModal: false,
        successTitle: '',
        successMessage: ''
      };
    },
    computed: {
      variancePercentage() {
        if (!this.cycleCount.book_quantity || this.cycleCount.book_quantity === 0) {
          return this.cycleCount.variance ? '100.00' : '0.00';
        }
        
        const percentage = (this.cycleCount.variance / this.cycleCount.book_quantity) * 100;
        return percentage.toFixed(2);
      },
      showVarianceAlert() {
        return this.cycleCount.variance && Math.abs(this.cycleCount.variance) > 0;
      },
      varianceAlertMessage() {
        if (this.cycleCount.variance > 0) {
          return `There are ${this.cycleCount.variance} more items found during counting than what was expected in the system.`;
        } else if (this.cycleCount.variance < 0) {
          return `There are ${Math.abs(this.cycleCount.variance)} fewer items found during counting than what was expected in the system.`;
        }
        return 'No variance detected between book and actual quantities.';
      },
      effectiveAdjustmentReason() {
        if (this.form.adjustment_reason === 'Other' && this.form.other_reason) {
          return this.form.other_reason;
        }
        return this.form.adjustment_reason;
      }
    },
    mounted() {
      this.loadCycleCount();
    },
    methods: {
      async loadCycleCount() {
        this.loading = true;
        
        try {
          const response = await axios.get(`/cycle-counts/${this.id}`);
          
          if (response.data.success && response.data.data) {
            this.cycleCount = response.data.data;
            
            // Verify if the count is in pending status
            if (this.cycleCount.status !== 'pending') {
              this.$router.push(`/cycle-counts/${this.id}`);
              alert('This cycle count is not pending approval.');
            }
          } else {
            this.$router.push('/cycle-counts');
            alert('Cycle count not found');
          }
        } catch (error) {
          console.error('Error loading cycle count:', error);
          this.$router.push('/cycle-counts');
          alert('Error loading cycle count details');
        } finally {
          this.loading = false;
        }
      },
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
      },
      formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        
        const date = new Date(dateTimeString);
        return date.toLocaleString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      },
      getVarianceCardClass() {
        if (!this.cycleCount.variance) return '';
        
        if (this.cycleCount.variance > 0) {
          return 'variance-positive';
        } else if (this.cycleCount.variance < 0) {
          return 'variance-negative';
        }
        return 'variance-zero';
      },
      getVarianceIcon() {
        if (!this.cycleCount.variance || this.cycleCount.variance === 0) {
          return 'fa-equals';
        } else if (this.cycleCount.variance > 0) {
          return 'fa-arrow-up';
        } else {
          return 'fa-arrow-down';
        }
      },
      getVarianceDescription() {
        if (!this.cycleCount.variance || this.cycleCount.variance === 0) {
          return 'No variance detected';
        } else if (this.cycleCount.variance > 0) {
          return 'More items found than expected';
        } else {
          return 'Fewer items found than expected';
        }
      },
      getVarianceAlertClass() {
        if (!this.cycleCount.variance) return 'alert-info';
        
        if (this.cycleCount.variance > 0) {
          return 'alert-warning';
        } else if (this.cycleCount.variance < 0) {
          return 'alert-danger';
        }
        return 'alert-info';
      },
      validateForm() {
        this.errors = {};
        let isValid = true;
        
        if (this.form.create_adjustment && !this.form.adjustment_reason) {
          this.errors.adjustment_reason = 'Please select an adjustment reason';
          isValid = false;
        }
        
        if (this.form.adjustment_reason === 'Other' && !this.form.other_reason) {
          this.errors.other_reason = 'Please specify the reason';
          isValid = false;
        }
        
        return isValid;
      },
      confirmApprove() {
        if (!this.validateForm()) {
          return;
        }
        
        this.confirmModalTitle = 'Approve Cycle Count';
        this.confirmModalMessage = `Are you sure you want to approve cycle count #${this.cycleCount.count_id}?`;
        this.confirmModalType = 'approve';
        this.confirmActionCallback = this.approveCycleCount;
        this.showConfirmModal = true;
      },
      confirmReject() {
        this.confirmModalTitle = 'Reject Cycle Count';
        this.confirmModalMessage = `Are you sure you want to reject cycle count #${this.cycleCount.count_id}? The count will be marked as rejected and will require a new count to be performed.`;
        this.confirmModalType = 'reject';
        this.confirmActionCallback = this.rejectCycleCount;
        this.showConfirmModal = true;
      },
      async approveCycleCount() {
        this.isSubmitting = true;
        
        try {
          const response = await axios.post(`/cycle-counts/${this.id}/approve`, {
            create_adjustment: this.form.create_adjustment,
            adjustment_reason: this.effectiveAdjustmentReason
          });
          
          if (response.data.success) {
            this.successTitle = 'Cycle Count Approved';
            this.successMessage = this.form.create_adjustment 
              ? 'The cycle count has been approved and a stock adjustment has been created to reconcile the variance.'
              : 'The cycle count has been approved without creating a stock adjustment.';
            this.showSuccessModal = true;
          } else {
            alert('Failed to approve cycle count: ' + response.data.message);
          }
        } catch (error) {
          console.error('Error approving cycle count:', error);
          
          if (error.response && error.response.data) {
            if (error.response.data.errors) {
              this.errors = error.response.data.errors;
            } else if (error.response.data.message) {
              alert('Error: ' + error.response.data.message);
            }
          } else {
            alert('An unexpected error occurred. Please try again.');
          }
        } finally {
          this.isSubmitting = false;
          this.closeModal();
        }
      },
      async rejectCycleCount() {
        this.isSubmitting = true;
        
        try {
          const response = await axios.post(`/cycle-counts/${this.id}/reject`, {
            notes: this.form.notes
          });
          
          if (response.data.success) {
            this.successTitle = 'Cycle Count Rejected';
            this.successMessage = 'The cycle count has been rejected. A new count will need to be performed.';
            this.showSuccessModal = true;
          } else {
            alert('Failed to reject cycle count: ' + response.data.message);
          }
        } catch (error) {
          console.error('Error rejecting cycle count:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            alert('Error: ' + error.response.data.message);
          } else {
            alert('An unexpected error occurred. Please try again.');
          }
        } finally {
          this.isSubmitting = false;
          this.closeModal();
        }
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
        this.confirmModalType = '';
        this.confirmActionCallback = null;
      },
      closeSuccessModal() {
        this.showSuccessModal = false;
        this.viewCycleCount();
      },
      viewCycleCount() {
        this.$router.push(`/cycle-counts/${this.id}`);
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
  
  .card {
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1.5rem;
  }
  
  .card-header {
    background-color: #f8f9fa;
    padding: 1rem 1.5rem;
  }
  
  .card-title {
    margin-bottom: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #343a40;
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .count-result-container {
    margin: -0.5rem;
  }
  
  .count-result-container > .col-md-4 {
    padding: 0.5rem;
  }
  
  .count-result-card {
    padding: 1.5rem;
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    text-align: center;
    height: 100%;
  }
  
  .count-result-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
    font-weight: 500;
  }
  
  .count-result-value {
    font-size: 2rem;
    font-weight: 600;
    color: #343a40;
    margin-bottom: 0.5rem;
  }
  
  .count-result-subtitle {
    font-size: 0.75rem;
    color: #6c757d;
  }
  
  .variance-percentage {
    font-size: 1rem;
    font-weight: normal;
    margin-left: 0.5rem;
  }
  
  .variance-positive {
    background-color: #fff3cd;
  }
  
  .variance-positive .count-result-value {
    color: #fd7e14;
  }
  
  .variance-negative {
    background-color: #f8d7da;
  }
  
  .variance-negative .count-result-value {
    color: #dc3545;
  }
  
  .variance-zero {
    background-color: #d1e7dd;
  }
  
  .variance-zero .count-result-value {
    color: #198754;
  }
  
  dl.row {
    margin-bottom: 0;
  }
  
  dt {
    font-weight: 600;
    color: #495057;
  }
  
  dd {
    margin-bottom: 0.5rem;
  }
  
  .form-check-label {
    font-weight: 500;
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
  
  .btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%);
  }
  </style>