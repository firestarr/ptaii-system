<!-- src/views/materials/MaterialPlanDetails.vue -->
<template>
    <div class="material-plan-details">
      <div v-if="isLoading" class="loading-container">
        <div class="spinner"></div>
        <p>Loading material plan details...</p>
      </div>
  
      <template v-else-if="plan">
        <!-- Header -->
        <div class="page-header">
          <div class="header-content">
            <div class="title-section">
              <button class="btn-back" @click="goBack">
                <i class="fas fa-arrow-left"></i>
              </button>
              <h1 class="page-title">Material Plan Details</h1>
            </div>
            
            <div class="header-actions">
              <button class="btn btn-outline" @click="printPlan">
                <i class="fas fa-print"></i> Print
              </button>
              <button 
                v-if="plan.status === 'Draft'" 
                class="btn btn-secondary" 
                @click="editPlan"
              >
                <i class="fas fa-edit"></i> Edit
              </button>
              <button 
                v-if="canGeneratePR" 
                class="btn btn-primary" 
                @click="generatePurchaseRequisition"
              >
                <i class="fas fa-file-invoice"></i> Generate Purchase Requisition
              </button>
            </div>
          </div>
        </div>
  
        <!-- Plan Details Card -->
        <div class="card">
          <div class="card-header">
            <h2>Material Plan Information</h2>
            <div class="status-badge" :class="statusClass">
              {{ plan.status }}
            </div>
          </div>
  
          <div class="card-body">
            <div class="details-grid">
              <div class="detail-item">
                <label>Plan ID</label>
                <p>{{ plan.plan_id }}</p>
              </div>
              <div class="detail-item">
                <label>Planning Period</label>
                <p>{{ formatPeriod(plan.planning_period) }}</p>
              </div>
              <div class="detail-item">
                <label>Material Type</label>
                <p>{{ materialTypeLabel(plan.material_type) }}</p>
              </div>
              <div class="detail-item">
                <label>Created At</label>
                <p>{{ formatDateTime(plan.created_at) }}</p>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Item Information Card -->
        <div class="card">
          <div class="card-header">
            <h2>Item Information</h2>
          </div>
          <div class="card-body">
            <div class="details-grid">
              <div class="detail-item">
                <label>Item Code</label>
                <p class="text-bold">{{ plan.item?.item_code }}</p>
              </div>
              <div class="detail-item">
                <label>Item Name</label>
                <p>{{ plan.item?.name }}</p>
              </div>
              <div class="detail-item">
                <label>Category</label>
                <p>{{ plan.item?.category?.name || '-' }}</p>
              </div>
              <div class="detail-item">
                <label>Unit of Measure</label>
                <p>{{ plan.item?.unit_of_measure?.symbol || '-' }}</p>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Quantities Card -->
        <div class="card">
          <div class="card-header">
            <h2>Quantity Details</h2>
          </div>
          <div class="card-body">
            <div class="details-grid">
              <div class="detail-item quantity-detail">
                <label>Forecast Quantity</label>
                <p>{{ formatNumber(plan.forecast_quantity) }}</p>
              </div>
              <div class="detail-item quantity-detail">
                <label>Available Stock</label>
                <p>{{ formatNumber(plan.available_stock) }}</p>
              </div>
              <div class="detail-item quantity-detail">
                <label>WIP Stock</label>
                <p>{{ formatNumber(plan.wip_stock) }}</p>
              </div>
              <div class="detail-item quantity-detail">
                <label>Buffer Percentage</label>
                <p>{{ plan.buffer_percentage }}%</p>
              </div>
              <div class="detail-item quantity-detail">
                <label>Buffer Quantity</label>
                <p>{{ formatNumber(plan.buffer_quantity) }}</p>
              </div>
              <div class="detail-item quantity-detail highlight">
                <label>Net Requirement</label>
                <p class="text-bold">{{ formatNumber(plan.net_requirement) }}</p>
              </div>
              <div class="detail-item quantity-detail highlight">
                <label>Planned Order Quantity</label>
                <p class="text-bold">{{ formatNumber(plan.planned_order_quantity) }}</p>
              </div>
            </div>
          </div>
        </div>
  
        <!-- BOM Information (if applicable) -->
        <div v-if="plan.bom" class="card">
          <div class="card-header">
            <h2>Bill of Materials (BOM)</h2>
          </div>
          <div class="card-body">
            <div class="details-grid">
              <div class="detail-item">
                <label>BOM Code</label>
                <p>{{ plan.bom?.bom_code }}</p>
              </div>
              <div class="detail-item">
                <label>Revision</label>
                <p>{{ plan.bom?.revision }}</p>
              </div>
              <div class="detail-item">
                <label>Standard Quantity</label>
                <p>{{ formatNumber(plan.bom?.standard_quantity) }} {{ plan.bom?.unit_of_measure?.symbol }}</p>
              </div>
              <div class="detail-item">
                <label>Status</label>
                <p>{{ plan.bom?.status }}</p>
              </div>
            </div>
  
            <!-- BOM Lines -->
            <div v-if="plan.bom?.bomLines" class="mt-4">
              <h3 class="section-title">BOM Components</h3>
              <table class="bom-table">
                <thead>
                  <tr>
                    <th>Item Code</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>UOM</th>
                    <th>Critical</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in plan.bom.bomLines" :key="line.line_id">
                    <td>{{ line.item?.item_code }}</td>
                    <td>{{ line.item?.name }}</td>
                    <td>{{ formatNumber(line.quantity) }}</td>
                    <td>{{ line.unit_of_measure?.symbol }}</td>
                    <td>
                      <span v-if="line.is_critical" class="badge bg-danger">Critical</span>
                      <span v-else class="badge bg-gray">Non-Critical</span>
                    </td>
                    <td>{{ line.notes || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
  
        <!-- Related Documents -->
        <div class="card">
          <div class="card-header">
            <h2>Related Documents</h2>
          </div>
          <div class="card-body">
            <div class="related-docs">
              <button 
                v-if="plan.material_type === 'FG'" 
                class="doc-button" 
                @click="viewBOM"
                :disabled="!plan.bom_id"
              >
                <i class="fas fa-file-alt"></i>
                <span>View Bill of Materials</span>
                <span v-if="!plan.bom_id" class="disabled-note">(No BOM assigned)</span>
              </button>
              
              <button 
                class="doc-button" 
                @click="viewRelatedPurchaseRequisitions"
              >
                <i class="fas fa-file-invoice"></i>
                <span>Related Purchase Requisitions</span>
              </button>
              
              <button 
                class="doc-button" 
                @click="viewStockTransactions"
              >
                <i class="fas fa-exchange-alt"></i>
                <span>Stock Transactions</span>
              </button>
            </div>
          </div>
        </div>
      </template>
  
      <div v-else class="error-container">
        <div class="error-message">
          <i class="fas fa-exclamation-circle"></i>
          <h3>Material plan not found</h3>
          <p>The material plan you're looking for doesn't exist or has been deleted.</p>
          <button class="btn btn-primary" @click="goBack">Go Back</button>
        </div>
      </div>
  
      <!-- PR Generation Modal -->
      <div v-if="showPRModal" class="modal">
        <div class="modal-backdrop" @click="closePRModal"></div>
        <div class="modal-content modal-md">
          <div class="modal-header">
            <h2>Generate Purchase Requisition</h2>
            <button class="close-btn" @click="closePRModal">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="submitPR">
              <div class="form-group">
                <label>Planning Period</label>
                <input
                  type="text"
                  :value="formatPeriod(plan.planning_period)"
                  class="form-control"
                  readonly
                />
              </div>
              <div class="form-group">
                <label>Lead Time (days) <span class="required">*</span></label>
                <input
                  type="number"
                  v-model="prForm.leadTimeDays"
                  class="form-control"
                  min="0"
                  required
                />
              </div>
              <div class="form-actions">
                <button type="button" class="btn btn-secondary" @click="closePRModal">
                  Cancel
                </button>
                <button type="submit" class="btn btn-primary" :disabled="isGeneratingPR">
                  <i class="fas fa-file-invoice" :class="{ 'fa-spin': isGeneratingPR }"></i>
                  {{ isGeneratingPR ? 'Generating...' : 'Generate PR' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'MaterialPlanDetails',
    props: {
      id: {
        type: [String, Number],
        required: true
      }
    },
    data() {
      return {
        plan: null,
        isLoading: true,
        showPRModal: false,
        isGeneratingPR: false,
        prForm: {
          leadTimeDays: 7
        }
      };
    },
    computed: {
      statusClass() {
        return {
          'Draft': 'status-draft',
          'Requisitioned': 'status-requisitioned',
          'Approved': 'status-approved'
        }[this.plan?.status] || 'status-draft';
      },
      canGeneratePR() {
        return this.plan?.material_type === 'RM' && 
               this.plan?.status === 'Draft' && 
               this.plan?.net_requirement > 0;
      }
    },
    mounted() {
      this.fetchPlanDetails();
    },
    methods: {
      async fetchPlanDetails() {
        this.isLoading = true;
        try {
          const response = await axios.get(`/material-planning/${this.id}`);
          this.plan = response.data.data;
        } catch (error) {
          console.error('Error fetching material plan details:', error);
          alert('Failed to fetch material plan details');
        } finally {
          this.isLoading = false;
        }
      },
      
      async submitPR() {
        if (!this.prForm.leadTimeDays) {
          alert('Please fill in all required fields');
          return;
        }
        
        this.isGeneratingPR = true;
        try {
          const payload = {
            period: this.plan.planning_period,
            lead_time_days: this.prForm.leadTimeDays
          };
          
          const response = await axios.post('/material-planning/purchase-requisition', payload);
          
          if (response.data.data) {
            // Redirect to PR detail page
            this.$router.push(`/purchasing/requisitions/${response.data.data.pr_id}`);
          }
          
          this.closePRModal();
          this.fetchPlanDetails();
          alert(response.data.message);
        } catch (error) {
          console.error('Error generating purchase requisition:', error);
          const message = error.response?.data?.message || 'Failed to generate purchase requisition';
          alert(message);
        } finally {
          this.isGeneratingPR = false;
        }
      },
      
      generatePurchaseRequisition() {
        this.showPRModal = true;
      },
      
      closePRModal() {
        this.showPRModal = false;
        this.prForm = {
          leadTimeDays: 7
        };
      },
      
      materialTypeLabel(type) {
        return {
          'FG': 'Finished Goods',
          'RM': 'Raw Materials'
        }[type] || type;
      },
      
      formatPeriod(period) {
        return period ? new Date(period + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'long' }) : '';
      },
      
      formatDateTime(dateTime) {
        return dateTime ? new Date(dateTime).toLocaleString() : '';
      },
      
      formatNumber(value) {
        return value ? Number(value).toLocaleString() : '0';
      },
      
      goBack() {
        this.$router.push('/materials/plans');
      },
      
      editPlan() {
        this.$router.push(`/materials/plans/${this.id}/edit`);
      },
      
      printPlan() {
        window.print();
      },
      
      viewBOM() {
        if (this.plan?.bom_id) {
          this.$router.push(`/manufacturing/boms/${this.plan.bom_id}`);
        }
      },
      
      viewRelatedPurchaseRequisitions() {
        this.$router.push(`/purchasing/requisitions?material_plan_id=${this.id}`);
      },
      
      viewStockTransactions() {
        this.$router.push(`/transactions?item_id=${this.plan.item_id}`);
      }
    }
  };
  </script>
  
  <style scoped>
  .material-plan-details {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 16px;
  }
  
  .loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    text-align: center;
  }
  
  .spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
  }
  
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
  
  .page-header {
    margin-bottom: 24px;
  }
  
  .header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .title-section {
    display: flex;
    align-items: center;
    gap: 16px;
  }
  
  .btn-back {
    background: none;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
  }
  
  .btn-back:hover {
    background: var(--gray-100);
  }
  
  .page-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .header-actions {
    display: flex;
    gap: 12px;
  }
  
  .btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  
  .btn-primary {
    background: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover {
    background: var(--primary-dark);
  }
  
  .btn-outline {
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-700);
  }
  
  .btn-outline:hover {
    background: var(--gray-50);
  }
  
  .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-800);
  }
  
  .btn-secondary:hover {
    background: var(--gray-200);
  }
  
  .card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
  }
  
  .card-header {
    padding: 16px 24px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .card-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .card-body {
    padding: 24px;
  }
  
  .status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
  }
  
  .status-draft {
    background: var(--gray-100);
    color: var(--gray-800);
  }
  
  .status-requisitioned {
    background: #dbeafe;
    color: #1e40af;
  }
  
  .status-approved {
    background: #dcfce7;
    color: #15803d;
  }
  
  .details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
  }
  
  .detail-item {
    background: var(--gray-50);
    padding: 16px;
    border-radius: 6px;
  }
  
  .detail-item label {
    display: block;
    font-size: 14px;
    color: var(--gray-600);
    margin-bottom: 4px;
  }
  
  .detail-item p {
    font-size: 16px;
    color: var(--gray-800);
    margin: 0;
  }
  
  .quantity-detail.highlight {
    background: var(--primary-bg);
    border: 1px solid var(--primary-color);
  }
  
  .text-bold {
    font-weight: 600;
  }
  
  .mt-4 {
    margin-top: 24px;
  }
  
  .section-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 16px;
  }
  
  .bom-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .bom-table th {
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid var(--gray-200);
    font-weight: 600;
    color: var(--gray-700);
    background: var(--gray-50);
  }
  
  .bom-table td {
    padding: 12px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-800);
  }
  
  .badge {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
  }
  
  .bg-danger {
    background: #fee2e2;
    color: #dc2626;
  }
  
  .bg-gray {
    background: var(--gray-100);
    color: var(--gray-800);
  }
  
  .related-docs {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  
  .doc-button {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    cursor: pointer;
    color: var(--gray-700);
    width: 100%;
    text-align: left;
  }
  
  .doc-button:hover:not(:disabled) {
    background: var(--gray-100);
  }
  
  .doc-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  
  .disabled-note {
    color: var(--gray-500);
    font-style: italic;
  }
  
  .error-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
  }
  
  .error-message {
    text-align: center;
    max-width: 400px;
  }
  
  .error-message i {
    font-size: 48px;
    color: var(--danger-color);
    margin-bottom: 16px;
  }
  
  .error-message h3 {
    font-size: 20px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 8px;
  }
  
  .error-message p {
    color: var(--gray-600);
    margin-bottom: 24px;
  }
  
  .modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 50;
    display: flex;
    justify-content: center;
    align-items: center;
  }
  
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 50;
  }
  
  .modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 100%;
    z-index: 60;
    overflow: hidden;
  }
  
  .modal-md {
    max-width: 600px;
  }
  
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .modal-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .close-btn {
    background: none;
    border: none;
    color: var(--gray-600);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 4px;
  }
  
  .close-btn:hover {
    background: var(--gray-100);
    color: var(--gray-800);
  }
  
  .modal-body {
    padding: 24px;
  }
  
  .form-group {
    margin-bottom: 16px;
  }
  
  .form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--gray-700);
  }
  
  .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 14px;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
  }
  
  .required {
    color: #dc2626;
  }
  
  .fa-spin {
    animation: spin 1s linear infinite;
  }
  
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  
  @media print {
    .header-actions, .doc-button, .btn, .modal {
      display: none !important;
    }
  }
  </style>