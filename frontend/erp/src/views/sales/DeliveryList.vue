<!-- src/views/sales/DeliveryList.vue -->
<template>
    <div class="deliveries">
      <!-- Search and Filter Section -->
      <div class="page-actions">
        <div class="search-filter">
          <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Cari pengiriman..."
              @input="handleSearch"
            />
            <button v-if="searchQuery" @click="clearSearch" class="clear-search">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="filters">
            <select v-model="statusFilter" @change="applyFilters">
              <option value="">Semua Status</option>
              <option value="Pending">Menunggu</option>
              <option value="In Transit">Dalam Pengiriman</option>
              <option value="Completed">Selesai</option>
              <option value="Cancelled">Dibatalkan</option>
            </select>

            <select v-model="dateRangeFilter" @change="applyFilters">
              <option value="all">Semua Waktu</option>
              <option value="today">Hari Ini</option>
              <option value="week">Minggu Ini</option>
              <option value="month">Bulan Ini</option>
              <option value="custom">Kustom</option>
            </select>
          </div>
        </div>

        <div class="btn-group">
          <button class="btn btn-primary" @click="createDelivery">
            <i class="fas fa-plus"></i> Buat Pengiriman
          </button>
          <button class="btn btn-info" @click="createFromOutstanding">
            <i class="fas fa-list-check"></i> Buat dari Outstanding
          </button>
        </div>
      </div>

      <!-- Custom Date Range (when Custom is selected) -->
      <div v-if="dateRangeFilter === 'custom'" class="custom-date-range">
        <div class="date-range-inputs">
          <div class="filter-group">
            <label for="startDate">Tanggal Mulai</label>
            <input
              type="date"
              id="startDate"
              v-model="customDateRange.startDate"
              @change="applyFilters"
            />
          </div>

          <div class="filter-group">
            <label for="endDate">Tanggal Akhir</label>
            <input
              type="date"
              id="endDate"
              v-model="customDateRange.endDate"
              @change="applyFilters"
            />
          </div>
        </div>
      </div>

      <!-- Deliveries Table -->
      <div class="card table-container">
        <div v-if="isLoading" class="loading-indicator">
          <i class="fas fa-spinner fa-spin"></i> Memuat data pengiriman...
        </div>

        <div v-else-if="filteredDeliveries.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-truck"></i>
          </div>
          <h3>Tidak ada pengiriman ditemukan</h3>
          <p>Coba sesuaikan pencarian atau filter, atau buat pengiriman baru.</p>
        </div>

        <table v-else class="data-table">
          <thead>
            <tr>
              <th @click="sortBy('delivery_number')" :class="{ sortable: true, active: sortKey === 'delivery_number' }">
                No. Pengiriman
                <i v-if="sortKey === 'delivery_number'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('delivery_date')" :class="{ sortable: true, active: sortKey === 'delivery_date' }">
                Tanggal
                <i v-if="sortKey === 'delivery_date'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>No. SO</th>
              <th>Pelanggan</th>
              <th @click="sortBy('status')" :class="{ sortable: true, active: sortKey === 'status' }">
                Status
                <i v-if="sortKey === 'status'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Metode Pengiriman</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="delivery in paginatedDeliveries" :key="delivery.delivery_id">
              <td class="delivery-number">{{ delivery.delivery_number }}</td>
              <td>{{ formatDate(delivery.delivery_date) }}</td>
              <td>{{ delivery.salesOrder?.so_number || '-' }}</td>
              <td>{{ delivery.customer?.name || '-' }}</td>
              <td>
                <span class="status-badge" :class="getStatusClass(delivery.status)">
                  {{ getStatusLabel(delivery.status) }}
                </span>
              </td>
              <td>{{ delivery.shipping_method || '-' }}</td>
              <td class="actions-cell">
                <button class="btn-icon view-btn" title="Lihat Detail" @click="viewDelivery(delivery)">
                  <i class="fas fa-eye"></i>
                </button>
                <button
                  v-if="canEdit(delivery)"
                  class="btn-icon edit-btn"
                  title="Edit Pengiriman"
                  @click="editDelivery(delivery)"
                >
                  <i class="fas fa-edit"></i>
                </button>
                <button
                  v-if="canComplete(delivery)"
                  class="btn-icon complete-btn"
                  title="Selesaikan Pengiriman"
                  @click="completeDelivery(delivery)"
                >
                  <i class="fas fa-check"></i>
                </button>
                <button
                  v-if="canCancel(delivery)"
                  class="btn-icon delete-btn"
                  title="Batalkan Pengiriman"
                  @click="confirmCancel(delivery)"
                >
                  <i class="fas fa-times"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="filteredDeliveries.length > 0" class="pagination-wrapper">
          <PaginationComponent
            :current-page="currentPage"
            :total-pages="totalPages"
            :from="paginationInfo.from"
            :to="paginationInfo.to"
            :total="filteredDeliveries.length"
            @page-changed="goToPage"
          />
        </div>
      </div>

      <!-- Cancel Confirmation Modal -->
      <ConfirmationModal
        v-if="showCancelModal"
        title="Konfirmasi Pembatalan"
        :message="`Apakah Anda yakin ingin membatalkan pengiriman <strong>${deliveryToCancel.delivery_number}</strong>?`"
        confirm-button-text="Batalkan"
        confirm-button-class="btn btn-danger"
        @confirm="cancelDelivery"
        @close="showCancelModal = false"
      />

      <!-- Complete Confirmation Modal -->
      <ConfirmationModal
        v-if="showCompleteModal"
        title="Konfirmasi Selesai"
        :message="`Apakah Anda yakin ingin menyelesaikan pengiriman <strong>${deliveryToComplete.delivery_number}</strong>?`"
        confirm-button-text="Selesaikan"
        confirm-button-class="btn btn-success"
        @confirm="confirmCompleteDelivery"
        @close="showCompleteModal = false"
      />
    </div>
  </template>

  <script>
  import { ref, computed, onMounted } from 'vue';
  import { useRouter } from 'vue-router';
  import axios from 'axios';

  export default {
    name: 'DeliveryList',
    setup() {
      const router = useRouter();
      const deliveries = ref([]);
      const isLoading = ref(true);

      // Search and filtering
      const searchQuery = ref('');
      const statusFilter = ref('');
      const dateRangeFilter = ref('month');
      const customDateRange = ref({
        startDate: new Date().toISOString().substr(0, 10),
        endDate: new Date().toISOString().substr(0, 10)
      });

      // Sorting
      const sortKey = ref('delivery_date');
      const sortOrder = ref('desc');

      // Pagination
      const currentPage = ref(1);
      const itemsPerPage = ref(10);

      // Modals
      const showCancelModal = ref(false);
      const showCompleteModal = ref(false);
      const deliveryToCancel = ref({});
      const deliveryToComplete = ref({});

      // Fetch data
      const fetchDeliveries = async () => {
        isLoading.value = true;
        try {
          const response = await axios.get('/deliveries');
          deliveries.value = response.data.data;
        } catch (error) {
          console.error('Error fetching deliveries:', error);
          alert('Terjadi kesalahan saat memuat data pengiriman');
        } finally {
          isLoading.value = false;
        }
      };

      // Computed properties
      const filteredDeliveries = computed(() => {
        let result = [...deliveries.value];

        // Apply search filter
        if (searchQuery.value) {
          const query = searchQuery.value.toLowerCase();
          result = result.filter(delivery =>
            delivery.delivery_number?.toLowerCase().includes(query) ||
            delivery.customer?.name?.toLowerCase().includes(query) ||
            delivery.salesOrder?.so_number?.toLowerCase().includes(query) ||
            delivery.shipping_method?.toLowerCase().includes(query)
          );
        }

        // Apply status filter
        if (statusFilter.value) {
          result = result.filter(delivery => delivery.status === statusFilter.value);
        }

        // Apply date range filter
        if (dateRangeFilter.value !== 'all') {
          const today = new Date();
          today.setHours(0, 0, 0, 0);

          const firstDayOfWeek = new Date(today);
          firstDayOfWeek.setDate(firstDayOfWeek.getDate() - firstDayOfWeek.getDay());

          const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

          switch (dateRangeFilter.value) {
            case 'today': {
              result = result.filter(delivery => {
                const date = new Date(delivery.delivery_date);
                return date.setHours(0, 0, 0, 0) === today.getTime();
              });
              break;
            }
            case 'week': {
              result = result.filter(delivery => {
                const date = new Date(delivery.delivery_date);
                return date >= firstDayOfWeek && date <= today;
              });
              break;
            }
            case 'month': {
              result = result.filter(delivery => {
                const date = new Date(delivery.delivery_date);
                return date >= firstDayOfMonth && date <= today;
              });
              break;
            }
            case 'custom': {
              const startDate = new Date(customDateRange.value.startDate);
              const endDate = new Date(customDateRange.value.endDate);
              endDate.setHours(23, 59, 59, 999);

              result = result.filter(delivery => {
                const date = new Date(delivery.delivery_date);
                return date >= startDate && date <= endDate;
              });
              break;
            }
          }
        }

        // Apply sorting
        result.sort((a, b) => {
          let valA = a[sortKey.value];
          let valB = b[sortKey.value];

          // Handle dates
          if (sortKey.value === 'delivery_date') {
            valA = new Date(valA);
            valB = new Date(valB);
          } else {
            // Convert to strings for string comparison
            valA = String(valA || '').toLowerCase();
            valB = String(valB || '').toLowerCase();
          }

          if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
          if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
          return 0;
        });

        return result;
      });

      const paginatedDeliveries = computed(() => {
        const start = (currentPage.value - 1) * itemsPerPage.value;
        const end = start + itemsPerPage.value;
        return filteredDeliveries.value.slice(start, end);
      });

      const totalPages = computed(() => {
        return Math.ceil(filteredDeliveries.value.length / itemsPerPage.value);
      });

      const paginationInfo = computed(() => {
        const total = filteredDeliveries.value.length;
        const from = total === 0 ? 0 : (currentPage.value - 1) * itemsPerPage.value + 1;
        const to = Math.min(from + itemsPerPage.value - 1, total);

        return { from, to, total };
      });

      // Methods
      const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          day: '2-digit',
          month: 'short',
          year: 'numeric'
        });
      };

      const getStatusLabel = (status) => {
        switch (status) {
          case 'Pending': return 'Menunggu';
          case 'In Transit': return 'Dalam Pengiriman';
          case 'Completed': return 'Selesai';
          case 'Cancelled': return 'Dibatalkan';
          default: return status;
        }
      };

      const getStatusClass = (status) => {
        switch (status) {
          case 'Pending': return 'status-pending';
          case 'In Transit': return 'status-transit';
          case 'Completed': return 'status-completed';
          case 'Cancelled': return 'status-cancelled';
          default: return '';
        }
      };

      const canEdit = (delivery) => {
        return delivery.status !== 'Completed' && delivery.status !== 'Cancelled';
      };

      const canComplete = (delivery) => {
        return delivery.status === 'In Transit';
      };

      const canCancel = (delivery) => {
        return delivery.status !== 'Completed' && delivery.status !== 'Cancelled';
      };

      const handleSearch = () => {
        currentPage.value = 1;
      };

      const clearSearch = () => {
        searchQuery.value = '';
        handleSearch();
      };

      const applyFilters = () => {
        currentPage.value = 1;
      };

      const sortBy = (key) => {
        if (sortKey.value === key) {
          sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
        } else {
          sortKey.value = key;
          sortOrder.value = 'asc';
        }
      };

      const goToPage = (page) => {
        currentPage.value = page;
      };

      // Navigation and actions
      const createDelivery = () => {
        router.push('/sales/deliveries/create');
      };

      const createFromOutstanding = () => {
        router.push('/sales/deliveries/create-consolidated');
      };

      const viewDelivery = (delivery) => {
              if (delivery.is_consolidated) {
                router.push(`/sales/encdeliveries/${delivery.delivery_id}`);
              } else {
                router.push(`/sales/deliveries/${delivery.delivery_id}`);
              }
            };

      const editDelivery = (delivery) => {
        if (delivery.is_consolidated) {
                router.push(`/sales/encdeliveries/${delivery.delivery_id}/edit`);
              } else {
                router.push(`/sales/deliveries/${delivery.delivery_id}/edit`);
              }   
      };

      const confirmCancel = (delivery) => {
        deliveryToCancel.value = delivery;
        showCancelModal.value = true;
      };

      const cancelDelivery = async () => {
        try {
          await axios.put(`/deliveries/${deliveryToCancel.value.delivery_id}`, {
            ...deliveryToCancel.value,
            status: 'Cancelled'
          });

          // Update local state
          const index = deliveries.value.findIndex(
            d => d.delivery_id === deliveryToCancel.value.delivery_id
          );
          if (index !== -1) {
            deliveries.value[index].status = 'Cancelled';
          }

          showCancelModal.value = false;
          alert('Pengiriman berhasil dibatalkan!');
        } catch (error) {
          console.error('Error cancelling delivery:', error);
          alert('Terjadi kesalahan saat membatalkan pengiriman');
        }
      };

      const completeDelivery = (delivery) => {
        deliveryToComplete.value = delivery;
        showCompleteModal.value = true;
      };

      const confirmCompleteDelivery = async () => {
        try {
          await axios.post(`/deliveries/${deliveryToComplete.value.delivery_id}/complete`);

          // Update local state
          const index = deliveries.value.findIndex(
            d => d.delivery_id === deliveryToComplete.value.delivery_id
          );
          if (index !== -1) {
            deliveries.value[index].status = 'Completed';
          }

          showCompleteModal.value = false;
          alert('Pengiriman berhasil diselesaikan!');
        } catch (error) {
          console.error('Error completing delivery:', error);
          alert('Terjadi kesalahan saat menyelesaikan pengiriman');
        }
      };

      onMounted(() => {
        fetchDeliveries();
      });

      return {
        deliveries,
        filteredDeliveries,
        paginatedDeliveries,
        isLoading,
        searchQuery,
        statusFilter,
        dateRangeFilter,
        customDateRange,
        sortKey,
        sortOrder,
        currentPage,
        totalPages,
        paginationInfo,
        showCancelModal,
        showCompleteModal,
        deliveryToCancel,
        deliveryToComplete,
        formatDate,
        getStatusLabel,
        getStatusClass,
        canEdit,
        canComplete,
        canCancel,
        handleSearch,
        clearSearch,
        applyFilters,
        sortBy,
        goToPage,
        createDelivery,
        createFromOutstanding,
        viewDelivery,
        editDelivery,
        confirmCancel,
        cancelDelivery,
        completeDelivery,
        confirmCompleteDelivery
      };
    }
  };
  </script>

  <style scoped>
  .deliveries {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .page-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
  }

  .search-filter {
    display: flex;
    flex-grow: 1;
    gap: 1rem;
    align-items: center;
  }

  .search-box {
    position: relative;
    width: 100%;
    max-width: 400px;
  }

  .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
  }

  .search-box input {
    width: 100%;
    padding: 0.625rem 2.5rem 0.625rem 2.25rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }

  .clear-search {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
  }

  .filters {
    display: flex;
    gap: 1rem;
  }

  .filters select {
    padding: 0.625rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background-color: white;
  }

  .custom-date-range {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
  }

  .date-range-inputs {
    display: flex;
    gap: 1rem;
  }

  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }

  .filter-group label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
  }

  .filter-group input {
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }

  .table-container {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
  }

  .data-table {
    width: 100%;
    border-collapse: collapse;
  }

  .data-table th,
  .data-table td {
    padding: 0.75rem 1rem;
    text-align: left;
  }

  .data-table th {
    background-color: #f8fafc;
    font-weight: 500;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
  }

  .data-table th.sortable {
    cursor: pointer;
    user-select: none;
  }

  .data-table th.sortable:hover {
    background-color: #f1f5f9;
  }

  .data-table th.active {
    color: #2563eb;
  }

  .data-table tr:hover td {
    background-color: #f8fafc;
  }

  .data-table td {
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
  }

  .delivery-number {
    font-weight: 500;
    color: #2563eb;
  }

  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
  }

  .status-pending {
    background-color: #e2e8f0;
    color: #475569;
  }

  .status-transit {
    background-color: #dbeafe;
    color: #2563eb;
  }

  .status-completed {
    background-color: #d1fae5;
    color: #059669;
  }

  .status-cancelled {
    background-color: #fee2e2;
    color: #dc2626;
  }

  .actions-cell {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
  }

  .btn-icon {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 0.375rem;
    border-radius: 0.25rem;
    transition: background-color 0.2s, color 0.2s;
  }

  .btn-icon:hover {
    background-color: #f1f5f9;
  }

  .view-btn:hover {
    color: #2563eb;
  }

  .edit-btn:hover {
    color: #f59e0b;
  }

  .complete-btn:hover {
    color: #059669;
  }

  .delete-btn:hover {
    color: #dc2626;
  }

  .pagination-wrapper {
    padding: 1rem;
    border-top: 1px solid #e2e8f0;
  }

  .loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    color: #64748b;
  }

  .loading-indicator i {
    margin-right: 0.5rem;
  }

  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
    color: #64748b;
  }

  .empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #cbd5e1;
  }

  .empty-state h3 {
    font-size: 1.25rem;
    margin: 0 0 0.5rem 0;
    color: #1e293b;
  }

  .empty-state p {
    margin: 0;
    max-width: 24rem;
  }

  .btn {
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    transition: background-color 0.2s, color 0.2s;
  }

  .btn-group {
    display: flex;
    gap: 0.5rem;
  }

  .btn-primary {
    background-color: #2563eb;
    color: white;
  }

  .btn-primary:hover {
    background-color: #1d4ed8;
  }

  .btn-info {
    background-color: #0ea5e9;
    color: white;
  }

  .btn-info:hover {
    background-color: #0284c7;
  }

  .btn-danger {
    background-color: #dc2626;
    color: white;
  }

  .btn-danger:hover {
    background-color: #b91c1c;
  }

  .btn-success {
    background-color: #059669;
    color: white;
  }

  .btn-success:hover {
    background-color: #047857;
  }

  @media (max-width: 768px) {
    .page-actions,
    .search-filter {
      flex-direction: column;
      align-items: stretch;
    }

    .date-range-inputs {
      flex-direction: column;
    }

    .actions-cell {
      flex-direction: column;
      align-items: center;
    }
  }
  </style>
