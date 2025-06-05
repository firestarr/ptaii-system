<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <div class="modal-title">
          <i class="fas fa-building"></i>
          <div class="title-content">
            <h3>Vendor Options</h3>
            <div class="item-info" v-if="item">
              <span class="item-code">{{ item.item_code }}</span>
              <span class="item-name">{{ item.item_name }}</span>
              <span class="required-qty">{{ item.required_quantity }} {{ item.uom }}</span>
            </div>
          </div>
        </div>
        <button @click="closeModal" class="close-button">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- No Vendors State -->
        <div v-if="!vendors || vendors.length === 0" class="no-vendors">
          <div class="no-vendors-icon">
            <i class="fas fa-building-slash"></i>
          </div>
          <h4>No Vendors Available</h4>
          <p>No vendors found with pricing for this item. Consider creating an RFQ to get quotations.</p>
        </div>

        <!-- Vendors List -->
        <div v-else class="vendors-container">
          <!-- Filter and Sort Controls -->
          <div class="controls-bar">
            <div class="filter-controls">
              <select v-model="sortBy" class="sort-select">
                <option value="price">Sort by Price</option>
                <option value="rating">Sort by Rating</option>
                <option value="lead_time">Sort by Lead Time</option>
                <option value="total_cost">Sort by Total Cost</option>
              </select>
              
              <div class="filter-badges">
                <label class="filter-badge">
                  <input type="checkbox" v-model="showOnlyContract" />
                  <span>Contract Only</span>
                </label>
                <label class="filter-badge">
                  <input type="checkbox" v-model="showOnlyQuotation" />
                  <span>With Quotation</span>
                </label>
              </div>
            </div>
            
            <div class="view-toggle">
              <button 
                :class="['view-btn', { active: viewMode === 'grid' }]"
                @click="viewMode = 'grid'">
                <i class="fas fa-th"></i>
              </button>
              <button 
                :class="['view-btn', { active: viewMode === 'list' }]"
                @click="viewMode = 'list'">
                <i class="fas fa-list"></i>
              </button>
            </div>
          </div>

          <!-- Vendors Grid/List -->
          <div :class="['vendors-list', `view-${viewMode}`]">
            <div 
              v-for="(vendor, index) in filteredAndSortedVendors" 
              :key="vendor.vendor_id"
              :class="['vendor-card', { 
                'selected': isSelected(vendor.vendor_id),
                'best-option': index === 0 && sortBy === 'price'
              }]"
              @click="selectVendor(vendor)">
              
              <!-- Best Option Badge -->
              <div v-if="index === 0 && sortBy === 'price'" class="best-badge">
                <i class="fas fa-crown"></i>
                Best Price
              </div>
              
              <!-- Vendor Header -->
              <div class="vendor-header">
                <div class="vendor-basic">
                  <h4 class="vendor-name">{{ vendor.vendor_name }}</h4>
                  <div class="vendor-code">{{ vendor.vendor_code }}</div>
                </div>
                
                <div class="vendor-badges">
                  <span v-if="vendor.has_active_contract" class="badge contract">
                    <i class="fas fa-file-contract"></i>
                    Contract
                  </span>
                  <span v-if="vendor.has_valid_quotation" class="badge quotation">
                    <i class="fas fa-file-alt"></i>
                    Quotation
                  </span>
                </div>
              </div>

              <!-- Vendor Metrics -->
              <div class="vendor-metrics">
                <div class="metric-grid">
                  <div class="metric-item price">
                    <div class="metric-label">Unit Price</div>
                    <div class="metric-value">
                      {{ formatCurrency(vendor.unit_price) }}
                      <span class="currency">{{ vendor.currency }}</span>
                    </div>
                  </div>
                  
                  <div class="metric-item total">
                    <div class="metric-label">Total Cost</div>
                    <div class="metric-value">
                      {{ formatCurrency(vendor.total_cost) }}
                    </div>
                  </div>
                  
                  <div class="metric-item rating">
                    <div class="metric-label">Rating</div>
                    <div class="metric-value">
                      <div class="rating-display">
                        <span class="rating-number">{{ vendor.vendor_rating }}</span>
                        <div class="stars">
                          <i 
                            v-for="star in 5" 
                            :key="star"
                            :class="['fas fa-star', { filled: star <= Math.round(vendor.vendor_rating / 2) }]">
                          </i>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="metric-item lead-time">
                    <div class="metric-label">Lead Time</div>
                    <div class="metric-value">
                      {{ vendor.estimated_lead_time_days }} days
                    </div>
                  </div>
                </div>
              </div>

              <!-- Additional Details -->
              <div class="vendor-details">
                <div class="detail-row">
                  <span class="detail-label">
                    <i class="fas fa-credit-card"></i>
                    Payment Terms:
                  </span>
                  <span class="detail-value">{{ vendor.payment_terms }}</span>
                </div>
                
                <div class="detail-row">
                  <span class="detail-label">
                    <i class="fas fa-box"></i>
                    Min Quantity:
                  </span>
                  <span class="detail-value">{{ vendor.min_quantity }} {{ item.uom }}</span>
                </div>
                
                <div class="detail-row">
                  <span class="detail-label">
                    <i class="fas fa-chart-line"></i>
                    Performance:
                  </span>
                  <span class="detail-value">
                    {{ vendor.last_delivery_performance }}% delivery rate
                  </span>
                </div>
              </div>

              <!-- Selection Checkbox (for multi-select mode) -->
              <div v-if="multiSelect" class="selection-checkbox">
                <input 
                  type="checkbox" 
                  :checked="isSelected(vendor.vendor_id)"
                  @click.stop="toggleSelection(vendor)"
                  class="vendor-checkbox"
                />
              </div>
            </div>
          </div>

          <!-- Comparison Summary (for multi-select) -->
          <div v-if="multiSelect && selectedVendors.length > 1" class="comparison-summary">
            <h4>
              <i class="fas fa-balance-scale"></i>
              Comparison Summary ({{ selectedVendors.length }} vendors)
            </h4>
            
            <div class="comparison-grid">
              <div class="comparison-metric">
                <div class="metric-label">Price Range</div>
                <div class="metric-value">
                  {{ formatCurrency(getPriceRange().min) }} - {{ formatCurrency(getPriceRange().max) }}
                </div>
              </div>
              
              <div class="comparison-metric">
                <div class="metric-label">Avg Rating</div>
                <div class="metric-value">
                  {{ getAverageRating().toFixed(1) }}/10
                </div>
              </div>
              
              <div class="comparison-metric">
                <div class="metric-label">Lead Time Range</div>
                <div class="metric-value">
                  {{ getLeadTimeRange().min }} - {{ getLeadTimeRange().max }} days
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <div class="footer-info">
          <span v-if="vendors && vendors.length > 0" class="vendor-count">
            {{ filteredAndSortedVendors.length }} of {{ vendors.length }} vendors shown
          </span>
          <span v-if="selectedVendors.length > 0" class="selection-count">
            {{ selectedVendors.length }} selected
          </span>
        </div>
        
        <div class="footer-actions">
          <button @click="closeModal" class="btn-secondary">
            Cancel
          </button>
          
          <button 
            v-if="!multiSelect"
            @click="viewVendorDetails"
            :disabled="selectedVendors.length === 0"
            class="btn-outline">
            <i class="fas fa-eye"></i>
            View Details
          </button>
          
          <button 
            v-if="multiSelect"
            @click="compareSelected"
            :disabled="selectedVendors.length < 2"
            class="btn-outline">
            <i class="fas fa-balance-scale"></i>
            Compare Selected
          </button>
          
          <button 
            @click="selectAndClose"
            :disabled="selectedVendors.length === 0"
            class="btn-primary">
            <i class="fas fa-check"></i>
            {{ multiSelect ? `Select ${selectedVendors.length} Vendors` : 'Select Vendor' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'VendorOptionsModal',
  props: {
    item: {
      type: Object,
      required: true
    },
    vendors: {
      type: Array,
      default: () => []
    },
    multiSelect: {
      type: Boolean,
      default: false
    },
    preSelected: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      selectedVendorIds: [...this.preSelected],
      sortBy: 'price',
      viewMode: 'grid',
      showOnlyContract: false,
      showOnlyQuotation: false
    }
  },
  computed: {
    selectedVendors() {
      return this.vendors.filter(vendor => 
        this.selectedVendorIds.includes(vendor.vendor_id)
      )
    },
    
    filteredAndSortedVendors() {
      let filtered = [...this.vendors]
      
      // Apply filters
      if (this.showOnlyContract) {
        filtered = filtered.filter(vendor => vendor.has_active_contract)
      }
      
      if (this.showOnlyQuotation) {
        filtered = filtered.filter(vendor => vendor.has_valid_quotation)
      }
      
      // Apply sorting
      filtered.sort((a, b) => {
        switch (this.sortBy) {
          case 'price':
            return a.unit_price - b.unit_price
          case 'rating':
            return b.vendor_rating - a.vendor_rating
          case 'lead_time':
            return a.estimated_lead_time_days - b.estimated_lead_time_days
          case 'total_cost':
            return a.total_cost - b.total_cost
          default:
            return 0
        }
      })
      
      return filtered
    }
  },
  methods: {
    closeModal() {
      this.$emit('close')
    },
    
    selectVendor(vendor) {
      if (this.multiSelect) {
        this.toggleSelection(vendor)
      } else {
        this.selectedVendorIds = [vendor.vendor_id]
      }
    },
    
    toggleSelection(vendor) {
      const index = this.selectedVendorIds.indexOf(vendor.vendor_id)
      if (index >= 0) {
        this.selectedVendorIds.splice(index, 1)
      } else {
        this.selectedVendorIds.push(vendor.vendor_id)
      }
    },
    
    isSelected(vendorId) {
      return this.selectedVendorIds.includes(vendorId)
    },
    
    selectAndClose() {
      if (this.selectedVendors.length > 0) {
        const result = this.multiSelect ? this.selectedVendors : this.selectedVendors[0]
        this.$emit('select', {
          item: this.item,
          vendors: result,
          pr_line_id: this.item.pr_line_id
        })
        this.closeModal()
      }
    },
    
    viewVendorDetails() {
      if (this.selectedVendors.length > 0) {
        this.$emit('view-details', this.selectedVendors[0])
      }
    },
    
    compareSelected() {
      if (this.selectedVendors.length >= 2) {
        this.$emit('compare', this.selectedVendors)
      }
    },
    
    getPriceRange() {
      if (this.selectedVendors.length === 0) return { min: 0, max: 0 }
      
      const prices = this.selectedVendors.map(v => v.unit_price)
      return {
        min: Math.min(...prices),
        max: Math.max(...prices)
      }
    },
    
    getAverageRating() {
      if (this.selectedVendors.length === 0) return 0
      
      const totalRating = this.selectedVendors.reduce((sum, v) => sum + v.vendor_rating, 0)
      return totalRating / this.selectedVendors.length
    },
    
    getLeadTimeRange() {
      if (this.selectedVendors.length === 0) return { min: 0, max: 0 }
      
      const leadTimes = this.selectedVendors.map(v => v.estimated_lead_time_days)
      return {
        min: Math.min(...leadTimes),
        max: Math.max(...leadTimes)
      }
    },
    
    formatCurrency(amount) {
      if (!amount) return '$0.00'
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    }
  },
  watch: {
    preSelected: {
      handler(newVal) {
        this.selectedVendorIds = [...newVal]
      },
      immediate: true
    }
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-container {
  background: white;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 1000px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Header */
.modal-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.modal-title {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.modal-title i {
  font-size: 1.5rem;
  margin-top: 0.25rem;
}

.title-content h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.5rem;
  font-weight: 600;
}

.item-info {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  opacity: 0.9;
}

.item-info span {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.25rem 0.75rem;
  border-radius: 15px;
  font-size: 0.9rem;
}

.close-button {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.close-button:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.1);
}

/* Body */
.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 0;
}

/* No Vendors State */
.no-vendors {
  text-align: center;
  padding: 4rem 2rem;
}

.no-vendors-icon {
  font-size: 4rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.no-vendors h4 {
  margin: 0 0 1rem 0;
  color: #374151;
}

.no-vendors p {
  color: #6b7280;
  margin: 0;
}

/* Controls */
.controls-bar {
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.filter-controls {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.sort-select {
  padding: 0.5rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: white;
  font-size: 0.9rem;
}

.filter-badges {
  display: flex;
  gap: 1rem;
}

.filter-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #6b7280;
  cursor: pointer;
}

.filter-badge input[type="checkbox"] {
  margin: 0;
}

.view-toggle {
  display: flex;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #d1d5db;
}

.view-btn {
  background: white;
  border: none;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.2s;
}

.view-btn.active {
  background: #3b82f6;
  color: white;
}

/* Vendors List */
.vendors-container {
  padding: 2rem;
}

.vendors-list.view-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.vendors-list.view-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.vendor-card {
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  background: white;
}

.vendor-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
  transform: translateY(-2px);
}

.vendor-card.selected {
  border-color: #3b82f6;
  background: #eff6ff;
}

.vendor-card.best-option {
  border-color: #f59e0b;
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.best-badge {
  position: absolute;
  top: -0.5rem;
  right: 1rem;
  background: #f59e0b;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 15px;
  font-size: 0.8rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Vendor Header */
.vendor-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.vendor-name {
  margin: 0 0 0.25rem 0;
  color: #374151;
  font-size: 1.1rem;
  font-weight: 600;
}

.vendor-code {
  color: #6b7280;
  font-size: 0.9rem;
}

.vendor-badges {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.badge {
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.badge.contract {
  background: #d1fae5;
  color: #065f46;
}

.badge.quotation {
  background: #dbeafe;
  color: #1e40af;
}

/* Vendor Metrics */
.vendor-metrics {
  margin-bottom: 1rem;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.metric-item {
  text-align: center;
}

.metric-label {
  font-size: 0.8rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.metric-value {
  font-weight: 600;
  color: #374151;
}

.metric-item.price .metric-value {
  color: #059669;
  font-size: 1.1rem;
}

.currency {
  font-size: 0.8rem;
  color: #6b7280;
  font-weight: normal;
}

.rating-display {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.rating-number {
  font-weight: 600;
}

.stars {
  display: flex;
  gap: 0.1rem;
}

.stars i {
  font-size: 0.8rem;
  color: #d1d5db;
}

.stars i.filled {
  color: #fbbf24;
}

/* Vendor Details */
.vendor-details {
  border-top: 1px solid #f3f4f6;
  padding-top: 1rem;
  font-size: 0.9rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.detail-label {
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.detail-value {
  color: #374151;
  font-weight: 500;
}

/* Selection Checkbox */
.selection-checkbox {
  position: absolute;
  top: 1rem;
  left: 1rem;
}

.vendor-checkbox {
  width: 1.2rem;
  height: 1.2rem;
  cursor: pointer;
}

/* Comparison Summary */
.comparison-summary {
  background: #f8fafc;
  border-radius: 12px;
  padding: 1.5rem;
  margin-top: 2rem;
}

.comparison-summary h4 {
  margin: 0 0 1rem 0;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.comparison-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.comparison-metric {
  text-align: center;
  background: white;
  padding: 1rem;
  border-radius: 8px;
}

.comparison-metric .metric-label {
  font-size: 0.9rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.comparison-metric .metric-value {
  font-weight: 600;
  color: #374151;
  font-size: 1.1rem;
}

/* Footer */
.modal-footer {
  background: #f8fafc;
  border-top: 1px solid #e5e7eb;
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.footer-info {
  display: flex;
  gap: 1rem;
  font-size: 0.9rem;
  color: #6b7280;
}

.selection-count {
  color: #3b82f6;
  font-weight: 500;
}

.footer-actions {
  display: flex;
  gap: 1rem;
}

.btn-secondary,
.btn-outline,
.btn-primary {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.btn-secondary {
  background: #6b7280;
  color: white;
  border: none;
}

.btn-outline {
  background: transparent;
  color: #3b82f6;
  border: 1px solid #3b82f6;
}

.btn-primary {
  background: #3b82f6;
  color: white;
  border: none;
}

.btn-secondary:hover,
.btn-outline:hover,
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-outline:hover {
  background: #3b82f6;
  color: white;
}

.btn-secondary:disabled,
.btn-outline:disabled,
.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* Responsive Design */
@media (max-width: 768px) {
  .modal-overlay {
    padding: 0.5rem;
  }
  
  .modal-header {
    padding: 1rem;
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .item-info {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .controls-bar {
    padding: 1rem;
    flex-direction: column;
    align-items: stretch;
  }
  
  .vendors-container {
    padding: 1rem;
  }
  
  .vendors-list.view-grid {
    grid-template-columns: 1fr;
  }
  
  .vendor-card {
    padding: 1rem;
  }
  
  .metric-grid {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
  
  .modal-footer {
    padding: 1rem;
    flex-direction: column;
    align-items: stretch;
  }
  
  .footer-actions {
    flex-direction: column;
  }
  
  .comparison-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .vendor-header {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .vendor-badges {
    flex-direction: row;
    flex-wrap: wrap;
  }
  
  .detail-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }
}
</style>