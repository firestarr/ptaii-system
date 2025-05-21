<!-- src/views/purchasing/PurchaseRequisitionForm.vue -->
<template>
    <div class="purchase-requisition-form">
      <div class="page-header">
        <h1>{{ isEditMode ? 'Edit Purchase Requisition' : 'Create Purchase Requisition' }}</h1>
        <div>
          <button v-if="isEditMode && form.status === 'draft'" @click="submitForm" class="btn btn-success mr-2">
            <i class="fas fa-paper-plane"></i> Submit for Approval
          </button>
          <button @click="saveForm" class="btn btn-primary" :disabled="loading">
            <i class="fas fa-save"></i> {{ isEditMode ? 'Update' : 'Save' }}
          </button>
        </div>
      </div>
  
      <div v-if="loading" class="text-center my-4">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
  
      <div v-else class="card">
        <div class="card-body">
          <form @submit.prevent="saveForm">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="pr_number">PR Number</label>
                  <input
                    type="text"
                    id="pr_number"
                    class="form-control"
                    v-model="form.pr_number"
                    readonly
                    disabled
                    placeholder="Will be generated automatically"
                  />
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="pr_date">PR Date <span class="text-danger">*</span></label>
                  <input
                    type="date"
                    id="pr_date"
                    class="form-control"
                    v-model="form.pr_date"
                    required
                    :class="{ 'is-invalid': errors.pr_date }"
                  />
                  <div v-if="errors.pr_date" class="invalid-feedback">{{ errors.pr_date[0] }}</div>
                </div>
              </div>
            </div>
  
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="requester_id">Requester <span class="text-danger">*</span></label>
                  <select
                    id="requester_id"
                    class="form-control"
                    v-model="form.requester_id"
                    required
                    :class="{ 'is-invalid': errors.requester_id }"
                  >
                    <option value="">Select Requester</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">
                      {{ user.name }}
                    </option>
                  </select>
                  <div v-if="errors.requester_id" class="invalid-feedback">{{ errors.requester_id[0] }}</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="status">Status</label>
                  <input
                    type="text"
                    id="status"
                    class="form-control"
                    v-model="form.status"
                    readonly
                    disabled
                  />
                </div>
              </div>
            </div>
  
            <div class="form-group">
              <label for="notes">Notes</label>
              <textarea
                id="notes"
                class="form-control"
                v-model="form.notes"
                rows="3"
                :class="{ 'is-invalid': errors.notes }"
              ></textarea>
              <div v-if="errors.notes" class="invalid-feedback">{{ errors.notes[0] }}</div>
            </div>
  
            <div class="card mt-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Requisition Lines</h5>
                <button type="button" class="btn btn-sm btn-primary" @click="addLine">
                  <i class="fas fa-plus"></i> Add Line
                </button>
              </div>
              <div class="card-body p-0">
                <div v-if="form.lines.length === 0" class="text-center py-4">
                  <p class="text-muted">No items added yet. Click "Add Line" to add items to this requisition.</p>
                </div>
                <div v-else class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead>
                      <tr>
                        <th style="width: 40%">Item</th>
                        <th style="width: 15%">Quantity</th>
                        <th style="width: 15%">Unit</th>
                        <th style="width: 20%">Required Date</th>
                        <th style="width: 10%">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(line, index) in form.lines" :key="index">
                        <td>
                          <div class="custom-dropdown">
                            <input
                              type="text"
                              class="form-control"
                              v-model="line.itemSearch"
                              :class="{ 'is-invalid': lineErrors[index]?.item_id }"
                              placeholder="Search for an item..."
                              @focus="showDropdown(index)"
                              @input="showDropdown(index)"
                              @blur="hideDropdown(index)"
                              required
                            />
                            <div v-show="line.showDropdown" class="custom-dropdown-menu" :style="{ minWidth: '300px', maxWidth: '450px' }">
                              <div v-for="item in filteredItems(line.itemSearch)" :key="item.item_id" 
                                @mousedown="selectItem(line, item)" class="dropdown-item">
                                {{ item.item_code }} - {{ item.name }}
                              </div>
                              <div v-if="filteredItems(line.itemSearch).length === 0" class="dropdown-item text-muted">No items found</div>
                            </div>
                          </div>
                          <div v-if="lineErrors[index]?.item_id" class="invalid-feedback">{{ lineErrors[index].item_id[0] }}</div>
                        </td>
                        <td>
                          <input
                            type="number"
                            class="form-control"
                            v-model.number="line.quantity"
                            min="0.01"
                            step="0.01"
                            required
                            :class="{ 'is-invalid': lineErrors[index]?.quantity }"
                          />
                          <div v-if="lineErrors[index]?.quantity" class="invalid-feedback">{{ lineErrors[index].quantity[0] }}</div>
                        </td>
                        <td>
                          <select
                            class="form-control"
                            v-model="line.uom_id"
                            required
                            :class="{ 'is-invalid': lineErrors[index]?.uom_id }"
                          >
                            <option value="">Select Unit</option>
                            <option v-for="uom in uoms" :key="uom.uom_id" :value="uom.uom_id">
                              {{ uom.name }}
                            </option>
                          </select>
                          <div v-if="lineErrors[index]?.uom_id" class="invalid-feedback">{{ lineErrors[index].uom_id[0] }}</div>
                        </td>
                        <td>
                          <input
                            type="date"
                            class="form-control"
                            v-model="line.required_date"
                            :class="{ 'is-invalid': lineErrors[index]?.required_date }"
                          />
                          <div v-if="lineErrors[index]?.required_date" class="invalid-feedback">{{ lineErrors[index].required_date[0] }}</div>
                        </td>
                        <td>
                          <button
                            type="button"
                            class="btn btn-sm btn-danger"
                            @click="removeLine(index)"
                            title="Remove Line"
                          >
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
  
            <div class="form-actions mt-4">
              <router-link :to="'/purchasing/requisitions'" class="btn btn-secondary mr-2">
                <i class="fas fa-arrow-left"></i> Cancel
              </router-link>
              <button v-if="isEditMode && form.status === 'draft'" @click="submitForm" type="button" class="btn btn-success mr-2">
                <i class="fas fa-paper-plane"></i> Submit for Approval
              </button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                <i class="fas fa-save"></i> {{ isEditMode ? 'Update' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Alert Modal -->
      <div v-if="alert.show" class="alert-modal">
        <div class="alert" :class="`alert-${alert.type}`">
          {{ alert.message }}
          <button type="button" class="close" @click="alert.show = false">
            <span>&times;</span>
          </button>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'PurchaseRequisitionForm',
    props: {
      id: {
        type: [String, Number],
        default: null
      }
    },
    data() {
      return {
        form: {
          pr_number: '',
          pr_date: this.formatDate(new Date()),
          requester_id: '',
          status: 'draft',
          notes: '',
          lines: []
        },
        originalForm: null,
        users: [],
        items: [],
        uoms: [],
        loading: true,
        saving: false,
        errors: {},
        lineErrors: [],
        alert: {
          show: false,
          type: 'success',
          message: ''
        }
      };
    },
    computed: {
      isEditMode() {
        return !!this.id;
      }
    },
    async created() {
      try {
        // Fetch required data
        await this.fetchDropdownData();
        
        // If editing, fetch PR data
        if (this.isEditMode) {
          await this.fetchPRData();
        } else {
          // Initialize with empty line for new PR
          this.addLine();
        }
        // Set requester_id to first user id if users array is not empty after fetching users
        if (!this.isEditMode && this.users.length > 0) {
          this.form.requester_id = this.users[0].id;
        }
      } catch (error) {
        this.showAlert('danger', 'Failed to load data. Please try again.');
        console.error('Error loading form data:', error);
      } finally {
        this.loading = false;
      }
    },
    methods: {
      // Methods for searchable dropdown
      filteredItems(search) {
        if (!search) {
          return this.items;
        }
        return this.items.filter(item => 
          item.name.toLowerCase().includes(search.toLowerCase()) || 
          item.item_code.toLowerCase().includes(search.toLowerCase())
        );
      },
      showDropdown(index) {
        if (!this.form.lines[index].showDropdown) {
          this.form.lines[index].showDropdown = true;
        }
      },
      hideDropdown(index) {
        setTimeout(() => {
          this.form.lines[index].showDropdown = false;
        }, 200);
      },
      selectItem(line, item) {
        line.item_id = item.item_id;
        line.itemSearch = `${item.item_code} - ${item.name}`;
        line.showDropdown = false;
        
        // Auto-fill UOM based on item's default UOM
        // Try different possible structures to find UOM
        if (item.unitOfMeasure && item.unitOfMeasure.uom_id) {
          line.uom_id = item.unitOfMeasure.uom_id;
        } else if (item.uom_id) {
          // Direct UOM ID reference
          line.uom_id = item.uom_id;
        } else if (item.default_uom_id) {
          // Try another possible property name
          line.uom_id = item.default_uom_id;
        }
        
        // Debugging - remove in production
        console.log('Selected item:', item);
        console.log('UOM set to:', line.uom_id);
      },

      async fetchDropdownData() {
        try {
          // Fetch users
          const usersResponse = await axios.get('/user');
          
          // Check the structure of the response
          if (Array.isArray(usersResponse.data)) {
            this.users = usersResponse.data;
          } else if (usersResponse.data && usersResponse.data.id) {
            // Single user object
            this.users = [usersResponse.data];
          } else if (usersResponse.data && Array.isArray(usersResponse.data.data)) {
            // Nested data array
            this.users = usersResponse.data.data;
          } else if (usersResponse.data && usersResponse.data.data && usersResponse.data.data.id) {
            // Nested single user
            this.users = [usersResponse.data.data];
          } else {
            this.users = [];
          }
          
          // Fetch purchasable items
          const itemsResponse = await axios.get('/items/purchasable');
          this.items = itemsResponse.data.data || [];
          
          // Debug - examine item structure
          if (this.items.length > 0) {
            console.log('Item structure example:', this.items[0]);
          }
          
          // Fetch units of measure
          const uomsResponse = await axios.get('/uoms');
          this.uoms = uomsResponse.data.data || [];
        } catch (error) {
          console.error('Error fetching dropdown data:', error);
          throw error;
        }
      },

      onItemChange(line) {
        const selectedItem = this.items.find(item => item.item_id === line.item_id);
        if (selectedItem && selectedItem.unitOfMeasure) {
          line.uom_id = selectedItem.unitOfMeasure.uom_id;
        } else {
          line.uom_id = '';
        }
      },
      
      async fetchPRData() {
        try {
          const response = await axios.get(`/purchase-requisitions/${this.id}`);
          const prData = response.data.data;
          
          this.form = {
            pr_number: prData.pr_number,
            pr_date: this.formatDate(prData.pr_date),
            requester_id: prData.requester_id,
            status: prData.status,
            notes: prData.notes,
            lines: prData.lines.map(line => {
              // Find matching item to get its name
              const matchedItem = this.items.find(item => item.item_id === line.item_id);
              const itemDisplay = matchedItem ? 
                `${matchedItem.item_code} - ${matchedItem.name}` : '';
              
              return {
                item_id: line.item_id,
                itemSearch: itemDisplay,  // Add itemSearch property for display
                quantity: parseFloat(line.quantity),
                uom_id: line.uom_id,
                required_date: line.required_date ? this.formatDate(line.required_date) : null,
                notes: line.notes,
                showDropdown: false  // Add dropdown visibility property
              };
            })
          };
          
          // Store original form state for change detection
          this.originalForm = JSON.parse(JSON.stringify(this.form));
        } catch (error) {
          console.error('Error fetching PR data:', error);
          throw error;
        }
      },
      
      addLine() {
        this.form.lines.push({
          item_id: '',
          itemSearch: '',  // Add itemSearch property
          quantity: 1,
          uom_id: '',
          required_date: '',
          notes: '',
          showDropdown: false  // Add dropdown visibility property
        });
      },
      
      removeLine(index) {
        if (this.form.lines.length > 1) {
          this.form.lines.splice(index, 1);
        } else {
          this.showAlert('warning', 'At least one line item is required.');
        }
      },
      
      async saveForm() {
        if (!this.validateForm()) {
          return;
        }
        
        this.saving = true;
        
        try {
          let response;
          const formData = this.prepareFormData();
          
          if (this.isEditMode) {
            response = await axios.put(`/purchase-requisitions/${this.id}`, formData);
            this.showAlert('success', 'Purchase Requisition updated successfully.');
          } else {
            response = await axios.post('/purchase-requisitions', formData);
            this.showAlert('success', 'Purchase Requisition created successfully.');
            
            // Redirect to detail page after create
            setTimeout(() => {
              this.$router.push(`/purchasing/requisitions/${response.data.data.pr_id}`);
            }, 1500);
          }
          
          // Update form with response data
          const prData = response.data.data;
          this.form.pr_number = prData.pr_number;
          this.form.status = prData.status;
          
          // Store new original form state for change detection
          this.originalForm = JSON.parse(JSON.stringify(this.form));
        } catch (error) {
          this.handleApiError(error);
        } finally {
          this.saving = false;
        }
      },
      
      async submitForm() {
        if (!this.validateForm()) {
          return;
        }
        
        this.saving = true;
        
        try {
          // First save any changes
          await this.saveForm();
          
          // Then update status to pending
          const response = await axios.patch(`/purchase-requisitions/${this.id}/status`, {
            status: 'pending'
          });
          
          this.form.status = response.data.data.status;
          this.showAlert('success', 'Purchase Requisition submitted for approval.');
          
          // Redirect to detail page after submit
          setTimeout(() => {
            this.$router.push(`/purchasing/requisitions/${this.id}`);
          }, 1500);
        } catch (error) {
          this.handleApiError(error);
        } finally {
          this.saving = false;
        }
      },
      
      validateForm() {
        this.errors = {};
        this.lineErrors = [];
        
        // Check if PR date is provided
        if (!this.form.pr_date) {
          this.errors.pr_date = ['PR Date is required.'];
        }
        
        // Check if requester is selected
        if (!this.form.requester_id) {
          this.errors.requester_id = ['Requester is required.'];
        }
        
        // Check lines
        let hasLineErrors = false;
        
        this.form.lines.forEach((line, index) => {
          const lineError = {};
          
          if (!line.item_id) {
            lineError.item_id = ['Item is required.'];
            hasLineErrors = true;
          }
          
          if (!line.quantity || line.quantity <= 0) {
            lineError.quantity = ['Quantity must be greater than 0.'];
            hasLineErrors = true;
          }
          
          if (!line.uom_id) {
            lineError.uom_id = ['Unit of Measure is required.'];
            hasLineErrors = true;
          }
          
          this.lineErrors[index] = Object.keys(lineError).length ? lineError : null;
        });
        
        return !Object.keys(this.errors).length && !hasLineErrors;
      },
      
      prepareFormData() {
        // Create a copy of the form data for submission
        const formData = {
          pr_date: this.form.pr_date,
          requester_id: this.form.requester_id,
          notes: this.form.notes,
          lines: this.form.lines.map(line => ({
            item_id: line.item_id,
            quantity: line.quantity,
            uom_id: line.uom_id,
            required_date: line.required_date,
            notes: line.notes
          }))
        };
        
        return formData;
      },
      
      handleApiError(error) {
        if (error.response && error.response.data && error.response.data.errors) {
          // Handle validation errors
          this.errors = error.response.data.errors;
          
          // Handle line errors if they exist
          if (this.errors.lines) {
            this.errors.lines.forEach(lineError => {
              const match = lineError.match(/^lines\.(\d+)\.(.+)/);
              if (match) {
                const lineIndex = parseInt(match[1]);
                const field = match[2];
                const message = lineError.split(':')[1].trim();
                
                if (!this.lineErrors[lineIndex]) {
                  this.lineErrors[lineIndex] = {};
                }
                
                this.lineErrors[lineIndex][field] = [message];
              }
            });
            
            delete this.errors.lines;
          }
          
          this.showAlert('danger', 'Please correct the errors in the form.');
        } else {
          // Handle other errors
          this.showAlert('danger', 'An error occurred. Please try again.');
          console.error('API Error:', error);
        }
      },
      
      showAlert(type, message) {
        this.alert = {
          show: true,
          type,
          message
        };
        
        // Hide alert after 5 seconds
        setTimeout(() => {
          this.alert.show = false;
        }, 5000);
      },
      
      formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
      },
      
      getCurrentUser() {
        // In a real app, you would get this from your auth store
        // For this example, we'll return a mock user
        return {
          id: 1,
          name: 'Current User'
        };
      }
    }
  };
  </script>
  
  <style scoped>
  .purchase-requisition-form {
    margin-bottom: 2rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .card {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: none;
    border-radius: 0.375rem;
  }
  
  .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 0.75rem 1rem;
  }
  
  .form-group label {
    font-weight: 500;
    font-size: 0.875rem;
    color: #495057;
    margin-bottom: 0.375rem;
  }
  
  .form-control {
    border-color: #ced4da;
    font-size: 0.875rem;
  }
  
  .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
  }
  
  textarea.form-control {
    min-height: 80px;
  }
  
  .table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    border-top: none;
  }
  
  .table td {
    vertical-align: middle;
  }
  
  /* Dropdown fix - increase z-index */
  .table-responsive {
    overflow: visible !important;
  }
  
  .table {
    position: relative;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-start;
  }
  
  .alert-modal {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 380px;
    z-index: 1050;
  }
  
  .alert {
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-left-width: 4px;
  }
  
  .close {
    background: none;
    border: none;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    opacity: 0.5;
    padding: 0;
    margin-left: 10px;
  }
  
  .close:hover {
    opacity: 1;
  }
  
  /* Dropdown styling */
  .custom-dropdown {
    position: relative;
    width: 100%;
  }
  
  .custom-dropdown-menu {
    position: fixed; /* Use fixed instead of absolute */
    z-index: 9999;
    display: block;
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
    padding: 0.5rem 0;
    margin: 0.125rem 0 0;
    background-color: #fff;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 0.25rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
  }
  
  /* Make dropdown appear above other elements */
  .dropdown-item {
    display: block;
    width: 100%;
    padding: 0.5rem 1rem;
    clear: both;
    font-weight: 400;
    color: #212529;
    text-align: left;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
    cursor: pointer;
  }
  
  .dropdown-item:hover {
    color: #16181b;
    text-decoration: none;
    background-color: #f8f9fa;
  }
  
  .text-muted {
    color: #6c757d;
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .form-actions {
      flex-direction: column;
      gap: 0.5rem;
    }
    
    .form-actions .btn {
      width: 100%;
      margin-right: 0 !important;
    }
    
    .alert-modal {
      width: calc(100% - 40px);
      top: 10px;
      right: 20px;
    }
  }
  </style>