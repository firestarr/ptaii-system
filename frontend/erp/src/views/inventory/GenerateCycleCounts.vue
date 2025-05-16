<!-- src/views/inventory/GenerateCycleCounts.vue -->
<template>
    <div class="generate-cycle-counts">
      <div class="page-header">
        <h1>Generate Cycle Count Tasks</h1>
        <div class="page-actions">
          <router-link to="/cycle-counts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Cycle Counts
          </router-link>
        </div>
      </div>
  
      <div class="card">
        <div class="card-body">
          <form @submit.prevent="generateCycleCounts">
            <div class="row mb-4">
              <div class="col-md-6">
                <h4>1. Select Warehouse</h4>
                <div class="mb-3">
                  <label for="warehouse" class="form-label">Warehouse <span class="text-danger">*</span></label>
                  <select id="warehouse" v-model="form.warehouse_id" class="form-select" required>
                    <option value="">Select Warehouse</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                      {{ warehouse.name }}
                    </option>
                  </select>
                  <div v-if="errors.warehouse_id" class="text-danger mt-1">
                    {{ errors.warehouse_id }}
                  </div>
                </div>
              </div>
  
              <div class="col-md-6">
                <h4>2. Select Count Date</h4>
                <div class="mb-3">
                  <label for="count_date" class="form-label">Count Date <span class="text-danger">*</span></label>
                  <input 
                    type="date" 
                    id="count_date" 
                    v-model="form.count_date" 
                    class="form-control" 
                    required
                    :min="todayFormatted"
                  >
                  <div v-if="errors.count_date" class="text-danger mt-1">
                    {{ errors.count_date }}
                  </div>
                </div>
              </div>
            </div>
  
            <div class="row mb-4">
              <div class="col-12">
                <h4>3. Select Items</h4>
                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label">Items <span class="text-danger">*</span></label>
                    <div class="item-selection-controls">
                      <button type="button" class="btn btn-sm btn-outline-primary me-2" @click="showItemSelector">
                        <i class="fas fa-plus"></i> Add Items
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger" @click="clearSelectedItems" :disabled="!form.item_ids.length">
                        <i class="fas fa-trash"></i> Clear All
                      </button>
                    </div>
                  </div>
                  
                  <div v-if="!form.item_ids.length" class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No items selected. Please add items for cycle counting.
                  </div>
  
                  <div v-else class="selected-items-list">
                    <div class="table-responsive">
                      <table class="table table-hover">
                        <thead>
                          <tr>
                            <th>Item Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(itemId, index) in form.item_ids" :key="index">
                            <td>{{ getItemById(itemId)?.item_code }}</td>
                            <td>{{ getItemById(itemId)?.name }}</td>
                            <td>{{ getItemById(itemId)?.category?.name }}</td>
                            <td>{{ getItemById(itemId)?.current_stock }}</td>
                            <td>
                              <button type="button" class="btn btn-sm btn-outline-danger" @click="removeItem(index)">
                                <i class="fas fa-times"></i>
                              </button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  
                  <div v-if="errors.item_ids" class="text-danger mt-1">
                    {{ errors.item_ids }}
                  </div>
                </div>
              </div>
            </div>
  
            <div class="d-flex justify-content-end mt-4">
              <button type="button" class="btn btn-secondary me-2" @click="$router.push('/cycle-counts')">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Generate Cycle Count Tasks
              </button>
            </div>
          </form>
        </div>
      </div>
  
      <!-- Item Selector Modal -->
      <div v-if="showItemSelectorModal" class="modal-backdrop" @click="closeItemSelector"></div>
      <div v-if="showItemSelectorModal" class="modal item-selector-modal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Select Items for Cycle Counting</h5>
              <button type="button" class="btn-close" @click="closeItemSelector"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <div class="input-group">
                  <span class="input-group-text">
                    <i class="fas fa-search"></i>
                  </span>
                  <input 
                    type="text" 
                    class="form-control" 
                    placeholder="Search items by code or name..." 
                    v-model="itemSearchQuery"
                  >
                </div>
              </div>
  
              <div class="filter-controls mb-3">
                <div class="row">
                  <div class="col-md-6">
                    <select class="form-select" v-model="itemFilterCategory">
                      <option value="">All Categories</option>
                      <option v-for="category in categories" :key="category.category_id" :value="category.category_id">
                        {{ category.name }}
                      </option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <select class="form-select" v-model="itemFilterStatus">
                      <option value="">All Stock Status</option>
                      <option value="low">Low Stock</option>
                      <option value="normal">Normal Stock</option>
                      <option value="over">Overstocked</option>
                    </select>
                  </div>
                </div>
              </div>
  
              <div class="item-list-container">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>
                        <div class="form-check">
                          <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="selectAllItems"
                            v-model="selectAll"
                            @change="toggleSelectAll"
                          >
                          <label class="form-check-label" for="selectAllItems"></label>
                        </div>
                      </th>
                      <th>Item Code</th>
                      <th>Name</th>
                      <th>Category</th>
                      <th>Current Stock</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in filteredItems" :key="item.item_id">
                      <td>
                        <div class="form-check">
                          <input 
                            type="checkbox" 
                            class="form-check-input" 
                            :id="'item-' + item.item_id"
                            v-model="selectedModalItems"
                            :value="item.item_id"
                          >
                          <label class="form-check-label" :for="'item-' + item.item_id"></label>
                        </div>
                      </td>
                      <td>{{ item.item_code }}</td>
                      <td>{{ item.name }}</td>
                      <td>{{ item.category?.name }}</td>
                      <td :class="getStockStatusClass(item)">{{ item.current_stock }}</td>
                    </tr>
                    <tr v-if="filteredItems.length === 0">
                      <td colspan="5" class="text-center py-3">No items found matching your search criteria</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <div class="selected-count">
                {{ selectedModalItems.length }} items selected
              </div>
              <button type="button" class="btn btn-secondary" @click="closeItemSelector">Cancel</button>
              <button type="button" class="btn btn-primary" @click="confirmItemSelection">
                Add Selected Items
              </button>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Success Modal -->
      <div v-if="showSuccessModal" class="modal-backdrop"></div>
      <div v-if="showSuccessModal" class="modal" tabindex="-1" style="display: block;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title">Success!</h5>
              <button type="button" class="btn-close btn-close-white" @click="closeSuccessModal"></button>
            </div>
            <div class="modal-body">
              <div class="text-center mb-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3">Cycle Count Tasks Generated</h4>
                <p>{{ successMessage }}</p>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeSuccessModal">Close</button>
              <button type="button" class="btn btn-primary" @click="viewGeneratedCounts">
                View Cycle Counts
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'GenerateCycleCounts',
    data() {
      return {
        form: {
          warehouse_id: '',
          count_date: this.getTodayFormatted(),
          item_ids: []
        },
        errors: {},
        loading: false,
        warehouses: [],
        items: [],
        categories: [],
        showItemSelectorModal: false,
        itemSearchQuery: '',
        itemFilterCategory: '',
        itemFilterStatus: '',
        selectedModalItems: [],
        selectAll: false,
        showSuccessModal: false,
        successMessage: '',
        generatedCounts: []
      };
    },
    computed: {
      todayFormatted() {
        return this.getTodayFormatted();
      },
      filteredItems() {
        return this.items.filter(item => {
          // Search by name or code
          const matchesSearch = !this.itemSearchQuery || 
            item.name.toLowerCase().includes(this.itemSearchQuery.toLowerCase()) ||
            item.item_code.toLowerCase().includes(this.itemSearchQuery.toLowerCase());
          
          // Filter by category
          const matchesCategory = !this.itemFilterCategory || 
            item.category_id === this.itemFilterCategory;
          
          // Filter by stock status
          const matchesStatus = !this.itemFilterStatus;
          if (this.itemFilterStatus === 'low') {
            return matchesSearch && matchesCategory && 
                   item.minimum_stock && item.current_stock < item.minimum_stock;
          } else if (this.itemFilterStatus === 'normal') {
            return matchesSearch && matchesCategory && 
                   (!item.minimum_stock || item.current_stock >= item.minimum_stock) &&
                   (!item.maximum_stock || item.current_stock <= item.maximum_stock);
          } else if (this.itemFilterStatus === 'over') {
            return matchesSearch && matchesCategory && 
                   item.maximum_stock && item.current_stock > item.maximum_stock;
          }
          
          return matchesSearch && matchesCategory && matchesStatus;
        });
      }
    },
    mounted() {
      this.loadWarehouses();
      this.loadItems();
      this.loadCategories();
    },
    methods: {
      async loadWarehouses() {
        try {
          const response = await axios.get('/warehouses');
          this.warehouses = response.data.data || [];
        } catch (error) {
          console.error('Error loading warehouses:', error);
        }
      },
      async loadItems() {
        try {
          const response = await axios.get('/items');
          this.items = response.data.data || [];
        } catch (error) {
          console.error('Error loading items:', error);
        }
      },
      async loadCategories() {
        try {
          const response = await axios.get('/categories');
          this.categories = response.data.data || [];
        } catch (error) {
          console.error('Error loading categories:', error);
        }
      },
      getTodayFormatted() {
        const today = new Date();
        return today.toISOString().split('T')[0]; // Format as YYYY-MM-DD
      },
      getItemById(itemId) {
        return this.items.find(item => item.item_id === itemId);
      },
      getStockStatusClass(item) {
        if (item.minimum_stock && item.current_stock < item.minimum_stock) {
          return 'text-danger';
        } else if (item.maximum_stock && item.current_stock > item.maximum_stock) {
          return 'text-warning';
        }
        return '';
      },
      showItemSelector() {
        this.showItemSelectorModal = true;
        this.selectedModalItems = [...this.form.item_ids];
        this.updateSelectAllState();
      },
      closeItemSelector() {
        this.showItemSelectorModal = false;
        this.itemSearchQuery = '';
        this.itemFilterCategory = '';
        this.itemFilterStatus = '';
        this.selectedModalItems = [];
        this.selectAll = false;
      },
      confirmItemSelection() {
        this.form.item_ids = [...this.selectedModalItems];
        this.closeItemSelector();
      },
      toggleSelectAll() {
        if (this.selectAll) {
          this.selectedModalItems = this.filteredItems.map(item => item.item_id);
        } else {
          this.selectedModalItems = [];
        }
      },
      updateSelectAllState() {
        this.selectAll = this.filteredItems.length > 0 && 
          this.filteredItems.every(item => this.selectedModalItems.includes(item.item_id));
      },
      removeItem(index) {
        this.form.item_ids.splice(index, 1);
      },
      clearSelectedItems() {
        this.form.item_ids = [];
      },
      async generateCycleCounts() {
        this.errors = {};
        
        // Validate form
        let hasErrors = false;
        
        if (!this.form.warehouse_id) {
          this.errors.warehouse_id = 'Please select a warehouse';
          hasErrors = true;
        }
        
        if (!this.form.count_date) {
          this.errors.count_date = 'Please select a count date';
          hasErrors = true;
        }
        
        if (!this.form.item_ids.length) {
          this.errors.item_ids = 'Please select at least one item';
          hasErrors = true;
        }
        
        if (hasErrors) {
          return;
        }
        
        this.loading = true;
        
        try {
          const response = await axios.post('/cycle-counts/generate', this.form);
          
          if (response.data.success) {
            this.generatedCounts = response.data.data || [];
            this.successMessage = response.data.message || `${this.generatedCounts.length} cycle count tasks generated successfully`;
            this.showSuccessModal = true;
            
            // Reset form
            this.form = {
              warehouse_id: '',
              count_date: this.getTodayFormatted(),
              item_ids: []
            };
          } else {
            alert('Failed to generate cycle counts: ' + response.data.message);
          }
        } catch (error) {
          console.error('Error generating cycle counts:', error);
          
          if (error.response && error.response.data) {
            if (error.response.data.errors) {
              this.errors = error.response.data.errors;
            } else if (error.response.data.message) {
              alert('Error: ' + error.response.data.message);
            }
          } else {
            alert('An unexpected error occurred. Please try again.');
          }
        } finally {
          this.loading = false;
        }
      },
      closeSuccessModal() {
        this.showSuccessModal = false;
        this.successMessage = '';
      },
      viewGeneratedCounts() {
        this.$router.push('/cycle-counts');
      }
    },
    watch: {
      selectedModalItems() {
        this.updateSelectAllState();
      },
      filteredItems() {
        this.updateSelectAllState();
      }
    }
  };
  </script>
  
  <style scoped>
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .card {
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
  }
  
  .form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
  }
  
  h4 {
    color: #343a40;
    margin-bottom: 1rem;
    font-size: 1.2rem;
  }
  
  .selected-items-list {
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    max-height: 300px;
    overflow-y: auto;
  }
  
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1050;
  }
  
  .modal {
    z-index: 1055;
  }
  
  .item-selector-modal .modal-dialog {
    max-width: 800px;
  }
  
  .item-list-container {
    max-height: 400px;
    overflow-y: auto;
  }
  
  .selected-count {
    margin-right: auto;
    font-weight: 500;
  }
  
  .btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%);
  }
  </style>