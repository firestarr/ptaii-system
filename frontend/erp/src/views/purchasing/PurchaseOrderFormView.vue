<!-- src/views/purchasing/PurchaseOrderFormView.vue -->
<template>
  <div class="po-form-container">
    <div class="page-header">
      <h1>{{ isEditMode ? 'Edit Purchase Order' : 'Create Purchase Order' }}</h1>
      <div class="action-buttons">
        <router-link to="/purchasing/orders" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to List
        </router-link>
      </div>
    </div>

    <div class="alert alert-info" v-if="isEditMode && purchaseOrder.status !== 'draft'">
      <i class="fas fa-info-circle"></i>
      This purchase order is in "{{ purchaseOrder.status }}" status and some fields may not be editable.
    </div>

    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
      </div>
      <p class="mt-2">Loading purchase order data...</p>
    </div>

    <form v-else @submit.prevent="savePurchaseOrder" class="po-form">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Purchase Order Information</h2>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Vendor <span class="text-danger">*</span></label>
                <select 
                  v-model="purchaseOrder.vendor_id" 
                  class="form-control" 
                  :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                  required
                >
                  <option value="" disabled>Select Vendor</option>
                  <option v-for="vendor in vendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
                    {{ vendor.name }}
                  </option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>PO Date <span class="text-danger">*</span></label>
                <input 
                  type="date" 
                  v-model="purchaseOrder.po_date" 
                  class="form-control"
                  :disabled="isEditMode && purchaseOrder.status !== 'draft'"
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
          </div>

          <div class="row mt-3">
            <div class="col-md-4">
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
            <div class="col-md-4">
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
            <div class="col-md-4">
              <div class="form-group">
                <label>Currency</label>
                <select 
                  v-model="purchaseOrder.currency_code" 
                  class="form-control"
                  :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                >
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
        </div>
      </div>

      <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Purchase Order Lines</h2>
          <button 
            type="button" 
            class="btn btn-sm btn-primary" 
            @click="addLine"
            :disabled="isEditMode && purchaseOrder.status !== 'draft'"
          >
            <i class="fas fa-plus"></i> Add Line
          </button>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th style="width: 40%">Item</th>
                  <th style="width: 15%">Quantity</th>
                  <th style="width: 15%">UOM</th>
                  <th style="width: 15%">Unit Price</th>
                  <th style="width: 15%">Total</th>
                  <th style="width: 10%">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in purchaseOrder.lines" :key="index">
                  <td>
                    <select 
                      v-model="line.item_id" 
                      class="form-control"
                      @change="updateLineItem(line, index)"
                      :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                      required
                    >
                      <option value="" disabled>Select Item</option>
                      <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                        {{ item.item_code }} - {{ item.name }}
                      </option>
                    </select>
                  </td>
                  <td>
                    <input 
                      type="number" 
                      v-model.number="line.quantity" 
                      class="form-control" 
                      min="0.01"
                      step="0.01"
                      @input="updateLineTotal(line)"
                      :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                      required
                    >
                  </td>
                  <td>
                    <select 
                      v-model="line.uom_id" 
                      class="form-control"
                      :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                      required
                    >
                      <option value="" disabled>Select UOM</option>
                      <option v-for="uom in uoms" :key="uom.uom_id" :value="uom.uom_id">
                        {{ uom.name }}
                      </option>
                    </select>
                  </td>
                  <td>
                    <input 
                      type="number" 
                      v-model.number="line.unit_price" 
                      class="form-control" 
                      min="0"
                      step="0.01"
                      @input="updateLineTotal(line)"
                      :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                      required
                    >
                  </td>
                  <td>
                    <input 
                      type="number" 
                      v-model.number="line.subtotal" 
                      class="form-control" 
                      readonly
                    >
                  </td>
                  <td>
                    <button 
                      type="button" 
                      class="btn btn-sm btn-danger"
                      @click="removeLine(index)"
                      :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <tr v-if="purchaseOrder.lines.length === 0">
                  <td colspan="6" class="text-center">
                    No items added. Click "Add Line" to add items to this purchase order.
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="text-right font-weight-bold">Subtotal:</td>
                  <td colspan="2">{{ formatCurrency(calculateSubtotal()) }}</td>
                </tr>
                <tr>
                  <td colspan="4" class="text-right font-weight-bold">Tax:</td>
                  <td colspan="2">
                    <input 
                      type="number" 
                      v-model.number="purchaseOrder.tax_amount" 
                      class="form-control"
                      min="0"
                      step="0.01"
                      @input="updateTotals"
                      :disabled="isEditMode && purchaseOrder.status !== 'draft'"
                    >
                  </td>
                </tr>
                <tr>
                  <td colspan="4" class="text-right font-weight-bold">Total:</td>
                  <td colspan="2" class="font-weight-bold">{{ formatCurrency(calculateTotal()) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <div class="form-actions mt-4">
        <button type="button" class="btn btn-secondary" @click="$router.go(-1)">Cancel</button>
        <button 
          type="submit" 
          class="btn btn-primary ml-2" 
          :disabled="isSaving || (isEditMode && purchaseOrder.status !== 'draft')"
        >
          <span v-if="isSaving">
            <i class="fas fa-spinner fa-spin"></i> Saving...
          </span>
          <span v-else>
            <i class="fas fa-save"></i> {{ isEditMode ? 'Update' : 'Create' }} Purchase Order
          </span>
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'PurchaseOrderFormView',
  data() {
    return {
      isEditMode: false,
      isLoading: false,
      isSaving: false,
      vendors: [],
      items: [],
      uoms: [],
      purchaseOrder: {
        po_date: new Date().toISOString().split('T')[0],
        vendor_id: '',
        payment_terms: '',
        delivery_terms: '',
        expected_delivery: '',
        currency_code: 'USD',
        tax_amount: 0,
        lines: []
      }
    };
  },
  created() {
    // Check if we're in edit mode
    const poId = this.$route.params.id;
    this.isEditMode = !!poId;
    
    // Load necessary data
    this.loadVendors();
    this.loadItems();
    this.loadUoms();
    
    if (this.isEditMode) {
      this.loadPurchaseOrder(poId);
    } else {
      // Add an empty line for new POs
      this.addLine();
    }
  },
  methods: {
    async loadVendors() {
      try {
        const response = await axios.get('/api/vendors');
        this.vendors = response.data.data;
      } catch (error) {
        console.error('Error loading vendors:', error);
      }
    },
    async loadItems() {
      try {
        const response = await axios.get('/api/items?is_purchasable=1');
        this.items = response.data.data;
      } catch (error) {
        console.error('Error loading items:', error);
      }
    },
    async loadUoms() {
      try {
        const response = await axios.get('/api/uoms');
        this.uoms = response.data.data;
      } catch (error) {
        console.error('Error loading UOMs:', error);
      }
    },
    async loadPurchaseOrder(poId) {
      this.isLoading = true;
      try {
        const response = await axios.get(`/api/purchase-orders/${poId}`);
        
        if (response.data.status === 'success') {
          this.purchaseOrder = response.data.data;
          
          // Ensure po_date is in the right format for input[type=date]
          if (this.purchaseOrder.po_date) {
            this.purchaseOrder.po_date = this.formatDateForInput(this.purchaseOrder.po_date);
          }
          
          // Ensure expected_delivery is in the right format for input[type=date]
          if (this.purchaseOrder.expected_delivery) {
            this.purchaseOrder.expected_delivery = this.formatDateForInput(this.purchaseOrder.expected_delivery);
          }
          
          // Ensure lines array exists
          if (!this.purchaseOrder.lines) {
            this.purchaseOrder.lines = [];
          }
          
          // If no lines, add one empty line
          if (this.purchaseOrder.lines.length === 0) {
            this.addLine();
          }
        }
      } catch (error) {
        console.error('Error loading purchase order:', error);
        alert('Failed to load purchase order data');
      } finally {
        this.isLoading = false;
      }
    },
    addLine() {
      this.purchaseOrder.lines.push({
        item_id: '',
        quantity: 1,
        uom_id: '',
        unit_price: 0,
        subtotal: 0,
        tax: 0,
        total: 0
      });
    },
    removeLine(index) {
      if (confirm('Are you sure you want to remove this line?')) {
        this.purchaseOrder.lines.splice(index, 1);
        this.updateTotals();
      }
    },
    updateLineItem(line, index) {
      // Find the selected item
      const item = this.items.find(i => i.item_id === line.item_id);
      if (item) {
        // Set default UOM if not already set
        if (!line.uom_id && item.uom_id) {
          line.uom_id = item.uom_id;
        }
        
        // Set default price if available
        if (item.cost_price && line.unit_price === 0) {
          line.unit_price = item.cost_price;
          this.updateLineTotal(line);
        }
      }
    },
    updateLineTotal(line) {
      if (line.quantity && line.unit_price) {
        line.subtotal = parseFloat((line.quantity * line.unit_price).toFixed(2));
      } else {
        line.subtotal = 0;
      }
      this.updateTotals();
    },
    calculateSubtotal() {
      return this.purchaseOrder.lines.reduce((sum, line) => sum + (line.subtotal || 0), 0);
    },
    calculateTotal() {
      const subtotal = this.calculateSubtotal();
      const tax = parseFloat(this.purchaseOrder.tax_amount || 0);
      return subtotal + tax;
    },
    updateTotals() {
      const subtotal = this.calculateSubtotal();
      const tax = parseFloat(this.purchaseOrder.tax_amount || 0);
      this.purchaseOrder.total_amount = subtotal + tax;
    },
    formatDateForInput(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toISOString().split('T')[0];
    },
    formatCurrency(amount) {
      if (amount === null || amount === undefined) return '-';
      return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount);
    },
    async savePurchaseOrder() {
      // Validate the form
      if (!this.purchaseOrder.vendor_id || !this.purchaseOrder.po_date) {
        alert('Please fill all required fields');
        return;
      }
      
      if (this.purchaseOrder.lines.length === 0) {
        alert('Please add at least one item to the purchase order');
        return;
      }
      
      for (const line of this.purchaseOrder.lines) {
        if (!line.item_id || !line.quantity || !line.uom_id) {
          alert('Please fill all required fields for each line item');
          return;
        }
      }
      
      this.isSaving = true;
      try {
        let response;
        
        if (this.isEditMode) {
          // Update existing PO
          response = await axios.put(
            `/api/purchase-orders/${this.purchaseOrder.po_id}`, 
            this.purchaseOrder
          );
        } else {
          // Create new PO
          response = await axios.post('/api/purchase-orders', this.purchaseOrder);
        }
        
        if (response.data.status === 'success') {
          // Show success message
          alert(this.isEditMode ? 'Purchase order updated successfully' : 'Purchase order created successfully');
          
          // Redirect to PO detail page
          this.$router.push(`/purchasing/orders/${response.data.data.po_id}`);
        }
      } catch (error) {
        console.error('Error saving purchase order:', error);
        
        // Show error message
        if (error.response && error.response.data && error.response.data.message) {
          alert(`Error: ${error.response.data.message}`);
        } else {
          alert('An error occurred while saving the purchase order');
        }
      } finally {
        this.isSaving = false;
      }
    }
  }
};
</script>

<style scoped>
.po-form-container {
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

.form-actions {
  display: flex;
  justify-content: flex-end;
}

.table th {
  background-color: #f8f9fa;
}
</style>