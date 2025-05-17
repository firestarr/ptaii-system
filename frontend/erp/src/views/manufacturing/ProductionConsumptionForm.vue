<!-- src/views/manufacturing/ProductionConsumptionForm.vue -->
<template>
    <div class="production-consumption-form">
      <div class="page-header">
        <h1>{{ isEditing ? 'Edit Material Consumption' : 'Add Material Consumption' }}</h1>
        <div class="actions">
          <router-link :to="`/manufacturing/production-orders/${productionId}`" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Production Order
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Loading...</span>
      </div>
  
      <form v-else @submit.prevent="saveConsumption" class="card">
        <div class="card-body">
          <div class="form-section">
            <h2>Material Consumption Details</h2>
            
            <div class="form-row">
              <div class="form-group">
                <label for="item_id">Material</label>
                <select 
                  id="item_id" 
                  v-model="form.item_id"
                  :class="{ 'error': errors.item_id }"
                  required
                >
                  <option value="">-- Select Material --</option>
                  <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                    {{ item.name }} ({{ item.item_code }})
                  </option>
                </select>
                <div v-if="errors.item_id" class="error-message">
                  {{ errors.item_id }}
                </div>
              </div>
              
              <div class="form-group">
                <label for="warehouse_id">Warehouse</label>
                <select 
                  id="warehouse_id" 
                  v-model="form.warehouse_id"
                  :class="{ 'error': errors.warehouse_id }"
                  required
                >
                  <option value="">-- Select Warehouse --</option>
                  <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                    {{ warehouse.name }}
                  </option>
                </select>
                <div v-if="errors.warehouse_id" class="error-message">
                  {{ errors.warehouse_id }}
                </div>
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label for="planned_quantity">Planned Quantity</label>
                <input 
                  type="number" 
                  id="planned_quantity" 
                  v-model="form.planned_quantity"
                  :class="{ 'error': errors.planned_quantity }"
                  min="0" 
                  step="0.01" 
                  @input="calculateVariance"
                  required
                >
                <div v-if="errors.planned_quantity" class="error-message">
                  {{ errors.planned_quantity }}
                </div>
              </div>
              
              <div class="form-group">
                <label for="actual_quantity">Actual Quantity</label>
                <input 
                  type="number" 
                  id="actual_quantity" 
                  v-model="form.actual_quantity"
                  :class="{ 'error': errors.actual_quantity }"
                  min="0" 
                  step="0.01"
                  @input="calculateVariance"
                >
                <div v-if="errors.actual_quantity" class="error-message">
                  {{ errors.actual_quantity }}
                </div>
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label for="variance">Variance</label>
                <input 
                  type="number" 
                  id="variance" 
                  v-model="form.variance"
                  readonly
                  :class="getVarianceClass()"
                >
              </div>
            </div>
  
            <div v-if="selectedItem" class="info-panel">
              <div class="info-panel-title">Material Information</div>
              <div class="info-panel-content">
                <div class="info-row">
                  <div class="info-label">Material Code:</div>
                  <div class="info-value">{{ selectedItem.item_code }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Description:</div>
                  <div class="info-value">{{ selectedItem.description || 'N/A' }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Unit of Measure:</div>
                  <div class="info-value">{{ selectedItem.unitOfMeasure?.symbol || 'N/A' }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Current Stock:</div>
                  <div class="info-value">{{ selectedItem.current_stock || '0' }}</div>
                </div>
              </div>
            </div>
            
            <div v-if="selectedWarehouse" class="info-panel">
              <div class="info-panel-title">Warehouse Information</div>
              <div class="info-panel-content">
                <div class="info-row">
                  <div class="info-label">Warehouse Code:</div>
                  <div class="info-value">{{ selectedWarehouse.code }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Location:</div>
                  <div class="info-value">{{ selectedWarehouse.address || 'N/A' }}</div>
                </div>
                <div v-if="itemStock" class="info-row">
                  <div class="info-label">Available Stock:</div>
                  <div class="info-value" :class="getStockClass()">{{ itemStock.quantity || '0' }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="card-footer">
          <button type="button" class="btn btn-secondary" @click="cancel">Cancel</button>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            <i v-if="saving" class="fas fa-spinner fa-spin"></i>
            {{ saving ? 'Saving...' : 'Save Consumption' }}
          </button>
        </div>
      </form>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'ProductionConsumptionForm',
    props: {
      productionId: {
        type: [Number, String],
        required: true
      },
      consumptionId: {
        type: [Number, String],
        required: false
      },
      prefillItemId: {
        type: [Number, String],
        required: false,
        default: null
      },
      prefillPlannedQuantity: {
        type: Number,
        required: false,
        default: 0
      }
    },
    data() {
      return {
        form: {
          production_id: this.productionId,
          item_id: this.prefillItemId || '',
          planned_quantity: this.prefillPlannedQuantity || 0,
          actual_quantity: 0,
          warehouse_id: '',
          variance: 0
        },
        productionOrder: null,
        items: [],
        warehouses: [],
        itemStock: null,
        loading: true,
        saving: false,
        errors: {}
      };
    },
    computed: {
      isEditing() {
        return !!this.consumptionId;
      },
      selectedItem() {
        return this.items.find(item => item.item_id === this.form.item_id);
      },
      selectedWarehouse() {
        return this.warehouses.find(warehouse => warehouse.warehouse_id === this.form.warehouse_id);
      }
    },
    created() {
      this.fetchInitialData();
    },
    methods: {
      async fetchInitialData() {
        this.loading = true;
        try {
          // Fetch production order, items and warehouses
          const [productionOrderRes, itemsRes, warehousesRes] = await Promise.all([
            axios.get(`/production-orders/${this.productionId}`),
            axios.get('/items?is_purchasable=1'),
            axios.get('/warehouses')
          ]);
          
          this.productionOrder = productionOrderRes.data.data || productionOrderRes.data;
          this.items = itemsRes.data.data || itemsRes.data;
          this.warehouses = warehousesRes.data.data || warehousesRes.data;
          
          // If editing, fetch consumption details
          if (this.isEditing) {
            await this.fetchConsumption();
          }
        } catch (error) {
          console.error('Error fetching initial data:', error);
          this.$toast.error('Failed to load required data');
        } finally {
          this.loading = false;
        }
      },
      
      async fetchConsumption() {
        try {
          const response = await axios.get(`/production-orders/${this.productionId}/consumptions/${this.consumptionId}`);
          const consumption = response.data.data || response.data;
          
          // Map consumption data to form
          this.form = {
            production_id: this.productionId,
            item_id: consumption.item_id,
            planned_quantity: consumption.planned_quantity,
            actual_quantity: consumption.actual_quantity || 0,
            warehouse_id: consumption.warehouse_id,
            variance: consumption.variance || 0
          };
          
          // Fetch item stock for the selected item and warehouse
          if (this.form.item_id && this.form.warehouse_id) {
            await this.checkItemStock();
          }
        } catch (error) {
          console.error('Error fetching consumption:', error);
          this.$toast.error('Failed to load consumption data');
        }
      },
      
      async checkItemStock() {
        if (!this.form.item_id || !this.form.warehouse_id) return;
        
        try {
          const response = await axios.get(`/item-stocks/item/${this.form.item_id}`, {
            params: { warehouse_id: this.form.warehouse_id }
          });
          
          this.itemStock = response.data.data || response.data;
        } catch (error) {
          console.error('Error checking item stock:', error);
          this.itemStock = null;
        }
      },
      
      calculateVariance() {
        const planned = parseFloat(this.form.planned_quantity) || 0;
        const actual = parseFloat(this.form.actual_quantity) || 0;
        this.form.variance = planned - actual;
      },
      
      getVarianceClass() {
        const variance = parseFloat(this.form.variance) || 0;
        
        if (variance === 0) return 'no-variance';
        if (Math.abs(variance) / this.form.planned_quantity <= 0.05) return 'low-variance';
        if (variance > 0) return 'positive-variance';
        return 'negative-variance';
      },
      
      getStockClass() {
        if (!this.itemStock || !this.form.planned_quantity) return '';
        
        const available = parseFloat(this.itemStock.quantity) || 0;
        const planned = parseFloat(this.form.planned_quantity) || 0;
        
        if (available < planned) return 'stock-warning';
        return 'stock-ok';
      },
      
      async saveConsumption() {
        this.errors = {};
        this.saving = true;
        
        try {
          this.calculateVariance();  // Ensure variance is calculated before saving
          
if (this.isEditing) {
  await axios.put(
    `/production-orders/${this.productionId}/consumptions/${this.consumptionId}`, 
    this.form
  );
} else {
  await axios.post(
    `/production-orders/${this.productionId}/consumptions`, 
    this.form
  );
}
          
          this.$toast.success(
            this.isEditing 
              ? 'Material consumption updated successfully' 
              : 'Material consumption added successfully'
          );
          
          // Redirect back to production order detail
          this.$router.push(`/manufacturing/production-orders/${this.productionId}`);
        } catch (error) {
          console.error('Error saving consumption:', error);
          
          if (error.response && error.response.data && error.response.data.errors) {
            this.errors = error.response.data.errors;
            this.$toast.error('Please correct the errors before submitting');
          } else {
            this.$toast.error('Failed to save material consumption');
          }
        } finally {
          this.saving = false;
        }
      },
      
      cancel() {
        // Go back to production order detail
        this.$router.push(`/manufacturing/production-orders/${this.productionId}`);
      }
    },
    watch: {
      'form.item_id'() {
        this.checkItemStock();
      },
      'form.warehouse_id'() {
        this.checkItemStock();
      }
    }
  };
  </script>