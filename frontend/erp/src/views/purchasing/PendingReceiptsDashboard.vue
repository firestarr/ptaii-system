<!-- src/views/purchasing/PendingReceiptsDashboard.vue -->
<template>
    <div class="pending-receipts-dashboard">
      <div class="card">
        <div class="card-header">
          <h2>Pending Receipts Dashboard</h2>
          <div class="actions">
            <router-link to="/purchasing/goods-receipts" class="btn btn-secondary">
              <i class="fas fa-list"></i> All Receipts
            </router-link>
            <router-link to="/purchasing/goods-receipts/create" class="btn btn-primary">
              <i class="fas fa-plus"></i> Create Receipt
            </router-link>
          </div>
        </div>
  
        <div class="card-body">
          <!-- Loading indicator -->
          <div v-if="loading" class="loading-container">
            <i class="fas fa-spinner fa-spin"></i> Loading dashboard data...
          </div>
  
          <div v-else>
            <!-- Summary Cards -->
            <div class="summary-cards">
              <div class="summary-card">
                <div class="summary-icon total-receipts">
                  <i class="fas fa-receipt"></i>
                </div>
                <div class="summary-content">
                  <span class="summary-label">Pending Receipts</span>
                  <span class="summary-value">{{ metrics.pendingReceipts }}</span>
                </div>
              </div>
  
              <div class="summary-card">
                <div class="summary-icon total-items">
                  <i class="fas fa-box"></i>
                </div>
                <div class="summary-content">
                  <span class="summary-label">Items to Receive</span>
                  <span class="summary-value">{{ metrics.pendingItems }}</span>
                </div>
              </div>
  
              <div class="summary-card">
                <div class="summary-icon total-vendors">
                  <i class="fas fa-users"></i>
                </div>
                <div class="summary-content">
                  <span class="summary-label">Vendors</span>
                  <span class="summary-value">{{ metrics.vendorCount }}</span>
                </div>
              </div>
  
              <div class="summary-card">
                <div class="summary-icon overdue">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="summary-content">
                  <span class="summary-label">Overdue Receipts</span>
                  <span class="summary-value">{{ metrics.overdueCount }}</span>
                </div>
              </div>
            </div>
  
            <!-- Pending Receipts -->
            <div class="dashboard-section">
              <div class="section-header">
                <h3>Pending Receipts</h3>
                <div class="section-actions">
                  <div class="search-box">
                    <input
                      type="text"
                      v-model="pendingReceiptsSearch"
                      placeholder="Search receipt or vendor..."
                      @input="filterPendingReceipts"
                    />
                    <i class="fas fa-search"></i>
                  </div>
                </div>
              </div>
              
              <div v-if="filteredPendingReceipts.length === 0" class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No pending receipts found</p>
              </div>
              
              <div v-else class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Receipt Number</th>
                      <th>Date</th>
                      <th>Vendor</th>
                      <th>Items</th>
                      <th>PO Numbers</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="receipt in filteredPendingReceipts" :key="receipt.receipt_id">
                      <td>{{ receipt.receipt_number }}</td>
                      <td>{{ formatDate(receipt.receipt_date) }}</td>
                      <td>{{ receipt.vendor ? receipt.vendor.name : 'N/A' }}</td>
                      <td>{{ receipt.lines.length }}</td>
                      <td>{{ receipt.po_numbers }}</td>
                      <td>
                        <div class="table-actions">
                          <router-link :to="`/purchasing/goods-receipts/${receipt.receipt_id}`" class="btn-icon" title="View Details">
                            <i class="fas fa-eye"></i>
                          </router-link>
                          <router-link :to="`/purchasing/goods-receipts/${receipt.receipt_id}/confirm`" class="btn-icon confirm" title="Confirm Receipt">
                            <i class="fas fa-check-circle"></i>
                          </router-link>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
  
            <!-- Outstanding Purchase Orders -->
            <div class="dashboard-section">
              <div class="section-header">
                <h3>Outstanding Purchase Orders</h3>
                <div class="section-actions">
                  <div class="search-box">
                    <input
                      type="text"
                      v-model="outstandingPOsSearch"
                      placeholder="Search PO or vendor..."
                      @input="filterOutstandingPOs"
                    />
                    <i class="fas fa-search"></i>
                  </div>
                </div>
              </div>
              
              <div v-if="filteredOutstandingPOs.length === 0" class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No outstanding purchase orders found</p>
              </div>
              
              <div v-else class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>PO Number</th>
                      <th>Date</th>
                      <th>Vendor</th>
                      <th>Expected Delivery</th>
                      <th>Status</th>
                      <th>Items</th>
                      <th>Progress</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="po in filteredOutstandingPOs" :key="po.po_id">
                      <td>{{ po.po_number }}</td>
                      <td>{{ formatDate(po.po_date) }}</td>
                      <td>{{ po.vendor ? po.vendor.name : 'N/A' }}</td>
                      <td>
                        <span :class="{ 'overdue-date': isOverdue(po.expected_delivery) }">
                          {{ formatDate(po.expected_delivery) }}
                        </span>
                      </td>
                      <td>
                        <span :class="'status-badge ' + po.status">
                          {{ po.status }}
                        </span>
                      </td>
                      <td>{{ po.total_items }}</td>
                      <td>
                        <div class="progress-bar">
                          <div class="progress-fill" :style="{ width: po.progress + '%' }"></div>
                        </div>
                        <div class="progress-text">{{ po.progress }}% received</div>
                      </td>
                      <td>
                        <div class="table-actions">
                          <router-link :to="`/purchasing/orders/${po.po_id}`" class="btn-icon" title="View PO">
                            <i class="fas fa-eye"></i>
                          </router-link>
                          <router-link 
                            to="/purchasing/goods-receipts/create" 
                            class="btn-icon create" 
                            title="Create Receipt"
                            @click="selectPOForReceipt(po)"
                          >
                            <i class="fas fa-plus"></i>
                          </router-link>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
  
            <!-- Vendor Performance -->
            <div class="dashboard-section">
              <div class="section-header">
                <h3>Vendor Receipt Performance</h3>
              </div>
              
              <div v-if="vendorPerformance.length === 0" class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>No vendor performance data available</p>
              </div>
              
              <div v-else class="vendor-performance-grid">
                <div v-for="vendor in vendorPerformance" :key="vendor.vendor_id" class="vendor-card">
                  <h4>{{ vendor.name }}</h4>
                  <div class="vendor-metrics">
                    <div class="metric">
                      <span class="metric-label">On-Time Delivery:</span>
                      <span class="metric-value">{{ vendor.on_time_percentage }}%</span>
                    </div>
                    <div class="metric">
                      <span class="metric-label">Avg. Receipt Time:</span>
                      <span class="metric-value">{{ vendor.avg_receipt_days }} days</span>
                    </div>
                    <div class="metric">
                      <span class="metric-label">Receipts Pending:</span>
                      <span class="metric-value">{{ vendor.pending_receipts }}</span>
                    </div>
                  </div>
                  <div class="vendor-chart">
                    <div class="on-time-bar">
                      <div class="on-time-fill" :style="{ width: vendor.on_time_percentage + '%' }"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'PendingReceiptsDashboard',
    data() {
      return {
        loading: true,
        metrics: {
          pendingReceipts: 0,
          pendingItems: 0,
          vendorCount: 0,
          overdueCount: 0
        },
        pendingReceipts: [],
        outstandingPOs: [],
        vendorPerformance: [],
        pendingReceiptsSearch: '',
        outstandingPOsSearch: '',
        filteredPendingReceipts: [],
        filteredOutstandingPOs: []
      };
    },
    created() {
      this.fetchDashboardData();
    },
    methods: {
      fetchDashboardData() {
        this.loading = true;
        
        // For a real implementation, you would create a specific API endpoint for the dashboard
        // This is a simulation using existing endpoints
        Promise.all([
          this.fetchPendingReceipts(),
          this.fetchOutstandingPOs(),
          this.fetchVendorPerformance()
        ])
          .then(() => {
            // Calculate metrics
            this.calculateMetrics();
          })
          .catch(error => {
            console.error('Error fetching dashboard data:', error);
            this.$toast.error('Failed to load dashboard data');
          })
          .finally(() => {
            this.loading = false;
          });
      },
      fetchPendingReceipts() {
        return axios.get('/api/goods-receipts', { params: { status: 'pending' } })
          .then(response => {
            this.pendingReceipts = response.data.data.data || [];
            this.filteredPendingReceipts = [...this.pendingReceipts];
          });
      },
      fetchOutstandingPOs() {
        return axios.get('/api/purchase-orders', { 
          params: { 
            status: ['sent', 'partial'],
            outstanding: true 
          } 
        })
          .then(response => {
            this.outstandingPOs = response.data.data || [];
            
            // Calculate progress for each PO
            this.outstandingPOs = this.outstandingPOs.map(po => {
              let received = 0;
              let total = 0;
              
              if (po.lines && po.lines.length > 0) {
                po.lines.forEach(line => {
                  const lineReceived = line.received_quantity || 0;
                  const lineTotal = line.quantity || 0;
                  
                  received += lineReceived;
                  total += lineTotal;
                });
                
                po.progress = total > 0 ? Math.round((received / total) * 100) : 0;
                po.total_items = po.lines.length;
              } else {
                po.progress = 0;
                po.total_items = 0;
              }
              
              return po;
            });
            
            this.filteredOutstandingPOs = [...this.outstandingPOs];
          });
      },
      fetchVendorPerformance() {
        // In a real implementation, this would be a specific endpoint
        // Here we'll simulate it with sample data based on vendors in the POs
        return Promise.resolve().then(() => {
          const vendors = {};
          
          // Collect vendor IDs from POs
          this.outstandingPOs.forEach(po => {
            if (po.vendor && po.vendor.vendor_id) {
              if (!vendors[po.vendor.vendor_id]) {
                vendors[po.vendor.vendor_id] = {
                  vendor_id: po.vendor.vendor_id,
                  name: po.vendor.name,
                  total_pos: 0,
                  on_time_deliveries: 0,
                  total_deliveries: 0,
                  receipt_days: [],
                  pending_receipts: 0
                };
              }
              
              vendors[po.vendor.vendor_id].total_pos++;
              
              // Simulate some random performance data
              const onTime = Math.random() > 0.3;
              if (onTime) {
                vendors[po.vendor.vendor_id].on_time_deliveries++;
              }
              vendors[po.vendor.vendor_id].total_deliveries++;
              
              // Random receipt days between 1 and 10
              vendors[po.vendor.vendor_id].receipt_days.push(Math.floor(Math.random() * 10) + 1);
            }
          });
          
          // Count pending receipts per vendor
          this.pendingReceipts.forEach(receipt => {
            if (receipt.vendor && receipt.vendor.vendor_id && vendors[receipt.vendor.vendor_id]) {
              vendors[receipt.vendor.vendor_id].pending_receipts++;
            }
          });
          
          // Calculate averages and percentages
          this.vendorPerformance = Object.values(vendors).map(vendor => {
            return {
              vendor_id: vendor.vendor_id,
              name: vendor.name,
              on_time_percentage: vendor.total_deliveries > 0 
                ? Math.round((vendor.on_time_deliveries / vendor.total_deliveries) * 100) 
                : 0,
              avg_receipt_days: vendor.receipt_days.length > 0 
                ? Math.round(vendor.receipt_days.reduce((sum, days) => sum + days, 0) / vendor.receipt_days.length * 10) / 10
                : 0,
              pending_receipts: vendor.pending_receipts
            };
          });
        });
      },
      calculateMetrics() {
        this.metrics = {
          pendingReceipts: this.pendingReceipts.length,
          pendingItems: this.pendingReceipts.reduce((sum, receipt) => sum + (receipt.lines ? receipt.lines.length : 0), 0),
          vendorCount: this.vendorPerformance.length,
          overdueCount: this.outstandingPOs.filter(po => this.isOverdue(po.expected_delivery)).length
        };
      },
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { 
          year: 'numeric', 
          month: 'short', 
          day: 'numeric' 
        });
      },
      isOverdue(dateString) {
        if (!dateString) return false;
        const deliveryDate = new Date(dateString);
        const today = new Date();
        return deliveryDate < today;
      },
      filterPendingReceipts() {
        if (!this.pendingReceiptsSearch) {
          this.filteredPendingReceipts = [...this.pendingReceipts];
          return;
        }
        
        const search = this.pendingReceiptsSearch.toLowerCase();
        this.filteredPendingReceipts = this.pendingReceipts.filter(receipt => 
          receipt.receipt_number.toLowerCase().includes(search) ||
          (receipt.vendor && receipt.vendor.name.toLowerCase().includes(search))
        );
      },
      filterOutstandingPOs() {
        if (!this.outstandingPOsSearch) {
          this.filteredOutstandingPOs = [...this.outstandingPOs];
          return;
        }
        
        const search = this.outstandingPOsSearch.toLowerCase();
        this.filteredOutstandingPOs = this.outstandingPOs.filter(po => 
          po.po_number.toLowerCase().includes(search) ||
          (po.vendor && po.vendor.name.toLowerCase().includes(search))
        );
      },
      selectPOForReceipt(po) {
        // Store selected PO in localStorage to use it in the create form
        localStorage.setItem('selectedPOForReceipt', JSON.stringify({
          vendor_id: po.vendor.vendor_id,
          po_ids: [po.po_id]
        }));
      }
    }
  };
  </script>
  
  <style scoped>
  .pending-receipts-dashboard {
    max-width: 100%;
  }
  
  .card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
  }
  
  .card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .card-header h2 {
    margin: 0;
    font-size: 1.5rem;
  }
  
  .actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    border: 1px solid transparent;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
    border-color: var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-300);
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .loading-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    font-size: 1rem;
    color: var(--gray-500);
  }
  
  .loading-container i {
    margin-right: 0.5rem;
  }
  
  .summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }
  
  .summary-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: 0.5rem;
    background-color: white;
    border: 1px solid var(--gray-200);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  }
  
  .summary-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
  }
  
  .summary-icon.total-receipts {
    background-color: #2563eb;
  }
  
  .summary-icon.total-items {
    background-color: #10b981;
  }
  
  .summary-icon.total-vendors {
    background-color: #8b5cf6;
  }
  
  .summary-icon.overdue {
    background-color: #ef4444;
  }
  
  .summary-content {
    display: flex;
    flex-direction: column;
  }
  
  .summary-label {
    font-size: 0.875rem;
    color: var(--gray-500);
    margin-bottom: 0.25rem;
  }
  
  .summary-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .dashboard-section {
    margin-bottom: 2.5rem;
  }
  
  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }
  
  .section-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--gray-800);
  }
  
  .section-actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .search-box {
    position: relative;
    min-width: 250px;
  }
  
  .search-box input {
    width: 100%;
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .search-box i {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .empty-state {
    text-align: center;
    padding: 2rem 0;
    color: var(--gray-500);
  }
  
  .empty-state i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
  }
  
  .table-responsive {
    overflow-x: auto;
    margin-bottom: 1rem;
  }
  
  .data-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .data-table th {
    background-color: var(--gray-50);
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
  }
  
  .data-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-800);
  }
  
  .data-table tr:hover td {
    background-color: var(--gray-50);
  }
  
  .overdue-date {
    color: #dc2626;
    font-weight: 500;
  }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
  }
  
  .status-badge.sent {
    background-color: #dbeafe;
    color: #1e40af;
  }
  
  .status-badge.partial {
    background-color: #fef3c7;
    color: #92400e;
  }
  
  .progress-bar {
    height: 0.5rem;
    background-color: var(--gray-200);
    border-radius: 0.25rem;
    overflow: hidden;
    margin-bottom: 0.25rem;
  }
  
  .progress-fill {
    height: 100%;
    background-color: #10b981;
    border-radius: 0.25rem;
  }
  
  .progress-text {
    font-size: 0.75rem;
    color: var(--gray-600);
  }
  
  .table-actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    color: var(--gray-700);
    background-color: var(--gray-100);
    border: 1px solid var(--gray-200);
    text-decoration: none;
    transition: all 0.2s;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-200);
  }
  
  .btn-icon.confirm {
    color: #059669;
    background-color: #d1fae5;
    border-color: #059669;
  }
  
  .btn-icon.confirm:hover {
    background-color: #a7f3d0;
  }
  
  .btn-icon.create {
    color: #0ea5e9;
    background-color: #e0f2fe;
    border-color: #0ea5e9;
  }
  
  .btn-icon.create:hover {
    background-color: #bae6fd;
  }
  
  .vendor-performance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
  }
  
  .vendor-card {
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  }
  
  .vendor-card h4 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1rem;
    color: var(--gray-800);
  }
  
  .vendor-metrics {
    margin-bottom: 1rem;
  }
  
  .metric {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
  }
  
  .metric-label {
    font-size: 0.875rem;
    color: var(--gray-600);
  }
  
  .metric-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .vendor-chart {
    margin-top: 1rem;
  }
  
  .on-time-bar {
    height: 0.5rem;
    background-color: var(--gray-200);
    border-radius: 0.25rem;
    overflow: hidden;
  }
  
  .on-time-fill {
    height: 100%;
    background-color: #10b981;
    border-radius: 0.25rem;
  }
  
  @media (max-width: 768px) {
    .card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .actions {
      width: 100%;
    }
    
    .actions .btn {
      flex: 1;
      justify-content: center;
    }
    
    .section-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }
    
    .search-box {
      width: 100%;
    }
  }
  </style>