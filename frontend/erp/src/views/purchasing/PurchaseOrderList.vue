<!-- src/views/purchasing/PurchaseOrderList.vue -->
<template>
  <div class="purchase-orders-container">
    <div class="page-header">
      <h1>Purchase Orders</h1>
      <div class="action-buttons">
        <router-link to="/purchasing/orders/create" class="btn btn-primary">
          <i class="fas fa-plus"></i> Create Purchase Order
        </router-link>
      </div>
    </div>

    <div class="filter-section">
      <div class="card">
        <div class="card-body">
          <h3 class="filter-title">Filters</h3>
          <div class="filter-form">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Status</label>
                  <select v-model="filters.status" class="form-control" @change="loadPurchaseOrders()">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="sent">Sent</option>
                    <option value="partial">Partial</option>
                    <option value="received">Received</option>
                    <option value="completed">Completed</option>
                    <option value="canceled">Canceled</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Vendor</label>
                  <select v-model="filters.vendor_id" class="form-control" @change="loadPurchaseOrders()">
                    <option value="">All Vendors</option>
                    <option v-for="vendor in vendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
                      {{ vendor.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Date From</label>
                  <input 
                    type="date" 
                    v-model="filters.date_from" 
                    class="form-control"
                    @change="loadPurchaseOrders()"
                  >
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Date To</label>
                  <input 
                    type="date" 
                    v-model="filters.date_to" 
                    class="form-control"
                    @change="loadPurchaseOrders()"
                  >
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Search</label>
                  <div class="input-group">
                    <input 
                      type="text" 
                      v-model="filters.search" 
                      class="form-control" 
                      placeholder="Search by PO number"
                    >
                    <div class="input-group-append">
                      <button class="btn btn-primary" @click="loadPurchaseOrders()">
                        <i class="fas fa-search"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-check mt-4">
                  <input 
                    type="checkbox" 
                    class="form-check-input" 
                    id="outstandingOnly" 
                    v-model="filters.has_outstanding"
                    @change="loadPurchaseOrders()"
                  >
                  <label class="form-check-label" for="outstandingOnly">Outstanding Only</label>
                </div>
              </div>
              <div class="col-md-3 text-right mt-4">
                <button class="btn btn-secondary" @click="resetFilters()">
                  <i class="fas fa-redo"></i> Reset Filters
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="purchase-orders-list mt-4">
      <div class="card">
        <div class="card-body">
          <div v-if="isLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading purchase orders...</p>
          </div>

          <div v-else-if="purchaseOrders.length === 0" class="empty-state">
            <i class="fas fa-clipboard-list fa-4x text-muted"></i>
            <h3>No Purchase Orders Found</h3>
            <p>Try adjusting your filters or create a new purchase order.</p>
          </div>

          <div v-else>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th @click="updateSort('po_number')">
                      PO Number 
                      <i v-if="sortField === 'po_number'" 
                        :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'">
                      </i>
                    </th>
                    <th @click="updateSort('po_date')">
                      Date 
                      <i v-if="sortField === 'po_date'" 
                        :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'">
                      </i>
                    </th>
                    <th>Vendor</th>
                    <th @click="updateSort('expected_delivery')">
                      Expected Delivery 
                      <i v-if="sortField === 'expected_delivery'" 
                        :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'">
                      </i>
                    </th>
                    <th @click="updateSort('total_amount')">
                      Amount 
                      <i v-if="sortField === 'total_amount'" 
                        :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'">
                      </i>
                    </th>
                    <th>Currency</th>
                    <th @click="updateSort('status')">
                      Status 
                      <i v-if="sortField === 'status'" 
                        :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'">
                      </i>
                    </th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="po in purchaseOrders" :key="po.po_id">
                    <td>{{ po.po_number }}</td>
                    <td>{{ formatDate(po.po_date) }}</td>
                    <td>{{ po.vendor ? po.vendor.name : '-' }}</td>
                    <td>{{ formatDate(po.expected_delivery) }}</td>
                    <td>{{ formatCurrency(po.total_amount) }}</td>
                    <td>{{ po.currency_code || 'USD' }}</td>
                    <td>
                      <span :class="getStatusBadgeClass(po.status)">
                        {{ po.status }}
                      </span>
                    </td>
                    <td>
                      <div class="btn-group">
                        <router-link :to="`/purchasing/orders/${po.po_id}`" class="btn btn-sm btn-info">
                          <i class="fas fa-eye"></i>
                        </router-link>
                        <router-link v-if="po.status === 'draft'" 
                          :to="`/purchasing/orders/${po.po_id}/edit`" 
                          class="btn btn-sm btn-primary">
                          <i class="fas fa-edit"></i>
                        </router-link>
                        <router-link :to="`/purchasing/orders/${po.po_id}/track`" 
                          class="btn btn-sm btn-secondary">
                          <i class="fas fa-chart-line"></i>
                        </router-link>
                        <button v-if="po.status === 'draft'" 
                          @click="confirmDelete(po)" 
                          class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-container d-flex justify-content-between align-items-center mt-4">
              <div class="pagination-info">
                Showing {{ paginationInfo.from }} to {{ paginationInfo.to }} of {{ paginationInfo.total }} items
              </div>
              <nav aria-label="Page navigation">
                <ul class="pagination">
                  <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <a class="page-link" href="#" @click.prevent="goToPage(currentPage - 1)">Previous</a>
                  </li>
                  <li v-for="page in paginationPages" :key="page" 
                    class="page-item" :class="{ active: currentPage === page }">
                    <a class="page-link" href="#" @click.prevent="goToPage(page)">{{ page }}</a>
                  </li>
                  <li class="page-item" :class="{ disabled: currentPage === paginationInfo.last_page }">
                    <a class="page-link" href="#" @click.prevent="goToPage(currentPage + 1)">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true" v-if="showDeleteModal">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Delete</h5>
            <button type="button" class="close" @click="showDeleteModal = false">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            Are you sure you want to delete purchase order <strong>{{ poToDelete?.po_number }}</strong>?
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">Cancel</button>
            <button type="button" class="btn btn-danger" @click="deletePurchaseOrder()">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'PurchaseOrderList',
  data() {
    return {
      purchaseOrders: [],
      vendors: [],
      isLoading: false,
      filters: {
        status: '',
        vendor_id: '',
        date_from: '',
        date_to: '',
        search: '',
        has_outstanding: false
      },
      sortField: 'po_date',
      sortDirection: 'desc',
      currentPage: 1,
      paginationInfo: {
        total: 0,
        per_page: 15,
        current_page: 1,
        last_page: 1,
        from: 0,
        to: 0
      },
      showDeleteModal: false,
      poToDelete: null
    };
  },
  computed: {
    paginationPages() {
      const pages = [];
      let startPage = Math.max(1, this.currentPage - 2);
      let endPage = Math.min(this.paginationInfo.last_page, startPage + 4);
      
      if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
      }
      
      for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
      }
      
      return pages;
    }
  },
  created() {
    this.loadVendors();
    this.loadPurchaseOrders();
  },
  methods: {
    async loadVendors() {
      try {
        const response = await axios.get('/api/vendors');
        this.vendors = response.data.data;
      } catch (error) {
        console.error('Error loading vendors:', error);
      }
    },
    async loadPurchaseOrders() {
      this.isLoading = true;
      try {
        const params = {
          page: this.currentPage,
          per_page: this.paginationInfo.per_page,
          sort_field: this.sortField,
          sort_direction: this.sortDirection,
          ...this.filters
        };

        const response = await axios.get('/api/purchase-orders', { params });
        
        if (response.data.status === 'success') {
          this.purchaseOrders = response.data.data.data;
          this.paginationInfo = {
            total: response.data.data.total,
            per_page: response.data.data.per_page,
            current_page: response.data.data.current_page,
            last_page: response.data.data.last_page,
            from: response.data.data.from,
            to: response.data.data.to
          };
        }
      } catch (error) {
        console.error('Error loading purchase orders:', error);
      } finally {
        this.isLoading = false;
      }
    },
    resetFilters() {
      this.filters = {
        status: '',
        vendor_id: '',
        date_from: '',
        date_to: '',
        search: '',
        has_outstanding: false
      };
      this.loadPurchaseOrders();
    },
    updateSort(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortField = field;
        this.sortDirection = 'asc';
      }
      this.loadPurchaseOrders();
    },
    goToPage(page) {
      if (page < 1 || page > this.paginationInfo.last_page) {
        return;
      }
      this.currentPage = page;
      this.loadPurchaseOrders();
    },
    confirmDelete(po) {
      this.poToDelete = po;
      this.showDeleteModal = true;
    },
    async deletePurchaseOrder() {
      if (!this.poToDelete) return;
      
      try {
        const response = await axios.delete(`/api/purchase-orders/${this.poToDelete.po_id}`);
        
        if (response.data.status === 'success') {
          // Show success notification
          alert('Purchase order deleted successfully');
          
          // Refresh the list
          this.loadPurchaseOrders();
        }
      } catch (error) {
        console.error('Error deleting purchase order:', error);
        
        // Show error notification
        if (error.response && error.response.data && error.response.data.message) {
          alert(`Error: ${error.response.data.message}`);
        } else {
          alert('An error occurred while deleting the purchase order');
        }
      } finally {
        this.showDeleteModal = false;
        this.poToDelete = null;
      }
    },
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    formatCurrency(amount) {
      if (amount === null || amount === undefined) return '-';
      return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount);
    },
    getStatusBadgeClass(status) {
      const statusClasses = {
        'draft': 'badge badge-secondary',
        'submitted': 'badge badge-info',
        'approved': 'badge badge-primary',
        'sent': 'badge badge-warning',
        'partial': 'badge badge-info',
        'received': 'badge badge-success',
        'completed': 'badge badge-success',
        'canceled': 'badge badge-danger'
      };
      
      return statusClasses[status] || 'badge badge-secondary';
    }
  }
};
</script>

<style scoped>
.purchase-orders-container {
  margin-bottom: 2rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.filter-title {
  font-size: 1.1rem;
  margin-bottom: 1rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 0;
  color: #6c757d;
  text-align: center;
}

.empty-state i {
  margin-bottom: 1rem;
}

.table th {
  cursor: pointer;
}

.badge {
  padding: 0.5em 0.75em;
  text-transform: capitalize;
}

/* Modal styles */
.modal {
  display: block;
  background-color: rgba(0, 0, 0, 0.5);
}
</style>