<!-- src/views/inventory/BatchesList.vue -->
<template>
    <div class="batches-list">
      <div class="page-header">
        <div class="header-actions">
          <h2 class="page-title">
            <router-link :to="`/items/${itemId}`" class="back-link">
              <i class="fas fa-arrow-left"></i>
            </router-link>
            {{ itemName ? `Batches for ${itemName}` : 'Item Batches' }}
          </h2>
          <router-link :to="`/items/${itemId}/batches/create`" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Batch
          </router-link>
        </div>
        
        <div class="header-filters">
          <div class="search-field">
            <i class="fas fa-search"></i>
            <input 
              type="text" 
              v-model="searchQuery" 
              placeholder="Search batches..." 
              @input="filterBatches"
            />
            <button v-if="searchQuery" @click="clearSearch" class="clear-btn">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div class="filter-options">
            <select v-model="expiryFilter" @change="filterBatches">
              <option value="all">All Batches</option>
              <option value="expired">Expired</option>
              <option value="near">Near Expiry (30 days)</option>
              <option value="valid">Valid</option>
            </select>
          </div>
        </div>
      </div>
  
      <div v-if="loading" class="loading-section">
        <div class="spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading batches...</p>
      </div>
  
      <div v-else-if="filteredBatches.length === 0" class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-box-open"></i>
        </div>
        <h3>No Batches Found</h3>
        <p v-if="searchQuery || expiryFilter !== 'all'">
          Try changing your search or filter criteria
        </p>
        <p v-else>
          Start by adding a new batch for this item
        </p>
        <router-link :to="`/items/${itemId}/batches/create`" class="btn btn-primary">
          Add New Batch
        </router-link>
      </div>
  
      <div v-else class="batches-table-container">
        <table class="batches-table">
          <thead>
            <tr>
              <th @click="sortBy('batch_number')" class="sortable">
                Batch Number
                <i v-if="sortColumn === 'batch_number'" 
                  :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('expiry_date')" class="sortable">
                Expiry Date
                <i v-if="sortColumn === 'expiry_date'" 
                  :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('manufacturing_date')" class="sortable">
                Manufacturing Date
                <i v-if="sortColumn === 'manufacturing_date'" 
                  :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Lot Number</th>
              <th @click="sortBy('days_until_expiry')" class="sortable">
                Expiry Status
                <i v-if="sortColumn === 'days_until_expiry'" 
                  :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="batch in filteredBatches" :key="batch.batch_id" 
                :class="{
                  'expired-row': batch.is_expired, 
                  'near-expiry-row': !batch.is_expired && batch.days_until_expiry <= 30
                }">
              <td>{{ batch.batch_number }}</td>
              <td>{{ formatDate(batch.expiry_date) || 'N/A' }}</td>
              <td>{{ formatDate(batch.manufacturing_date) || 'N/A' }}</td>
              <td>{{ batch.lot_number || 'N/A' }}</td>
              <td>
                <span :class="getExpiryStatusClass(batch)">
                  {{ getExpiryStatusText(batch) }}
                </span>
              </td>
              <td class="actions-cell">
                <router-link :to="`/items/${itemId}/batches/${batch.batch_id}`" class="btn btn-sm btn-info">
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link :to="`/items/${itemId}/batches/${batch.batch_id}/edit`" class="btn btn-sm btn-primary">
                  <i class="fas fa-edit"></i>
                </router-link>
                <button @click="confirmDelete(batch)" class="btn btn-sm btn-danger">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
  
      <!-- Confirmation Modal -->
      <div v-if="showDeleteModal" class="modal-backdrop">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button @click="showDeleteModal = false" class="close-btn">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete batch <strong>{{ batchToDelete.batch_number }}</strong>?</p>
            <p v-if="deleteError" class="error-message">{{ deleteError }}</p>
          </div>
          <div class="modal-footer">
            <button @click="showDeleteModal = false" class="btn btn-secondary">Cancel</button>
            <button @click="deleteBatch()" :disabled="deleting" class="btn btn-danger">
              <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'BatchesList',
    props: {
      itemId: {
        type: [String, Number],
        required: true
      }
    },
    data() {
      return {
        batches: [],
        itemName: '',
        loading: true,
        searchQuery: '',
        expiryFilter: 'all',
        sortColumn: 'batch_number',
        sortDirection: 'asc',
        filteredBatches: [],
        showDeleteModal: false,
        batchToDelete: {},
        deleting: false,
        deleteError: ''
      };
    },
    created() {
      this.fetchItemDetails();
      this.fetchBatches();
    },
    methods: {
      async fetchItemDetails() {
        try {
          const response = await axios.get(`/items/${this.itemId}`);
          if (response.data.success) {
            this.itemName = response.data.data.name;
          }
        } catch (error) {
          console.error('Error fetching item details:', error);
        }
      },
      async fetchBatches() {
        this.loading = true;
        try {
          const response = await axios.get(`/items/${this.itemId}/batches`);
          if (response.data.success) {
            this.batches = response.data.data.map(batch => ({
              ...batch,
              is_expired: this.isExpired(batch.expiry_date),
              days_until_expiry: this.daysUntilExpiry(batch.expiry_date)
            }));
            this.filterBatches();
          }
        } catch (error) {
          console.error('Error fetching batches:', error);
        } finally {
          this.loading = false;
        }
      },
      formatDate(dateString) {
        if (!dateString) return null;
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      },
      isExpired(dateString) {
        if (!dateString) return false;
        const expiryDate = new Date(dateString);
        const today = new Date();
        return expiryDate < today;
      },
      daysUntilExpiry(dateString) {
        if (!dateString) return null;
        const expiryDate = new Date(dateString);
        const today = new Date();
        if (expiryDate < today) return 0;
        
        // Calculate difference in days
        const diffTime = expiryDate - today;
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      },
      getExpiryStatusText(batch) {
        if (!batch.expiry_date) return 'No Expiry Date';
        if (batch.is_expired) return 'Expired';
        if (batch.days_until_expiry <= 30) return `Expires in ${batch.days_until_expiry} days`;
        return 'Valid';
      },
      getExpiryStatusClass(batch) {
        if (!batch.expiry_date) return 'status-na';
        if (batch.is_expired) return 'status-expired';
        if (batch.days_until_expiry <= 30) return 'status-warning';
        return 'status-valid';
      },
      clearSearch() {
        this.searchQuery = '';
        this.filterBatches();
      },
      filterBatches() {
        let filtered = [...this.batches];
        
        // Apply search filter
        if (this.searchQuery) {
          const query = this.searchQuery.toLowerCase();
          filtered = filtered.filter(batch => 
            batch.batch_number.toLowerCase().includes(query) ||
            (batch.lot_number && batch.lot_number.toLowerCase().includes(query))
          );
        }
        
        // Apply expiry filter
        if (this.expiryFilter !== 'all') {
          switch (this.expiryFilter) {
            case 'expired':
              filtered = filtered.filter(batch => batch.is_expired);
              break;
            case 'near':
              filtered = filtered.filter(batch => !batch.is_expired && batch.days_until_expiry <= 30);
              break;
            case 'valid':
              filtered = filtered.filter(batch => !batch.is_expired && batch.days_until_expiry > 30);
              break;
          }
        }
        
        // Apply sorting
        this.applySorting(filtered);
      },
      sortBy(column) {
        if (this.sortColumn === column) {
          this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          this.sortColumn = column;
          this.sortDirection = 'asc';
        }
        
        this.filterBatches();
      },
      applySorting(batches) {
        const direction = this.sortDirection === 'asc' ? 1 : -1;
        
        this.filteredBatches = batches.sort((a, b) => {
          let valueA = a[this.sortColumn];
          let valueB = b[this.sortColumn];
          
          if (this.sortColumn === 'expiry_date' || this.sortColumn === 'manufacturing_date') {
            valueA = valueA ? new Date(valueA).getTime() : 0;
            valueB = valueB ? new Date(valueB).getTime() : 0;
          }
          
          if (valueA < valueB) return -1 * direction;
          if (valueA > valueB) return 1 * direction;
          return 0;
        });
      },
      confirmDelete(batch) {
        this.batchToDelete = batch;
        this.showDeleteModal = true;
        this.deleteError = '';
      },
      async deleteBatch() {
        this.deleting = true;
        this.deleteError = '';
        
        try {
          const response = await axios.delete(`/items/${this.itemId}/batches/${this.batchToDelete.batch_id}`);
          if (response.data.success) {
            // Remove the batch from the batches array
            this.batches = this.batches.filter(batch => batch.batch_id !== this.batchToDelete.batch_id);
            this.filterBatches();
            this.showDeleteModal = false;
            
            // Show success message
            this.$emit('show-notification', {
              type: 'success',
              message: 'Batch deleted successfully'
            });
          }
        } catch (error) {
          console.error('Error deleting batch:', error);
          if (error.response && error.response.data && error.response.data.message) {
            this.deleteError = error.response.data.message;
          } else {
            this.deleteError = 'An error occurred while deleting the batch. Please try again.';
          }
        } finally {
          this.deleting = false;
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .batches-list {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
  }
  
  .page-header {
    margin-bottom: 1.5rem;
  }
  
  .header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }
  
  .page-title {
    display: flex;
    align-items: center;
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
  }
  
  .back-link {
    margin-right: 0.75rem;
    color: var(--gray-600);
    transition: color 0.2s;
  }
  
  .back-link:hover {
    color: var(--primary-color);
  }
  
  .header-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
  }
  
  .search-field {
    position: relative;
    flex-grow: 1;
    max-width: 400px;
  }
  
  .search-field i {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .search-field input {
    width: 100%;
    padding: 0.625rem 2.25rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .search-field input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .clear-btn {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
  }
  
  .filter-options select {
    padding: 0.625rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    background-color: white;
    font-size: 0.875rem;
  }
  
  .loading-section {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    color: var(--gray-500);
  }
  
  .spinner {
    font-size: 2rem;
    margin-bottom: 1rem;
  }
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .empty-icon {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
  }
  
  .empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
  }
  
  .empty-state p {
    color: var(--gray-500);
    margin-bottom: 1.5rem;
    max-width: 24rem;
  }
  
  .batches-table-container {
    overflow-x: auto;
  }
  
  .batches-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .batches-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .batches-table th.sortable {
    cursor: pointer;
    user-select: none;
  }
  
  .batches-table th.sortable:hover {
    background-color: var(--gray-100);
  }
  
  .batches-table th i {
    margin-left: 0.25rem;
  }
  
  .batches-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .batches-table tr:hover td {
    background-color: var(--gray-50);
  }
  
  .batches-table .actions-cell {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
  }
  
  .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .btn-info {
    background-color: #3b82f6;
    color: white;
  }
  
  .btn-info:hover {
    background-color: #2563eb;
  }
  
  .btn-danger {
    background-color: #ef4444;
    color: white;
  }
  
  .btn-danger:hover {
    background-color: #dc2626;
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-300);
  }
  
  .btn i {
    margin-right: 0.5rem;
  }
  
  .btn-sm i {
    margin-right: 0;
  }
  
  .expired-row {
    background-color: rgba(239, 68, 68, 0.1);
  }
  
  .near-expiry-row {
    background-color: rgba(234, 179, 8, 0.1);
  }
  
  .status-expired {
    color: #ef4444;
    font-weight: 500;
  }
  
  .status-warning {
    color: #f59e0b;
    font-weight: 500;
  }
  
  .status-valid {
    color: #10b981;
    font-weight: 500;
  }
  
  .status-na {
    color: var(--gray-500);
    font-style: italic;
  }
  
  /* Modal styles */
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 100;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 400px;
    overflow: hidden;
  }
  
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
  }
  
  .close-btn {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    font-size: 1.25rem;
  }
  
  .modal-body {
    padding: 1.5rem;
  }
  
  .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .error-message {
    color: #ef4444;
    margin-top: 0.75rem;
    font-size: 0.875rem;
  }
  
  @media (max-width: 640px) {
    .header-actions {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .header-filters {
      flex-direction: column;
      align-items: stretch;
    }
    
    .search-field {
      max-width: none;
    }
    
    .batches-table th:nth-child(3),
    .batches-table td:nth-child(3),
    .batches-table th:nth-child(4),
    .batches-table td:nth-child(4) {
      display: none;
    }
  }
  </style>