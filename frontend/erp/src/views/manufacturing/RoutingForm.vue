<!-- src/views/manufacturing/RoutingForm.vue -->
<template>
    <div class="routing-form-container">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">{{ isEditMode ? 'Edit Routing' : 'Buat Routing Baru' }}</h2>
          <div>
            <button @click="goBack" class="btn btn-secondary mr-2">
              <i class="fas fa-arrow-left mr-1"></i> Kembali
            </button>
            <button @click="saveRouting" class="btn btn-primary" :disabled="isSaving">
              <i class="fas fa-save mr-1"></i> {{ isSaving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
        <div class="card-body">
          <form @submit.prevent="saveRouting">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="item_id">Produk <span class="text-danger">*</span></label>
                  <div class="form-group position-relative" style="z-index: 1000;">
                    <input
                      type="text"
                      class="form-control"
                      placeholder="Cari dan pilih produk..."
                      v-model="searchQuery"
                      :disabled="isEditMode"
                      ref="inputRef"
                      @focus="showDropdown = true"
                      @blur="hideDropdown"
                      @input="onSearchInput"
                      @keydown.down.prevent="highlightNext"
                      @keydown.up.prevent="highlightPrev"
                      @keydown.enter.prevent="selectHighlighted"
                      autocomplete="off"
                      required
                    />
                    <input type="hidden" v-model="routing.item_id" required />
                    <div
                      v-if="showDropdown && filteredItems.length > 0"
                      class="dropdown-menu show w-100"
                      style="max-height: 200px; overflow-y: auto;"
                    >
                      <div
                        v-for="(item, index) in filteredItems"
                        :key="item.item_id"
                        class="dropdown-item"
                        :class="{ active: index === highlightedIndex }"
                        @mousedown.prevent="selectItem(item)"
                      >
                        {{ item.name }} ({{ item.item_code }})
                      </div>
                    </div>
                    <small v-if="errors.item_id" class="text-danger">{{ errors.item_id[0] }}</small>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="routing_code">Kode Routing <span class="text-danger">*</span></label>
                  <input
                    id="routing_code"
                    v-model="routing.routing_code"
                    type="text"
                    class="form-control"
                    required
                    maxlength="50"
                    placeholder="Kode unik untuk routing ini"
                  />
                  <small v-if="errors.routing_code" class="text-danger">{{ errors.routing_code[0] }}</small>
                </div>
              </div>
            </div>
  
            <div class="row mt-3">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="revision">Revisi <span class="text-danger">*</span></label>
                  <input
                    id="revision"
                    v-model="routing.revision"
                    type="text"
                    class="form-control"
                    required
                    maxlength="10"
                    placeholder="Mis: 01, A, 1.0"
                  />
                  <small v-if="errors.revision" class="text-danger">{{ errors.revision[0] }}</small>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="effective_date">Tanggal Efektif <span class="text-danger">*</span></label>
                  <input
                    id="effective_date"
                    v-model="routing.effective_date"
                    type="date"
                    class="form-control"
                    required
                  />
                  <small v-if="errors.effective_date" class="text-danger">{{ errors.effective_date[0] }}</small>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="status">Status <span class="text-danger">*</span></label>
                  <select id="status" v-model="routing.status" class="form-control" required>
                    <option value="Draft">Draft</option>
                    <option value="Active">Aktif</option>
                    <option value="Obsolete">Tidak Berlaku</option>
                  </select>
                  <small v-if="errors.status" class="text-danger">{{ errors.status[0] }}</small>
                </div>
              </div>
            </div>
  
            <div class="mt-4">
              <h3>Operasi Routing</h3>
              <p class="text-muted">
                {{ isEditMode ? 
                  'Operasi dapat ditambahkan setelah routing disimpan di halaman detail.' : 
                  'Operasi dapat ditambahkan setelah routing disimpan.' }}
              </p>
            </div>
          </form>
        </div>
      </div>
    </div>
  </template>
  
  <script>
import { ref, reactive, computed, onMounted, watch } from 'vue';
  import { useRouter, useRoute } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'RoutingForm',
  setup() {
    const router = useRouter();
    const route = useRoute();
    const routingId = computed(() => route.params.id);
    const isEditMode = computed(() => !!routingId.value);
    const isSaving = ref(false);
    const items = ref([]);
    const errors = ref({});

    // Routing form data
    const routing = reactive({
      item_id: '',
      routing_code: '',
      revision: '',
      effective_date: new Date().toISOString().substring(0, 10), // Default to today
      status: 'Draft',
    });

    const searchQuery = ref('');
    const showDropdown = ref(false);
    const highlightedIndex = ref(-1);
    const dropdownRef = ref(null);

    // Filtered items based on search query
    const filteredItems = computed(() => {
      if (!searchQuery.value) {
        return items.value;
      }
      const query = searchQuery.value.toLowerCase();
      return items.value.filter(
        (item) =>
          item.name.toLowerCase().includes(query) ||
          item.item_code.toLowerCase().includes(query)
      );
    });

    // Update searchQuery when routing.item_id changes (for edit mode)
    const updateSearchQueryFromItemId = () => {
      const selectedItem = items.value.find(item => item.item_id === routing.item_id);
      if (selectedItem) {
        searchQuery.value = `${selectedItem.name} (${selectedItem.item_code})`;
      } else {
        searchQuery.value = '';
      }
    };

    // Watch routing.item_id to update searchQuery
    watch(() => routing.item_id, () => {
      updateSearchQueryFromItemId();
    });

    // Handle input event on search box
    const onSearchInput = () => {
      showDropdown.value = true;
      highlightedIndex.value = -1;
      routing.item_id = ''; // Clear selected item_id while typing
    };

    // Select an item from dropdown
    const selectItem = (item) => {
      routing.item_id = item.item_id;
      searchQuery.value = `${item.name} (${item.item_code})`;
      showDropdown.value = false;
      highlightedIndex.value = -1;
    };

    // Keyboard navigation handlers
    const highlightNext = () => {
      if (highlightedIndex.value < filteredItems.value.length - 1) {
        highlightedIndex.value++;
      }
    };

    const highlightPrev = () => {
      if (highlightedIndex.value > 0) {
        highlightedIndex.value--;
      }
    };

    const selectHighlighted = () => {
      if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredItems.value.length) {
        selectItem(filteredItems.value[highlightedIndex.value]);
      }
    };

    // Load items for dropdown
    const loadItems = async () => {
      try {
        const response = await axios.get('/items');
        items.value = response.data.data;
        updateSearchQueryFromItemId();
      } catch (error) {
        console.error('Error loading items:', error);
      }
    };

    // Load existing routing data if in edit mode
    const loadRoutingData = async () => {
      if (!isEditMode.value) return;
      
      try {
        const response = await axios.get(`/routings/${routingId.value}`);
        const data = response.data.data;
        
        // Update reactive object with fetched data
        routing.item_id = data.item_id;
        routing.routing_code = data.routing_code;
        routing.revision = data.revision;
        routing.effective_date = new Date(data.effective_date).toISOString().substring(0, 10);
        routing.status = data.status;
      } catch (error) {
        console.error('Error loading routing data:', error);
        alert('Gagal memuat data routing. Silakan coba lagi.');
      }
    };

    // Close dropdown when clicking outside
    const hideDropdown = () => {
      setTimeout(() => {
        showDropdown.value = false;
      }, 200);
    };

    // Save routing
    const saveRouting = async () => {
      isSaving.value = true;
      errors.value = {};
      
      try {
        let response;
        
        if (isEditMode.value) {
          response = await axios.put(`/routings/${routingId.value}`, routing);
        } else {
          response = await axios.post('/routings', routing);
        }
        
        // Navigate to detail page after successful save
        const savedRouting = response.data.data;
        router.push(`/manufacturing/routings/${savedRouting.routing_id}`);
      } catch (error) {
        console.error('Error saving routing:', error);
        
        if (error.response && error.response.data && error.response.data.errors) {
          errors.value = error.response.data.errors;
        } else {
          alert('Gagal menyimpan routing. Silakan coba lagi.');
        }
      } finally {
        isSaving.value = false;
      }
    };

    // Go back to previous page
    const goBack = () => {
      router.go(-1);
    };

    // Load necessary data on component mount
    onMounted(() => {
      loadItems();
      if (isEditMode.value) {
        loadRoutingData();
      }
    });

    // Removed global click listener for outside clicks

    return {
      routing,
      items,
      errors,
      isEditMode,
      isSaving,
      saveRouting,
      goBack,
      searchQuery,
      showDropdown,
      filteredItems,
      highlightedIndex,
      onSearchInput,
      selectItem,
      highlightNext,
      highlightPrev,
      selectHighlighted,
      dropdownRef,
      hideDropdown,
    };
  },
};
  </script>
  
  <style scoped>
  /* Main container styling */
.routing-form-container {
  max-width: 1100px;
  margin: 1.5rem auto;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* Card styling with subtle shadows */
.card {
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
  background: #ffffff;
  border: none;
  overflow: hidden;
}

/* Clean header with better spacing */
.card-header {
  padding: 1.25rem 1.5rem;
  background: #f8fafc;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 600;
  color: #334155;
}

/* Card body with consistent padding */
.card-body {
  padding: 1.75rem;
  background: #ffffff;
}

/* Form layout and spacing */
form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.row {
  display: flex;
  flex-wrap: wrap;
  margin: 0 -0.75rem 1.5rem -0.75rem;
}

.row:last-child {
  margin-bottom: 0;
}

/* Improved column spacing */
.col-md-6, .col-md-4 {
  padding: 0 0.75rem;
  margin-bottom: 0.5rem;
}

/* Better form group spacing */
.form-group {
  margin-bottom: 1.5rem;
}

.form-group:last-child {
  margin-bottom: 0;
}

/* Clear label styling */
label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.95rem;
  color: #334155;
}

.text-danger {
  color: #ef4444;
  margin-left: 0.25rem;
}

/* Enhanced form controls */
.form-control {
  display: block;
  width: 100%;
  padding: 0.6rem 0.8rem;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #334155;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-control:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
  outline: none;
}

.form-control::placeholder {
  color: #94a3b8;
  opacity: 1;
}

/* Improved dropdown styling */
.dropdown-menu {
  margin-top: 0.25rem;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  z-index: 1050;
  overflow: hidden;
}

.dropdown-item {
  padding: 0.6rem 1rem;
  font-size: 0.95rem;
  transition: all 0.15s ease;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
}

.dropdown-item:last-child {
  border-bottom: none;
}

.dropdown-item:hover, 
.dropdown-item.active {
  background-color: #eff6ff;
  color: #1e40af;
}

/* Better button styling */
.btn {
  font-weight: 500;
  padding: 0.6rem 1rem;
  font-size: 0.95rem;
  border-radius: 6px;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  cursor: pointer;
}

.btn-primary {
  background-color: #3b82f6;
  border: 1px solid #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #2563eb;
  border-color: #2563eb;
  box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25);
}

.btn-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-secondary {
  background-color: #e2e8f0;
  border: 1px solid #e2e8f0;
  color: #334155;
}

.btn-secondary:hover {
  background-color: #cbd5e1;
  border-color: #cbd5e1;
  color: #1e293b;
}

/* Spacing utilities */
.mr-1 {
  margin-right: 0.25rem;
}

.mr-2 {
  margin-right: 0.5rem;
}

.mt-3 {
  margin-top: 1rem;
}

.mt-4 {
  margin-top: 1.5rem;
}

/* Section headers */
h3 {
  font-size: 1.2rem;
  font-weight: 600;
  color: #334155;
  margin-top: 0.5rem;
  margin-bottom: 0.75rem;
}

.text-muted {
  color: #64748b !important;
  font-size: 0.9rem;
}

/* Helper text and validation styling */
small.text-danger {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.8rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .card-header div {
    margin-top: 1rem;
    display: flex;
    width: 100%;
  }
  
  .btn {
    flex: 1;
  }
  
  .col-md-6, .col-md-4 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .form-group {
    margin-bottom: 1.25rem;
  }
}

/* Accessibility improvements */
.form-control:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 1px;
}

/* Ensure adequate touch targets for mobile */
@media (max-width: 576px) {
  .btn {
    padding: 0.75rem 1rem;
  }
  
  .form-control {
    padding: 0.75rem 0.8rem;
  }
  
  .dropdown-item {
    padding: 0.75rem 1rem;
  }
}
  </style>
