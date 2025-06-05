<template>
  <div class="procurement-path-analysis">
    <!-- Header Section -->
    <div class="analysis-header">
      <div class="header-content">
        <div class="pr-info">
          <h1 class="page-title">
            <i class="fas fa-route"></i>
            Procurement Path Analysis
          </h1>
          <div class="pr-details" v-if="pr.pr_number">
            <span class="pr-number">{{ pr.pr_number }}</span>
            <span class="pr-date">{{ formatDate(pr.pr_date) }}</span>
            <span class="pr-requester">{{ pr.requester?.name }}</span>
          </div>
        </div>
        
        <div class="header-actions">
          <button @click="refreshAnalysis" :disabled="loading" class="btn-refresh">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh Analysis
          </button>
          <router-link 
            :to="{ name: 'PRVendorRecommendations', params: { id: $route.params.id } }"
            class="btn-secondary">
            <i class="fas fa-users"></i>
            View Vendor Recommendations
          </router-link>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Analyzing procurement paths...</p>
    </div>

    <!-- Analysis Results -->
    <div v-else-if="analysis.length > 0" class="analysis-results">
      
      <!-- Overall Recommendation Card -->
      <div class="recommendation-card">
        <div class="card-header">
          <h2>
            <i class="fas fa-lightbulb"></i>
            Overall Recommendation
          </h2>
          <div class="recommendation-badge" :class="`badge-${overallRecommendation}`">
            {{ getRecommendationLabel(overallRecommendation) }}
          </div>
        </div>
        
        <div class="card-content">
          <div class="summary-stats">
            <div class="stat-item">
              <div class="stat-number">{{ summary.total_items }}</div>
              <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-item success">
              <div class="stat-number">{{ summary.direct_po_possible }}</div>
              <div class="stat-label">Direct PO Ready</div>
            </div>
            <div class="stat-item warning">
              <div class="stat-number">{{ summary.rfq_required }}</div>
              <div class="stat-label">RFQ Required</div>
            </div>
          </div>
          
          <div class="recommendation-actions">
            <button 
              v-if="summary.direct_po_possible === summary.total_items"
              @click="proceedWithDirectPO"
              class="btn-primary action-btn">
              <i class="fas fa-shopping-cart"></i>
              Create Direct Purchase Orders
            </button>
            
            <button 
              v-if="summary.direct_po_possible > 0 && summary.rfq_required > 0"
              @click="proceedWithMixed"
              class="btn-secondary action-btn">
              <i class="fas fa-tasks"></i>
              Handle Mixed Approach
            </button>
            
            <button 
              v-if="summary.rfq_required > 0"
              @click="proceedWithRFQ"
              class="btn-warning action-btn">
              <i class="fas fa-paper-plane"></i>
              Create RFQ for {{ summary.rfq_required }} Items
            </button>
          </div>
        </div>
      </div>

      <!-- Items Analysis Table -->
      <div class="analysis-table-container">
        <div class="table-header">
          <h3>
            <i class="fas fa-list"></i>
            Items Analysis
          </h3>
          <div class="table-filters">
            <select v-model="filterPath" class="filter-select">
              <option value="">All Paths</option>
              <option value="direct_po">Direct PO</option>
              <option value="rfq_required">RFQ Required</option>
            </select>
          </div>
        </div>
        
        <div class="table-wrapper">
          <table class="analysis-table">
            <thead>
              <tr>
                <th class="item-column">Item</th>
                <th class="path-column">Recommended Path</th>
                <th class="reasons-column">Analysis Reasons</th>
                <th class="vendors-column">Vendor Options</th>
                <th class="actions-column">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in filteredAnalysis" :key="item.item_id" 
                  :class="{ 'row-success': item.can_create_po_directly, 'row-warning': !item.can_create_po_directly }">
                
                <!-- Item Info -->
                <td class="item-cell">
                  <div class="item-info">
                    <div class="item-code">{{ item.item_code }}</div>
                    <div class="item-name">{{ item.item_name }}</div>
                  </div>
                </td>
                
                <!-- Recommended Path -->
                <td class="path-cell">
                  <div class="path-badge" :class="`path-${item.recommended_path}`">
                    <i :class="getPathIcon(item.recommended_path)"></i>
                    {{ getPathLabel(item.recommended_path) }}
                  </div>
                </td>
                
                <!-- Reasons -->
                <td class="reasons-cell">
                  <div class="reasons-list">
                    <div v-for="reason in item.reasons" :key="reason" class="reason-item">
                      <i class="fas fa-check-circle" v-if="item.can_create_po_directly"></i>
                      <i class="fas fa-exclamation-triangle" v-else></i>
                      {{ reason }}
                    </div>
                  </div>
                </td>
                
                <!-- Vendor Options -->
                <td class="vendors-cell">
                  <div class="vendor-options">
                    <div class="vendor-count">
                      <i class="fas fa-building"></i>
                      {{ item.vendor_options_count }} vendors available
                    </div>
                    <div v-if="item.best_vendor" class="best-vendor">
                      <strong>Best:</strong> {{ item.best_vendor.vendor_name }}
                      <span class="vendor-price">
                        {{ formatCurrency(item.best_vendor.unit_price) }}
                      </span>
                    </div>
                  </div>
                </td>
                
                <!-- Actions -->
                <td class="actions-cell">
                  <div class="action-buttons">
                    <button 
                      v-if="item.can_create_po_directly"
                      @click="createDirectPOForItem(item)"
                      class="btn-small btn-success"
                      title="Create PO directly">
                      <i class="fas fa-shopping-cart"></i>
                    </button>
                    
                    <button 
                      @click="viewVendorOptions(item)"
                      class="btn-small btn-info"
                      title="View vendor options">
                      <i class="fas fa-eye"></i>
                    </button>
                    
                    <button 
                      v-if="!item.can_create_po_directly"
                      @click="createRFQForItem(item)"
                      class="btn-small btn-warning"
                      title="Create RFQ">
                      <i class="fas fa-paper-plane"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Detailed Workflow Guide -->
      <div class="workflow-guide">
        <h3>
          <i class="fas fa-map"></i>
          Recommended Workflow
        </h3>
        
        <div class="workflow-steps">
          <div class="workflow-step" 
               :class="{ 'step-active': currentWorkflowStep === 1 }">
            <div class="step-number">1</div>
            <div class="step-content">
              <h4>Items Ready for Direct PO</h4>
              <p v-if="summary.direct_po_possible > 0">
                {{ summary.direct_po_possible }} items have established pricing and can proceed directly to PO creation.
              </p>
              <p v-else class="text-muted">
                No items are ready for direct PO creation.
              </p>
              <button 
                v-if="summary.direct_po_possible > 0"
                @click="handleDirectPOItems"
                class="btn-outline">
                Process {{ summary.direct_po_possible }} Items
              </button>
            </div>
          </div>
          
          <div class="workflow-step" 
               :class="{ 'step-active': currentWorkflowStep === 2 }">
            <div class="step-number">2</div>
            <div class="step-content">
              <h4>Items Requiring RFQ</h4>
              <p v-if="summary.rfq_required > 0">
                {{ summary.rfq_required }} items need competitive quotations before PO creation.
              </p>
              <p v-else class="text-muted">
                No items require RFQ process.
              </p>
              <button 
                v-if="summary.rfq_required > 0"
                @click="handleRFQItems"
                class="btn-outline">
                Create RFQ for {{ summary.rfq_required }} Items
              </button>
            </div>
          </div>
          
          <div class="workflow-step" 
               :class="{ 'step-active': currentWorkflowStep === 3 }">
            <div class="step-number">3</div>
            <div class="step-content">
              <h4>Complete Procurement</h4>
              <p>After all items are processed, the PR will be fully converted to POs.</p>
              <button 
                @click="viewPRStatus"
                class="btn-outline">
                Check PR Status
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading" class="empty-state">
      <div class="empty-icon">
        <i class="fas fa-clipboard-list"></i>
      </div>
      <h3>No Analysis Available</h3>
      <p>Unable to analyze procurement paths for this PR. Please ensure the PR is approved and has valid items.</p>
      <button @click="refreshAnalysis" class="btn-primary">
        Try Again
      </button>
    </div>

    <!-- Vendor Options Modal -->
    <div v-if="showVendorModal" class="modal-overlay" @click="closeVendorModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h4>Vendor Options - {{ selectedItem?.item_name }}</h4>
          <button @click="closeVendorModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="selectedItem?.best_vendor" class="vendor-details">
            <h5>Recommended Vendor</h5>
            <div class="vendor-card">
              <div class="vendor-name">{{ selectedItem.best_vendor.vendor_name }}</div>
              <div class="vendor-metrics">
                <span>Price: {{ formatCurrency(selectedItem.best_vendor.unit_price) }}</span>
                <span>Rating: ⭐ {{ selectedItem.best_vendor.vendor_rating }}/10</span>
                <span>Lead Time: {{ selectedItem.best_vendor.estimated_lead_time_days }} days</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="viewAllVendors" class="btn-primary">
            View All {{ selectedItem?.vendor_options_count }} Vendors
          </button>
          <button @click="closeVendorModal" class="btn-secondary">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ProcurementPathAnalysis',
  props: {
    id: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      loading: false,
      pr: {},
      analysis: [],
      summary: {
        total_items: 0,
        direct_po_possible: 0,
        rfq_required: 0
      },
      overallRecommendation: 'mixed',
      filterPath: '',
      currentWorkflowStep: 1,
      showVendorModal: false,
      selectedItem: null
    }
  },
  computed: {
    filteredAnalysis() {
      if (!this.filterPath) return this.analysis
      
      return this.analysis.filter(item => {
        if (this.filterPath === 'direct_po') {
          return item.can_create_po_directly
        } else if (this.filterPath === 'rfq_required') {
          return !item.can_create_po_directly
        }
        return true
      })
    }
  },
  mounted() {
    this.fetchAnalysis()
  },
  methods: {
    async fetchAnalysis() {
      this.loading = true
      try {
        const response = await axios.get(`/purchase-requisitions/${this.id}/procurement-path`)
        
        this.pr = response.data.data.pr
        this.analysis = response.data.data.items_analysis
        this.summary = response.data.data.summary
        this.overallRecommendation = response.data.data.overall_recommendation
        
        // Set initial workflow step based on analysis
        if (this.summary.direct_po_possible > 0) {
          this.currentWorkflowStep = 1
        } else if (this.summary.rfq_required > 0) {
          this.currentWorkflowStep = 2
        }
        
      } catch (error) {
        console.error('Error fetching procurement path analysis:', error)
        this.$toast.error('Failed to fetch procurement analysis')
      } finally {
        this.loading = false
      }
    },

    refreshAnalysis() {
      this.fetchAnalysis()
    },

    getRecommendationLabel(recommendation) {
      const labels = {
        'direct_po': 'Direct PO Recommended',
        'mixed': 'Mixed Approach Required',
        'rfq_required': 'RFQ Process Required'
      }
      return labels[recommendation] || 'Analysis Required'
    },

    getPathLabel(path) {
      const labels = {
        'direct_po': 'Direct PO',
        'rfq_required': 'RFQ Required'
      }
      return labels[path] || 'Unknown'
    },

    getPathIcon(path) {
      const icons = {
        'direct_po': 'fas fa-shopping-cart',
        'rfq_required': 'fas fa-paper-plane'
      }
      return icons[path] || 'fas fa-question'
    },

    proceedWithDirectPO() {
      // Navigate to multi-vendor PO creation
      this.$router.push({
        name: 'CreateMultiVendorPO',
        params: { id: this.id }
      })
    },

    proceedWithMixed() {
      // Navigate to vendor recommendations for mixed approach
      this.$router.push({
        name: 'PRVendorRecommendations',
        params: { id: this.id }
      })
    },

    proceedWithRFQ() {
      // Navigate to multi-vendor RFQ creation
      this.$router.push({
        name: 'CreateMultiVendorRFQ',
        params: { id: this.id }
      })
    },

    handleDirectPOItems() {
      this.currentWorkflowStep = 1
      this.proceedWithDirectPO()
    },

    handleRFQItems() {
      this.currentWorkflowStep = 2
      this.proceedWithRFQ()
    },

    viewPRStatus() {
      this.$router.push({
        name: 'PurchaseRequisitionDetail',
        params: { id: this.id }
      })
    },

    createDirectPOForItem(item) {
      // Navigate to PO creation with specific item
      this.$router.push({
        name: 'CreatePOFromPR',
        params: { prId: this.id },
        query: { 
          item_id: item.item_id,
          vendor_id: item.best_vendor?.vendor_id 
        }
      })
    },

    viewVendorOptions(item) {
      this.selectedItem = item
      this.showVendorModal = true
    },

    createRFQForItem(item) {
      // Navigate to RFQ creation for specific item
      this.$router.push({
        name: 'CreateMultiVendorRFQ',
        params: { id: this.id },
        query: { item_id: item.item_id }
      })
    },

    viewAllVendors() {
      this.closeVendorModal()
      this.$router.push({
        name: 'PRVendorRecommendations',
        params: { id: this.id },
        query: { item_id: this.selectedItem?.item_id }
      })
    },

    closeVendorModal() {
      this.showVendorModal = false
      this.selectedItem = null
    },

    formatDate(dateString) {
      if (!dateString) return ''
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },

    formatCurrency(amount) {
      if (!amount) return ''
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    }
  }
}
</script>

<style scoped>
.procurement-path-analysis {
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

/* Header Section */
.analysis-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 600;
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pr-details {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.pr-details span {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.9rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn-refresh, .btn-secondary {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-refresh {
  background: rgba(255, 255, 255, 0.2);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-secondary {
  background: white;
  color: #667eea;
}

.btn-refresh:hover, .btn-secondary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Loading State */
.loading-container {
  text-align: center;
  padding: 4rem 2rem;
}

.loading-spinner {
  width: 3rem;
  height: 3rem;
  border: 3px solid #f3f4f6;
  border-top: 3px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Recommendation Card */
.recommendation-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.card-header {
  background: #f8fafc;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h2 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #374151;
}

.recommendation-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.9rem;
}

.badge-direct_po {
  background: #d1fae5;
  color: #065f46;
}

.badge-mixed {
  background: #fef3c7;
  color: #92400e;
}

.badge-rfq_required {
  background: #fee2e2;
  color: #991b1b;
}

.card-content {
  padding: 1.5rem;
}

.summary-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-item {
  text-align: center;
  padding: 1rem;
  border-radius: 8px;
  background: #f9fafb;
}

.stat-item.success {
  background: #ecfdf5;
}

.stat-item.warning {
  background: #fffbeb;
}

.stat-number {
  font-size: 2rem;
  font-weight: 700;
  color: #374151;
}

.stat-item.success .stat-number {
  color: #059669;
}

.stat-item.warning .stat-number {
  color: #d97706;
}

.stat-label {
  font-size: 0.9rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.recommendation-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.action-btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Analysis Table */
.analysis-table-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.table-header {
  background: #f8fafc;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h3 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #374151;
}

.filter-select {
  padding: 0.5rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: white;
}

.table-wrapper {
  overflow-x: auto;
}

.analysis-table {
  width: 100%;
  border-collapse: collapse;
}

.analysis-table th {
  background: #f9fafb;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #e5e7eb;
}

.analysis-table td {
  padding: 1rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: top;
}

.row-success {
  background: rgba(236, 253, 245, 0.5);
}

.row-warning {
  background: rgba(255, 251, 235, 0.5);
}

.item-info {
  min-width: 200px;
}

.item-code {
  font-weight: 600;
  color: #374151;
}

.item-name {
  font-size: 0.9rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.path-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-weight: 500;
  font-size: 0.9rem;
}

.path-direct_po {
  background: #d1fae5;
  color: #065f46;
}

.path-rfq_required {
  background: #fef3c7;
  color: #92400e;
}

.reasons-list {
  min-width: 250px;
}

.reason-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.25rem 0;
  font-size: 0.9rem;
}

.reason-item i {
  font-size: 0.8rem;
}

.fa-check-circle {
  color: #059669;
}

.fa-exclamation-triangle {
  color: #d97706;
}

.vendor-options {
  min-width: 200px;
}

.vendor-count {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.best-vendor {
  font-size: 0.9rem;
}

.vendor-price {
  color: #059669;
  font-weight: 600;
  margin-left: 0.5rem;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-small {
  padding: 0.5rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  transition: all 0.2s;
}

.btn-success {
  background: #10b981;
  color: white;
}

.btn-info {
  background: #3b82f6;
  color: white;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-small:hover {
  transform: scale(1.1);
}

/* Workflow Guide */
.workflow-guide {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 2rem;
  margin-bottom: 2rem;
}

.workflow-guide h3 {
  margin: 0 0 2rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #374151;
}

.workflow-steps {
  display: grid;
  gap: 1.5rem;
}

.workflow-step {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 8px;
  border: 2px solid #f3f4f6;
  transition: all 0.2s;
}

.workflow-step.step-active {
  border-color: #3b82f6;
  background: #eff6ff;
}

.step-number {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  flex-shrink: 0;
}

.step-active .step-number {
  background: #3b82f6;
  color: white;
}

.step-content h4 {
  margin: 0 0 0.5rem 0;
  color: #374151;
}

.step-content p {
  margin: 0 0 1rem 0;
  color: #6b7280;
}

.text-muted {
  color: #9ca3af !important;
}

.btn-outline {
  padding: 0.5rem 1rem;
  border: 1px solid #3b82f6;
  background: transparent;
  color: #3b82f6;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-outline:hover {
  background: #3b82f6;
  color: white;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.empty-icon {
  font-size: 4rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.empty-state h3 {
  margin: 0 0 1rem 0;
  color: #374151;
}

.empty-state p {
  color: #6b7280;
  margin: 0 0 2rem 0;
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
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h4 {
  margin: 0;
  color: #374151;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: #6b7280;
}

.modal-body {
  padding: 1.5rem;
}

.vendor-card {
  background: #f9fafb;
  border-radius: 8px;
  padding: 1rem;
}

.vendor-name {
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.vendor-metrics {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  font-size: 0.9rem;
  color: #6b7280;
}

.modal-footer {
  padding: 1.5rem;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Responsive Design */
@media (max-width: 768px) {
  .procurement-path-analysis {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-actions {
    justify-content: stretch;
  }
  
  .summary-stats {
    grid-template-columns: 1fr;
  }
  
  .recommendation-actions {
    flex-direction: column;
  }
  
  .table-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .analysis-table {
    font-size: 0.9rem;
  }
  
  .analysis-table th,
  .analysis-table td {
    padding: 0.75rem 0.5rem;
  }
  
  .workflow-step {
    flex-direction: column;
    gap: 1rem;
  }
  
  .modal-content {
    margin: 1rem;
    width: calc(100% - 2rem);
  }
}
</style>