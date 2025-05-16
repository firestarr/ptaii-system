<!-- src/views/manufacturing/RoutingList.vue -->
<template>
    <div class="routing-list-container">
      <SearchFilter
        v-model:value="searchQuery"
        placeholder="Cari kode routing, item, atau status..."
        @search="handleSearch"
        @clear="resetFilter"
      >
        <template #filters>
          <div class="filter-group">
            <label>Status</label>
            <select v-model="filters.status" @change="filterData">
              <option value="">Semua Status</option>
              <option value="Active">Aktif</option>
              <option value="Draft">Draft</option>
              <option value="Obsolete">Tidak Berlaku</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Item</label>
            <select v-model="filters.itemId" @change="filterData">
              <option value="">Semua Item</option>
              <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                {{ item.name }}
              </option>
            </select>
          </div>
        </template>
        <template #actions>
          <router-link to="/manufacturing/routings/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Routing
          </router-link>
        </template>
      </SearchFilter>
  
      <div class="card">
        <div class="card-body p-0">
          <DataTable
            :columns="columns"
            :items="filteredRoutings"
            :is-loading="isLoading"
            empty-title="Tidak ada data routing"
            empty-message="Tidak ada routing yang sesuai dengan filter Anda."
            initial-sort-key="routing_code"
            initial-sort-order="asc"
            @sort="handleSort"
          >
            <!-- Formatting the effective date -->
            <template #effective_date="{ value }">
              {{ formatDate(value) }}
            </template>
  
            <!-- Status column with badge -->
            <template #status="{ value }">
              <span
                class="badge"
                :class="{
                  'badge-success': value === 'Active',
                  'badge-warning': value === 'Draft',
                  'badge-secondary': value === 'Obsolete'
                }"
              >
                {{ value }}
              </span>
            </template>
  
            <!-- Actions column -->
            <template #actions="{ item }">
              <div class="d-flex gap-2 justify-content-end">
                <router-link
                  :to="`/manufacturing/routings/${item.routing_id}`"
                  class="btn btn-sm btn-secondary"
                  title="Lihat Detail"
                >
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link
                  :to="`/manufacturing/routings/${item.routing_id}/edit`"
                  class="btn btn-sm btn-primary"
                  title="Edit"
                >
                  <i class="fas fa-edit"></i>
                </router-link>
                <button
                  @click="confirmDelete(item)"
                  class="btn btn-sm btn-danger"
                  title="Hapus"
                >
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
            </template>
          </DataTable>
        </div>
      </div>
  
      <div class="card-footer">
        <PaginationComponent
          :current-page="pagination.currentPage"
          :total-pages="pagination.totalPages"
          :from="pagination.from"
          :to="pagination.to"
          :total="pagination.total"
          @page-changed="handlePageChange"
        />
      </div>
  
      <!-- Confirmation Modal for Delete -->
      <ConfirmationModal
        v-if="showDeleteModal"
        :title="'Hapus Routing'"
        :message="`Anda yakin ingin menghapus routing <strong>${selectedRouting?.routing_code}</strong>?<br>Tindakan ini tidak dapat dibatalkan.`"
        confirm-button-text="Hapus"
        confirm-button-class="btn btn-danger"
        @confirm="deleteRouting"
        @close="showDeleteModal = false"
      />
    </div>
  </template>
  
  <script>
  import { ref, reactive, onMounted } from 'vue';
  import axios from 'axios';
  
  
  export default {
    name: 'RoutingList',
    setup() {
      const routings = ref([]);
      const filteredRoutings = ref([]);
      const isLoading = ref(true);
      const items = ref([]);
      const searchQuery = ref('');
      const selectedRouting = ref(null);
      const showDeleteModal = ref(false);
  
      // Filters
      const filters = reactive({
        status: '',
        itemId: '',
      });
  
      // Sorting
      const sorting = reactive({
        field: 'routing_code',
        order: 'asc',
      });
  
      // Pagination
      const pagination = reactive({
        currentPage: 1,
        totalPages: 1,
        from: 1,
        to: 10,
        total: 0,
        perPage: 10,
      });
  
      // Table columns definition
      const columns = [
        { key: 'routing_code', label: 'Kode Routing', sortable: true },
        { key: 'item_name', label: 'Produk', sortable: true },
        { key: 'revision', label: 'Revisi', sortable: true },
        { key: 'effective_date', label: 'Tanggal Efektif', sortable: true },
        { key: 'status', label: 'Status', sortable: true },
      ];
  
      // Format date to local format
      const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          day: '2-digit',
          month: 'short',
          year: 'numeric',
        });
      };
  
      // Load routings data from API
        const loadRoutings = async () => {
        isLoading.value = true;
        try {
            const response = await axios.get('/routings', {
            params: {
                page: pagination.currentPage,
                per_page: pagination.perPage,
                sort_field: sorting.field,
                sort_order: sorting.order,
                search: searchQuery.value,
                status: filters.status,
                item_id: filters.itemId,
            },
            });

            console.log('Routings data:', response.data.data);

            // Check if response has the expected structure
            if (response.data && response.data.data) {
            // Add flat item_name property for each routing
            const routingsWithItemName = response.data.data.map(routing => ({
                ...routing,
                item_name: routing.item ? routing.item.name : '',
            }));

            routings.value = routingsWithItemName;
            filteredRoutings.value = routingsWithItemName;

            // Update pagination if meta is available
            if (response.data.meta) {
                pagination.currentPage = response.data.meta.current_page;
                pagination.totalPages = response.data.meta.last_page;
                pagination.from = response.data.meta.from || 0;
                pagination.to = response.data.meta.to || 0;
                pagination.total = response.data.meta.total;
            } else {
                // Alternative pagination structure (adjust based on your API)
                pagination.currentPage = response.data.current_page || 1;
                pagination.totalPages = response.data.last_page || 1;
                pagination.from = response.data.from || 0;
                pagination.to = response.data.to || 0;
                pagination.total = response.data.total || 0;
            }
            } else {
            console.error('Unexpected API response format:', response);
            routings.value = [];
            filteredRoutings.value = [];
            }
        } catch (error) {
            console.error('Error loading routings:', error);
            routings.value = [];
            filteredRoutings.value = [];
        } finally {
            isLoading.value = false;
        }
        };
  
      // Load all available items for the filter dropdown
      const loadItems = async () => {
        try {
          const response = await axios.get('/items');
          items.value = response.data.data;
        } catch (error) {
          console.error('Error loading items:', error);
        }
      };
  
      // Handler for search
      const handleSearch = () => {
        pagination.currentPage = 1;
        loadRoutings();
      };
  
      // Handler for filtering
      const filterData = () => {
        pagination.currentPage = 1;
        loadRoutings();
      };
  
      // Reset all filters
      const resetFilter = () => {
        searchQuery.value = '';
        filters.status = '';
        filters.itemId = '';
        pagination.currentPage = 1;
        loadRoutings();
      };
  
      // Handler for sorting
      const handleSort = ({ key, order }) => {
        sorting.field = key;
        sorting.order = order;
        loadRoutings();
      };
  
      // Handler for pagination
      const handlePageChange = (page) => {
        pagination.currentPage = page;
        loadRoutings();
      };
  
      // Confirm delete
      const confirmDelete = (routing) => {
        selectedRouting.value = routing;
        showDeleteModal.value = true;
      };
  
      // Delete routing
      const deleteRouting = async () => {
        try {
          await axios.delete(`/routings/${selectedRouting.value.routing_id}`);
          loadRoutings();
          showDeleteModal.value = false;
        } catch (error) {
          console.error('Error deleting routing:', error);
          // Show error message based on the response
          if (error.response && error.response.data && error.response.data.message) {
            alert(error.response.data.message);
          } else {
            alert('Gagal menghapus routing. Silakan coba lagi.');
          }
        }
      };
  
      // Load data on component mount
      onMounted(() => {
        loadRoutings();
        loadItems();
      });
  
      return {
        routings,
        filteredRoutings,
        isLoading,
        items,
        searchQuery,
        filters,
        columns,
        pagination,
        selectedRouting,
        showDeleteModal,
        formatDate,
        handleSearch,
        filterData,
        resetFilter,
        handleSort,
        handlePageChange,
        confirmDelete,
        deleteRouting,
      };
    },
  };
  </script>
  
  <style scoped>
  .routing-list-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  
  .card-footer {
    background-color: white;
    border-top: 1px solid var(--gray-200);
    padding: 1rem;
  }
  </style>