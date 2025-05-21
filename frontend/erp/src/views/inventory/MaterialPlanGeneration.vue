<!-- src/views/materials/MaterialPlanGeneration.vue -->
<template>
  <div class="material-plan-generation">
    <!-- Header -->
    <div class="page-header">
      <h1 class="page-title">Generate Material Plans</h1>
      <p class="page-description">Generate material plans based on sales forecasts and BOM requirements</p>
    </div>

    <!-- Main Form Card -->
    <div class="card form-card">
      <form @submit.prevent="generatePlans">
        <!-- Generation Parameters Section -->
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-sliders-h"></i>
            Generation Parameters
          </h2>
          
          <div class="form-row">
            <div class="form-group">
              <label>Start Planning Period <span class="required">*</span></label>
              <input 
                type="month" 
                v-model="formData.startPeriod" 
                class="form-control"
                @change="updatePlanningPeriodDisplay"
                required
              />
              <small class="form-note">Materials will be planned from this month</small>
            </div>

            <div class="form-group">
              <label>Buffer Percentage <span class="required">*</span></label>
              <div class="input-group">
                <input 
                  type="number" 
                  v-model="formData.bufferPercentage" 
                  class="form-control"
                  min="0" 
                  max="100" 
                  step="1"
                  required
                />
                <span class="input-addon">%</span>
              </div>
              <small class="form-note">Safety stock buffer percentage</small>
            </div>
          </div>

          <div class="form-row planning-range-preview" v-if="planningPeriods">
            <div class="preview-box">
              <h3>Planning Periods</h3>
              <div class="period-chips">
                <span v-for="period in planningPeriods" :key="period" class="period-chip">
                  {{ formatPeriod(period) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Item Selection Section -->
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-layer-group"></i>
            Item Selection
          </h2>
          
          <div class="form-group selection-type">
            <div class="radio-group">
              <label class="radio-option">
                <input type="radio" value="all" v-model="formData.selectionType" />
                <span class="radio-label">Generate for all items</span>
              </label>
              <label class="radio-option">
                <input type="radio" value="specific" v-model="formData.selectionType" />
                <span class="radio-label">Select specific items</span>
              </label>
            </div>
          </div>

          <div v-if="formData.selectionType === 'specific'" class="form-group">
            <label>Select Items</label>
              <multiselect
                v-model="formData.selectedItems"
                :options="itemOptions"
                :multiple="true"
                :searchable="true"
                :loading="isLoadingItems"
                track-by="value"
                label="label"
                placeholder="Search and select items..."
                :custom-label="customLabel"
              >
                <template #selection="{ values }">
                  <span v-if="values.length" class="multiselect__tags-wrap">
                    <template v-for="value in values" :key="value.value">
                      <span class="multiselect__tag">
                        <span>{{ value.label }}</span>
                        <i aria-hidden="true" tabindex="1" @keydown.enter.prevent="removeElement(value)"
                           @mousedown.prevent="removeElement(value)" class="multiselect__tag-icon"></i>
                      </span>
                    </template>
                  </span>
                </template>
              </multiselect>
            <small class="form-note">
              Selected: {{ formData.selectedItems.length }} item(s)
            </small>
          </div>
        </div>

        <!-- Advanced Options Section -->
        <div class="form-section">
          <button 
            type="button" 
            class="section-toggle" 
            @click="showAdvanced = !showAdvanced"
          >
            <i class="fas" :class="showAdvanced ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
            Advanced Options
          </button>
          
          <transition name="slide">
            <div v-if="showAdvanced" class="advanced-options">
              <div class="form-row">
                <div class="form-group">
                  <label>Include WIP Stock</label>
                  <div class="checkbox-group">
                    <label class="checkbox-option">
                      <input type="checkbox" v-model="formData.includeWip" />
                      <span class="checkbox-label">Consider Work In Progress stock</span>
                    </label>
                  </div>
                </div>

                <div class="form-group">
                  <label>Calculate by</label>
                  <select v-model="formData.calculationMethod" class="form-control">
                    <option value="forecast">Sales Forecast</option>
                    <option value="historical">Historical Demand</option>
                    <option value="both">Combined Method</option>
                  </select>
                </div>
              </div>
            </div>
          </transition>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button type="button" class="btn btn-outline" @click="cancel">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" :disabled="isGenerating">
            <i class="fas fa-cogs" :class="{ 'fa-spin': isGenerating }"></i>
            {{ isGenerating ? 'Generating...' : 'Generate Material Plans' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Progress Modal -->
    <div v-if="showProgress" class="modal">
      <div class="modal-backdrop"></div>
      <div class="modal-content modal-progress">
        <div class="modal-body">
          <div class="progress-content">
            <div class="progress-animation">
              <i class="fas fa-cogs fa-spin"></i>
            </div>
            <h3>Generating Material Plans</h3>
            <p>Please wait while we process your request...</p>
            <div class="progress-bar">
              <div class="progress-fill" :style="{ width: progress + '%' }"></div>
            </div>
            <p class="progress-text">{{ progressMessage }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Results Modal -->
    <div v-if="showResults" class="modal">
      <div class="modal-backdrop" @click="closeResults"></div>
      <div class="modal-content modal-md">
        <div class="modal-header">
          <h2>Generation Results</h2>
          <button class="close-btn" @click="closeResults">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="results-content">
            <div class="result-icon success">
              <i class="fas fa-check-circle"></i>
            </div>
            <h3>Material Plans Generated Successfully</h3>
            
            <div class="results-stats">
              <div class="stat-item">
                <span class="stat-label">Finished Goods Plans:</span>
                <span class="stat-value">{{ results.finishedGoodsCount }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Raw Material Plans:</span>
                <span class="stat-value">{{ results.rawMaterialsCount }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Total Plans Created:</span>
                <span class="stat-value text-bold">{{ results.totalCount }}</span>
              </div>
            </div>

            <div class="results-actions">
              <button class="btn btn-outline" @click="viewPlans">
                View Generated Plans
              </button>
              <button class="btn btn-primary" @click="generateNew">
                Generate New Plans
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import Multiselect from 'vue-multiselect';

export default {
  name: 'MaterialPlanGeneration',
  components: {
    Multiselect
  },
  data() {
    return {
      formData: {
        startPeriod: '',
        bufferPercentage: 10,
        selectionType: 'all',
        selectedItems: [],
        includeWip: true,
        calculationMethod: 'forecast'
      },
      itemOptions: [],
      isLoadingItems: false,
      isGenerating: false,
      showAdvanced: false,
      planningPeriods: null,
      showProgress: false,
      progress: 0,
      progressMessage: '',
      showResults: false,
      results: {
        finishedGoodsCount: 0,
        rawMaterialsCount: 0,
        totalCount: 0
      }
    };
  },
  mounted() {
    this.setDefaultPeriod();
    this.fetchItemOptions();
  },
  watch: {
    formData: {
      deep: true,
      handler() {
        this.validateForm();
      }
    }
  },
  methods: {
    setDefaultPeriod() {
      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      this.formData.startPeriod = `${year}-${month}`;
      this.updatePlanningPeriodDisplay();
    },
    
    updatePlanningPeriodDisplay() {
      if (this.formData.startPeriod) {
        const startDate = new Date(this.formData.startPeriod + '-01');
        this.planningPeriods = Array.from({ length: 6 }, (_, i) => {
          const date = new Date(startDate);
          date.setMonth(date.getMonth() + i);
          return date.toISOString().slice(0, 7);
        });
      } else {
        this.planningPeriods = null;
      }
    },
    
    formatPeriod(period) {
      return new Date(period + '-01').toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long' 
      });
    },
    
    async fetchItemOptions() {
      this.isLoadingItems = true;
      try {
        const response = await axios.get('/items');
        this.itemOptions = response.data.data.map(item => ({
          value: item.item_id,
          label: `${item.item_code} - ${item.name}`,
          category: item.category?.name
        }));
      } catch (error) {
        console.error('Error fetching items:', error);
      } finally {
        this.isLoadingItems = false;
      }
    },
    
    customLabel(option) {
      return `${option.label}${option.category ? ` (${option.category})` : ''}`;
    },
    
    removeElement(element) {
      this.formData.selectedItems = this.formData.selectedItems.filter(
        item => item.value !== element.value
      );
    },
    
    validateForm() {
      return this.formData.startPeriod && this.formData.bufferPercentage >= 0;
    },
    
    async generatePlans() {
      if (!this.validateForm()) {
        alert('Please fill in all required fields');
        return;
      }
      
      this.isGenerating = true;
      this.showProgress = true;
      this.progress = 0;
      this.progressMessage = 'Initializing...';
      
      try {
        const payload = {
          start_period: this.formData.startPeriod + '-01',
          buffer_percentage: this.formData.bufferPercentage,
          item_ids: this.formData.selectionType === 'specific' ? 
            this.formData.selectedItems.map(item => item.value) : []
        };
        
        // Simulate progress updates
        const progressInterval = setInterval(() => {
          if (this.progress < 90) {
            this.progress += 10;
            this.progressMessage = this.getProgressMessage(this.progress);
          }
        }, 1000);
        
        const response = await axios.post('/material-planning/generate', payload);
        
        clearInterval(progressInterval);
        this.progress = 100;
        this.progressMessage = 'Complete!';
        
        // Parse results
        const data = response.data.data;
        this.results = {
          finishedGoodsCount: data.finished_goods?.length || 0,
          rawMaterialsCount: data.raw_materials?.length || 0,
          totalCount: (data.finished_goods?.length || 0) + (data.raw_materials?.length || 0)
        };
        
        setTimeout(() => {
          this.showProgress = false;
          this.showResults = true;
        }, 1000);
        
      } catch (error) {
        console.error('Error generating material plans:', error);
        this.showProgress = false;
        alert('Failed to generate material plans');
      } finally {
        this.isGenerating = false;
      }
    },
    
    getProgressMessage(progress) {
      if (progress <= 20) return 'Processing sales forecasts...';
      if (progress <= 40) return 'Calculating finished goods requirements...';
      if (progress <= 60) return 'Exploding BOMs...';
      if (progress <= 80) return 'Calculating raw material requirements...';
      if (progress <= 90) return 'Finalizing material plans...';
      return 'Completing...';
    },
    
    cancel() {
      this.$router.push('/materials/plans');
    },
    
    closeResults() {
      this.showResults = false;
    },
    
    viewPlans() {
      this.$router.push('/materials/plans');
    },
    
    generateNew() {
      this.showResults = false;
      // Reset form for new generation
      this.formData.selectedItems = [];
      this.formData.selectionType = 'all';
    }
  }
};
</script>

<style scoped>
.material-plan-generation {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 16px;
}

.page-header {
  margin-bottom: 32px;
}

.page-title {
  font-size: 24px;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 8px;
}

.page-description {
  color: var(--gray-600);
  font-size: 16px;
}

.form-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 32px;
  margin-bottom: 24px;
}

.form-section {
  margin-bottom: 32px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-title i {
  color: var(--primary-color);
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
}

.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: block;
  font-weight: 500;
  margin-bottom: 8px;
  color: var(--gray-700);
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--gray-200);
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.2s;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.input-group {
  display: flex;
}

.input-addon {
  padding: 8px 12px;
  background: var(--gray-100);
  border: 1px solid var(--gray-200);
  border-left: none;
  border-radius: 0 6px 6px 0;
  color: var(--gray-600);
  font-weight: 500;
}

.form-note {
  color: var(--gray-500);
  font-size: 12px;
  margin-top: 4px;
}

.planning-range-preview {
  grid-column: 1 / -1;
}

.preview-box {
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 6px;
  padding: 16px;
}

.preview-box h3 {
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: 12px;
}

.period-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.period-chip {
  background: var(--primary-bg);
  color: var(--primary-color);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
}

.radio-group, .checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.radio-option, .checkbox-option {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.radio-option input, .checkbox-option input {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.section-toggle {
  background: none;
  border: none;
  color: var(--gray-700);
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  margin-bottom: 16px;
}

.section-toggle:hover {
  color: var(--primary-color);
}

.slide-enter-active, .slide-leave-active {
  transition: all 0.3s ease;
}

.slide-enter-from, .slide-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid var(--gray-200);
}

.btn {
  padding: 8px 24px;
  border-radius: 6px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 180px;
  justify-content: center;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
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

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.required {
  color: #dc2626;
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

.modal-progress {
  max-width: 400px;
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

.progress-content {
  text-align: center;
}

.progress-animation {
  font-size: 48px;
  color: var(--primary-color);
  margin-bottom: 16px;
}

.progress-content h3 {
  font-size: 18px;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 8px;
}

.progress-content p {
  color: var(--gray-600);
  margin-bottom: 24px;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: var(--gray-100);
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 16px;
}

.progress-fill {
  height: 100%;
  background: var(--primary-color);
  transition: width 0.5s ease;
}

.progress-text {
  color: var(--gray-600);
  font-size: 14px;
}

.results-content {
  text-align: center;
}

.result-icon {
  font-size: 56px;
  margin-bottom: 16px;
}

.result-icon.success {
  color: #059669;
}

.results-content h3 {
  font-size: 18px;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 24px;
}

.results-stats {
  background: var(--gray-50);
  border-radius: 6px;
  padding: 16px;
  margin-bottom: 24px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
}

.stat-label {
  color: var(--gray-600);
}

.stat-value {
  font-weight: 600;
  color: var(--gray-800);
}

.stat-value.text-bold {
  font-size: 18px;
  color: var(--primary-color);
}

.results-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
}

.fa-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>