<!-- src/views/inventory/ItemStockDetail.vue -->
<template>
    <div class="item-detail">
      <div class="page-header">
        <div class="header-left">
          <router-link to="/item-stocks" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Stock List
          </router-link>
          <h1>Item Stock Details</h1>
        </div>
        <div class="actions">
          <router-link :to="`/item-stocks/transfer?item=${itemId}`" class="btn btn-primary">
            <i class="fas fa-exchange-alt"></i> Transfer Stock
          </router-link>
          <router-link :to="`/item-stocks/adjust?item=${itemId}`" class="btn btn-secondary">
            <i class="fas fa-sliders-h"></i> Adjust Stock
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="loading">
        <i class="fas fa-spinner fa-spin"></i> Loading item data...
      </div>
  
      <div v-else-if="error" class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        {{ error }}
      </div>
  
      <div v-else-if="!item" class="empty-state">
        <i class="fas fa-box-open"></i>
        <h3>Item Not Found</h3>
        <p>The requested item could not be found. Please check the item ID and try again.</p>
      </div>
  
      <div v-else class="content-container">
        <div class="item-info-card">
          <div class="card-header">
            <h2>Item Information</h2>
          </div>
          <div class="card-body">
            <div class="info-grid">
              <div class="info-item">
                <div class="info-label">Item Code</div>
                <div class="info-value">{{ item.item_code }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">Item Name</div>
                <div class="info-value">{{ item.item_name }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">Category</div>
                <div class="info-value">{{ item.category_name || 'N/A' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">Unit of Measure</div>
                <div class="info-value">{{ item.uom_name || 'N/A' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">Total Stock</div>
                <div class="info-value highlighted">{{ item.total_stock.toFixed(2) }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">Stock Status</div>
                <div class="info-value">
                  <span class="status-badge" :class="getStockStatusClass(item)">
                    {{ getStockStatus(item) }}
                  </span>
                </div>
              </div>
              <div class="info-item">
                <div class="info-label">Min Stock Level</div>
                <div class="info-value">{{ item.minimum_stock?.toFixed(2) || 'Not set' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">Max Stock Level</div>
                <div class="info-value">{{ item.maximum_stock?.toFixed(2) || 'Not set' }}</div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="warehouse-stocks-card">
          <div class="card-header">
            <h2>Warehouse Stock Distribution</h2>
          </div>
          <div class="card-body">
            <div v-if="item.warehouse_stocks.length === 0" class="empty-state small">
              <i class="fas fa-warehouse"></i>
              <p>No stock found in any warehouse</p>
            </div>
            <table v-else class="stock-table">
              <thead>
                <tr>
                  <th>Warehouse</th>
                  <th>Quantity</th>
                  <th>Reserved</th>
                  <th>Available</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="stock in item.warehouse_stocks" :key="stock.warehouse_id">
                  <td>{{ stock.warehouse_name }}</td>
                  <td>{{ stock.quantity.toFixed(2) }}</td>
                  <td>{{ stock.reserved_quantity.toFixed(2) }}</td>
                  <td>{{ stock.available_quantity.toFixed(2) }}</td>
                  <td class="actions-cell">
                    <router-link 
                      :to="`/item-stocks/transfer?item=${itemId}&warehouse=${stock.warehouse_id}`" 
                      class="btn-icon" 
                      title="Transfer"
                    >
                      <i class="fas fa-exchange-alt"></i>
                    </router-link>
                    <router-link 
                      :to="`/item-stocks/adjust?item=${itemId}&warehouse=${stock.warehouse_id}`" 
                      class="btn-icon" 
                      title="Adjust"
                    >
                      <i class="fas fa-sliders-h"></i>
                    </router-link>
                    <router-link 
                      :to="`/item-stocks/reserve?item=${itemId}&warehouse=${stock.warehouse_id}`" 
                      class="btn-icon" 
                      title="Reserve"
                    >
                      <i class="fas fa-lock"></i>
                    </router-link>
                    <router-link 
                      :to="`/stock-transactions/items/${itemId}/movement?warehouse=${stock.warehouse_id}`" 
                      class="btn-icon" 
                      title="Movement History"
                    >
                      <i class="fas fa-history"></i>
                    </router-link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
  
        <div class="transactions-card">
          <div class="card-header">
            <h2>Recent Transactions</h2>
            <router-link :to="`/stock-transactions/items/${itemId}/movement`" class="view-all-link">
              View All <i class="fas fa-chevron-right"></i>
            </router-link>
          </div>
          <div class="card-body">
            <div v-if="transactions.length === 0" class="empty-state small">
              <i class="fas fa-exchange-alt"></i>
              <p>No recent transactions found</p>
            </div>
            <table v-else class="transactions-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Type</th>
                  <th>Warehouse</th>
                  <th>Quantity</th>
                  <th>Reference</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(tx, index) in transactions" :key="index">
                  <td>{{ formatDate(tx.transaction_date) }}</td>
                  <td>
                    <span class="transaction-type" :class="getTransactionTypeClass(tx)">
                      {{ formatTransactionType(tx.transaction_type) }}
                    </span>
                  </td>
                  <td>{{ tx.warehouse_name }}</td>
                  <td :class="{'quantity-positive': tx.quantity > 0, 'quantity-negative': tx.quantity < 0}">
                    {{ tx.quantity > 0 ? '+' : '' }}{{ tx.quantity.toFixed(2) }}
                  </td>
                  <td>{{ tx.reference_document || 'N/A' }} {{ tx.reference_number ? `#${tx.reference_number}` : '' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'ItemStockDetail',
    props: {
      itemId: {
        type: [String, Number],
        required: true
      }
    },
    data() {
      return {
        item: null,
        transactions: [],
        loading: true,
        error: null
      };
    },
    created() {
      this.fetchItemStockData();
      this.fetchRecentTransactions();
    },
    methods: {
      async fetchItemStockData() {
        this.loading = true;
        this.error = null;
        
        try {
          const response = await axios.get(`/item-stocks/item/${this.itemId}`);
          
          if (response.data && response.data.data) {
            this.item = response.data.data;
            
            // Ensure all warehouse stocks have available_quantity
            if (this.item.warehouse_stocks) {
              this.item.warehouse_stocks = this.item.warehouse_stocks.map(stock => ({
                ...stock,
                available_quantity: stock.quantity - stock.reserved_quantity
              }));
            }
          } else {
            this.item = null;
          }
        } catch (err) {
          console.error('Error fetching item stock data:', err);
          this.error = 'Failed to load item stock data. Please try again later.';
        } finally {
          this.loading = false;
        }
      },
      async fetchRecentTransactions() {
        try {
          const response = await axios.get(`/transactions/items/${this.itemId}/movement?limit=5`);
          
          if (response.data && response.data.data) {
            this.transactions = response.data.data.map(tx => ({
              ...tx,
              warehouse_name: tx.warehouse?.name || 'Unknown Warehouse'
            }));
          } else {
            this.transactions = [];
          }
        } catch (err) {
          console.error('Error fetching transactions:', err);
          this.transactions = [];
        }
      },
      getStockStatus(item) {
        // Get the item's minimum and maximum stock levels
        const minStock = item.minimum_stock || 0;
        const maxStock = item.maximum_stock || Infinity;
        
        if (item.total_stock <= 0) {
          return 'Out of Stock';
        } else if (item.total_stock < minStock) {
          return 'Low Stock';
        } else if (item.total_stock > maxStock && maxStock !== Infinity) {
          return 'High Stock';
        } else {
          return 'Normal';
        }
      },
      getStockStatusClass(item) {
        const status = this.getStockStatus(item);
        
        switch(status) {
          case 'Out of Stock': return 'status-danger';
          case 'Low Stock': return 'status-warning';
          case 'High Stock': return 'status-info';
          default: return 'status-success';
        }
      },
      formatTransactionType(type) {
        switch(type) {
          case 'receive': return 'Received';
          case 'issue': return 'Issued';
          case 'transfer': return 'Transfer';
          case 'adjustment': return 'Adjustment';
          case 'return': return 'Return';
          default: return type.charAt(0).toUpperCase() + type.slice(1);
        }
      },
      getTransactionTypeClass(tx) {
        const quantity = tx.quantity;
        
        if (quantity > 0) {
          return 'type-positive';
        } else if (quantity < 0) {
          return 'type-negative';
        } else {
          return '';
        }
      },
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      }
    }
  };
  </script>
  
  <style scoped>
  .item-detail {
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
    text-decoration: none;
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
  
  .empty-state.small {
    padding: 2rem;
  }
  
  .empty-state.small i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
  }
  
  .empty-state.small p {
    margin: 0;
  }
  
  .content-container {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.5rem;
  }
  
  .item-info-card, .warehouse-stocks-card, .transactions-card {
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
  
  .view-all-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.875rem;
  }
  
  .view-all-link:hover {
    text-decoration: underline;
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
  }
  
  .info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .info-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
  }
  
  .info-value {
    font-size: 0.875rem;
    color: var(--gray-800);
  }
  
  .info-value.highlighted {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary-color);
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
  
  .stock-table, .transactions-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .stock-table th, .transactions-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 500;
    color: var(--gray-700);
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .stock-table td, .transactions-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .stock-table tbody tr:hover, .transactions-table tbody tr:hover {
    background-color: var(--gray-50);
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
  
  .transaction-type {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .type-positive {
    background-color: rgba(5, 150, 105, 0.1);
    color: var(--success-color);
  }
  
  .type-negative {
    background-color: rgba(220, 38, 38, 0.1);
    color: var(--danger-color);
  }
  
  .quantity-positive {
    color: var(--success-color);
    font-weight: 500;
  }
  
  .quantity-negative {
    color: var(--danger-color);
    font-weight: 500;
  }
  
  @media (min-width: 1024px) {
    .content-container {
      grid-template-columns: repeat(2, 1fr);
    }
    
    .item-info-card {
      grid-column: 1 / -1;
    }
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      gap: 1rem;
    }
    
    .actions {
      width: 100%;
    }
    
    .info-grid {
      grid-template-columns: 1fr;
      gap: 1rem;
    }
    
    .stock-table, .transactions-table {
      display: block;
      overflow-x: auto;
    }
  }
  </style>