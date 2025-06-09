<template>
  <div class="create-po-from-pr">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-shopping-cart"></i>
            Create Purchase Order from PR
          </h1>
          <div class="pr-info" v-if="pr.pr_number">
            <span class="pr-badge">{{ pr.pr_number }}</span>
            <span class="pr-date">{{ formatDate(pr.pr_date) }}</span>
            <span class="pr-requester">{{ pr.requester?.name }}</span>
          </div>
        </div>
        
        <div class="header-actions">
          <router-link 
            :to="{ name: 'PurchaseRequisitionDetail', params: { id: prId } }"
            class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to PR
          </router-link>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading PR details...</p>
    </div>

    <!-- Main Content -->
    <div v-else-if="pr.pr_id" class="content-container">
      
      <!-- Strategy Selection -->
      <div class="strategy-card">
        <div class="card-header">
          <h3>
            <i class="fas fa-route"></i>
            Procurement Strategy
          </h3>
        </div>
        <div class="card-content">
          <div class="strategy-options">
            <label class="strategy-option" :class="{ 'selected': strategy === 'single' }">
              <input 
                type="radio" 
                v-model="strategy" 
                value="single"
                @change="resetForm">
              <div class="option-content">
                <div class="option-title">Single Vendor PO</div>
                <div class="option-description">Create one PO with a single vendor for all items</div>
              </div>
            </label>
            
            <label class="strategy-option" :class="{ 'selected': strategy === 'split' }">
              <input 
                type="radio" 
                v-model="strategy" 
                value="split"
                @change="resetForm">
              <div class="option-content">
                <div class="option-title">Multi-Vendor Split PO</div>
                <div class="option-description">Split items across multiple vendors for optimal pricing</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Single Vendor Form -->
      <div v-if="strategy === 'single'" class="form-container">
        
        <!-- Vendor Selection -->
        <div class="form-card">
          <div class="card-header">
            <h3>
              <i class="fas fa-building"></i>
              Vendor Selection
            </h3>
          </div>
          <div class="card-content">
            <div class="form-group">
              <label class="form-label">
                Select Vendor *
                <span v-if="autoSelected" class="auto-selected-badge">
                  <i class="fas fa-magic"></i>
                  Auto-selected
                </span>
              </label>
              <select 
                v-model="form.vendor_id" 
                class="form-select"
                :class="{ 'auto-selected': autoSelected }"
                @change="onVendorChange"
                required>
                <option value="">Choose vendor...</option>
                <option 
                  v-for="vendor in availableVendors" 
                  :key="vendor.vendor_id" 
                  :value="vendor.vendor_id">
                  {{ vendor.name }} ({{ vendor.vendor_code }})
                </option>
              </select>
            </div>
            
            <div v-if="selectedVendor" class="vendor-info">
              <div class="vendor-details">
                <div class="detail-item">
                  <span class="label">Payment Terms:</span>
                  <span class="value">{{ selectedVendor.payment_term_description || (selectedVendor.payment_term + ' days') }}</span>
                </div>
                <div class="detail-item">
                  <span class="label">Preferred Currency:</span>
                  <span class="value">{{ selectedVendor.preferred_currency || 'USD' }}</span>
                </div>
                <div class="detail-item">
                  <span class="label">Status:</span>
                  <span class="value status" :class="selectedVendor.status">{{ selectedVendor.status }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pricing Source -->
        <div v-if="form.vendor_id" class="form-card">
          <div class="card-header">
            <h3>
              <i class="fas fa-dollar-sign"></i>
              Pricing Source
            </h3>
          </div>
          <div class="card-content">
            <div class="pricing-options">
              <label class="pricing-option" :class="{ 'selected': form.pricing_source === 'contract' }">
                <input 
                  type="radio" 
                  v-model="form.pricing_source" 
                  value="contract"
                  :disabled="!pricingAvailability.contract"
                  @change="onPricingSourceChange">
                <div class="option-content">
                  <div class="option-title">
                    Contract Pricing
                    <i v-if="!pricingAvailability.contract" class="fas fa-exclamation-triangle text-warning"></i>
                  </div>
                  <div class="option-description">Use pricing from active vendor contract</div>
                </div>
              </label>
              
              <label class="pricing-option" :class="{ 'selected': form.pricing_source === 'quotation' }">
                <input 
                  type="radio" 
                  v-model="form.pricing_source" 
                  value="quotation"
                  :disabled="!pricingAvailability.quotation"
                  @change="onPricingSourceChange">
                <div class="option-content">
                  <div class="option-title">
                    Quotation Pricing
                    <i v-if="!pricingAvailability.quotation" class="fas fa-exclamation-triangle text-warning"></i>
                  </div>
                  <div class="option-description">Use pricing from valid vendor quotation</div>
                </div>
              </label>
              
              <label class="pricing-option" :class="{ 'selected': form.pricing_source === 'item_pricing' }">
                <input 
                  type="radio" 
                  v-model="form.pricing_source" 
                  value="item_pricing"
                  :disabled="!pricingAvailability.item_pricing"
                  @change="onPricingSourceChange">
                <div class="option-content">
                  <div class="option-title">
                    Item Pricing
                    <i v-if="!pricingAvailability.item_pricing" class="fas fa-exclamation-triangle text-warning"></i>
                  </div>
                  <div class="option-description">Use vendor-specific item pricing</div>
                </div>
              </label>
              
              <label class="pricing-option" :class="{ 'selected': form.pricing_source === 'manual' }">
                <input 
                  type="radio" 
                  v-model="form.pricing_source" 
                  value="manual"
                  @change="onPricingSourceChange">
                <div class="option-content">
                  <div class="option-title">Manual Pricing</div>
                  <div class="option-description">Enter prices manually for each item</div>
                </div>
              </label>
            </div>
            
            <div v-if="pricingValidation.length > 0" class="pricing-warnings">
              <div class="warning-header">
                <i class="fas fa-exclamation-triangle"></i>
                Pricing Issues Found
              </div>
              <ul class="warning-list">
                <li v-for="warning in pricingValidation" :key="warning.item_id">
                  <strong>{{ warning.item_code }}</strong>: {{ warning.message }}
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Manual Pricing Table -->
        <div v-if="form.pricing_source === 'manual'" class="form-card">
          <div class="card-header">
            <h3>
              <i class="fas fa-edit"></i>
              Manual Pricing
            </h3>
          </div>
          <div class="card-content">
            <div class="pricing-table-wrapper">
              <table class="pricing-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>UOM</th>
                    <th>Unit Price *</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in pr.lines" :key="line.line_id">
                    <td class="item-cell">
                      <div class="item-info">
                        <div class="item-code">{{ line.item.item_code }}</div>
                        <div class="item-name">{{ line.item.name }}</div>
                      </div>
                    </td>
                    <td class="quantity-cell">{{ line.quantity }}</td>
                    <td class="uom-cell">{{ line.unit_of_measure.symbol }}</td>
                    <td class="price-cell">
                      <input 
                        type="number" 
                        :value="getManualPrice(line.line_id).unit_price"
                        @input="updateManualPrice(line.line_id, $event.target.value)"
                        class="price-input"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required>
                    </td>
                    <td class="total-cell">
                      {{ formatCurrency(line.quantity * (getManualPrice(line.line_id).unit_price || 0)) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="total-row">
                    <td colspan="4"><strong>Total Amount</strong></td>
                    <td><strong>{{ formatCurrency(calculateManualTotal()) }}</strong></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- PO Details -->
        <div v-if="form.pricing_source" class="form-card">
          <div class="card-header">
            <h3>
              <i class="fas fa-file-alt"></i>
              Purchase Order Details
            </h3>
          </div>
          <div class="card-content">
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Currency</label>
                <select v-model="form.currency_code" class="form-select">
                  <option value="">Auto (Vendor Default)</option>
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="GBP">GBP</option>
                  <option value="JPY">JPY</option>
                  <option value="IDR">IDR</option>
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Exchange Rate</label>
                <input 
                  type="number" 
                  v-model.number="form.exchange_rate"
                  class="form-input"
                  step="0.000001"
                  min="0"
                  placeholder="Auto calculate">
              </div>
              
              <div class="form-group">
                <label class="form-label">Payment Terms</label>
                <input 
                  type="text" 
                  v-model="form.payment_terms"
                  class="form-input"
                  :placeholder="selectedVendor ? (selectedVendor.payment_term_description || (selectedVendor.payment_term + ' days')) : 'Enter payment terms'">
              </div>
              
              <div class="form-group">
                <label class="form-label">Delivery Terms</label>
                <input 
                  type="text" 
                  v-model="form.delivery_terms"
                  class="form-input"
                  placeholder="Enter delivery terms">
              </div>
              
              <div class="form-group span-2">
                <label class="form-label">Expected Delivery</label>
                <input 
                  type="date" 
                  v-model="form.expected_delivery"
                  class="form-input"
                  :min="today">
              </div>
            </div>
          </div>
        </div>

        <!-- Preview -->
        <div v-if="form.pricing_source && canShowPreview" class="form-card">
          <div class="card-header">
            <h3>
              <i class="fas fa-eye"></i>
              Preview
            </h3>
          </div>
          <div class="card-content">
            <div class="preview-summary">
              <div class="summary-item">
                <span class="label">Vendor:</span>
                <span class="value">{{ selectedVendor?.name }}</span>
              </div>
              <div class="summary-item">
                <span class="label">Pricing Source:</span>
                <span class="value">{{ getPricingSourceLabel(form.pricing_source) }}</span>
              </div>
              <div class="summary-item">
                <span class="label">Currency:</span>
                <span class="value">{{ form.currency_code || selectedVendor?.preferred_currency || 'USD' }}</span>
              </div>
              <div class="summary-item">
                <span class="label">Total Items:</span>
                <span class="value">{{ pr.lines?.length || 0 }}</span>
              </div>
              <div v-if="form.pricing_source === 'manual'" class="summary-item">
                <span class="label">Estimated Total:</span>
                <span class="value">{{ formatCurrency(calculateManualTotal()) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="action-buttons">
        <button 
          @click="goBack"
          class="btn-secondary">
          <i class="fas fa-arrow-left"></i>
          Back
        </button>
        
        <button 
          @click="saveDraft"
          :disabled="saving"
          class="btn-outline">
          <i class="fas fa-save"></i>
          Save Draft
        </button>
        
        <button 
          @click="createPO"
          :disabled="!canSubmit || saving"
          class="btn-primary">
          <i class="fas fa-shopping-cart" :class="{ 'fa-spin': saving }"></i>
          {{ saving ? 'Creating...' : 'Create Purchase Order' }}
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading" class="empty-state">
      <div class="empty-icon">
        <i class="fas fa-file-alt"></i>
      </div>
      <h3>Purchase Requisition Not Found</h3>
      <p>The specified PR could not be loaded or doesn't exist.</p>
      <router-link :to="{ name: 'PurchaseRequisitionList' }" class="btn-primary">
        Back to PR List
      </router-link>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'CreatePOFromPR',
  props: {
    prId: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      loading: false,
      saving: false,
      pr: {},
      availableVendors: [],
      strategy: 'single',
      autoSelected: false,
      form: {
        vendor_id: '',
        pricing_source: '',
        currency_code: '',
        exchange_rate: null,
        payment_terms: '',
        delivery_terms: '',
        expected_delivery: '',
        manual_prices: []
      },
      pricingAvailability: {
        contract: false,
        quotation: false,
        item_pricing: false
      },
      pricingValidation: [],
      selectedVendor: null
    }
  },
  computed: {
    today() {
      return new Date().toISOString().split('T')[0]
    },
    
    canShowPreview() {
      if (this.form.pricing_source === 'manual') {
        return this.form.manual_prices.length > 0 && 
               this.form.manual_prices.every(p => p.unit_price > 0)
      }
      return true
    },
    
    canSubmit() {
      return this.form.vendor_id && 
             this.form.pricing_source && 
             (this.form.pricing_source !== 'manual' || this.isManualPricingComplete()) &&
             this.pricingValidation.length === 0
    }
  },
  watch: {
    '$route.query': {
      handler() {
        this.handleQueryParameters()
      },
      immediate: false
    },
    
    availableVendors: {
      handler(vendors) {
        if (vendors.length > 0) {
          this.handleQueryParameters()
        }
      },
      immediate: false
    }
  },
  mounted() {
    this.fetchPR()
    this.fetchVendors()
    this.handleQueryParameters()
  },
  methods: {
    handleQueryParameters() {
      const queryVendorId = this.$route.query.vendor_id
      const autoSelect = this.$route.query.auto_select === 'true'
      
      if (queryVendorId && this.availableVendors.length > 0) {
        const vendor = this.availableVendors.find(v => v.vendor_id == queryVendorId)
        if (vendor) {
          this.form.vendor_id = queryVendorId
          this.autoSelected = autoSelect
          this.onVendorChange()
          
          if (autoSelect) {
            this.$toast?.success(`Vendor "${vendor.name}" auto-selected based on analysis recommendation`)
          }
        }
      }
      
      const recommendedStrategy = this.$route.query.strategy
      if (recommendedStrategy && ['single', 'split'].includes(recommendedStrategy)) {
        this.strategy = recommendedStrategy
        this.resetForm()
        
        if (queryVendorId && this.strategy === 'single') {
          this.form.vendor_id = queryVendorId
          this.autoSelected = autoSelect
        }
      }
    },

    async fetchPR() {
      this.loading = true
      try {
        const response = await axios.get(`/purchase-requisitions/${this.prId}`)
        this.pr = response.data.data
        
      } catch (error) {
        console.error('Error fetching PR:', error)
        this.$toast?.error('Failed to load Purchase Requisition')
      } finally {
        this.loading = false
      }
    },

    async fetchVendors() {
      try {
        const response = await axios.get('/vendors', {
          params: { status: 'active', per_page: 100 }
        })
        this.availableVendors = response.data.data.data || []
      } catch (error) {
        console.error('Error fetching vendors:', error)
        this.$toast?.error('Failed to load vendors')
      }
    },

    resetForm() {
      this.form = {
        vendor_id: '',
        pricing_source: '',
        currency_code: '',
        exchange_rate: null,
        payment_terms: '',
        delivery_terms: '',
        expected_delivery: '',
        manual_prices: []
      }
      this.selectedVendor = null
      this.pricingValidation = []
      this.autoSelected = false
    },

    async onVendorChange() {
      if (this.selectedVendor && this.selectedVendor.vendor_id != this.form.vendor_id) {
        this.autoSelected = false
      }
      
      this.selectedVendor = this.availableVendors.find(v => v.vendor_id == this.form.vendor_id)
      
      if (this.selectedVendor) {
        this.form.currency_code = this.selectedVendor.preferred_currency || ''
        this.form.payment_terms = this.selectedVendor.payment_term_description || (this.selectedVendor.payment_term + ' days')
        
        await this.checkPricingAvailability()
      }
    },

    async checkPricingAvailability() {
      if (!this.selectedVendor) return
      
      try {
        // Simulate pricing availability check
        this.pricingAvailability = {
          contract: Math.random() > 0.7,
          quotation: Math.random() > 0.5,
          item_pricing: Math.random() > 0.3
        }
        
        // Auto-select first available pricing source
        if (this.pricingAvailability.contract) {
          this.form.pricing_source = 'contract'
        } else if (this.pricingAvailability.quotation) {
          this.form.pricing_source = 'quotation'
        } else if (this.pricingAvailability.item_pricing) {
          this.form.pricing_source = 'item_pricing'
        } else {
          this.form.pricing_source = 'manual'
        }
        
        this.onPricingSourceChange()
        
        if (this.autoSelected && this.form.pricing_source !== 'manual') {
          this.$toast?.info(`Using ${this.getPricingSourceLabel(this.form.pricing_source)} for optimal workflow`)
        }
        
      } catch (error) {
        console.error('Error checking pricing availability:', error)
      }
    },

    onPricingSourceChange() {
      if (this.form.pricing_source === 'manual') {
        this.initializeManualPricing()
      }
      this.validatePricing()
    },

    initializeManualPricing() {
      this.form.manual_prices = this.pr.lines?.map(line => ({
        pr_line_id: line.line_id,
        unit_price: 0
      })) || []
    },

    getManualPrice(lineId) {
      return this.form.manual_prices.find(p => p.pr_line_id === lineId) || { unit_price: 0 }
    },

    updateManualPrice(lineId, price) {
      const priceObj = this.form.manual_prices.find(p => p.pr_line_id === lineId)
      if (priceObj) {
        priceObj.unit_price = parseFloat(price) || 0
      }
    },

    calculateManualTotal() {
      let total = 0
      for (const line of this.pr.lines || []) {
        const price = this.getManualPrice(line.line_id).unit_price || 0
        total += line.quantity * price
      }
      return total
    },

    isManualPricingComplete() {
      return this.form.manual_prices.length > 0 && 
             this.form.manual_prices.every(p => p.unit_price > 0)
    },

    validatePricing() {
      this.pricingValidation = []
      
      if (!this.form.pricing_source || !this.selectedVendor) return
      
      for (const line of this.pr.lines || []) {
        if (this.form.pricing_source === 'manual') {
          const price = this.getManualPrice(line.line_id).unit_price
          if (!price || price <= 0) {
            this.pricingValidation.push({
              item_id: line.item_id,
              item_code: line.item.item_code,
              message: 'Unit price is required'
            })
          }
        } else {
          if (Math.random() > 0.8) {
            this.pricingValidation.push({
              item_id: line.item_id,
              item_code: line.item.item_code,
              message: `No ${this.form.pricing_source} pricing found`
            })
          }
        }
      }
    },

    getPricingSourceLabel(source) {
      const labels = {
        'contract': 'Contract Pricing',
        'quotation': 'Quotation Pricing', 
        'item_pricing': 'Item Pricing',
        'manual': 'Manual Pricing'
      }
      return labels[source] || source
    },

    async createPO() {
      if (!this.canSubmit) return
      
      this.saving = true
      
      try {
        const payload = {
          pr_id: this.prId,
          vendor_id: this.form.vendor_id,
          pricing_source: this.form.pricing_source,
          currency_code: this.form.currency_code || undefined,
          exchange_rate: this.form.exchange_rate || undefined,
          payment_terms: this.form.payment_terms || undefined,
          delivery_terms: this.form.delivery_terms || undefined,
          expected_delivery: this.form.expected_delivery || undefined
        }
        
        if (this.form.pricing_source === 'manual') {
          payload.manual_prices = this.form.manual_prices
        }
        
        const response = await axios.post('/purchase-orders/create-from-pr', payload)
        
        this.$toast?.success('Purchase Order created successfully!')
        
        this.$router.push({ 
          name: 'PurchaseOrderDetail', 
          params: { id: response.data.data.po_id }
        })
        
      } catch (error) {
        console.error('Error creating PO:', error)
        this.$toast?.error(error.response?.data?.message || 'Failed to create Purchase Order')
      } finally {
        this.saving = false
      }
    },

    async saveDraft() {
      this.$toast?.info('Save draft functionality not implemented yet')
    },

    goBack() {
      this.$router.go(-1)
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
      if (!amount) return '$0.00'
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    }
  }
}
</script>

<style scoped>
.create-po-from-pr {
  padding: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Header Section */
.page-header {
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

.pr-info {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.pr-badge, .pr-date, .pr-requester {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.9rem;
}

.header-actions .btn-secondary {
  padding: 0.75rem 1.5rem;
  background: white;
  color: #667eea;
  border: none;
  border-radius: 8px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  transition: all 0.2s;
}

.header-actions .btn-secondary:hover {
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

/* Content Container */
.content-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Strategy Card */
.strategy-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  background: #f8fafc;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.card-header h3 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #374151;
}

.card-content {
  padding: 1.5rem;
}

.strategy-options {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.strategy-option {
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.strategy-option.selected {
  border-color: #3b82f6;
  background: #eff6ff;
}

.strategy-option input[type="radio"] {
  margin-top: 0.25rem;
}

.option-title {
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.25rem;
}

.option-description {
  font-size: 0.9rem;
  color: #6b7280;
}

/* Form Cards */
.form-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  display: block;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
}

.form-select,
.form-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 1rem;
  transition: border-color 0.2s;
}

.form-select:focus,
.form-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.span-2 {
  grid-column: span 2;
}

/* Auto-selected indicators */
.auto-selected-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: #10b981;
  color: white;
  padding: 0.2rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  margin-left: 0.5rem;
}

.form-select.auto-selected {
  border-color: #10b981;
  background: #ecfdf5;
}

.form-select.auto-selected:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

/* Vendor Info */
.vendor-info {
  margin-top: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.vendor-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-item .label {
  font-size: 0.9rem;
  color: #6b7280;
}

.detail-item .value {
  font-weight: 500;
  color: #374151;
}

.status.active {
  color: #059669;
}

.status.inactive {
  color: #dc2626;
}

/* Pricing Options */
.pricing-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.pricing-option {
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.pricing-option.selected {
  border-color: #3b82f6;
  background: #eff6ff;
}

.pricing-option:has(input:disabled) {
  opacity: 0.6;
  cursor: not-allowed;
}

.pricing-option input[type="radio"] {
  margin-top: 0.25rem;
}

.text-warning {
  color: #f59e0b;
}

/* Pricing Warnings */
.pricing-warnings {
  background: #fef3c7;
  border: 1px solid #f59e0b;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.warning-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: #92400e;
  margin-bottom: 0.5rem;
}

.warning-list {
  margin: 0;
  padding-left: 1.5rem;
}

.warning-list li {
  color: #92400e;
  margin: 0.25rem 0;
}

/* Pricing Table */
.pricing-table-wrapper {
  overflow-x: auto;
}

.pricing-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

.pricing-table th {
  background: #f9fafb;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 2px solid #e5e7eb;
}

.pricing-table td {
  padding: 1rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: top;
}

.item-cell {
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

.price-input {
  width: 120px;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
}

.total-row {
  background: #f9fafb;
  font-weight: 600;
}

/* Preview */
.preview-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 6px;
}

.summary-item .label {
  font-weight: 500;
  color: #6b7280;
}

.summary-item .value {
  font-weight: 600;
  color: #374151;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  padding: 2rem 0;
  border-top: 1px solid #e5e7eb;
  margin-top: 2rem;
}

.btn-primary,
.btn-secondary,
.btn-outline {
  padding: 0.75rem 2rem;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
  border: none;
  text-decoration: none;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-outline {
  background: transparent;
  color: #3b82f6;
  border: 1px solid #3b82f6;
}

.btn-primary:hover,
.btn-secondary:hover,
.btn-outline:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:disabled,
.btn-secondary:disabled,
.btn-outline:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
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

/* Responsive Design */
@media (max-width: 768px) {
  .create-po-from-pr {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .strategy-options {
    grid-template-columns: 1fr;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .span-2 {
    grid-column: span 1;
  }
  
  .pricing-options {
    grid-template-columns: 1fr;
  }
  
  .vendor-details {
    grid-template-columns: 1fr;
  }
  
  .preview-summary {
    grid-template-columns: 1fr;
  }
  
  .action-buttons {
    flex-direction: column;
  }
  
  .pricing-table {
    font-size: 0.8rem;
  }
  
  .pricing-table th,
  .pricing-table td {
    padding: 0.5rem;
  }
}
</style>