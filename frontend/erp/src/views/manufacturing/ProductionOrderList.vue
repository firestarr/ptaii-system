<!-- src/views/manufacturing/ProductionOrderList.vue -->
<template>
    <div class="production-order-list">
      <div class="page-header">
        <h1>Production Orders</h1>
        <div class="actions">
          <router-link to="/manufacturing/production-orders/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Production Order
          </router-link>
        </div>
      </div>
  
      <div class="filters-container">
        <SearchFilter 
          placeholder="Search production orders..." 
          v-model:value="searchQuery"
          @search="fetchProductionOrders"
        >
          <template #filters>
            <div class="filter-group">
              <label for="status-filter">Status</label>
              <select id="status-filter" v-model="statusFilter" @change="fetchProductionOrders">
                <option value="">All Statuses</option>
                <option value="Draft">Draft</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
              </select>
            </div>
            <div class="filter-group">
              <label for="date-filter">Date Range</label>
              <div class="date-range">
                <input type="date" v-model="startDate" @change="fetchProductionOrders">
                <span>to</span>
                <input type="date" v-model="endDate" @change="fetchProductionOrders">
              </div>
            </div>
          </template>
        </SearchFilter>
      </div>
  
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Loading production orders...</span>
      </div>
      
      <div v-else-if="productionOrders.length === 0" class="empty-state">
        <i class="fas fa-clipboard-list"></i>
        <h3>No Production Orders Found</h3>
        <p>No production orders match your search criteria or no production orders have been created yet.</p>
        <router-link to="/manufacturing/production-orders/create" class="btn btn-primary">
          Create Production Order
        </router-link>
      </div>
  
      <div v-else class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th @click="sortBy('production_number')" class="sortable">
                Production # 
                <i v-if="sortKey === 'production_number'" 
                  :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('production_date')" class="sortable">
                Date
                <i v-if="sortKey === 'production_date'" 
                  :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Work Order</th>
              <th>Product</th>
              <th @click="sortBy('planned_quantity')" class="sortable">
                Planned Qty
                <i v-if="sortKey === 'planned_quantity'" 
                  :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('actual_quantity')" class="sortable">
                Actual Qty
                <i v-if="sortKey === 'actual_quantity'" 
                  :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('status')" class="sortable">
                Status
                <i v-if="sortKey === 'status'" 
                  :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in sortedProductionOrders" :key="order.production_id">
              <td>{{ order.production_number }}</td>
              <td>{{ formatDate(order.production_date) }}</td>
              <td>{{ order.work_order?.wo_number || 'N/A' }}</td>
              <td>{{ order.work_order?.item?.item_code || 'N/A' }}</td>
              <td>{{ order.planned_quantity }}</td>
              <td>{{ order.actual_quantity }}</td>
              <td>
                <span class="status-badge" :class="getStatusClass(order.status)">
                  {{ order.status }}
                </span>
              </td>
              <td class="actions-cell">
                <router-link :to="`/manufacturing/production-orders/${order.production_id}`" 
                            class="btn btn-sm btn-info" title="View">
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link v-if="order.status === 'Draft'" 
                            :to="`/manufacturing/production-orders/${order.production_id}/edit`" 
                            class="btn btn-sm btn-primary" title="Edit">
                  <i class="fas fa-edit"></i>
                </router-link>
                <router-link v-if="order.status === 'In Progress'" 
                            :to="`/manufacturing/production-orders/${order.production_id}/complete`" 
                            class="btn btn-sm btn-success" title="Complete">
                  <i class="fas fa-check"></i>
                </router-link>
                <button v-if="order.status === 'Draft'" 
                        @click="confirmDelete(order)" 
                        class="btn btn-sm btn-danger" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
  
      <div class="pagination-container" v-if="productionOrders.length > 0">
        <PaginationComponent
          :current-page="currentPage"
          :total-pages="totalPages"
          :from="from"
          :to="to"
          :total="total"
          @page-changed="changePage"
        />
      </div>
  
      <!-- Confirmation Modal -->
      <ConfirmationModal
        v-if="showDeleteModal"
        title="Delete Production Order"
        :message="`Are you sure you want to delete production order <strong>${selectedOrder?.production_number}</strong>? This action cannot be undone.`"
        confirm-button-text="Delete"
        confirm-button-class="btn btn-danger"
        @confirm="deleteProductionOrder"
        @close="cancelDelete"
      />
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'ProductionOrderList',
    data() {
      return {
        productionOrders: [],
        searchQuery: '',
        statusFilter: '',
        startDate: '',
        endDate: '',
        loading: true,
        currentPage: 1,
        totalPages: 1,
        from: 1,
        to: 10,
        total: 0,
        sortKey: 'production_date',
        sortOrder: 'desc',
        showDeleteModal: false,
        selectedOrder: null
      };
    },
    computed: {
      sortedProductionOrders() {
        return [...this.productionOrders].sort((a, b) => {
          let modifier = this.sortOrder === 'asc' ? 1 : -1;
          
          if (this.sortKey === 'production_date') {
            return new Date(a.production_date) > new Date(b.production_date) ? modifier : -modifier;
          } else {
            if (a[this.sortKey] < b[this.sortKey]) return -1 * modifier;
            if (a[this.sortKey] > b[this.sortKey]) return 1 * modifier;
            return 0;
          }
        });
      }
    },
    created() {
      this.fetchProductionOrders();
    },
    methods: {
      async fetchProductionOrders() {
        this.loading = true;
        try {
          // Build query parameters
          const params = {
            page: this.currentPage,
            search: this.searchQuery,
            status: this.statusFilter,
            start_date: this.startDate,
            end_date: this.endDate,
            sort_by: this.sortKey,
            sort_order: this.sortOrder
          };
  
          const response = await axios.get('/production-orders', { params });
          
          // Check if API returns paginated data or just an array
          if (response.data.data && response.data.meta) {
            // Paginated response
            this.productionOrders = response.data.data;
            this.currentPage = response.data.meta.current_page;
            this.totalPages = response.data.meta.last_page;
            this.from = response.data.meta.from || 1;
            this.to = response.data.meta.to || this.productionOrders.length;
            this.total = response.data.meta.total;
          } else if (response.data.data) {
            // Non-paginated response with data property
            this.productionOrders = response.data.data;
            this.calculatePagination();
          } else {
            // Direct array response
            this.productionOrders = response.data;
            this.calculatePagination();
          }
        } catch (error) {
          console.error('Error fetching production orders:', error);
          this.$toast.error('Failed to load production orders');
        } finally {
          this.loading = false;
        }
      },
      
      calculatePagination() {
        // Simple pagination calculation for non-paginated API
        this.total = this.productionOrders.length;
        this.totalPages = Math.ceil(this.total / 10);
        this.from = (this.currentPage - 1) * 10 + 1;
        this.to = Math.min(this.from + 9, this.total);
      },
      
      changePage(page) {
        this.currentPage = page;
        this.fetchProductionOrders();
      },
      
      sortBy(key) {
        if (this.sortKey === key) {
          this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
          this.sortKey = key;
          this.sortOrder = 'asc';
        }
        this.fetchProductionOrders();
      },
      
      formatDate(date) {
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString();
      },
      
      getStatusClass(status) {
        switch (status) {
          case 'Draft': return 'status-draft';
          case 'In Progress': return 'status-in-progress';
          case 'Completed': return 'status-completed';
          case 'Cancelled': return 'status-cancelled';
          default: return '';
        }
      },
      
      confirmDelete(order) {
        this.selectedOrder = order;
        this.showDeleteModal = true;
      },
      
      async deleteProductionOrder() {
        try {
          await axios.delete(`/production-orders/${this.selectedOrder.production_id}`);
          this.$toast.success('Production order deleted successfully');
          this.fetchProductionOrders();
        } catch (error) {
          console.error('Error deleting production order:', error);
          this.$toast.error('Failed to delete production order');
        } finally {
          this.showDeleteModal = false;
          this.selectedOrder = null;
        }
      },
      
      cancelDelete() {
        this.showDeleteModal = false;
        this.selectedOrder = null;
      }
    }
  };
  </script>
  
  <style scoped>
  .production-order-list {
    padding: 1rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .filters-container {
    margin-bottom: 1.5rem;
  }
  
  .date-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
  }
  
  .table th,
  .table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-200);
    text-align: left;
  }
  
  .table th {
    background-color: var(--gray-50);
    font-weight: 500;
  }
  
  .table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .sortable {
    cursor: pointer;
    user-select: none;
  }
  
  .sortable:hover {
    background-color: var(--gray-100);
  }
  
  .status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .status-draft {
    background-color: var(--gray-200);
    color: var(--gray-700);
  }
  
  .status-in-progress {
    background-color: #bfdbfe;
    color: #1e40af;
  }
  
  .status-completed {
    background-color: #bbf7d0;
    color: #166534;
  }
  
  .status-cancelled {
    background-color: #fecaca;
    color: #b91c1c;
  }
  
  .actions-cell {
    display: flex;
    gap: 0.5rem;
  }
  
  .loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--gray-500);
  }
  
  .loading-container i {
    font-size: 2rem;
    margin-bottom: 1rem;
  }
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    text-align: center;
  }
  
  .empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
  }
  
  .pagination-container {
    margin-top: 1.5rem;
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .actions {
      width: 100%;
    }
    
    .table-responsive {
      overflow-x: auto;
    }
    
    .date-range {
      flex-direction: column;
      align-items: flex-start;
    }
  }
  </style>