<template>
  <div class="multi-vendor-wizard">
    <!-- Wizard Steps -->
    <div class="wizard-steps">
      <div :class="['step', { active: currentStep === 1 }]">
        <span class="step-number">1</span>
        <span class="step-title">Vendor Selection</span>
      </div>
      <div :class="['step', { active: currentStep === 2 }]">
        <span class="step-number">2</span>
        <span class="step-title">Quantity Split</span>
      </div>
      <div :class="['step', { active: currentStep === 3 }]">
        <span class="step-number">3</span>
        <span class="step-title">Review & Create</span>
      </div>
    </div>

    <!-- Step 1: Vendor Selection -->
    <div v-if="currentStep === 1" class="step-content">
      <h3>Select Vendors for Each Item</h3>
      <div class="vendor-selection-grid">
        <div v-for="item in prItems" :key="item.pr_line_id" class="item-card">
          <div class="item-header">
            <h4>{{ item.item_code }} - {{ item.item_name }}</h4>
            <span class="quantity">{{ item.required_quantity }} {{ item.uom }}</span>
          </div>
          
          <div class="vendor-options">
            <div v-for="vendor in item.available_vendors" 
                 :key="vendor.vendor_id"
                 :class="['vendor-option', { selected: isVendorSelected(item.pr_line_id, vendor.vendor_id) }]"
                 @click="toggleVendorSelection(item.pr_line_id, vendor)">
              
              <div class="vendor-info">
                <strong>{{ vendor.vendor_name }}</strong>
                <div class="vendor-details">
                  <span>{{ formatCurrency(vendor.unit_price) }} / {{ item.uom }}</span>
                  <span>{{ vendor.estimated_lead_time_days }} days</span>
                  <span>⭐ {{ vendor.vendor_rating }}/10</span>
                </div>
              </div>
              
              <div v-if="vendor.has_active_contract" class="badge contract">Contract</div>
              <div v-if="vendor.has_valid_quotation" class="badge quotation">Quotation</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 2: Quantity Split -->
    <div v-if="currentStep === 2" class="step-content">
      <h3>Split Quantities Between Selected Vendors</h3>
      <div class="quantity-split-table">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Total Required</th>
              <th>Vendor Splits</th>
              <th>Remaining</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in prItems" :key="item.pr_line_id">
              <td>
                <div>
                  <strong>{{ item.item_code }}</strong>
                  <div>{{ item.item_name }}</div>
                </div>
              </td>
              <td>{{ item.required_quantity }} {{ item.uom }}</td>
              <td>
                <div class="vendor-splits">
                  <div v-for="selection in getItemSelections(item.pr_line_id)" 
                       :key="selection.vendor_id" 
                       class="vendor-split">
                    <span class="vendor-name">{{ selection.vendor_name }}</span>
                    <input 
                      type="number" 
                      v-model.number="selection.quantity"
                      @input="updateQuantitySplit"
                      :max="item.required_quantity"
                      min="0"
                      step="0.01"
                      class="quantity-input"
                    />
                    <span class="unit-price">@ {{ formatCurrency(selection.unit_price) }}</span>
                    <button @click="removeVendorSplit(item.pr_line_id, selection.vendor_id)" class="btn-remove">×</button>
                  </div>
                </div>
              </td>
              <td :class="{ error: getRemainingQuantity(item.pr_line_id) !== 0 }">
                {{ getRemainingQuantity(item.pr_line_id) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Step 3: Review & Create -->
    <div v-if="currentStep === 3" class="step-content">
      <h3>Review Purchase Orders</h3>
      <div class="po-preview">
        <div v-for="(poData, vendorId) in groupedSelections" :key="vendorId" class="po-card">
          <div class="po-header">
            <h4>PO for {{ getVendorName(vendorId) }}</h4>
            <div class="po-totals">
              <span>{{ poData.items.length }} items</span>
              <span>Total: {{ formatCurrency(poData.total) }}</span>
            </div>
          </div>
          
          <div class="po-lines">
            <table>
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Quantity</th>
                  <th>Unit Price</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in poData.items" :key="line.pr_line_id + '_' + line.vendor_id">
                  <td>{{ line.item_code }} - {{ line.item_name }}</td>
                  <td>{{ line.quantity }}</td>
                  <td>{{ formatCurrency(line.unit_price) }}</td>
                  <td>{{ formatCurrency(line.quantity * line.unit_price) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <div class="wizard-navigation">
      <button @click="previousStep" :disabled="currentStep === 1" class="btn-outline">
        Previous
      </button>
      <button v-if="currentStep < 3" @click="nextStep" :disabled="!canProceed" class="btn-primary">
        Next
      </button>
      <button v-else @click="createPOs" :disabled="!canCreate" class="btn-success">
        Create {{ Object.keys(groupedSelections).length }} Purchase Orders
      </button>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'MultiVendorPOWizard',
  props: {
    id: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      currentStep: 1,
      pr: {},
      prItems: [],
      vendorSelections: {}, // { pr_line_id: [vendor_objects] }
      quantitySplits: {}, // { pr_line_id: { vendor_id: { quantity, unit_price } } }
      loading: false
    }
  },
  computed: {
    canProceed() {
      if (this.currentStep === 1) {
        // Check if all items have at least one vendor selected
        return this.prItems.every(item => 
          this.vendorSelections[item.pr_line_id] && 
          this.vendorSelections[item.pr_line_id].length > 0
        )
      }
      if (this.currentStep === 2) {
        // Check if all quantities are properly split
        return this.prItems.every(item => 
          this.getRemainingQuantity(item.pr_line_id) === 0
        )
      }
      return true
    },
    
    canCreate() {
      return this.canProceed && Object.keys(this.groupedSelections).length > 0
    },
    
    groupedSelections() {
      const grouped = {}
      
      this.prItems.forEach(item => {
        const splits = this.quantitySplits[item.pr_line_id] || {}
        
        Object.entries(splits).forEach(([vendorId, splitData]) => {
          if (splitData.quantity > 0) {
            if (!grouped[vendorId]) {
              grouped[vendorId] = {
                vendor_id: vendorId,
                vendor_name: splitData.vendor_name,
                items: [],
                total: 0
              }
            }
            
            const lineTotal = splitData.quantity * splitData.unit_price
            grouped[vendorId].items.push({
              pr_line_id: item.pr_line_id,
              item_code: item.item_code,
              item_name: item.item_name,
              quantity: splitData.quantity,
              unit_price: splitData.unit_price,
              vendor_id: vendorId
            })
            grouped[vendorId].total += lineTotal
          }
        })
      })
      
      return grouped
    }
  },
  mounted() {
    this.fetchPRData()
  },
  methods: {
    async fetchPRData() {
      this.loading = true
      try {
        const response = await axios.get(`/purchase-requisitions/${this.id}/vendor-recommendations`)
        if (response.data && response.data.data) {
          this.pr = response.data.data.pr || {}
          this.prItems = response.data.data.recommendations || []
          
          // Initialize selections
        this.prItems.forEach(item => {
          this.vendorSelections = { ...this.vendorSelections, [item.pr_line_id]: [] }
          this.quantitySplits = { ...this.quantitySplits, [item.pr_line_id]: {} }
        })
        } else {
          this.pr = {}
          this.prItems = []
          this.$toast.error('Invalid response data from server')
        }
      } catch (error) {
        console.error('Error fetching PR data:', error)
        this.$toast.error('Failed to fetch PR data')
      } finally {
        this.loading = false
      }
    },

    toggleVendorSelection(prLineId, vendor) {
      const selections = this.vendorSelections[prLineId] || []
      const index = selections.findIndex(v => v.vendor_id === vendor.vendor_id)
      
      if (index >= 0) {
        // Remove vendor
        selections.splice(index, 1)
        this.$delete(this.quantitySplits[prLineId], vendor.vendor_id)
      } else {
        // Add vendor
        selections.push(vendor)
        
        // Initialize quantity split
        const item = this.prItems.find(i => i.pr_line_id === prLineId)
        const remainingQty = this.getRemainingQuantity(prLineId)
        const defaultQty = Math.min(remainingQty, item.required_quantity)
        
        this.quantitySplits = {
          ...this.quantitySplits,
          [prLineId]: {
            ...this.quantitySplits[prLineId],
            [vendor.vendor_id]: {
              quantity: defaultQty,
              unit_price: vendor.unit_price,
              vendor_name: vendor.vendor_name
            }
          }
        }
      }
      
      this.updateQuantitySplit()
    },

    isVendorSelected(prLineId, vendorId) {
      const selections = this.vendorSelections[prLineId] || []
      return selections.some(v => v.vendor_id === vendorId)
    },

    getItemSelections(prLineId) {
      const selections = this.vendorSelections[prLineId] || []
      return selections.map(vendor => ({
        vendor_id: vendor.vendor_id,
        vendor_name: vendor.vendor_name,
        unit_price: vendor.unit_price,
        quantity: this.quantitySplits[prLineId][vendor.vendor_id]?.quantity || 0
      }))
    },

    getRemainingQuantity(prLineId) {
      const item = this.prItems.find(i => i.pr_line_id === prLineId)
      if (!item) return 0
      
      const splits = this.quantitySplits[prLineId] || {}
      const totalSplit = Object.values(splits).reduce((sum, split) => sum + (split.quantity || 0), 0)
      
      return item.required_quantity - totalSplit
    },

    updateQuantitySplit() {
      // Trigger reactivity
      this.$forceUpdate()
    },

    removeVendorSplit(prLineId, vendorId) {
      const selections = this.vendorSelections[prLineId] || []
      const index = selections.findIndex(v => v.vendor_id === vendorId)
      
      if (index >= 0) {
        selections.splice(index, 1)
        this.$delete(this.quantitySplits[prLineId], vendorId)
      }
    },

    getVendorName(vendorId) {
      // Find vendor name from selections
      for (const prLineId in this.quantitySplits) {
        const splits = this.quantitySplits[prLineId]
        if (splits[vendorId]) {
          return splits[vendorId].vendor_name
        }
      }
      return 'Unknown Vendor'
    },

    nextStep() {
      if (this.canProceed && this.currentStep < 3) {
        this.currentStep++
        
        // Auto-initialize quantity splits when moving to step 2
        if (this.currentStep === 2) {
          this.initializeQuantitySplits()
        }
      }
    },

    previousStep() {
      if (this.currentStep > 1) {
        this.currentStep--
      }
    },

    initializeQuantitySplits() {
      this.prItems.forEach(item => {
        const selections = this.vendorSelections[item.pr_line_id] || []
        
        if (selections.length === 1) {
          // Single vendor - assign full quantity
          const vendor = selections[0]
          this.$set(this.quantitySplits[item.pr_line_id], vendor.vendor_id, {
            quantity: item.required_quantity,
            unit_price: vendor.unit_price,
            vendor_name: vendor.vendor_name
          })
        } else if (selections.length > 1) {
          // Multiple vendors - split equally
          const qtyPerVendor = item.required_quantity / selections.length
          
          selections.forEach(vendor => {
            this.$set(this.quantitySplits[item.pr_line_id], vendor.vendor_id, {
              quantity: qtyPerVendor,
              unit_price: vendor.unit_price,
              vendor_name: vendor.vendor_name
            })
          })
        }
      })
    },

    async createPOs() {
      this.loading = true
      try {
        // Prepare vendor selections for API
        const vendorSelections = []
        
        Object.entries(this.groupedSelections).forEach(([vendorId, poData]) => {
          poData.items.forEach(item => {
            vendorSelections.push({
              pr_line_id: item.pr_line_id,
              vendor_id: parseInt(vendorId),
              quantity: item.quantity,
              unit_price: item.unit_price
            })
          })
        })
        
        const response = await axios.post('/purchase-orders/create-split-from-pr', {
          pr_id: parseInt(this.id),
          vendor_selections: vendorSelections
        })
        
        this.$toast.success(`${response.data.data.length} Purchase Orders created successfully!`)
        
        // Navigate to PO list or show success page
        this.$router.push({
          name: 'PurchaseOrders',
          query: { created_from_pr: this.id }
        })
        
      } catch (error) {
        this.$toast.error('Failed to create Purchase Orders')
        console.error(error)
      } finally {
        this.loading = false
      }
    },

    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    }
  }
}
</script>

<style scoped>
.wizard-steps {
  display: flex;
  justify-content: center;
  margin-bottom: 2rem;
}

.step {
  display: flex;
  align-items: center;
  margin: 0 1rem;
  opacity: 0.5;
}

.step.active {
  opacity: 1;
}

.step-number {
  background: #e5e7eb;
  border-radius: 50%;
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 0.5rem;
}

.step.active .step-number {
  background: #3b82f6;
  color: white;
}

.vendor-selection-grid {
  display: grid;
  gap: 1rem;
}

.item-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.vendor-option {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 0.75rem;
  margin: 0.5rem 0;
  cursor: pointer;
  transition: all 0.2s;
}

.vendor-option:hover {
  border-color: #3b82f6;
}

.vendor-option.selected {
  border-color: #3b82f6;
  background-color: #eff6ff;
}

.quantity-split-table table {
  width: 100%;
  border-collapse: collapse;
}

.quantity-split-table th,
.quantity-split-table td {
  border: 1px solid #e5e7eb;
  padding: 0.75rem;
  text-align: left;
}

.vendor-split {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.25rem 0;
}

.quantity-input {
  width: 80px;
  padding: 0.25rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
}

.po-preview {
  display: grid;
  gap: 1rem;
}

.po-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.wizard-navigation {
  display: flex;
  justify-content: space-between;
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.btn-primary, .btn-outline, .btn-success {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-outline {
  background: transparent;
  color: #3b82f6;
  border: 1px solid #3b82f6;
}

.btn-success {
  background: #10b981;
  color: white;
}

.btn-primary:disabled,
.btn-outline:disabled,
.btn-success:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>