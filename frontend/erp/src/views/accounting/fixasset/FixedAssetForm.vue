<template>
  <AppLayout>
    <div class="asset-form-page">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <div class="breadcrumb">
              <router-link to="/accounting/fixed-assets" class="breadcrumb-link">
                <i class="fas fa-building"></i>
                Fixed Assets
              </router-link>
              <i class="fas fa-chevron-right breadcrumb-separator"></i>
              <span class="breadcrumb-current">{{ isEditing ? 'Edit Asset' : 'Create Asset' }}</span>
            </div>
            <h1 class="page-title">
              <i :class="isEditing ? 'fas fa-edit' : 'fas fa-plus'"></i>
              {{ isEditing ? 'Edit Fixed Asset' : 'Create New Fixed Asset' }}
            </h1>
            <p class="page-subtitle">
              {{ isEditing ? 'Update asset information and track changes' : 'Add a new fixed asset to your inventory' }}
            </p>
          </div>
          <div class="header-actions">
            <button @click="goBack" class="btn btn-outline">
              <i class="fas fa-arrow-left"></i>
              Back to List
            </button>
          </div>
        </div>
      </div>

      <!-- Form Container -->
      <div class="form-container">
        <form @submit.prevent="submitForm" class="asset-form">
          <!-- Basic Information Section -->
          <div class="form-section">
            <div class="section-header">
              <h2>
                <i class="fas fa-info-circle"></i>
                Basic Information
              </h2>
              <p>Enter the basic details of the fixed asset</p>
            </div>
            
            <div class="form-grid">
              <div class="form-group">
                <label for="asset_code" class="form-label required">Asset Code</label>
                <input
                  id="asset_code"
                  v-model="form.asset_code"
                  type="text"
                  class="form-input"
                  :class="{ 'error': errors.asset_code }"
                  placeholder="Enter unique asset code"
                  required
                >
                <span v-if="errors.asset_code" class="error-message">{{ errors.asset_code[0] }}</span>
              </div>
              
              <div class="form-group">
                <label for="name" class="form-label required">Asset Name</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="form-input"
                  :class="{ 'error': errors.name }"
                  placeholder="Enter asset name"
                  required
                >
                <span v-if="errors.name" class="error-message">{{ errors.name[0] }}</span>
              </div>
              
              <div class="form-group">
                <label for="category" class="form-label required">Category</label>
                <select
                  id="category"
                  v-model="form.category"
                  class="form-select"
                  :class="{ 'error': errors.category }"
                  required
                >
                  <option value="">Select category</option>
                  <option value="Building">Building</option>
                  <option value="Equipment">Equipment</option>
                  <option value="Vehicle">Vehicle</option>
                  <option value="Furniture">Furniture</option>
                  <option value="Computer">Computer</option>
                  <option value="Machinery">Machinery</option>
                  <option value="Other">Other</option>
                </select>
                <span v-if="errors.category" class="error-message">{{ errors.category[0] }}</span>
              </div>
              
              <div class="form-group">
                <label for="status" class="form-label required">Status</label>
                <select
                  id="status"
                  v-model="form.status"
                  class="form-select"
                  :class="{ 'error': errors.status }"
                  required
                >
                  <option value="">Select status</option>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                  <option value="Under Maintenance">Under Maintenance</option>
                  <option value="Disposed">Disposed</option>
                </select>
                <span v-if="errors.status" class="error-message">{{ errors.status[0] }}</span>
              </div>
            </div>
          </div>

          <!-- Financial Information Section -->
          <div class="form-section">
            <div class="section-header">
              <h2>
                <i class="fas fa-dollar-sign"></i>
                Financial Information
              </h2>
              <p>Enter the financial details and depreciation information</p>
            </div>
            
            <div class="form-grid">
              <div class="form-group">
                <label for="acquisition_date" class="form-label required">Acquisition Date</label>
                <input
                  id="acquisition_date"
                  v-model="form.acquisition_date"
                  type="date"
                  class="form-input"
                  :class="{ 'error': errors.acquisition_date }"
                  :disabled="isEditing && hasDepreciations"
                  required
                >
                <span v-if="errors.acquisition_date" class="error-message">{{ errors.acquisition_date[0] }}</span>
                <span v-if="isEditing && hasDepreciations" class="info-message">
                  Cannot modify acquisition date after depreciation has been recorded
                </span>
              </div>
              
              <div class="form-group">
                <label for="acquisition_cost" class="form-label required">Acquisition Cost</label>
                <div class="input-group">
                  <span class="input-prefix">$</span>
                  <input
                    id="acquisition_cost"
                    v-model.number="form.acquisition_cost"
                    type="number"
                    step="0.01"
                    min="0"
                    class="form-input"
                    :class="{ 'error': errors.acquisition_cost }"
                    :disabled="isEditing && hasDepreciations"
                    placeholder="0.00"
                    required
                  >
                </div>
                <span v-if="errors.acquisition_cost" class="error-message">{{ errors.acquisition_cost[0] }}</span>
                <span v-if="isEditing && hasDepreciations" class="info-message">
                  Cannot modify acquisition cost after depreciation has been recorded
                </span>
              </div>
              
              <div class="form-group" v-if="isEditing">
                <label for="current_value" class="form-label">Current Book Value</label>
                <div class="input-group">
                  <span class="input-prefix">$</span>
                  <input
                    id="current_value"
                    v-model.number="form.current_value"
                    type="number"
                    step="0.01"
                    min="0"
                    class="form-input"
                    :class="{ 'error': errors.current_value }"
                    placeholder="0.00"
                  >
                </div>
                <span v-if="errors.current_value" class="error-message">{{ errors.current_value[0] }}</span>
                <span class="info-message">Current book value after depreciation</span>
              </div>
              
              <div class="form-group">
                <label for="depreciation_rate" class="form-label required">Depreciation Rate (%)</label>
                <div class="input-group">
                  <input
                    id="depreciation_rate"
                    v-model.number="form.depreciation_rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="form-input"
                    :class="{ 'error': errors.depreciation_rate }"
                    :disabled="isEditing && hasDepreciations"
                    placeholder="0.00"
                    required
                  >
                  <span class="input-suffix">%</span>
                </div>
                <span v-if="errors.depreciation_rate" class="error-message">{{ errors.depreciation_rate[0] }}</span>
                <span v-if="isEditing && hasDepreciations" class="info-message">
                  Cannot modify depreciation rate after depreciation has been recorded
                </span>
              </div>
            </div>
          </div>

          <!-- Depreciation Preview (for new assets) -->
          <div v-if="!isEditing && form.acquisition_cost && form.depreciation_rate" class="form-section">
            <div class="section-header">
              <h2>
                <i class="fas fa-chart-line"></i>
                Depreciation Preview
              </h2>
              <p>Preview of depreciation calculation based on entered values</p>
            </div>
            
            <div class="depreciation-preview">
              <div class="preview-cards">
                <div class="preview-card">
                  <div class="preview-icon">
                    <i class="fas fa-calendar-alt"></i>
                  </div>
                  <div class="preview-content">
                    <h4>Annual Depreciation</h4>
                    <p class="preview-value">${{ formatNumber(annualDepreciation) }}</p>
                  </div>
                </div>
                
                <div class="preview-card">
                  <div class="preview-icon">
                    <i class="fas fa-calendar-day"></i>
                  </div>
                  <div class="preview-content">
                    <h4>Monthly Depreciation</h4>
                    <p class="preview-value">${{ formatNumber(monthlyDepreciation) }}</p>
                  </div>
                </div>
                
                <div class="preview-card">
                  <div class="preview-icon">
                    <i class="fas fa-hourglass-half"></i>
                  </div>
                  <div class="preview-content">
                    <h4>Years to Full Depreciation</h4>
                    <p class="preview-value">{{ yearsToFullDepreciation }} years</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="form-actions">
            <button type="button" @click="goBack" class="btn btn-outline">
              <i class="fas fa-times"></i>
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="loading">
              <i v-if="loading" class="fas fa-spinner fa-spin"></i>
              <i v-else :class="isEditing ? 'fas fa-save' : 'fas fa-plus'"></i>
              {{ loading ? 'Saving...' : (isEditing ? 'Update Asset' : 'Create Asset') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'FixedAssetForm',
  components: {
  },
  setup() {
    const router = useRouter()
    const route = useRoute()
    
    // Reactive data
    const loading = ref(false)
    const errors = ref({})
    const hasDepreciations = ref(false)
    
    const form = reactive({
      asset_code: '',
      name: '',
      category: '',
      acquisition_date: '',
      acquisition_cost: 0,
      current_value: 0,
      depreciation_rate: 0,
      status: ''
    })
    
    // Computed
    const isEditing = computed(() => !!route.params.id)
    
    const annualDepreciation = computed(() => {
      if (!form.acquisition_cost || !form.depreciation_rate) return 0
      return (form.acquisition_cost * form.depreciation_rate) / 100
    })
    
    const monthlyDepreciation = computed(() => {
      return annualDepreciation.value / 12
    })
    
    const yearsToFullDepreciation = computed(() => {
      if (!form.depreciation_rate) return 0
      return Math.ceil(100 / form.depreciation_rate)
    })
    
    // Methods
    const fetchAsset = async () => {
      if (!isEditing.value) return
      
      try {
        loading.value = true
        const response = await axios.get(`/accounting/fixed-assets/${route.params.id}`)
        const asset = response.data.data
        
        // Populate form
        Object.keys(form).forEach(key => {
          if (asset[key] !== undefined) {
            form[key] = asset[key]
          }
        })
        
        // Check if asset has depreciation records
        hasDepreciations.value = asset.asset_depreciations && asset.asset_depreciations.length > 0
        
      } catch (error) {
        console.error('Error fetching asset:', error)
        if (error.response?.status === 404) {
          router.push('/accounting/fixed-assets')
        }
      } finally {
        loading.value = false
      }
    }
    
    const validateForm = () => {
      errors.value = {}
      
      if (!form.asset_code) {
        errors.value.asset_code = ['Asset code is required']
      }
      
      if (!form.name) {
        errors.value.name = ['Asset name is required']
      }
      
      if (!form.category) {
        errors.value.category = ['Category is required']
      }
      
      if (!form.acquisition_date) {
        errors.value.acquisition_date = ['Acquisition date is required']
      }
      
      if (!form.acquisition_cost || form.acquisition_cost <= 0) {
        errors.value.acquisition_cost = ['Acquisition cost must be greater than 0']
      }
      
      if (!form.depreciation_rate || form.depreciation_rate < 0 || form.depreciation_rate > 100) {
        errors.value.depreciation_rate = ['Depreciation rate must be between 0 and 100']
      }
      
      if (!form.status) {
        errors.value.status = ['Status is required']
      }
      
      return Object.keys(errors.value).length === 0
    }
    
    const submitForm = async () => {
      if (!validateForm()) {
        return
      }
      
      try {
        loading.value = true
        errors.value = {}
        
        const payload = { ...form }
        
        // Set current_value to acquisition_cost for new assets
        if (!isEditing.value) {
          payload.current_value = payload.acquisition_cost
        }
        
        if (isEditing.value) {
          await axios.put(`/accounting/fixed-assets/${route.params.id}`, payload)
        } else {
          await axios.post('/accounting/fixed-assets', payload)
        }
        
        // Success - redirect to list
        router.push('/accounting/fixed-assets')
        
      } catch (error) {
        console.error('Error saving asset:', error)
        
        if (error.response?.status === 422) {
          errors.value = error.response.data.errors || {}
        }
      } finally {
        loading.value = false
      }
    }
    
    const goBack = () => {
      router.push('/accounting/fixed-assets')
    }
    
    const formatNumber = (value) => {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(value || 0)
    }
    
    // Watchers
    watch(() => form.acquisition_cost, (newVal) => {
      if (!isEditing.value) {
        form.current_value = newVal
      }
    })
    
    // Lifecycle
    onMounted(() => {
      if (isEditing.value) {
        fetchAsset()
      } else {
        // Set default date to today
        form.acquisition_date = new Date().toISOString().split('T')[0]
      }
    })
    
    return {
      form,
      loading,
      errors,
      isEditing,
      hasDepreciations,
      annualDepreciation,
      monthlyDepreciation,
      yearsToFullDepreciation,
      submitForm,
      goBack,
      formatNumber
    }
  }
}
</script>

<style scoped>
.asset-form-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

/* Page Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.breadcrumb-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6366f1;
  text-decoration: none;
  transition: all 0.3s ease;
}

.breadcrumb-link:hover {
  color: #4f46e5;
}

.breadcrumb-separator {
  color: #94a3b8;
  font-size: 0.75rem;
}

.breadcrumb-current {
  color: #64748b;
  font-weight: 500;
}

.title-section h1 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 2.25rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.title-section h1 i {
  color: #6366f1;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

.header-actions {
  flex-shrink: 0;
}

/* Buttons */
.btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 10px;
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn-outline {
  background: white;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.btn-outline:hover {
  border-color: #6366f1;
  color: #6366f1;
}

/* Form Container */
.form-container {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.asset-form {
  display: flex;
  flex-direction: column;
}

/* Form Sections */
.form-section {
  padding: 2rem;
  border-bottom: 1px solid #e2e8f0;
}

.form-section:last-child {
  border-bottom: none;
}

.section-header {
  margin-bottom: 2rem;
}

.section-header h2 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.section-header h2 i {
  color: #6366f1;
}

.section-header p {
  color: #64748b;
  margin: 0;
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

/* Form Groups */
.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.form-label.required::after {
  content: '*';
  color: #ef4444;
  margin-left: 0.25rem;
}

/* Form Inputs */
.form-input,
.form-select {
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 1rem;
  transition: all 0.3s ease;
  background: white;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input.error,
.form-select.error {
  border-color: #ef4444;
}

.form-input:disabled,
.form-select:disabled {
  background: #f8fafc;
  color: #94a3b8;
  cursor: not-allowed;
}

/* Input Groups */
.input-group {
  position: relative;
  display: flex;
  align-items: center;
}

.input-prefix {
  position: absolute;
  left: 1rem;
  color: #64748b;
  font-weight: 500;
  z-index: 1;
}

.input-suffix {
  position: absolute;
  right: 1rem;
  color: #64748b;
  font-weight: 500;
  z-index: 1;
}

.input-group .form-input {
  padding-left: 2rem;
}

.input-group .form-input:has(+ .input-suffix) {
  padding-right: 2.5rem;
}

/* Messages */
.error-message {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.info-message {
  color: #6366f1;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

/* Depreciation Preview */
.depreciation-preview {
  background: #f8fafc;
  border-radius: 12px;
  padding: 1.5rem;
}

.preview-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.preview-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.preview-icon {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.preview-content h4 {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0 0 0.25rem 0;
  font-weight: 500;
}

.preview-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 2rem;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

/* Responsive Design */
@media (max-width: 768px) {
  .asset-form-page {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-actions {
    order: -1;
    display: flex;
    justify-content: flex-end;
  }
  
  .form-section {
    padding: 1.5rem;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .preview-cards {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column-reverse;
    padding: 1.5rem;
  }
  
  .title-section h1 {
    font-size: 1.875rem;
  }
}

@media (max-width: 480px) {
  .breadcrumb {
    flex-wrap: wrap;
  }
  
  .form-actions {
    gap: 0.75rem;
  }
  
  .btn {
    justify-content: center;
  }
}
</style>