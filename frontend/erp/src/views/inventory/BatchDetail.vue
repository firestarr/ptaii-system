<!-- src/views/inventory/BatchDetail.vue -->
<template>
    <div class="batch-detail-container">
      <div class="header-section">
        <div class="header-title">
          <router-link :to="`/items/${itemId}/batches`" class="back-link">
            <i class="fas fa-arrow-left"></i>
          </router-link>
          <h2>Batch Details</h2>
        </div>
        <div class="header-actions">
          <router-link :to="`/items/${itemId}/batches/${id}/edit`" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Batch
          </router-link>
        </div>
      </div>
      
      <div v-if="loading" class="loading-section">
        <div class="spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading batch details...</p>
      </div>
      
      <div v-else-if="!batch" class="error-section">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Batch Not Found</h3>
        <p>The requested batch information could not be found.</p>
        <router-link :to="`/items/${itemId}/batches`" class="btn btn-primary">
          Back to Batches List
        </router-link>
      </div>
      
      <div v-else class="detail-content">
        <div class="item-info-card" v-if="item">
          <div class="card-header">
            <h3>Item Information</h3>
            <router-link :to="`/items/${itemId}`" class="btn btn-text">
              View Item Details
            </router-link>
          </div>
          <div class="card-content">
            <div class="info-row">
              <span class="info-label">Item Code:</span>
              <span class="info-value code">{{ item.item_code }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Item Name:</span>
              <span class="info-value">{{ item.name }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Category:</span>
              <span class="info-value">{{ item.category ? item.category.name : 'N/A' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Unit of Measure:</span>
              <span class="info-value">{{ item.unit_of_measure ? item.unit_of_measure.name : 'N/A' }}</span>
            </div>
          </div>
        </div>
        
        <div class="detail-cards">
          <div class="detail-card">
            <div class="card-header">
              <h3>Batch Information</h3>
            </div>
            <div class="card-content">
              <div class="info-row">
                <span class="info-label">Batch Number:</span>
                <span class="info-value code">{{ batch.batch_number }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Lot Number:</span>
                <span class="info-value">{{ batch.lot_number || 'N/A' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Manufacturing Date:</span>
                <span class="info-value">{{ formatDate(batch.manufacturing_date) || 'N/A' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Expiry Date:</span>
                <span class="info-value" :class="getExpiryClass()">
                  {{ formatDate(batch.expiry_date) || 'N/A' }}
                  <span v-if="batch.expiry_date" class="expiry-status">
                    {{ getExpiryText() }}
                  </span>
                </span>
              </div>
              <div class="info-row">
                <span class="info-label">Created At:</span>
                <span class="info-value">{{ formatDateTime(batch.created_at) }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Updated At:</span>
                <span class="info-value">{{ formatDateTime(batch.updated_at) }}</span>
              </div>
            </div>
          </div>
          
          <div class="detail-card">
            <div class="card-header">
              <h3>Stock Information</h3>
            </div>
            <div v-if="stockTransactions.length === 0" class="card-empty-state">
              <p>No stock transactions found for this batch.</p>
            </div>
            <div v-else class="card-content">
              <div class="stock-summary">
                <div class="summary-item">
                  <div class="summary-value">{{ totalStock }}</div>
                  <div class="summary-label">Current Stock</div>
                </div>
                <div class="summary-item">
                  <div class="summary-value">{{ totalReceived }}</div>
                  <div class="summary-label">Total Received</div>
                </div>
                <div class="summary-item">
                  <div class="summary-value">{{ totalIssued }}</div>
                  <div class="summary-label">Total Issued</div>
                </div>
              </div>
              
              <div class="stock-transaction-list">
                <h4>Recent Transactions</h4>
                <table class="transactions-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Type</th>
                      <th>Quantity</th>
                      <th>Warehouse</th>
                      <th>Reference</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="transaction in recentTransactions" :key="transaction.transaction_id">
                      <td>{{ formatDate(transaction.transaction_date) }}</td>
                      <td>
                        <span :class="getTransactionTypeClass(transaction.transaction_type)">
                          {{ formatTransactionType(transaction.transaction_type) }}
                        </span>
                      </td>
                      <td :class="transaction.quantity >= 0 ? 'positive-qty' : 'negative-qty'">
                        {{ formatQuantity(transaction.quantity) }}
                      </td>
                      <td>{{ transaction.warehouse ? transaction.warehouse.name : 'N/A' }}</td>
                      <td class="reference-cell">
                        <span v-if="transaction.reference_document && transaction.reference_number" class="reference-tag">
                          {{ formatReferenceDocument(transaction.reference_document) }} #{{ transaction.reference_number }}
                        </span>
                        <span v-else>-</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
                
                <div v-if="stockTransactions.length > 5" class="view-all-link">
                  <router-link :to="`/stock-transactions/items/${itemId}/movement?batch_id=${id}`">
                    View All Transactions
                  </router-link>
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
    name: 'BatchDetail',
    props: {
      itemId: {
        type: [String, Number],
        required: true
      },
      id: {
        type: [String, Number],
        required: true
      }
    },
    data() {
      return {
        batch: null,
        item: null,
        stockTransactions: [],
        loading: true
      };
    },
    computed: {
      recentTransactions() {
        return this.stockTransactions.slice(0, 5);
      },
      totalStock() {
        return this.stockTransactions.reduce((sum, transaction) => sum + transaction.quantity, 0);
      },
      totalReceived() {
        return this.stockTransactions
          .filter(t => t.quantity > 0)
          .reduce((sum, transaction) => sum + transaction.quantity, 0);
      },
      totalIssued() {
        return Math.abs(
          this.stockTransactions
            .filter(t => t.quantity < 0)
            .reduce((sum, transaction) => sum + transaction.quantity, 0)
        );
      }
    },
    created() {
      this.fetchBatchDetails();
    },
    methods: {
      async fetchBatchDetails() {
        this.loading = true;
        
        try {
          const batchResponse = await axios.get(`/items/${this.itemId}/batches/${this.id}`);
          
          if (batchResponse.data.success) {
            this.batch = batchResponse.data.data;
            
            // Add expiry status
            if (this.batch.expiry_date) {
              const expiryDate = new Date(this.batch.expiry_date);
              const today = new Date();
              today.setHours(0, 0, 0, 0);
              
              this.batch.is_expired = expiryDate < today;
              
              if (!this.batch.is_expired) {
                const diffTime = expiryDate.getTime() - today.getTime();
                this.batch.days_until_expiry = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
              } else {
                this.batch.days_until_expiry = 0;
              }
            }
            
            // Fetch item details
            const itemResponse = await axios.get(`/items/${this.itemId}`);
            if (itemResponse.data.success) {
              this.item = itemResponse.data.data;
            }
            
            // Fetch stock transactions
            // Note: This endpoint would need to be implemented on the backend
            try {
              const transactionsResponse = await axios.get(`/transactions`, {
                params: {
                  item_id: this.itemId,
                  batch_id: this.id
                }
              });
              
              if (transactionsResponse.data.success) {
                this.stockTransactions = transactionsResponse.data.data;
              }
            } catch (error) {
              console.error('Error fetching stock transactions:', error);
              this.stockTransactions = [];
            }
          }
        } catch (error) {
          console.error('Error fetching batch details:', error);
          this.batch = null;
        } finally {
          this.loading = false;
        }
      },
      formatDate(dateString) {
        if (!dateString) return null;
        
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      },
      formatDateTime(dateString) {
        if (!dateString) return null;
        
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      },
      getExpiryClass() {
        if (!this.batch || !this.batch.expiry_date) return '';
        
        if (this.batch.is_expired) return 'text-expired';
        if (this.batch.days_until_expiry <= 30) return 'text-warning';
        return 'text-valid';
      },
      getExpiryText() {
        if (!this.batch || !this.batch.expiry_date) return '';
        
        if (this.batch.is_expired) return '(Expired)';
        if (this.batch.days_until_expiry <= 30) return `(Expires in ${this.batch.days_until_expiry} days)`;
        return '';
      },
      formatQuantity(quantity) {
        return quantity.toLocaleString('id-ID');
      },
      formatTransactionType(type) {
        switch (type) {
          case 'receive':
            return 'Receive';
          case 'issue':
            return 'Issue';
          case 'transfer':
            return 'Transfer';
          case 'adjustment':
            return 'Adjustment';
          case 'return':
            return 'Return';
          default:
            return type.charAt(0).toUpperCase() + type.slice(1);
        }
      },
      getTransactionTypeClass(type) {
        switch (type) {
          case 'receive':
          case 'return':
            return 'transaction-receive';
          case 'issue':
          case 'transfer':
            return 'transaction-issue';
          case 'adjustment':
            return 'transaction-adjustment';
          default:
            return '';
        }
      },
      formatReferenceDocument(docType) {
        switch (docType) {
          case 'goods_receipt':
            return 'GR';
          case 'stock_transfer':
            return 'ST';
          case 'stock_adjustment':
            return 'SA';
          case 'sales_order':
            return 'SO';
          case 'purchase_order':
            return 'PO';
          default:
            return docType.replace('_', ' ').toUpperCase();
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .batch-detail-container {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
  }
  
  .header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .header-title {
    display: flex;
    align-items: center;
  }
  
  .header-title h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
  }
  
  .back-link {
    font-size: 1.25rem;
    color: var(--gray-600);
    margin-right: 0.75rem;
    display: flex;
    align-items: center;
    transition: color 0.2s;
  }
  
  .back-link:hover {
    color: var(--primary-color);
  }
  
  .loading-section, .error-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .spinner {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
  }
  
  .error-icon {
    font-size: 2.5rem;
    color: #ef4444;
    margin-bottom: 1rem;
  }
  
  .error-section h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
  }
  
  .error-section p {
    color: var(--gray-500);
    margin-bottom: 1.5rem;
  }
  
  .detail-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }
  
  .item-info-card, .detail-card {
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    overflow: hidden;
  }
  
  .detail-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
  }
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .card-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .card-content {
    padding: 1.5rem;
  }
  
  .card-empty-state {
    padding: 2rem;
    text-align: center;
    color: var(--gray-500);
  }
  
  .info-row {
    display: flex;
    margin-bottom: 0.75rem;
  }
  
  .info-row:last-child {
    margin-bottom: 0;
  }
  
  .info-label {
    font-weight: 500;
    color: var(--gray-600);
    width: 40%;
  }
  
  .info-value {
    color: var(--gray-800);
    flex: 1;
  }
  
  .info-value.code {
    font-family: monospace;
    background-color: var(--gray-100);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
  }
  
  .expiry-status {
    font-size: 0.875rem;
    margin-left: 0.5rem;
  }
  
  .text-expired {
    color: #ef4444;
  }
  
  .text-warning {
    color: #f59e0b;
  }
  
  .text-valid {
    color: #10b981;
  }
  
  .stock-summary {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .summary-item {
    flex: 1;
    text-align: center;
  }
  
  .summary-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
  }
  
  .summary-label {
    font-size: 0.875rem;
    color: var(--gray-500);
  }
  
  .stock-transaction-list h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 1rem;
    color: var(--gray-700);
  }
  
  .transactions-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .transactions-table th {
    text-align: left;
    padding: 0.625rem;
    border-bottom: 1px solid var(--gray-200);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .transactions-table td {
    padding: 0.625rem;
    border-bottom: 1px solid var(--gray-100);
  }
  
  .positive-qty {
    color: #10b981;
    font-weight: 500;
  }
  
  .negative-qty {
    color: #ef4444;
    font-weight: 500;
  }
  
  .transaction-receive {
    color: #10b981;
    font-weight: 500;
  }
  
  .transaction-issue {
    color: #ef4444;
    font-weight: 500;
  }
  
  .transaction-adjustment {
    color: #f59e0b;
    font-weight: 500;
  }
  
  .reference-tag {
    display: inline-block;
    background-color: var(--gray-100);
    color: var(--gray-700);
    font-size: 0.75rem;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
  }
  
  .view-all-link {
    margin-top: 1rem;
    text-align: center;
  }
  
  .view-all-link a {
    color: var(--primary-color);
    font-size: 0.875rem;
    font-weight: 500;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .btn-text {
    background: none;
    color: var(--primary-color);
    padding: 0.25rem 0.5rem;
  }
  
  .btn-text:hover {
    background-color: var(--gray-100);
    text-decoration: none;
  }
  
  .btn i {
    margin-right: 0.5rem;
  }
  
  @media (max-width: 768px) {
    .header-section {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .detail-cards {
      grid-template-columns: 1fr;
    }
    
    .stock-summary {
      flex-direction: column;
      gap: 1rem;
    }
    
    .transactions-table {
      display: block;
      overflow-x: auto;
    }
  }
  </style>