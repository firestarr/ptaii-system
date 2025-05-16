<!-- src/views/inventory/StockAdjustmentForm.vue -->
<template>
    <div class="adjustment-form-page">
      <div class="page-header">
        <div class="header-content">
          <h2 class="page-title">{{ isEditMode ? 'Edit Stock Adjustment' : 'Create Stock Adjustment' }}</h2>
          <div class="header-actions">
            <button 
              class="btn btn-secondary" 
              @click="confirmCancel"
              :disabled="isSubmitting"
            >
              <i class="fas fa-times mr-1"></i> Cancel
            </button>
            <button 
              class="btn btn-primary" 
              @click="saveAdjustment"
              :disabled="isSubmitting || !isFormValid"
            >
              <i v-if="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-save mr-1"></i>
              {{ isEditMode ? 'Update' : 'Save' }}
            </button>
          </div>
        </div>
        
        <div class="breadcrumbs">
          <router-link to="/stock-adjustments" class="breadcrumb-item">
            <i class="fas fa-sliders-h mr-1"></i> Stock Adjustments
          </router-link>
          <span class="breadcrumb-separator">/</span>
          <span class="breadcrumb-item active">
            {{ isEditMode ? 'Edit' : 'Create' }}
          </span>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading...</p>
      </div>
  
      <div v-else-if="error" class="error-container">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <p>{{ error }}</p>
        <div class="error-actions">
          <router-link to="/stock-adjustments" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
          </router-link>
          <button @click="retryLoading" class="btn btn-primary">
            <i class="fas fa-sync mr-1"></i> Try Again
          </button>
        </div>
      </div>
  
      <div v-else class="adjustment-form-container">
        <form @submit.prevent="saveAdjustment">
          <div class="form-card">
            <div class="card-header">
              <h3 class="card-title">Adjustment Details</h3>
            </div>
            <div class="card-body">
              <div class="form-row">
                <div class="form-group">
                  <label for="adjustment_date">Adjustment Date</label>
                  <input
                    type="date"
                    id="adjustment_date"
                    v-model="form.adjustment_date"
                    class="form-control"
                    :class="{ 'is-invalid': validationErrors.adjustment_date }"
                    required
                  />
                  <div v-if="validationErrors.adjustment_date" class="invalid-feedback">
                    {{ validationErrors.adjustment_date }}
                  </div>
                </div>
  
                <div class="form-group">
                  <label for="reference_document">Reference Document</label>
                  <input
                    type="text"
                    id="reference_document"
                    v-model="form.reference_document"
                    class="form-control"
                    :class="{ 'is-invalid': validationErrors.reference_document }"
                    placeholder="e.g., Inventory count #123"
                  />
                  <div v-if="validationErrors.reference_document" class="invalid-feedback">
                    {{ validationErrors.reference_document }}
                  </div>
                </div>
              </div>
  
              <div class="form-group">
                <label for="adjustment_reason">Adjustment Reason</label>
                <textarea
                  id="adjustment_reason"
                  v-model="form.adjustment_reason"
                  class="form-control"
                  :class="{ 'is-invalid': validationErrors.adjustment_reason }"
                  rows="3"
                  placeholder="Provide a reason for this stock adjustment"
                ></textarea>
                <div v-if="validationErrors.adjustment_reason" class="invalid-feedback">
                  {{ validationErrors.adjustment_reason }}
                </div>
              </div>
            </div>
          </div>
  
          <div class="form-card mt-4">
            <div class="card-header">
              <h3 class="card-title">Adjustment Items</h3>
              <button 
                type="button" 
                class="btn btn-sm btn-primary"
                @click="addAdjustmentLine"
              >
                <i class="fas fa-plus mr-1"></i> Add Item
              </button>
            </div>
            <div class="card-body">
              <div v-if="form.lines.length === 0" class="empty-lines">
                <p>No items added yet. Click "Add Item" to start adding items to this adjustment.</p>
              </div>
              
              <div v-else class="adjustment-lines">
                <div class="line-headers">
                  <div class="line-header item-col">Item</div>
                  <div class="line-header warehouse-col">Warehouse</div>
                  <div class="line-header location-col">Location</div>
                  <div class="line-header quantity-col">Book Qty</div>
                  <div class="line-header quantity-col">Adjusted Qty</div>
                  <div class="line-header variance-col">Variance</div>
                  <div class="line-header actions-col">Actions</div>
                </div>
                
                <div 
                  v-for="(line, index) in form.lines" 
                  :key="index"
                  class="adjustment-line"
                >
                  <div class="line-cell item-col">
                    <select
                      v-model="line.item_id"
                      class="form-control"
                      :class="{ 'is-invalid': getLineError(index, 'item_id') }"
                      required
                      @change="updateBookQuantity(index)"
                    >
                      <option value="" disabled>Select an item</option>
                      <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                        {{ item.name }} ({{ item.item_code }})
                      </option>
                    </select>
                    <div v-if="getLineError(index, 'item_id')" class="invalid-feedback">
                      {{ getLineError(index, 'item_id') }}
                    </div>
                  </div>
                  
                  <div class="line-cell warehouse-col">
                    <select
                      v-model="line.warehouse_id"
                      class="form-control"
                      :class="{ 'is-invalid': getLineError(index, 'warehouse_id') }"
                      required
                      @change="fetchLocations(index); updateBookQuantity(index)"
                    >
                      <option value="" disabled>Select warehouse</option>
                      <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                        {{ warehouse.name }}
                      </option>
                    </select>
                    <div v-if="getLineError(index, 'warehouse_id')" class="invalid-feedback">
                      {{ getLineError(index, 'warehouse_id') }}
                    </div>
                  </div>
                  
                  <div class="line-cell location-col">
                    <select
                      v-model="line.location_id"
                      class="form-control"
                      :class="{ 'is-invalid': getLineError(index, 'location_id') }"
                      @change="updateBookQuantity(index)"
                    >
                      <option value="">Select location (optional)</option>
                      <option v-for="location in getLocationsForLine(index)" :key="location.location_id" :value="location.location_id">
                        {{ location.code }}
                      </option>
                    </select>
                    <div v-if="getLineError(index, 'location_id')" class="invalid-feedback">
                      {{ getLineError(index, 'location_id') }}
                    </div>
                  </div>
                  
                  <div class="line-cell quantity-col">
                    <input
                      type="number"
                      v-model.number="line.book_quantity"
                      class="form-control"
                      :class="{ 'is-invalid': getLineError(index, 'book_quantity') }"
                      readonly
                    />
                    <div v-if="getLineError(index, 'book_quantity')" class="invalid-feedback">
                      {{ getLineError(index, 'book_quantity') }}
                    </div>
                  </div>
                  
                  <div class="line-cell quantity-col">
                    <input
                      type="number"
                      v-model.number="line.adjusted_quantity"
                      class="form-control"
                      :class="{ 'is-invalid': getLineError(index, 'adjusted_quantity') }"
                      step="0.01"
                      required
                      @input="calculateVariance(index)"
                    />
                    <div v-if="getLineError(index, 'adjusted_quantity')" class="invalid-feedback">
                      {{ getLineError(index, 'adjusted_quantity') }}
                    </div>
                  </div>
                  
                  <div class="line-cell variance-col">
                    <div 
                      class="variance-display"
                      :class="getVarianceClass(line.variance)"
                    >
                      {{ formatVariance(line.variance) }}
                    </div>
                  </div>
                  
                  <div class="line-cell actions-col">
                    <button
                      type="button"
                      class="btn-icon btn-danger"
                      @click="removeLine(index)"
                      title="Remove Item"
                    >
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </div>
              </div>
              
              <div v-if="form.lines.length > 0" class="adjustment-summary">
                <div class="summary-item">
                  <span class="summary-label">Total Items:</span>
                  <span class="summary-value">{{ form.lines.length }}</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">Total Variance:</span>
                  <span 
                    class="summary-value"
                    :class="getVarianceClass(totalVariance)"
                  >
                    {{ formatVariance(totalVariance) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
  
          <div class="form-actions">
            <button 
              type="button" 
              class="btn btn-secondary"
              @click="confirmCancel"
              :disabled="isSubmitting"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              class="btn btn-primary"
              :disabled="isSubmitting || !isFormValid"
            >
              <i v-if="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
              {{ isEditMode ? 'Update Adjustment' : 'Save Adjustment' }}
            </button>
          </div>
        </form>
      </div>
  
      <!-- Confirmation modal for cancellation -->
      <div v-if="showCancelModal" class="modal-backdrop">
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h3>Confirm Cancel</h3>
            <button class="btn-close" @click="showCancelModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Are you sure you want to cancel? Any unsaved changes will be lost.
            </p>
          </div>
          <div class="modal-footer">
            <button 
              class="btn btn-secondary" 
              @click="showCancelModal = false"
            >
              No, Continue Editing
            </button>
            <button 
              class="btn btn-danger" 
              @click="cancelForm"
            >
              Yes, Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import { ref, reactive, computed, onMounted } from 'vue';
  import { useRouter, useRoute } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'StockAdjustmentForm',
    setup() {
      const router = useRouter();
      const route = useRoute();
      
      // Check if in edit mode
      const isEditMode = computed(() => !!route.params.id);
      
      // Data
      const form = reactive({
        adjustment_date: new Date().toISOString().split('T')[0],
        adjustment_reason: '',
        reference_document: '',
        lines: []
      });
      
      const items = ref([]);
      const warehouses = ref([]);
      const warehouseLocations = ref({});
      const loading = ref(false);
      const error = ref(null);
      const isSubmitting = ref(false);
      const showCancelModal = ref(false);
      const validationErrors = ref({});
      const lineErrors = ref([]);
      
      // Computed properties
      const isFormValid = computed(() => {
        return form.adjustment_date && 
               form.lines.length > 0 &&
               !form.lines.some(line => !line.item_id || !line.warehouse_id || isNaN(line.adjusted_quantity));
      });
      
      const totalVariance = computed(() => {
        return form.lines.reduce((total, line) => {
          return total + (line.variance || 0);
        }, 0);
      });
      
      // Methods
      const loadFormData = async () => {
        loading.value = true;
        error.value = null;
        
        try {
          // Load adjustment data if in edit mode
          if (isEditMode.value) {
            const adjustmentResponse = await axios.get(`/stock-adjustments/${route.params.id}`);
            const adjustmentData = adjustmentResponse.data.data;
            
            form.adjustment_date = adjustmentData.adjustment_date;
            form.adjustment_reason = adjustmentData.adjustment_reason || '';
            form.reference_document = adjustmentData.reference_document || '';
            
            // Initialize with empty lines - we'll populate them after loading items and warehouses
            form.lines = [];
          }
          
          // Load items and warehouses regardless of mode
          const [itemsResponse, warehousesResponse] = await Promise.all([
            axios.get('/items'),
            axios.get('/warehouses')
          ]);
          
          items.value = itemsResponse.data.data;
          warehouses.value = warehousesResponse.data.data;
          
          // Now populate lines if in edit mode
          if (isEditMode.value) {
            const adjustmentResponse = await axios.get(`/stock-adjustments/${route.params.id}`);
            const adjustmentData = adjustmentResponse.data.data;
            
            if (adjustmentData.adjustment_lines && adjustmentData.adjustment_lines.length > 0) {
              // For each line, we need to get the locations for its warehouse
              for (const line of adjustmentData.adjustment_lines) {
                await fetchLocations(null, line.warehouse_id);
                
                form.lines.push({
                  line_id: line.line_id,
                  item_id: line.item_id,
                  warehouse_id: line.warehouse_id,
                  location_id: line.location_id || '',
                  book_quantity: line.book_quantity,
                  adjusted_quantity: line.adjusted_quantity,
                  variance: line.variance
                });
              }
            }
          }
          
          loading.value = false;
        } catch (err) {
          console.error('Error loading form data:', err);
          error.value = 'Failed to load necessary data. Please try again.';
          loading.value = false;
        }
      };
      
      const fetchLocations = async (lineIndex, warehouseId = null) => {
        try {
          const targetWarehouseId = warehouseId || form.lines[lineIndex].warehouse_id;
          
          if (!targetWarehouseId) return;
          
          // Skip if we already have this warehouse's locations
          if (warehouseLocations.value[targetWarehouseId]) return;
          
          // Fetch all zones for this warehouse
          const zonesResponse = await axios.get(`/warehouses/${targetWarehouseId}/zones`);
          const zones = zonesResponse.data.data;
          
          // For each zone, fetch its locations
          const locationsPromises = zones.map(zone => 
            axios.get(`/zones/${zone.zone_id}/locations`)
          );
          
          const locationsResponses = await Promise.all(locationsPromises);
          
          // Combine all locations from all zones
          const locations = locationsResponses.flatMap(response => response.data.data);
          
          // Store the locations for this warehouse
          warehouseLocations.value[targetWarehouseId] = locations;
        } catch (err) {
          console.error('Error fetching locations:', err);
        }
      };
      
      const getLocationsForLine = (index) => {
        const warehouseId = form.lines[index].warehouse_id;
        if (!warehouseId) return [];
        
        return warehouseLocations.value[warehouseId] || [];
      };
      
      const updateBookQuantity = async (index) => {
        const line = form.lines[index];
        
        if (!line.item_id || !line.warehouse_id) {
          line.book_quantity = 0;
          line.adjusted_quantity = 0;
          line.variance = 0;
          return;
        }
        
        try {
          // Query for current stock level
          const params = {
            item_id: line.item_id,
            warehouse_id: line.warehouse_id
          };
          
          if (line.location_id) {
            params.location_id = line.location_id;
          }
          
          const response = await axios.get('/items/stock-status', { params });
          
          line.book_quantity = response.data.current_stock || 0;
          
          // Reset adjusted quantity or keep it if it was already set
          if (!line.adjusted_quantity) {
            line.adjusted_quantity = line.book_quantity;
          }
          
          calculateVariance(index);
          } catch (err) {
            console.error('Error getting current stock:', err);
            line.book_quantity = 0;
          }
          
          // eslint-disable-next-line no-unused-vars
          const _ = null;
      };
      
      const calculateVariance = (index) => {
        const line = form.lines[index];
        line.variance = line.adjusted_quantity - line.book_quantity;
      };
      
      const addAdjustmentLine = () => {
        form.lines.push({
          item_id: '',
          warehouse_id: '',
          location_id: '',
          book_quantity: 0,
          adjusted_quantity: 0,
          variance: 0
        });
        
        // Add a corresponding entry in the lineErrors array
        lineErrors.value.push({});
      };
      
      const removeLine = (index) => {
        form.lines.splice(index, 1);
        lineErrors.value.splice(index, 1);
      };
      
      const validateForm = () => {
        validationErrors.value = {};
        lineErrors.value = form.lines.map(() => ({}));
        
        // Validate header fields
        if (!form.adjustment_date) {
          validationErrors.value.adjustment_date = 'Adjustment date is required';
        }
        
        // Validate lines
        if (form.lines.length === 0) {
          validationErrors.value.lines = 'At least one item must be added to the adjustment';
          return false;
        }
        
        let isValid = true;
        
        // Validate each line
        form.lines.forEach((line, index) => {
          if (!line.item_id) {
            lineErrors.value[index].item_id = 'Item is required';
            isValid = false;
          }
          
          if (!line.warehouse_id) {
            lineErrors.value[index].warehouse_id = 'Warehouse is required';
            isValid = false;
          }
          
          if (isNaN(line.adjusted_quantity)) {
            lineErrors.value[index].adjusted_quantity = 'Adjusted quantity must be a number';
            isValid = false;
          }
        });
        
        return Object.keys(validationErrors.value).length === 0 && isValid;
      };
      
      const saveAdjustment = async () => {
        if (!validateForm()) return;
        
        isSubmitting.value = true;
        
        try {
          const payload = {
            adjustment_date: form.adjustment_date,
            adjustment_reason: form.adjustment_reason,
            reference_document: form.reference_document,
            lines: form.lines.map(line => ({
              line_id: line.line_id,
              item_id: line.item_id,
              warehouse_id: line.warehouse_id,
              location_id: line.location_id || null,
              book_quantity: line.book_quantity,
              adjusted_quantity: line.adjusted_quantity
            }))
          };
          
          if (isEditMode.value) {
            await axios.put(`/stock-adjustments/${route.params.id}`, payload);
          } else {
            await axios.post('/stock-adjustments', payload);
          }
          
          // Navigate back to the list
          router.push('/stock-adjustments');
        } catch (err) {
          console.error('Error saving adjustment:', err);
          
          if (err.response && err.response.status === 422) {
            // Handle validation errors
            const serverErrors = err.response.data.errors;
            
            // Map server errors to our format
            for (const [key, value] of Object.entries(serverErrors)) {
              if (key.startsWith('lines.')) {
                // Extract index and field name from the key
                const [ index, field] = key.match(/lines\.(\d+)\.(.+)/) || [];
                
                if (index !== undefined && field) {
                  if (!lineErrors.value[index]) {
                    lineErrors.value[index] = {};
                  }
                  lineErrors.value[index][field] = value[0];
                }
              } else {
                validationErrors.value[key] = value[0];
              }
            }
          } else {
            alert('Failed to save adjustment. Please try again.');
          }
        } finally {
          isSubmitting.value = false;
        }
      };
      
      const getLineError = (lineIndex, field) => {
        return lineErrors.value[lineIndex] && lineErrors.value[lineIndex][field];
      };
      
      const confirmCancel = () => {
        showCancelModal.value = true;
      };
      
      const cancelForm = () => {
        router.push('/stock-adjustments');
      };
      
      const retryLoading = () => {
        loadFormData();
      };
      
      const formatVariance = (variance) => {
        if (variance === undefined || variance === null) return '0';
        return variance > 0 ? `+${variance}` : variance;
      };
      
      const getVarianceClass = (variance) => {
        if (!variance) return '';
        
        if (variance > 0) {
          return 'variance-positive';
        } else if (variance < 0) {
          return 'variance-negative';
        }
        return '';
      };
      
      onMounted(() => {
        loadFormData();
      });
      
      return {
        form,
        items,
        warehouses,
        loading,
        error,
        isSubmitting,
        showCancelModal,
        validationErrors,
        lineErrors,
        isEditMode,
        isFormValid,
        totalVariance,
        loadFormData,
        fetchLocations,
        getLocationsForLine,
        updateBookQuantity,
        calculateVariance,
        addAdjustmentLine,
        removeLine,
        saveAdjustment,
        getLineError,
        confirmCancel,
        cancelForm,
        retryLoading,
        formatVariance,
        getVarianceClass
      };
    }
  };
  </script>
  
  <style scoped>
  .adjustment-form-page {
    padding: 1rem;
  }
  
  .page-header {
    margin-bottom: 1.5rem;
  }
  
  .header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }
  
  .page-title {
    font-size: 1.75rem;
    margin: 0;
    color: var(--gray-800);
  }
  
  .header-actions {
    display: flex;
    gap: 0.75rem;
  }
  
  .breadcrumbs {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
  }
  
  .breadcrumb-item {
    color: var(--primary-color);
  }
  
  .breadcrumb-item.active {
    color: var(--gray-600);
  }
  
  .breadcrumb-separator {
    margin: 0 0.5rem;
    color: var(--gray-400);
  }
  
  .loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .loading-spinner {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
  }
  
  .error-container {
    text-align: center;
    padding: 3rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .error-icon {
    font-size: 2.5rem;
    color: var(--danger-color);
    margin-bottom: 1rem;
  }
  
  .error-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  
  .adjustment-form-container {
    margin-bottom: 2rem;
  }
  
  .form-card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
  }
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .card-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .card-body {
    padding: 1rem;
  }
  
  .form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }
  
  .form-group {
    margin-bottom: 1rem;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .form-control {
    width: 100%;
    padding: 0.625rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    transition: border-color 0.2s;
  }
  
  .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .form-control.is-invalid {
    border-color: var(--danger-color);
  }
  
  .invalid-feedback {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--danger-color);
  }
  
  .empty-lines {
    padding: 2rem;
    text-align: center;
    color: var(--gray-500);
    background-color: var(--gray-50);
    border-radius: 0.375rem;
    border: 1px dashed var(--gray-300);
  }
  
  .adjustment-lines {
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    overflow: hidden;
  }
  
  .line-headers {
    display: flex;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    font-weight: 500;
    color: var(--gray-600);
    font-size: 0.875rem;
  }
  
  .line-header {
    padding: 0.75rem 0.5rem;
  }
  
  .adjustment-line {
    display: flex;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .adjustment-line:last-child {
    border-bottom: none;
  }
  
  .line-cell {
    padding: 0.5rem;
  }
  
  .item-col {
    width: 25%;
  }
  
  .warehouse-col {
    width: 20%;
  }
  
  .location-col {
    width: 15%;
  }
  
  .quantity-col {
    width: 12%;
  }
  
  .variance-col {
    width: 12%;
  }
  
  .actions-col {
    width: 4%;
    display: flex;
    justify-content: center;
    align-items: center;
  }
  
  .variance-display {
    padding: 0.375rem 0.5rem;
    font-weight: 500;
    text-align: center;
    border-radius: 0.25rem;
  }
  
  .variance-positive {
    color: var(--success-color);
    background-color: var(--success-bg);
  }
  
  .variance-negative {
    color: var(--danger-color);
    background-color: var(--danger-bg);
  }
  
  .adjustment-summary {
    display: flex;
    justify-content: flex-end;
    gap: 2rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .summary-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .summary-label {
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .summary-value {
    font-weight: 600;
    font-size: 1.125rem;
    color: var(--gray-800);
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  
  .btn {
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
  }
  
  .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-800);
  }
  
  .btn-secondary:hover:not(:disabled) {
    background-color: var(--gray-300);
  }
  
  .btn-danger {
    background-color: var(--danger-color);
    color: white;
  }
  
  .btn-danger:hover:not(:disabled) {
    background-color: var(--danger-light);
  }
  
  .btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
  }
  
  .btn-icon {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.25rem;
    transition: background-color 0.2s, color 0.2s;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .btn-icon.btn-danger {
    color: var(--danger-color);
  }
  
  .btn-icon.btn-danger:hover {
    background-color: var(--danger-bg);
  }
  
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
    z-index: 1000;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 95%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
  }
  
  .modal-sm {
    max-width: 400px;
  }
  
  .modal-header {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .btn-close {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    font-size: 1.25rem;
    line-height: 1;
    padding: 0.25rem;
  }
  
  .btn-close:hover {
    color: var(--gray-800);
  }
  
  .modal-body {
    padding: 1rem;
  }
  
  .modal-footer {
    padding: 1rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
  }
  
  .mr-1 {
    margin-right: 0.25rem;
  }
  
  .mr-2 {
    margin-right: 0.5rem;
  }
  
  .mt-4 {
    margin-top: 1rem;
  }
  
  @media (max-width: 768px) {
    .form-row {
      grid-template-columns: 1fr;
    }
    
    .adjustment-lines {
      overflow-x: auto;
    }
    
    .line-headers, 
    .adjustment-line {
      min-width: 800px;
    }
  }
  </style>