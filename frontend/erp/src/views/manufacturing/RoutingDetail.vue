<!-- src/views/manufacturing/RoutingDetail.vue -->
<template>
    <div class="routing-detail-container">
      <!-- Header with actions -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title">Detail Routing</h1>
        <div class="action-buttons">
          <router-link to="/manufacturing/routings" class="btn btn-secondary mr-2">
            <i class="fas fa-list mr-1"></i> Daftar Routing
          </router-link>
          <router-link :to="`/manufacturing/routings/${routingId}/edit`" class="btn btn-primary mr-2">
            <i class="fas fa-edit mr-1"></i> Edit Routing
          </router-link>
          <button @click="confirmDelete" class="btn btn-danger">
            <i class="fas fa-trash-alt mr-1"></i> Hapus
          </button>
        </div>
      </div>
  
      <!-- Loading indicator -->
      <div v-if="isLoading" class="text-center py-5">
        <i class="fas fa-spinner fa-spin fa-2x"></i>
        <p class="mt-2">Memuat data routing...</p>
      </div>
  
      <div v-else>
        <!-- Routing Information Card -->
        <div class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Informasi Routing</h2>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <table class="table table-borderless detail-table">
                  <tbody>
                    <tr>
                      <th width="40%">Kode Routing:</th>
                      <td>{{ routing.routing_code }}</td>
                    </tr>
                    <tr>
                      <th>Revisi:</th>
                      <td>{{ routing.revision }}</td>
                    </tr>
                    <tr>
                      <th>Status:</th>
                      <td>
                        <span
                          class="badge"
                          :class="{
                            'badge-success': routing.status === 'Active',
                            'badge-warning': routing.status === 'Draft',
                            'badge-secondary': routing.status === 'Obsolete'
                          }"
                        >
                          {{ routing.status }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-borderless detail-table">
                  <tbody>
                    <tr>
                      <th width="40%">Produk:</th>
                      <td>
                        {{ routing.item ? `${routing.item.name} (${routing.item.item_code})` : '-' }}
                      </td>
                    </tr>
                    <tr>
                      <th>Tanggal Efektif:</th>
                      <td>{{ formatDate(routing.effective_date) }}</td>
                    </tr>
                    <tr>
                      <th>Total Operasi:</th>
                      <td>{{ operations.length }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Operations Card -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title">Operasi Routing</h2>
            <button @click="showOperationModal = true" class="btn btn-primary">
              <i class="fas fa-plus mr-1"></i> Tambah Operasi
            </button>
          </div>
          <div class="card-body p-0">
          <DataTable
            :columns="operationColumns"
            :items="sortedOperations"
            :is-loading="isLoadingOperations"
            empty-title="Belum ada operasi"
            empty-message="Tambahkan operasi untuk routing ini menggunakan tombol 'Tambah Operasi'"
            initial-sort-key="sequence"
            initial-sort-order="asc"
          >
            <!-- Work Center column -->
            <!-- Removed scoped slot to allow default rendering of string -->
  
              <!-- Run Time column -->
              <template #run_time="{ value }">
                {{ value }} {{ getUnitName(value, item) }}
              </template>
  
              <!-- Setup Time column -->
              <template #setup_time="{ value, item }">
                {{ value }} {{ getUnitName(value, item) }}
              </template>
  
              <!-- Cost columns -->
              <template #labor_cost="{ value }">
                {{ formatCurrency(value) }}
              </template>
  
              <template #overhead_cost="{ value }">
                {{ formatCurrency(value) }}
              </template>
  
              <!-- Actions column -->
              <template #actions="{ item }">
                <div class="d-flex gap-2 justify-content-end">
                  <button
                    @click="editOperation(item)"
                    class="btn btn-sm btn-primary"
                    title="Edit"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    @click="confirmDeleteOperation(item)"
                    class="btn btn-sm btn-danger"
                    title="Hapus"
                  >
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </div>
              </template>
            </DataTable>
          </div>
        </div>
      </div>
  
      <!-- Operation Form Modal -->
      <div v-if="showOperationModal" class="modal">
        <div class="modal-backdrop" @click="cancelOperationForm"></div>
        <div class="modal-content modal-lg">
          <div class="modal-header">
            <h2>{{ selectedOperation ? 'Edit Operasi' : 'Tambah Operasi Baru' }}</h2>
            <button class="close-btn" @click="cancelOperationForm">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveOperation">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="workcenter_id">Work Center <span class="text-danger">*</span></label>
                    <select
                      id="workcenter_id"
                      v-model="operationForm.workcenter_id"
                      class="form-control"
                      required
                    >
                      <option value="" disabled>-- Pilih Work Center --</option>
                      <option
                        v-for="wc in workCenters"
                        :key="wc.workcenter_id"
                        :value="wc.workcenter_id"
                      >
                        {{ wc.name }} ({{ wc.code }})
                      </option>
                    </select>
                    <small v-if="operationErrors.workcenter_id" class="text-danger">
                      {{ operationErrors.workcenter_id[0] }}
                    </small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="operation_name">Nama Operasi <span class="text-danger">*</span></label>
                    <input
                      id="operation_name"
                      v-model="operationForm.operation_name"
                      type="text"
                      class="form-control"
                      required
                      maxlength="100"
                    />
                    <small v-if="operationErrors.operation_name" class="text-danger">
                      {{ operationErrors.operation_name[0] }}
                    </small>
                  </div>
                </div>
              </div>
  
              <div class="row mt-3">
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="sequence">Urutan <span class="text-danger">*</span></label>
                    <input
                      id="sequence"
                      v-model.number="operationForm.sequence"
                      type="number"
                      min="1"
                      class="form-control"
                      required
                    />
                    <small v-if="operationErrors.sequence" class="text-danger">
                      {{ operationErrors.sequence[0] }}
                    </small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="uom_id">Satuan Waktu <span class="text-danger">*</span></label>
                    <select id="uom_id" v-model="operationForm.uom_id" class="form-control" required>
                      <option value="" disabled>-- Pilih Satuan --</option>
                      <option
                        v-for="uom in unitOfMeasures"
                        :key="uom.uom_id"
                        :value="uom.uom_id"
                      >
                        {{ uom.name }} ({{ uom.symbol }})
                      </option>
                    </select>
                    <small v-if="operationErrors.uom_id" class="text-danger">
                      {{ operationErrors.uom_id[0] }}
                    </small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="setup_time">Waktu Setup <span class="text-danger">*</span></label>
                    <input
                      id="setup_time"
                      v-model.number="operationForm.setup_time"
                      type="number"
                      min="0"
                      step="0.1"
                      class="form-control"
                      required
                    />
                    <small v-if="operationErrors.setup_time" class="text-danger">
                      {{ operationErrors.setup_time[0] }}
                    </small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="run_time">Waktu Proses <span class="text-danger">*</span></label>
                    <input
                      id="run_time"
                      v-model.number="operationForm.run_time"
                      type="number"
                      min="0"
                      step="0.1"
                      class="form-control"
                      required
                    />
                    <small v-if="operationErrors.run_time" class="text-danger">
                      {{ operationErrors.run_time[0] }}
                    </small>
                  </div>
                </div>
              </div>
  
              <div class="row mt-3">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="labor_cost">Biaya Tenaga Kerja <span class="text-danger">*</span></label>
                    <input
                      id="labor_cost"
                      v-model.number="operationForm.labor_cost"
                      type="number"
                      min="0"
                      step="0.01"
                      class="form-control"
                      required
                    />
                    <small v-if="operationErrors.labor_cost" class="text-danger">
                      {{ operationErrors.labor_cost[0] }}
                    </small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="overhead_cost">Biaya Overhead <span class="text-danger">*</span></label>
                    <input
                      id="overhead_cost"
                      v-model.number="operationForm.overhead_cost"
                      type="number"
                      min="0"
                      step="0.01"
                      class="form-control"
                      required
                    />
                    <small v-if="operationErrors.overhead_cost" class="text-danger">
                      {{ operationErrors.overhead_cost[0] }}
                    </small>
                  </div>
                </div>
              </div>
  
              <div class="form-actions mt-4">
                <button type="button" class="btn btn-secondary mr-2" @click="cancelOperationForm">
                  Batal
                </button>
                <button type="submit" class="btn btn-primary" :disabled="isSavingOperation">
                  {{ isSavingOperation ? 'Menyimpan...' : 'Simpan' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal for Delete Routing -->
      <ConfirmationModal
        v-if="showDeleteModal"
        title="Hapus Routing"
        :message="`Anda yakin ingin menghapus routing <strong>${routing.routing_code}</strong>?<br>Tindakan ini tidak dapat dibatalkan.`"
        confirm-button-text="Hapus"
        confirm-button-class="btn btn-danger"
        @confirm="deleteRouting"
        @close="showDeleteModal = false"
      />
  
      <!-- Confirmation Modal for Delete Operation -->
      <ConfirmationModal
        v-if="showDeleteOperationModal"
        title="Hapus Operasi"
        :message="`Anda yakin ingin menghapus operasi <strong>${selectedOperation?.operation_name}</strong>?<br>Tindakan ini tidak dapat dibatalkan.`"
        confirm-button-text="Hapus"
        confirm-button-class="btn btn-danger"
        @confirm="deleteOperation"
        @close="showDeleteOperationModal = false"
      />
    </div>
  </template>
  
  <script>
  import { ref, reactive, computed, onMounted } from 'vue';
  import { useRouter, useRoute } from 'vue-router';
  import axios from 'axios';
  
  export default {
    name: 'RoutingDetail',
    setup() {
      const router = useRouter();
      const route = useRoute();
      const routingId = computed(() => route.params.id);
      
      const isLoading = ref(true);
      const isLoadingOperations = ref(true);
      const routing = ref({});
      const operations = ref([]);
      const workCenters = ref([]);
      const unitOfMeasures = ref([]);
      
      const selectedOperation = ref(null);
      const showOperationModal = ref(false);
      const isSavingOperation = ref(false);
      const operationErrors = ref({});
      
      const showDeleteModal = ref(false);
      const showDeleteOperationModal = ref(false);
  
      // Initial operation form values
      const operationForm = reactive({
        workcenter_id: '',
        operation_name: '',
        sequence: 10,
        setup_time: 0,
        run_time: 0,
        uom_id: '',
        labor_cost: 0,
        overhead_cost: 0,
      });
  
      // Operation table columns
      const operationColumns = [
        { key: 'sequence', label: 'Urutan', sortable: true },
        { key: 'operation_name', label: 'Nama Operasi', sortable: true },
        { key: 'work_center_name', label: 'Work Center' },
        { key: 'setup_time', label: 'Waktu Setup' },
        { key: 'run_time', label: 'Waktu Proses' },
        { key: 'labor_cost', label: 'Biaya Tenaga Kerja' },
        { key: 'overhead_cost', label: 'Biaya Overhead' },
      ];
  
      // Sort operations by sequence
      const sortedOperations = computed(() => {
        return [...operations.value].sort((a, b) => a.sequence - b.sequence);
      });
  
      // Format date
      const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          day: '2-digit',
          month: 'short',
          year: 'numeric',
        });
      };
  
      // Format currency
      const formatCurrency = (value) => {
        if (value === null || value === undefined) return '-';
        return new Intl.NumberFormat('id-ID', {
          style: 'currency',
          currency: 'IDR',
          minimumFractionDigits: 0,
          maximumFractionDigits: 0,
        }).format(value);
      };
  
      // Get unit of measure name based on ID
      const getUnitName = (value, item) => {
        if (!item || !item.unitOfMeasure) return '';
        return item.unitOfMeasure.symbol || '';
      };
  
      // Load routing data
      const loadRouting = async () => {
        isLoading.value = true;
        try {
          const response = await axios.get(`/routings/${routingId.value}`);
          routing.value = response.data.data;
        } catch (error) {
          console.error('Error loading routing:', error);
          alert('Gagal memuat data routing. Silakan coba lagi.');
        } finally {
          isLoading.value = false;
        }
      };
  
      // Load operations
      const loadOperations = async () => {
        isLoadingOperations.value = true;
        try {
          const response = await axios.get(`/routings/${routingId.value}/operations`);
          console.log('Operations data:', response.data.data); // Debug log to check workCenter presence
          // Map operations to add work_center_name property as string for DataTable column
          operations.value = response.data.data.map(op => ({
            ...op,
            work_center_name: op.work_center ? op.work_center.name : '-'
          }));
        } catch (error) {
          console.error('Error loading operations:', error);
        } finally {
          isLoadingOperations.value = false;
        }
      };
  
      // Load work centers for dropdown
      const loadWorkCenters = async () => {
        try {
          const response = await axios.get('/work-centers');
          workCenters.value = response.data.data;
        } catch (error) {
          console.error('Error loading work centers:', error);
        }
      };
  
      // Load units of measure for dropdown
      const loadUnitOfMeasures = async () => {
        try {
          const response = await axios.get('/uoms');
          unitOfMeasures.value = response.data.data;
        } catch (error) {
          console.error('Error loading units of measure:', error);
        }
      };
  
      // Edit operation
      const editOperation = (operation) => {
        selectedOperation.value = operation;
        
        // Copy operation data to form
        operationForm.workcenter_id = operation.workcenter_id;
        operationForm.operation_name = operation.operation_name;
        operationForm.sequence = operation.sequence;
        operationForm.setup_time = operation.setup_time;
        operationForm.run_time = operation.run_time;
        operationForm.uom_id = operation.uom_id;
        operationForm.labor_cost = operation.labor_cost;
        operationForm.overhead_cost = operation.overhead_cost;
        
        showOperationModal.value = true;
      };
  
      // Reset operation form
      const resetOperationForm = () => {
        selectedOperation.value = null;
        operationForm.workcenter_id = '';
        operationForm.operation_name = '';
        operationForm.sequence = operations.value.length > 0 
          ? Math.max(...operations.value.map(o => o.sequence)) + 10 
          : 10;
        operationForm.setup_time = 0;
        operationForm.run_time = 0;
        operationForm.uom_id = '';
        operationForm.labor_cost = 0;
        operationForm.overhead_cost = 0;
        operationErrors.value = {};
      };
  
      // Cancel operation form
      const cancelOperationForm = () => {
        showOperationModal.value = false;
        resetOperationForm();
      };
  
      // Save operation
      const saveOperation = async () => {
        isSavingOperation.value = true;
        operationErrors.value = {};
        
        try {
            if (selectedOperation.value) {
            // Update existing operation
            await axios.put(
                `/routings/${routingId.value}/operations/${selectedOperation.value.operation_id}`,
                operationForm
            );
            } else {
            // Create new operation
            await axios.post(
                `/routings/${routingId.value}/operations`,
                operationForm
            );
            }
          
            await loadOperations(); // Reload operations
            showOperationModal.value = false;
            resetOperationForm();
        } catch (error) {
            console.error('Error saving operation:', error);
            
            if (error.response && error.response.data && error.response.data.errors) {
            operationErrors.value = error.response.data.errors;
            } else {
            alert('Gagal menyimpan operasi. Silakan coba lagi.');
            }
        } finally {
            isSavingOperation.value = false;
        }
        };
  
      // Confirm delete routing
      const confirmDelete = () => {
        showDeleteModal.value = true;
      };
  
      // Delete routing
      const deleteRouting = async () => {
        try {
          await axios.delete(`/routings/${routingId.value}`);
          router.push('/manufacturing/routings');
        } catch (error) {
          console.error('Error deleting routing:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            alert(error.response.data.message);
          } else {
            alert('Gagal menghapus routing. Silakan coba lagi.');
          }
          
          showDeleteModal.value = false;
        }
      };
  
      // Confirm delete operation
      const confirmDeleteOperation = (operation) => {
        selectedOperation.value = operation;
        showDeleteOperationModal.value = true;
      };
  
      // Delete operation
      const deleteOperation = async () => {
        try {
          await axios.delete(
            `/routings/${routingId.value}/operations/${selectedOperation.value.operation_id}`
          );
          await loadOperations(); // Reload operations
          showDeleteOperationModal.value = false;
        } catch (error) {
          console.error('Error deleting operation:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            alert(error.response.data.message);
          } else {
            alert('Gagal menghapus operasi. Silakan coba lagi.');
          }
          
          showDeleteOperationModal.value = false;
        }
      };
  
      // Load data on component mount
      onMounted(() => {
        loadRouting();
        loadOperations();
        loadWorkCenters();
        loadUnitOfMeasures();
      });
  
      return {
        routingId,
        isLoading,
        isLoadingOperations,
        routing,
        operations,
        sortedOperations,
        operationColumns,
        operationForm,
        operationErrors,
        selectedOperation,
        showOperationModal,
        isSavingOperation,
        workCenters,
        unitOfMeasures,
        showDeleteModal,
        showDeleteOperationModal,
        formatDate,
        formatCurrency,
        getUnitName,
        editOperation,
        cancelOperationForm,
        saveOperation,
        confirmDelete,
        deleteRouting,
        confirmDeleteOperation,
        deleteOperation,
      };
    },
  };
  </script>
  
  <style scoped>
  /* Container styling */
.routing-detail-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 1.5rem;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #334155;
}

/* Header styling */
.page-title {
  font-size: 1.75rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.d-flex {
  display: flex;
}

.justify-content-between {
  justify-content: space-between;
}

.align-items-center {
  align-items: center;
}

.mb-3 {
  margin-bottom: 1.5rem;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Button styling */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.65rem 1rem;
  font-size: 0.95rem;
  font-weight: 500;
  line-height: 1.5;
  border-radius: 0.5rem;
  border: 1px solid transparent;
  transition: all 0.2s ease;
  cursor: pointer;
  text-decoration: none;
  gap: 0.5rem;
}

.btn-primary {
  background-color: #3b82f6;
  border-color: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background-color: #2563eb;
  border-color: #2563eb;
  box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25);
}

.btn-secondary {
  background-color: #f1f5f9;
  border-color: #cbd5e1;
  color: #475569;
}

.btn-secondary:hover {
  background-color: #e2e8f0;
  color: #334155;
}

.btn-danger {
  background-color: #ef4444;
  border-color: #ef4444;
  color: white;
}

.btn-danger:hover {
  background-color: #dc2626;
  border-color: #dc2626;
  box-shadow: 0 2px 4px rgba(220, 38, 38, 0.25);
}

.btn-sm {
  padding: 0.4rem 0.7rem;
  font-size: 0.85rem;
}

.mr-1 {
  margin-right: 0.25rem;
}

.mr-2 {
  margin-right: 0.5rem;
}

/* Card styling */
.card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 8px rgba(0, 0, 0, 0.08);
  margin-bottom: 1.5rem;
  overflow: hidden;
  border: none;
}

.card-header {
  padding: 1.25rem 1.5rem;
  background-color: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.card-body {
  padding: 1.5rem;
}

.p-0 {
  padding: 0;
}

/* Badge styling */
.badge {
  display: inline-block;
  padding: 0.35em 0.65em;
  font-size: 0.75em;
  font-weight: 600;
  line-height: 1;
  text-align: center;
  white-space: nowrap;
  vertical-align: baseline;
  border-radius: 0.25rem;
}

.badge-success {
  background-color: #22c55e;
  color: white;
}

.badge-warning {
  background-color: #f59e0b;
  color: white;
}

.badge-secondary {
  background-color: #94a3b8;
  color: white;
}

/* Detail table styling */
.detail-table {
  width: 100%;
  margin-bottom: 0;
}

.table {
  width: 100%;
  margin-bottom: 1rem;
  border-collapse: collapse;
}

.table-borderless th,
.table-borderless td {
  border: none;
}

.detail-table th {
  font-weight: 600;
  color: #64748b;
  padding: 0.75rem 0;
  vertical-align: top;
}

.detail-table td {
  color: #334155;
  padding: 0.75rem 0;
  vertical-align: top;
}

.row {
  display: flex;
  flex-wrap: wrap;
  margin-right: -0.75rem;
  margin-left: -0.75rem;
}

.col-md-6 {
  flex: 0 0 50%;
  max-width: 50%;
  padding-right: 0.75rem;
  padding-left: 0.75rem;
}

.col-md-3 {
  flex: 0 0 25%;
  max-width: 25%;
  padding-right: 0.75rem;
  padding-left: 0.75rem;
}

/* Loading indicator */
.text-center {
  text-align: center;
}

.py-5 {
  padding-top: 3rem;
  padding-bottom: 3rem;
}

.mt-2 {
  margin-top: 0.5rem;
}

.fa-spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* DataTable styling */
.table-responsive {
  overflow-x: auto;
  margin-bottom: 1rem;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Modal styling */
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1050;
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
  background-color: rgba(15, 23, 42, 0.5);
  z-index: 1051;
  backdrop-filter: blur(2px);
}

.modal-content {
  background-color: white;
  border-radius: 0.75rem;
  box-shadow: 0 4px 25px rgba(0, 0, 0, 0.15);
  width: 100%;
  max-width: 700px;
  z-index: 1052;
  max-height: 90vh;
  overflow-y: auto;
  animation: modal-appear 0.25s ease;
  border: none;
}

@keyframes modal-appear {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.modal-lg {
  max-width: 900px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 600;
  color: #1e293b;
}

.modal-body {
  padding: 1.5rem;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  transition: background-color 0.2s ease;
}

.close-btn:hover {
  background-color: #f1f5f9;
  color: #334155;
}

/* Form styling */
.form-group {
  margin-bottom: 1.25rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.95rem;
  color: #475569;
}

.text-danger {
  color: #ef4444;
}

.form-control {
  display: block;
  width: 100%;
  padding: 0.65rem 0.85rem;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #334155;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #cbd5e1;
  border-radius: 0.5rem;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
  border-color: #3b82f6;
  outline: 0;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}

select.form-control {
  appearance: none;
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 1em;
  padding-right: 2.5rem;
}

small.text-danger {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.8rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
  gap: 0.75rem;
}

.mt-3 {
  margin-top: 1rem;
}

.mt-4 {
  margin-top: 1.5rem;
}

/* Gap utilities */
.gap-2 {
  gap: 0.5rem;
}

/* DataTable action buttons container */
.justify-content-end {
  justify-content: flex-end;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .d-flex.justify-content-between.align-items-center {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .action-buttons {
    margin-top: 1rem;
    width: 100%;
  }
  
  .btn {
    flex: 1;
  }
  
  .row {
    flex-direction: column;
  }
  
  .col-md-6, .col-md-3 {
    flex: 0 0 100%;
    max-width: 100%;
    margin-bottom: 0.5rem;
  }
  
  .modal-content {
    width: 95%;
    max-height: 85vh;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .form-actions .btn {
    width: 100%;
  }
}

/* Touch optimizations for smaller screens */
@media (max-width: 576px) {
  .routing-detail-container {
    padding: 1rem;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
  
  .btn {
    padding: 0.75rem 1rem;
  }
  
  .card-header {
    padding: 1rem;
  }
  
  .card-body {
    padding: 1rem;
  }
  
  .modal-header, .modal-body {
    padding: 1rem;
  }
}
  </style>