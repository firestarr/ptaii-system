<!-- src/views/purchasing/PurchaseRequisitionApproval.vue -->
<template>
    <div class="pr-approval-page">
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Memuat data...
      </div>
  
      <div v-else-if="error" class="error-message">
        <i class="fas fa-exclamation-circle"></i> {{ error }}
      </div>
  
      <div v-else>
        <div class="page-header">
          <h2 class="title">Persetujuan Permintaan Pembelian</h2>
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
                <div class="label">Catatan</div>
                <div class="value">{{ purchaseRequisition.notes || '-' }}</div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="info-card">
          <div class="card-header">
            <h3>Daftar Item</h3>
          </div>
          <div class="card-body">
            <div class="table-container">
              <table class="items-table">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>Kode Item</th>
                    <th>Nama Item</th>
                    <th>Jumlah</th>
                    <th>Satuan</th>
                    <th>Tanggal Dibutuhkan</th>
                    <th>Catatan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, index) in purchaseRequisition.lines" :key="index">
                    <td>{{ index + 1 }}</td>
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
          </div>
        </div>
  
        <div class="info-card">
          <div class="card-header">
            <h3>Persetujuan</h3>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label for="approval-notes">Catatan Persetujuan</label>
              <textarea 
                id="approval-notes" 
                v-model="approvalNotes" 
                rows="3" 
                class="form-control" 
                placeholder="Tambahkan catatan persetujuan (opsional)">
              </textarea>
            </div>
  
            <div class="radio-group">
              <div class="radio-label">Status Persetujuan:</div>
              <div class="radio-options">
                <div class="radio-option">
                  <input 
                    type="radio" 
                    id="approve" 
                    value="approved" 
                    v-model="approvalStatus"
                    :disabled="isSubmitting"
                  >
                  <label for="approve">Setujui</label>
                </div>
                <div class="radio-option">
                  <input 
                    type="radio" 
                    id="reject" 
                    value="rejected" 
                    v-model="approvalStatus"
                    :disabled="isSubmitting"
                  >
                  <label for="reject">Tolak</label>
                </div>
              </div>
            </div>
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
            class="btn"
            :class="{ 'btn-success': approvalStatus === 'approved', 'btn-danger': approvalStatus === 'rejected' }"
            @click="submitApproval"
            :disabled="!approvalStatus || isSubmitting"
          >
            <i class="fas fa-spinner fa-spin" v-if="isSubmitting"></i>
            {{ approvalStatus === 'approved' ? 'Setujui PR' : 'Tolak PR' }}
          </button>
        </div>
      </div>
  
      <div v-if="showConfirmationModal" class="modal">
        <div class="modal-backdrop" @click="showConfirmationModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>Konfirmasi Persetujuan</h2>
            <button class="close-btn" @click="showConfirmationModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Apakah Anda yakin ingin 
              <strong>{{ approvalStatus === 'approved' ? 'menyetujui' : 'menolak' }}</strong> 
              permintaan pembelian ini?
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
                :class="{ 'btn btn-success': approvalStatus === 'approved', 'btn btn-danger': approvalStatus === 'rejected' }"
                @click="confirmApproval"
              >
                {{ approvalStatus === 'approved' ? 'Setujui' : 'Tolak' }}
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
    name: 'PurchaseRequisitionApproval',
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
        approvalNotes: '',
        approvalStatus: null,
        isSubmitting: false,
        showConfirmationModal: false
      };
    },
    computed: {
      requesterName() {
        return this.purchaseRequisition.requester?.name || '-';
      }
    },
    created() {
      this.fetchPurchaseRequisition();
    },
    methods: {
      async fetchPurchaseRequisition() {
        this.loading = true;
        this.error = null;
        
        try {
          const response = await axios.get(`/purchase-requisitions/${this.id}`);
          this.purchaseRequisition = response.data.data;
          
          // Check if PR is in a valid state for approval
          if (this.purchaseRequisition.status !== 'pending') {
            this.error = `PR ini tidak dalam status 'pending'. Status saat ini: ${this.purchaseRequisition.status}`;
          }
        } catch (error) {
          console.error('Error fetching purchase requisition:', error);
          this.error = 'Gagal memuat data PR. Silakan coba lagi.';
        } finally {
          this.loading = false;
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
      
      submitApproval() {
        if (!this.approvalStatus) return;
        
        this.showConfirmationModal = true;
      },
      
      async confirmApproval() {
        this.isSubmitting = true;
        this.showConfirmationModal = false;
        
        try {
          await axios.patch(`/purchase-requisitions/${this.id}/status`, {
            status: this.approvalStatus,
            notes: this.approvalNotes
          });
          
          this.$router.push({
            path: `/purchasing/requisitions/${this.id}`,
            query: { 
              message: `PR berhasil ${this.approvalStatus === 'approved' ? 'disetujui' : 'ditolak'}`,
              type: 'success'
            }
          });
        } catch (error) {
          console.error('Error updating PR status:', error);
          this.error = `Gagal ${this.approvalStatus === 'approved' ? 'menyetujui' : 'menolak'} PR. Silakan coba lagi.`;
          this.isSubmitting = false;
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .pr-approval-page {
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
  
  .text-right {
    text-align: right;
  }
  
  .form-group {
    margin-bottom: 20px;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
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
  
  .radio-group {
    margin-bottom: 20px;
  }
  
  .radio-label {
    font-weight: 500;
    margin-bottom: 10px;
  }
  
  .radio-options {
    display: flex;
    gap: 24px;
  }
  
  .radio-option {
    display: flex;
    align-items: center;
  }
  
  .radio-option input {
    margin-right: 8px;
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
  
  .btn-success {
    background-color: #38761d;
    color: white;
  }
  
  .btn-success:hover:not(:disabled) {
    background-color: #2c5a16;
  }
  
  .btn-danger {
    background-color: #c62828;
    color: white;
  }
  
  .btn-danger:hover:not(:disabled) {
    background-color: #b51c1c;
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
    z-index: 60;
    overflow: hidden;
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
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
  }
  
  @media (max-width: 768px) {
    .info-grid {
      grid-template-columns: 1fr;
    }
    
    .radio-options {
      flex-direction: column;
      gap: 12px;
    }
    
    .action-buttons {
      flex-direction: column;
      gap: 12px;
    }
    
    .btn {
      width: 100%;
    }
  }
  </style>