<!-- src/views/inventory/NegativeStocks.vue -->
<template>
    <div class="negative-stocks">
      <div class="page-header">
        <div class="header-left">
          <router-link to="/item-stocks" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Stock List
          </router-link>
          <h1>Negative Stock Report</h1>
        </div>
        <div class="actions">
          <button class="btn btn-primary" @click="refreshData" :disabled="loading">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>
      </div>
  
      <div class="content-container">
        <div class="summary-card">
          <div class="card-header">
            <h2>Negative Stock Summary</h2>
          </div>
          <div class="card-body">
            <div v-if="loading" class="loading-indicator">
              <i class="fas fa-spinner fa-spin"></i> Loading summary data...
            </div>
            <div v-else-if="!summary" class="empty-state small">
              <i class="fas fa-check-circle"></i>
              <p>No summary data available.</p>
            </div>
            <div v-else class="summary-grid">
              <div class="summary-item">
                <div class="summary-value">{{ summary.total_negative_items }}</div>
                <div class="summary-label">Items with Negative Stock</div>
              </div>
              <div class="summary-item">
                <div class="summary-value">{{ formatQuantity(summary.total_negative_quantity) }}</div>
                <div class="summary-label">Total Negative Quantity</div>
              </div>
              <div class="summary-item">
                <div class="summary-value">{{ formatCurrency(summary.total_negative_value) }}</div>
                <div class="summary-label">Total Negative Value</div>
              </div>
            </div>
  
            <div v-if="summary && summary.warehouse_summary.length > 0" class="warehouse-summary">
              <h3>Affected Warehouses</h3>
              <div class="warehouse-summary-table-container">
                <table class="warehouse-summary-table">
                  <thead>
                    <tr>
                      <th>Warehouse</th>
                      <th>Items</th>
                      <th>Total Negative Quantity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="warehouse in summary.warehouse_summary" :key="warehouse.warehouse_id">
                      <td>{{ warehouse.warehouse_name }}</td>
                      <td>{{ warehouse.item_count }}</td>
                      <td>{{ formatQuantity(warehouse.total_negative_quantity) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
  
        <div class="negative-stocks-card">
          <div class="card-header">
            <h2>Items with Negative Stock</h2>
          </div>
          <div class="card-body">
            <div class="filters">
              <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input 
                  type="text" 
                  v-model="searchQuery" 
                  placeholder="Search by item code or name" 
                  @input="filterItems"
                />
                <button v-if="searchQuery" @click="clearSearch" class="clear-search">
                  <i class="fas fa-times"></i>
                </button>
              </div>
  
              <div class="filter-group">
                <label for="warehouseFilter">Warehouse:</label>
                <select id="warehouseFilter" v-model="warehouseFilter" @change="filterItems">
                  <option value="">All Warehouses</option>
                  <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                    {{ warehouse.name }}
                  </option>
                </select>
              </div>
            </div>
  
            <div v-if="loading" class="loading-indicator">
              <i class="fas fa-spinner fa-spin"></i> Loading negative stock data...
            </div>
            
            <div v-else-if="filteredStocks.length === 0" class="empty-state small">
              <i class="fas fa-check-circle"></i>
              <p>No negative stock items found{{ warehouseFilter ? ' in this warehouse' : '' }}.</p>
            </div>
            
            <div v-else class="negative-stocks-table-container">
              <table class="negative-stocks-table">
                <thead>
                  <tr>
                    <th @click="sortBy('item_code')">
                      Item Code 
                      <i v-if="sortKey === 'item_code'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                    </th>
                    <th @click="sortBy('item_name')">
                      Item Name
                      <i v-if="sortKey === 'item_name'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                    </th>
                    <th @click="sortBy('warehouse_name')">
                      Warehouse
                      <i v-if="sortKey === 'warehouse_name'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                    </th>
                    <th @click="sortBy('quantity')">
                      Quantity
                      <i v-if="sortKey === 'quantity'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                    </th>
                    <th @click="sortBy('value')">
                      Value
                      <i v-if="sortKey === 'value'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                    </th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="stock in paginatedStocks" :key="`${stock.item_id}-${stock.warehouse_id}`">
                    <td>{{ stock.item_code }}</td>
                    <td>{{ stock.item_name }}</td>
                    <td>{{ stock.warehouse_name }}</td>
                    <td class="negative-value">{{ formatQuantity(stock.quantity) }}</td>
                    <td class="negative-value">{{ formatCurrency(stock.value) }}</td>
                    <td class="actions-cell">
                      <router-link 
                        :to="`/item-stocks/adjust?item=${stock.item_id}&warehouse=${stock.warehouse_id}`" 
                        class="btn-icon" 
                        title="Adjust Stock"
                      >
                        <i class="fas fa-sliders-h"></i>
                      </router-link>
                      <router-link 
                        :to="`/stock-transactions/items/${stock.item_id}/movement?warehouse=${stock.warehouse_id}`" 
                        class="btn-icon" 
                        title="View Movements"
                      >
                        <i class="fas fa-history"></i>
                      </router-link>
                      <button 
                        class="btn-icon" 
                        @click="showCorrectModal(stock)" 
                        title="Quick Correct"
                      >
                        <i class="fas fa-magic"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
  
              <div class="pagination" v-if="filteredStocks.length > 0">
                <div class="pagination-info">
                  Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ filteredStocks.length }} items
                </div>
                <div class="pagination-controls">
                  <button 
                    class="pagination-btn" 
                    :disabled="currentPage === 1" 
                    @click="currentPage--"
                  >
                    <i class="fas fa-chevron-left"></i>
                  </button>
                  
                  <template v-for="page in displayedPages" :key="page">
                    <button 
                      v-if="page !== '...'" 
                      class="pagination-btn" 
                      :class="{ active: page === currentPage }"
                      @click="currentPage = page"
                    >
                      {{ page }}
                    </button>
                    <span v-else class="pagination-ellipsis">...</span>
                  </template>
                  
                  <button 
                    class="pagination-btn" 
                    :disabled="currentPage === totalPages" 
                    @click="currentPage++"
                  >
                    <i class="fas fa-chevron-right"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Quick Correct Modal -->
      <div class="modal" v-if="correctModal.show">
        <div class="modal-backdrop" @click="correctModal.show = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Quick Correct Negative Stock</h2>
            <button class="close-btn" @click="correctModal.show = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="stock-details">
              <div class="detail-row">
                <div class="detail-label">Item:</div>
                <div class="detail-value">{{ correctModal.stock?.item_code }} - {{ correctModal.stock?.item_name }}</div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Warehouse:</div>
                <div class="detail-value">{{ correctModal.stock?.warehouse_name }}</div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Current Quantity:</div>
                <div class="detail-value negative-value">{{ formatQuantity(correctModal.stock?.quantity) }}</div>
              </div>
            </div>
  
            <div class="correction-form">
              <div class="form-group">
                <label for="correctionQuantity">New Quantity <span class="required">*</span></label>
                <input 
                  type="number" 
                  id="correctionQuantity" 
                  v-model.number="correctModal.newQuantity" 
                  min="0" 
                  step="0.01" 
                  required
                />
                <div class="input-hint">
                  Set to 0 to reset the balance or specify a positive quantity
                </div>
              </div>
  
              <div class="form-group">
                <label for="correctionReason">Reason <span class="required">*</span></label>
                <select 
                  id="correctionReason" 
                  v-model="correctModal.reason" 
                  required
                >
                  <option value="">Select a reason</option>
                  <option value="inventory_count">Inventory Count</option>
                  <option value="system_error">System Error</option>
                  <option value="data_correction">Data Correction</option>
                  <option value="reconciliation">Stock Reconciliation</option>
                  <option value="other">Other</option>
                </select>
              </div>
  
              <div v-if="correctModal.reason === 'other'" class="form-group">
                <label for="correctionOtherReason">Specify Reason <span class="required">*</span></label>
                <input 
                  type="text" 
                  id="correctionOtherReason" 
                  v-model="correctModal.otherReason" 
                  placeholder="Specify reason for correction" 
                  required
                />
              </div>
  
              <div class="form-group">
                <label for="correctionNotes">Notes</label>
                <textarea 
                  id="correctionNotes" 
                  v-model="correctModal.notes" 
                  rows="2" 
                  placeholder="Additional notes for this correction"
                ></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="correctModal.show = false">Cancel</button>
            <button 
              class="btn btn-primary" 
              @click="submitCorrection" 
              :disabled="!isFormValid || correctModal.loading"
            >
              <i v-if="correctModal.loading" class="fas fa-spinner fa-spin"></i>
              <span v-else>Apply Correction</span>
            </button>
          </div>
        </div>
      </div>
  
      <!-- Success Modal -->
      <div class="modal" v-if="showSuccessModal">
        <div class="modal-backdrop" @click="showSuccessModal = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Correction Successful</h2>
            <button class="close-btn" @click="showSuccessModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="success-message">
              <i class="fas fa-check-circle"></i>
              <p>Stock correction completed successfully!</p>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-primary" @click="closeSuccessAndRefresh">Close</button>
          </div>
        </div>
      </div>
  
      <!-- Error Modal -->
      <div class="modal" v-if="errorModal.show">
        <div class="modal-backdrop" @click="errorModal.show = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Error</h2>
            <button class="close-btn" @click="errorModal.show = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="error-message">
              <i class="fas fa-exclamation-circle"></i>
              <p>{{ errorModal.message }}</p>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-primary" @click="errorModal.show = false">Close</button>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'NegativeStocks',
    data() {
      return {
        negativeStocks: [],
        warehouses: [],
        summary: null,
        loading: true,
        searchQuery: '',
        warehouseFilter: '',
        sortKey: 'item_code',
        sortOrder: 'asc',
        currentPage: 1,
        itemsPerPage: 10,
        correctModal: {
          show: false,
          stock: null,
          newQuantity: 0,
          reason: '',
          otherReason: '',
          notes: '',
          loading: false
        },
        showSuccessModal: false,
        errorModal: {
          show: false,
          message: ''
        }
      };
    },
    computed: {
      filteredStocks() {
        let result = [...this.negativeStocks];
        
        // Search filter
        if (this.searchQuery) {
          const query = this.searchQuery.toLowerCase();
          result = result.filter(stock => 
            stock.item_code.toLowerCase().includes(query) || 
            stock.item_name.toLowerCase().includes(query)
          );
        }
        
        // Warehouse filter
        if (this.warehouseFilter) {
          result = result.filter(stock => stock.warehouse_id == this.warehouseFilter);
        }
        
        // Sort
        result = result.sort((a, b) => {
          let aValue = a[this.sortKey];
          let bValue = b[this.sortKey];
          
          // Handle comparison for strings vs numbers
          if (typeof aValue === 'string') {
            aValue = aValue.toLowerCase();
            bValue = bValue.toLowerCase();
          }
          
          if (aValue < bValue) return this.sortOrder === 'asc' ? -1 : 1;
          if (aValue > bValue) return this.sortOrder === 'asc' ? 1 : -1;
          return 0;
        });
        
        return result;
      },
      totalPages() {
        return Math.ceil(this.filteredStocks.length / this.itemsPerPage);
      },
      startIndex() {
        return (this.currentPage - 1) * this.itemsPerPage;
      },
      endIndex() {
        return Math.min(this.startIndex + this.itemsPerPage, this.filteredStocks.length);
      },
      paginatedStocks() {
        return this.filteredStocks.slice(this.startIndex, this.endIndex);
      },
      displayedPages() {
        const total = this.totalPages;
        const current = this.currentPage;
        const pages = [];
        
        if (total <= 7) {
          // Show all pages if 7 or fewer
          for (let i = 1; i <= total; i++) {
            pages.push(i);
          }
        } else {
          // Always include first page
          pages.push(1);
          
          // Show ellipsis if current page is more than 3
          if (current > 3) {
            pages.push('...');
          }
          
          // Add pages around current page
          const startPage = Math.max(2, current - 1);
          const endPage = Math.min(total - 1, current + 1);
          
          for (let i = startPage; i <= endPage; i++) {
            pages.push(i);
          }
          
          // Show ellipsis if current page is less than total - 2
          if (current < total - 2) {
            pages.push('...');
          }
          
          // Always include last page
          if (total > 1) {
            pages.push(total);
          }
        }
        
        return pages;
      },
      isFormValid() {
        if (this.correctModal.newQuantity < 0) return false;
        if (!this.correctModal.reason) return false;
        if (this.correctModal.reason === 'other' && !this.correctModal.otherReason.trim()) return false;
        
        return true;
      }
    },
    created() {
      this.fetchWarehouses();
      this.fetchData();
    },
    methods: {
      async fetchWarehouses() {
        try {
          const response = await axios.get('/warehouses');
          if (response.data && response.data.data) {
            this.warehouses = response.data.data;
          }
        } catch (err) {
          console.error('Error fetching warehouses:', err);
          this.showError('Failed to load warehouse data. Please try again later.');
        }
      },
      async fetchData() {
        this.loading = true;
        
        try {
          // Fetch negative stocks
          const stocksResponse = await axios.get('/item-stocks/negative');
          if (stocksResponse.data && stocksResponse.data.data) {
            this.negativeStocks = stocksResponse.data.data.map(stock => ({
              ...stock,
              // Calculate approximate value based on cost price * quantity
              value: (stock.item && stock.item.cost_price) ? stock.item.cost_price * stock.quantity : 0
            }));
          } else {
            this.negativeStocks = [];
          }
          
          // Fetch summary
          const summaryResponse = await axios.get('/item-stocks/negative-stock-summary');
          if (summaryResponse.data && summaryResponse.data.data) {
            this.summary = summaryResponse.data.data;
          } else {
            this.summary = null;
          }
        } catch (err) {
          console.error('Error fetching negative stocks:', err);
          this.showError('Failed to load negative stock data. Please try again later.');
        } finally {
          this.loading = false;
        }
      },
      refreshData() {
        this.fetchData();
      },
      filterItems() {
        this.currentPage = 1; // Reset to first page when filtering
      },
      clearSearch() {
        this.searchQuery = '';
        this.filterItems();
      },
      sortBy(key) {
        // If already sorted by this key, toggle order
        if (this.sortKey === key) {
          this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
          // Otherwise, sort by this key in ascending order
          this.sortKey = key;
          this.sortOrder = 'asc';
        }
      },
      formatQuantity(quantity) {
        return Number(quantity).toFixed(2);
      },
      formatCurrency(value) {
        // Use a simple currency format, can be replaced with a more specific format if needed
        return `$${Math.abs(Number(value)).toFixed(2)}`;
      },
      showCorrectModal(stock) {
        this.correctModal = {
          show: true,
          stock: stock,
          newQuantity: 0, // Default to setting it to zero
          reason: '',
          otherReason: '',
          notes: '',
          loading: false
        };
      },
      async submitCorrection() {
        if (!this.isFormValid) return;
        
        this.correctModal.loading = true;
        
        try {
          // Determine the reason text
          const reason = this.correctModal.reason === 'other' 
            ? this.correctModal.otherReason 
            : this.correctModal.reason;
          
          // Submit stock adjustment to correct the negative stock
          const response = await axios.post('/item-stocks/adjust', {
            item_id: this.correctModal.stock.item_id,
            warehouse_id: this.correctModal.stock.warehouse_id,
            new_quantity: this.correctModal.newQuantity,
            reason: reason,
            notes: `Negative stock correction. ${this.correctModal.notes}`
          });
          
          if (response.data && response.data.message) {
            this.correctModal.show = false;
            this.showSuccessModal = true;
          }
        } catch (err) {
          console.error('Error submitting correction:', err);
          this.showError(err.response?.data?.message || 'Failed to correct stock. Please try again later.');
        } finally {
          this.correctModal.loading = false;
        }
      },
      showError(message) {
        this.errorModal = {
          show: true,
          message: message
        };
      },
      closeSuccessAndRefresh() {
        this.showSuccessModal = false;
        this.refreshData();
      }
    }
  };
  </script>
  
  <style scoped>
  .negative-stocks {
    margin-bottom: 2rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
  }
  
  .header-left {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.875rem;
  }
  
  .back-link:hover {
    text-decoration: underline;
  }
  
  .page-header h1 {
    margin: 0;
    font-size: 1.75rem;
  }
  
  .actions {
    display: flex;
    gap: 0.75rem;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-100);
    color: var(--gray-800);
    border: 1px solid var(--gray-200);
  }
  
  .btn-secondary:hover:not(:disabled) {
    background-color: var(--gray-200);
  }
  
  .btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .content-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
  
  @media (min-width: 1024px) {
    .content-container {
      grid-template-columns: 1fr 2fr;
    }
  }
  
  .summary-card, .negative-stocks-card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
  
  .card-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--gray-800);
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem 0;
    color: var(--gray-500);
  }
  
  .loading-indicator i {
    margin-right: 0.5rem;
  }
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
    color: var(--gray-600);
  }
  
  .empty-state i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--success-color);
  }
  
  .empty-state h3 {
    margin: 0 0 0.5rem;
    color: var(--gray-700);
  }
  
  .empty-state p {
    color: var(--gray-500);
    max-width: 20rem;
    margin: 0;
  }
  
  .empty-state.small {
    padding: 2rem 0;
  }
  
  .empty-state.small i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
  }
  
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1.5rem;
  }
  
  .summary-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background-color: var(--gray-50);
    border-radius: 0.5rem;
    text-align: center;
  }
  
  .summary-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--danger-color);
  }
  
  .summary-label {
    font-size: 0.875rem;
    color: var(--gray-600);
  }
  
  .warehouse-summary {
    margin-top: 2rem;
  }
  
  .warehouse-summary h3 {
    font-size: 1rem;
    margin: 0 0 1rem;
    color: var(--gray-700);
  }
  
  .warehouse-summary-table-container {
    overflow-x: auto;
  }
  
  .warehouse-summary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .warehouse-summary-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 500;
    color: var(--gray-700);
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .warehouse-summary-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .warehouse-summary-table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .filters {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: flex-end;
  }
  
  .search-box {
    position: relative;
    flex-grow: 1;
    max-width: 24rem;
  }
  
  .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .search-box input {
    width: 100%;
    padding: 0.625rem 2.5rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .clear-search {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    padding: 0.25rem;
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
    padding: 0.625rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    min-width: 10rem;
  }
  
  .negative-stocks-table-container {
    overflow-x: auto;
  }
  
  .negative-stocks-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .negative-stocks-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 500;
    color: var(--gray-700);
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    position: sticky;
    top: 0;
    z-index: 10;
    cursor: pointer;
  }
  
  .negative-stocks-table th:hover {
    background-color: var(--gray-100);
  }
  
  .negative-stocks-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .negative-stocks-table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .negative-value {
    color: var(--danger-color);
    font-weight: 500;
  }
  
  .actions-cell {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2rem;
    width: 2rem;
    border-radius: 0.375rem;
    color: var(--gray-600);
    background-color: var(--gray-50);
    border: 1px solid var(--gray-200);
    transition: all 0.2s;
    cursor: pointer;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    margin-top: 1rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .pagination-info {
    color: var(--gray-500);
    font-size: 0.875rem;
  }
  
  .pagination-controls {
    display: flex;
    gap: 0.25rem;
  }
  
  .pagination-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    background: none;
    border: 1px solid var(--gray-200);
    color: var(--gray-500);
    cursor: pointer;
  }
  
  .pagination-btn:hover:not(:disabled) {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .pagination-btn.active {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
  }
  
  .pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .pagination-ellipsis {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 2rem;
    height: 2rem;
    color: var(--gray-500);
  }
  
  .modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
  }
  
  .modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
  }
  
  .modal-content {
    position: relative;
    background-color: white;
    border-radius: 0.5rem;
    max-width: 28rem;
    width: 100%;
    z-index: 60;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  }
  
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--gray-800);
  }
  
  .close-btn {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .close-btn:hover {
    color: var(--gray-800);
  }
  
  .modal-body {
    padding: 1.5rem;
  }
  
  .stock-details {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .detail-row {
    display: flex;
    margin-bottom: 0.75rem;
  }
  
  .detail-label {
    width: 6rem;
    font-weight: 500;
    color: var(--gray-700);
    font-size: 0.875rem;
  }
  
  .detail-value {
    flex: 1;
    color: var(--gray-800);
    font-size: 0.875rem;
  }
  
  .correction-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  
  .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .form-group label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .required {
    color: var(--danger-color);
  }
  
  .form-group input[type="number"],
  .form-group input[type="text"] {
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .form-group input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .form-group select {
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    appearance: none;
    background-color: white;
    background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
  }
  
  .form-group select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .form-group textarea {
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    resize: vertical;
  }
  
  .form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .input-hint {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
  }
  
  .success-message, .error-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  
  .success-message i, .error-message i {
    font-size: 3rem;
    margin-bottom: 1rem;
  }
  
  .success-message i {
    color: var(--success-color);
  }
  
  .error-message i {
    color: var(--danger-color);
  }
  
  .success-message p, .error-message p {
    font-size: 1rem;
    color: var(--gray-800);
    margin: 0;
  }
  
  .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--gray-200);
  }
  
  @media (max-width: 768px) {
    .summary-grid {
      grid-template-columns: 1fr;
    }
    
    .filters {
      flex-direction: column;
      align-items: stretch;
    }
    
    .search-box {
      max-width: none;
    }
    
    .pagination {
      flex-direction: column;
      gap: 1rem;
    }
  }
  </style>