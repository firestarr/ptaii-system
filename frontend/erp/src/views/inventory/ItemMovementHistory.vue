<!-- src/views/inventory/ItemMovementHistory.vue -->
<template>
  <div class="item-movement-history">
    <div class="page-header">
      <div class="header-content">
        <h1>Item Movement History</h1>
        <div v-if="item" class="item-details">
          <div class="item-code">{{ item.item_code }}</div>
          <div class="item-name">{{ item.name }}</div>
        </div>
      </div>
      <div class="page-actions">
        <router-link to="/stock-transactions" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to Transactions
        </router-link>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
      </div>
      <p>Loading item movement data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <p>{{ error }}</p>
      <button @click="fetchItem" class="btn btn-primary">
        <i class="fas fa-sync"></i> Try Again
      </button>
    </div>

    <!-- Content -->
    <div v-else-if="item" class="movement-content">
      <div class="row">
        <!-- Left Column - Primary Info -->
        <div class="col-lg-4">
          <div class="card mb-4">
            <div class="card-header">
              <h3 class="card-title">Item Details</h3>
              <router-link :to="`/items/${itemId}`" class="btn-text">
                <i class="fas fa-external-link-alt"></i> View Item
              </router-link>
            </div>
            <div class="card-body">
              <div class="detail-item">
                <div class="detail-label">Item Code</div>
                <div class="detail-value">{{ item.item_code }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Name</div>
                <div class="detail-value">{{ item.name }}</div>
              </div>
              <div class="detail-item" v-if="item.category">
                <div class="detail-label">Category</div>
                <div class="detail-value">{{ item.category.name }}</div>
              </div>
              <div class="detail-item" v-if="item.unitOfMeasure">
                <div class="detail-label">Unit of Measure</div>
                <div class="detail-value">
                  {{ item.unitOfMeasure.name }} ({{ item.unitOfMeasure.symbol }})
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Current Stock</div>
                <div class="detail-value" :class="getStockStatusClass(item)">
                  {{ item.current_stock }}
                  <span v-if="item.unitOfMeasure" class="unit-symbol">
                    {{ item.unitOfMeasure.symbol }}
                  </span>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Minimum Stock</div>
                <div class="detail-value">
                  {{ item.minimum_stock }}
                  <span v-if="item.unitOfMeasure" class="unit-symbol">
                    {{ item.unitOfMeasure.symbol }}
                  </span>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Maximum Stock</div>
                <div class="detail-value">
                  {{ item.maximum_stock }}
                  <span v-if="item.unitOfMeasure" class="unit-symbol">
                    {{ item.unitOfMeasure.symbol }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header">
              <h3 class="card-title">Filters</h3>
            </div>
            <div class="card-body">
              <form @submit.prevent="applyFilters">
                <div class="form-group">
                  <label for="start-date">From Date</label>
                  <input
                    type="date"
                    id="start-date"
                    v-model="filters.start_date"
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label for="end-date">To Date</label>
                  <input
                    type="date"
                    id="end-date"
                    v-model="filters.end_date"
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label for="warehouse-filter">Warehouse</label>
                  <div class="select-with-status">
                    <select 
                      id="warehouse-filter" 
                      v-model="filters.warehouse_id"
                      class="form-control"
                    >
                      <option value="">All Warehouses</option>
                      <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                        {{ warehouse.name }}
                      </option>
                    </select>
                    <div v-if="warehousesLoading" class="status-indicator">
                      <i class="fas fa-spinner fa-spin"></i>
                    </div>
                  </div>
                </div>
                <div class="form-actions">
                  <button type="button" class="btn btn-secondary" @click="resetFilters">
                    Reset
                  </button>
                  <button type="submit" class="btn btn-primary">
                    Apply Filters
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Movement Summary</h3>
            </div>
            <div class="card-body">
              <div class="summary-grid">
                <div class="summary-item">
                  <div class="summary-label">Total In</div>
                  <div class="summary-value text-success">
                    {{ totalIn }}
                    <span v-if="item.unitOfMeasure" class="unit-symbol">
                      {{ item.unitOfMeasure.symbol }}
                    </span>
                  </div>
                </div>
                <div class="summary-item">
                  <div class="summary-label">Total Out</div>
                  <div class="summary-value text-danger">
                    {{ totalOut }}
                    <span v-if="item.unitOfMeasure" class="unit-symbol">
                      {{ item.unitOfMeasure.symbol }}
                    </span>
                  </div>
                </div>
                <div class="summary-item">
                  <div class="summary-label">Net Change</div>
                  <div class="summary-value" :class="getQuantityClass(netChange)">
                    {{ netChange }}
                    <span v-if="item.unitOfMeasure" class="unit-symbol">
                      {{ item.unitOfMeasure.symbol }}
                    </span>
                  </div>
                </div>
                <div class="summary-item">
                  <div class="summary-label">Transactions</div>
                  <div class="summary-value">{{ transactions.length }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column - Movement Chart and Transactions -->
        <div class="col-lg-8">
          <div class="card mb-4">
            <div class="card-header">
              <h3 class="card-title">Stock Movement Chart</h3>
              <div class="chart-controls">
                <select v-model="chartType" class="form-control form-control-sm">
                  <option value="line">Line Chart</option>
                  <option value="bar">Bar Chart</option>
                </select>
              </div>
            </div>
            <div class="card-body chart-container">
              <div v-if="transactions.length === 0" class="empty-chart-message">
                <p>No transaction data available for the selected period.</p>
              </div>
              <div v-else ref="chartContainer" class="chart-canvas-container">
                <canvas ref="movementChart"></canvas>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Transaction History</h3>
              <div class="page-info" v-if="totalItems > 0">
                Showing {{ transactions.length }} of {{ totalItems }} transactions
              </div>
            </div>
            <div class="card-body p-0" v-if="transactions.length > 0">
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Transaction</th>
                      <th>Type</th>
                      <th>Warehouse</th>
                      <th class="text-right">Quantity</th>
                      <th class="text-right">Balance</th>
                      <th class="text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="transaction in transactions" :key="transaction.transaction_id">
                      <td>{{ formatDate(transaction.transaction_date) }}</td>
                      <td>
                        <router-link :to="`/stock-transactions/${transaction.transaction_id}`" class="font-weight-bold">
                          #{{ transaction.transaction_id }}
                        </router-link>
                        <div v-if="transaction.reference_document || transaction.reference_number" class="text-muted small">
                          {{ transaction.reference_document || '' }}
                          {{ transaction.reference_document && transaction.reference_number ? ' - ' : '' }}
                          {{ transaction.reference_number || '' }}
                        </div>
                      </td>
                      <td>
                        <span class="badge" :class="getTransactionTypeClass(transaction.transaction_type)">
                          {{ formatTransactionType(transaction.transaction_type) }}
                        </span>
                      </td>
                      <td>
                        <div v-if="transaction.warehouse">
                          {{ transaction.warehouse.name }}
                          <!-- Location removed because backend no longer supports location relationship -->
                          <!-- <div v-if="transaction.location" class="small text-muted">
                            Location: {{ transaction.location.code }}
                          </div> -->
                        </div>
                        <div v-else class="text-muted">--</div>
                      </td>
                      <td class="text-right">
                        <span :class="getQuantityClass(transaction.quantity)">
                          {{ transaction.quantity }}
                          <span v-if="item.unitOfMeasure" class="unit-symbol">
                            {{ item.unitOfMeasure.symbol }}
                          </span>
                        </span>
                      </td>
                      <td class="text-right">
                        <span :class="getQuantityClass(transaction.running_balance)">
                          {{ transaction.running_balance }}
                          <span v-if="item.unitOfMeasure" class="unit-symbol">
                            {{ item.unitOfMeasure.symbol }}
                          </span>
                        </span>
                      </td>
                      <td class="text-right">
                        <router-link :to="`/stock-transactions/${transaction.transaction_id}`" class="btn-sm btn-outline-primary">
                          <i class="fas fa-eye"></i> View
                        </router-link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Pagination -->
              <div v-if="totalPages > 1" class="pagination-container">
                <nav aria-label="Transaction pagination">
                  <ul class="pagination">
                    <li class="page-item" :class="{ 'disabled': currentPage === 1 }">
                      <button 
                        class="page-link" 
                        @click="changePage(currentPage - 1)" 
                        :disabled="currentPage === 1"
                      >
                        <i class="fas fa-chevron-left"></i>
                      </button>
                    </li>
                    <li 
                      v-for="page in displayedPages" 
                      :key="page" 
                      class="page-item" 
                      :class="{ 'active': currentPage === page }"
                    >
                      <button 
                        class="page-link" 
                        @click="changePage(page)"
                      >
                        {{ page }}
                      </button>
                    </li>
                    <li class="page-item" :class="{ 'disabled': currentPage === totalPages }">
                      <button 
                        class="page-link" 
                        @click="changePage(currentPage + 1)" 
                        :disabled="currentPage === totalPages"
                      >
                        <i class="fas fa-chevron-right"></i>
                      </button>
                    </li>
                  </ul>
                </nav>
              </div>
            </div>
            <div v-else class="card-body">
              <div class="empty-state">
                <i class="fas fa-exchange-alt empty-icon"></i>
                <p>No transactions found for this item.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import Chart from 'chart.js/auto';

export default {
  name: 'ItemMovementHistory',
  setup() {
    const route = useRoute();
    
    // Route params
    const itemId = computed(() => route.params.itemId);
    
    // Chart references
    const chartContainer = ref(null);
    const movementChart = ref(null);
    let chart = null;
    
    // Data
    const item = ref(null);
    const transactions = ref([]);
    const warehouses = ref([]);
    const loading = ref(true);
    const error = ref(null);
    const warehousesLoading = ref(false);
    
    // Filters
    const filters = ref({
      start_date: '',
      end_date: '',
      warehouse_id: '',
    });
    
    // Chart options
    const chartType = ref('line');
    
    // Pagination
    const currentPage = ref(1);
    const totalPages = ref(1);
    const totalItems = ref(0);
    const perPage = ref(25);
    
    // Computed properties
    const totalIn = computed(() => {
      return transactions.value
        .filter(t => t.quantity > 0)
        .reduce((total, t) => total + t.quantity, 0)
        .toFixed(2);
    });
    
    const totalOut = computed(() => {
      return Math.abs(
        transactions.value
          .filter(t => t.quantity < 0)
          .reduce((total, t) => total + t.quantity, 0)
      ).toFixed(2);
    });
    
    const netChange = computed(() => {
      return transactions.value
        .reduce((total, t) => total + t.quantity, 0)
        .toFixed(2);
    });
    
    const displayedPages = computed(() => {
      if (totalPages.value <= 7) {
        return Array.from({ length: totalPages.value }, (_, i) => i + 1);
      }
      
      if (currentPage.value <= 4) {
        return [1, 2, 3, 4, 5, '...', totalPages.value];
      }
      
      if (currentPage.value >= totalPages.value - 3) {
        return [1, '...', totalPages.value - 4, totalPages.value - 3, totalPages.value - 2, totalPages.value - 1, totalPages.value];
      }
      
      return [1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value];
    });
    
    // Methods
    const fetchItem = async () => {
      loading.value = true;
      error.value = null;
      
      try {
        const response = await axios.get(`/items/${itemId.value}`);
        item.value = response.data.data;
        
        // After fetching the item, fetch its movement history
        fetchItemMovement();
        fetchWarehouses();
      } catch (err) {
        console.error('Error fetching item:', err);
        error.value = 'Failed to load item details. Please try again.';
        loading.value = false;
      }
    };
    
    const fetchItemMovement = async () => {
      try {
        const params = {
          page: currentPage.value,
          per_page: perPage.value
        };
        
        // Add filters if set
        if (filters.value.start_date) params.start_date = filters.value.start_date;
        if (filters.value.end_date) params.end_date = filters.value.end_date;
        if (filters.value.warehouse_id) params.warehouse_id = filters.value.warehouse_id;
        
        const response = await axios.get(`/transactions/items/${itemId.value}/movement`, { params });
        transactions.value = response.data.data.transactions.data;
        totalPages.value = Math.ceil(response.data.data.transactions.total / response.data.data.transactions.per_page);
        totalItems.value = response.data.data.transactions.total;
        currentPage.value = response.data.data.transactions.current_page;
        
        // After getting transactions, render chart
        nextTick(() => {
          renderChart();
        });
      } catch (err) {
        console.error('Error fetching item movement:', err);
        error.value = 'Failed to load movement history. Please try again.';
      } finally {
        loading.value = false;
      }
    };
    
    const fetchWarehouses = async () => {
      warehousesLoading.value = true;
      try {
        const response = await axios.get('/warehouses');
        warehouses.value = response.data.data;
      } catch (err) {
        console.error('Error fetching warehouses:', err);
      } finally {
        warehousesLoading.value = false;
      }
    };
    
    const renderChart = () => {
      if (!chartContainer.value || transactions.value.length === 0) return;
      
      const ctx = movementChart.value.getContext('2d');
      
      // Destroy previous chart if exists
      if (chart) {
        chart.destroy();
      }
      
      // Prepare data for chart
      const dates = transactions.value.map(t => formatDate(t.transaction_date));
      const runningBalances = transactions.value.map(t => t.running_balance);
      const quantities = transactions.value.map(t => t.quantity);
      
      // Create new chart
      chart = new Chart(ctx, {
        type: chartType.value,
        data: {
          labels: dates,
          datasets: [
            {
              label: 'Running Balance',
              data: runningBalances,
              borderColor: 'rgb(75, 192, 192)',
              backgroundColor: 'rgba(75, 192, 192, 0.2)',
              borderWidth: 2,
              tension: 0.1,
              yAxisID: 'y',
              fill: true
            },
            {
              label: 'Transaction Quantity',
              data: quantities,
              borderColor: 'rgb(153, 102, 255)',
              backgroundColor: function(context) {
                const index = context.dataIndex;
                const value = context.dataset.data[index];
                return value >= 0 ? 'rgba(75, 192, 100, 0.5)' : 'rgba(255, 99, 132, 0.5)';
              },
              borderWidth: 1,
              yAxisID: 'y1',
              type: 'bar'
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: {
              title: {
                display: true,
                text: 'Date'
              },
              reverse: true // Show most recent data on the right
            },
            y: {
              title: {
                display: true,
                text: 'Running Balance'
              },
              position: 'left',
            },
            y1: {
              title: {
                display: true,
                text: 'Transaction Quantity'
              },
              position: 'right',
              grid: {
                drawOnChartArea: false,
              },
            }
          },
          interaction: {
            mode: 'index',
            intersect: false,
          },
        }
      });
    };
    
    const applyFilters = () => {
      currentPage.value = 1;
      fetchItemMovement();
    };
    
    const resetFilters = () => {
      filters.value = {
        start_date: '',
        end_date: '',
        warehouse_id: '',
      };
      currentPage.value = 1;
      fetchItemMovement();
    };
    
    const changePage = (page) => {
      if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
        currentPage.value = page;
        fetchItemMovement();
      }
    };
    
    const formatDate = (dateString) => {
      if (!dateString) return '--';
      
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    };
    
    const formatTransactionType = (type) => {
      if (!type) return '--';
      
      const types = {
        'receive': 'Receive',
        'issue': 'Issue',
        'transfer': 'Transfer',
        'adjustment': 'Adjustment',
        'return': 'Return'
      };
      
      return types[type.toLowerCase()] || type;
    };
    
    const getTransactionTypeClass = (type) => {
      if (!type) return '';
      
      const typeClasses = {
        'receive': 'badge-success',
        'issue': 'badge-danger',
        'transfer': 'badge-warning',
        'adjustment': 'badge-secondary',
        'return': 'badge-info'
      };
      
      return typeClasses[type.toLowerCase()] || 'badge-secondary';
    };
    
    const getQuantityClass = (quantity) => {
      if (!quantity) return '';
      
      if (quantity > 0) {
        return 'text-success';
      } else if (quantity < 0) {
        return 'text-danger';
      }
      
      return '';
    };
    
    const getStockStatusClass = (item) => {
      if (!item) return '';
      
      if (item.current_stock <= 0) {
        return 'text-danger';
      } else if (item.current_stock <= item.minimum_stock) {
        return 'text-warning';
      } else {
        return 'text-success';
      }
    };
    
    // Watch for chart type changes
    watch(chartType, () => {
      nextTick(() => {
        renderChart();
      });
    });
    
    // Lifecycle hooks
    onMounted(() => {
      fetchItem();
    });
    
    return {
      itemId,
      item,
      transactions,
      warehouses,
      loading,
      error,
      warehousesLoading,
      filters,
      chartType,
      chartContainer,
      movementChart,
      currentPage,
      totalPages,
      totalItems,
      totalIn,
      totalOut,
      netChange,
      displayedPages,
      fetchItem,
      fetchItemMovement,
      applyFilters,
      resetFilters,
      changePage,
      formatDate,
      formatTransactionType,
      getTransactionTypeClass,
      getQuantityClass,
      getStockStatusClass
    };
  }
};
</script>

<style scoped>
.item-movement-history {
  padding: 1rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.header-content h1 {
  margin: 0;
}

.item-details {
  margin-top: 0.5rem;
}

.item-code {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

.item-name {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-800);
}

.page-actions {
  display: flex;
  gap: 0.75rem;
}

.loading-container, .error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 1.5rem;
}

.loading-spinner, .error-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.error-icon {
  color: var(--danger-color);
}

.row {
  display: flex;
  flex-wrap: wrap;
  margin-right: -0.75rem;
  margin-left: -0.75rem;
}

.col-lg-4 {
  flex: 0 0 33.333333%;
  max-width: 33.333333%;
  padding-right: 0.75rem;
  padding-left: 0.75rem;
}

.col-lg-8 {
  flex: 0 0 66.666667%;
  max-width: 66.666667%;
  padding-right: 0.75rem;
  padding-left: 0.75rem;
}

.card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 1.5rem;
}

.card-header {
  padding: 1rem;
  border-bottom: 1px solid var(--gray-200);
  background-color: var(--gray-50);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-800);
}

.card-body {
  padding: 1rem;
}

.card-body.p-0 {
  padding: 0;
}

.detail-item {
  margin-bottom: 0.75rem;
}

.detail-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

.detail-value {
  color: var(--gray-800);
}

.unit-symbol {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin-left: 0.25rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--gray-700);
}

.form-control {
  width: 100%;
  padding: 0.5rem 0.75rem;
  font-size: 1rem;
  line-height: 1.5;
  color: var(--gray-900);
  background-color: white;
  border: 1px solid var(--gray-300);
  border-radius: 0.375rem;
}

.form-control-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1rem;
}

.select-with-status {
  position: relative;
}

.status-indicator {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-500);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.summary-item {
  margin-bottom: 0.75rem;
}

.summary-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

.summary-value {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-800);
}

.chart-container {
  padding: 1rem;
  height: 400px;
}

.chart-canvas-container {
  height: 100%;
  width: 100%;
}

.chart-controls {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.empty-chart-message {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100%;
  color: var(--gray-500);
}

.page-info {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  text-align: center;
  color: var(--gray-500);
}

.empty-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
  color: var(--gray-400);
}

.pagination-container {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 1rem;
  background-color: var(--gray-50);
  border-top: 1px solid var(--gray-200);
}

.pagination {
  display: flex;
  list-style-type: none;
  margin: 0;
  padding: 0;
}

.page-item {
  margin: 0 0.125rem;
}

.page-link {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 2rem;
  height: 2rem;
  padding: 0 0.5rem;
  border: 1px solid var(--gray-300);
  border-radius: 0.25rem;
  background-color: white;
  color: var(--gray-700);
  cursor: pointer;
}

.page-item.active .page-link {
  background-color: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.page-item.disabled .page-link {
  color: var(--gray-400);
  cursor: not-allowed;
}

.badge {
  display: inline-block;
  padding: 0.35em 0.65em;
  font-size: 0.75em;
  font-weight: 700;
  line-height: 1;
  text-align: center;
  white-space: nowrap;
  vertical-align: baseline;
  border-radius: 0.375rem;
}

.badge-success {
  background-color: var(--success-light);
  color: var(--success-color);
}

.badge-danger {
  background-color: var(--danger-light);
  color: var(--danger-color);
}

.badge-warning {
  background-color: var(--warning-light);
  color: var(--warning-color);
}

.badge-info {
  background-color: var(--primary-bg);
  color: var(--primary-color);
}

.badge-secondary {
  background-color: var(--gray-200);
  color: var(--gray-700);
}

.btn-text {
  background: none;
  border: none;
  color: var(--primary-color);
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  display: inline-flex;
  align-items: center;
}

.btn-text:hover {
  text-decoration: underline;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  border-radius: 0.25rem;
}

.btn-outline-primary {
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
  background-color: transparent;
}

.btn-outline-primary:hover {
  color: white;
  background-color: var(--primary-color);
}

.text-muted {
  color: var(--gray-600);
}

.text-success {
  color: var(--success-color);
}

.text-danger {
  color: var(--danger-color);
}

.text-warning {
  color: var(--warning-color);
}

.mb-4 {
  margin-bottom: 1.5rem;
}

/* Responsive styles */
@media (max-width: 992px) {
  .col-lg-4, .col-lg-8 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .chart-container {
    height: 300px;
  }
}

@media (max-width: 768px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>