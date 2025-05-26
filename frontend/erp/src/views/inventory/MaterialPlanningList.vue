<!-- src/views/materials/MaterialPlanningList.vue -->
<template>
  <div class="material-planning-container">
    <!-- Material Planning Header -->
    <div class="page-header">
      <h1 class="page-title">Material Planning</h1>
      <div class="header-actions">
        <button class="btn btn-primary" @click="showGenerateModal = true">
          <i class="fas fa-cogs"></i> Generate Material Plans
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card mb-4">
      <div class="card-body">
        <div class="filter-controls">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="form-group">
                <label class="form-label">Start Period</label>
                <input
                  type="month"
                  v-model="filters.startPeriod"
                  class="form-control"
                  @change="handleFilterChange"
                />
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="form-label">Material Type</label>
                <select v-model="filters.materialType" class="form-control" @change="handleFilterChange">
                  <option value="">All Types</option>
                  <option value="FG">Finished Goods</option>
                  <option value="RM">Raw Materials</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select v-model="filters.status" class="form-control" @change="handleFilterChange">
                  <option value="">All Status</option>
                  <option value="Draft">Draft</option>
                  <option value="Requisitioned">Requisitioned</option>
                  <option value="Work Order Created">Work Order Created</option>
                  <option value="Approved">Approved</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="form-label">Search</label>
                <div class="search-input">
                  <input
                    type="text"
                    v-model="filters.search"
                    class="form-control"
                    placeholder="Search material plans..."
                  />
                  <button class="btn btn-outline-primary" @click="handleFilterChange">
                    <i class="fas fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="filter-actions mt-3">
          <button class="btn btn-outline" @click="exportPlans">
            <i class="fas fa-file-export"></i> Export
          </button>
        </div>
      </div>
    </div>

    <!-- Material Plans Horizontal View -->
    <div class="card">
      <div v-if="isLoading" class="text-center py-5">
        <i class="fas fa-spinner fa-spin fa-2x"></i>
        <p class="mt-2">Loading material planning data...</p>
      </div>
      
      <div v-else-if="groupedPlans.length === 0" class="text-center py-5">
        <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
        <h4>No material plans available</h4>
        <p class="text-muted">
          Try adjusting your filters or generate material plans first
        </p>
      </div>
      
      <div v-else class="card-body">
        <div v-for="(group, index) in groupedPlans" :key="index" class="mb-5">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">
              {{ group.material_type === 'FG' ? 'Finished Goods' : 'Raw Materials' }}
              <span class="badge bg-primary ms-2">{{ group.items.length }} items</span>
            </h3>
            <button
              class="btn btn-sm btn-outline-secondary"
              @click="toggleGroupExpand(group.material_type)"
            >
              <i
                :class="
                  expandedGroups.includes(group.material_type)
                    ? 'fas fa-chevron-up'
                    : 'fas fa-chevron-down'
                "
              ></i>
              {{ expandedGroups.includes(group.material_type) ? 'Collapse' : 'Expand' }}
            </button>
          </div>

          <div v-show="expandedGroups.includes(group.material_type)">
            <div class="table-responsive">
              <table class="table table-bordered table-hover">
                <thead class="bg-light">
                  <tr>
                    <th style="min-width: 200px">Item</th>
                    <th
                      v-for="period in periodRange"
                      :key="period"
                      style="min-width: 120px"
                      class="text-center"
                    >
                      {{ formatPeriodHeader(period) }}
                    </th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in group.items" :key="item.item_id">
                    <td>
                      <div class="d-flex flex-column">
                        <strong>{{ item.item_code }}</strong>
                        <small class="text-muted">{{ item.name }}</small>
                        <small class="status-chip" :class="statusClasses[item.status]">
                          {{ item.status }}
                        </small>
                      </div>
                    </td>
                    <td
                      v-for="period in periodRange"
                      :key="period"
                      class="text-center"
                      :class="{
                        'bg-light-success': getPeriodData(item, period).net_requirement <= 0 && !getPeriodData(item, period).is_empty,
                        'bg-light-danger': getPeriodData(item, period).net_requirement > 0 && !getPeriodData(item, period).is_empty,
                        'empty-period': getPeriodData(item, period).is_empty
                      }"
                    >
                      <div class="planning-cell">
                        <div class="planning-row">
                          <span class="planning-label">Forecast:</span>
                          <span class="planning-value">{{ formatNumber(getPeriodData(item, period).forecast_quantity) }}</span>
                        </div>
                        <div class="planning-row">
                          <span class="planning-label">Available:</span>
                          <span class="planning-value">{{ formatNumber(getPeriodData(item, period).available_stock) }}</span>
                        </div>
                        <div class="planning-row">
                          <span class="planning-label">Net Req:</span>
                          <span class="planning-value font-weight-bold">{{ formatNumber(getPeriodData(item, period).net_requirement) }}</span>
                        </div>
                        <div class="planning-row">
                          <span class="planning-label">Planned:</span>
                          <span class="planning-value">{{ formatNumber(getPeriodData(item, period).planned_order_quantity) }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="text-center action-column">
                      <div class="action-buttons">
                        <button 
                          class="btn-icon" 
                          @click="viewDetails(item)"
                          title="View Details"
                        >
                          <i class="fas fa-eye"></i>
                        </button>
                        <button 
                          v-if="item.status === 'Draft'" 
                          class="btn-icon btn-icon-edit" 
                          @click="editPlan(item)"
                          title="Edit"
                        >
                          <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button 
                          v-if="item.status === 'Draft'" 
                          class="btn-icon btn-icon-danger" 
                          @click="deletePlan(item)"
                          title="Delete"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                        <!-- Generate WO Button for Finished Goods -->
                        <button 
                          v-if="item.material_type === 'FG' && item.status === 'Draft' && hasPositiveNetRequirement(item)" 
                          class="btn-icon btn-icon-manufacturing" 
                          @click="generateWorkOrder(item)"
                          title="Generate Work Order"
                        >
                          <i class="fas fa-cogs"></i>
                        </button>
                        <!-- Generate PR Button for Raw Materials -->
                        <button 
                          v-if="item.material_type === 'RM' && item.status === 'Draft' && hasPositiveNetRequirement(item)" 
                          class="btn-icon btn-icon-success" 
                          @click="generatePurchaseRequisition(item)"
                          title="Generate Purchase Requisition"
                        >
                          <i class="fas fa-file-invoice"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <PaginationComponent
          :current-page="currentPage"
          :total-pages="totalPages"
          :from="from"
          :to="to"
          :total="total"
          @page-changed="handlePageChange"
        />
      </div>
    </div>

    <!-- Generate Material Plans Modal -->
    <div v-if="showGenerateModal" class="modal">
      <div class="modal-backdrop" @click="closeGenerateModal"></div>
      <div class="modal-content modal-md">
        <div class="modal-header">
          <h2>Generate Material Plans</h2>
          <button class="close-btn" @click="closeGenerateModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="generatePlans">
            <div class="form-group">
              <label>Start Period <span class="required">*</span></label>
              <input
                type="month"
                v-model="generateForm.startPeriod"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label>Buffer Percentage <span class="required">*</span></label>
              <input
                type="number"
                v-model="generateForm.bufferPercentage"
                class="form-control"
                min="0"
                max="100"
                required
              />
            </div>
            <div class="form-group">
              <label>Select Items (Optional)</label>
              <multiselect
                v-model="generateForm.itemIds"
                :options="itemOptions"
                :multiple="true"
                :searchable="true"
                track-by="value"
                label="label"
                placeholder="Leave empty to generate for all items"
              />
            </div>
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="closeGenerateModal">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isGenerating">
                <i class="fas fa-cogs" :class="{ 'fa-spin': isGenerating }"></i>
                {{ isGenerating ? 'Generating...' : 'Generate' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Work Order Generation Modal -->
    <div v-if="showWOModal" class="modal">
      <div class="modal-backdrop" @click="closeWOModal"></div>
      <div class="modal-content modal-md">
        <div class="modal-header">
          <h2>Generate Work Order</h2>
          <button class="close-btn" @click="closeWOModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="submitWO">
            <div class="form-group">
              <label>Planning Period</label>
              <input
                type="month"
                v-model="woForm.period"
                class="form-control"
                readonly
              />
            </div>
            <div class="form-group">
              <label>Planned Start Date <span class="required">*</span></label>
              <input
                type="date"
                v-model="woForm.plannedStartDate"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label>Lead Time (days) <span class="required">*</span></label>
              <input
                type="number"
                v-model="woForm.leadTimeDays"
                class="form-control"
                min="1"
                required
              />
            </div>
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="closeWOModal">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isGeneratingWO">
                <i class="fas fa-cogs" :class="{ 'fa-spin': isGeneratingWO }"></i>
                {{ isGeneratingWO ? 'Generating...' : 'Generate WO' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Purchase Requisition Generation Modal -->
    <div v-if="showPRModal" class="modal">
      <div class="modal-backdrop" @click="closePRModal"></div>
      <div class="modal-content modal-md">
        <div class="modal-header">
          <h2>Generate Purchase Requisition</h2>
          <button class="close-btn" @click="closePRModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="submitPR">
            <div class="form-group">
              <label>Planning Period</label>
              <input
                type="month"
                v-model="prForm.period"
                class="form-control"
                readonly
              />
            </div>
            <div class="form-group">
              <label>Lead Time (days) <span class="required">*</span></label>
              <input
                type="number"
                v-model="prForm.leadTimeDays"
                class="form-control"
                min="0"
                required
              />
            </div>
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="closePRModal">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isGeneratingPR">
                <i class="fas fa-file-invoice" :class="{ 'fa-spin': isGeneratingPR }"></i>
                {{ isGeneratingPR ? 'Generating...' : 'Generate PR' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Confirmation Modals -->
    <ConfirmationModal
      v-if="showDeleteModal"
      title="Delete Material Plan"
      message="Are you sure you want to delete this material plan?"
      @confirm="confirmDelete"
      @close="closeDeleteModal"
    />
  </div>
</template>

<script>
import axios from 'axios';
import PaginationComponent from '@/components/common/Pagination.vue';
import ConfirmationModal from '@/components/common/ConfirmationModal.vue';
import Multiselect from 'vue-multiselect';

export default {
  name: 'MaterialPlanningList',
  components: {
    PaginationComponent,
    ConfirmationModal,
    Multiselect
  },
  data() {
    return {
      plans: [],
      allFilteredPlans: [],
      allGroupedPlans: [],
      groupedPlans: [],
      periodRange: [],
      isLoading: false,
      currentPage: 1,
      totalPages: 1,
      from: 0,
      to: 0,
      total: 0,
      expandedGroups: [],
      filters: {
        search: '',
        startPeriod: this.getCurrentMonth(),
        materialType: '',
        status: ''
      },
      statusClasses: {
        'Draft': 'bg-gray',
        'Requisitioned': 'bg-blue',
        'Work Order Created': 'bg-purple',
        'Approved': 'bg-green'
      },
      showGenerateModal: false,
      isGenerating: false,
      generateForm: {
        startPeriod: '',
        bufferPercentage: 10,
        itemIds: []
      },
      itemOptions: [],
      // Work Order Modal
      showWOModal: false,
      isGeneratingWO: false,
      woForm: {
        period: '',
        plannedStartDate: '',
        leadTimeDays: 7
      },
      // Purchase Requisition Modal
      showPRModal: false,
      isGeneratingPR: false,
      prForm: {
        period: '',
        leadTimeDays: 7
      },
      selectedPlan: null,
      showDeleteModal: false,
      deleteItemId: null
    };
  },
  mounted() {
    this.fetchPlans();
    this.fetchItemOptions();
    // Initially expand all groups
    this.expandedGroups = ['FG', 'RM'];
  },
  methods: {
    getCurrentMonth() {
      const date = new Date();
      const year = date.getFullYear();
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      return `${year}-${month}`;
    },
    async fetchPlans(page = 1) {
      this.isLoading = true;
      try {
        const periodRange = this.generateSixMonthPeriodRange(this.filters.startPeriod);
        let allPlans = [];
        
        // Build params with filters
        const params = {
          search: this.filters.search,
          materialType: this.filters.materialType,
          status: this.filters.status,
          per_page: 2000
        };
        
        // Remove empty filters to avoid sending empty strings
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key];
          }
        });
        
        const response = await axios.get('/material-planning', { params });
        
        if (response.data.data) {
          // Filter data to only include our 6-month range
          allPlans = response.data.data.filter(plan => {
            const planPeriod = plan.planning_period.substring(0, 7);
            return periodRange.includes(planPeriod);
          });
        }
        
        // Store all filtered plans
        this.allFilteredPlans = allPlans;
        
        // Reset to page 1 when fetchPlans is called (usually when filters change)
        this.implementFrontendPagination(page);
        
      } catch (error) {
        console.error('Error fetching plans:', error);
        alert('Failed to fetch material plans');
      } finally {
        this.isLoading = false;
      }
    },

    // Add method to handle filter changes
    handleFilterChange() {
      // Reset to page 1 when filters change
      this.currentPage = 1;
      this.fetchPlans(1);
    },

    implementFrontendPagination(page = 1) {
      const itemsPerPage = 20;
      
      // First, process all data to get all items
      this.processAllDataForItems();
      
      // Get all items from processed data
      let allItems = [];
      this.allGroupedPlans.forEach(group => {
        allItems = [...allItems, ...group.items];
      });
      
      // Calculate pagination
      this.total = allItems.length;
      this.totalPages = Math.ceil(allItems.length / itemsPerPage) || 1;
      this.currentPage = Math.min(Math.max(page, 1), this.totalPages);
      
      // Calculate start and end indices
      const startIndex = (this.currentPage - 1) * itemsPerPage;
      const endIndex = startIndex + itemsPerPage;
      
      this.from = this.total > 0 ? startIndex + 1 : 0;
      this.to = Math.min(endIndex, this.total);
      
      // Get items for current page
      const paginatedItems = allItems.slice(startIndex, endIndex);
      
      // Group paginated items back by material type
      const paginatedGroups = new Map();
      paginatedItems.forEach(item => {
        if (!paginatedGroups.has(item.material_type)) {
          paginatedGroups.set(item.material_type, {
            material_type: item.material_type,
            items: []
          });
        }
        paginatedGroups.get(item.material_type).items.push(item);
      });
      
      // Convert to array and sort
      this.groupedPlans = Array.from(paginatedGroups.values()).sort(a => {
        return a.material_type === 'FG' ? -1 : 1;
      });
    },

    // New method to process all data and store complete grouped items
    processAllDataForItems() {
      const plans = this.allFilteredPlans || [];
      
      this.periodRange = this.generateSixMonthPeriodRange(this.filters.startPeriod);
      
      const itemsMap = new Map();
      
      plans.forEach(plan => {
        if (!plan.item) return;
        
        const itemKey = plan.item.item_id;
        if (!itemsMap.has(itemKey)) {
          itemsMap.set(itemKey, {
            item_id: plan.item.item_id,
            item_code: plan.item.item_code,
            name: plan.item.name,
            material_type: plan.material_type,
            status: plan.status,
            periods: {}
          });
        }
        
        const period = plan.planning_period.substring(0, 7);
        itemsMap.get(itemKey).periods[period] = {
          plan_id: plan.plan_id,
          forecast_quantity: plan.forecast_quantity || 0,
          available_stock: plan.available_stock || 0,
          wip_stock: plan.wip_stock || 0,
          net_requirement: plan.net_requirement || 0,
          planned_order_quantity: plan.planned_order_quantity || 0,
          status: plan.status
        };
      });
      
      // Group all items by material type (store complete data)
      const materialGroups = new Map();
      for (const item of itemsMap.values()) {
        if (!materialGroups.has(item.material_type)) {
          materialGroups.set(item.material_type, {
            material_type: item.material_type,
            items: []
          });
        }
        materialGroups.get(item.material_type).items.push(item);
      }
      
      this.allGroupedPlans = Array.from(materialGroups.values()).sort(a => {
        return a.material_type === 'FG' ? -1 : 1;
      });
      
      // Sort items within each group by item code
      this.allGroupedPlans.forEach(group => {
        group.items.sort((a, b) => a.item_code.localeCompare(b.item_code));
      });
    },

    processDataForHorizontalView(plansData = null) {
      const plans = plansData || this.allFilteredPlans || [];
      
      this.periodRange = this.generateSixMonthPeriodRange(this.filters.startPeriod);
      
      const itemsMap = new Map();
      
      plans.forEach(plan => {
        if (!plan.item) return;
        
        const itemKey = plan.item.item_id;
        if (!itemsMap.has(itemKey)) {
          itemsMap.set(itemKey, {
            item_id: plan.item.item_id,
            item_code: plan.item.item_code,
            name: plan.item.name,
            material_type: plan.material_type,
            status: plan.status,
            periods: {}
          });
        }
        
        const period = plan.planning_period.substring(0, 7);
        itemsMap.get(itemKey).periods[period] = {
          plan_id: plan.plan_id,
          forecast_quantity: plan.forecast_quantity || 0,
          available_stock: plan.available_stock || 0,
          wip_stock: plan.wip_stock || 0,
          net_requirement: plan.net_requirement || 0,
          planned_order_quantity: plan.planned_order_quantity || 0,
          status: plan.status
        };
      });
      
      // If this is called from implementFrontendPagination, return the items
      if (plansData) {
        const materialGroups = new Map();
        for (const item of itemsMap.values()) {
          if (!materialGroups.has(item.material_type)) {
            materialGroups.set(item.material_type, {
              material_type: item.material_type,
              items: []
            });
          }
          materialGroups.get(item.material_type).items.push(item);
        }
        
        this.groupedPlans = Array.from(materialGroups.values()).sort(a => {
          return a.material_type === 'FG' ? -1 : 1;
        });
        
        this.groupedPlans.forEach(group => {
          group.items.sort((a, b) => a.item_code.localeCompare(b.item_code));
        });
      }
    },

    handlePageChange(page) {
      this.implementFrontendPagination(page);
    },
    
    generateSixMonthPeriodRange(startPeriod) {
      const periods = [];
      if (!startPeriod) return periods;
      
      const [year, month] = startPeriod.split('-').map(num => parseInt(num, 10));
      const startDate = new Date(year, month - 1); // Month is 0-indexed in Date
      
      for (let i = 0; i < 6; i++) {
        const currentDate = new Date(startDate);
        currentDate.setMonth(startDate.getMonth() + i);
        const currentYear = currentDate.getFullYear();
        const currentMonth = (currentDate.getMonth() + 1).toString().padStart(2, '0');
        periods.push(`${currentYear}-${currentMonth}`);
      }
      
      return periods;
    },
    
    getPeriodData(item, period) {
      return item.periods[period] || {
        forecast_quantity: 0,
        available_stock: 0,
        net_requirement: 0,
        planned_order_quantity: 0,
        status: item.status,
        is_empty: true
      };
    },
    
    hasPositiveNetRequirement(item) {
      for (const periodData of Object.values(item.periods)) {
        if (periodData.net_requirement > 0) {
          return true;
        }
      }
      return false;
    },
    
    formatPeriodHeader(period) {
      const [year, month] = period.split('-');
      const date = new Date(year, parseInt(month) - 1, 1);
      return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    },
    
    async fetchItemOptions() {
      try {
        const response = await axios.get('/items');
        this.itemOptions = response.data.data.map(item => ({
          value: item.item_id,
          label: `${item.item_code} - ${item.name}`
        }));
      } catch (error) {
        console.error('Error fetching items:', error);
      }
    },
    
    async generatePlans() {
      if (!this.generateForm.startPeriod || !this.generateForm.bufferPercentage) {
        alert('Please fill in all required fields');
        return;
      }
      
      this.isGenerating = true;
      try {
        const payload = {
          start_period: this.generateForm.startPeriod + '-01',
          buffer_percentage: this.generateForm.bufferPercentage,
          item_ids: this.generateForm.itemIds.map(item => item.value)
        };
        
        const response = await axios.post('/material-planning/generate', payload);
        this.closeGenerateModal();
        this.fetchPlans();
        alert(response.data.message);
      } catch (error) {
        console.error('Error generating material plans:', error);
        alert('Failed to generate material plans');
      } finally {
        this.isGenerating = false;
      }
    },
    
    async submitWO() {
      if (!this.woForm.plannedStartDate || !this.woForm.leadTimeDays) {
        alert('Please fill in all required fields');
        return;
      }
      
      this.isGeneratingWO = true;
      try {
        const payload = {
          period: this.woForm.period + '-01',
          planned_start_date: this.woForm.plannedStartDate,
          lead_time_days: this.woForm.leadTimeDays
        };
        
        const response = await axios.post('/material-planning/work-orders', payload);
        
        this.closeWOModal();
        this.fetchPlans();
        
        // Show success message with work order details
        if (response.data.data && response.data.data.length > 0) {
          const woNumbers = response.data.data.map(wo => wo.wo_number).join(', ');
          alert(`${response.data.message}\nWork Order Numbers: ${woNumbers}`);
          
          // Optionally redirect to work order list
          // this.$router.push('/manufacturing/work-orders');
        } else {
          alert(response.data.message);
        }
      } catch (error) {
        console.error('Error generating work orders:', error);
        const message = error.response?.data?.message || 'Failed to generate work orders';
        alert(message);
      } finally {
        this.isGeneratingWO = false;
      }
    },
    
    async submitPR() {
      if (!this.prForm.leadTimeDays) {
        alert('Please fill in all required fields');
        return;
      }
      
      this.isGeneratingPR = true;
      try {
        const payload = {
          period: this.prForm.period + '-01',
          lead_time_days: this.prForm.leadTimeDays
        };
        
        const response = await axios.post('/material-planning/purchase-requisition', payload);
        
        if (response.data.data) {
          // Redirect to PR detail page
          this.$router.push(`/purchasing/requisitions/${response.data.data.pr_id}`);
        }
        
        this.closePRModal();
        this.fetchPlans();
        alert(response.data.message);
      } catch (error) {
        console.error('Error generating purchase requisition:', error);
        const message = error.response?.data?.message || 'Failed to generate purchase requisition';
        alert(message);
      } finally {
        this.isGeneratingPR = false;
      }
    },
    
    viewDetails(item) {
      // Find the first plan_id for this item
      for (const periodData of Object.values(item.periods)) {
        if (periodData.plan_id) {
          this.$router.push(`/materials/plans/${periodData.plan_id}`);
          return;
        }
      }
    },
    
    editPlan(item) {
      // Find the first plan_id for this item
      for (const periodData of Object.values(item.periods)) {
        if (periodData.plan_id) {
          this.$router.push(`/materials/plans/${periodData.plan_id}/edit`);
          return;
        }
      }
    },
    
    deletePlan(item) {
      // Find the first plan_id for this item
      for (const periodData of Object.values(item.periods)) {
        if (periodData.plan_id) {
          this.deleteItemId = periodData.plan_id;
          this.showDeleteModal = true;
          return;
        }
      }
    },
    
    async confirmDelete() {
      try {
        await axios.delete(`/material-planning/${this.deleteItemId}`);
        this.fetchPlans();
        alert('Material plan deleted successfully');
      } catch (error) {
        console.error('Error deleting material plan:', error);
        alert('Failed to delete material plan');
      } finally {
        this.closeDeleteModal();
      }
    },
    
    generateWorkOrder(item) {
      this.selectedPlan = item;
      // Get the first period with positive net requirement
      for (const [period, periodData] of Object.entries(item.periods)) {
        if (periodData.net_requirement > 0) {
          this.woForm.period = period;
          // Set default planned start date to beginning of planning period
          const [year, month] = period.split('-');
          this.woForm.plannedStartDate = `${year}-${month}-01`;
          this.showWOModal = true;
          return;
        }
      }
    },
    
    generatePurchaseRequisition(item) {
      this.selectedPlan = item;
      // Get the first period with positive net requirement
      for (const [period, periodData] of Object.entries(item.periods)) {
        if (periodData.net_requirement > 0) {
          this.prForm.period = period;
          this.showPRModal = true;
          return;
        }
      }
    },
    
    exportPlans() {
      // Implementation for export functionality
      console.log('Exporting plans...');
    },
    
    formatNumber(value) {
      return Number(value).toLocaleString();
    },
    
    toggleGroupExpand(materialType) {
      const index = this.expandedGroups.indexOf(materialType);
      if (index === -1) {
        this.expandedGroups.push(materialType);
      } else {
        this.expandedGroups.splice(index, 1);
      }
    },
    
    closeGenerateModal() {
      this.showGenerateModal = false;
      this.generateForm = {
        startPeriod: '',
        bufferPercentage: 10,
        itemIds: []
      };
    },
    
    closeWOModal() {
      this.showWOModal = false;
      this.woForm = {
        period: '',
        plannedStartDate: '',
        leadTimeDays: 7
      };
      this.selectedPlan = null;
    },
    
    closePRModal() {
      this.showPRModal = false;
      this.prForm = {
        period: '',
        leadTimeDays: 7
      };
      this.selectedPlan = null;
    },
    
    closeDeleteModal() {
      this.showDeleteModal = false;
      this.deleteItemId = null;
    },
    
  }
};
</script>

<style scoped>
.material-planning-container {
  max-width: 1600px;
  margin: 0 auto;
  padding: 2rem;
}

.page-header {
  margin-bottom: 2.5rem;
  border-bottom: 1px solid #eaeaea;
  padding-bottom: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-title {
  font-size: 1.8rem;
  margin-bottom: 0;
  color: var(--gray-800);
}

.filter-card {
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  border: none;
  margin-bottom: 2rem;
}

.filter-controls {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 1.5rem;
  border: 1px solid #eef0f2;
}

.row.g-3 {
  display: flex;
  flex-wrap: wrap;
  margin-right: -10px;
  margin-left: -10px;
}

.col-md-3 {
  padding-left: 10px;
  padding-right: 10px;
  width: 25%;
  flex: 0 0 25%;
  max-width: 25%;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
  font-size: 0.9rem;
}

.form-control {
  border-radius: 5px;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ced4da;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  width: 100%;
  height: calc(1.5em + 0.75rem + 2px);
  font-size: 0.9rem;
}

.search-input {
  display: flex;
}

.search-input .form-control {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.search-input .btn {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}

.filter-actions {
  display: flex;
  justify-content: flex-end;
}

.btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
}

.btn-outline {
  border: 1px solid var(--gray-200);
  background: white;
  color: var(--gray-700);
}

.btn-outline:hover {
  background: var(--gray-50);
}

.btn-outline-primary {
  border: 1px solid var(--primary-color);
  background: white;
  color: var(--primary-color);
}

.btn-outline-primary:hover {
  background: var(--primary-bg);
}

.btn-secondary {
  background: var(--gray-100);
  color: var(--gray-800);
}

.btn-secondary:hover {
  background: var(--gray-200);
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.btn-outline-secondary {
  border-color: #e2e6ea;
  color: #6c757d;
  padding: 0.375rem 0.75rem;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.btn-outline-secondary:hover {
  background-color: #f8f9fa;
  border-color: #cbd3da;
}

.btn-outline-secondary i {
  margin-right: 0.25rem;
}

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: none;
}

.card-body {
  padding: 1.75rem;
}

.table-responsive {
  margin: 1rem 0 2rem 0;
  border-radius: 6px;
  overflow: hidden;
}

.table {
  margin-bottom: 0;
  width: 100%;
  border-collapse: collapse;
}

.table thead th {
  background-color: #f8f9fa;
  color: #495057;
  font-weight: 600;
  padding: 0.85rem;
  border-bottom-width: 1px;
  text-align: center;
}

.table tbody td {
  padding: 1rem 0.85rem;
  vertical-align: middle;
  border: 1px solid #e9ecef;
}

.table tbody tr:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.planning-cell {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.planning-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
}

.planning-label {
  color: #6c757d;
  font-weight: 500;
}

.planning-value {
  font-weight: 600;
}

.font-weight-bold {
  font-weight: 700 !important;
}

.no-data {
  color: #adb5bd;
  font-style: italic;
}

.empty-period {
  background-color: #f8f9fa;
}

.bg-light-success {
  background-color: rgba(40, 167, 69, 0.08);
}

.bg-light-danger {
  background-color: rgba(220, 53, 69, 0.08);
}

.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.action-column {
  width: 180px;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: none;
  background: var(--gray-100);
  color: var(--gray-600);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon:hover {
  background: var(--gray-200);
}

.btn-icon-edit:hover {
  background: var(--primary-bg);
  color: var(--primary-color);
}

.btn-icon-danger:hover {
  background: #fee2e2;
  color: #dc2626;
}

.btn-icon-success:hover {
  background: #dcfce7;
  color: #16a34a;
}

.btn-icon-manufacturing:hover {
  background: #e0e7ff;
  color: #6366f1;
}

.status-chip {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  display: inline-block;
  margin-top: 4px;
}

.bg-gray {
  background: var(--gray-100);
  color: var(--gray-800);
}

.bg-blue {
  background: #dbeafe;
  color: #1e40af;
}

.bg-purple {
  background: #e0e7ff;
  color: #6366f1;
}

.bg-green {
  background: #dcfce7;
  color: #15803d;
}

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
  background: rgba(0, 0, 0, 0.5);
  z-index: 50;
}

.modal-content {
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  width: 100%;
  z-index: 60;
  overflow: hidden;
}

.modal-md {
  max-width: 600px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid var(--gray-200);
}

.modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: var(--gray-800);
}

.close-btn {
  background: none;
  border: none;
  color: var(--gray-600);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 4px;
}

.close-btn:hover {
  background: var(--gray-100);
  color: var(--gray-800);
}

.modal-body {
  padding: 24px;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-weight: 500;
  margin-bottom: 8px;
  color: var(--gray-700);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--gray-200);
}

.required {
  color: #dc2626;
}

.badge {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-weight: 500;
}

.bg-primary {
  background-color: #0d6efd;
  color: white;
}

.mb-4 {
  margin-bottom: 1.5rem !important;
}

.mb-5 {
  margin-bottom: 3rem !important;
}

.mt-2 {
  margin-top: 0.5rem !important;
}

.mt-3 {
  margin-top: 1rem !important;
}

.py-5 {
  padding-top: 3rem !important;
  padding-bottom: 3rem !important;
}

.text-center {
  text-align: center !important;
}

.text-muted {
  color: #6c757d !important;
}

.d-flex {
  display: flex !important;
}

.justify-content-between {
  justify-content: space-between !important;
}

.align-items-center {
  align-items: center !important;
}

.fa-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 991px) {
  .col-md-3 {
    width: 50%;
    flex: 0 0 50%;
    max-width: 50%;
    margin-bottom: 1rem;
  }
  
  .filter-controls {
    padding: 1.25rem;
  }
  
  .card-body {
    padding: 1.25rem;
  }
}

@media (max-width: 768px) {
  .material-planning-container {
    padding: 1rem;
  }
  
  .col-md-3 {
    width: 100%;
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .header-actions {
    width: 100%;
  }
  
  .table thead th,
  .table tbody td {
    padding: 0.6rem;
  }
}
</style>