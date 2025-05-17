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
  .routing-detail-container {
    max-width: 1200px;
    margin: 0 auto;
  }
  
  .detail-table th {
    font-weight: 600;
    color: var(--gray-600);
  }
  
  .detail-table td {
    color: var(--gray-800);
  }
  
  /* Modal Styles */
  .modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
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
    z-index: 1001;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
    width: 100%;
    max-width: 700px;
    z-index: 1002;
    max-height: 90vh;
    overflow-y: auto;
  }
  
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
  }
  
  .modal-body {
    padding: 1.5rem;
  }
  
  .close-btn {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: var(--gray-500);
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .modal-lg {
    max-width: 900px;
  }
  </style>