<!-- src/views/purchasing/CreatePOFromQuotation.vue -->
<template>
  <div class="create-po-container">
    <div class="page-header">
      <h1>Create Purchase Order from Quotation</h1>
      <div class="action-buttons">
        <router-link to="/purchasing/rfqs" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to RFQs
        </router-link>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
      </div>
      <p class="mt-2">Loading quotation data...</p>
    </div>

    <div v-else class="quotation-content">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Source Quotation Information</h2>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <table class="table table-sm table-borderless detail-table">
                <tbody>
                  <tr>
                    <th>RFQ Number:</th>
                    <td>{{ quotation.requestForQuotation ? quotation.requestForQuotation.rfq_number : 'N/A' }}</td>
                  </tr>
                  <tr>
                    <th>Quotation Date:</th>
                    <td>{{ formatDate(quotation.quotation_date) }}</td>
                  </tr>
                  <tr>
                    <th>Validity Date:</th>
                    <td>{{ formatDate(quotation.validity_date) || 'Not specified' }}</td>
                  </tr>
                  <tr>
                    <th>Status:</th>
                    <td>
                      <span class="badge" :class="getStatusBadgeClass(quotation.status)">
                        {{ quotation.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm table-borderless detail-table">
                <tbody>
                  <tr>
                    <th>Vendor:</th>
                    <td>{{ quotation.vendor ? quotation.vendor.name : 'Unknown' }}</td>
                  </tr>
                  <tr>
                    <th>Vendor Contact:</th>
                    <td>{{ quotation.vendor ? quotation.vendor.contact_person : 'Unknown' }}</td>
                  </tr>
                  <tr>
                    <th>Vendor Email:</th>
                    <td>{{ quotation.vendor ? quotation.vendor.email : 'Unknown' }}</td>
                  </tr>
                  <tr>
                    <th>Vendor Phone:</th>
                    <td>{{ quotation.vendor ? quotation.vendor.phone : 'Unknown' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card mt-4">
        <div class="card-header">
          <h2 class="card-title">Quotation Items</h2>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 40%">Item</th>
                  <th style="width: 15%">Quantity</th>
                  <th style="width: 15%">Unit Price</th>
                  <th style="width: 25%">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in quotation.lines" :key="line.line_id">
                  <td>{{ index + 1 }}</td>
                  <td>
                    {{ line.item ? line.item.name : 'Unknown Item' }}
                    <small class="text-muted d-block">
                      {{ line.item ? line.item.item_code : '' }}
                    </small>
                  </td>
                  <td>
                    {{ formatNumber(line.quantity) }}
                    {{ line.unitOfMeasure ? line.unitOfMeasure.name : '' }}
                  </td>
                  <td>{{ formatCurrency(line.unit_price) }}</td>
                  <td>{{ formatCurrency(line.unit_price * line.quantity) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="text-right font-weight-bold">Total:</td>
                  <td class="font-weight-bold">{{ formatCurrency(calculateTotal()) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <div class="card mt-4">
        <div class="card-header">
          <h2 class="card-title">Purchase Order Details</h2>
        </div>
        <div class="card-body">
          <form @submit.prevent="createPurchaseOrder">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label>PO Date <span class="text-danger">*</span></label>
                  <input 
                    type="date" 
                    v-model="purchaseOrder.po_date" 
                    class="form-control"
                    required
                  >
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Expected Delivery</label>
                  <input 
                    type="date" 
                    v-model="purchaseOrder.expected_delivery" 
                    class="form-control"
                  >
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Currency</label>
                  <select v-model="purchaseOrder.currency_code" class="form-control">
                    <option value="USD">USD - US Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="GBP">GBP - British Pound</option>
                    <option value="IDR">IDR - Indonesian Rupiah</option>
                    <option value="JPY">JPY - Japanese Yen</option>
                    <option value="CNY">CNY - Chinese Yuan</option>
                    <option value="SGD">SGD - Singapore Dollar</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Payment Terms</label>
                  <select v-model="purchaseOrder.payment_terms" class="form-control">
                    <option value="">Select Payment Terms</option>
                    <option value="Net 30">Net 30</option>
                    <option value="Net 45">Net 45</option>
                    <option value="Net 60">Net 60</option>
                    <option value="Immediate">Immediate</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Delivery Terms</label>
                  <select v-model="purchaseOrder.delivery_terms" class="form-control">
                    <option value="">Select Delivery Terms</option>
                    <option value="FOB">FOB (Free On Board)</option>
                    <option value="CIF">CIF (Cost, Insurance, Freight)</option>
                    <option value="EXW">EXW (Ex Works)</option>
                    <option value="DDP">DDP (Delivered Duty Paid)</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-group mt-3">
              <label>Notes</label>
              <textarea v-model="purchaseOrder.notes" class="form-control" rows="3"></textarea>
            </div>

            <div class="alert alert-info mt-4">
              <i class="fas fa-info-circle"></i>
              Creating a purchase order will mark this quotation as accepted. All items and prices from the quotation will be transferred to the new purchase order.
            </div>

            <div class="form-actions mt-4">
              <button type="button" class="btn btn-secondary" @click="$router.go(-1)">Cancel</button>
              <button 
                type="submit" 
                class="btn btn-primary ml-2" 
                :disabled="isCreating || !isQuotationValid"
              >
                <span v-if="isCreating">
                  <i class="fas fa-spinner fa-spin"></i> Creating...
                </span>
                <span v-else>
                  <i class="fas fa-file-invoice"></i> Create Purchase Order
                </span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'CreatePOFromQuotation',
  data() {
    return {
      isLoading: true,
      isCreating: false,
      quotation: {},
      purchaseOrder: {
        po_date: new Date().toISOString().split('T')[0],
        expected_delivery: '',
        payment_terms: '',
        delivery_terms: '',
        currency_code: 'USD',
        notes: ''
      }
    };
  },
  computed: {
    isQuotationValid() {
      return (
        this.quotation.status === 'accepted' && 
        this.quotation.vendor_id && 
        this.quotation.lines && 
        this.quotation.lines.length > 0
      );
    }
  },
  created() {
    const quotationId = this.$route.params.id;
    if (quotationId) {
      this.loadQuotation(quotationId);
    } else {
      this.$router.push('/purchasing/rfqs');
    }
  },
  methods: {
    async loadQuotation(quotationId) {
      this.isLoading = true;
      try {
        const response = await axios.get(`/api/vendor-quotations/${quotationId}`);
        
        if (response.data.status === 'success') {
          this.quotation = response.data.data;
          
          // Use vendor's preferred currency if available
          if (this.quotation.vendor && this.quotation.vendor.preferred_currency) {
            this.purchaseOrder.currency_code = this.quotation.vendor.preferred_currency;
          }
          
          // If the quotation is not in accepted status, show a warning
          if (this.quotation.status !== 'accepted') {
            alert('Warning: This quotation is not in accepted status. You should accept the quotation before creating a purchase order.');
          }
        }
      } catch (error) {
        console.error('Error loading quotation:', error);
        alert('Failed to load quotation data');
      } finally {
        this.isLoading = false;
      }
    },
    calculateTotal() {
      if (!this.quotation.lines) return 0;
      return this.quotation.lines.reduce((sum, line) => sum + (line.unit_price * line.quantity), 0);
    },
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    formatCurrency(amount) {
      if (amount === null || amount === undefined) return '-';
      return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount);
    },
    formatNumber(number) {
      if (number === null || number === undefined) return '-';
      return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
      }).format(number);
    },
    getStatusBadgeClass(status) {
      const statusClasses = {
        'draft': 'badge-secondary',
        'sent': 'badge-primary',
        'accepted': 'badge-success',
        'rejected': 'badge-danger',
        'expired': 'badge-warning'
      };
      
      return `badge ${statusClasses[status] || 'badge-secondary'}`;
    },
    async createPurchaseOrder() {
      if (!this.isQuotationValid) {
        alert('This quotation cannot be used to create a purchase order.');
        return;
      }
      
      this.isCreating = true;
      try {
        const response = await axios.post('/api/purchase-orders/create-from-quotation', {
          quotation_id: this.quotation.quotation_id,
          po_date: this.purchaseOrder.po_date,
          expected_delivery: this.purchaseOrder.expected_delivery,
          payment_terms: this.purchaseOrder.payment_terms,
          delivery_terms: this.purchaseOrder.delivery_terms,
          currency_code: this.purchaseOrder.currency_code,
          notes: this.purchaseOrder.notes
        });
        
        if (response.data.status === 'success') {
          // Show success message
          alert('Purchase order created successfully');
          
          // Redirect to the new purchase order
          this.$router.push(`/purchasing/orders/${response.data.data.po_id}`);
        }
      } catch (error) {
        console.error('Error creating purchase order:', error);
        
        // Show error message
        if (error.response && error.response.data && error.response.data.message) {
          alert(`Error: ${error.response.data.message}`);
        } else {
          alert('An error occurred while creating the purchase order');
        }
      } finally {
        this.isCreating = false;
      }
    }
  }
};
</script>

<style scoped>
.create-po-container {
  margin-bottom: 2rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.card-title {
  margin-bottom: 0;
  font-size: 1.25rem;
}

.detail-table th {
  width: 40%;
  font-weight: 600;
}

.badge {
  padding: 0.5em 0.75em;
  text-transform: capitalize;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}
</style>