<template>
  <div class="period-form-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <button @click="goBack" class="back-btn">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-calendar-plus"></i>
              {{ isEditing ? 'Edit' : 'Create' }} Accounting Period
            </h1>
            <p class="page-subtitle">{{ isEditing ? 'Update period information' : 'Set up a new accounting period' }}</p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="resetForm" class="btn btn-secondary" :disabled="saving">
            <i class="fas fa-undo"></i>
            Reset
          </button>
          <button @click="savePeriod" class="btn btn-primary" :disabled="saving || !isFormValid">
            <i v-if="saving" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-save"></i>
            {{ saving ? 'Saving...' : (isEditing ? 'Update Period' : 'Create Period') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Progress Steps -->
    <div class="progress-steps" v-if="!isEditing">
      <div class="step" :class="{ active: currentStep >= 1, completed: currentStep > 1 }">
        <div class="step-number">1</div>
        <div class="step-label">Basic Info</div>
      </div>
      <div class="step-line" :class="{ completed: currentStep > 1 }"></div>
      <div class="step" :class="{ active: currentStep >= 2, completed: currentStep > 2 }">
        <div class="step-number">2</div>
        <div class="step-label">Date Range</div>
      </div>
      <div class="step-line" :class="{ completed: currentStep > 2 }"></div>
      <div class="step" :class="{ active: currentStep >= 3 }">
        <div class="step-number">3</div>
        <div class="step-label">Review</div>
      </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
      <!-- Step 1: Basic Information -->
      <div v-show="currentStep === 1 || isEditing" class="form-section">
        <div class="section-header">
          <h2>
            <i class="fas fa-info-circle"></i>
            Basic Information
          </h2>
          <p>Enter the fundamental details for your accounting period</p>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="period_name" class="required">Period Name</label>
            <input 
              id="period_name"
              v-model="formData.period_name" 
              type="text" 
              required
              placeholder="e.g., January 2024, Q1 2024"
              class="form-input"
              :class="{ error: errors.period_name }"
            >
            <div v-if="errors.period_name" class="error-message">
              {{ errors.period_name }}
            </div>
            <div class="field-hint">
              Use a descriptive name that clearly identifies this accounting period
            </div>
          </div>

          <div class="form-group">
            <label for="status" class="required">Status</label>
            <select 
              id="status" 
              v-model="formData.status" 
              required 
              class="form-select"
              :class="{ error: errors.status }"
            >
              <option value="">Select Status</option>
              <option value="Open">Open</option>
              <option value="Closed">Closed</option>
              <option value="Locked">Locked</option>
            </select>
            <div v-if="errors.status" class="error-message">
              {{ errors.status }}
            </div>
            <div class="field-hint">
              <div class="status-help">
                <div><strong>Open:</strong> Period is active for transactions</div>
                <div><strong>Closed:</strong> Period is closed but can be reopened</div>
                <div><strong>Locked:</strong> Period is permanently locked</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!isEditing" class="step-actions">
          <button @click="nextStep" class="btn btn-primary" :disabled="!isStep1Valid">
            Next: Date Range
            <i class="fas fa-arrow-right"></i>
          </button>
        </div>
      </div>

      <!-- Step 2: Date Range -->
      <div v-show="currentStep === 2 || isEditing" class="form-section">
        <div class="section-header">
          <h2>
            <i class="fas fa-calendar-alt"></i>
            Date Range
          </h2>
          <p>Define the start and end dates for this accounting period</p>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="start_date" class="required">Start Date</label>
            <input 
              id="start_date"
              v-model="formData.start_date" 
              type="date" 
              required
              class="form-input"
              :class="{ error: errors.start_date }"
              @change="onDateChange"
            >
            <div v-if="errors.start_date" class="error-message">
              {{ errors.start_date }}
            </div>
          </div>

          <div class="form-group">
            <label for="end_date" class="required">End Date</label>
            <input 
              id="end_date"
              v-model="formData.end_date" 
              type="date" 
              required
              class="form-input"
              :class="{ error: errors.end_date }"
              :min="formData.start_date"
              @change="onDateChange"
            >
            <div v-if="errors.end_date" class="error-message">
              {{ errors.end_date }}
            </div>
          </div>
        </div>

        <!-- Date Range Summary -->
        <div v-if="formData.start_date && formData.end_date" class="date-summary">
          <div class="summary-card">
            <div class="summary-item">
              <i class="fas fa-calendar-day"></i>
              <div>
                <div class="summary-label">Duration</div>
                <div class="summary-value">{{ periodDuration }} days</div>
              </div>
            </div>
            <div class="summary-item">
              <i class="fas fa-calendar-week"></i>
              <div>
                <div class="summary-label">Weeks</div>
                <div class="summary-value">{{ Math.ceil(periodDuration / 7) }} weeks</div>
              </div>
            </div>
            <div class="summary-item">
              <i class="fas fa-calendar-check"></i>
              <div>
                <div class="summary-label">Period Type</div>
                <div class="summary-value">{{ getPeriodType() }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Date Validation Warnings -->
        <div v-if="dateWarnings.length > 0" class="warnings-section">
          <div v-for="warning in dateWarnings" :key="warning.type" class="warning-card" :class="warning.type">
            <i :class="warning.icon"></i>
            <div>
              <div class="warning-title">{{ warning.title }}</div>
              <div class="warning-message">{{ warning.message }}</div>
            </div>
          </div>
        </div>

        <div v-if="!isEditing" class="step-actions">
          <button @click="prevStep" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back
          </button>
          <button @click="nextStep" class="btn btn-primary" :disabled="!isStep2Valid">
            Next: Review
            <i class="fas fa-arrow-right"></i>
          </button>
        </div>
      </div>

      <!-- Step 3: Review -->
      <div v-show="currentStep === 3" class="form-section">
        <div class="section-header">
          <h2>
            <i class="fas fa-clipboard-check"></i>
            Review & Confirm
          </h2>
          <p>Please review all information before creating the period</p>
        </div>

        <div class="review-section">
          <div class="review-grid">
            <div class="review-item">
              <div class="review-label">Period Name</div>
              <div class="review-value">{{ formData.period_name }}</div>
            </div>
            <div class="review-item">
              <div class="review-label">Status</div>
              <div class="review-value">
                <span class="status-badge" :class="formData.status.toLowerCase()">
                  {{ formData.status }}
                </span>
              </div>
            </div>
            <div class="review-item">
              <div class="review-label">Start Date</div>
              <div class="review-value">{{ formatDate(formData.start_date) }}</div>
            </div>
            <div class="review-item">
              <div class="review-label">End Date</div>
              <div class="review-value">{{ formatDate(formData.end_date) }}</div>
            </div>
            <div class="review-item">
              <div class="review-label">Duration</div>
              <div class="review-value">{{ periodDuration }} days</div>
            </div>
            <div class="review-item">
              <div class="review-label">Period Type</div>
              <div class="review-value">{{ getPeriodType() }}</div>
            </div>
          </div>

          <!-- Overlap Check Results -->
          <div v-if="overlapCheckResult" class="overlap-result">
            <div v-if="overlapCheckResult.hasOverlap" class="overlap-warning">
              <i class="fas fa-exclamation-triangle"></i>
              <div>
                <div class="overlap-title">Date Range Conflict</div>
                <div class="overlap-message">
                  This period overlaps with: {{ overlapCheckResult.conflictingPeriods.join(', ') }}
                </div>
              </div>
            </div>
            <div v-else class="overlap-success">
              <i class="fas fa-check-circle"></i>
              <div>
                <div class="overlap-title">No Conflicts</div>
                <div class="overlap-message">This date range is available</div>
              </div>
            </div>
          </div>

          <div class="review-actions">
            <button @click="prevStep" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i>
              Back to Edit
            </button>
            <button @click="savePeriod" class="btn btn-primary" :disabled="saving || overlapCheckResult?.hasOverlap">
              <i v-if="saving" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-save"></i>
              {{ saving ? 'Creating...' : 'Create Period' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Templates -->
    <div class="quick-templates" v-if="!isEditing">
      <div class="templates-header">
        <h3>
          <i class="fas fa-magic"></i>
          Quick Templates
        </h3>
        <p>Use predefined templates to quickly set up common period types</p>
      </div>
      
      <div class="templates-grid">
        <div 
          v-for="template in periodTemplates" 
          :key="template.id" 
          class="template-card"
          @click="applyTemplate(template)"
        >
          <div class="template-icon" :class="template.type">
            <i :class="template.icon"></i>
          </div>
          <div class="template-content">
            <h4>{{ template.name }}</h4>
            <p>{{ template.description }}</p>
            <div class="template-duration">{{ template.duration }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Existing Periods Preview -->
    <div class="existing-periods" v-if="existingPeriods.length > 0">
      <div class="periods-header">
        <h3>
          <i class="fas fa-history"></i>
          Recent Periods
        </h3>
        <p>Reference your existing periods for consistency</p>
      </div>
      
      <div class="periods-list">
        <div 
          v-for="period in existingPeriods.slice(0, 5)" 
          :key="period.period_id" 
          class="period-item"
        >
          <div class="period-info">
            <div class="period-name">{{ period.period_name }}</div>
            <div class="period-range">
              {{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}
            </div>
          </div>
          <div class="period-status">
            <span class="status-badge" :class="period.status.toLowerCase()">
              {{ period.status }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AccountingPeriodForm',
  data() {
    return {
      isEditing: false,
      periodId: null,
      currentStep: 1,
      saving: false,
      existingPeriods: [],
      overlapCheckResult: null,
      
      formData: {
        period_name: '',
        start_date: '',
        end_date: '',
        status: 'Open'
      },
      
      errors: {},
      
      dateWarnings: [],
      
      periodTemplates: [
        {
          id: 'monthly',
          name: 'Monthly Period',
          description: 'Standard monthly accounting period',
          duration: '1 Month',
          type: 'monthly',
          icon: 'fas fa-calendar',
          generate: () => this.generateMonthlyPeriod()
        },
        {
          id: 'quarterly',
          name: 'Quarterly Period',
          description: 'Three-month quarterly period',
          duration: '3 Months',
          type: 'quarterly',
          icon: 'fas fa-chart-pie',
          generate: () => this.generateQuarterlyPeriod()
        },
        {
          id: 'yearly',
          name: 'Annual Period',
          description: 'Full year accounting period',
          duration: '12 Months',
          type: 'yearly',
          icon: 'fas fa-calendar-alt',
          generate: () => this.generateYearlyPeriod()
        },
        {
          id: 'custom',
          name: 'Custom Period',
          description: 'Define your own date range',
          duration: 'Variable',
          type: 'custom',
          icon: 'fas fa-cogs',
          generate: () => this.generateCustomPeriod()
        }
      ]
    }
  },
  
  computed: {
    isFormValid() {
      return this.isStep1Valid && this.isStep2Valid && Object.keys(this.errors).length === 0
    },
    
    isStep1Valid() {
      return this.formData.period_name && this.formData.status
    },
    
    isStep2Valid() {
      return this.formData.start_date && this.formData.end_date && this.formData.start_date <= this.formData.end_date
    },
    
    periodDuration() {
      if (!this.formData.start_date || !this.formData.end_date) return 0
      const start = new Date(this.formData.start_date)
      const end = new Date(this.formData.end_date)
      const diffTime = Math.abs(end - start)
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
    }
  },
  
  async mounted() {
    this.periodId = this.$route.params.id
    this.isEditing = !!this.periodId
    
    if (this.isEditing) {
      await this.loadPeriod()
    } else {
      this.currentStep = 1
    }
    
    await this.loadExistingPeriods()
  },
  
  methods: {
    async loadPeriod() {
      try {
        const response = await axios.get(`/accounting/accounting-periods/${this.periodId}`)
        this.formData = { ...response.data.data }
      } catch (error) {
        console.error('Error loading period:', error)
        this.$toast?.error('Failed to load period')
        this.goBack()
      }
    },
    
    async loadExistingPeriods() {
      try {
        const response = await axios.get('/accounting/accounting-periods')
        this.existingPeriods = response.data.data
      } catch (error) {
        console.error('Error loading existing periods:', error)
      }
    },
    
    async checkForOverlaps() {
      if (!this.formData.start_date || !this.formData.end_date) return
      
      const conflictingPeriods = this.existingPeriods.filter(period => {
        if (this.isEditing && period.period_id === this.periodId) return false
        
        const periodStart = new Date(period.start_date)
        const periodEnd = new Date(period.end_date)
        const formStart = new Date(this.formData.start_date)
        const formEnd = new Date(this.formData.end_date)
        
        return (formStart <= periodEnd && formEnd >= periodStart)
      })
      
      this.overlapCheckResult = {
        hasOverlap: conflictingPeriods.length > 0,
        conflictingPeriods: conflictingPeriods.map(p => p.period_name)
      }
    },
    
    onDateChange() {
      this.validateDates()
      this.generateDateWarnings()
      if (this.currentStep === 3) {
        this.checkForOverlaps()
      }
    },
    
    validateDates() {
      this.errors = { ...this.errors }
      
      if (this.formData.start_date && this.formData.end_date) {
        if (new Date(this.formData.start_date) > new Date(this.formData.end_date)) {
          this.errors.end_date = 'End date must be after start date'
        } else {
          delete this.errors.end_date
        }
      }
    },
    
    generateDateWarnings() {
      this.dateWarnings = []
      
      if (!this.formData.start_date || !this.formData.end_date) return
      
      const duration = this.periodDuration
      
      if (duration > 365) {
        this.dateWarnings.push({
          type: 'warning',
          icon: 'fas fa-exclamation-triangle',
          title: 'Long Period',
          message: 'This period is longer than a year. Consider breaking it into smaller periods.'
        })
      } else if (duration < 7) {
        this.dateWarnings.push({
          type: 'info',
          icon: 'fas fa-info-circle',
          title: 'Short Period',
          message: 'This is a very short accounting period.'
        })
      }
      
      const start = new Date(this.formData.start_date)
      const end = new Date(this.formData.end_date)
      
      if (end > new Date()) {
        this.dateWarnings.push({
          type: 'info',
          icon: 'fas fa-calendar-plus',
          title: 'Future Period',
          message: 'This period extends into the future.'
        })
      }
    },
    
    getPeriodType() {
      const duration = this.periodDuration
      
      if (duration <= 31) return 'Monthly'
      if (duration <= 92) return 'Quarterly'
      if (duration <= 184) return 'Semi-Annual'
      if (duration <= 366) return 'Annual'
      return 'Multi-Year'
    },
    
    nextStep() {
      if (this.currentStep === 1 && this.isStep1Valid) {
        this.currentStep = 2
      } else if (this.currentStep === 2 && this.isStep2Valid) {
        this.currentStep = 3
        this.checkForOverlaps()
      }
    },
    
    prevStep() {
      if (this.currentStep > 1) {
        this.currentStep--
      }
    },
    
    async savePeriod() {
      try {
        this.saving = true
        this.errors = {}
        
        if (this.isEditing) {
          await axios.put(`/accounting/accounting-periods/${this.periodId}`, this.formData)
          this.$toast?.success('Period updated successfully')
        } else {
          await axios.post('/accounting/accounting-periods', this.formData)
          this.$toast?.success('Period created successfully')
        }
        
        this.goBack()
      } catch (error) {
        console.error('Error saving period:', error)
        
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors
        } else {
          this.$toast?.error(error.response?.data?.message || 'Failed to save period')
        }
      } finally {
        this.saving = false
      }
    },
    
    resetForm() {
      this.formData = {
        period_name: '',
        start_date: '',
        end_date: '',
        status: 'Open'
      }
      this.errors = {}
      this.dateWarnings = []
      this.overlapCheckResult = null
      this.currentStep = 1
    },
    
    goBack() {
      this.$router.push('/accounting/periods')
    },
    
    formatDate(date) {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    },
    
    applyTemplate(template) {
      template.generate()
    },
    
    generateMonthlyPeriod() {
      const now = new Date()
      const year = now.getFullYear()
      const month = now.getMonth()
      
      const start = new Date(year, month, 1)
      const end = new Date(year, month + 1, 0)
      
      this.formData.period_name = start.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
      this.formData.start_date = start.toISOString().split('T')[0]
      this.formData.end_date = end.toISOString().split('T')[0]
      this.formData.status = 'Open'
      
      this.onDateChange()
    },
    
    generateQuarterlyPeriod() {
      const now = new Date()
      const year = now.getFullYear()
      const quarter = Math.floor(now.getMonth() / 3) + 1
      
      const start = new Date(year, (quarter - 1) * 3, 1)
      const end = new Date(year, quarter * 3, 0)
      
      this.formData.period_name = `Q${quarter} ${year}`
      this.formData.start_date = start.toISOString().split('T')[0]
      this.formData.end_date = end.toISOString().split('T')[0]
      this.formData.status = 'Open'
      
      this.onDateChange()
    },
    
    generateYearlyPeriod() {
      const now = new Date()
      const year = now.getFullYear()
      
      const start = new Date(year, 0, 1)
      const end = new Date(year, 11, 31)
      
      this.formData.period_name = `Fiscal Year ${year}`
      this.formData.start_date = start.toISOString().split('T')[0]
      this.formData.end_date = end.toISOString().split('T')[0]
      this.formData.status = 'Open'
      
      this.onDateChange()
    },
    
    generateCustomPeriod() {
      // Just reset to allow manual entry
      this.formData.period_name = ''
      this.formData.start_date = ''
      this.formData.end_date = ''
      this.formData.status = 'Open'
    }
  }
}
</script>

<style scoped>
/* Main Layout */
.period-form-page {
  padding: 1.5rem;
  background: #f8fafc;
  min-height: 100vh;
}

/* Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.header-left {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.back-btn {
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  padding: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
  color: #6b7280;
}

.back-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.title-section {
  flex: 1;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: #3b82f6;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

/* Progress Steps */
.progress-steps {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 2rem;
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e5e7eb;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  transition: all 0.2s;
}

.step.active .step-number {
  background: #3b82f6;
  color: white;
}

.step.completed .step-number {
  background: #10b981;
  color: white;
}

.step-label {
  font-size: 0.9rem;
  color: #6b7280;
  font-weight: 500;
}

.step.active .step-label {
  color: #3b82f6;
}

.step.completed .step-label {
  color: #10b981;
}

.step-line {
  width: 100px;
  height: 2px;
  background: #e5e7eb;
  transition: all 0.2s;
}

.step-line.completed {
  background: #10b981;
}

/* Form Card */
.form-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 2rem;
}

.form-section {
  padding: 2rem;
}

.section-header {
  margin-bottom: 2rem;
  text-align: center;
}

.section-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
}

.section-header h2 i {
  color: #3b82f6;
}

.section-header p {
  color: #64748b;
  font-size: 1rem;
}

/* Form Elements */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
  margin-bottom: 2rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 500;
  color: #374151;
  font-size: 0.9rem;
}

.form-group label.required::after {
  content: ' *';
  color: #ef4444;
}

.form-input, .form-select {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: all 0.2s;
  background: white;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.error, .form-select.error {
  border-color: #ef4444;
}

.error-message {
  color: #ef4444;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

.field-hint {
  color: #6b7280;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

.status-help {
  background: #f8fafc;
  padding: 0.75rem;
  border-radius: 6px;
  border-left: 3px solid #3b82f6;
  font-size: 0.8rem;
}

.status-help div {
  margin-bottom: 0.25rem;
}

.status-help div:last-child {
  margin-bottom: 0;
}

/* Date Summary */
.date-summary {
  margin-top: 1.5rem;
}

.summary-card {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.summary-item i {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.summary-label {
  font-size: 0.8rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.summary-value {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.9rem;
}

/* Warnings */
.warnings-section {
  margin-top: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.warning-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid;
}

.warning-card.warning {
  background: #fffbeb;
  border-color: #f59e0b;
}

.warning-card.info {
  background: #eff6ff;
  border-color: #3b82f6;
}

.warning-card i {
  margin-top: 0.25rem;
}

.warning-card.warning i {
  color: #f59e0b;
}

.warning-card.info i {
  color: #3b82f6;
}

.warning-title {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.25rem;
  font-size: 0.9rem;
}

.warning-message {
  color: #6b7280;
  font-size: 0.8rem;
}

/* Step Actions */
.step-actions {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e5e7eb;
}

/* Review Section */
.review-section {
  max-width: 600px;
  margin: 0 auto;
}

.review-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.review-item {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.review-label {
  font-size: 0.8rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.review-value {
  font-weight: 600;
  color: #1e293b;
  font-size: 1rem;
}

.overlap-result {
  margin: 1.5rem 0;
}

.overlap-warning, .overlap-success {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid;
}

.overlap-warning {
  background: #fef2f2;
  border-color: #ef4444;
}

.overlap-success {
  background: #f0fdf4;
  border-color: #10b981;
}

.overlap-warning i {
  color: #ef4444;
  margin-top: 0.25rem;
}

.overlap-success i {
  color: #10b981;
  margin-top: 0.25rem;
}

.overlap-title {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.25rem;
  font-size: 0.9rem;
}

.overlap-message {
  color: #6b7280;
  font-size: 0.8rem;
}

.review-actions {
  display: flex;
  justify-content: center;
  gap: 1rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

/* Status Badge */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-badge.open {
  background: #dcfce7;
  color: #166534;
}

.status-badge.closed {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge.locked {
  background: #fef3c7;
  color: #92400e;
}

/* Quick Templates */
.quick-templates {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 2rem;
}

.templates-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  text-align: center;
}

.templates-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.templates-header h3 i {
  color: #8b5cf6;
}

.templates-header p {
  color: #64748b;
  font-size: 0.9rem;
}

.templates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
  padding: 1.5rem;
}

.template-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.template-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.template-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: white;
}

.template-icon.monthly {
  background: #3b82f6;
}

.template-icon.quarterly {
  background: #10b981;
}

.template-icon.yearly {
  background: #f59e0b;
}

.template-icon.custom {
  background: #8b5cf6;
}

.template-content {
  flex: 1;
}

.template-content h4 {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.25rem;
  font-size: 0.9rem;
}

.template-content p {
  color: #6b7280;
  font-size: 0.8rem;
  margin-bottom: 0.5rem;
}

.template-duration {
  color: #3b82f6;
  font-size: 0.8rem;
  font-weight: 500;
}

/* Existing Periods */
.existing-periods {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.periods-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  text-align: center;
}

.periods-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.periods-header h3 i {
  color: #6b7280;
}

.periods-header p {
  color: #64748b;
  font-size: 0.9rem;
}

.periods-list {
  padding: 1rem;
}

.period-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  border-radius: 6px;
  margin-bottom: 0.5rem;
  transition: background-color 0.2s;
}

.period-item:hover {
  background: #f8fafc;
}

.period-item:last-child {
  margin-bottom: 0;
}

.period-info {
  flex: 1;
}

.period-name {
  font-weight: 500;
  color: #1e293b;
  margin-bottom: 0.25rem;
  font-size: 0.9rem;
}

.period-range {
  color: #6b7280;
  font-size: 0.8rem;
}

.period-status {
  margin-left: 1rem;
}

/* Buttons */
.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #4b5563;
}

/* Responsive */
@media (max-width: 768px) {
  .period-form-page {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1.5rem;
    align-items: stretch;
  }
  
  .header-left {
    flex-direction: column;
    gap: 1rem;
  }
  
  .header-actions {
    justify-content: stretch;
  }
  
  .header-actions .btn {
    flex: 1;
  }
  
  .progress-steps {
    overflow-x: auto;
    justify-content: flex-start;
    padding: 1rem;
  }
  
  .step-line {
    width: 60px;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-card {
    grid-template-columns: 1fr;
  }
  
  .review-grid {
    grid-template-columns: 1fr;
  }
  
  .templates-grid {
    grid-template-columns: 1fr;
  }
  
  .template-card {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }
  
  .step-actions, .review-actions {
    flex-direction: column;
  }
  
  .step-actions .btn, .review-actions .btn {
    width: 100%;
  }
}
</style>