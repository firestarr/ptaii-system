<!-- src/views/manufacturing/WorkCentersList.vue -->
<template>
    <div class="work-centers-container">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Work Centers</h2>
          <router-link to="/manufacturing/work-centers/create" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i> Add Work Center
          </router-link>
        </div>
        <div class="card-body">
          <SearchFilter
            placeholder="Search work centers..."
            v-model:value="searchQuery"
            @search="handleSearch"
            @clear="resetFilters"
          >
            <template #filters>
              <div class="filter-group">
                <label>Status</label>
                <select v-model="statusFilter" class="form-control" @change="handleSearch">
                  <option value="">All Status</option>
                  <option value="true">Active</option>
                  <option value="false">Inactive</option>
                </select>
              </div>
            </template>
            <template #actions>
              <button class="btn btn-secondary" @click="resetFilters">
                <i class="fas fa-redo mr-2"></i> Reset
              </button>
            </template>
          </SearchFilter>
  
          <div v-if="isLoading" class="text-center p-5">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Loading work centers...</p>
          </div>
  
          <div v-else-if="workCenters.length === 0" class="empty-state">
            <div class="empty-icon">
              <i class="fas fa-industry"></i>
            </div>
            <h3>No Work Centers Found</h3>
            <p>No work centers match your search criteria or no work centers have been created yet.</p>
            <router-link to="/manufacturing/work-centers/create" class="btn btn-primary mt-3">
              <i class="fas fa-plus mr-2"></i> Add Work Center
            </router-link>
          </div>
  
          <DataTable v-else
            :columns="columns"
            :items="workCenters"
            :is-loading="isLoading"
            keyField="workcenter_id"
            :initial-sort-key="sortKey"
            :initial-sort-order="sortOrder"
            @sort="handleSort"
          >
            <template #status="{ value }">
              <span :class="['badge', value ? 'badge-success' : 'badge-secondary']">
                {{ value ? 'Active' : 'Inactive' }}
              </span>
            </template>
            
            <template #capacity="{ value }">
              {{ value }} units/hour
            </template>
            
            <template #cost_per_hour="{ value }">
              {{ formatCurrency(value) }}
            </template>
            
            <template #actions="{ item }">
              <div class="btn-group">
                <router-link :to="`/manufacturing/work-centers/${item.workcenter_id}`" class="btn btn-sm btn-info">
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link :to="`/manufacturing/work-centers/${item.workcenter_id}/edit`" class="btn btn-sm btn-primary">
                  <i class="fas fa-edit"></i>
                </router-link>
                <router-link :to="`/manufacturing/work-centers/${item.workcenter_id}/schedule`" class="btn btn-sm btn-success">
                  <i class="fas fa-calendar-alt"></i>
                </router-link>
                <button @click="confirmDelete(item)" class="btn btn-sm btn-danger">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </template>
            
            <template #footer>
              <PaginationComponent
                :current-page="currentPage"
                :total-pages="totalPages"
                :from="from"
                :to="to"
                :total="total"
                @page-changed="handlePageChange"
              />
            </template>
          </DataTable>
        </div>
      </div>
      
      <!-- Confirmation Modal -->
      <ConfirmationModal
        v-if="showDeleteModal"
        :title="'Delete Work Center'"
        :message="deleteMessage"
        confirm-button-text="Delete"
        confirm-button-class="btn btn-danger"
        @confirm="deleteWorkCenter"
        @close="showDeleteModal = false"
      />
    </div>
  </template>
  
  <script>
import { ref/*, /*reactive*/, onMounted, computed } from 'vue';
import axios from 'axios';
// import { useRouter } from 'vue-router';

export default {
  name: 'WorkCentersList',
  setup() {
    // const router = useRouter();
    const workCenters = ref([]);
      const isLoading = ref(true);
      const searchQuery = ref('');
      const statusFilter = ref('');
      const sortKey = ref('name');
      const sortOrder = ref('asc');
      const currentPage = ref(1);
      const totalPages = ref(1);
      const from = ref(0);
      const to = ref(0);
      const total = ref(0);
      const perPage = ref(10);
      
      // Delete confirmation
      const showDeleteModal = ref(false);
      const workCenterToDelete = ref(null);
      const deleteMessage = computed(() => {
        if (!workCenterToDelete.value) return '';
        return `Are you sure you want to delete the work center <strong>${workCenterToDelete.value.name}</strong>? This action cannot be undone.`;
      });
  
      const columns = [
        { key: 'code', label: 'Code', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'capacity', label: 'Capacity', sortable: true, template: 'capacity' },
        { key: 'cost_per_hour', label: 'Cost/Hour', sortable: true, template: 'cost_per_hour' },
        { key: 'is_active', label: 'Status', sortable: true, template: 'status' }
      ];
  
      const loadWorkCenters = async () => {
        isLoading.value = true;
        try {
          const params = {
            page: currentPage.value,
            per_page: perPage.value,
            search: searchQuery.value,
            sort_field: sortKey.value,
            sort_order: sortOrder.value
          };
  
          if (statusFilter.value !== '') {
            params.is_active = statusFilter.value;
          }
  
          const response = await axios.get('/work-centers', { params });
          workCenters.value = response.data.data;
          
          // Check if pagination data is available
          if (response.data.meta) {
            currentPage.value = response.data.meta.current_page;
            totalPages.value = response.data.meta.last_page;
            from.value = response.data.meta.from || 0;
            to.value = response.data.meta.to || 0;
            total.value = response.data.meta.total || 0;
          }
        } catch (error) {
          console.error('Error loading work centers:', error);
          alert('Failed to load work centers. Please try again later.');
        } finally {
          isLoading.value = false;
        }
      };
  
      const handleSearch = () => {
        currentPage.value = 1;
        loadWorkCenters();
      };
  
      const handleSort = (sortData) => {
        sortKey.value = sortData.key;
        sortOrder.value = sortData.order;
        loadWorkCenters();
      };
  
      const handlePageChange = (page) => {
        currentPage.value = page;
        loadWorkCenters();
      };
  
      const resetFilters = () => {
        searchQuery.value = '';
        statusFilter.value = '';
        currentPage.value = 1;
        sortKey.value = 'name';
        sortOrder.value = 'asc';
        loadWorkCenters();
      };
  
      const confirmDelete = (workCenter) => {
        workCenterToDelete.value = workCenter;
        showDeleteModal.value = true;
      };
  
      const deleteWorkCenter = async () => {
        if (!workCenterToDelete.value) return;
        
        try {
          await axios.delete(`/work-centers/${workCenterToDelete.value.workcenter_id}`);
          showDeleteModal.value = false;
          loadWorkCenters();
        } catch (error) {
          if (error.response && error.response.status === 400) {
            alert(error.response.data.message || 'This work center cannot be deleted because it is in use.');
          } else {
            console.error('Error deleting work center:', error);
            alert('Failed to delete work center. Please try again later.');
          }
          showDeleteModal.value = false;
        }
      };
  
      const formatCurrency = (value) => {
        return new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency: 'USD',
          minimumFractionDigits: 2
        }).format(value);
      };
  
      onMounted(() => {
        loadWorkCenters();
      });
  
      return {
        workCenters,
        isLoading,
        columns,
        searchQuery,
        statusFilter,
        sortKey,
        sortOrder,
        currentPage,
        totalPages,
        from,
        to,
        total,
        showDeleteModal,
        deleteMessage,
        formatCurrency,
        handleSearch,
        handleSort,
        handlePageChange,
        resetFilters,
        confirmDelete,
        deleteWorkCenter
      };
    }
  };
  </script>
  
  <style scoped>
  /* Container styling */
.work-centers-container {
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
  box-shadow: 0 1px 8px rgba(0, 0, 0, 0.07);
  border: none;
  overflow: hidden;
  margin-bottom: 2rem;
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

.mb-4 {
  margin-bottom: 1.5rem;
}

/* Button styling */
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

.btn-secondary {
  background-color: #f1f5f9;
  color: #475569;
  border-color: #e2e8f0;
}

.btn-secondary:hover {
  background-color: #e2e8f0;
  color: #334155;
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
  padding: 0.4rem;
  font-size: 0.85rem;
}

/* Button group */
.btn-group {
  display: flex;
  gap: 0.35rem;
}

.btn-group .btn {
  border-radius: 6px;
}

/* Badge styling */
.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.35rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1;
  border-radius: 0.25rem;
  white-space: nowrap;
}

.badge-success {
  background-color: #dcfce7;
  color: #16a34a;
}

.badge-secondary {
  background-color: #f1f5f9;
  color: #64748b;
}

/* Search filter styling */
.filter-group {
  display: flex;
  flex-direction: column;
  margin-right: 1rem;
  min-width: 180px;
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

/* Loading state */
.text-center {
  text-align: center;
}

.p-5 {
  padding: 2.5rem;
}

.fa-spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.mt-2 {
  margin-top: 0.5rem;
}

.mt-3 {
  margin-top: 1rem;
}

.mr-2 {
  margin-right: 0.5rem;
}

/* Empty state styling */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3.5rem 1.5rem;
  text-align: center;
}

.empty-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 5rem;
  height: 5rem;
  border-radius: 50%;
  font-size: 2.5rem;
  margin-bottom: 1.5rem;
  color: #94a3b8;
  background-color: #f1f5f9;
}

.empty-state h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: #64748b;
  font-size: 0.95rem;
  max-width: 30rem;
  margin: 0 auto 1.5rem;
}

/* Table styling - assuming you have a DataTable component */
:deep(.data-table) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
}

:deep(.data-table th) {
  background-color: #f8fafc;
  font-weight: 600;
  color: #475569;
  text-align: left;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
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

/* Sort styling */
:deep(.sort-icon) {
  margin-left: 0.35rem;
  color: #94a3b8;
  font-size: 0.85rem;
}

:deep(.sort-active) {
  color: #3b82f6;
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

/* Pagination component styling */
:deep(.pagination) {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  background-color: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

:deep(.pagination-info) {
  color: #64748b;
  font-size: 0.9rem;
}

:deep(.pagination-buttons) {
  display: flex;
  gap: 0.25rem;
}

:deep(.pagination-buttons button) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 6px;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

:deep(.pagination-buttons button:hover) {
  background-color: #f1f5f9;
  color: #334155;
}

:deep(.pagination-buttons button.active) {
  background-color: #3b82f6;
  border-color: #3b82f6;
  color: white;
}

:deep(.pagination-buttons button:disabled) {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive styling */
@media (max-width: 768px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .card-header .btn {
    margin-top: 1rem;
    align-self: flex-start;
  }
  
  .btn-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    width: 100%;
  }
  
  .btn-group .btn {
    width: 100%;
  }
  
  :deep(.pagination) {
    flex-direction: column;
    gap: 1rem;
  }
  
  :deep(.pagination-info) {
    text-align: center;
  }
  
  :deep(.pagination-buttons) {
    justify-content: center;
  }
}

/* Small screen optimization */
@media (max-width: 576px) {
  .work-centers-container {
    padding: 1rem 0.75rem;
  }
  
  .card-body {
    padding: 1rem;
  }
  
  .card-header {
    padding: 1rem;
  }
  
  :deep(.data-table th),
  :deep(.data-table td) {
    padding: 0.75rem 0.5rem;
  }
  
  .btn-sm {
    width: 2.5rem;
    height: 2.5rem;
  }
}
  </style>