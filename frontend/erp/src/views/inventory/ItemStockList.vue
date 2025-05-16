<!-- src/views/inventory/ItemStockList.vue -->
<template>
    <div class="item-stock-list">
      <div class="page-header">
        <h1>Inventory Stock Management</h1>
        <div class="actions">
          <router-link to="/item-stocks/transfer" class="btn btn-primary">
            <i class="fas fa-exchange-alt"></i> Transfer Stock
          </router-link>
          <router-link to="/item-stocks/adjust" class="btn btn-secondary">
            <i class="fas fa-sliders-h"></i> Adjust Stock
          </router-link>
          <router-link to="/item-stocks/negative" class="btn btn-warning">
            <i class="fas fa-exclamation-triangle"></i> View Negative Stocks
          </router-link>
        </div>
      </div>
  
      <div class="filters">
        <div class="search-box">
          <i class="fas fa-search search-icon"></i>
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Search by item code or name" 
            @input="searchItems"
          />
          <button v-if="searchQuery" @click="clearSearch" class="clear-search">
            <i class="fas fa-times"></i>
          </button>
        </div>
  
        <div class="filter-group">
          <label for="warehouseFilter">Warehouse:</label>
          <select id="warehouseFilter" v-model="warehouseFilter" @change="filterByWarehouse">
            <option value="">All Warehouses</option>
            <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
              {{ warehouse.name }}
            </option>
          </select>
        </div>
  
        <div class="filter-group">
          <label for="stockFilter">Stock Level:</label>
          <select id="stockFilter" v-model="stockFilter" @change="filterByStockLevel">
            <option value="">All</option>
            <option value="low">Low Stock</option>
            <option value="normal">Normal</option>
            <option value="high">High Stock</option>
          </select>
        </div>
      </div>
  
      <div v-if="loading" class="loading">
        <i class="fas fa-spinner fa-spin"></i> Loading inventory data...
      </div>
  
      <div v-else-if="error" class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        {{ error }}
      </div>
  
      <div v-else-if="filteredStocks.length === 0" class="empty-state">
        <i class="fas fa-box-open"></i>
        <h3>No Stock Items Found</h3>
        <p>Try adjusting your search or filter criteria.</p>
      </div>
  
      <div v-else class="stock-table-container">
        <table class="stock-table">
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
              <th @click="sortBy('reserved_quantity')">
                Reserved
                <i v-if="sortKey === 'reserved_quantity'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('available_quantity')">
                Available
                <i v-if="sortKey === 'available_quantity'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="stock in paginatedStocks" :key="`${stock.item_id}-${stock.warehouse_id}`">
              <td>{{ stock.item_code }}</td>
              <td>{{ stock.item_name }}</td>
              <td>{{ stock.warehouse_name }}</td>
              <td>{{ stock.quantity.toFixed(2) }}</td>
              <td>{{ stock.reserved_quantity.toFixed(2) }}</td>
              <td>{{ stock.available_quantity.toFixed(2) }}</td>
              <td>
                <span class="status-badge" :class="getStockStatusClass(stock)">
                  {{ getStockStatus(stock) }}
                </span>
              </td>
              <td class="actions-cell">
                <router-link :to="`/item-stocks/item/${stock.item_id}`" class="btn-icon" title="View Details">
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link :to="`/item-stocks/transfer?item=${stock.item_id}&warehouse=${stock.warehouse_id}`" class="btn-icon" title="Transfer">
                  <i class="fas fa-exchange-alt"></i>
                </router-link>
                <router-link :to="`/item-stocks/adjust?item=${stock.item_id}&warehouse=${stock.warehouse_id}`" class="btn-icon" title="Adjust">
                  <i class="fas fa-sliders-h"></i>
                </router-link>
                <router-link :to="`/item-stocks/reserve?item=${stock.item_id}&warehouse=${stock.warehouse_id}`" class="btn-icon" title="Reserve">
                  <i class="fas fa-lock"></i>
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
  
        <div class="pagination">
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
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'ItemStockList',
    data() {
      return {
        stocks: [],
        warehouses: [],
        loading: true,
        error: null,
        searchQuery: '',
        warehouseFilter: '',
        stockFilter: '',
        sortKey: 'item_code',
        sortOrder: 'asc',
        currentPage: 1,
        itemsPerPage: 10
      };
    },
    created() {
      this.fetchData();
      this.fetchWarehouses();
    },
    computed: {
      filteredStocks() {
        let result = this.stocks;
        
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
        
        // Stock level filter
        if (this.stockFilter) {
          switch(this.stockFilter) {
            case 'low':
              result = result.filter(stock => this.getStockStatus(stock) === 'Low Stock');
              break;
            case 'normal':
              result = result.filter(stock => this.getStockStatus(stock) === 'Normal');
              break;
            case 'high':
              result = result.filter(stock => this.getStockStatus(stock) === 'High Stock');
              break;
          }
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
      }
    },
    methods: {
      async fetchData() {
        this.loading = true;
        this.error = null;
        
        try {
          // Get all stocks first
          const response = await axios.get('/item-stocks');
          
          if (response.data && response.data.data) {
            // Transform the data to include available_quantity
            this.stocks = response.data.data.map(stock => ({
              ...stock,
              available_quantity: stock.quantity - stock.reserved_quantity,
              // Make sure relevant fields are included
              item_id: stock.item_id,
              item_code: stock.item?.item_code || 'N/A',
              item_name: stock.item?.name || 'Unknown Item',
              warehouse_id: stock.warehouse_id,
              warehouse_name: stock.warehouse?.name || 'Unknown Warehouse'
            }));
          } else {
            this.stocks = [];
          }
        } catch (err) {
          console.error('Error fetching stock data:', err);
          this.error = 'Failed to load inventory data. Please try again later.';
        } finally {
          this.loading = false;
        }
      },
      async fetchWarehouses() {
        try {
          const response = await axios.get('/warehouses');
          if (response.data && response.data.data) {
            this.warehouses = response.data.data;
          }
        } catch (err) {
          console.error('Error fetching warehouses:', err);
        }
      },
      searchItems() {
        this.currentPage = 1; // Reset to first page when searching
      },
      clearSearch() {
        this.searchQuery = '';
        this.searchItems();
      },
      filterByWarehouse() {
        this.currentPage = 1; // Reset to first page when filtering
      },
      filterByStockLevel() {
        this.currentPage = 1; // Reset to first page when filtering
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
      getStockStatus(stock) {
        // Get the item's minimum and maximum stock levels
        const minStock = stock.item?.minimum_stock || 0;
        const maxStock = stock.item?.maximum_stock || Infinity;
        
        if (stock.quantity <= 0) {
          return 'Out of Stock';
        } else if (stock.quantity < minStock) {
          return 'Low Stock';
        } else if (stock.quantity > maxStock && maxStock !== Infinity) {
          return 'High Stock';
        } else {
          return 'Normal';
        }
      },
      getStockStatusClass(stock) {
        const status = this.getStockStatus(stock);
        
        switch(status) {
          case 'Out of Stock': return 'status-danger';
          case 'Low Stock': return 'status-warning';
          case 'High Stock': return 'status-info';
          default: return 'status-success';
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .item-stock-list {
    margin-bottom: 2rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
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
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-100);
    color: var(--gray-800);
    border: 1px solid var(--gray-200);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-200);
  }
  
  .btn-warning {
    background-color: var(--warning-color);
    color: white;
  }
  
  .btn-warning:hover {
    background-color: #b45309;
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
  
  .loading, .error-message, .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    text-align: center;
    color: var(--gray-600);
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .loading i, .error-message i, .empty-state i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
  }
  
  .error-message {
    color: var(--danger-color);
  }
  
  .empty-state h3 {
    margin: 0 0 0.5rem;
    color: var(--gray-700);
  }
  
  .empty-state p {
    color: var(--gray-500);
    max-width: 20rem;
  }
  
  .stock-table-container {
    overflow-x: auto;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .stock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .stock-table th {
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
  
  .stock-table th:hover {
    background-color: var(--gray-100);
  }
  
  .stock-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .stock-table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .status-success {
    background-color: rgba(5, 150, 105, 0.1);
    color: var(--success-color);
  }
  
  .status-warning {
    background-color: rgba(217, 119, 6, 0.1);
    color: var(--warning-color);
  }
  
  .status-danger {
    background-color: rgba(220, 38, 38, 0.1);
    color: var(--danger-color);
  }
  
  .status-info {
    background-color: rgba(37, 99, 235, 0.1);
    color: var(--primary-color);
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
  }
  
  .btn-icon:hover {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
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
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .actions {
      width: 100%;
      justify-content: flex-start;
    }
    
    .filters {
      flex-direction: column;
      align-items: stretch;
      gap: 1rem;
    }
    
    .search-box {
      max-width: none;
    }
    
    .filter-group {
      width: 100%;
    }
    
    .filter-group select {
      width: 100%;
    }
    
    .pagination {
      flex-direction: column;
      gap: 1rem;
    }
  }
  </style>