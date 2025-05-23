<template>
  <div class="forecast-list-container">
    <div class="page-header">
      <h1>Sales Forecasts</h1>
      <div class="page-actions">
        <router-link 
          to="/sales/forecasts/create" 
          class="btn btn-primary"
        >
          <i class="fas fa-plus"></i>
          New Forecast
        </router-link>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
      <SearchFilter
        v-model="search"
        placeholder="Search forecasts..."
        @search="handleSearch"
      >
        <template #filters>
          <div class="filter-group">
            <label>Customer</label>
            <select v-model="filters.customer_id" @change="loadForecasts" class="form-control">
              <option value="">All Customers</option>
              <option 
                v-for="customer in customers" 
                :key="customer.customer_id" 
                :value="customer.customer_id"
              >
                {{ customer.name }}
              </option>
            </select>
          </div>

          <div class="filter-group">
            <label>Item</label>
            <select v-model="filters.item_id" @change="loadForecasts" class="form-control">
              <option value="">All Items</option>
              <option 
                v-for="item in items" 
                :key="item.item_id" 
                :value="item.item_id"
              >
                {{ item.name }}
              </option>
            </select>
          </div>

          <div class="filter-group">
            <label>Period</label>
            <input 
              type="month" 
              v-model="filters.forecast_period" 
              @change="loadForecasts"
              class="form-control"
            >
          </div>

          <div class="filter-group">
            <label>Source</label>
            <select v-model="filters.forecast_source" @change="loadForecasts" class="form-control">
              <option value="">All Sources</option>
              <option value="Customer">Customer</option>
              <option value="System-Manual">System Manual</option>
              <option value="System-Average">System Average</option>
              <option value="System-Weighted">System Weighted</option>
              <option value="System-Trend">System Trend</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Show All Versions</label>
            <select v-model="filters.all_versions" @change="loadForecasts" class="form-control">
              <option value="false">Current Only</option>
              <option value="true">All Versions</option>
            </select>
          </div>
        </template>

        <template #actions>
          <button @click="clearFilters" class="btn btn-secondary">
            <i class="fas fa-times"></i>
            Clear Filters
          </button>
          <router-link 
            to="/sales/forecasts/analytics" 
            class="btn btn-info"
          >
            <i class="fas fa-chart-line"></i>
            Analytics
          </router-link>
        </template>
      </SearchFilter>
    </div>

    <!-- Forecasts Table -->
    <div class="card">
      <DataTable
        :columns="columns"
        :items="forecasts"
        :is-loading="isLoading"
        :key-field="'forecast_id'"
        empty-title="No Forecasts Found"
        empty-message="No sales forecasts match your current filters."
        @sort="handleSort"
      >
        <template #period="{ item }">
          {{ formatPeriod(item.forecast_period) }}
        </template>

        <template #customer="{ item }">
          <div class="customer-info">
            <div class="customer-name">{{ item.customer?.name }}</div>
            <div class="customer-code">{{ item.customer?.customer_code }}</div>
          </div>
        </template>

        <template #item="{ item }">
          <div class="item-info">
            <div class="item-name">{{ item.item?.name }}</div>
            <div class="item-code">{{ item.item?.item_code }}</div>
          </div>
        </template>

        <template #forecast_quantity="{ item }">
          {{ formatNumber(item.forecast_quantity) }}
        </template>

        <template #actual_quantity="{ item }">
          <span v-if="item.actual_quantity !== null">
            {{ formatNumber(item.actual_quantity) }}
          </span>
          <span v-else class="text-muted">-</span>
        </template>

        <template #variance="{ item }">
          <span 
            v-if="item.variance !== null"
            :class="getVarianceClass(item.variance)"
          >
            {{ formatNumber(item.variance) }}
          </span>
          <span v-else class="text-muted">-</span>
        </template>

        <template #variance_percentage="{ item }">
          <span 
            v-if="item.variance !== null && item.forecast_quantity > 0"
            :class="getVarianceClass(item.variance)"
          >
            {{ formatPercentage(item.variance / item.forecast_quantity) }}
          </span>
          <span v-else class="text-muted">-</span>
        </template>

        <template #source="{ item }">
          <span class="source-badge" :class="getSourceClass(item.forecast_source)">
            {{ item.forecast_source }}
          </span>
        </template>

        <template #confidence="{ item }">
          <div class="confidence-bar">
            <div 
              class="confidence-fill"
              :style="{ width: (item.confidence_level * 100) + '%' }"
            ></div>
            <span class="confidence-text">
              {{ formatPercentage(item.confidence_level) }}
            </span>
          </div>
        </template>

        <template #is_current="{ item }">
          <span 
            class="status-badge"
            :class="item.is_current_version ? 'status-current' : 'status-old'"
          >
            {{ item.is_current_version ? 'Current' : 'Old Version' }}
          </span>
        </template>

        <template #actions="{ item }">
          <div class="action-buttons">
            <router-link 
              :to="`/sales/forecasts/${item.forecast_id}`"
              class="btn btn-sm btn-info"
              title="View Details"
            >
              <i class="fas fa-eye"></i>
            </router-link>
            
            <router-link 
              v-if="item.is_current_version"
              :to="`/sales/forecasts/${item.forecast_id}/edit`"
              class="btn btn-sm btn-warning"
              title="Edit Forecast"
            >
              <i class="fas fa-edit"></i>
            </router-link>
            
            <button 
              v-if="item.is_current_version"
              @click="confirmDelete(item)"
              class="btn btn-sm btn-danger"
              title="Delete Forecast"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </template>
      </DataTable>

      <!-- Pagination -->
      <div v-if="pagination.total > 0" class="card-footer">
        <PaginationComponent
          :current-page="pagination.current_page"
          :total-pages="pagination.last_page"
          :from="pagination.from"
          :to="pagination.to"
          :total="pagination.total"
          @page-changed="handlePageChange"
        />
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <ConfirmationModal
      v-if="showDeleteModal"
      title="Delete Forecast"
      :message="`Are you sure you want to delete the forecast for ${deleteTarget?.item?.name} - ${deleteTarget?.customer?.name}?`"
      confirm-button-text="Delete"
      confirm-button-class="btn btn-danger"
      @confirm="deleteForecast"
      @close="showDeleteModal = false"
    />
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'SalesForecastList',
  data() {
    return {
      forecasts: [],
      customers: [],
      items: [],
      isLoading: false,
      showDeleteModal: false,
      deleteTarget: null,
      search: '',
      filters: {
        customer_id: '',
        item_id: '',
        forecast_period: '',
        forecast_source: '',
        all_versions: 'false'
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        from: 0,
        to: 0,
        total: 0
      },
      sortBy: '',
      sortOrder: 'desc',
      columns: [
        { key: 'forecast_period', label: 'Period', sortable: true, template: 'period' },
        { key: 'customer', label: 'Customer', template: 'customer' },
        { key: 'item', label: 'Item', template: 'item' },
        { key: 'forecast_quantity', label: 'Forecast Qty', sortable: true, template: 'forecast_quantity', class: 'text-right' },
        { key: 'actual_quantity', label: 'Actual Qty', sortable: true, template: 'actual_quantity', class: 'text-right' },
        { key: 'variance', label: 'Variance', sortable: true, template: 'variance', class: 'text-right' },
        { key: 'variance_percentage', label: 'Variance %', template: 'variance_percentage', class: 'text-right' },
        { key: 'forecast_source', label: 'Source', template: 'source' },
        { key: 'confidence_level', label: 'Confidence', template: 'confidence' },
        { key: 'is_current_version', label: 'Version', template: 'is_current' }
      ]
    };
  },
  async mounted() {
    await this.loadDropdownData();
    await this.loadForecasts();
  },
  methods: {
    async loadDropdownData() {
      try {
        const [customersResponse, itemsResponse] = await Promise.all([
          axios.get('/customers'),
          axios.get('/items')
        ]);
        
        this.customers = customersResponse.data.data || customersResponse.data;
        this.items = itemsResponse.data.data || itemsResponse.data;
      } catch (error) {
        console.error('Error loading dropdown data:', error);
        this.$toast?.error('Failed to load filter data');
      }
    },

    async loadForecasts() {
      try {
        this.isLoading = true;
        
        const params = {
          page: this.pagination.current_page,
          search: this.search,
          ...this.filters
        };
        
        if (this.sortBy) {
          params.sort_by = this.sortBy;
          params.sort_order = this.sortOrder;
        }
        
        const response = await axios.get('/forecasts', { params });
        
        if (response.data.data) {
          // Paginated response
          this.forecasts = response.data.data;
          this.pagination = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total
          };
        } else {
          // Simple array response
          this.forecasts = response.data;
          this.pagination = {
            current_page: 1,
            last_page: 1,
            from: 1,
            to: this.forecasts.length,
            total: this.forecasts.length
          };
        }
      } catch (error) {
        console.error('Error loading forecasts:', error);
        this.$toast?.error('Failed to load forecasts');
        this.forecasts = [];
      } finally {
        this.isLoading = false;
      }
    },

    handleSearch(searchTerm) {
      this.search = searchTerm;
      this.pagination.current_page = 1;
      this.loadForecasts();
    },

    handleSort(sortData) {
      this.sortBy = sortData.key;
      this.sortOrder = sortData.order;
      this.loadForecasts();
    },

    handlePageChange(page) {
      this.pagination.current_page = page;
      this.loadForecasts();
    },

    clearFilters() {
      this.search = '';
      this.filters = {
        customer_id: '',
        item_id: '',
        forecast_period: '',
        forecast_source: '',
        all_versions: 'false'
      };
      this.pagination.current_page = 1;
      this.loadForecasts();
    },

    confirmDelete(forecast) {
      this.deleteTarget = forecast;
      this.showDeleteModal = true;
    },

    async deleteForecast() {
      try {
        await axios.delete(`/forecasts/${this.deleteTarget.forecast_id}`);
        this.$toast?.success('Forecast deleted successfully');
        this.showDeleteModal = false;
        this.deleteTarget = null;
        await this.loadForecasts();
      } catch (error) {
        console.error('Error deleting forecast:', error);
        this.$toast?.error('Failed to delete forecast');
      }
    },

    formatPeriod(date) {
      if (!date) return '';
      const d = new Date(date);
      return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
    },

    formatNumber(value) {
      if (value === null || value === undefined) return '';
      return parseFloat(value).toLocaleString('en-US', { 
        minimumFractionDigits: 0, 
        maximumFractionDigits: 2 
      });
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return '';
      return (parseFloat(value) * 100).toFixed(1) + '%';
    },

    getVarianceClass(variance) {
      if (variance > 0) return 'text-success';
      if (variance < 0) return 'text-danger';
      return 'text-muted';
    },

    getSourceClass(source) {
      const classes = {
        'Customer': 'source-customer',
        'System-Manual': 'source-manual',
        'System-Average': 'source-system',
        'System-Weighted': 'source-system',
        'System-Trend': 'source-system'
      };
      return classes[source] || 'source-other';
    }
  }
};
</script>

<style scoped>
.forecast-list-container {
  padding: 2rem;
  max-width: 100%;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.page-header h1 {
  margin: 0;
  color: var(--gray-800);
}

.page-actions {
  display: flex;
  gap: 1rem;
}

.filters-section {
  margin-bottom: 1.5rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 150px;
}

.filter-group label {
  font-size: 0.75rem;
  color: var(--gray-500);
  font-weight: 500;
}

.filter-group select,
.filter-group input {
  padding: 0.5rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background-color: white;
}

.card {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-footer {
  padding: 1rem;
  border-top: 1px solid var(--gray-200);
  background: var(--gray-50);
}

.customer-info,
.item-info {
  display: flex;
  flex-direction: column;
}

.customer-name,
.item-name {
  font-weight: 500;
  color: var(--gray-800);
}

.customer-code,
.item-code {
  font-size: 0.75rem;
  color: var(--gray-500);
}

.source-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.source-customer {
  background-color: #dbeafe;
  color: #1e40af;
}

.source-manual {
  background-color: #f3e8ff;
  color: #7c3aed;
}

.source-system {
  background-color: #ecfdf5;
  color: #059669;
}

.source-other {
  background-color: var(--gray-100);
  color: var(--gray-600);
}

.confidence-bar {
  position: relative;
  width: 60px;
  height: 20px;
  background: var(--gray-200);
  border-radius: 10px;
  overflow: hidden;
}

.confidence-fill {
  height: 100%;
  background: var(--primary-color);
  transition: width 0.3s ease;
}

.confidence-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 0.65rem;
  font-weight: 500;
  color: white;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-current {
  background-color: #dcfce7;
  color: #166534;
}

.status-old {
  background-color: var(--gray-100);
  color: var(--gray-600);
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.btn-secondary {
  background-color: white;
  color: var(--gray-700);
  border-color: var(--gray-300);
}

.btn-info {
  background-color: #0ea5e9;
  color: white;
  border-color: #0ea5e9;
}

.btn-warning {
  background-color: #f59e0b;
  color: white;
  border-color: #f59e0b;
}

.btn-danger {
  background-color: var(--danger-color);
  color: white;
  border-color: var(--danger-color);
}

.btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.text-right {
  text-align: right;
}

.text-success {
  color: var(--success-color);
}

.text-danger {
  color: var(--danger-color);
}

.text-muted {
  color: var(--gray-500);
}

@media (max-width: 768px) {
  .forecast-list-container {
    padding: 1rem;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .page-actions {
    justify-content: flex-end;
  }
}
</style>