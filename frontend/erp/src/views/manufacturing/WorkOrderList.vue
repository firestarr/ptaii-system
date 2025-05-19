<!-- src/views/manufacturing/WorkOrderList.vue -->
<template>
    <div class="work-orders-page">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Work Orders</h2>
          <router-link to="/manufacturing/work-orders/create" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i> Create Work Order
          </router-link>
        </div>
        <div class="card-body">
          <!-- Filters -->
          <div class="filter-section mb-4">
            <SearchFilter
              placeholder="Search by work order number, product name..."
              v-model:value="searchQuery"
              @search="handleSearch"
              @clear="clearFilters"
            >
              <template #filters>
                <div class="filter-group">
                  <label>Status</label>
                  <select v-model="statusFilter" @change="handleFilterChange" class="form-control">
                    <option value="">All Status</option>
                    <option value="Draft">Draft</option>
                    <option value="Planned">Planned</option>
                    <option value="Released">Released</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Closed">Closed</option>
                    <option value="Cancelled">Cancelled</option>
                  </select>
                </div>
                <div class="filter-group">
                  <label>Date Range</label>
                  <input 
                    type="date" 
                    v-model="dateFrom" 
                    class="form-control"
                    @change="handleFilterChange"
                  />
                </div>
                <div class="filter-group">
                  <label>To</label>
                  <input 
                    type="date" 
                    v-model="dateTo" 
                    class="form-control"
                    @change="handleFilterChange"
                  />
                </div>
              </template>
            </SearchFilter>
          </div>
  
          <!-- Status summary cards -->
          <div class="status-summary mb-4 grid grid-cols-4 sm:grid-cols-2 gap-4">
            <div class="status-card" v-for="status in statusSummary" :key="status.label">
              <div class="card">
                <div class="card-body p-3 d-flex">
                  <div class="status-icon" :class="status.class">
                    <i :class="status.icon"></i>
                  </div>
                  <div class="status-details">
                    <div class="status-count">{{ status.count }}</div>
                    <div class="status-label">{{ status.label }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
  
          <!-- Work orders table -->
          <div v-if="isLoading" class="loading-indicator">
            <i class="fas fa-spinner fa-spin"></i> Loading work orders...
          </div>
          
          <DataTable
            v-else
            :columns="columns"
            :items="workOrders"
            keyField="wo_id"
            :isLoading="isLoading"
            :emptyTitle="'No work orders found'"
            :emptyMessage="'No work orders match your search criteria.'"
            :initialSortKey="'wo_date'"
            :initialSortOrder="'desc'"
            @sort="handleSort"
          >
            <!-- Status template -->
            <template #status="{ value }">
              <span class="badge" :class="getStatusClass(value)">{{ value }}</span>
            </template>
            
            <!-- Date template -->
            <template #wo_date="{ value }">
              {{ formatDate(value) }}
            </template>
            
            <!-- Progress template -->
            <template #progress="{ item }">
              <div class="progress-bar-container">
                <div 
                  class="progress-bar" 
                  :style="{ width: getProgressPercentage(item) + '%' }"
                  :class="getProgressClass(item.status)"
                ></div>
                <span class="progress-text">{{ getProgressPercentage(item) }}%</span>
              </div>
            </template>
            
            <!-- Actions template -->
            <template #actions="{ item }">
              <div class="actions-cell">
                <router-link 
                  :to="`/manufacturing/work-orders/${item.wo_id}`"
                  class="btn btn-sm btn-primary mr-1" 
                  title="View details"
                >
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link 
                  v-if="item.status === 'Draft'"
                  :to="`/manufacturing/work-orders/${item.wo_id}/edit`"
                  class="btn btn-sm btn-info mr-1" 
                  title="Edit"
                >
                  <i class="fas fa-edit"></i>
                </router-link>
                <button 
                  v-if="item.status === 'Draft'"
                  @click="confirmRelease(item)" 
                  class="btn btn-sm btn-success mr-1" 
                  title="Release"
                >
                  <i class="fas fa-play-circle"></i>
                </button>
                <button 
                  v-else-if="item.status === 'Released'"
                  @click="confirmStart(item)" 
                  class="btn btn-sm btn-success mr-1" 
                  title="Start production"
                >
                  <i class="fas fa-tasks"></i>
                </button>
                <button 
                  v-if="item.status === 'Draft'"
                  @click="confirmDelete(item)" 
                  class="btn btn-sm btn-danger" 
                  title="Delete"
                >
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </template>
          </DataTable>
          
          <!-- Pagination -->
          <div class="pagination-container mt-4">
            <PaginationComponent
              :currentPage="currentPage"
              :totalPages="totalPages"
              :from="from"
              :to="to"
              :total="total"
              @page-changed="handlePageChange"
            />
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
      <ConfirmationModal
        v-if="showModal"
        :title="modalTitle"
        :message="modalMessage"
        :confirmButtonText="modalConfirmText"
        :confirmButtonClass="modalConfirmClass"
        @confirm="handleModalConfirm"
        @close="showModal = false"
      />
    </div>
  </template>
  
  <script>
  import { ref, reactive, computed, onMounted } from 'vue';
  //import { useRouter } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'WorkOrderList',
    setup() {
      //const router = useRouter();
      
      // Data
      const workOrders = ref([]);
      const isLoading = ref(true);
      const searchQuery = ref('');
      const statusFilter = ref('');
      const dateFrom = ref('');
      const dateTo = ref('');
      const sortField = ref('wo_date');
      const sortOrder = ref('desc');
      const currentPage = ref(1);
      const perPage = ref(10);
      const total = ref(0);
      const totalPages = ref(0);
      
      // Modal state
      const showModal = ref(false);
      const modalTitle = ref('');
      const modalMessage = ref('');
      const modalConfirmText = ref('');
      const modalConfirmClass = ref('btn btn-primary');
      const modalAction = ref('');
      const selectedItem = ref(null);
      
      // Pagination computed properties
      const from = computed(() => {
        if (total.value === 0) return 0;
        return (currentPage.value - 1) * perPage.value + 1;
      });
      
      const to = computed(() => {
        const lastItem = currentPage.value * perPage.value;
        return lastItem > total.value ? total.value : lastItem;
      });
      
      // Table columns definition
      const columns = [
        { key: 'wo_number', label: 'WO Number', sortable: true },
        { key: 'wo_date', label: 'Date', sortable: true, template: 'wo_date' },
        { key: 'item_name', label: 'Item', sortable: true },
        { key: 'planned_quantity', label: 'Qty', sortable: true },
        { key: 'status', label: 'Status', sortable: true, template: 'status' },
        { key: 'progress', label: 'Progress', template: 'progress' },
        { key: 'planned_start_date', label: 'Start Date', sortable: true },
        { key: 'planned_end_date', label: 'End Date', sortable: true }
      ];
  
      // Status summary
      const statusSummary = reactive([
        { label: 'Planned', count: 0, icon: 'fas fa-clipboard-list', class: 'bg-info' },
        { label: 'In Progress', count: 0, icon: 'fas fa-spinner', class: 'bg-warning' },
        { label: 'Completed', count: 0, icon: 'fas fa-check-circle', class: 'bg-success' },
        { label: 'Total', count: 0, icon: 'fas fa-clipboard', class: 'bg-primary' }
      ]);
  
      // Methods
      const fetchWorkOrders = async () => {
        isLoading.value = true;
        try {
          // Construct query parameters
          const params = {
            page: currentPage.value,
            per_page: perPage.value,
            sort_field: sortField.value,
            sort_order: sortOrder.value
          };
          
          if (searchQuery.value) {
            params.search = searchQuery.value;
          }
          
          if (statusFilter.value) {
            params.status = statusFilter.value;
          }
          
          if (dateFrom.value) {
            params.date_from = dateFrom.value;
          }
          
          if (dateTo.value) {
            params.date_to = dateTo.value;
          }
          
          const response = await axios.get('/work-orders', { params });
          workOrders.value = response.data.data.map(wo => ({
            ...wo,
            item_name: wo.item ? wo.item.name : 'Unknown'
          }));
          
          total.value = response.data.meta.total;
          totalPages.value = response.data.meta.last_page;
          
          // Update status summary
          updateStatusSummary();
        } catch (error) {
          console.error('Error fetching work orders:', error);
        } finally {
          isLoading.value = false;
        }
      };
      
      const updateStatusSummary = () => {
        // Reset counts
        statusSummary.forEach(status => status.count = 0);
        
        // Count work orders by status
        workOrders.value.forEach(wo => {
          statusSummary[3].count++; // Increment total count
          
          if (wo.status === 'Planned' || wo.status === 'Released') {
            statusSummary[0].count++;
          } else if (wo.status === 'In Progress') {
            statusSummary[1].count++;
          } else if (wo.status === 'Completed') {
            statusSummary[2].count++;
          }
        });
      };
      
      const handleSearch = () => {
        currentPage.value = 1;
        fetchWorkOrders();
      };
      
      const handleFilterChange = () => {
        currentPage.value = 1;
        fetchWorkOrders();
      };
      
      const clearFilters = () => {
        searchQuery.value = '';
        statusFilter.value = '';
        dateFrom.value = '';
        dateTo.value = '';
        currentPage.value = 1;
        fetchWorkOrders();
      };
      
      const handleSort = ({ key, order }) => {
        sortField.value = key;
        sortOrder.value = order;
        fetchWorkOrders();
      };
      
      const handlePageChange = (page) => {
        currentPage.value = page;
        fetchWorkOrders();
      };
      
      const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit'
        });
      };
      
      const getStatusClass = (status) => {
        switch (status) {
          case 'Draft': return 'badge-secondary';
          case 'Planned': return 'badge-info';
          case 'Released': return 'badge-primary';
          case 'In Progress': return 'badge-warning';
          case 'Completed': return 'badge-success';
          case 'Closed': return 'badge-dark';
          case 'Cancelled': return 'badge-danger';
          default: return 'badge-secondary';
        }
      };
      
      const getProgressPercentage = (workOrder) => {
        if (workOrder.status === 'Completed' || workOrder.status === 'Closed') {
          return 100;
        } else if (workOrder.status === 'Cancelled') {
          return 0;
        } else if (workOrder.status === 'Draft' || workOrder.status === 'Planned') {
          return 0;
        }
        
        // Calculate based on completed operations if available
        if (workOrder.operations_count && workOrder.completed_operations_count) {
          return Math.round((workOrder.completed_operations_count / workOrder.operations_count) * 100);
        }
        
        // Default progress for 'In Progress' status
        return workOrder.status === 'In Progress' ? 50 : 0;
      };
      
      const getProgressClass = (status) => {
        switch (status) {
          case 'In Progress': return 'progress-warning';
          case 'Completed': 
          case 'Closed': return 'progress-success';
          default: return 'progress-primary';
        }
      };
      
      // Modal confirmation methods
      const confirmDelete = (item) => {
        selectedItem.value = item;
        modalTitle.value = 'Delete Work Order';
        modalMessage.value = `Are you sure you want to delete work order <strong>${item.wo_number}</strong>?<br>This action cannot be undone.`;
        modalConfirmText.value = 'Delete';
        modalConfirmClass.value = 'btn btn-danger';
        modalAction.value = 'delete';
        showModal.value = true;
      };
      
      const confirmRelease = (item) => {
        selectedItem.value = item;
        modalTitle.value = 'Release Work Order';
        modalMessage.value = `Are you sure you want to release work order <strong>${item.wo_number}</strong>?<br>Once released, it cannot be edited or deleted.`;
        modalConfirmText.value = 'Release';
        modalConfirmClass.value = 'btn btn-success';
        modalAction.value = 'release';
        showModal.value = true;
      };
      
      const confirmStart = (item) => {
        selectedItem.value = item;
        modalTitle.value = 'Start Production';
        modalMessage.value = `Are you sure you want to start production for work order <strong>${item.wo_number}</strong>?`;
        modalConfirmText.value = 'Start';
        modalConfirmClass.value = 'btn btn-success';
        modalAction.value = 'start';
        showModal.value = true;
      };
      
      const handleModalConfirm = async () => {
        if (!selectedItem.value) return;
        
        try {
          const woId = selectedItem.value.wo_id;
          
          if (modalAction.value === 'delete') {
            await axios.delete(`/work-orders/${woId}`);
            fetchWorkOrders();
          } else if (modalAction.value === 'release') {
            await axios.patch(`/work-orders/${woId}`, { status: 'Released' });
            fetchWorkOrders();
          } else if (modalAction.value === 'start') {
            await axios.patch(`/work-orders/${woId}`, { status: 'In Progress' });
            fetchWorkOrders();
          }
        } catch (error) {
          console.error(`Error performing ${modalAction.value} action:`, error);
        } finally {
          showModal.value = false;
        }
      };
      
      // Lifecycle hooks
      onMounted(() => {
        fetchWorkOrders();
      });
      
      return {
        workOrders,
        isLoading,
        columns,
        searchQuery,
        statusFilter,
        dateFrom,
        dateTo,
        currentPage,
        totalPages,
        from,
        to,
        total,
        statusSummary,
        showModal,
        modalTitle,
        modalMessage,
        modalConfirmText,
        modalConfirmClass,
        handleSearch,
        handleFilterChange,
        clearFilters,
        handleSort,
        handlePageChange,
        formatDate,
        getStatusClass,
        getProgressPercentage,
        getProgressClass,
        confirmDelete,
        confirmRelease,
        confirmStart,
        handleModalConfirm
      };
    }
  };
  </script>
  
  <style scoped>
  /* Container styling */
.work-orders-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #334155;
}

/* Card styling */
.card {
  background-color: #ffffff;
  border-radius: 8px;
  box-shadow: 0 1px 8px rgba(0, 0, 0, 0.08);
  border: none;
  overflow: hidden;
  margin-bottom: 1.5rem;
}

.card-header {
  padding: 1.25rem 1.5rem;
  background-color: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.card-body {
  padding: 1.5rem;
}

/* Filter section */
.filter-section {
  margin-bottom: 1.75rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid #e2e8f0;
}

.filter-group {
  display: flex;
  flex-direction: column;
  margin-right: 1rem;
  min-width: 175px;
}

.filter-group label {
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 0.35rem;
  color: #475569;
}

.form-control {
  display: block;
  width: 100%;
  padding: 0.65rem 0.85rem;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #334155;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
  border-color: #3b82f6;
  outline: 0;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}

select.form-control {
  padding-right: 2.5rem;
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 1em;
  appearance: none;
}

/* Status summary cards */
.status-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.status-card .card {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  margin-bottom: 0;
}

.status-card .card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.status-card .card-body {
  padding: 1.25rem;
  display: flex;
  align-items: center;
}

.status-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  margin-right: 1rem;
  color: white;
  font-size: 1.25rem;
}

.status-details {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.status-count {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
  color: #1e293b;
}

.status-label {
  font-size: 0.875rem;
  color: #64748b;
  margin-top: 0.35rem;
  font-weight: 500;
}

/* Background colors for status icons */
.bg-info {
  background-color: #0ea5e9;
}

.bg-warning {
  background-color: #f59e0b;
}

.bg-success {
  background-color: #10b981;
}

.bg-primary {
  background-color: #3b82f6;
}

/* Loading indicator */
.loading-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  color: #64748b;
  font-size: 1rem;
}

.loading-indicator i {
  font-size: 1.25rem;
  margin-right: 0.75rem;
  color: #3b82f6;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Data table styling */
:deep(.data-table) {
  width: 100%;
  border-collapse: collapse;
  border-spacing: 0;
  margin-bottom: 1.5rem;
}

:deep(.data-table th) {
  text-align: left;
  padding: 1rem;
  font-weight: 600;
  color: #475569;
  background-color: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

:deep(.data-table td) {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
  vertical-align: middle;
}

:deep(.data-table tr:hover) {
  background-color: #f8fafc;
}

:deep(.data-table tr:last-child td) {
  border-bottom: none;
}

/* Status badges */
.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.35rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1;
  border-radius: 9999px;
  white-space: nowrap;
}

.badge-secondary {
  background-color: #e2e8f0;
  color: #475569;
}

.badge-info {
  background-color: #dbeafe;
  color: #1d4ed8;
}

.badge-primary {
  background-color: #bfdbfe;
  color: #1e40af;
}

.badge-warning {
  background-color: #fef3c7;
  color: #b45309;
}

.badge-success {
  background-color: #d1fae5;
  color: #047857;
}

.badge-dark {
  background-color: #cbd5e1;
  color: #0f172a;
}

.badge-danger {
  background-color: #fee2e2;
  color: #b91c1c;
}

/* Progress bar */
.progress-bar-container {
  position: relative;
  width: 100%;
  height: 0.5rem;
  background-color: #e2e8f0;
  border-radius: 9999px;
  overflow: hidden;
}

.progress-bar {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  border-radius: 9999px;
  transition: width 0.3s ease;
}

.progress-primary {
  background-color: #3b82f6;
}

.progress-warning {
  background-color: #f59e0b;
}

.progress-success {
  background-color: #10b981;
}

.progress-text {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 600;
  color: #1e293b;
}

/* Action buttons */
.actions-cell {
  display: flex;
  gap: 0.35rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.65rem 1rem;
  font-size: 0.95rem;
  font-weight: 500;
  line-height: 1.5;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  border: 1px solid transparent;
}

.btn-primary {
  background-color: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.btn-primary:hover {
  background-color: #2563eb;
  border-color: #2563eb;
  box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25);
}

.btn-info {
  background-color: #0ea5e9;
  color: white;
  border-color: #0ea5e9;
}

.btn-info:hover {
  background-color: #0284c7;
  border-color: #0284c7;
  box-shadow: 0 2px 4px rgba(14, 165, 233, 0.25);
}

.btn-success {
  background-color: #10b981;
  color: white;
  border-color: #10b981;
}

.btn-success:hover {
  background-color: #059669;
  border-color: #059669;
  box-shadow: 0 2px 4px rgba(16, 185, 129, 0.25);
}

.btn-danger {
  background-color: #ef4444;
  color: white;
  border-color: #ef4444;
}

.btn-danger:hover {
  background-color: #dc2626;
  border-color: #dc2626;
  box-shadow: 0 2px 4px rgba(239, 68, 68, 0.25);
}

.btn-sm {
  padding: 0.35rem 0.5rem;
  font-size: 0.85rem;
  border-radius: 4px;
}

/* Utility classes */
.d-flex {
  display: flex;
}

.justify-content-between {
  justify-content: space-between;
}

.align-items-center {
  align-items: center;
}

.mb-4 {
  margin-bottom: 1.5rem;
}

.mt-4 {
  margin-top: 1.5rem;
}

.mr-1 {
  margin-right: 0.25rem;
}

.mr-2 {
  margin-right: 0.5rem;
}

.p-3 {
  padding: 0.75rem;
}

/* Pagination */
.pagination-container {
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  .status-summary {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }
}

@media (max-width: 768px) {
  .work-orders-page {
    padding: 1rem;
  }
  
  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .card-header .btn {
    margin-top: 1rem;
    align-self: stretch;
  }
  
  .filter-section {
    display: flex;
    flex-direction: column;
  }
  
  .status-card .card-body {
    padding: 1rem;
  }
  
  .status-icon {
    width: 2.5rem;
    height: 2.5rem;
    font-size: 1rem;
  }
  
  .status-count {
    font-size: 1.5rem;
  }
  
  :deep(.data-table) {
    font-size: 0.85rem;
  }
  
  :deep(.data-table th),
  :deep(.data-table td) {
    padding: 0.75rem 0.5rem;
  }
  
  .actions-cell {
    flex-wrap: wrap;
  }
}

@media (max-width: 480px) {
  .status-summary {
    grid-template-columns: 1fr;
  }
  
  .status-card .card-body {
    justify-content: center;
    text-align: center;
    flex-direction: column;
  }
  
  .status-icon {
    margin-right: 0;
    margin-bottom: 0.5rem;
  }
}
  </style>