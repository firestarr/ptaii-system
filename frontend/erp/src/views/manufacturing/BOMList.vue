<!-- src/views/manufacturing/BOMList.vue -->
<template>
  <div class="bom-list-container">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Bill of Materials</h2>
        
        <SearchFilter 
          v-model:value="searchTerm" 
          placeholder="Search by BOM code or product name"
          @search="loadBOMs"
          @clear="resetSearch"
        >
          <template #filters>
            <div class="filter-group">
              <label for="status-filter">Status</label>
              <select id="status-filter" v-model="filters.status" @change="loadBOMs">
                <option value="">All Statuses</option>
                <option value="Draft">Draft</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Obsolete">Obsolete</option>
              </select>
            </div>
          </template>
          
          <template #actions>
            <div class="action-buttons">
              <router-link to="/manufacturing/boms/create" class="btn btn-primary">
                <i class="fas fa-plus mr-2"></i> New BOM
              </router-link>
              <router-link to="/manufacturing/boms/yield-based" class="btn btn-info">
                <i class="fas fa-flask mr-2"></i> Yield-Based BOM
              </router-link>
            </div>
          </template>
        </SearchFilter>
        
        <div class="table-container">
          <DataTable
            :columns="columns"
            :items="boms"
            :is-loading="isLoading"
            :empty-title="'No BOMs Found'"
            :empty-message="'No bill of materials match your search criteria.'"
            :initial-sort-key="'bom_code'"
            @sort="handleSort"
            keyField="bom_id"
          >
            <template #status="{ value }">
              <span :class="getStatusBadgeClass(value)">{{ value }}</span>
            </template>
            
            <template #effective_date="{ value }">
              {{ formatDate(value) }}
            </template>

            <template #has_yield_based="{ item }">
              <span v-if="hasYieldBasedComponents(item)" class="badge badge-info">
                <i class="fas fa-flask mr-1"></i> Yield-Based
              </span>
            </template>
            
            <template #actions="{ item }">
              <div class="action-group">
                <router-link :to="`/manufacturing/boms/${item.bom_id}`" class="btn btn-sm btn-info" title="View Details">
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link :to="`/manufacturing/boms/${item.bom_id}/edit`" class="btn btn-sm btn-primary" title="Edit">
                  <i class="fas fa-edit"></i>
                </router-link>
                <button @click="confirmDelete(item)" class="btn btn-sm btn-danger" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </template>
          </DataTable>
        </div>
        
        <div class="pagination-container">
          <PaginationComponent
            :current-page="currentPage"
            :total-pages="totalPages"
            :from="from"
            :to="to"
            :total="total"
            @page-changed="handlePageChange"
          />
        </div>
      </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <ConfirmationModal
      v-if="showDeleteModal"
      title="Delete BOM"
      :message="`Are you sure you want to delete BOM <strong>${bomToDelete?.bom_code}</strong>?<br>This action cannot be undone.`"
      confirm-button-text="Delete"
      confirm-button-class="btn btn-danger"
      @confirm="deleteBOM"
      @close="cancelDelete"
    />
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import DataTable from '@/components/common/DataTable.vue';

export default {
  name: 'BOMList',
  components: {
    DataTable
  },
  setup() {
    const boms = ref([]);
    const isLoading = ref(true);
    const searchTerm = ref('');
    const filters = reactive({
      status: ''
    });
    
    // Pagination
    const currentPage = ref(1);
    const totalPages = ref(1);
    const from = ref(0);
    const to = ref(0);
    const total = ref(0);
    const perPage = ref(10);
    
    // Sorting
    const sortField = ref('bom_code');
    const sortOrder = ref('asc');
    
    // Delete modal
    const showDeleteModal = ref(false);
    const bomToDelete = ref(null);
    
    const columns = [
      { key: 'bom_code', label: 'BOM Code', sortable: true },
      { key: 'item_name', label: 'Item', sortable: true },
      { key: 'revision', label: 'Revision', sortable: true },
      { key: 'effective_date', label: 'Effective Date', sortable: true, template: 'effective_date' },
      { key: 'has_yield_based', label: 'Type', template: 'has_yield_based', class: 'text-center', sortable: false },
      { key: 'status', label: 'Status', sortable: true, template: 'status', class: 'text-center' },
    ];
    
    const loadBOMs = async () => {
      isLoading.value = true;
      
      try {
            const response = await axios.get('/boms', {
              params: {
                page: currentPage.value,
                per_page: perPage.value,
                search: searchTerm.value,
                status: filters.status === '' ? null : filters.status,
                sort_field: sortField.value,
                sort_order: sortOrder.value
              }
            });
        
        // Add flat item_name property for each BOM
        const bomsWithItemName = response.data.data.map(bom => ({
          ...bom,
          item_name: bom.item ? bom.item.name : '',
        }));
        
        boms.value = bomsWithItemName;
        
        // Update pagination
        const meta = response.data.meta;
        currentPage.value = meta.current_page;
        totalPages.value = meta.last_page;
        from.value = (meta.current_page - 1) * meta.per_page + 1;
        to.value = Math.min(meta.current_page * meta.per_page, meta.total);
        total.value = meta.total;
      } catch (error) {
        console.error('Error loading BOMs:', error);
      } finally {
        isLoading.value = false;
      }
    };
    
    const resetSearch = () => {
      searchTerm.value = '';
      loadBOMs();
    };
    
    const handlePageChange = (page) => {
      currentPage.value = page;
      loadBOMs();
    };
    
    const handleSort = ({ key, order }) => {
      sortField.value = key;
      sortOrder.value = order;
      loadBOMs();
    };
    
    const getStatusBadgeClass = (status) => {
      if (!status) return 'badge badge-secondary';
      
      const statusLower = status.toLowerCase();
      if (statusLower === 'draft') return 'badge badge-secondary';
      if (statusLower === 'active') return 'badge badge-success';
      if (statusLower === 'inactive') return 'badge badge-warning';
      if (statusLower === 'obsolete') return 'badge badge-danger';
      return 'badge badge-secondary';
    };
    
    const formatDate = (dateString) => {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    };
    
    const hasYieldBasedComponents = (bom) => {
      if (!bom.bomLines || !Array.isArray(bom.bomLines)) return false;
      return bom.bomLines.some(line => line.is_yield_based);
    };
    
    const confirmDelete = (bom) => {
      bomToDelete.value = bom;
      showDeleteModal.value = true;
    };
    
    const cancelDelete = () => {
      bomToDelete.value = null;
      showDeleteModal.value = false;
    };
    
    const deleteBOM = async () => {
      if (!bomToDelete.value) return;
      
      try {
        await axios.delete(`/boms/${bomToDelete.value.bom_id}`);
        loadBOMs();
      } catch (error) {
        console.error('Error deleting BOM:', error);
        
        // Show error message if from backend
        if (error.response && error.response.data && error.response.data.message) {
          alert(error.response.data.message);
        } else {
          alert('Failed to delete BOM. It might be in use or there was a server error.');
        }
      } finally {
        cancelDelete();
      }
    };
    
    onMounted(() => {
      loadBOMs();
    });
    
    return {
      boms,
      isLoading,
      columns,
      searchTerm,
      filters,
      currentPage,
      totalPages,
      from,
      to,
      total,
      showDeleteModal,
      bomToDelete,
      loadBOMs,
      resetSearch,
      handlePageChange,
      handleSort,
      getStatusBadgeClass,
      formatDate,
      hasYieldBasedComponents,
      confirmDelete,
      cancelDelete,
      deleteBOM
    };
  }
};
</script>

<style scoped>
.bom-list-container {
  padding: 1rem;
}

.card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  padding: 1.5rem;
}

.card-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 1.5rem;
}

.pagination-container {
  margin-top: 1.5rem;
}

.badge {
  display: inline-block;
  padding: 0.35em 0.65em;
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1;
  color: #fff;
  text-align: center;
  white-space: nowrap;
  vertical-align: baseline;
  border-radius: 0.375rem;
}

.badge-secondary {
  background-color: var(--gray-500);
}

.badge-success {
  background-color: var(--success-color);
}

.badge-info {
  background-color: #0ea5e9;
}

.badge-warning {
  background-color: var(--warning-color);
}

.badge-danger {
  background-color: var(--danger-color);
}

.action-group {
  display: flex;
  gap: 0.25rem;
  justify-content: center;
}

.action-group .btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 0.25rem;
}

.table-container {
  margin-top: 1.5rem;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 150px;
}

.filter-group label {
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--gray-600);
}

.filter-group select {
  background-color: white;
  border: 1px solid var(--gray-300);
  border-radius: 0.375rem;
  padding: 0.5rem;
  font-size: 0.875rem;
  color: var(--gray-700);
}

.btn-primary {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
  font-weight: 500;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
}

.btn-info {
  background-color: #0ea5e9;
  border-color: #0ea5e9;
  color: white;
  font-weight: 500;
}

.btn-info:hover {
  background-color: #0284c7;
}

.btn-danger {
  background-color: var(--danger-color);
  border-color: var(--danger-color);
  color: white;
}

.btn-danger:hover {
  background-color: #b91c1c;
}

@media (max-width: 768px) {
  .action-buttons {
    flex-direction: column;
    width: 100%;
  }
  
  .action-buttons .btn {
    width: 100%;
    margin-bottom: 0.5rem;
  }
}
</style>