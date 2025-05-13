<!-- src/views/purchasing/RFQCompare.vue -->
<template>
    <div class="rfq-compare-container">
      <div class="page-header">
        <h1>Compare Vendor Quotations</h1>
        <div class="header-actions">
          <router-link :to="`/purchasing/rfqs/${rfqId}`" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to RFQ
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Loading quotations...
      </div>
  
      <div v-else-if="!rfq" class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-circle"></i>
      </div>
      <h3>Request for Quotation Not Found</h3>
      <p>The RFQ you are trying to view does not exist or has been deleted.</p>
      <router-link to="/purchasing/rfqs" class="btn btn-primary">
        Go Back to RFQ List
      </router-link>
    </div>

    <div v-else-if="quotations.length === 0" class="error-state">
      <div class="error-icon">
        <i class="fas fa-file-invoice-dollar"></i>
      </div>
      <h3>No Quotations Available</h3>
      <p>There are no vendor quotations available for comparison.</p>
      <router-link :to="`/purchasing/rfqs/${rfqId}`" class="btn btn-primary">
        View RFQ Details
      </router-link>
    </div>

    <div v-else class="rfq-compare-content">
      <div class="detail-card">
        <div class="card-header">
          <h2 class="card-title">RFQ Information</h2>
          <div class="status-badge" :class="getStatusClass(rfq.status)">
            {{ capitalizeFirstLetter(rfq.status) }}
          </div>
        </div>

        <div class="card-body">
          <div class="detail-row">
            <div class="detail-group">
              <label>RFQ Number</label>
              <div class="detail-value">{{ rfq.rfq_number }}</div>
            </div>
            
            <div class="detail-group">
              <label>RFQ Date</label>
              <div class="detail-value">{{ formatDate(rfq.rfq_date) }}</div>
            </div>
            
            <div class="detail-group">
              <label>Validity Date</label>
              <div class="detail-value">{{ formatDate(rfq.validity_date) || 'N/A' }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="detail-card">
        <div class="card-header">
          <h2 class="card-title">Vendor Quotation Comparison</h2>
          <div class="quotation-count">{{ quotations.length }} Quotations</div>
        </div>

        <div class="card-body">
          <div class="comparison-controls">
            <div class="filter-group">
              <label>Compare by</label>
              <select v-model="compareBy" class="form-control">
                <option value="item">By Item</option>
                <option value="vendor">By Vendor</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label>Sort by</label>
              <select v-model="sortBy" class="form-control">
                <option value="price">Price (Low to High)</option>
                <option value="price-desc">Price (High to Low)</option>
                <option value="vendor">Vendor Name</option>
                <option value="delivery">Delivery Date</option>
              </select>
            </div>
          </div>
          
          <!-- By Item Comparison View -->
          <div v-if="compareBy === 'item'" class="item-comparison">
<div 
  v-for="item in rfq.lines" 
  :key="item.line_id" 
  class="item-comparison-card"
>
              <div class="item-header">
                <div class="item-info">
                  <div class="item-name">{{ item.item.name }}</div>
                  <div class="item-code">{{ item.item.item_code }}</div>
                </div>
                <div class="item-qty">
                  {{ formatNumber(item.quantity) }} {{ item.unit_of_measure.symbol }}
                </div>
              </div>
              
              <div class="item-quotations">
                <table class="comparison-table">
                  <thead>
                    <tr>
                      <th>Vendor</th>
                      <th>Unit Price</th>
                      <th>Delivery Date</th>
                      <th>Total Price</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr 
                      v-for="quotation in sortedQuotations(item.item_id)" 
                      :key="`${item.item_id}-${quotation.vendor.vendor_id}`"
                      :class="{ 'best-price': isBestPrice(quotation, item.item_id) }"
                    >
                      <td>
                        <div class="vendor-name">{{ quotation.vendor.name }}</div>
                      </td>
                      <td>{{ formatCurrency(getQuotationPrice(quotation, item.item_id)) }}</td>
                      <td>{{ formatDate(getQuotationDeliveryDate(quotation, item.item_id)) || 'Not specified' }}</td>
                      <td>{{ formatCurrency(getQuotationPrice(quotation, item.item_id) * item.quantity) }}</td>
                      <td>
                        <button 
                          v-if="quotation.status !== 'accepted'"
                          @click="acceptQuotation(quotation, item.item_id)" 
                          class="btn btn-outline-success btn-sm"
                        >
                          Accept
                        </button>
                        <span v-else class="accepted-label">
                          <i class="fas fa-check-circle"></i> Accepted
                        </span>
                      </td>
                    </tr>
                    <tr v-if="sortedQuotations(item.item_id).length === 0">
                      <td colspan="5" class="no-quotations">
                        No vendor quotations for this item
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <!-- By Vendor Comparison View -->
          <div v-else class="vendor-comparison">
            <div 
              v-for="quotation in quotations" 
              :key="quotation.quotation_id" 
              class="vendor-comparison-card"
            >
              <div class="vendor-header">
                <div class="vendor-info">
                  <div class="vendor-name">{{ quotation.vendor.name }}</div>
                  <div class="vendor-details">
                    <span>Quote Date: {{ formatDate(quotation.quotation_date) }}</span>
                    <span v-if="quotation.validity_date">
                      Valid until: {{ formatDate(quotation.validity_date) }}
                    </span>
                  </div>
                </div>
                
                <div class="vendor-total">
                  Total: {{ formatCurrency(getVendorTotal(quotation)) }}
                </div>
              </div>
              
              <div class="vendor-items">
                <table class="comparison-table">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Description</th>
                      <th>Quantity</th>
                      <th>Unit Price</th>
                      <th>Delivery Date</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr 
                      v-for="line in quotation.lines" 
                      :key="line.line_id"
                      :class="{ 'best-price': isBestPriceVendorView(quotation, line.item_id) }"
                    >
                      <td>
                        <div class="item-code">{{ line.item.item_code }}</div>
                        <div class="item-name">{{ line.item.name }}</div>
                      </td>
                      <td>{{ line.item.description || 'N/A' }}</td>
                      <td>
                        {{ formatNumber(getRFQItemQuantity(line.item_id)) }}
                        {{ getRFQItemUOM(line.item_id) }}
                      </td>
                      <td>{{ formatCurrency(line.unit_price) }}</td>
                      <td>{{ formatDate(line.delivery_date) || 'Not specified' }}</td>
                      <td>{{ formatCurrency(getLineTotal(line)) }}</td>
                    </tr>
                    <tr v-if="quotation.lines.length === 0">
                      <td colspan="6" class="no-quotations">
                        No items quoted by this vendor
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <div class="vendor-actions">
                <button 
                  v-if="quotation.status !== 'accepted'"
                  @click="acceptVendorQuotation(quotation)" 
                  class="btn btn-outline-success"
                >
                  Accept Quotation
                </button>
                <router-link 
                  :to="`/purchasing/vendor-quotations/${quotation.quotation_id}`" 
                  class="btn btn-outline-primary"
                >
                  View Details
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <router-link :to="`/purchasing/rfqs/${rfqId}`" class="btn btn-secondary">
          Back to RFQ
        </router-link>
        <router-link 
          :to="`/purchasing/po/create-from-quotation?rfq_id=${rfqId}`" 
          class="btn btn-primary"
          v-if="hasAcceptedQuotations"
        >
          <i class="fas fa-file-alt"></i> Create Purchase Order
        </router-link>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showAcceptModal" class="modal">
      <div class="modal-backdrop" @click="showAcceptModal = false"></div>
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h2>Accept Quotation</h2>
          <button class="close-btn" @click="showAcceptModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>
            Are you sure you want to accept the quotation from 
            <strong>{{ selectedQuotation?.vendor?.name }}</strong>
            {{ selectedItemId ? 'for this item' : '' }}?
          </p>
          
          <div class="form-actions">
            <button type="button" class="btn btn-secondary" @click="showAcceptModal = false">
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-success"
              @click="confirmAcceptQuotation"
              :disabled="isProcessing"
            >
              <i v-if="isProcessing" class="fas fa-spinner fa-spin"></i>
              {{ isProcessing ? 'Processing...' : 'Accept Quotation' }}
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
  name: 'RFQCompare',
  props: {
    rfqId: {
      type: [Number, String],
      required: true
    }
  },
  data() {
    return {
      rfq: null,
      quotations: [],
      loading: true,
      compareBy: 'item',
      sortBy: 'price',
      showAcceptModal: false,
      selectedQuotation: null,
      selectedItemId: null,
      isProcessing: false
    }
  },
  computed: {
    hasAcceptedQuotations() {
      return this.quotations.some(quotation => quotation.status === 'accepted');
    }
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData() {
      this.loading = true;
      
      try {
        const response = await axios.get(`/request-for-quotations/${this.rfqId}`);
        
        if (response.data.status === 'success' && response.data.data) {
          this.rfq = response.data.data;
          
          if (this.rfq.vendor_quotations && this.rfq.vendor_quotations.length > 0) {
            // Load detailed quotation data
            const quotationPromises = this.rfq.vendor_quotations.map(quotation => 
              axios.get(`/vendor-quotations/${quotation.quotation_id}`)
            );
            
            const quotationResponses = await Promise.all(quotationPromises);
            this.quotations = quotationResponses
              .filter(response => response.data.status === 'success')
              .map(response => response.data.data);
          }
        } else {
          throw new Error(response.data.message || 'Failed to load RFQ');
        }
      } catch (error) {
        console.error('Error loading RFQ data:', error);
        this.rfq = null;
        
        if (error.response && error.response.status === 404) {
          this.$toast.error('Request for Quotation not found');
        } else {
          this.$toast.error('Failed to load RFQ details. Please try again.');
        }
      } finally {
        this.loading = false;
      }
    },
    formatDate(dateString) {
      if (!dateString) return null;
      
      const date = new Date(dateString);
      return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    },
    formatNumber(number) {
      return new Intl.NumberFormat('id-ID').format(number);
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(amount);
    },
    capitalizeFirstLetter(string) {
      if (!string) return '';
      return string.charAt(0).toUpperCase() + string.slice(1);
    },
    getStatusClass(status) {
      switch (status) {
        case 'draft': return 'status-draft';
        case 'sent': return 'status-sent';
        case 'closed': return 'status-closed';
        case 'canceled': return 'status-canceled';
        default: return '';
      }
    },
    // Get the quotation line for a specific item
    getQuotationLine(quotation, itemId) {
      return quotation.lines.find(line => line.item_id === itemId);
    },
    // Get unit price from a quotation for a specific item
    getQuotationPrice(quotation, itemId) {
      const line = this.getQuotationLine(quotation, itemId);
      return line ? line.unit_price : 0;
    },
    // Get delivery date from a quotation for a specific item
    getQuotationDeliveryDate(quotation, itemId) {
      const line = this.getQuotationLine(quotation, itemId);
      return line ? line.delivery_date : null;
    },
    // Sort quotations for a specific item based on current sort criteria
    sortedQuotations(itemId) {
      const quotationsWithItem = this.quotations.filter(quotation => {
        return quotation.lines.some(line => line.item_id === itemId);
      });
      
      if (this.sortBy === 'price') {
        return quotationsWithItem.sort((a, b) => {
          return this.getQuotationPrice(a, itemId) - this.getQuotationPrice(b, itemId);
        });
      } else if (this.sortBy === 'price-desc') {
        return quotationsWithItem.sort((a, b) => {
          return this.getQuotationPrice(b, itemId) - this.getQuotationPrice(a, itemId);
        });
      } else if (this.sortBy === 'vendor') {
        return quotationsWithItem.sort((a, b) => {
          return a.vendor.name.localeCompare(b.vendor.name);
        });
      } else if (this.sortBy === 'delivery') {
        return quotationsWithItem.sort((a, b) => {
          const dateA = this.getQuotationDeliveryDate(a, itemId);
          const dateB = this.getQuotationDeliveryDate(b, itemId);
          
          if (!dateA && !dateB) return 0;
          if (!dateA) return 1;
          if (!dateB) return -1;
          
          return new Date(dateA) - new Date(dateB);
        });
      }
      
      return quotationsWithItem;
    },
    // Check if a quotation has the best price for a specific item
    isBestPrice(quotation, itemId) {
      const sorted = this.sortedQuotations(itemId);
      if (sorted.length === 0) return false;
      
      // If sorting by price, first one is the best
      if (this.sortBy === 'price') {
        return quotation.quotation_id === sorted[0].quotation_id;
      }
      
      // If sorting by price descending, last one is the best
      if (this.sortBy === 'price-desc') {
        return quotation.quotation_id === sorted[sorted.length - 1].quotation_id;
      }
      
      // Otherwise, compare prices
      const bestPrice = Math.min(...sorted.map(q => this.getQuotationPrice(q, itemId)));
      return this.getQuotationPrice(quotation, itemId) === bestPrice;
    },
    // Similar check for vendor view
    isBestPriceVendorView(quotation, itemId) {
      const allQuotationsForItem = this.quotations.filter(q => 
        q.lines.some(line => line.item_id === itemId)
      );
      
      if (allQuotationsForItem.length === 0) return false;
      
      const prices = allQuotationsForItem.map(q => {
        const line = q.lines.find(l => l.item_id === itemId);
        return line ? line.unit_price : Infinity;
      });
      
      const bestPrice = Math.min(...prices);
      const thisPrice = this.getQuotationPrice(quotation, itemId);
      
      return thisPrice === bestPrice;
    },
    // Get total amount from a vendor quotation
    getVendorTotal(quotation) {
      return quotation.lines.reduce((total, line) => {
        const rfqLine = this.getRFQLine(line.item_id);
        const quantity = rfqLine ? rfqLine.quantity : line.quantity;
        return total + (line.unit_price * quantity);
      }, 0);
    },
    // Get total price for a quotation line
    getLineTotal(line) {
      const rfqLine = this.getRFQLine(line.item_id);
      const quantity = rfqLine ? rfqLine.quantity : line.quantity;
      return line.unit_price * quantity;
    },
    // Get RFQ line for a specific item
    getRFQLine(itemId) {
      return this.rfq.lines.find(line => line.item_id === itemId);
    },
    // Get quantity from RFQ for a specific item
    getRFQItemQuantity(itemId) {
      const line = this.getRFQLine(itemId);
      return line ? line.quantity : 0;
    },
    // Get UOM from RFQ for a specific item
    getRFQItemUOM(itemId) {
      const line = this.getRFQLine(itemId);
      return line && line.unit_of_measure ? line.unit_of_measure.symbol : '';
    },
    // Accept a quotation for a specific item
    acceptQuotation(quotation, itemId) {
      this.selectedQuotation = quotation;
      this.selectedItemId = itemId;
      this.showAcceptModal = true;
    },
    // Accept a whole vendor quotation
    acceptVendorQuotation(quotation) {
      this.selectedQuotation = quotation;
      this.selectedItemId = null;
      this.showAcceptModal = true;
    },
    // Confirm accepting a quotation
    async confirmAcceptQuotation() {
      if (!this.selectedQuotation) return;
      
      this.isProcessing = true;
      
      try {
        const data = {
          status: 'accepted'
        };
        
        // If item-specific acceptance
        if (this.selectedItemId) {
          data.item_id = this.selectedItemId;
        }
        
        const response = await axios.patch(
          `/vendor-quotations/${this.selectedQuotation.quotation_id}/status`, 
          data
        );
        
        if (response.data.status === 'success') {
          this.$toast.success('Quotation accepted successfully');
          await this.loadData(); // Reload data to reflect changes
        } else {
          throw new Error(response.data.message || 'Failed to accept quotation');
        }
      } catch (error) {
        console.error('Error accepting quotation:', error);
        
        if (error.response && error.response.data && error.response.data.message) {
          this.$toast.error('Failed to accept quotation: ' + error.response.data.message);
        } else {
          this.$toast.error('Failed to accept quotation. Please try again.');
        }
      } finally {
        this.showAcceptModal = false;
        this.selectedQuotation = null;
        this.selectedItemId = null;
        this.isProcessing = false;
      }
    }
  }
}
</script>

<style scoped>
.rfq-compare-container {
  padding: 1rem;
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0;
  font-size: 1.5rem;
}

.header-actions {
  display: flex;
  gap: 0.5rem;
}

.loading-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 3rem 0;
  color: var(--gray-500);
  font-size: 0.875rem;
}

.loading-indicator i {
  margin-right: 0.5rem;
  animation: spin 1s linear infinite;
}

.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 0;
  text-align: center;
}

.error-icon {
  font-size: 3rem;
  color: #ef4444;
  margin-bottom: 1rem;
}

.error-state h3 {
  font-size: 1.25rem;
  margin-bottom: 0.5rem;
}

.error-state p {
  color: var(--gray-500);
  margin-bottom: 1.5rem;
}

.rfq-compare-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.detail-card {
  border: 1px solid var(--gray-200);
  border-radius: 0.5rem;
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background-color: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.card-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-800);
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: capitalize;
}

.status-draft {
  background-color: var(--gray-100);
  color: var(--gray-700);
}

.status-sent {
  background-color: #dbeafe;
  color: #1e40af;
}

.status-closed {
  background-color: #dcfce7;
  color: #166534;
}

.status-canceled {
  background-color: #fee2e2;
  color: #991b1b;
}

.card-body {
  padding: 1.5rem;
}

.detail-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.detail-row:last-child {
  margin-bottom: 0;
}

.detail-group {
  flex: 1;
  min-width: 200px;
}

.detail-group label {
  display: block;
  font-size: 0.75rem;
  color: var(--gray-500);
  margin-bottom: 0.25rem;
}

.detail-value {
  font-size: 0.875rem;
  color: var(--gray-800);
}

.quotation-count {
  font-size: 0.875rem;
  color: var(--gray-500);
  font-weight: 500;
}

.comparison-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.filter-group label {
  font-size: 0.75rem;
  color: var(--gray-500);
  font-weight: 500;
}

.filter-group select {
  padding: 0.5rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  min-width: 200px;
}

.item-comparison {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.item-comparison-card {
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  overflow: hidden;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.item-name {
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: 0.25rem;
}

.item-code {
  font-size: 0.75rem;
  color: var(--gray-500);
}

.item-qty {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-600);
}

.item-quotations {
  padding: 1rem;
}

.vendor-comparison {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.vendor-comparison-card {
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  overflow: hidden;
}

.vendor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.vendor-info {
  display: flex;
  flex-direction: column;
}

.vendor-name {
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: 0.25rem;
}

.vendor-details {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.75rem;
  color: var(--gray-500);
}

.vendor-total {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-800);
}

.vendor-items {
  padding: 1rem;
}

.vendor-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1rem;
  border-top: 1px solid var(--gray-200);
}

.comparison-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.comparison-table th {
  text-align: left;
  padding: 0.75rem;
  border-bottom: 1px solid var(--gray-200);
  font-weight: 500;
  color: var(--gray-600);
}

.comparison-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--gray-100);
  color: var(--gray-800);
  vertical-align: middle;
}

.comparison-table tr:last-child td {
  border-bottom: none;
}

.best-price {
  background-color: rgba(16, 185, 129, 0.05);
}

.best-price td {
  font-weight: 500;
}

.accepted-label {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: #10b981;
  font-size: 0.875rem;
  font-weight: 500;
}

.accepted-label i {
  font-size: 1rem;
}

.no-quotations {
  text-align: center;
  color: var(--gray-500);
  padding: 1rem 0;
}

.form-actions {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
}

.btn {
  padding: 0.625rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
  border: none;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
}

.btn-secondary {
  background-color: white;
  color: var(--gray-700);
  border: 1px solid var(--gray-300);
}

.btn-secondary:hover {
  background-color: var(--gray-50);
}

.btn-outline {
  background-color: white;
  color: var(--gray-700);
  border: 1px solid var(--gray-300);
}

.btn-outline:hover {
  background-color: var(--gray-50);
}

.btn-outline-primary {
  background-color: white;
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
}

.btn-outline-primary:hover {
  background-color: rgba(37, 99, 235, 0.05);
}

.btn-outline-success {
  background-color: white;
  color: #10b981;
  border: 1px solid #10b981;
}

.btn-outline-success:hover {
  background-color: rgba(16, 185, 129, 0.05);
}

.btn-success {
  background-color: #10b981;
  color: white;
  border: none;
}

.btn-success:hover:not(:disabled) {
  background-color: #059669;
}

.btn-success:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

/* Modal Styles */
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
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 50;
}

.modal-content {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  width: 100%;
  z-index: 60;
  overflow: hidden;
}

.modal-sm {
  max-width: 400px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
  color: #1e293b;
}

.close-btn {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem;
  border-radius: 0.25rem;
}

.close-btn:hover {
  background-color: #f1f5f9;
  color: #0f172a;
}

.modal-body {
  padding: 1.5rem;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .header-actions {
    width: 100%;
  }
  
  .detail-row {
    flex-direction: column;
    gap: 1rem;
  }
  
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .comparison-controls {
    flex-direction: column;
  }
  
  .item-header,
  .vendor-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .comparison-table {
    display: block;
    overflow-x: auto;
  }
  
  .vendor-actions {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .form-actions {
    flex-direction: column;
  }
}
</style>