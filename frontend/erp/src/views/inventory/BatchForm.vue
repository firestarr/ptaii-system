<!-- src/views/inventory/BatchForm.vue -->
<template>
    <div class="batch-form-container">
      <div class="form-header">
        <h2 class="form-title">
          <router-link :to="`/items/${itemId}/batches`" class="back-link">
            <i class="fas fa-arrow-left"></i>
          </router-link>
          {{ isEditMode ? 'Edit Batch' : 'Create New Batch' }}
        </h2>
        <div class="item-info" v-if="item">
          <div class="item-code">{{ item.item_code }}</div>
          <div class="item-name">{{ item.name }}</div>
        </div>
      </div>
  
      <div v-if="loading" class="loading-overlay">
        <div class="spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>{{ loadingMessage }}</p>
      </div>
  
      <form @submit.prevent="submitForm" class="batch-form" :class="{ 'form-loading': loading }">
        <div class="form-group">
          <label for="batch_number">Batch Number <span class="required">*</span></label>
          <input 
            type="text" 
            id="batch_number" 
            v-model="form.batch_number" 
            required
            :class="{ 'input-error': errors.batch_number }"
          />
          <div v-if="errors.batch_number" class="error-message">{{ errors.batch_number[0] }}</div>
        </div>
  
        <div class="form-row">
          <div class="form-group">
            <label for="manufacturing_date">Manufacturing Date</label>
            <input 
              type="date" 
              id="manufacturing_date" 
              v-model="form.manufacturing_date"
              :max="today"
              :class="{ 'input-error': errors.manufacturing_date }"
            />
            <div v-if="errors.manufacturing_date" class="error-message">{{ errors.manufacturing_date[0] }}</div>
          </div>
  
          <div class="form-group">
            <label for="expiry_date">Expiry Date</label>
            <input 
              type="date" 
              id="expiry_date" 
              v-model="form.expiry_date"
              :min="form.manufacturing_date || today"
              :class="{ 'input-error': errors.expiry_date }"
            />
            <div v-if="errors.expiry_date" class="error-message">{{ errors.expiry_date[0] }}</div>
            <div v-if="form.expiry_date" class="help-text">
              {{ daysUntilExpiry }} days until expiry
            </div>
          </div>
        </div>
  
        <div class="form-group">
          <label for="lot_number">Lot Number</label>
          <input 
            type="text" 
            id="lot_number" 
            v-model="form.lot_number"
            :class="{ 'input-error': errors.lot_number }"
          />
          <div v-if="errors.lot_number" class="error-message">{{ errors.lot_number[0] }}</div>
        </div>
  
        <div class="form-actions">
          <router-link :to="`/items/${itemId}/batches`" class="btn btn-secondary">
            Cancel
          </router-link>
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
            {{ submitting ? (isEditMode ? 'Updating...' : 'Creating...') : (isEditMode ? 'Update Batch' : 'Create Batch') }}
          </button>
        </div>
      </form>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'BatchForm',
    props: {
      itemId: {
        type: [String, Number],
        required: true
      },
      id: {
        type: [String, Number],
        default: null
      }
    },
    data() {
      return {
        form: {
          batch_number: '',
          manufacturing_date: '',
          expiry_date: '',
          lot_number: ''
        },
        item: null,
        loading: false,
        submitting: false,
        errors: {},
        loadingMessage: 'Loading...'
      };
    },
    computed: {
      isEditMode() {
        return !!this.id;
      },
      today() {
        return new Date().toISOString().split('T')[0];
      },
      daysUntilExpiry() {
        if (!this.form.expiry_date) return null;
        
        const expiryDate = new Date(this.form.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (expiryDate < today) return 'Expired';
        
        const diffTime = expiryDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        return diffDays;
      }
    },
    created() {
      this.fetchItemDetails();
      
      if (this.isEditMode) {
        this.fetchBatchDetails();
      }
    },
    methods: {
      async fetchItemDetails() {
        this.loading = true;
        this.loadingMessage = 'Loading item details...';
        
        try {
          const response = await axios.get(`/items/${this.itemId}`);
          if (response.data.success) {
            this.item = response.data.data;
          }
        } catch (error) {
          console.error('Error fetching item details:', error);
          this.$router.push(`/items/${this.itemId}/batches`);
        } finally {
          if (!this.isEditMode) {
            this.loading = false;
          }
        }
      },
      async fetchBatchDetails() {
        this.loading = true;
        this.loadingMessage = 'Loading batch details...';
        
        try {
          const response = await axios.get(`/items/${this.itemId}/batches/${this.id}`);
          if (response.data.success) {
            const batch = response.data.data;
            
            // Format dates for the date inputs (YYYY-MM-DD)
            this.form.batch_number = batch.batch_number;
            this.form.manufacturing_date = batch.manufacturing_date ? new Date(batch.manufacturing_date).toISOString().split('T')[0] : '';
            this.form.expiry_date = batch.expiry_date ? new Date(batch.expiry_date).toISOString().split('T')[0] : '';
            this.form.lot_number = batch.lot_number || '';
          }
        } catch (error) {
          console.error('Error fetching batch details:', error);
          this.$router.push(`/items/${this.itemId}/batches`);
        } finally {
          this.loading = false;
        }
      },
      async submitForm() {
        this.submitting = true;
        this.errors = {};
        
        try {
          let response;
          
          if (this.isEditMode) {
            // Update existing batch
            response = await axios.put(`/items/${this.itemId}/batches/${this.id}`, this.form);
          } else {
            // Create new batch
            response = await axios.post(`/items/${this.itemId}/batches`, this.form);
          }
          
          if (response.data.success) {
            // Redirect to batch list with success message
            this.$router.push({
              path: `/items/${this.itemId}/batches`,
              query: { 
                message: this.isEditMode ? 'Batch updated successfully' : 'Batch created successfully',
                type: 'success'
              }
            });
          }
        } catch (error) {
          console.error('Error submitting form:', error);
          
          if (error.response && error.response.data) {
            if (error.response.data.errors) {
              this.errors = error.response.data.errors;
            } else if (error.response.data.message) {
              // Show general error
              this.errors = { general: [error.response.data.message] };
            }
          } else {
            this.errors = { general: ['An error occurred while saving the batch. Please try again.'] };
          }
        } finally {
          this.submitting = false;
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .batch-form-container {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    position: relative;
  }
  
  .form-header {
    margin-bottom: 2rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .form-title {
    display: flex;
    align-items: center;
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
  }
  
  .back-link {
    margin-right: 0.75rem;
    color: var(--gray-600);
    transition: color 0.2s;
  }
  
  .back-link:hover {
    color: var(--primary-color);
  }
  
  .item-info {
    display: flex;
    gap: 1rem;
    align-items: center;
  }
  
  .item-code {
    background-color: var(--gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: var(--gray-700);
    font-family: monospace;
  }
  
  .item-name {
    font-size: 1rem;
    color: var(--gray-800);
    font-weight: 500;
  }
  
  .loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 10;
  }
  
  .spinner {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
  }
  
  .form-loading {
    opacity: 0.6;
    pointer-events: none;
  }
  
  .batch-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    max-width: 800px;
  }
  
  .form-row {
    display: flex;
    gap: 1.5rem;
  }
  
  .form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  
  label {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: var(--gray-700);
  }
  
  .required {
    color: #ef4444;
  }
  
  input {
    padding: 0.625rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  
  input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .input-error {
    border-color: #ef4444;
  }
  
  .input-error:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
  }
  
  .error-message {
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 0.25rem;
  }
  
  .help-text {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1rem;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1.25rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
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
    color: var(--gray-700);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-300);
  }
  
  .btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .btn i {
    margin-right: 0.5rem;
  }
  
  @media (max-width: 640px) {
    .form-row {
      flex-direction: column;
    }
  }
  </style>