<!-- src/views/manufacturing/WorkCenterForm.vue -->
<template>
    <div class="work-center-form">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">{{ isEditMode ? 'Edit Work Center' : 'Create Work Center' }}</h2>
        </div>
        <div class="card-body">
          <div v-if="isLoading" class="text-center p-5">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Loading work center data...</p>
          </div>
          
          <form v-else @submit.prevent="saveWorkCenter">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="code">Code <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    id="code"
                    v-model="workCenter.code"
                    class="form-control"
                    :class="{ 'is-invalid': errors.code }"
                    required
                    maxlength="50"
                    placeholder="Enter unique code"
                  />
                  <div v-if="errors.code" class="invalid-feedback">{{ errors.code }}</div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group">
                  <label for="name">Name <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    id="name"
                    v-model="workCenter.name"
                    class="form-control"
                    :class="{ 'is-invalid': errors.name }"
                    required
                    maxlength="100"
                    placeholder="Enter work center name"
                  />
                  <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
                </div>
              </div>
            </div>
            
            <div class="row mt-3">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="capacity">Capacity (units/hour) <span class="text-danger">*</span></label>
                  <input
                    type="number"
                    id="capacity"
                    v-model="workCenter.capacity"
                    class="form-control"
                    :class="{ 'is-invalid': errors.capacity }"
                    required
                    min="0"
                    step="0.01"
                    placeholder="Enter capacity"
                  />
                  <div v-if="errors.capacity" class="invalid-feedback">{{ errors.capacity }}</div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-group">
                  <label for="cost_per_hour">Cost per Hour <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">$</span>
                    </div>
                    <input
                      type="number"
                      id="cost_per_hour"
                      v-model="workCenter.cost_per_hour"
                      class="form-control"
                      :class="{ 'is-invalid': errors.cost_per_hour }"
                      required
                      min="0"
                      step="0.01"
                      placeholder="Enter hourly cost"
                    />
                  </div>
                  <div v-if="errors.cost_per_hour" class="invalid-feedback">{{ errors.cost_per_hour }}</div>
                </div>
              </div>
            </div>
            
            <div class="form-group mt-3">
              <div class="custom-control custom-switch">
                <input
                  type="checkbox"
                  id="is_active"
                  v-model="workCenter.is_active"
                  class="custom-control-input"
                />
                <label class="custom-control-label" for="is_active">Active</label>
              </div>
            </div>
            
            <div class="form-group mt-4">
              <label for="description">Description</label>
              <textarea
                id="description"
                v-model="workCenter.description"
                class="form-control"
                :class="{ 'is-invalid': errors.description }"
                rows="4"
                placeholder="Enter optional description"
              ></textarea>
              <div v-if="errors.description" class="invalid-feedback">{{ errors.description }}</div>
            </div>
            
            <div class="form-actions mt-4">
              <button type="button" class="btn btn-secondary mr-2" @click="goBack">
                <i class="fa-solid fa-arrow-left"></i> Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="isSaving">
                <i class="fas fa-save mr-2"></i> {{ isSaving ? 'Saving...' : 'Save Work Center' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import { ref, reactive, computed, onMounted } from 'vue';
  import { useRoute, useRouter } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'WorkCenterForm',
    setup() {
      const route = useRoute();
      const router = useRouter();
      
      const workCenterId = computed(() => route.params.id);
      const isEditMode = computed(() => !!workCenterId.value);
      
      const workCenter = reactive({
        name: '',
        code: '',
        capacity: '',
        cost_per_hour: '',
        description: '',
        is_active: true
      });
      
      const errors = reactive({});
      const isLoading = ref(false);
      const isSaving = ref(false);
      
      const loadWorkCenter = async () => {
        if (!isEditMode.value) return;
        
        isLoading.value = true;
        try {
          const response = await axios.get(`/work-centers/${workCenterId.value}`);
          const data = response.data.data;
          
          // Update the reactive object properties
          Object.keys(workCenter).forEach(key => {
            if (data[key] !== undefined) {
              workCenter[key] = data[key];
            }
          });
        } catch (error) {
          console.error('Error loading work center:', error);
          alert('Failed to load work center details. Please try again later.');
          router.push('/manufacturing/work-centers');
        } finally {
          isLoading.value = false;
        }
      };
      
      const saveWorkCenter = async () => {
        isSaving.value = true;
        errors.value = {};
        
        try {
          let response;
          
          if (isEditMode.value) {
            response = await axios.put(`/work-centers/${workCenterId.value}`, workCenter);
          } else {
            response = await axios.post('/work-centers', workCenter);
          }
          
          // Navigate to the detail page after successful save
          const id = isEditMode.value ? workCenterId.value : response.data.data.workcenter_id;
          router.push(`/manufacturing/work-centers/${id}`);
        } catch (error) {
          if (error.response && error.response.data.errors) {
            // Set validation errors
            Object.assign(errors, error.response.data.errors);
          } else {
            console.error('Error saving work center:', error);
            alert('Failed to save work center. Please try again later.');
          }
        } finally {
          isSaving.value = false;
        }
      };
      
      const goBack = () => {
        router.go(-1);
      };
      
      onMounted(() => {
        loadWorkCenter();
      });
      
      return {
        workCenter,
        errors,
        isLoading,
        isSaving,
        isEditMode,
        saveWorkCenter,
        goBack
      };
    }
  };
  </script>
  
  <style scoped>
  /* Styling keseluruhan form */
/* Styling dasar untuk form */
.work-center-form {
  max-width: 900px;
  margin: 0 auto;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* Card styling yang lebih profesional */
.card {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border-radius: 6px;
  border: none;
  background: #fff;
  margin-bottom: 2rem;
}

.card-header {
  padding: 1.25rem 1.5rem;
  background-color: #f8f9fa;
  border-bottom: 1px solid #eaeaea;
}

.card-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: #333;
}

.card-body {
  padding: 2rem 1.5rem;
}

/* Layout form yang lebih terstruktur */
form {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

.row {
  display: flex;
  flex-wrap: wrap;
  margin: 0 -0.75rem;
  gap: 0;
}

.col-md-6 {
  flex: 0 0 50%;
  padding: 0 0.75rem;
  box-sizing: border-box;
}

@media (max-width: 768px) {
  .col-md-6 {
    flex: 0 0 100%;
    margin-bottom: 1.25rem;
  }
}

/* Styling form group yang rapi */
.form-group {
  margin-bottom: 1.5rem;
}

/* Label yang jelas dan konsisten */
label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
  font-weight: 500;
  color: #414141;
}

.text-danger {
  color: #dc3545;
  margin-left: 0.25rem;
}

/* Input yang bersih dan user-friendly */
.form-control {
  display: block;
  width: 100%;
  padding: 0.65rem 0.85rem;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #495057;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #ced4da;
  border-radius: 4px;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  box-sizing: border-box;
}

.form-control:focus {
  border-color: #80bdff;
  outline: 0;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-control::placeholder {
  color: #aab0b7;
  opacity: 1;
}

/* Input group dengan simbol mata uang */
.input-group {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  width: 100%;
}

.input-group-prepend {
  margin-right: -1px;
  display: flex;
}

.input-group-text {
  display: flex;
  align-items: center;
  padding: 0.65rem 0.75rem;
  font-size: 0.95rem;
  font-weight: 400;
  line-height: 1.5;
  color: #495057;
  text-align: center;
  white-space: nowrap;
  background-color: #e9ecef;
  border: 1px solid #ced4da;
  border-radius: 4px 0 0 4px;
}

.input-group > .form-control {
  position: relative;
  flex: 1 1 auto;
  width: 1%;
  margin-bottom: 0;
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}

/* Styling untuk checkbox */
.custom-control {
  position: relative;
  display: block;
  min-height: 1.5rem;
  padding-left: 1.75rem;
  margin: 1rem 0;
}

.custom-control-input {
  position: absolute;
  z-index: -1;
  opacity: 0;
}

.custom-control-label {
  position: relative;
  margin-bottom: 0;
  cursor: pointer;
}

.custom-control-label::before {
  position: absolute;
  top: 0.25rem;
  left: -1.75rem;
  display: block;
  width: 1rem;
  height: 1rem;
  pointer-events: none;
  content: "";
  background-color: #fff;
  border: 1px solid #adb5bd;
  border-radius: 0.25rem;
}

.custom-control-input:checked ~ .custom-control-label::before {
  color: #fff;
  background-color: #28a745;
  border-color: #28a745;
}

/* Textarea dengan ukuran yang lebih baik */
textarea.form-control {
  min-height: 120px;
  resize: vertical;
}

/* Styling untuk validasi */
.is-invalid {
  border-color: #dc3545 !important;
}

.invalid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 80%;
  color: #dc3545;
}

/* Tombol yang menarik dan jelas */
.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.5rem;
  gap: 0.75rem;
}

.btn {
  display: inline-block;
  font-weight: 500;
  text-align: center;
  vertical-align: middle;
  user-select: none;
  padding: 0.65rem 1.25rem;
  font-size: 0.95rem;
  line-height: 1.5;
  border-radius: 4px;
  transition: all 0.15s ease-in-out;
  cursor: pointer;
  border: 1px solid transparent;
}

.btn:focus {
  outline: 0;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn-primary {
  color: #fff;
  background-color: #007bff;
  border-color: #007bff;
}

.btn-primary:hover {
  background-color: #0069d9;
  border-color: #0062cc;
}

.btn-secondary {
  color: #fff;
  background-color: #6c757d;
  border-color: #6c757d;
}

.btn-secondary:hover {
  background-color: #5a6268;
  border-color: #545b62;
}

.mr-2 {
  margin-right: 0.5rem;
}

/* Icon styling */
.fas {
  display: inline-block;
  font-style: normal;
  font-variant: normal;
  font-weight: normal;
  line-height: 1;
}

/* Loading indicator */
.text-center {
  text-align: center;
}

.p-5 {
  padding: 3rem;
}

.mt-2 {
  margin-top: 0.5rem;
}

/* Responsive adjustments */
@media (max-width: 576px) {
  .card-body {
    padding: 1.5rem 1rem;
  }
  
  .form-actions {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .btn {
    width: 100%;
  }
}
  </style>