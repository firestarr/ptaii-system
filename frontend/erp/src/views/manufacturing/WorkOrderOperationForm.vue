<!-- src/views/manufacturing/WorkOrderOperationForm.vue -->
<template>
    <div class="operation-form-page">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Operation Details</h2>
          <p class="card-subtitle">Work Order: {{ workOrderNumber }}</p>
        </div>
        <div class="card-body">
          <div v-if="isLoading" class="loading-indicator">
            <i class="fas fa-spinner fa-spin"></i> Loading operation details...
          </div>
          
          <div v-else>
            <!-- Form Errors -->
            <div v-if="formErrors.length > 0" class="alert alert-danger">
              <h5><i class="fas fa-exclamation-triangle mr-2"></i> Please fix the following errors:</h5>
              <ul>
                <li v-for="(error, index) in formErrors" :key="index">{{ error }}</li>
              </ul>
            </div>
            
            <!-- Operation Information Section -->
            <div class="section-title">
              <h3>Operation Information</h3>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="info-item">
                  <div class="info-label">Operation:</div>
                  <div class="info-value">{{ operation.operation_name }}</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="info-item">
                  <div class="info-label">Work Center:</div>
                  <div class="info-value">{{ operation.work_center_name }}</div>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="info-item">
                  <div class="info-label">Sequence:</div>
                  <div class="info-value">{{ operation.sequence }}</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="info-item">
                  <div class="info-label">Status:</div>
                  <div class="info-value">
                    <span class="badge" :class="getStatusClass(operation.status)">
                      {{ operation.status }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Scheduling Section -->
            <div class="section-title">
              <h3>Scheduling</h3>
            </div>
            
            <form @submit.prevent="submitForm">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Scheduled Start Date</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="operation.scheduled_start"
                      :disabled="!canEditSchedule"
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Scheduled End Date</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="operation.scheduled_end"
                      :disabled="!canEditSchedule"
                    />
                    <div v-if="endDateError" class="error-message">
                      End date must be after start date
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Time Tracking Section -->
              <div class="section-title">
                <h3>Time Tracking</h3>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Actual Start Date</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="operation.actual_start"
                      :disabled="!canEditActualStart"
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Actual End Date</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="operation.actual_end"
                      :disabled="!canEditActualEnd"
                    />
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Setup Time (min)</label>
                    <div class="time-display">
                      <div class="planned-time">
                        <span class="time-label">Planned:</span>
                        <span class="time-value">{{ operation.setup_time }}</span>
                      </div>
                      <div class="actual-time">
                        <span class="time-label">Actual:</span>
                        <input 
                          type="number" 
                          class="form-control" 
                          v-model="operation.actual_setup_time"
                          :disabled="!canEditActualTimes"
                          min="0"
                        />
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Run Time (min)</label>
                    <div class="time-display">
                      <div class="planned-time">
                        <span class="time-label">Planned:</span>
                        <span class="time-value">{{ operation.run_time }}</span>
                      </div>
                      <div class="actual-time">
                        <span class="time-label">Actual:</span>
                        <input 
                          type="number" 
                          class="form-control" 
                          v-model="operation.actual_run_time"
                          :disabled="!canEditActualTimes"
                          min="0"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Labor Time (min)</label>
                    <input 
                      type="number" 
                      class="form-control" 
                      v-model="operation.actual_labor_time"
                      :disabled="!canEditActualTimes"
                      min="0"
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Machine Time (min)</label>
                    <input 
                      type="number" 
                      class="form-control" 
                      v-model="operation.actual_machine_time"
                      :disabled="!canEditActualTimes"
                      min="0"
                    />
                  </div>
                </div>
              </div>
              
              <!-- Status Section -->
              <div class="section-title">
                <h3>Status Update</h3>
              </div>
              
              <div class="form-group">
                <label>Status</label>
                <select 
                  class="form-control" 
                  v-model="operation.status"
                  :disabled="!canEditStatus"
                >
                  <option value="Pending">Pending</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Completed">Completed</option>
                </select>
              </div>
              
              <div class="form-group">
                <label>Notes</label>
                <textarea 
                  class="form-control" 
                  v-model="operation.notes" 
                  rows="3"
                  placeholder="Enter any additional notes or observations..."
                ></textarea>
              </div>
              
              <!-- Action Buttons -->
              <div class="form-actions">
                <button type="button" class="btn btn-secondary mr-2" @click="cancelForm">
                  Cancel
                </button>
                <button 
                  v-if="operation.status === 'Pending'"
                  type="button" 
                  class="btn btn-success mr-2" 
                  @click="startOperation"
                  :disabled="!canStartOperation"
                >
                  <i class="fas fa-play mr-1"></i> Start Operation
                </button>
                <button 
                  v-if="operation.status === 'In Progress'"
                  type="button" 
                  class="btn btn-success mr-2" 
                  @click="completeOperation"
                  :disabled="!canCompleteOperation"
                >
                  <i class="fas fa-check mr-1"></i> Complete Operation
                </button>
                <button type="submit" class="btn btn-primary" :disabled="isSubmitting || !canUpdate">
                  <i v-if="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
                  Update
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import { ref, computed, onMounted } from 'vue';
  import { useRouter, useRoute } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'WorkOrderOperationForm',
    setup() {
      const router = useRouter();
      const route = useRoute();
      
      // Data
      const isLoading = ref(true);
      const isSubmitting = ref(false);
      const formErrors = ref([]);
      const endDateError = ref(false);
      const workOrderNumber = ref('');
      const workOrderStatus = ref('');
      
      const operation = ref({
        operation_id: '',
        operation_name: '',
        work_center_name: '',
        sequence: 0,
        status: '',
        scheduled_start: null,
        scheduled_end: null,
        actual_start: null,
        actual_end: null,
        setup_time: 0,
        run_time: 0,
        actual_setup_time: 0,
        actual_run_time: 0,
        actual_labor_time: 0,
        actual_machine_time: 0,
        notes: ''
      });
      
      // Computed properties
      const canEditSchedule = computed(() => {
        return operation.value.status === 'Pending' && workOrderStatus.value !== 'Draft';
      });
      
      const canEditActualStart = computed(() => {
        return ['In Progress', 'Completed'].includes(operation.value.status);
      });
      
      const canEditActualEnd = computed(() => {
        return operation.value.status === 'Completed';
      });
      
      const canEditActualTimes = computed(() => {
        return ['In Progress', 'Completed'].includes(operation.value.status);
      });
      
      const canEditStatus = computed(() => {
        return workOrderStatus.value === 'In Progress';
      });
      
      const canStartOperation = computed(() => {
        return operation.value.status === 'Pending' && workOrderStatus.value === 'In Progress';
      });
      
      const canCompleteOperation = computed(() => {
        return operation.value.status === 'In Progress';
      });
      
      const canUpdate = computed(() => {
        // Check if the form has any changes
        return true; // Simplified for now
      });
      
      // Methods
      const loadOperation = async () => {
        isLoading.value = true;
        
        try {
          const workOrderId = route.params.workOrderId;
          const operationId = route.params.operationId;
          
          // Load work order info first to get its status
          const workOrderResponse = await axios.get(`/work-orders/${workOrderId}`);
          workOrderNumber.value = workOrderResponse.data.data.wo_number;
          workOrderStatus.value = workOrderResponse.data.data.status;
          
          // Load operation details
          const operationResponse = await axios.get(`/work-orders/${workOrderId}/operations/${operationId}`);
          const data = operationResponse.data.data;
          
          // Map API response to our form model
          operation.value = {
            operation_id: data.operation_id,
            operation_name: data.routingOperation?.operation_name || 'Unknown',
            work_center_name: data.routingOperation?.workCenter?.name || 'Unknown',
            sequence: data.routingOperation?.sequence || 0,
            status: data.status,
            scheduled_start: data.scheduled_start,
            scheduled_end: data.scheduled_end,
            actual_start: data.actual_start,
            actual_end: data.actual_end,
            setup_time: data.routingOperation?.setup_time || 0,
            run_time: data.routingOperation?.run_time || 0,
            actual_setup_time: data.actual_setup_time || 0,
            actual_run_time: data.actual_run_time || 0,
            actual_labor_time: data.actual_labor_time || 0,
            actual_machine_time: data.actual_machine_time || 0,
            notes: data.notes || ''
          };
        } catch (error) {
          console.error('Error loading operation:', error);
          formErrors.value.push('Failed to load operation details.');
        } finally {
          isLoading.value = false;
        }
      };
      
      const validateForm = () => {
        formErrors.value = [];
        endDateError.value = false;
        
        // Check if scheduled end date is after scheduled start date
        if (operation.value.scheduled_start && operation.value.scheduled_end) {
          if (new Date(operation.value.scheduled_end) <= new Date(operation.value.scheduled_start)) {
            formErrors.value.push('Scheduled end date must be after the scheduled start date.');
            endDateError.value = true;
          }
        }
        
        // Check if actual end date is after actual start date
        if (operation.value.actual_start && operation.value.actual_end) {
          if (new Date(operation.value.actual_end) <= new Date(operation.value.actual_start)) {
            formErrors.value.push('Actual end date must be after the actual start date.');
          }
        }
        
        return formErrors.value.length === 0;
      };
      
      const submitForm = async () => {
        if (!validateForm()) return;
        
        isSubmitting.value = true;
        
        try {
          const workOrderId = route.params.workOrderId;
          const operationId = route.params.operationId;
          
          // Prepare data to send to API
          const updatedData = {
            scheduled_start: operation.value.scheduled_start,
            scheduled_end: operation.value.scheduled_end,
            actual_start: operation.value.actual_start,
            actual_end: operation.value.actual_end,
            actual_labor_time: operation.value.actual_labor_time,
            actual_machine_time: operation.value.actual_machine_time,
            status: operation.value.status,
            notes: operation.value.notes
          };
          
          await axios.patch(`/work-orders/${workOrderId}/operations/${operationId}`, updatedData);
          
          // Redirect back to work order details
          router.push(`/manufacturing/work-orders/${workOrderId}`);
        } catch (error) {
          console.error('Error updating operation:', error);
          if (error.response && error.response.data && error.response.data.errors) {
            // Extract validation errors from the response
            const serverErrors = error.response.data.errors;
            formErrors.value = Object.values(serverErrors).flat();
          } else {
            formErrors.value.push('An error occurred while updating the operation.');
          }
        } finally {
          isSubmitting.value = false;
        }
      };
      
      const startOperation = async () => {
        try {
          const workOrderId = route.params.workOrderId;
          const operationId = route.params.operationId;
          
          operation.value.status = 'In Progress';
          operation.value.actual_start = new Date().toISOString().split('T')[0];
          
          await axios.patch(`/work-orders/${workOrderId}/operations/${operationId}`, {
            status: 'In Progress',
            actual_start: operation.value.actual_start
          });
          
          // No redirect here, just update the form state
        } catch (error) {
          console.error('Error starting operation:', error);
          formErrors.value.push('Failed to start the operation.');
        }
      };
      
      const completeOperation = async () => {
        try {
          const workOrderId = route.params.workOrderId;
          const operationId = route.params.operationId;
          
          operation.value.status = 'Completed';
          operation.value.actual_end = new Date().toISOString().split('T')[0];
          
          await axios.patch(`/work-orders/${workOrderId}/operations/${operationId}`, {
            status: 'Completed',
            actual_end: operation.value.actual_end
          });
          
          // No redirect here, just update the form state
        } catch (error) {
          console.error('Error completing operation:', error);
          formErrors.value.push('Failed to complete the operation.');
        }
      };
      
      const cancelForm = () => {
        const workOrderId = route.params.workOrderId;
        router.push(`/manufacturing/work-orders/${workOrderId}`);
      };
      
      const getStatusClass = (status) => {
        switch (status) {
          case 'Pending': return 'badge-secondary';
          case 'In Progress': return 'badge-warning';
          case 'Completed': return 'badge-success';
          default: return 'badge-secondary';
        }
      };
      
      // Lifecycle hooks
      onMounted(async () => {
        await loadOperation();
      });
      
      return {
        isLoading,
        isSubmitting,
        formErrors,
        endDateError,
        workOrderNumber,
        operation,
        canEditSchedule,
        canEditActualStart,
        canEditActualEnd,
        canEditActualTimes,
        canEditStatus,
        canStartOperation,
        canCompleteOperation,
        canUpdate,
        submitForm,
        startOperation,
        completeOperation,
        cancelForm,
        getStatusClass
      };
    }
  };
  </script>
  
  <style scoped>
  .operation-form-page {
    padding: 1rem;
  }
  
  .card-subtitle {
    margin-top: 0.5rem;
    color: var(--gray-600);
  }
  
  .section-title {
    margin: 1.5rem 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .section-title h3 {
    font-size: 1.25rem;
    margin: 0;
    color: var(--gray-800);
  }
  
  .row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -1rem;
  }
  
  .col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding: 0 1rem;
  }
  
  @media (max-width: 768px) {
    .row {
      flex-direction: column;
    }
    
    .col-md-6 {
      flex: 0 0 100%;
      max-width: 100%;
    }
  }
  
  .info-item {
    display: flex;
    margin-bottom: 0.75rem;
  }
  
  .info-label {
    font-weight: 500;
    width: 40%;
    color: var(--gray-600);
  }
  
  .info-value {
    flex: 1;
    color: var(--gray-800);
  }
  
  .form-group {
    margin-bottom: 1.5rem;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .form-control {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    background-color: white;
    color: var(--gray-800);
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  
  .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
  }
  
  .form-control:disabled {
    background-color: var(--gray-100);
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .error-message {
    color: var(--danger-color);
    font-size: 0.875rem;
    margin-top: 0.25rem;
  }
  
  .time-display {
    display: flex;
    align-items: center;
    gap: 1rem;
  }
  
  .planned-time, .actual-time {
    display: flex;
    align-items: center;
  }
  
  .time-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-right: 0.5rem;
    min-width: 3.5rem;
  }
  
  .time-value {
    font-weight: 500;
  }
  
  .actual-time .form-control {
    width: 5rem;
    padding: 0.25rem 0.5rem;
    text-align: center;
  }
  
  .form-actions {
    margin-top: 2rem;
    display: flex;
    justify-content: flex-end;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
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
  
  .btn-success {
    background-color: var(--success-color);
    color: white;
  }
  
  .btn-success:hover:not(:disabled) {
    background-color: #047857;
  }
  
  .btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }
  
  .mr-2 {
    margin-right: 0.5rem;
  }
  
  .loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px;
    color: var(--gray-500);
  }
  
  .badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    text-align: center;
    white-space: nowrap;
  }
  
  .badge-secondary {
    background-color: #f1f5f9;
    color: #475569;
  }
  
  .badge-warning {
    background-color: #fef3c7;
    color: #92400e;
  }
  
  .badge-success {
    background-color: #dcfce7;
    color: #166534;
  }
  
  .alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 0.375rem;
  }
  
  .alert-danger {
    background-color: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
  }
  
  .alert h5 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
  }
  
  .alert ul {
    margin: 0;
    padding-left: 1.5rem;
  }
  </style>