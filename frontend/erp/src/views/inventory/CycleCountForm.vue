<!-- src/views/inventory/CycleCountForm.vue -->
<template>
    <div class="cycle-count-form">
      <div class="page-header">
        <h1>{{ isEditMode ? 'Edit Cycle Count' : 'Record Cycle Count' }}</h1>
        <div class="page-actions">
          <router-link to="/cycle-counts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Cycle Counts
          </router-link>
        </div>
      </div>
  
      <div class="card">
        <div class="card-body">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading cycle count data...</p>
          </div>
  
          <form v-else @submit.prevent="saveCount">
            <div class="row mb-4">
              <div class="col-md-6">
                <h4>Count Information</h4>
                
                <div class="mb-3">
                  <label for="item_id" class="form-label">Item <span class="text-danger">*</span></label>
                  <select 
                    id="item_id" 
                    v-model="form.item_id" 
                    class="form-select" 
                    required
                    :disabled="isEditMode"
                  >
                    <option value="">Select Item</option>
                    <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                      {{ item.item_code }} - {{ item.name }}
                    </option>
                  </select>
                  <div v-if="errors.item_id" class="text-danger mt-1">
                    {{ errors.item_id }}
                  </div>
                </div>
  
                <div class="mb-3">
                  <label for="warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
                  <select 
                    id="warehouse_id" 
                    v-model="form.warehouse_id" 
                    class="form-select" 
                    required
                    :disabled="isEditMode"
                    @change="updateBookQuantity"
                  >
                    <option value="">Select Warehouse</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                      {{ warehouse.name }}
                    </option>
                  </select>
                  <div v-if="errors.warehouse_id" class="text-danger mt-1">
                    {{ errors.warehouse_id }}
                  </div>
                </div>
  
                <div class="mb-3">
                  <label for="count_date" class="form-label">Count Date <span class="text-danger">*</span></label>
                  <input 
                    type="date" 
                    id="count_date" 
                    v-model="form.count_date" 
                    class="form-control" 
                    required
                  >
                  <div v-if="errors.count_date" class="text-danger mt-1">
                    {{ errors.count_date }}
                  </div>
                </div>
              </div>
  
              <div class="col-md-6">
                <h4>Count Results</h4>
                
                <div class="mb-3">
                  <label for="book_quantity" class="form-label">Book Quantity</label>
                  <div class="input-group">
                    <input 
                      type="number" 
                      id="book_quantity" 
                      v-model="form.book_quantity" 
                      class="form-control" 
                      step="0.01"
                      readonly
                    >
                    <button 
                      type="button" 
                      class="btn btn-outline-secondary" 
                      @click="updateBookQuantity" 
                      title="Update book quantity from current stock"
                      :disabled="!form.item_id || !form.warehouse_id"
                    >
                      <i class="fas fa-sync-alt"></i>
                    </button>
                  </div>
                  <div v-if="errors.book_quantity" class="text-danger mt-1">
                    {{ errors.book_quantity }}
                  </div>
                </div>
  
                <div class="mb-3">
                  <label for="actual_quantity" class="form-label">Actual Quantity <span class="text-danger">*</span></label>
                  <input 
                    type="number" 
                    id="actual_quantity" 
                    v-model="form.actual_quantity" 
                    class="form-control" 
                    step="0.01"
                    required
                    min="0"
                    @input="calculateVariance"
                  >
                  <div v-if="errors.actual_quantity" class="text-danger mt-1">
                    {{ errors.actual_quantity }}
                  </div>
                </div>
  
                <div class="mb-3">
                  <label for="variance" class="form-label">Variance</label>
                  <input 
                    type="number" 
                    id="variance" 
                    v-model="form.variance" 
                    class="form-control" 
                    readonly
                    :class="{'text-success bg-success-subtle': form.variance > 0, 
                             'text-danger bg-danger-subtle': form.variance < 0}"
                  >
                </div>
  
                <div v-if="form.variance !== 0" class="mb-3">
                  <label for="variance_percentage" class="form-label">Variance Percentage</label>
                  <div class="input-group">
                    <input 
                      type="text" 
                      id="variance_percentage" 
                      :value="variancePercentage + '%'" 
                      class="form-control" 
                      readonly
                      :class="{'text-success bg-success-subtle': form.variance > 0, 
                               'text-danger bg-danger-subtle': form.variance < 0}"
                    >
                    <span class="input-group-text" :class="{'text-success': form.variance > 0, 
                                                             'text-danger': form.variance < 0}">
                      <i class="fas" :class="form.variance > 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                    </span>
                  </div>
                </div>
              </div>
            </div>
  
            <div class="alert" :class="getVarianceAlertClass()" v-if="showVarianceAlert">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <span>{{ varianceAlertMessage }}</span>
            </div>
  
            <div class="d-flex justify-content-end mt-4">
              <button type="button" class="btn btn-secondary me-2" @click="$router.push('/cycle-counts')">
                Cancel
              </button>
              <button 
                type="submit" 
                class="btn btn-primary" 
                :disabled="submitting"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                {{ isEditMode ? 'Update Cycle Count' : 'Save Cycle Count' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'CycleCountForm',
    props: {
      id: {
        type: [String, Number],
        required: false
      }
    },
    data() {
      return {
        isEditMode: false,
        loading: false,
        submitting: false,
        items: [],
        warehouses: [],
        form: {
          item_id: '',
          warehouse_id: '',
          book_quantity: 0,
          actual_quantity: 0,
          variance: 0,
          count_date: this.getTodayFormatted()
        },
        errors: {}
      };
    },
    computed: {
      variancePercentage() {
        if (this.form.book_quantity === 0) {
          return this.form.variance !== 0 ? '100.00' : '0.00';
        }
        
        const percentage = (this.form.variance / this.form.book_quantity) * 100;
        return percentage.toFixed(2);
      },
      showVarianceAlert() {
        return Math.abs(this.form.variance) > 0;
      },
      varianceAlertMessage() {
        if (this.form.variance > 0) {
          return `Positive variance detected. There are ${this.form.variance} more items than expected in the system.`;
        } else if (this.form.variance < 0) {
          return `Negative variance detected. There are ${Math.abs(this.form.variance)} fewer items than expected in the system.`;
        }
        return '';
      }
    },
    mounted() {
      this.loadItems();
      this.loadWarehouses();
      
      if (this.id) {
        this.isEditMode = true;
        this.loadCycleCount();
      }
    },
    methods: {
      async loadItems() {
        try {
          const response = await axios.get('/items');
          this.items = response.data.data || [];
        } catch (error) {
          console.error('Error loading items:', error);
        }
      },
      async loadWarehouses() {
        try {
          const response = await axios.get('/warehouses');
          this.warehouses = response.data.data || [];
        } catch (error) {
          console.error('Error loading warehouses:', error);
        }
      },
      async loadCycleCount() {
        this.loading = true;
        
        try {
          const response = await axios.get(`/cycle-counts/${this.id}`);
          
          if (response.data.success && response.data.data) {
            const cycleCount = response.data.data;
            
            this.form = {
              item_id: cycleCount.item_id,
              warehouse_id: cycleCount.warehouse_id,
              book_quantity: cycleCount.book_quantity,
              actual_quantity: cycleCount.actual_quantity,
              variance: cycleCount.variance,
              count_date: this.formatDateForInput(cycleCount.count_date)
            };
          } else {
            this.$router.push('/cycle-counts');
            alert('Cycle count not found');
          }
        } catch (error) {
          console.error('Error loading cycle count:', error);
          this.$router.push('/cycle-counts');
          alert('Error loading cycle count details');
        } finally {
          this.loading = false;
        }
      },
      getTodayFormatted() {
        const today = new Date();
        return today.toISOString().split('T')[0]; // Format as YYYY-MM-DD
      },
      formatDateForInput(dateString) {
        if (!dateString) return '';
        
        // If it's already in YYYY-MM-DD format, return as is
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
          return dateString;
        }
        
        const date = new Date(dateString);
        return date.toISOString().split('T')[0];
      },
      async updateBookQuantity() {
        if (!this.form.item_id || !this.form.warehouse_id) {
          return;
        }
        
        try {
          // Get current stock for this item in this warehouse
          const response = await axios.get('/transactions/items/' + this.form.item_id + '/movement', {
            params: {
              warehouse_id: this.form.warehouse_id
            }
          });
          
          // Calculate the current stock by summing all transactions
          if (response.data.success && response.data.data) {
            const transactions = response.data.data;
            let stockQuantity = 0;
            
            transactions.forEach(transaction => {
              stockQuantity += parseFloat(transaction.quantity);
            });
            
            this.form.book_quantity = stockQuantity;
            this.calculateVariance();
          }
        } catch (error) {
          console.error('Error fetching stock quantity:', error);
          alert('Failed to fetch current stock quantity');
        }
      },
      calculateVariance() {
        this.form.variance = parseFloat(this.form.actual_quantity) - parseFloat(this.form.book_quantity);
      },
      getVarianceAlertClass() {
        if (this.form.variance > 0) {
          return 'alert-warning';
        } else if (this.form.variance < 0) {
          return 'alert-danger';
        }
        return 'alert-info';
      },
      async saveCount() {
        this.errors = {};
        this.submitting = true;
        
        try {
          let response;
          
          if (this.isEditMode) {
            response = await axios.put(`/cycle-counts/${this.id}`, this.form);
          } else {
            response = await axios.post('/cycle-counts', this.form);
          }
          
          if (response.data.success) {
            this.$router.push('/cycle-counts');
            alert(`Cycle count ${this.isEditMode ? 'updated' : 'created'} successfully`);
          } else {
            alert(`Failed to ${this.isEditMode ? 'update' : 'create'} cycle count: ${response.data.message}`);
          }
        } catch (error) {
          console.error(`Error ${this.isEditMode ? 'updating' : 'creating'} cycle count:`, error);
          
          if (error.response && error.response.data) {
            if (error.response.data.errors) {
              this.errors = error.response.data.errors;
            } else if (error.response.data.message) {
              alert(`Error: ${error.response.data.message}`);
            }
          } else {
            alert('An unexpected error occurred. Please try again.');
          }
        } finally {
          this.submitting = false;
        }
      }
    },
    watch: {
      'form.item_id'() {
        if (!this.isEditMode) {
          this.updateBookQuantity();
        }
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
  
  .bg-success-subtle {
    background-color: rgba(25, 135, 84, 0.1);
  }
  
  .bg-danger-subtle {
    background-color: rgba(220, 53, 69, 0.1);
  }
  </style>