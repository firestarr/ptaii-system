<!-- src/views/purchasing/VendorQuotationCompare.vue -->
<template>
    <div class="quotation-compare-container">
      <div class="page-header">
        <h1>Compare Vendor Quotations</h1>
        <router-link to="/purchasing/quotations" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to List
        </router-link>
      </div>
  
      <div class="filter-section">
        <div class="filter-header">
          <h2>Select RFQ to Compare Quotations</h2>
        </div>
        <div class="filter-content">
          <div class="filter-select-group">
            <label for="rfq-select">Request for Quotation:</label>
            <select 
              id="rfq-select" 
              v-model="selectedRFQ"
              @change="loadQuotations"
            >
              <option value="">Select RFQ</option>
              <option v-for="rfq in rfqOptions" :key="rfq.rfq_id" :value="rfq.rfq_id">
                {{ rfq.rfq_number }} - {{ formatDate(rfq.rfq_date) }}
              </option>
            </select>
          </div>
          <div class="filter-help">
            <i class="fas fa-info-circle"></i>
            Select a Request for Quotation to view and compare all vendor responses.
          </div>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i> Loading quotations...
      </div>
  
      <div v-else>
        <div v-if="quotations.length === 0 && selectedRFQ" class="empty-state">
          <i class="fas fa-file-invoice-dollar empty-icon"></i>
          <h3>No quotations found</h3>
          <p>No vendor quotations are available for the selected Request for Quotation.</p>
        </div>
  
        <div v-else-if="quotations.length > 0" class="comparison-section">
          <!-- RFQ Information -->
          <div class="rfq-info-section">
            <div class="rfq-badge">
              <span>RFQ: {{ rfqDetails.rfq_number }}</span>
              <span 
                class="status-badge" 
                :class="rfqStatusClass"
              >{{ capitalizeFirstLetter(rfqDetails.status) }}</span>
            </div>
            <div class="rfq-details">
              <div class="rfq-detail-item">
                <span class="detail-label">Date:</span>
                <span class="detail-value">{{ formatDate(rfqDetails.rfq_date) }}</span>
              </div>
              <div class="rfq-detail-item">
                <span class="detail-label">Valid Until:</span>
                <span class="detail-value">{{ formatDate(rfqDetails.validity_date) }}</span>
              </div>
            </div>
          </div>
          
          <!-- Quotation Summary Cards -->
          <div class="summary-cards">
            <div 
              v-for="(quotation, index) in quotations" 
              :key="quotation.quotation_id"
              class="summary-card"
              :class="getQuotationCardClass(quotation, index)"
            >
              <div class="summary-header">
                <h3>{{ quotation.vendor ? quotation.vendor.name : 'Unknown Vendor' }}</h3>
                <span 
                  class="status-badge" 
                  :class="getStatusClass(quotation.status)"
                >{{ capitalizeFirstLetter(quotation.status) }}</span>
              </div>
              <div class="summary-body">
                <div class="summary-row">
                  <span class="summary-label">Quotation Date:</span>
                  <span class="summary-value">{{ formatDate(quotation.quotation_date) }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-label">Valid Until:</span>
                  <span class="summary-value">{{ formatDate(quotation.validity_date) }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-label">Total Amount:</span>
                  <span class="summary-value highlight">{{ formatCurrency(calculateTotal(quotation)) }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-label">Earliest Delivery:</span>
                  <span class="summary-value">{{ getEarliestDelivery(quotation) }}</span>
                </div>
              </div>
              <div class="summary-footer">
                <router-link :to="`/purchasing/quotations/${quotation.quotation_id}`" class="btn-link">
                  <i class="fas fa-eye"></i> View Details
                </router-link>
                <router-link 
                  v-if="quotation.status === 'accepted'" 
                  :to="`/purchasing/quotations/${quotation.quotation_id}/create-po`" 
                  class="btn-link success"
                >
                  <i class="fas fa-file-invoice"></i> Create PO
                </router-link>
                <button 
                  v-if="quotation.status === 'received'" 
                  @click="changeStatus(quotation.quotation_id, 'accepted')" 
                  class="btn-link success"
                >
                  <i class="fas fa-check"></i> Accept
                </button>
              </div>
            </div>
          </div>
          
          <!-- Detailed Comparison Table -->
          <div class="comparison-table-section">
            <h2>Item Details Comparison</h2>
            <div v-if="comparisonData.length > 0" class="table-responsive">
              <table class="comparison-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Requested Qty</th>
                    <th v-for="quotation in quotations" :key="`head-${quotation.quotation_id}`">
                      {{ quotation.vendor ? quotation.vendor.name : 'Unknown' }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="(item, itemIndex) in comparisonData" :key="itemIndex">
                    <!-- Item row -->
                    <tr class="item-row">
                      <td>{{ item.item_code }}</td>
                      <td>{{ item.item_name }}</td>
                      <td>{{ formatNumber(item.requested_quantity) }} {{ item.uom_symbol }}</td>
                      <td v-for="quotation in quotations" :key="`${itemIndex}-${quotation.quotation_id}`">
                        <div class="vendor-price">
                          {{ formatCurrency(getItemPrice(item.item_id, quotation)) }}
                        </div>
                      </td>
                    </tr>
                    <!-- Delivery row -->
                    <tr class="delivery-row">
                      <td colspan="3" class="delivery-label">Delivery Date</td>
                      <td v-for="quotation in quotations" :key="`del-${itemIndex}-${quotation.quotation_id}`">
                        {{ formatDate(getItemDeliveryDate(item.item_id, quotation)) }}
                      </td>
                    </tr>
                    <!-- Highlight best price -->
                    <tr class="highlight-row">
                      <td colspan="3" class="highlight-label">
                        <span v-if="getBestPriceVendor(item.item_id)">Best Price</span>
                      </td>
                      <td 
                        v-for="quotation in quotations" 
                        :key="`best-${itemIndex}-${quotation.quotation_id}`"
                        :class="{ 'best-price': isBestPrice(item.item_id, quotation.quotation_id) }"
                      >
                        <i v-if="isBestPrice(item.item_id, quotation.quotation_id)" class="fas fa-check-circle"></i>
                      </td>
                    </tr>
                  </template>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="total-label"><strong>Total</strong></td>
                    <td 
                      v-for="quotation in quotations" 
                      :key="`total-${quotation.quotation_id}`"
                      class="total-value"
                      :class="{ 'best-total': isBestTotalPrice(quotation.quotation_id) }"
                    >
                      <strong>{{ formatCurrency(calculateTotal(quotation)) }}</strong>
                      <i v-if="isBestTotalPrice(quotation.quotation_id)" class="fas fa-trophy"></i>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          
          <!-- Recommendation Section -->
          <div class="recommendation-section">
            <h2>Recommendation</h2>
            <div class="recommendation-content">
              <div class="recommendation-icon">
                <i class="fas fa-chart-line"></i>
              </div>
              <div class="recommendation-text">
                <p>
                  Based on price comparison, 
                  <strong>{{ getBestOverallVendor() ? getBestOverallVendor().name : 'None' }}</strong> 
                  offers the best overall value with the lowest total price.
                </p>
                <p>
                  For individual items:
                  <ul>
                    <li v-for="(item, index) in getItemRecommendations()" :key="index">
                      <strong>{{ item.item_name }}:</strong> 
                      Best price from <strong>{{ item.vendor_name }}</strong> 
                      at {{ formatCurrency(item.price) }}
                    </li>
                  </ul>
                </p>
                <div v-if="getBestOverallVendor()" class="recommendation-actions">
                  <template v-for="quotation in quotations" :key="`action-${quotation.quotation_id}`">
                    <template v-if="isBestTotalPrice(quotation.quotation_id)">
                      <button 
                        v-if="quotation.status === 'received'"
                        @click="changeStatus(quotation.quotation_id, 'accepted')" 
                        class="btn btn-success"
                      >
                        <i class="fas fa-check"></i> Accept Best Quotation
                      </button>
                      <router-link 
                        v-if="quotation.status === 'accepted'"
                        :to="`/purchasing/quotations/${quotation.quotation_id}/create-po`" 
                        class="btn btn-success"
                      >
                        <i class="fas fa-file-invoice"></i> Create PO for Best Quotation
                      </router-link>
                    </template>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'VendorQuotationCompare',
    data() {
      return {
        loading: false,
        selectedRFQ: '',
        rfqOptions: [],
        quotations: [],
        rfqDetails: {},
        comparisonData: [],
        processingAction: false
      };
    },
    computed: {
      rfqStatusClass() {
        if (!this.rfqDetails || !this.rfqDetails.status) return '';
        
        switch (this.rfqDetails.status) {
          case 'draft': return 'status-warn';
          case 'sent': return 'status-info';
          case 'closed': return 'status-success';
          case 'cancelled': return 'status-danger';
          default: return '';
        }
      }
    },
    methods: {
      // Initial data load
      fetchRFQOptions() {
        axios.get('/request-for-quotations')
          .then(response => {
            if (response.data.status === 'success') {
              this.rfqOptions = response.data.data.data.filter(rfq => rfq.status !== 'draft');
            }
          })
          .catch(error => {
            console.error('Error fetching RFQ options:', error);
            this.$toasted.error('Failed to load RFQ options');
          });
      },
// Continuing the script section of VendorQuotationCompare.vue

    // Load quotations for selected RFQ
    loadQuotations() {
      if (!this.selectedRFQ) {
        this.quotations = [];
        this.rfqDetails = {};
        this.comparisonData = [];
        return;
      }
      
      this.loading = true;
      
      // Load RFQ details
      axios.get(`/request-for-quotations/${this.selectedRFQ}`)
        .then(response => {
          if (response.data.status === 'success') {
            this.rfqDetails = response.data.data;
            
            // Now load all quotations for this RFQ
            return axios.get('/vendor-quotations', {
              params: { rfq_id: this.selectedRFQ }
            });
          }
        })
        .then(response => {
          if (response && response.data.status === 'success') {
            this.quotations = response.data.data.data;
            this.prepareComparisonData();
          }
        })
        .catch(error => {
          console.error('Error fetching data:', error);
          this.$toasted.error('Failed to load comparison data');
        })
        .finally(() => {
          this.loading = false;
        });
    },
    
    // Prepare data for comparison table
    prepareComparisonData() {
      if (!this.rfqDetails.lines || this.rfqDetails.lines.length === 0) {
        this.comparisonData = [];
        return;
      }
      
      // Create a list of all items from RFQ
      this.comparisonData = this.rfqDetails.lines.map(line => {
        return {
          item_id: line.item_id,
          item_code: line.item ? line.item.item_code : 'Unknown',
          item_name: line.item ? line.item.name : 'Unknown',
          requested_quantity: line.quantity,
          uom_symbol: line.unitOfMeasure ? line.unitOfMeasure.symbol : '',
          prices: {},
          delivery_dates: {},
          best_price_vendor: null,
          best_price: Infinity
        };
      });
      
      // For each quotation, extract item prices and delivery dates
      this.quotations.forEach(quotation => {
        if (!quotation.lines) return;
        
        quotation.lines.forEach(line => {
          const itemIndex = this.comparisonData.findIndex(item => item.item_id === line.item_id);
          
          if (itemIndex !== -1) {
            // Store price for this vendor
            this.comparisonData[itemIndex].prices[quotation.quotation_id] = line.unit_price;
            
            // Store delivery date for this vendor
            this.comparisonData[itemIndex].delivery_dates[quotation.quotation_id] = line.delivery_date;
            
            // Check if this is the best price so far
            if (line.unit_price < this.comparisonData[itemIndex].best_price) {
              this.comparisonData[itemIndex].best_price = line.unit_price;
              this.comparisonData[itemIndex].best_price_vendor = quotation.quotation_id;
            }
          }
        });
      });
    },
    
    // Get the unit price for an item from a quotation
    getItemPrice(itemId, quotation) {
      if (!quotation.lines) return 'N/A';
      
      const line = quotation.lines.find(line => line.item_id === itemId);
      return line ? line.unit_price : 'N/A';
    },
    
    // Get the delivery date for an item from a quotation
    getItemDeliveryDate(itemId, quotation) {
      if (!quotation.lines) return null;
      
      const line = quotation.lines.find(line => line.item_id === itemId);
      return line ? line.delivery_date : null;
    },
    
    // Check if this vendor has the best price for an item
    isBestPrice(itemId, quotationId) {
      const item = this.comparisonData.find(item => item.item_id === itemId);
      return item && item.best_price_vendor === quotationId;
    },
    
    // Calculate total for a quotation
    calculateTotal(quotation) {
      if (!quotation.lines || quotation.lines.length === 0) return 0;
      
      return quotation.lines.reduce((total, line) => {
        return total + (line.quantity * line.unit_price);
      }, 0);
    },
    
    // Check if this quotation has the best total price
    isBestTotalPrice(quotationId) {
      if (this.quotations.length === 0) return false;
      
      const bestQuotation = this.quotations.reduce((best, current) => {
        const bestTotal = this.calculateTotal(best);
        const currentTotal = this.calculateTotal(current);
        return currentTotal < bestTotal ? current : best;
      }, this.quotations[0]);
      
      return bestQuotation.quotation_id === quotationId;
    },
    
    // Get the vendor with best overall price
    getBestOverallVendor() {
      if (this.quotations.length === 0) return null;
      
      const bestQuotation = this.quotations.reduce((best, current) => {
        const bestTotal = this.calculateTotal(best);
        const currentTotal = this.calculateTotal(current);
        return currentTotal < bestTotal ? current : best;
      }, this.quotations[0]);
      
      return bestQuotation.vendor;
    },
    
    // Get the vendor with best price for an item
    getBestPriceVendor(itemId) {
      const item = this.comparisonData.find(item => item.item_id === itemId);
      
      if (!item || !item.best_price_vendor) return null;
      
      const quotation = this.quotations.find(q => q.quotation_id === item.best_price_vendor);
      return quotation ? quotation.vendor : null;
    },
    
    // Get list of item recommendations
    getItemRecommendations() {
      return this.comparisonData.map(item => {
        const vendor = this.getBestPriceVendor(item.item_id);
        
        return {
          item_id: item.item_id,
          item_name: item.item_name,
          vendor_name: vendor ? vendor.name : 'None',
          price: item.best_price
        };
      });
    },
    
    // Get earliest delivery date from a quotation
    getEarliestDelivery(quotation) {
      if (!quotation.lines || quotation.lines.length === 0) return 'N/A';
      
      const dates = quotation.lines
        .map(line => line.delivery_date)
        .filter(date => date); // Filter out nulls/undefined
      
      if (dates.length === 0) return 'N/A';
      
      const earliestDate = new Date(Math.min(...dates.map(date => new Date(date))));
      return this.formatDate(earliestDate);
    },
    
    // Change quotation status
    changeStatus(id, newStatus) {
      this.processingAction = true;
      
      axios.patch(`/vendor-quotations/${id}/status`, { status: newStatus })
        .then(response => {
          if (response.data.status === 'success') {
            // Update the local data to reflect the change
            const index = this.quotations.findIndex(q => q.quotation_id === id);
            if (index !== -1) {
              this.quotations[index].status = newStatus;
            }
            
            // Show success notification
            this.$toasted.success(`Quotation ${newStatus} successfully.`);
          } else {
            this.$toasted.error(`Failed to update status: ${response.data.message}`);
          }
        })
        .catch(error => {
          console.error('API Error:', error);
          this.$toasted.error('An error occurred while updating status');
        })
        .finally(() => {
          this.processingAction = false;
        });
    },
    
    // Get class for quotation card
    getQuotationCardClass(quotation, index) {
      const classes = ['vendor-' + (index % 5 + 1)]; // Cycle through 5 color variants
      
      if (this.isBestTotalPrice(quotation.quotation_id)) {
        classes.push('best-overall');
      }
      
      return classes;
    },
    
    // Get class for status badge
    getStatusClass(status) {
      switch (status) {
        case 'received': return 'status-info';
        case 'accepted': return 'status-success';
        case 'rejected': return 'status-danger';
        default: return '';
      }
    },
    
    // Format date for display
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    
    // Format number for display
    formatNumber(value) {
      if (value === undefined || value === null) return 'N/A';
      return new Intl.NumberFormat('id-ID').format(value);
    },
    
    // Format currency for display
    formatCurrency(value) {
      if (value === undefined || value === null || value === 'N/A') return 'N/A';
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(value);
    },
    
    // Capitalize first letter of a string
    capitalizeFirstLetter(string) {
      if (!string) return '';
      return string.charAt(0).toUpperCase() + string.slice(1);
    }
  },
  mounted() {
    this.fetchRFQOptions();
    
    // If RFQ ID is provided in query params, select it automatically
    if (this.$route.query.rfq_id) {
      this.selectedRFQ = this.$route.query.rfq_id;
      this.loadQuotations();
    }
  }
};
</script>

<style scoped>
.quotation-compare-container {
  max-width: 1200px;
  margin: 0 auto;
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
  color: var(--gray-800);
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  background-color: var(--gray-100);
  color: var(--gray-700);
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background-color: var(--gray-200);
  text-decoration: none;
}

.btn-secondary i {
  margin-right: 0.5rem;
}

.filter-section {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 1.5rem;
  overflow: hidden;
}

.filter-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--gray-200);
}

.filter-header h2 {
  margin: 0;
  font-size: 1.125rem;
  color: var(--gray-800);
}

.filter-content {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.filter-select-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-select-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-700);
}

.filter-select-group select {
  padding: 0.625rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  color: var(--gray-800);
  background-color: white;
  width: 100%;
  max-width: 500px;
}

.filter-help {
  font-size: 0.875rem;
  color: var(--gray-500);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.loading-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 200px;
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  color: var(--gray-500);
  margin-bottom: 1.5rem;
}

.loading-container i {
  margin-right: 0.5rem;
  animation: spin 1s linear infinite;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 0;
  text-align: center;
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.empty-icon {
  font-size: 3rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

.empty-state h3 {
  font-size: 1.125rem;
  color: var(--gray-700);
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: var(--gray-500);
  max-width: 24rem;
}

.comparison-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.rfq-info-section {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.rfq-badge {
  display: flex;
  align-items: center;
  gap: 1rem;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-800);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-info {
  background-color: #e0f2fe;
  color: #0369a1;
}

.status-success {
  background-color: #dcfce7;
  color: #166534;
}

.status-danger {
  background-color: #fee2e2;
  color: #b91c1c;
}

.status-warn {
  background-color: #fff7ed;
  color: #9a3412;
}

.rfq-details {
  display: flex;
  flex-wrap: wrap;
  gap: 2rem;
}

.rfq-detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.75rem;
  color: var(--gray-500);
  font-weight: 500;
}

.detail-value {
  font-size: 0.875rem;
  color: var(--gray-800);
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.summary-card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  border-top: 3px solid var(--gray-300);
}

.vendor-1 {
  border-top-color: #3b82f6;
}

.vendor-2 {
  border-top-color: #10b981;
}

.vendor-3 {
  border-top-color: #f59e0b;
}

.vendor-4 {
  border-top-color: #6366f1;
}

.vendor-5 {
  border-top-color: #ec4899;
}

.best-overall {
  box-shadow: 0 4px 6px rgba(37, 99, 235, 0.1), 0 0 0 2px rgba(37, 99, 235, 0.2);
}

.summary-header {
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--gray-200);
}

.summary-header h3 {
  margin: 0;
  font-size: 1rem;
  color: var(--gray-800);
}

.summary-body {
  padding: 1.5rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
}

.summary-label {
  font-size: 0.75rem;
  color: var(--gray-500);
}

.summary-value {
  font-size: 0.875rem;
  color: var(--gray-800);
  font-weight: 500;
}

.highlight {
  font-size: 1rem;
  font-weight: 600;
  color: var(--primary-color);
}

.summary-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  background-color: var(--gray-50);
}

.btn-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--primary-color);
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.2s;
}

.btn-link:hover {
  color: var(--primary-dark);
  text-decoration: none;
}

.btn-link.success {
  color: #059669;
}

.btn-link.success:hover {
  color: #047857;
}

.comparison-table-section {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
}

.comparison-table-section h2 {
  margin-top: 0;
  margin-bottom: 1.5rem;
  font-size: 1.125rem;
  color: var(--gray-800);
}

.table-responsive {
  overflow-x: auto;
}

.comparison-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.comparison-table th {
  text-align: left;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--gray-200);
  background-color: var(--gray-50);
  font-weight: 500;
  color: var(--gray-600);
  position: sticky;
  top: 0;
  z-index: 10;
}

.comparison-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--gray-100);
  color: var(--gray-800);
}

.item-row td {
  border-bottom: none;
  padding-bottom: 0.25rem;
}

.delivery-row td {
  padding-top: 0.25rem;
  padding-bottom: 0.25rem;
  color: var(--gray-500);
  font-size: 0.75rem;
}

.delivery-label {
  text-align: right;
  font-weight: 500;
}

.highlight-row td {
  padding-top: 0.25rem;
  padding-bottom: 0.75rem;
  color: var(--gray-500);
  font-size: 0.75rem;
}

.highlight-label {
  text-align: right;
  font-weight: 500;
}

.best-price {
  color: #059669;
  font-weight: 600;
}

.best-price i {
  margin-left: 0.5rem;
}

.vendor-price {
  font-weight: 500;
}

.comparison-table tfoot td {
  border-top: 2px solid var(--gray-200);
  background-color: var(--gray-50);
  padding: 1rem;
  font-size: 1rem;
}

.total-label {
  text-align: right;
}

.total-value {
  text-align: center;
}

.best-total {
  color: #059669;
  font-weight: 600;
}

.best-total i {
  margin-left: 0.5rem;
  color: #f59e0b;
}

.recommendation-section {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
}

.recommendation-section h2 {
  margin-top: 0;
  margin-bottom: 1.5rem;
  font-size: 1.125rem;
  color: var(--gray-800);
}

.recommendation-content {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
}

.recommendation-icon {
  font-size: 2.5rem;
  color: var(--primary-color);
  padding: 1rem;
  background-color: rgba(37, 99, 235, 0.1);
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.recommendation-text {
  flex: 1;
}

.recommendation-text p {
  margin-top: 0;
  margin-bottom: 1rem;
  line-height: 1.5;
}

.recommendation-text ul {
  margin-top: 0.5rem;
  padding-left: 1.5rem;
}

.recommendation-text li {
  margin-bottom: 0.5rem;
}

.recommendation-actions {
  margin-top: 1.5rem;
  display: flex;
  justify-content: flex-end;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.625rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  border: none;
}

.btn i {
  margin-right: 0.5rem;
}

.btn-success {
  background-color: #059669;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background-color: #047857;
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
  
  .recommendation-content {
    flex-direction: column;
  }
  
  .recommendation-icon {
    align-self: center;
  }
  
  .comparison-table th,
  .comparison-table td {
    white-space: nowrap;
  }
}
</style>