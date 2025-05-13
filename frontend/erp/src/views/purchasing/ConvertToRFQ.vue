<!-- src/views/purchasing/ConvertToRFQ.vue -->
<template>
    <div class="convert-rfq-page">
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Memuat data...
      </div>
  
      <div v-else-if="error" class="error-message">
        <i class="fas fa-exclamation-circle"></i> {{ error }}
      </div>
  
      <div v-else>
        <div class="page-header">
          <h2 class="title">Konversi PR ke RFQ</h2>
          <div class="status-badge" :class="getStatusClass(purchaseRequisition.status)">
            {{ purchaseRequisition.status }}
          </div>
        </div>
  
        <div class="info-card">
          <div class="card-header">
            <h3>Informasi PR</h3>
          </div>
          <div class="card-body">
            <div class="info-grid">
              <div class="info-item">
                <div class="label">Nomor PR</div>
                <div class="value">{{ purchaseRequisition.pr_number }}</div>
              </div>
              <div class="info-item">
                <div class="label">Tanggal PR</div>
                <div class="value">{{ formatDate(purchaseRequisition.pr_date) }}</div>
              </div>
              <div class="info-item">
                <div class="label">Pemohon</div>
                <div class="value">{{ requesterName }}</div>
              </div>
              <div class="info-item">
                <div class="label">Status</div>
                <div class="value">{{ purchaseRequisition.status }}</div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="info-card">
          <div class="card-header">
            <h3>Detail RFQ Baru</h3>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group">
                <label for="rfq-date">Tanggal RFQ <span class="required">*</span></label>
                <input 
                  type="date" 
                  id="rfq-date" 
                  v-model="rfqData.rfq_date" 
                  class="form-control"
                  :class="{ 'is-invalid': errors.rfq_date }"
                  required
                >
                <div class="error-text" v-if="errors.rfq_date">{{ errors.rfq_date }}</div>
              </div>
  
              <div class="form-group">
                <label for="validity-date">Tanggal Berlaku</label>
                <input 
                  type="date" 
                  id="validity-date" 
                  v-model="rfqData.validity_date" 
                  class="form-control"
                  :class="{ 'is-invalid': errors.validity_date }"
                  min="rfqData.rfq_date"
                >
                <div class="error-text" v-if="errors.validity_date">{{ errors.validity_date }}</div>
              </div>
            </div>
  
            <div class="form-group">
              <label for="notes">Catatan</label>
              <textarea 
                id="notes" 
                v-model="rfqData.notes" 
                rows="3" 
                class="form-control"
                placeholder="Tambahkan catatan untuk RFQ ini">
              </textarea>
            </div>
  
            <div class="vendor-selection">
              <h4>Pilihan Vendor</h4>
              <div class="form-group">
                <div class="vendor-search">
                  <div class="search-input">
                    <i class="fas fa-search search-icon"></i>
                    <input 
                      type="text" 
                      placeholder="Cari vendor..." 
                      v-model="vendorSearchQuery"
                      @input="searchVendors"
                      class="form-control"
                    >
                  </div>
                  <button 
                    type="button" 
                    class="btn btn-outline-primary add-vendor-btn"
                    @click="showVendorsList = true"
                  >
                    <i class="fas fa-plus"></i> Tambah Vendor
                  </button>
                </div>
              </div>
  
              <div v-if="selectedVendors.length === 0" class="empty-vendors">
                <p>Belum ada vendor yang dipilih. Silakan tambahkan vendor untuk RFQ ini.</p>
              </div>
  
              <div v-else class="selected-vendors">
                <div 
                  v-for="vendor in selectedVendors" 
                  :key="vendor.vendor_id" 
                  class="vendor-item"
                >
                  <div class="vendor-info">
                    <div class="vendor-name">{{ vendor.name }}</div>
                    <div class="vendor-details">
                      <span class="vendor-code">{{ vendor.vendor_code }}</span>
                      <span v-if="vendor.contact_person" class="vendor-contact">
                        <i class="fas fa-user"></i> {{ vendor.contact_person }}
                      </span>
                      <span v-if="vendor.email" class="vendor-email">
                        <i class="fas fa-envelope"></i> {{ vendor.email }}
                      </span>
                    </div>
                  </div>
                  <button 
                    type="button" 
                    class="btn btn-icon remove-vendor-btn" 
                    @click="removeVendor(vendor)"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="info-card">
          <div class="card-header">
            <div class="header-with-actions">
              <h3>Item untuk RFQ</h3>
              <div class="header-actions">
                <div class="toggle-switch">
                  <input 
                    type="checkbox" 
                    id="select-all" 
                    v-model="selectAll" 
                    @change="toggleSelectAll"
                  >
                  <label for="select-all">Pilih Semua Item</label>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="table-container">
              <table class="items-table">
                <thead>
                  <tr>
                    <th style="width: 50px">Pilih</th>
                    <th>Kode Item</th>
                    <th>Nama Item</th>
                    <th>Jumlah</th>
                    <th>Satuan</th>
                    <th>Tanggal Dibutuhkan</th>
                    <th>Catatan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr 
                    v-for="(line, index) in purchaseRequisition.lines" 
                    :key="index"
                    :class="{ 'selected-row': selectedLines[index] }"
                  >
                    <td class="text-center">
                      <input 
                        type="checkbox" 
                        v-model="selectedLines[index]" 
                        @change="updateSelectAllState"
                      >
                    </td>
                    <td>{{ line.item.item_code }}</td>
                    <td>{{ line.item.name }}</td>
                    <td class="text-right">{{ formatNumber(line.quantity) }}</td>
                    <td>{{ line.unitOfMeasure?.name || '-' }}</td>
                    <td>{{ formatDate(line.required_date) }}</td>
                    <td>{{ line.notes || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="errors.lines" class="error-text mt-2">{{ errors.lines }}</div>
          </div>
        </div>
  
        <div class="action-buttons">
          <button 
            type="button" 
            class="btn btn-secondary" 
            @click="goBack"
            :disabled="isSubmitting"
          >
            Kembali
          </button>
          <button 
            type="button" 
            class="btn btn-primary" 
            @click="submitConversion"
            :disabled="isSubmitting || !isValidForm"
          >
            <i class="fas fa-spinner fa-spin" v-if="isSubmitting"></i>
            Buat RFQ
          </button>
        </div>
      </div>
  
      <!-- Vendor List Modal -->
      <div v-if="showVendorsList" class="modal">
        <div class="modal-backdrop" @click="showVendorsList = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Pilih Vendor</h2>
            <button class="close-btn" @click="showVendorsList = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="vendor-search-modal">
              <i class="fas fa-search search-icon"></i>
              <input 
                type="text" 
                placeholder="Cari vendor..." 
                v-model="vendorSearchQuery"
                @input="searchVendors"
                class="form-control"
              >
            </div>
  
            <div v-if="loadingVendors" class="loading-vendors">
              <i class="fas fa-spinner fa-spin"></i> Memuat vendor...
            </div>
  
            <div v-else-if="vendors.length === 0" class="no-vendors">
              <p>Tidak ada vendor yang ditemukan.</p>
            </div>
  
            <div v-else class="vendors-list">
              <div 
                v-for="vendor in filteredVendors" 
                :key="vendor.vendor_id"
                class="vendor-list-item"
                :class="{ 'selected': isVendorSelected(vendor) }"
                @click="toggleVendor(vendor)"
              >
                <div class="vendor-list-info">
                  <div class="vendor-list-name">{{ vendor.name }}</div>
                  <div class="vendor-list-details">
                    <span class="vendor-list-code">{{ vendor.vendor_code }}</span>
                    <span v-if="vendor.email" class="vendor-list-email">{{ vendor.email }}</span>
                  </div>
                </div>
                <div class="vendor-list-checkbox">
                  <i 
                    class="fas" 
                    :class="isVendorSelected(vendor) ? 'fa-check-circle' : 'fa-circle'"
                  ></i>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button 
              type="button" 
              class="btn btn-secondary" 
              @click="showVendorsList = false"
            >
              Batal
            </button>
            <button 
              type="button" 
              class="btn btn-primary" 
              @click="confirmVendors"
            >
              Pilih Vendor ({{ tempSelectedVendors.length }})
            </button>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
      <div v-if="showConfirmationModal" class="modal">
        <div class="modal-backdrop" @click="showConfirmationModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>Konfirmasi Konversi</h2>
            <button class="close-btn" @click="showConfirmationModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Apakah Anda yakin ingin mengkonversi permintaan pembelian ini menjadi RFQ?
            </p>
            <p>
              <strong>Vendor terpilih:</strong> {{ selectedVendors.length }} vendor<br>
              <strong>Item terpilih:</strong> {{ getSelectedItemsCount() }} item
            </p>
            
            <div class="form-actions">
              <button 
                type="button" 
                class="btn btn-secondary" 
                @click="showConfirmationModal = false"
              >
                Batal
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="confirmConversion"
              >
                Konfirmasi
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'ConvertToRFQ',
    props: {
      id: {
        type: [Number, String],
        required: true
      }
    },
    data() {
      return {
        purchaseRequisition: {},
        loading: true,
        error: null,
        rfqData: {
          rfq_date: new Date().toISOString().split('T')[0],
          validity_date: null,
          notes: '',
          lines: []
        },
        selectedLines: [],
        selectAll: false,
        vendors: [],
        loadingVendors: false,
        selectedVendors: [],
        tempSelectedVendors: [],
        vendorSearchQuery: '',
        isSubmitting: false,
        errors: {},
        showVendorsList: false,
        showConfirmationModal: false
      };
    },
    computed: {
      requesterName() {
        return this.purchaseRequisition.requester?.name || '-';
      },
      isValidForm() {
        // Check if at least one line is selected
        const hasSelectedLines = this.selectedLines.some(selected => selected);
        
        // Check if at least one vendor is selected
        const hasSelectedVendors = this.selectedVendors.length > 0;
        
        // Check if all required fields are filled
        const hasRequiredFields = !!this.rfqData.rfq_date;
        
        return hasSelectedLines && hasSelectedVendors && hasRequiredFields;
      },
      filteredVendors() {
        if (!this.vendorSearchQuery) {
          return this.vendors;
        }
        
        const searchTerm = this.vendorSearchQuery.toLowerCase();
        return this.vendors.filter(vendor => 
          vendor.name.toLowerCase().includes(searchTerm) || 
          vendor.vendor_code.toLowerCase().includes(searchTerm) ||
          (vendor.email && vendor.email.toLowerCase().includes(searchTerm)) ||
          (vendor.contact_person && vendor.contact_person.toLowerCase().includes(searchTerm))
        );
      }
    },
    created() {
      this.fetchPurchaseRequisition();
      this.fetchVendors();
    },
    methods: {
      async fetchPurchaseRequisition() {
        this.loading = true;
        this.error = null;
        
        try {
          const response = await axios.get(`/api/purchase-requisitions/${this.id}`);
          this.purchaseRequisition = response.data.data;
          
          // Initialize selectedLines array with false for each line
          this.selectedLines = Array(this.purchaseRequisition.lines.length).fill(false);
          
          // Check if PR is in a valid state for conversion
          if (this.purchaseRequisition.status !== 'approved') {
            this.error = `PR ini tidak dalam status 'approved'. Status saat ini: ${this.purchaseRequisition.status}`;
          }
        } catch (error) {
          console.error('Error fetching purchase requisition:', error);
          this.error = 'Gagal memuat data PR. Silakan coba lagi.';
        } finally {
          this.loading = false;
        }
      },
      
      async fetchVendors() {
        this.loadingVendors = true;
        
        try {
          const response = await axios.get('/api/vendors?is_active=true');
          this.vendors = response.data.data || [];
        } catch (error) {
          console.error('Error fetching vendors:', error);
          this.vendors = [];
        } finally {
          this.loadingVendors = false;
        }
      },
      
      getStatusClass(status) {
        switch (status) {
          case 'draft': return 'status-draft';
          case 'pending': return 'status-pending';
          case 'approved': return 'status-approved';
          case 'rejected': return 'status-rejected';
          case 'canceled': return 'status-canceled';
          default: return '';
        }
      },
      
      formatDate(dateString) {
        if (!dateString) return '-';
        
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('id-ID', options);
      },
      
      formatNumber(value) {
        if (value === null || value === undefined) return '-';
        
        return new Intl.NumberFormat('id-ID').format(value);
      },
      
      goBack() {
        this.$router.push(`/purchasing/requisitions/${this.id}`);
      },
      
      toggleSelectAll() {
        for (let i = 0; i < this.selectedLines.length; i++) {
          this.selectedLines[i] = this.selectAll;
        }
      },
      
      updateSelectAllState() {
        this.selectAll = this.selectedLines.every(selected => selected);
      },
      
      searchVendors() {
        // This is handled by the computed property filteredVendors
      },
      
      isVendorSelected(vendor) {
        return this.tempSelectedVendors.some(v => v.vendor_id === vendor.vendor_id);
      },
      
      toggleVendor(vendor) {
        if (this.isVendorSelected(vendor)) {
          this.tempSelectedVendors = this.tempSelectedVendors.filter(v => v.vendor_id !== vendor.vendor_id);
        } else {
          this.tempSelectedVendors.push(vendor);
        }
      },
      
      confirmVendors() {
        this.selectedVendors = [...this.tempSelectedVendors];
        this.showVendorsList = false;
      },
      
      removeVendor(vendor) {
        this.selectedVendors = this.selectedVendors.filter(v => v.vendor_id !== vendor.vendor_id);
        this.tempSelectedVendors = this.tempSelectedVendors.filter(v => v.vendor_id !== vendor.vendor_id);
      },
      
      getSelectedItemsCount() {
        return this.selectedLines.filter(selected => selected).length;
      },
      
      submitConversion() {
        this.errors = {};
        
        // Validate form
        if (!this.rfqData.rfq_date) {
          this.errors.rfq_date = 'Tanggal RFQ wajib diisi';
        }
        
        if (this.rfqData.validity_date && this.rfqData.validity_date < this.rfqData.rfq_date) {
          this.errors.validity_date = 'Tanggal berlaku harus setelah atau sama dengan tanggal RFQ';
        }
        
        if (this.selectedVendors.length === 0) {
          this.errors.vendors = 'Pilih minimal satu vendor';
        }
        
        if (!this.selectedLines.some(selected => selected)) {
          this.errors.lines = 'Pilih minimal satu item untuk RFQ';
        }
        
        if (Object.keys(this.errors).length > 0) {
          return;
        }
        
        // Show confirmation modal
        this.showConfirmationModal = true;
      },
      
      async confirmConversion() {
        this.isSubmitting = true;
        this.showConfirmationModal = false;
        
        // Prepare the RFQ data
        const lines = [];
        this.purchaseRequisition.lines.forEach((line, index) => {
          if (this.selectedLines[index]) {
            lines.push({
              item_id: line.item_id,
              quantity: line.quantity,
              uom_id: line.uom_id,
              required_date: line.required_date
            });
          }
        });
        
        const requestData = {
          pr_id: this.purchaseRequisition.pr_id,
          rfq_date: this.rfqData.rfq_date,
          validity_date: this.rfqData.validity_date,
          notes: this.rfqData.notes,
          vendors: this.selectedVendors.map(v => v.vendor_id),
          lines: lines
        };
        
        try {
          const response = await axios.post('/api/request-for-quotations/from-requisition', requestData);
          
          // Redirect to the RFQ list with success message
          this.$router.push({
            path: '/purchasing/rfqs',
            query: { 
              message: 'RFQ berhasil dibuat dari PR',
              type: 'success'
            }
          });
        } catch (error) {
          console.error('Error creating RFQ:', error);
          
          if (error.response && error.response.data && error.response.data.errors) {
            this.errors = error.response.data.errors;
          } else {
            this.error = 'Gagal membuat RFQ. Silakan coba lagi.';
          }
          
          this.isSubmitting = false;
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .convert-rfq-page {
    padding: 20px;
    max-width: 100%;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
  }
  
  .title {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
  }
  
  .status-badge {
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
  }
  
  .status-draft {
    background-color: var(--gray-200);
    color: var(--gray-700);
  }
  
  .status-pending {
    background-color: #ffecb3;
    color: #8b6d00;
  }
  
  .status-approved {
    background-color: #d0f0c0;
    color: #38761d;
  }
  
  .status-rejected {
    background-color: #ffcdd2;
    color: #c62828;
  }
  
  .status-canceled {
    background-color: var(--gray-300);
    color: var(--gray-600);
  }
  
  .info-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
    overflow: hidden;
  }
  
  .card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .header-with-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .card-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
  }
  
  .card-body {
    padding: 20px;
  }
  
  .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
  }
  
  .info-item {
    margin-bottom: 8px;
  }
  
  .label {
    font-size: 0.875rem;
    color: var(--gray-500);
    margin-bottom: 4px;
  }
  
  .value {
    font-size: 1rem;
    color: var(--gray-800);
  }
  
  .form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 16px;
  }
  
  .form-group {
    flex: 1 1 300px;
    margin-bottom: 16px;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
  }
  
  .required {
    color: #e53e3e;
  }
  
  .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  
  .form-control:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .is-invalid {
    border-color: #e53e3e;
  }
  
  .error-text {
    color: #e53e3e;
    font-size: 0.875rem;
    margin-top: 4px;
  }
  
  .mt-2 {
    margin-top: 8px;
  }
  
  .table-container {
    overflow-x: auto;
  }
  
  .items-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .items-table th,
  .items-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .items-table th {
    background-color: var(--gray-50);
    font-weight: 600;
    color: var(--gray-700);
  }
  
  .items-table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .selected-row {
    background-color: #e6f2ff !important;
  }
  
  .text-right {
    text-align: right;
  }
  
  .text-center {
    text-align: center;
  }
  
  .toggle-switch {
    display: flex;
    align-items: center;
  }
  
  .toggle-switch input[type="checkbox"] {
    margin-right: 8px;
  }
  
  .vendor-selection {
    margin-top: 20px;
  }
  
  .vendor-selection h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 12px;
  }
  
  .vendor-search {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
  }
  
  .search-input {
    position: relative;
    flex: 1;
  }
  
  .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .search-input input {
    padding-left: 36px;
  }
  
  .add-vendor-btn {
    white-space: nowrap;
  }
  
  .empty-vendors {
    padding: 20px;
    text-align: center;
    background-color: var(--gray-50);
    border-radius: 4px;
    color: var(--gray-600);
  }
  
  .selected-vendors {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  
  .vendor-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background-color: var(--gray-50);
    border-radius: 4px;
    border: 1px solid var(--gray-200);
  }
  
  .vendor-info {
    flex: 1;
  }
  
  .vendor-name {
    font-weight: 500;
    margin-bottom: 4px;
  }
  
  .vendor-details {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.875rem;
    color: var(--gray-600);
  }
  
  .remove-vendor-btn {
    color: var(--gray-500);
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background-color 0.2s;
  }
  
  .remove-vendor-btn:hover {
    background-color: #f8d7da;
    color: #721c24;
  }
  
  .action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
  }
  
  .btn {
    padding: 10px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: background-color 0.2s;
  }
  
  .btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
  }
  
  .btn-secondary:hover:not(:disabled) {
    background-color: var(--gray-300);
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-outline-primary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
  }
  
  .btn-outline-primary:hover {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-icon {
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .loading-indicator,
  .error-message {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 0;
    font-size: 1rem;
  }
  
  .loading-indicator i,
  .error-message i {
    margin-right: 8px;
  }
  
  .error-message {
    color: #c62828;
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
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 50;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 600px;
    z-index: 60;
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
  }
  
  .modal-sm {
    max-width: 400px;
  }
  
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .modal-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
  }
  
  .close-btn {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
  }
  
  .modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
  }
  
  .modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }
  
  .vendor-search-modal {
    position: relative;
    margin-bottom: 16px;
  }
  
  .loading-vendors,
  .no-vendors {
    padding: 20px;
    text-align: center;
    color: var(--gray-600);
  }
  
  .vendors-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--gray-200);
    border-radius: 4px;
  }
  
  .vendor-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-200);
    cursor: pointer;
    transition: background-color 0.2s;
  }
  
  .vendor-list-item:last-child {
    border-bottom: none;
  }
  
  .vendor-list-item:hover {
    background-color: var(--gray-50);
  }
  
  .vendor-list-item.selected {
    background-color: #e6f2ff;
  }
  
  .vendor-list-info {
    flex: 1;
  }
  
  .vendor-list-name {
    font-weight: 500;
    margin-bottom: 4px;
  }
  
  .vendor-list-details {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.875rem;
    color: var(--gray-600);
  }
  
  .vendor-list-checkbox {
    color: var(--gray-400);
  }
  
  .vendor-list-item.selected .vendor-list-checkbox {
    color: var(--primary-color);
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
  }
  
  @media (max-width: 768px) {
    .form-row {
      flex-direction: column;
      gap: 0;
    }
    
    .info-grid {
      grid-template-columns: 1fr;
    }
    
    .vendor-search {
      flex-direction: column;
    }
    
    .vendor-details {
      flex-direction: column;
      gap: 4px;
    }
    
    .action-buttons {
      flex-direction: column;
      gap: 12px;
    }
    
    .btn {
      width: 100%;
    }
    
    .modal-content {
      width: 95%;
      max-height: 80vh;
    }
  }
  </style>