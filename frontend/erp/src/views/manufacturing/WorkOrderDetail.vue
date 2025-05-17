<!-- src/views/manufacturing/WorkOrderDetail.vue -->
<template>
  <div class="work-order-detail-page">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h2 class="card-title">
            Work Order: {{ workOrder.wo_number }}
            <span class="badge ml-2" :class="getStatusClass(workOrder.status)">{{ workOrder.status }}</span>
          </h2>
          <p class="card-subtitle">{{ workOrder.product_name }}</p>
        </div>
        <div class="actions">
          <router-link 
            v-if="workOrder.status === 'Draft'"
            :to="`/manufacturing/work-orders/${workOrder.wo_id}/edit`" 
            class="btn btn-info mr-2"
          >
            <i class="fas fa-edit mr-1"></i> Edit
          </router-link>
          <button 
            v-if="workOrder.status === 'Draft'"
            @click="confirmRelease" 
            class="btn btn-success mr-2"
          >
            <i class="fas fa-play-circle mr-1"></i> Release
          </button>
          <button 
            v-if="workOrder.status === 'Released'"
            @click="confirmStart" 
            class="btn btn-success mr-2"
          >
            <i class="fas fa-tasks mr-1"></i> Start Production
          </button>
          <button 
            v-if="workOrder.status === 'In Progress'"
            @click="confirmComplete" 
            class="btn btn-success mr-2"
          >
            <i class="fas fa-check-circle mr-1"></i> Complete
          </button>
          <button 
            v-if="['Draft', 'Planned', 'Released'].includes(workOrder.status)"
            @click="confirmCancel" 
            class="btn btn-danger mr-2"
          >
            <i class="fas fa-ban mr-1"></i> Cancel
          </button>
          <button @click="printWorkOrder" class="btn btn-secondary">
            <i class="fas fa-print mr-1"></i> Print
          </button>
        </div>
      </div>
      
      <!-- Status progress bar -->
      <div class="work-order-progress">
        <div class="progress-steps">
          <div class="progress-step" :class="{ 'active': isStepActive('Draft'), 'completed': isStepCompleted('Draft') }">
            <div class="step-icon">
              <i class="fas fa-file-alt"></i>
            </div>
            <div class="step-label">Draft</div>
          </div>
          <div class="progress-step" :class="{ 'active': isStepActive('Released'), 'completed': isStepCompleted('Released') }">
            <div class="step-icon">
              <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="step-label">Released</div>
          </div>
          <div class="progress-step" :class="{ 'active': isStepActive('In Progress'), 'completed': isStepCompleted('In Progress') }">
            <div class="step-icon">
              <i class="fas fa-cogs"></i>
            </div>
            <div class="step-label">In Progress</div>
          </div>
          <div class="progress-step" :class="{ 'active': isStepActive('Completed'), 'completed': isStepCompleted('Completed') }">
            <div class="step-icon">
              <i class="fas fa-check"></i>
            </div>
            <div class="step-label">Completed</div>
          </div>
        </div>
      </div>
      
      <div class="card-body">
        <!-- Details and Tabs -->
        <div class="row">
          <!-- Work Order Details Column -->
          <div class="col-md-4">
            <div class="info-card">
              <div class="info-card-header">
                <h3>Work Order Details</h3>
              </div>
              <div class="info-card-body">
                <div class="info-item">
                  <div class="info-label">Product:</div>
                  <div class="info-value">{{ workOrder.product_name }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">WO Date:</div>
                  <div class="info-value">{{ formatDate(workOrder.wo_date) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Planned Quantity:</div>
                  <div class="info-value">{{ workOrder.planned_quantity }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Completed Quantity:</div>
                  <div class="info-value">{{ workOrder.completed_quantity || 0 }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">BOM:</div>
                  <div class="info-value">{{ workOrder.bom_code }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Routing:</div>
                  <div class="info-value">{{ workOrder.routing_code }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Planned Start:</div>
                  <div class="info-value">{{ formatDate(workOrder.planned_start_date) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Planned End:</div>
                  <div class="info-value">{{ formatDate(workOrder.planned_end_date) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Actual Start:</div>
                  <div class="info-value">{{ workOrder.actual_start_date ? formatDate(workOrder.actual_start_date) : 'Not started' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Actual End:</div>
                  <div class="info-value">{{ workOrder.actual_end_date ? formatDate(workOrder.actual_end_date) : 'Not completed' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-label">Priority:</div>
                  <div class="info-value">
                    <span class="badge" :class="getPriorityClass(workOrder.priority)">
                      {{ workOrder.priority || 'Medium' }}
                    </span>
                  </div>
                </div>
                <div class="info-item">
                  <div class="info-label">Notes:</div>
                  <div class="info-value notes">{{ workOrder.notes || 'No notes' }}</div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Tabs Column -->
          <div class="col-md-8">
            <div class="tabs-container">
              <div class="tabs-header">
                <div 
                  class="tab-item" 
                  :class="{ 'active': activeTab === 'operations' }"
                  @click="handleTabChange('operations')"
                >
                  <i class="fas fa-tasks mr-2"></i>Operations
                </div>
                <div 
                  class="tab-item" 
                  :class="{ 'active': activeTab === 'materials' }"
                  @click="handleTabChange('materials')"
                >
                  <i class="fas fa-boxes mr-2"></i>Materials
                </div>
                <div 
                  class="tab-item" 
                  :class="{ 'active': activeTab === 'production' }"
                  @click="handleTabChange('production')"
                >
                  <i class="fas fa-industry mr-2"></i>Production
                </div>
                <div 
                  class="tab-item" 
                  :class="{ 'active': activeTab === 'quality' }"
                  @click="handleTabChange('quality')"
                >
                  <i class="fas fa-check-square mr-2"></i>Quality
                </div>
              </div>
              
              <div class="tab-content">
                <!-- Operations Tab -->
                <div v-if="activeTab === 'operations'" class="tab-pane">
                  <div class="tab-pane-header">
                    <h3>Operations</h3>
                    <div v-if="workOrder.status === 'In Progress'" class="tab-actions">
                      <button class="btn btn-sm btn-success" @click="scheduleOperations">
                        <i class="fas fa-calendar-alt mr-1"></i> Schedule Operations
                      </button>
                    </div>
                  </div>
                  
                  <div v-if="isLoading" class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Loading operations...
                  </div>
                  
                  <div v-else-if="operations.length === 0" class="empty-state">
                    <div class="empty-icon">
                      <i class="fas fa-tasks"></i>
                    </div>
                    <h4>No Operations</h4>
                    <p>This work order has no operations defined.</p>
                  </div>
                  
                  <div v-else class="operations-list">
                    <div v-for="operation in operations" :key="operation.operation_id" class="operation-item">
                      <div class="operation-header" @click="toggleOperation(operation.operation_id)">
                        <div class="operation-info">
                          <div class="operation-sequence">{{ operation.sequence }}</div>
                          <div class="operation-name">{{ operation.operation_name }}</div>
                          <div class="operation-work-center">{{ operation.work_center_name }}</div>
                        </div>
                        <div class="operation-status">
                          <span class="badge" :class="getOperationStatusClass(operation.status)">
                            {{ operation.status }}
                          </span>
                          <i :class="expandedOperations.includes(operation.operation_id) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="ml-2"></i>
                        </div>
                      </div>
                      
                      <div v-if="expandedOperations.includes(operation.operation_id)" class="operation-details">
                        <div class="operation-detail-grid">
                          <div class="detail-group">
                            <div class="detail-label">Work Center:</div>
                            <div class="detail-value">{{ operation.work_center_name }}</div>
                          </div>
                          <div class="detail-group">
                            <div class="detail-label">Setup Time:</div>
                            <div class="detail-value">{{ operation.setup_time }} min</div>
                          </div>
                          <div class="detail-group">
                            <div class="detail-label">Run Time:</div>
                            <div class="detail-value">{{ operation.run_time }} min</div>
                          </div>
                          <div class="detail-group">
                            <div class="detail-label">Total Time:</div>
                            <div class="detail-value">{{ operation.setup_time + operation.run_time }} min</div>
                          </div>
                          <div class="detail-group">
                            <div class="detail-label">Scheduled Start:</div>
                            <div class="detail-value">{{ operation.scheduled_start ? formatDate(operation.scheduled_start) : 'Not scheduled' }}</div>
                          </div>
                          <div class="detail-group">
                            <div class="detail-label">Scheduled End:</div>
                            <div class="detail-value">{{ operation.scheduled_end ? formatDate(operation.scheduled_end) : 'Not scheduled' }}</div>
                          </div>
                          <div class="detail-group">
                            <div class="detail-label">Actual Start:</div>
                            <div class="detail-value">{{ operation.actual_start ? formatDate(operation.actual_start) : 'Not started' }}</div>
                          </div>
                          <div class="detail-group">
                            <div class="detail-label">Actual End:</div>
                            <div class="detail-value">{{ operation.actual_end ? formatDate(operation.actual_end) : 'Not completed' }}</div>
                          </div>
                        </div>
                        
                        <div class="operation-actions">
                          <button 
                            v-if="operation.status === 'Pending' && workOrder.status === 'In Progress'"
                            @click="startOperation(operation)" 
                            class="btn btn-sm btn-success"
                          >
                            <i class="fas fa-play mr-1"></i> Start
                          </button>
                          <button 
                            v-if="operation.status === 'In Progress'"
                            @click="completeOperation(operation)" 
                            class="btn btn-sm btn-success"
                          >
                            <i class="fas fa-check mr-1"></i> Complete
                          </button>
                          <button 
                            @click="viewOperationDetails(operation)" 
                            class="btn btn-sm btn-primary"
                          >
                            <i class="fas fa-eye mr-1"></i> Details
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Materials Tab -->
                <div v-if="activeTab === 'materials'" class="tab-pane">
                  <div class="tab-pane-header">
                    <h3>Required Materials</h3>
                  </div>
                  
                  <div v-if="isLoading" class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Loading materials...
                  </div>
                  
                  <div v-else-if="materials.length === 0" class="empty-state">
                    <div class="empty-icon">
                      <i class="fas fa-boxes"></i>
                    </div>
                    <h4>No Materials</h4>
                    <p>This work order has no materials defined.</p>
                  </div>
                  
                  <div v-else class="table-responsive">
                    <table class="materials-table">
                      <thead>
                        <tr>
                          <th>Item Code</th>
                          <th>Name</th>
                          <th>Required Qty</th>
                          <th>UOM</th>
                          <th>Issued Qty</th>
                          <th>Status</th>
                          <th v-if="workOrder.status === 'In Progress'">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="material in materials" :key="material.line_id">
                          <td>{{ material.item_code }}</td>
                          <td>{{ material.item_name }}</td>
<td>{{ material.required_quantity }}</td>
                          <td>{{ material.uom_name }}</td>
                          <td>{{ material.issued_quantity || 0 }}</td>
                          <td>
                            <span class="badge" :class="getMaterialStatusClass(material)">
                              {{ getMaterialStatus(material) }}
                            </span>
                          </td>
                          <td v-if="workOrder.status === 'In Progress'">
                            <button 
                              @click="issueMaterial(material)" 
                              class="btn btn-sm btn-primary"
                              :disabled="material.issued_quantity >= material.quantity"
                            >
                              <i class="fas fa-dolly mr-1"></i> Issue
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                
                <!-- Production Tab -->
                <div v-if="activeTab === 'production'" class="tab-pane">
                  <div class="tab-pane-header">
                    <h3>Production Orders</h3>
                    <div v-if="['In Progress', 'Released'].includes(workOrder.status)" class="tab-actions">
                      <button class="btn btn-sm btn-success" @click="createProductionOrder">
                        <i class="fas fa-plus mr-1"></i> Create Production Order
                      </button>
                    </div>
                  </div>
                  
                  <div v-if="isLoading" class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Loading production orders...
                  </div>
                  
                  <div v-else-if="productionOrders.length === 0" class="empty-state">
                    <div class="empty-icon">
                      <i class="fas fa-industry"></i>
                    </div>
                    <h4>No Production Orders</h4>
                    <p>There are no production orders for this work order yet.</p>
                  </div>
                  
                  <div v-else class="production-orders-list">
                    <div 
                      v-for="order in productionOrders" 
                      :key="order.production_id" 
                      class="production-order-card"
                    >
                      <div class="production-order-header">
                        <div>
                          <h4>{{ order.production_number }}</h4>
                          <span class="text-muted">{{ formatDate(order.production_date) }}</span>
                        </div>
                        <span class="badge" :class="getStatusClass(order.status)">
                          {{ order.status }}
                        </span>
                      </div>
                      <div class="production-order-body">
                        <div class="info-row">
                          <div class="info-col">
                            <div class="info-label">Planned Qty:</div>
                            <div class="info-value">{{ order.planned_quantity }}</div>
                          </div>
                          <div class="info-col">
                            <div class="info-label">Actual Qty:</div>
                            <div class="info-value">{{ order.actual_quantity || 0 }}</div>
                          </div>
                          <div class="info-col">
                            <div class="info-label">Variance:</div>
                            <div class="info-value" :class="getVarianceClass(order)">
                              {{ getVariance(order) }}
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="production-order-footer">
                        <button @click="viewProductionOrder(order)" class="btn btn-sm btn-primary">
                          <i class="fas fa-eye mr-1"></i> View
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Quality Tab -->
                <div v-if="activeTab === 'quality'" class="tab-pane">
                  <div class="tab-pane-header">
                    <h3>Quality Inspections</h3>
                    <div v-if="['In Progress', 'Completed'].includes(workOrder.status)" class="tab-actions">
                      <button class="btn btn-sm btn-success" @click="createQualityInspection">
                        <i class="fas fa-plus mr-1"></i> Create Inspection
                      </button>
                    </div>
                  </div>
                  
                  <div v-if="isLoading" class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Loading quality inspections...
                  </div>
                  
                  <div v-else-if="qualityInspections.length === 0" class="empty-state">
                    <div class="empty-icon">
                      <i class="fas fa-check-square"></i>
                    </div>
                    <h4>No Quality Inspections</h4>
                    <p>There are no quality inspections for this work order yet.</p>
                  </div>
                  
                  <div v-else class="quality-inspections-list">
                    <div 
                      v-for="inspection in qualityInspections" 
                      :key="inspection.inspection_id" 
                      class="quality-inspection-card"
                    >
                      <div class="quality-inspection-header">
                        <div>
                          <h4>Inspection #{{ inspection.inspection_id }}</h4>
                          <span class="text-muted">{{ formatDate(inspection.inspection_date) }}</span>
                        </div>
                        <span class="badge" :class="getQualityStatusClass(inspection.status)">
                          {{ inspection.status }}
                        </span>
                      </div>
                      <div class="quality-inspection-body">
                        <p v-if="inspection.notes">{{ inspection.notes }}</p>
                        <div class="parameters-summary">
                          <div class="parameter-stat">
                            <div class="stat-value">{{ inspection.total_parameters || 0 }}</div>
                            <div class="stat-label">Parameters</div>
                          </div>
                          <div class="parameter-stat text-success">
                            <div class="stat-value">{{ inspection.passed_parameters || 0 }}</div>
                            <div class="stat-label">Passed</div>
                          </div>
                          <div class="parameter-stat text-danger">
                            <div class="stat-value">{{ inspection.failed_parameters || 0 }}</div>
                            <div class="stat-label">Failed</div>
                          </div>
                        </div>
                      </div>
                      <div class="quality-inspection-footer">
                        <button @click="viewQualityInspection(inspection)" class="btn btn-sm btn-primary">
                          <i class="fas fa-eye mr-1"></i> View
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Confirmation Modal -->
    <ConfirmationModal
      v-if="showModal"
      :title="modalTitle"
      :message="modalMessage"
      :confirmButtonText="modalConfirmText"
      :confirmButtonClass="modalConfirmClass"
      @confirm="handleModalConfirm"
      @close="showModal = false"
    />
    
    <!-- Operation Form Modal -->
    <div v-if="showOperationModal" class="modal">
      <div class="modal-backdrop" @click="showOperationModal = false"></div>
      <div class="modal-content">
        <div class="modal-header">
          <h3>Operation Details</h3>
          <button class="close-btn" @click="showOperationModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="selectedOperation">
            <form @submit.prevent="updateOperation">
              <div class="form-group">
                <label>Operation</label>
                <input type="text" class="form-control" :value="selectedOperation.operation_name" disabled />
              </div>
              
              <div class="form-group">
                <label>Work Center</label>
                <input type="text" class="form-control" :value="selectedOperation.work_center_name" disabled />
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Scheduled Start</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="selectedOperation.scheduled_start"
                      :disabled="selectedOperation.status !== 'Pending'"
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Scheduled End</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="selectedOperation.scheduled_end"
                      :disabled="selectedOperation.status !== 'Pending'"
                    />
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Actual Start</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="selectedOperation.actual_start"
                      :disabled="!['In Progress', 'Completed'].includes(selectedOperation.status)"
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Actual End</label>
                    <input 
                      type="date" 
                      class="form-control" 
                      v-model="selectedOperation.actual_end"
                      :disabled="selectedOperation.status !== 'Completed'"
                    />
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Actual Labor Time (min)</label>
                    <input 
                      type="number" 
                      class="form-control" 
                      v-model="selectedOperation.actual_labor_time"
                      :disabled="!['Completed', 'In Progress'].includes(selectedOperation.status)"
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Actual Machine Time (min)</label>
                    <input 
                      type="number" 
                      class="form-control" 
                      v-model="selectedOperation.actual_machine_time"
                      :disabled="!['Completed', 'In Progress'].includes(selectedOperation.status)"
                    />
                  </div>
                </div>
              </div>
              
              <div class="form-group">
                <label>Status</label>
                <select 
                  class="form-control" 
                  v-model="selectedOperation.status"
                  :disabled="workOrder.status !== 'In Progress'"
                >
                  <option value="Pending">Pending</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Completed">Completed</option>
                </select>
              </div>
              
              <div class="form-actions">
                <button 
                  type="button" 
                  class="btn btn-secondary" 
                  @click="showOperationModal = false"
                >
                  Cancel
                </button>
                <button 
                  type="submit" 
                  class="btn btn-primary"
                  :disabled="!isOperationEditable"
                >
                  Update
                </button>
              </div>
            </form>
          </div>
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
  name: 'WorkOrderDetail',
  setup() {
    const router = useRouter();
    const route = useRoute();
    
    // Data
    const workOrder = ref({
      wo_id: '',
      wo_number: '',
      wo_date: '',
      product_name: '',
      planned_quantity: 0,
      completed_quantity: 0,
      bom_code: '',
      routing_code: '',
      planned_start_date: '',
      planned_end_date: '',
      actual_start_date: null,
      actual_end_date: null,
      status: '',
      priority: 'Medium',
      notes: ''
    });
    
    const isLoading = ref(true);
    const activeTab = ref('operations');
    const operations = ref([]);
    const materials = ref([]);
    const productionOrders = ref([]);
    const qualityInspections = ref([]);
    const expandedOperations = ref([]);
    
    // Modal state
    const showModal = ref(false);
    const modalTitle = ref('');
    const modalMessage = ref('');
    const modalConfirmText = ref('');
    const modalConfirmClass = ref('btn btn-primary');
    const modalAction = ref('');
    
    // Operation modal state
    const showOperationModal = ref(false);
    const selectedOperation = ref(null);
    
    const isOperationEditable = computed(() => {
      return workOrder.value.status === 'In Progress' && selectedOperation.value;
    });

    // Methods
    const loadWorkOrder = async () => {
      isLoading.value = true;
      
      try {
        const response = await axios.get(`/work-orders/${route.params.id}`);
        const data = response.data.data;
        
        // Format the work order data
        workOrder.value = {
          wo_id: data.wo_id,
          wo_number: data.wo_number,
          wo_date: data.wo_date,
          product_name: data.item ? data.item.name : 'Unknown',
          item_id: data.item_id,
          planned_quantity: data.planned_quantity,
          completed_quantity: data.completed_quantity || 0,
          bom_id: data.bom_id,
          bom_code: data.bom ? data.bom.bom_code : 'Unknown',
          routing_id: data.routing_id,
          routing_code: data.routing ? data.routing.routing_code : 'Unknown',
          planned_start_date: data.planned_start_date,
          planned_end_date: data.planned_end_date,
          actual_start_date: data.actual_start_date,
          actual_end_date: data.actual_end_date,
          status: data.status,
          priority: data.priority || 'Medium',
          notes: data.notes || ''
        };
        
        // Load operations, materials, production orders based on the active tab
        loadTabData();
      } catch (error) {
        console.error('Error loading work order:', error);
      } finally {
        isLoading.value = false;
      }
    };
    
    const loadTabData = async () => {
      if (activeTab.value === 'operations') {
        await loadOperations();
      } else if (activeTab.value === 'materials') {
        await loadMaterials();
      } else if (activeTab.value === 'production') {
        await loadProductionOrders();
      } else if (activeTab.value === 'quality') {
        await loadQualityInspections();
      }
    };
    
const loadOperations = async () => {
      isLoading.value = true;
      
      try {
        const response = await axios.get(`/work-orders/${route.params.id}/operations`);
        operations.value = response.data.data.map(op => ({
          ...op,
          work_center_name: op.routing_operation?.work_center?.name || 'Unknown'
        }));
      } catch (error) {
        console.error('Error loading operations:', error);
      } finally {
        isLoading.value = false;
      }
    };
    
const loadMaterials = async () => {
      isLoading.value = true;
      
      try {
        // Assuming BOM lines are loaded from /boms/{bom_id}/lines
        const response = await axios.get(`/boms/${workOrder.value.bom_id}/lines`);
        console.log('BOM lines response:', response.data.data);
        console.log('Work order planned quantity:', workOrder.value.planned_quantity);
        
        // Calculate required quantities based on work order planned quantity and yield
        materials.value = response.data.data.map(line => {
let requiredQty = line.quantity * workOrder.value.planned_quantity;
if (line.is_yield_based && line.yield_ratio > 0) {
  requiredQty = requiredQty / line.yield_ratio;
}
// Round up to the nearest integer
requiredQty = Math.ceil(requiredQty);
          return {
            ...line,
            item_code: line.item?.item_code || 'Unknown',
            item_name: line.item?.name || 'Unknown',
            uom_name: line.unit_of_measure?.name || 'Unknown',
            required_quantity: requiredQty,
            issued_quantity: line.issued_quantity || 0
          };
        });
      } catch (error) {
        console.error('Error loading materials:', error);
      } finally {
        isLoading.value = false;
      }
    };
    
    const loadProductionOrders = async () => {
      isLoading.value = true;
      
      try {
        const response = await axios.get(`/production-orders`, {
          params: { wo_id: route.params.id }
        });
        productionOrders.value = response.data.data;
      } catch (error) {
        console.error('Error loading production orders:', error);
      } finally {
        isLoading.value = false;
      }
    };
    
    const loadQualityInspections = async () => {
      isLoading.value = true;
      
      try {
        const response = await axios.get(`/quality-inspections/by-reference/work_order/${route.params.id}`);
        qualityInspections.value = response.data.data.map(inspection => {
          // Calculate summary statistics
          const totalParams = inspection.qualityParameters?.length || 0;
          const passedParams = inspection.qualityParameters?.filter(p => p.is_passed)?.length || 0;
          
          return {
            ...inspection,
            total_parameters: totalParams,
            passed_parameters: passedParams,
            failed_parameters: totalParams - passedParams
          };
        });
      } catch (error) {
        console.error('Error loading quality inspections:', error);
      } finally {
        isLoading.value = false;
      }
    };
    
    const formatDate = (dateString) => {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
      });
    };
    
    const getStatusClass = (status) => {
      switch (status) {
        case 'Draft': return 'badge-secondary';
        case 'Planned': return 'badge-info';
        case 'Released': return 'badge-primary';
        case 'In Progress': return 'badge-warning';
        case 'Completed': return 'badge-success';
        case 'Closed': return 'badge-dark';
        case 'Cancelled': return 'badge-danger';
        default: return 'badge-secondary';
      }
    };
    
    const getPriorityClass = (priority) => {
      switch (priority) {
        case 'Low': return 'badge-info';
        case 'Medium': return 'badge-primary';
        case 'High': return 'badge-warning';
        case 'Urgent': return 'badge-danger';
        default: return 'badge-primary';
      }
    };
    
    const getOperationStatusClass = (status) => {
      switch (status) {
        case 'Pending': return 'badge-secondary';
        case 'In Progress': return 'badge-warning';
        case 'Completed': return 'badge-success';
        default: return 'badge-secondary';
      }
    };
    
    const getQualityStatusClass = (status) => {
      switch (status) {
        case 'Pending': return 'badge-secondary';
        case 'Passed': return 'badge-success';
        case 'Failed': return 'badge-danger';
        default: return 'badge-secondary';
      }
    };
    
    const getMaterialStatus = (material) => {
      if (!material.issued_quantity) return 'Not Issued';
      if (material.issued_quantity < material.quantity) return 'Partially Issued';
      return 'Fully Issued';
    };
    
    const getMaterialStatusClass = (material) => {
      if (!material.issued_quantity) return 'badge-danger';
      if (material.issued_quantity < material.quantity) return 'badge-warning';
      return 'badge-success';
    };
    
    const getVariance = (productionOrder) => {
      if (!productionOrder.actual_quantity) return 0;
      return productionOrder.actual_quantity - productionOrder.planned_quantity;
    };
    
    const getVarianceClass = (productionOrder) => {
      const variance = getVariance(productionOrder);
      if (variance < 0) return 'text-danger';
      if (variance > 0) return 'text-success';
      return '';
    };
    
    const isStepActive = (step) => {
      return workOrder.value.status === step;
    };
    
    const isStepCompleted = (step) => {
      const statusOrder = ['Draft', 'Released', 'In Progress', 'Completed', 'Closed'];
      const currentIndex = statusOrder.indexOf(workOrder.value.status);
      const stepIndex = statusOrder.indexOf(step);
      
      return currentIndex > stepIndex;
    };
    
    const toggleOperation = (operationId) => {
      const index = expandedOperations.value.indexOf(operationId);
      if (index === -1) {
        expandedOperations.value.push(operationId);
      } else {
        expandedOperations.value.splice(index, 1);
      }
    };
    
    // Modal confirmation methods
    const confirmRelease = () => {
      modalTitle.value = 'Release Work Order';
      modalMessage.value = `Are you sure you want to release work order <strong>${workOrder.value.wo_number}</strong>?<br>Once released, it cannot be edited or deleted.`;
      modalConfirmText.value = 'Release';
      modalConfirmClass.value = 'btn btn-success';
      modalAction.value = 'release';
      showModal.value = true;
    };
    
    const confirmStart = () => {
      modalTitle.value = 'Start Production';
      modalMessage.value = `Are you sure you want to start production for work order <strong>${workOrder.value.wo_number}</strong>?`;
      modalConfirmText.value = 'Start';
      modalConfirmClass.value = 'btn btn-success';
      modalAction.value = 'start';
      showModal.value = true;
    };
    
    const confirmComplete = () => {
      modalTitle.value = 'Complete Work Order';
      modalMessage.value = `Are you sure you want to mark work order <strong>${workOrder.value.wo_number}</strong> as completed?`;
      modalConfirmText.value = 'Complete';
      modalConfirmClass.value = 'btn btn-success';
      modalAction.value = 'complete';
      showModal.value = true;
    };
    
    const confirmCancel = () => {
      modalTitle.value = 'Cancel Work Order';
      modalMessage.value = `Are you sure you want to cancel work order <strong>${workOrder.value.wo_number}</strong>?<br>This action cannot be undone.`;
      modalConfirmText.value = 'Cancel';
      modalConfirmClass.value = 'btn btn-danger';
      modalAction.value = 'cancel';
      showModal.value = true;
    };
    
    const handleModalConfirm = async () => {
      try {
        const woId = route.params.id;
        
        if (modalAction.value === 'release') {
          await axios.patch(`/work-orders/${woId}`, { status: 'Released' });
          workOrder.value.status = 'Released';
        } else if (modalAction.value === 'start') {
          await axios.patch(`/work-orders/${woId}`, { 
            status: 'In Progress',
            actual_start_date: new Date().toISOString().split('T')[0]
          });
          workOrder.value.status = 'In Progress';
          workOrder.value.actual_start_date = new Date().toISOString().split('T')[0];
        } else if (modalAction.value === 'complete') {
          await axios.patch(`/work-orders/${woId}`, { 
            status: 'Completed',
            actual_end_date: new Date().toISOString().split('T')[0]
          });
          workOrder.value.status = 'Completed';
          workOrder.value.actual_end_date = new Date().toISOString().split('T')[0];
        } else if (modalAction.value === 'cancel') {
          await axios.patch(`/work-orders/${woId}`, { status: 'Cancelled' });
          workOrder.value.status = 'Cancelled';
        }
      } catch (error) {
        console.error(`Error performing ${modalAction.value} action:`, error);
      } finally {
        showModal.value = false;
      }
    };
    
    // Operation methods
    const startOperation = async (operation) => {
      try {
        await axios.patch(`/work-orders/${route.params.id}/operations/${operation.operation_id}`, {
          status: 'In Progress',
          actual_start: new Date().toISOString().split('T')[0]
        });
        
        // Reload operations
        await loadOperations();
      } catch (error) {
        console.error('Error starting operation:', error);
      }
    };
    
    const completeOperation = async (operation) => {
      try {
        await axios.patch(`/work-orders/${route.params.id}/operations/${operation.operation_id}`, {
          status: 'Completed',
          actual_end: new Date().toISOString().split('T')[0]
        });
        
        // Reload operations
        await loadOperations();
      } catch (error) {
        console.error('Error completing operation:', error);
      }
    };
    
    const viewOperationDetails = (operation) => {
      selectedOperation.value = { ...operation };
      showOperationModal.value = true;
    };
    
    const updateOperation = async () => {
      if (!selectedOperation.value) return;
      
      try {
        await axios.patch(
          `/work-orders/${route.params.id}/operations/${selectedOperation.value.operation_id}`, 
          selectedOperation.value
        );
        
        // Reload operations
        await loadOperations();
        showOperationModal.value = false;
      } catch (error) {
        console.error('Error updating operation:', error);
      }
    };
    
    const scheduleOperations = () => {
      // Logic to auto-schedule operations based on planned start/end dates
      // This could be implemented as a API call to a backend function
      alert('This functionality would call a backend API to auto-schedule operations based on work order dates and operation sequence.');
    };
    
    // Material methods
    const issueMaterial = (material) => {
      // Logic to issue material
      alert(`This functionality would open a form to issue ${material.item_name} from inventory.`);
    };
    
    // Production methods
    const createProductionOrder = () => {
      // Redirect to production order creation page
      router.push(`/manufacturing/production-orders/create?wo_id=${route.params.id}`);
    };
    
    const viewProductionOrder = (productionOrder) => {
      // Redirect to production order detail page
      router.push(`/manufacturing/production-orders/${productionOrder.production_id}`);
    };
    
    // Quality methods
    const createQualityInspection = () => {
      // Redirect to quality inspection creation page
      router.push(`/manufacturing/quality-inspections/create?reference_type=work_order&reference_id=${route.params.id}`);
    };
    
    const viewQualityInspection = (inspection) => {
      // Redirect to quality inspection detail page
      router.push(`/manufacturing/quality-inspections/${inspection.inspection_id}`);
    };
    
    const printWorkOrder = () => {
      // Logic to print the work order
      window.print();
    };
    
    // Watch for tab changes to load appropriate data
    const handleTabChange = (newTab) => {
      activeTab.value = newTab;
      loadTabData();
    };
    
    // Lifecycle hooks
    onMounted(async () => {
      await loadWorkOrder();
    });
    
    return {
      workOrder,
      isLoading,
      operations,
      materials,
      productionOrders,
      qualityInspections,
      activeTab,
      expandedOperations,
      showModal,
      modalTitle,
      modalMessage,
      modalConfirmText,
      modalConfirmClass,
      showOperationModal,
      selectedOperation,
      isOperationEditable,
      formatDate,
      getStatusClass,
      getPriorityClass,
      getOperationStatusClass,
      getQualityStatusClass,
      getMaterialStatus,
      getMaterialStatusClass,
      getVariance,
      getVarianceClass,
      isStepActive,
      isStepCompleted,
      toggleOperation,
      confirmRelease,
      confirmStart,
      confirmComplete,
      confirmCancel,
      handleModalConfirm,
      startOperation,
      completeOperation,
      viewOperationDetails,
      updateOperation,
      scheduleOperations,
      issueMaterial,
      createProductionOrder,
      viewProductionOrder,
      createQualityInspection,
      viewQualityInspection,
      printWorkOrder,
      handleTabChange
    };
  }
};
</script>
<style scoped>
.work-order-detail-page {
  padding: 1rem;
}

.card-subtitle {
  margin-top: 0.5rem;
  color: var(--gray-600);
}

.work-order-progress {
  background-color: var(--gray-100);
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
}

.progress-steps {
  display: flex;
  justify-content: space-between;
  position: relative;
}

.progress-steps::before {
  content: '';
  position: absolute;
  top: 1.75rem;
  left: 0;
  width: 100%;
  height: 4px;
  background-color: var(--gray-300);
  z-index: 0;
}

.progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  z-index: 1;
  width: 25%;
}

.step-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 50%;
  background-color: white;
  border: 2px solid var(--gray-300);
  margin-bottom: 0.75rem;
  font-size: 1.25rem;
  color: var(--gray-500);
}

.progress-step.active .step-icon {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.progress-step.completed .step-icon {
  background-color: var(--success-color);
  border-color: var(--success-color);
  color: white;
}

.step-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-600);
}

.progress-step.active .step-label {
  color: var(--primary-color);
  font-weight: 600;
}

.progress-step.completed .step-label {
  color: var(--success-color);
  font-weight: 600;
}

.row {
  display: flex;
  flex-wrap: wrap;
  margin: 0 -1rem;
}

.col-md-4 {
  flex: 0 0 33.3333%;
  max-width: 33.3333%;
  padding: 0 1rem;
}

.col-md-8 {
  flex: 0 0 66.6667%;
  max-width: 66.6667%;
  padding: 0 1rem;
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
  
  .col-md-4, .col-md-8, .col-md-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }
}

.info-card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  margin-bottom: 1rem;
}

.info-card-header {
  padding: 1rem;
  border-bottom: 1px solid var(--gray-200);
}

.info-card-header h3 {
  margin: 0;
  font-size: 1.125rem;
  color: var(--gray-800);
}

.info-card-body {
  padding: 1rem;
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

.info-value.notes {
  white-space: pre-line;
}

.tabs-container {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.tabs-header {
  display: flex;
  border-bottom: 1px solid var(--gray-200);
}

.tab-item {
  padding: 1rem 1.5rem;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  position: relative;
  display: flex;
  align-items: center;
}

.tab-item:hover {
  background-color: var(--gray-50);
}

.tab-item.active {
  color: var(--primary-color);
  font-weight: 600;
}

.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  width: 100%;
  height: 2px;
  background-color: var(--primary-color);
}

.tab-content {
  min-height: 400px;
}

.tab-pane {
  padding: 1.5rem;
}

.tab-pane-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.tab-pane-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

.loading-indicator, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 0;
  color: var(--gray-500);
}

.empty-icon {
  font-size: 3rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

.empty-state h4 {
  margin-bottom: 0.5rem;
  color: var(--gray-700);
}

.operation-item {
  border: 1px solid var(--gray-200);
  border-radius: 0.5rem;
  overflow: hidden;
  margin-bottom: 1rem;
}

.operation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: var(--gray-50);
  cursor: pointer;
}

.operation-info {
  display: flex;
  align-items: center;
}

.operation-sequence {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background-color: var(--gray-200);
  color: var(--gray-800);
  font-weight: 600;
  margin-right: 1rem;
}

.operation-name {
  font-weight: 500;
  color: var(--gray-800);
  margin-right: 1rem;
}

.operation-work-center {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.operation-status {
  display: flex;
  align-items: center;
}

.operation-details {
  padding: 1rem;
  border-top: 1px solid var(--gray-200);
  background-color: white;
}

.operation-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.detail-group {
  display: flex;
}

.detail-label {
  font-weight: 500;
  color: var(--gray-600);
  width: 40%;
}

.detail-value {
  flex: 1;
  color: var(--gray-800);
}

.operation-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}

.materials-table {
  width: 100%;
  border-collapse: collapse;
}

.materials-table th,
.materials-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--gray-200);
  text-align: left;
}

.materials-table th {
  font-weight: 600;
  color: var(--gray-600);
  background-color: var(--gray-50);
}

.materials-table tbody tr:hover {
  background-color: var(--gray-50);
}

.production-orders-list,
.quality-inspections-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

@media (max-width: 768px) {
  .production-orders-list,
  .quality-inspections-list {
    grid-template-columns: 1fr;
  }
}

.production-order-card,
.quality-inspection-card {
  background-color: white;
  border: 1px solid var(--gray-200);
  border-radius: 0.5rem;
  overflow: hidden;
}

.production-order-header,
.quality-inspection-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--gray-200);
  background-color: var(--gray-50);
}

.production-order-header h4,
.quality-inspection-header h4 {
  margin: 0;
  font-size: 1rem;
}

.production-order-body,
.quality-inspection-body {
  padding: 1rem;
}

.info-row {
  display: flex;
  flex-wrap: wrap;
  margin: -0.5rem;
}

.info-col {
  padding: 0.5rem;
  flex: 1;
}

.parameters-summary {
  display: flex;
  justify-content: space-between;
  margin-top: 1rem;
  text-align: center;
}

.parameter-stat {
  flex: 1;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: 600;
}

.text-success {
  color: var(--success-color);
}

.text-danger {
  color: var(--danger-color);
}

.production-order-footer,
.quality-inspection-footer {
  padding: 0.75rem 1rem;
  border-top: 1px solid var(--gray-200);
  background-color: var(--gray-50);
  display: flex;
  justify-content: flex-end;
}

/* Modal styles */
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 50;
  display: flex;
  justify-content: center;
  align-items: center;
}

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 40;
}

.modal-content {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 600px;
  z-index: 60;
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
}

.close-btn {
  background: none;
  border: none;
  color: var(--gray-500);
  cursor: pointer;
}

.modal-body {
  padding: 1.5rem;
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
  display: block;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 0.375rem;
  background-color: white;
  color: var(--gray-800);
}

.form-control:disabled {
  background-color: var(--gray-100);
  cursor: not-allowed;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
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

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
}

.btn-secondary {
  background-color: var(--gray-200);
  color: var(--gray-800);
}

.btn-secondary:hover {
  background-color: var(--gray-300);
}

.btn-success {
  background-color: var(--success-color);
  color: white;
}

.btn-success:hover {
  background-color: #047857;
}

.btn-info {
  background-color: #0ea5e9;
  color: white;
}

.btn-info:hover {
  background-color: #0284c7;
}

.btn-danger {
  background-color: var(--danger-color);
  color: white;
}

.btn-danger:hover {
  background-color: #b91c1c;
}

.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
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

.badge-primary {
  background-color: #e0f2fe;
  color: #0369a1;
}

.badge-secondary {
  background-color: #f1f5f9;
  color: #475569;
}

.badge-success {
  background-color: #dcfce7;
  color: #166534;
}

.badge-warning {
  background-color: #fef3c7;
  color: #92400e;
}

.badge-danger {
  background-color: #fee2e2;
  color: #b91c1c;
}

.badge-info {
  background-color: #dbeafe;
  color: #1e40af;
}

.badge-dark {
  background-color: #1e293b;
  color: white;
}

@media print {
  .actions, .tabs-header, .operation-actions, .tab-actions {
    display: none !important;
  }
  
  .col-md-4, .col-md-8 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .card {
    box-shadow: none !important;
    border: 1px solid #ddd;
  }
}
</style>
