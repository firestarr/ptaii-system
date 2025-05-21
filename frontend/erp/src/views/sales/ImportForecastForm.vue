<!-- src/views/sales/ImportForecastForm.vue -->
<template>
  <div class="page-container">
    <div class="page-header">
      <h2>Import Sales Forecast</h2>
      <p class="text-muted">Upload customer forecasts or generate system forecasts</p>
    </div>

    <div class="row">
      <!-- CSV Import Section -->
      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Import Customer Forecasts</h5>
          </div>
          <div class="card-body">
            <form @submit.prevent="importForecast" class="mb-3">
              <div class="mb-3">
                <label class="form-label">Customer</label>
                <select
                  class="form-select"
                  v-model="importForm.customer_id"
                  required
                >
                  <option value="">Select Customer</option>
                  <option
                    v-for="customer in customers"
                    :key="customer.customer_id"
                    :value="customer.customer_id"
                  >
                    {{ customer.name }}
                  </option>
                </select>
                <div class="form-text">
                  Select the customer who provided this forecast
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Forecast Issue Date</label>
                <input
                  type="date"
                  class="form-control"
                  v-model="importForm.forecast_issue_date"
                  required
                />
                <div class="form-text">
                  Date when this forecast was issued by the customer
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">CSV File</label>
                <input
                  type="file"
                  class="form-control"
                  accept=".csv,.txt"
                  @change="handleFileUpload"
                  required
                />
                <div class="form-text">
                  CSV must include an "item_code" column and monthly forecast columns in YYYY-MM format
                </div>
              </div>

              <div class="mb-3 form-check">
                <input 
                  type="checkbox" 
                  class="form-check-input" 
                  id="fillMissingPeriods" 
                  v-model="importForm.fill_missing_periods"
                />
                <label class="form-check-label" for="fillMissingPeriods">
                  Fill missing periods with system forecasts
                </label>
              </div>

              <div v-if="importError" class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ importError }}
              </div>

              <div class="d-flex">
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="importing"
                >
                  <i v-if="importing" class="fas fa-spinner fa-spin me-2"></i>
                  <i v-else class="fas fa-upload me-2"></i>
                  Import Forecast
                </button>
                <button
                  type="button"
                  class="btn btn-outline-secondary ms-2"
                  @click="resetImportForm"
                >
                  Reset
                </button>
              </div>
            </form>

            <div v-if="importSuccess" class="alert alert-success" role="alert">
              <i class="fas fa-check-circle me-2"></i>
              {{ importSuccess }}
            </div>

            <div v-if="importResponse && importResponse.errors && importResponse.errors.length > 0">
              <h6 class="mt-4 mb-2">Import Errors</h6>
              <table class="table table-sm table-bordered">
                <thead>
                  <tr>
                    <th>Row</th>
                    <th>Item Code</th>
                    <th>Month</th>
                    <th>Error</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(error, index) in importResponse.errors" :key="index">
                    <td>{{ error.row }}</td>
                    <td>{{ error.item_code }}</td>
                    <td>{{ error.month || 'N/A' }}</td>
                    <td>{{ error.error }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- System Generate Section -->
      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Generate System Forecasts</h5>
          </div>
          <div class="card-body">
            <form @submit.prevent="generateForecast" class="mb-3">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Start Period</label>
                    <input
                      type="month"
                      class="form-control"
                      v-model="generateForm.start_period"
                      required
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">End Period</label>
                    <input
                      type="month"
                      class="form-control"
                      v-model="generateForm.end_period"
                      required
                    />
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Customer (Optional)</label>
                <select
                  class="form-select"
                  v-model="generateForm.customer_id"
                >
                  <option value="">All Customers</option>
                  <option
                    v-for="customer in customers"
                    :key="customer.customer_id"
                    :value="customer.customer_id"
                  >
                    {{ customer.name }}
                  </option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Item (Optional)</label>
                <select
                  class="form-select"
                  v-model="generateForm.item_id"
                >
                  <option value="">All Items</option>
                  <option
                    v-for="item in items"
                    :key="item.item_id"
                    :value="item.item_id"
                  >
                    {{ item.item_code }} - {{ item.name }}
                  </option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Forecast Method</label>
                <select
                  class="form-select"
                  v-model="generateForm.method"
                  required
                >
                  <option value="trend">Trend (Linear Regression)</option>
                  <option value="weighted">Weighted Average</option>
                  <option value="average">Simple Average</option>
                </select>
                <div class="form-text">
                  <strong>Trend:</strong> Uses linear regression based on historical data<br>
                  <strong>Weighted Average:</strong> Gives more weight to recent periods<br>
                  <strong>Simple Average:</strong> Calculates the average of historical quantities
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Forecast Issue Date (Optional)</label>
                <input
                  type="date"
                  class="form-control"
                  v-model="generateForm.forecast_issue_date"
                />
                <div class="form-text">
                  Defaults to today's date if not specified
                </div>
              </div>

              <div v-if="generateError" class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ generateError }}
              </div>

              <div class="d-flex">
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="generating"
                >
                  <i v-if="generating" class="fas fa-spinner fa-spin me-2"></i>
                  <i v-else class="fas fa-magic me-2"></i>
                  Generate Forecasts
                </button>
                <button
                  type="button"
                  class="btn btn-outline-secondary ms-2"
                  @click="resetGenerateForm"
                >
                  Reset
                </button>
              </div>
            </form>

            <div v-if="generateSuccess" class="alert alert-success" role="alert">
              <i class="fas fa-check-circle me-2"></i>
              {{ generateSuccess }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">CSV Import Format</h5>
      </div>
      <div class="card-body">
        <h6>Required Format</h6>
        <p>
          The CSV file must contain an <code>item_code</code> column and at least one month column in <code>YYYY-MM</code> format (e.g., <code>2025-05</code>).
        </p>

        <h6>Example</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead>
              <tr>
                <th>item_code</th>
                <th>2025-05</th>
                <th>2025-06</th>
                <th>2025-07</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>ITEM001</td>
                <td>100</td>
                <td>120</td>
                <td>140</td>
              </tr>
              <tr>
                <td>ITEM002</td>
                <td>50</td>
                <td>60</td>
                <td>70</td>
              </tr>
            </tbody>
          </table>
        </div>

        <h6 class="mt-3">Notes</h6>
        <ul>
          <li>Each row represents one item's forecast</li>
          <li>Month columns (YYYY-MM) represent forecast quantities for that period</li>
          <li>Empty cells will be ignored</li>
          <li>Negative values are not allowed</li>
          <li>Commas in numbers (e.g., 1,000) will be automatically handled</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ImportForecastForm',
  data() {
    return {
      // Import form data
      importForm: {
        customer_id: '',
        forecast_issue_date: this.getTodayFormatted(),
        csv_file: null,
        fill_missing_periods: false
      },
      importing: false,
      importError: null,
      importSuccess: null,
      importResponse: null,
      
      // Generate form data
      generateForm: {
        start_period: this.getCurrentMonth(),
        end_period: this.getMonthsLater(6),
        customer_id: '',
        item_id: '',
        method: 'trend',
        forecast_issue_date: this.getTodayFormatted()
      },
      generating: false,
      generateError: null,
      generateSuccess: null,
      
      // Lists for dropdowns
      customers: [],
      items: []
    };
  },
  created() {
    this.fetchCustomers();
    this.fetchItems();
  },
  methods: {
    // Common utility methods
    getTodayFormatted() {
      const today = new Date();
      const year = today.getFullYear();
      const month = (today.getMonth() + 1).toString().padStart(2, '0');
      const day = today.getDate().toString().padStart(2, '0');
      return `${year}-${month}-${day}`;
    },
    getCurrentMonth() {
      const today = new Date();
      const year = today.getFullYear();
      const month = (today.getMonth() + 1).toString().padStart(2, '0');
      return `${year}-${month}`;
    },
    getMonthsLater(months) {
      const date = new Date();
      date.setMonth(date.getMonth() + months);
      const year = date.getFullYear();
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      return `${year}-${month}`;
    },
    
    // Data fetching methods
    async fetchCustomers() {
      try {
        const response = await axios.get('/customers');
        this.customers = response.data.data || [];
      } catch (error) {
        console.error('Error fetching customers:', error);
      }
    },
    async fetchItems() {
      try {
        const response = await axios.get('/items', {
            params: { sellable: true }
          });
        this.items = response.data.data || [];
      } catch (error) {
        console.error('Error fetching items:', error);
      }
    },
    
    // Import methods
    handleFileUpload(event) {
      this.importForm.csv_file = event.target.files[0];
    },
    async importForecast() {
      if (!this.importForm.customer_id || !this.importForm.forecast_issue_date || !this.importForm.csv_file) {
        this.importError = 'Please fill in all required fields and select a CSV file';
        return;
      }

      this.importing = true;
      this.importError = null;
      this.importSuccess = null;
      this.importResponse = null;

      try {
        const formData = new FormData();
        formData.append('customer_id', this.importForm.customer_id);
        formData.append('forecast_issue_date', this.importForm.forecast_issue_date);
        formData.append('csv_file', this.importForm.csv_file);
        formData.append('fill_missing_periods', this.importForm.fill_missing_periods ? 1 : 0);

        const response = await axios.post('/forecasts/import', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });

        this.importResponse = response.data;
        
        if (response.data.message) {
          this.importSuccess = response.data.message;
        }
      } catch (error) {
        console.error('Error importing forecast:', error);
        this.importError = error.response?.data?.message || 'An error occurred while importing the forecast';
      } finally {
        this.importing = false;
      }
    },
    resetImportForm() {
      this.importForm = {
        customer_id: '',
        forecast_issue_date: this.getTodayFormatted(),
        csv_file: null,
        fill_missing_periods: false
      };
      this.importError = null;
      this.importSuccess = null;
      this.importResponse = null;
      
      // Reset file input
      const fileInput = document.querySelector('input[type="file"]');
      if (fileInput) {
        fileInput.value = '';
      }
    },
    
    // Generate methods
    async generateForecast() {
      if (!this.generateForm.start_period || !this.generateForm.end_period || !this.generateForm.method) {
        this.generateError = 'Please fill in all required fields';
        return;
      }

      // Validate end period is after start period
      if (this.generateForm.end_period <= this.generateForm.start_period) {
        this.generateError = 'End period must be after start period';
        return;
      }

      this.generating = true;
      this.generateError = null;
      this.generateSuccess = null;

      try {
        const payload = {
          start_period: `${this.generateForm.start_period}-01`,
          end_period: `${this.generateForm.end_period}-01`,
          method: this.generateForm.method
        };

        // Add optional parameters if they exist
        if (this.generateForm.customer_id) {
          payload.customer_id = this.generateForm.customer_id;
        }

        if (this.generateForm.item_id) {
          payload.item_id = this.generateForm.item_id;
        }

        if (this.generateForm.forecast_issue_date) {
          payload.forecast_issue_date = this.generateForm.forecast_issue_date;
        }

        const response = await axios.post('/forecasts/generate', payload);
        
        if (response.data.message) {
          this.generateSuccess = response.data.message;
        }
      } catch (error) {
        console.error('Error generating forecast:', error);
        this.generateError = error.response?.data?.message || 'An error occurred while generating the forecast';
      } finally {
        this.generating = false;
      }
    },
    resetGenerateForm() {
      this.generateForm = {
        start_period: this.getCurrentMonth(),
        end_period: this.getMonthsLater(6),
        customer_id: '',
        item_id: '',
        method: 'trend',
        forecast_issue_date: this.getTodayFormatted()
      };
      this.generateError = null;
      this.generateSuccess = null;
    }
  }
};
</script>

<style scoped>
/* Styling yang disempurnakan untuk ImportForecastForm.vue */

.page-container {
  padding: 2rem;
  max-width: 1600px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 2.5rem;
  border-bottom: 1px solid #eaeaea;
  padding-bottom: 1.5rem;
  position: relative;
}

.page-header:after {
  content: "";
  position: absolute;
  bottom: -1px;
  left: 0;
  height: 3px;
  width: 100px;
  background: linear-gradient(to right, #4361ee, #7048e8);
  border-radius: 3px;
}

.page-header h2 {
  font-size: 1.8rem;
  margin-bottom: 0.5rem;
  color: #2d3748;
  font-weight: 600;
}

.page-header .text-muted {
  font-size: 1rem;
  color: #718096;
}

/* Card styling */
.card {
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
  border: none;
  margin-bottom: 1.5rem;
  transition: all 0.3s ease;
  height: 100%;
}

.card:hover {
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.card-body {
  padding: 1.5rem;
}

.card-header {
  background-color: #fff;
  border-bottom: 1px solid #eef0f2;
  padding: 1.25rem 1.5rem;
}

.card-header h5 {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 0;
  font-size: 1.2rem;
}

/* Form styling */
.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #2d3748;
  font-size: 0.9rem;
}

.form-control,
.form-select {
  border-radius: 6px;
  padding: 0.65rem 1rem;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
  font-size: 0.95rem;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.form-control:focus,
.form-select:focus {
  border-color: #4361ee;
  box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
}

.form-text {
  margin-top: 0.4rem;
  font-size: 0.85rem;
  color: #718096;
}

/* Custom file input styling */
input[type="file"].form-control {
  padding: 0.5rem;
  background-color: #f8fafc;
  font-size: 0.9rem;
}

/* Proper spacing for form groups */
.mb-3 {
  margin-bottom: 1.25rem !important;
}

/* Checkbox styling */
.form-check {
  padding-left: 1.8rem;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
}

.form-check-input {
  margin-left: -1.8rem;
  width: 1.1rem;
  height: 1.1rem;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
}

.form-check-input:checked {
  background-color: #4361ee;
  border-color: #4361ee;
}

.form-check-label {
  color: #2d3748;
  font-size: 0.95rem;
}

/* Button styling */
.btn {
  border-radius: 6px;
  font-weight: 500;
  padding: 0.65rem 1.25rem;
  transition: all 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn i {
  margin-right: 0.5rem;
}

.btn-primary {
  background-color: #4361ee;
  border-color: #4361ee;
}

.btn-primary:hover,
.btn-primary:active {
  background-color: #3a56d4;
  border-color: #3a56d4;
  box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
}

.btn-outline-secondary {
  color: #718096;
  border-color: #e2e8f0;
  background-color: white;
}

.btn-outline-secondary:hover {
  color: #2d3748;
  background-color: #f8fafc;
  border-color: #718096;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Alert styling */
.alert {
  border: none;
  border-radius: 6px;
  padding: 1rem 1.25rem;
  margin-top: 1.5rem;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.alert i {
  font-size: 1.1rem;
}

.alert-success {
  background-color: rgba(46, 204, 113, 0.15);
  color: #1e8449;
  border-left: 4px solid #2ecc71;
}

.alert-danger {
  background-color: rgba(231, 76, 60, 0.15);
  color: #a93226;
  border-left: 4px solid #e74c3c;
}

/* Table styling */
.table-responsive {
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
  margin-top: 1rem;
}

.table {
  margin-bottom: 0;
  font-size: 0.9rem;
}

.table th {
  background-color: #f8fafc;
  font-weight: 600;
  color: #2d3748;
  border-top: none;
  border-bottom: 2px solid #e2e8f0;
  padding: 0.75rem 1rem;
}

.table td {
  padding: 0.75rem 1rem;
  vertical-align: middle;
  border-color: #e2e8f0;
}

.table-bordered,
.table-bordered td,
.table-bordered th {
  border-color: #e2e8f0;
}

.table-sm td,
.table-sm th {
  padding: 0.5rem 0.75rem;
}

/* Code styling */
code {
  font-size: 0.9em;
  background-color: #f8fafc;
  padding: 0.2em 0.4em;
  border-radius: 4px;
  color: #e83e8c;
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

/* Documentation section styling */
.card h6 {
  font-size: 1.1rem;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: #2d3748;
}

.card h6:first-child {
  margin-top: 0;
}

.card ul {
  padding-left: 1.5rem;
}

.card ul li {
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
  color: #2d3748;
}

/* Row styling */
.row {
  margin-left: -0.75rem;
  margin-right: -0.75rem;
}

.row > [class*="col-"] {
  padding-left: 0.75rem;
  padding-right: 0.75rem;
}

/* Spinner animation */
.fa-spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
  .page-container {
    padding: 1.5rem;
  }
  
  .card-body {
    padding: 1.25rem;
  }
  
  .row .col-lg-6 {
    margin-bottom: 1.5rem;
  }
  
  .row .col-lg-6:last-child {
    margin-bottom: 0;
  }
}

@media (max-width: 767.98px) {
  .page-container {
    padding: 1rem;
  }
  
  .page-header h2 {
    font-size: 1.5rem;
  }
  
  .d-flex {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    margin-bottom: 0.75rem;
  }
  
  .ms-2 {
    margin-left: 0 !important;
  }
  
  .card-header,
  .card-body {
    padding: 1rem;
  }
  
  .table td,
  .table th {
    padding: 0.5rem 0.75rem;
  }
}
</style>