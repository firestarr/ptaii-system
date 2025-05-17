<!-- src/views/manufacturing/ProductionOrderForm.vue -->
<template>
  <div class="production-order-form">
    <div class="page-header">
      <h1>{{ isEditing ? 'Edit Production Order' : 'Create Production Order' }}</h1>
      <div class="actions">
        <router-link to="/manufacturing/production-orders" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to List
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <span>Loading...</span>
    </div>

    <form v-else @submit.prevent="saveProductionOrder" class="card">
      <div class="card-body">
        <div class="form-section">
          <h2>Production Order Details</h2>

          <div class="form-row">
            <div class="form-group">
              <label for="production_number">Production Number</label>
              <input 
                type="text" 
                id="production_number" 
                v-model="form.production_number" 
                :readonly="isEditing"
                :class="{ 'error': errors && errors.production_number }"
                placeholder="Will be auto-generated if left empty"
              >
              <div v-if="errors && errors.production_number" class="error-message">
                {{ errors.production_number }}
              </div>
            </div>

            <div class="form-group">
              <label for="production_date">Production Date</label>
              <input 
                type="date" 
                id="production_date" 
                v-model="form.production_date"
                :class="{ 'error': errors && errors.production_date }"
                required
              >
              <div v-if="errors && errors.production_date" class="error-message">
                {{ errors.production_date }}
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="work_order">Work Order</label>
              <select 
                id="work_order" 
                v-model="form.wo_id"
                :class="{ 'error': errors && errors.wo_id }"
                @change="loadWorkOrderDetails"
                required
              >
                <option value="">-- Select Work Order --</option>
                <option v-for="wo in workOrders" :key="wo.wo_id" :value="wo.wo_id">
                  {{ wo.wo_number }} - {{ wo.item?.name || 'Unknown Item' }}
                </option>
              </select>
              <div v-if="errors && errors.wo_id" class="error-message">
                {{ errors.wo_id }}
              </div>
            </div>

            <div class="form-group">
              <label for="status">Status</label>
              <select 
                id="status" 
                v-model="form.status"
                :class="{ 'error': errors && errors.status }"
                required
              >
                <option value="Draft">Draft</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
              </select>
              <div v-if="errors && errors.status" class="error-message">
                {{ errors.status }}
              </div>
            </div>
          </div>

          <div v-if="workOrderDetails" class="info-panel">
            <div class="info-panel-title">Work Order Information</div>
            <div class="info-panel-content">
              <div class="info-row">
                <div class="info-label">Item:</div>
                <div class="info-value">{{ workOrderDetails.item?.name || 'N/A' }}</div>
              </div>
              <div class="info-row">
                <div class="info-label">BOM:</div>
                <div class="info-value">{{ workOrderDetails.bom?.bom_code || 'N/A' }}</div>
              </div>
              <div class="info-row">
                <div class="info-label">Routing:</div>
                <div class="info-value">{{ workOrderDetails.routing?.routing_code || 'N/A' }}</div>
              </div>
              <div class="info-row">
                <div class="info-label">Planned Quantity:</div>
                <div class="info-value">{{ workOrderDetails.planned_quantity }}</div>
              </div>
              <div class="info-row">
                <div class="info-label">Planned Start:</div>
                <div class="info-value">{{ formatDate(workOrderDetails.planned_start_date) }}</div>
              </div>
              <div class="info-row">
                <div class="info-label">Planned End:</div>
                <div class="info-value">{{ formatDate(workOrderDetails.planned_end_date) }}</div>
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
                :class="{ 'error': errors && errors.planned_quantity }"
                min="0" 
                step="0.01" 
                required
              >
              <div v-if="errors && errors.planned_quantity" class="error-message">
                {{ errors.planned_quantity }}
              </div>
            </div>

            <div class="form-group">
              <label for="actual_quantity">Actual Quantity</label>
              <input 
                type="number" 
                id="actual_quantity" 
                v-model="form.actual_quantity"
                :class="{ 'error': errors && errors.actual_quantity }"
                min="0" 
                step="0.01"
              >
              <div v-if="errors && errors.actual_quantity" class="error-message">
                {{ errors.actual_quantity }}
              </div>
            </div>
          </div>
        </div>

        <div v-if="form.wo_id && bomComponents.length > 0" class="form-section material-section">
          <h2>Material Consumption</h2>
          <p class="section-description">
            The following materials will be consumed from the BOM.
            You can adjust the quantities as needed.
          </p>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Warehouse</th>
                  <th>Planned Quantity</th>
                  <th>Actual Quantity</th>
                  <th>UoM</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(component, index) in form.consumptions" :key="index">
                  <td>
                    <div class="item-name">{{ getItemName(component.item_id) }}</div>
                    <div v-if="component.item_id" class="item-code">{{ getItemCode(component.item_id) }}</div>
                  </td>
                  <td>
                    <select v-model="component.warehouse_id" required>
                      <option value="">-- Select Warehouse --</option>
                      <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                        {{ warehouse.name }}
                      </option>
                    </select>
                    <div v-if="errors && errors[`consumptions.${index}.warehouse_id`]" class="error-message">
                      {{ errors[`consumptions.${index}.warehouse_id`] }}
                    </div>
                  </td>
                  <td>
                    <input 
                      type="number" 
                      v-model="component.planned_quantity" 
                      min="0" 
                      step="0.01" 
                      required
                    >
                    <div v-if="errors && errors[`consumptions.${index}.planned_quantity`]" class="error-message">
                      {{ errors[`consumptions.${index}.planned_quantity`] }}
                    </div>
                  </td>
                  <td>
                    <input 
                      type="number" 
                      v-model="component.actual_quantity" 
                      min="0" 
                      step="0.01"
                    >
                  </td>
                  <td>{{ getItemUom(component.item_id) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card-footer">
        <button type="button" class="btn btn-secondary" @click="cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <i v-if="saving" class="fas fa-spinner fa-spin"></i>
          {{ saving ? 'Saving...' : 'Save Production Order' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ProductionOrderForm',
  props: {
    id: {
      type: [Number, String],
      required: false
    }
  },
  data() {
    return {
      form: {
        production_number: '',
        production_date: new Date().toISOString().substr(0, 10),
        wo_id: '',
        planned_quantity: 0,
        actual_quantity: 0,
        status: 'Draft',
        consumptions: []
      },
      workOrders: [],
      workOrderDetails: null,
      warehouses: [],
      items: {},
      bomComponents: [],
      loading: true,
      saving: false,
      errors: {}
    };
  },
  computed: {
    isEditing() {
      return !!this.id;
    }
  },
  created() {
    this.fetchInitialData();
  },
  methods: {
    async fetchInitialData() {
      this.loading = true;
      try {
        // Fetch work orders
        const [workOrdersRes, warehousesRes] = await Promise.all([
          axios.get('/work-orders'),
          axios.get('/warehouses')
        ]);
        
        this.workOrders = workOrdersRes.data.data || workOrdersRes.data;
        this.warehouses = warehousesRes.data.data || warehousesRes.data;
        
        // If editing, fetch production order details
        if (this.isEditing) {
          await this.fetchProductionOrder();
        }
      } catch (error) {
        console.error('Error fetching initial data:', error);
        this.$toast.error('Failed to load required data');
      } finally {
        this.loading = false;
      }
    },
    
    async fetchProductionOrder() {
      try {
        const response = await axios.get(`/production-orders/${this.id}`);
        const productionOrder = response.data.data || response.data;
        
        // Map production order data to form
        this.form = {
          production_number: productionOrder.production_number,
          production_date: productionOrder.production_date,
          wo_id: productionOrder.wo_id,
          planned_quantity: productionOrder.planned_quantity,
          actual_quantity: productionOrder.actual_quantity || 0,
          status: productionOrder.status,
          consumptions: []
        };
        
        // Load work order details
        await this.loadWorkOrderDetails();
        
        // Load consumption data
        if (productionOrder.productionConsumptions) {
          this.form.consumptions = productionOrder.productionConsumptions.map(consumption => ({
            consumption_id: consumption.consumption_id,
            item_id: consumption.item_id,
            planned_quantity: consumption.planned_quantity,
            actual_quantity: consumption.actual_quantity || 0,
            warehouse_id: consumption.warehouse_id
          }));
        }
      } catch (error) {
        console.error('Error fetching production order:', error);
        this.$toast.error('Failed to load production order data');
      }
    },
    
    async loadWorkOrderDetails() {
      if (!this.form.wo_id) {
        this.workOrderDetails = null;
        this.bomComponents = [];
        this.form.consumptions = [];
        return;
      }
      
      try {
        // Fetch work order details
        const response = await axios.get(`/work-orders/${this.form.wo_id}`);
        this.workOrderDetails = response.data.data || response.data;
        
        // Set default production quantity from work order
        if (!this.isEditing || this.form.planned_quantity === 0) {
          this.form.planned_quantity = this.workOrderDetails.planned_quantity;
        }
        
        // Fetch BOM components
        if (this.workOrderDetails.bom_id) {
          const bomResponse = await axios.get(`/boms/${this.workOrderDetails.bom_id}`);
          const bom = bomResponse.data.data || bomResponse.data;
          
          if (bom && bom.bomLines) {
            this.bomComponents = bom.bomLines;
            
            // Fetch items data
            const itemIds = this.bomComponents.map(line => line.item_id);
            const itemsResponse = await axios.get('/items', { params: { ids: itemIds.join(',') } });
            const items = itemsResponse.data.data || itemsResponse.data;
            
            // Create a lookup object for items
            items.forEach(item => {
              this.items[item.item_id] = item;
            });
            
            // Only create new consumptions if not editing or if no consumptions exist
            if (!this.isEditing || this.form.consumptions.length === 0) {
              // Calculate component quantities based on production quantity ratio
              const ratio = this.form.planned_quantity / bom.standard_quantity;
              
              this.form.consumptions = this.bomComponents.map(component => {
                return {
                  item_id: component.item_id,
                  planned_quantity: parseFloat((component.quantity * ratio).toFixed(4)),
                  actual_quantity: 0,
                  warehouse_id: this.getDefaultWarehouse(),
                  variance: 0
                };
              });
            }
          }
        }
      } catch (error) {
        console.error('Error loading work order details:', error);
        this.$toast.error('Failed to load work order details');
      }
    },
    
    getDefaultWarehouse() {
      // Return first warehouse or empty if none available
      return this.warehouses.length > 0 ? this.warehouses[0].warehouse_id : '';
    },
    
    getItemName(itemId) {
      return this.items[itemId]?.name || 'Unknown Item';
    },
    
    getItemCode(itemId) {
      return this.items[itemId]?.item_code || '';
    },
    
    getItemUom(itemId) {
      return this.items[itemId]?.unitOfMeasure?.symbol || '';
    },
    
    formatDate(date) {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString();
    },
    
    async saveProductionOrder() {
      this.errors = {};
      this.saving = true;
      
      try {
        // Calculate variances for consumptions
        this.form.consumptions.forEach(consumption => {
          consumption.variance = consumption.planned_quantity - (consumption.actual_quantity || 0);
        });
        
        let response;
        if (this.isEditing) {
          response = await axios.put(`/production-orders/${this.id}`, this.form);
        } else {
          response = await axios.post('/production-orders', this.form);
        }
        
        this.$toast.success(
          this.isEditing 
            ? 'Production order updated successfully' 
            : 'Production order created successfully'
        );
        
        // Redirect to production order detail view
        const productionId = this.isEditing 
          ? this.id 
          : (response.data.data?.production_id || response.data.production_id);
          
        this.$router.push(`/manufacturing/production-orders/${productionId}`);
      } catch (error) {
        console.error('Error saving production order:', error);
        
        if (error.response && error.response.data && error.response.data.errors) {
          this.errors = error.response.data.errors;
          this.$toast.error('Please correct the errors before submitting');
        } else {
          this.$toast.error('Failed to save production order');
        }
      } finally {
        this.saving = false;
      }
    },
    
    cancel() {
      // Go back to previous page or to list
      if (this.$router.options.history.state.back) {
        this.$router.back();
      } else {
        this.$router.push('/manufacturing/production-orders');
      }
    }
  }
};
</script>
