<!-- src/views/purchasing/RFQSend.vue -->
<template>
    <div class="rfq-send-container">
      <div class="page-header">
        <h1>Send RFQ to Vendors</h1>
        <div class="header-actions">
          <router-link :to="`/purchasing/rfqs/${rfqId}`" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to RFQ
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Loading...
      </div>
  
      <div v-else-if="!rfq" class="error-state">
        <div class="error-icon">
          <i class="fas fa-exclamation-circle"></i>
        </div>
        <h3>Request for Quotation Not Found</h3>
        <p>The RFQ you are trying to send does not exist or has been deleted.</p>
        <router-link to="/purchasing/rfqs" class="btn btn-primary">
          Go Back to RFQ List
        </router-link>
      </div>
  
      <div v-else-if="rfq.status !== 'draft'" class="error-state">
        <div class="error-icon">
          <i class="fas fa-ban"></i>
        </div>
        <h3>RFQ Cannot Be Sent</h3>
        <p>This RFQ has already been sent or is in a state that cannot be modified.</p>
        <router-link :to="`/purchasing/rfqs/${rfqId}`" class="btn btn-primary">
          View RFQ Details
        </router-link>
      </div>
  
      <div v-else class="rfq-send-content">
        <div class="detail-card">
          <div class="card-header">
            <h2 class="card-title">RFQ Information</h2>
            <div class="status-badge status-draft">
              {{ capitalizeFirstLetter(rfq.status) }}
            </div>
          </div>
  
          <div class="card-body">
            <div class="detail-row">
              <div class="detail-group">
                <label>RFQ Number</label>
                <div class="detail-value">{{ rfq.rfq_number }}</div>
              </div>
              
              <div class="detail-group">
                <label>RFQ Date</label>
                <div class="detail-value">{{ formatDate(rfq.rfq_date) }}</div>
              </div>
              
              <div class="detail-group">
                <label>Validity Date</label>
                <div class="detail-value">{{ formatDate(rfq.validity_date) || 'N/A' }}</div>
              </div>
            </div>
            
            <div class="detail-row">
              <div class="detail-group full-width">
                <label>Items</label>
                <div class="item-chips">
                  <div class="item-chip" v-for="line in rfq.lines" :key="line.line_id">
                    <span class="item-code">{{ line.item.item_code }}</span>
                    <span class="item-quantity">{{ formatNumber(line.quantity) }} {{ line.unit_of_measure.symbol }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="detail-card">
          <div class="card-header">
            <h2 class="card-title">Select Vendors</h2>
            <div class="vendor-actions">
              <button 
                type="button" 
                class="btn btn-outline-primary btn-sm" 
                @click="selectAllVendors"
              >
                Select All
              </button>
              <button 
                type="button" 
                class="btn btn-outline-secondary btn-sm" 
                @click="deselectAllVendors"
              >
                Deselect All
              </button>
            </div>
          </div>
  
          <div class="card-body">
            <div class="search-filter">
              <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input 
                  type="text" 
                  v-model="vendorSearch" 
                  placeholder="Search vendors..." 
                  class="form-control"
                />
                <button v-if="vendorSearch" @click="vendorSearch = ''" class="clear-search">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              
              <div class="filter-group">
                <label>Filter by Category</label>
                <select v-model="categoryFilter" class="form-control">
                  <option value="">All Categories</option>
                  <option v-for="category in vendorCategories" :key="category" :value="category">
                    {{ category }}
                  </option>
                </select>
              </div>
            </div>
  
            <div v-if="loadingVendors" class="loading-indicator">
              <i class="fas fa-spinner fa-spin"></i> Loading vendors...
            </div>
            
            <div v-else-if="filteredVendors.length === 0" class="empty-vendors">
              <div class="empty-icon">
                <i class="fas fa-users"></i>
              </div>
              <h3>No Vendors Found</h3>
              <p>No vendors match your search criteria.</p>
            </div>
            
            <div v-else class="vendors-grid">
              <div 
                v-for="vendor in filteredVendors" 
                :key="vendor.vendor_id" 
                class="vendor-card"
                :class="{ 'vendor-selected': selectedVendors.includes(vendor.vendor_id) }"
                @click="toggleVendorSelection(vendor.vendor_id)"
              >
                <div class="vendor-card-header">
                  <div class="vendor-info">
                    <div class="vendor-name">{{ vendor.name }}</div>
                    <div class="vendor-code">{{ vendor.vendor_code }}</div>
                  </div>
                  <div class="vendor-checkbox">
                    <input 
                      type="checkbox" 
                      :checked="selectedVendors.includes(vendor.vendor_id)"
                      @click.stop
                      @change="toggleVendorSelection(vendor.vendor_id)"
                    />
                  </div>
                </div>
                
                <div class="vendor-details">
                  <div class="vendor-contact">
                    <div v-if="vendor.contact_person" class="contact-person">
                      <i class="fas fa-user"></i> {{ vendor.contact_person }}
                    </div>
                    <div v-if="vendor.email" class="contact-email">
                      <i class="fas fa-envelope"></i> {{ vendor.email }}
                    </div>
                    <div v-if="vendor.phone" class="contact-phone">
                      <i class="fas fa-phone"></i> {{ vendor.phone }}
                    </div>
                  </div>
                  
                  <div v-if="vendor.category" class="vendor-category">
                    <span class="category-badge">{{ vendor.category }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="form-actions">
          <button type="button" @click="$router.go(-1)" class="btn btn-secondary">
            Cancel
          </button>
          <button 
            type="button" 
            @click="sendRFQ" 
            class="btn btn-primary" 
            :disabled="selectedVendors.length === 0 || isSending"
          >
            <i v-if="isSending" class="fas fa-spinner fa-spin"></i>
            {{ isSending ? 'Sending...' : 'Send RFQ to Vendors' }}
          </button>
        </div>
      </div>
  
      <!-- Success Modal -->
      <div v-if="showSuccessModal" class="modal">
        <div class="modal-backdrop"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>RFQ Sent Successfully</h2>
            <button class="close-btn" @click="closeSuccessModal">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="success-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <p>RFQ {{ rfq?.rfq_number }} has been successfully sent to {{ selectedVendors.length }} vendors.</p>
            
            <div class="form-actions">
              <router-link :to="`/purchasing/rfqs/${rfqId}`" class="btn btn-primary">
                View RFQ Details
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'RFQSend',
    props: {
      rfqId: {
        type: [Number, String],
        required: true
      }
    },
    data() {
      return {
        rfq: null,
        loading: true,
        loadingVendors: true,
        vendors: [],
        selectedVendors: [],
        vendorSearch: '',
        categoryFilter: '',
        isSending: false,
        showSuccessModal: false
      }
    },
    computed: {
      vendorCategories() {
        const categories = [...new Set(this.vendors
          .filter(vendor => vendor.category)
          .map(vendor => vendor.category))];
        
        return categories.sort();
      },
      filteredVendors() {
        if (!this.vendors) return [];
        
        return this.vendors.filter(vendor => {
          // Filter by search term
          const searchMatches = !this.vendorSearch || 
            vendor.name.toLowerCase().includes(this.vendorSearch.toLowerCase()) ||
            vendor.vendor_code.toLowerCase().includes(this.vendorSearch.toLowerCase()) ||
            (vendor.contact_person && vendor.contact_person.toLowerCase().includes(this.vendorSearch.toLowerCase()));
          
          // Filter by category
          const categoryMatches = !this.categoryFilter || vendor.category === this.categoryFilter;
          
          return searchMatches && categoryMatches;
        });
      }
    },
    async mounted() {
      try {
        await Promise.all([
          this.loadRFQ(),
          this.loadVendors()
        ]);
      } catch (error) {
        console.error('Error initializing component:', error);
      }
    },
    methods: {
      async loadRFQ() {
        try {
          const response = await axios.get(`/request-for-quotations/${this.rfqId}`);
          
          if (response.data.status === 'success' && response.data.data) {
            this.rfq = response.data.data;
          } else {
            throw new Error(response.data.message || 'Failed to load RFQ');
          }
        } catch (error) {
          console.error('Error loading RFQ:', error);
          this.rfq = null;
          
          if (error.response && error.response.status === 404) {
            this.$toast.error('Request for Quotation not found');
          } else {
            this.$toast.error('Failed to load RFQ details. Please try again.');
          }
        } finally {
          this.loading = false;
        }
      },
      async loadVendors() {
        this.loadingVendors = true;
        
        try {
          const response = await axios.get('/vendors', {
            params: {
              status: 'active'
            }
          });
          
          if (response.data.data) {
            this.vendors = response.data.data;
          } else {
            throw new Error('Failed to load vendors');
          }
        } catch (error) {
          console.error('Error loading vendors:', error);
          this.$toast.error('Failed to load vendors. Please try again.');
        } finally {
          this.loadingVendors = false;
        }
      },
      formatDate(dateString) {
        if (!dateString) return null;
        
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
      },
      formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
      },
      capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
      },
      toggleVendorSelection(vendorId) {
        const index = this.selectedVendors.indexOf(vendorId);
        
        if (index === -1) {
          this.selectedVendors.push(vendorId);
        } else {
          this.selectedVendors.splice(index, 1);
        }
      },
      selectAllVendors() {
        this.selectedVendors = this.filteredVendors.map(vendor => vendor.vendor_id);
      },
      deselectAllVendors() {
        this.selectedVendors = [];
      },
      async sendRFQ() {
        if (this.selectedVendors.length === 0) {
          this.$toast.warning('Please select at least one vendor');
          return;
        }
        
        this.isSending = true;
        
        try {
          const response = await axios.post('/vendor-quotations/create-from-rfq', {
            rfq_id: this.rfqId,
            vendor_ids: this.selectedVendors
          });
          
          if (response.data.status === 'success') {
            // Update RFQ status
            await axios.patch(`/request-for-quotations/${this.rfqId}/status`, {
              status: 'sent'
            });
            
            // Show success modal
            this.showSuccessModal = true;
          } else {
            throw new Error(response.data.message || 'Failed to send RFQ');
          }
        } catch (error) {
          console.error('Error sending RFQ:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            this.$toast.error('Failed to send RFQ: ' + error.response.data.message);
          } else {
            this.$toast.error('Failed to send RFQ to vendors. Please try again.');
          }
        } finally {
          this.isSending = false;
        }
      },
      closeSuccessModal() {
        this.showSuccessModal = false;
        this.$router.push(`/purchasing/rfqs/${this.rfqId}`);
      }
    }
  }
  </script>
  
  <style scoped>
  .rfq-send-container {
    padding: 1rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .page-header h1 {
    margin: 0;
    font-size: 1.5rem;
  }
  
  .header-actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    color: var(--gray-500);
    font-size: 0.875rem;
  }
  
  .loading-indicator i {
    margin-right: 0.5rem;
    animation: spin 1s linear infinite;
  }
  
  .error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .error-icon {
    font-size: 3rem;
    color: #ef4444;
    margin-bottom: 1rem;
  }
  
  .error-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
  }
  
  .error-state p {
    color: var(--gray-500);
    margin-bottom: 1.5rem;
  }
  
  .rfq-send-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }
  
  .detail-card {
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    overflow: hidden;
  }
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .card-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
  }
  
  .status-draft {
    background-color: var(--gray-100);
    color: var(--gray-700);
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .detail-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
  }
  
  .detail-row:last-child {
    margin-bottom: 0;
  }
  
  .detail-group {
    flex: 1;
    min-width: 200px;
  }
  
  .detail-group.full-width {
    flex-basis: 100%;
  }
  
  .detail-group label {
    display: block;
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-bottom: 0.25rem;
  }
  
  .detail-value {
    font-size: 0.875rem;
    color: var(--gray-800);
  }
  
  .item-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  
  .item-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    background-color: var(--gray-100);
    border-radius: 0.25rem;
    font-size: 0.75rem;
  }
  
  .item-code {
    font-weight: 500;
    color: var(--gray-800);
  }
  
  .item-quantity {
    color: var(--gray-600);
  }
  
  .vendor-actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .search-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }
  
  .search-box {
    position: relative;
    flex: 1;
    min-width: 250px;
  }
  
  .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .search-box input {
    width: 100%;
    padding: 0.625rem 2.25rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  
  .search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .clear-search {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 9999px;
  }
  
  .clear-search:hover {
    background-color: var(--gray-100);
    color: var(--gray-700);
  }
  
  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .filter-group label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
  }
  
  .filter-group select {
    padding: 0.625rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    min-width: 200px;
  }
  
  .empty-vendors {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .empty-icon {
    font-size: 2.5rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
  }
  
  .empty-vendors h3 {
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
  }
  
  .empty-vendors p {
    color: var(--gray-500);
  }
  
  .vendors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
  }
  
  .vendor-card {
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    overflow: hidden;
    transition: all 0.2s;
    cursor: pointer;
  }
  
  .vendor-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }
  
  .vendor-selected {
    border-color: var(--primary-color);
    background-color: rgba(37, 99, 235, 0.05);
  }
  
  .vendor-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .vendor-name {
    font-weight: 500;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
  }
  
  .vendor-code {
    font-size: 0.75rem;
    color: var(--gray-500);
  }
  
  .vendor-checkbox input {
    width: 1.25rem;
    height: 1.25rem;
    cursor: pointer;
  }
  
  .vendor-details {
    padding: 1rem;
  }
  
  .vendor-contact {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
  }
  
  .contact-person,
  .contact-email,
  .contact-phone {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--gray-600);
  }
  
  .contact-person i,
  .contact-email i,
  .contact-phone i {
    width: 1rem;
    color: var(--gray-500);
  }
  
  .vendor-category {
    display: flex;
    justify-content: flex-end;
  }
  
  .category-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background-color: var(--gray-100);
    border-radius: 9999px;
    font-size: 0.75rem;
    color: var(--gray-700);
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1rem;
  }
  
  .btn {
    padding: 0.625rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
  }
  
  .btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .btn-secondary {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-50);
  }
  
  .btn-outline {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-outline:hover {
    background-color: var(--gray-50);
  }
  
  .btn-outline-primary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
  }
  
  .btn-outline-primary:hover {
    background-color: rgba(37, 99, 235, 0.05);
  }
  
  .btn-outline-secondary {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-outline-secondary:hover {
    background-color: var(--gray-50);
  }
  
  /* Modal Styles */
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
    border-radius: 0.5rem;
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
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
  }
  
  .modal-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: #1e293b;
  }
  
  .close-btn {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 0.25rem;
  }
  
  .close-btn:hover {
    background-color: #f1f5f9;
    color: #0f172a;
  }
  
  .modal-body {
    padding: 1.5rem;
    text-align: center;
  }
  
  .success-icon {
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 1rem;
  }
  
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .header-actions {
      width: 100%;
    }
    
    .detail-row {
      flex-direction: column;
      gap: 1rem;
    }
    
    .card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }
    
    .search-filter {
      flex-direction: column;
    }
    
    .vendors-grid {
      grid-template-columns: 1fr;
    }
  }
  </style>