<!-- src/views/inventory/CycleCountDetail.vue -->
<template>
    <div class="cycle-count-detail">
      <div class="page-header">
        <h1>Cycle Count Detail</h1>
        <div class="page-actions">
          <router-link to="/cycle-counts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Cycle Counts
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
        <!-- Status Bar -->
        <div class="status-bar mb-4">
          <div class="status-badge" :class="getStatusClass(cycleCount.status)">
            <i class="fas" :class="getStatusIcon(cycleCount.status)"></i>
            <span>{{ formatStatus(cycleCount.status) }}</span>
          </div>
          
          <div class="status-actions">
            <div v-if="cycleCount.status === 'draft'" class="btn-group">
              <router-link :to="`/cycle-counts/${cycleCount.count_id}/edit`" class="btn btn-outline-primary">
                <i class="fas fa-edit"></i> Edit
              </router-link>
              <button @click="confirmSubmit" class="btn btn-outline-success">
                <i class="fas fa-paper-plane"></i> Submit for Approval
              </button>
              <button @click="confirmDelete" class="btn btn-outline-danger">
                <i class="fas fa-trash"></i> Delete
              </button>
            </div>
            
            <div v-if="cycleCount.status === 'pending'" class="btn-group">
              <router-link :to="`/cycle-counts/${cycleCount.count_id}/approve`" class="btn btn-outline-success">
                <i class="fas fa-check-circle"></i> Review & Approve
              </router-link>
            </div>
          </div>
        </div>
  
        <!-- Main Info Card -->
        <div class="card mb-4">
          <div class="card-header">
            <h4 class="card-title mb-0">Count Information</h4>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">Count ID</dt>
                  <dd class="col-sm-8">#{{ cycleCount.count_id }}</dd>
                  
                  <dt class="col-sm-4">Count Date</dt>
                  <dd class="col-sm-8">{{ formatDate(cycleCount.count_date) }}</dd>
                  
                  <dt class="col-sm-4">Status</dt>
                  <dd class="col-sm-8">
                    <span class="badge" :class="getStatusBadgeClass(cycleCount.status)">
                      {{ formatStatus(cycleCount.status) }}
                    </span>
                  </dd>
                </dl>
              </div>
              
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">Warehouse</dt>
                  <dd class="col-sm-8">{{ cycleCount.warehouse?.name || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Created At</dt>
                  <dd class="col-sm-8">{{ formatDateTime(cycleCount.created_at) }}</dd>
                  
                  <dt class="col-sm-4">Last Updated</dt>
                  <dd class="col-sm-8">{{ formatDateTime(cycleCount.updated_at) }}</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Item Info Card -->
        <div class="card mb-4">
          <div class="card-header">
            <h4 class="card-title mb-0">Item Information</h4>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">Item Code</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.item_code || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Item Name</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.name || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Category</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.category?.name || 'N/A' }}</dd>
                </dl>
              </div>
              
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">UOM</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.unit_of_measure?.name || 'N/A' }}</dd>
                  
                  <dt class="col-sm-4">Min. Stock</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.minimum_stock || 'Not set' }}</dd>
                  
                  <dt class="col-sm-4">Max. Stock</dt>
                  <dd class="col-sm-8">{{ cycleCount.item?.maximum_stock || 'Not set' }}</dd>
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
            <div class="row">
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
              <span>{{ varianceAlertMessage }}</span>
              
              <div v-if="cycleCount.status === 'approved'" class="mt-2">
                <strong>This variance has been approved.</strong> Stock levels have been adjusted accordingly.
              </div>
            </div>
          </div>
        </div>
  
        <!-- Adjustment Info (if approved) -->
        <div v-if="cycleCount.status === 'approved'" class="card mb-4">
          <div class="card-header bg-success text-white">
            <h4 class="card-title mb-0">
              <i class="fas fa-check-circle me-2"></i> Approved Adjustment
            </h4>
          </div>
          <div class="card-body">
            <p>This cycle count was approved and a stock adjustment was created to reconcile the variance.</p>
            
            <div class="row">
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">Adjustment ID</dt>
                  <dd class="col-sm-8">
                    <a href="#" class="text-primary">#123456</a>
                  </dd>
                  
                  <dt class="col-sm-4">Adjusted By</dt>
                  <dd class="col-sm-8">John Doe</dd>
                  
                  <dt class="col-sm-4">Adjustment Date</dt>
                  <dd class="col-sm-8">May 10, 2025</dd>
                </dl>
              </div>
              
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">Reason</dt>
                  <dd class="col-sm-8">Cycle Count Reconciliation</dd>
                  
                  <dt class="col-sm-4">Status</dt>
                  <dd class="col-sm-8">
                    <span class="badge bg-success">Completed</span>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
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
    name: 'CycleCountDetail',
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
        showConfirmModal: false,
        confirmModalTitle: '',
        confirmModalMessage: '',
        confirmActionCallback: null
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
          return `Positive variance detected: There are ${this.cycleCount.variance} more items found during counting than what was expected in the system.`;
        } else if (this.cycleCount.variance < 0) {
          return `Negative variance detected: There are ${Math.abs(this.cycleCount.variance)} fewer items found during counting than what was expected in the system.`;
        }
        return '';
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
      formatStatus(status) {
        if (!status) return 'Unknown';
        return status.charAt(0).toUpperCase() + status.slice(1);
      },
      getStatusClass(status) {
        switch (status) {
          case 'draft':
            return 'status-draft';
          case 'pending':
            return 'status-pending';
          case 'approved':
            return 'status-approved';
          case 'rejected':
            return 'status-rejected';
          case 'completed':
            return 'status-completed';
          default:
            return '';
        }
      },
      getStatusIcon(status) {
        switch (status) {
          case 'draft':
            return 'fa-edit';
          case 'pending':
            return 'fa-clock';
          case 'approved':
            return 'fa-check-circle';
          case 'rejected':
            return 'fa-times-circle';
          case 'completed':
            return 'fa-check-double';
          default:
            return 'fa-question-circle';
        }
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
        
        if (this.cycleCount.status === 'approved') {
          return 'alert-success';
        } else if (this.cycleCount.variance > 0) {
          return 'alert-warning';
        } else if (this.cycleCount.variance < 0) {
          return 'alert-danger';
        }
        return 'alert-info';
      },
      confirmSubmit() {
        this.confirmModalTitle = 'Submit Cycle Count';
        this.confirmModalMessage = `Are you sure you want to submit cycle count #${this.cycleCount.count_id} for approval? Once submitted, you will not be able to edit it anymore.`;
        this.confirmActionCallback = this.submitCycleCount;
        this.showConfirmModal = true;
      },
      async submitCycleCount() {
        try {
          const response = await axios.post(`/cycle-counts/${this.id}/submit`);
          
          if (response.data.success) {
            alert('Cycle count submitted for approval successfully.');
            this.loadCycleCount();
          } else {
            alert('Failed to submit cycle count: ' + response.data.message);
          }
        } catch (error) {
          console.error('Error submitting cycle count:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            alert('Error: ' + error.response.data.message);
          } else {
            alert('An unexpected error occurred. Please try again.');
          }
        }
        
        this.closeModal();
      },
      confirmDelete() {
        this.confirmModalTitle = 'Delete Cycle Count';
        this.confirmModalMessage = `Are you sure you want to delete cycle count #${this.cycleCount.count_id}? This action cannot be undone.`;
        this.confirmActionCallback = this.deleteCycleCount;
        this.showConfirmModal = true;
      },
      async deleteCycleCount() {
        try {
          const response = await axios.delete(`/cycle-counts/${this.id}`);
          
          if (response.data.success) {
            this.$router.push('/cycle-counts');
            alert('Cycle count deleted successfully.');
          } else {
            alert('Failed to delete cycle count: ' + response.data.message);
          }
        } catch (error) {
          console.error('Error deleting cycle count:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            alert('Error: ' + error.response.data.message);
          } else {
            alert('An unexpected error occurred. Please try again.');
          }
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
  
  .status-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-radius: 0.5rem;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
  }
  
  .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-weight: 500;
  }
  
  .status-badge i {
    margin-right: 0.5rem;
    font-size: 1.1rem;
  }
  
  .status-draft {
    background-color: #e9ecef;
    color: #495057;
  }
  
  .status-pending {
    background-color: #fff3cd;
    color: #856404;
  }
  
  .status-approved {
    background-color: #d1e7dd;
    color: #0f5132;
  }
  
  .status-rejected {
    background-color: #f8d7da;
    color: #842029;
  }
  
  .status-completed {
    background-color: #cff4fc;
    color: #055160;
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