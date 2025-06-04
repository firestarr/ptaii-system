<!-- src/components/inventory/ItemDetailModal.vue -->
<template>
  <div class="modal">
    <div class="modal-backdrop" @click="$emit('close')"></div>
    <div class="modal-content">
      <div class="modal-header">
        <h2>Item Details</h2>
        <button class="close-btn" @click="$emit('close')">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div v-if="item" class="item-details">
          <!-- Basic Information Section -->
          <div class="detail-section">
            <h3 class="section-title">Basic Information</h3>
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Item Code</div>
                <div class="detail-value">{{ item.item_code }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Name</div>
                <div class="detail-value">{{ item.name }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Category</div>
                <div class="detail-value">{{ item.category ? item.category.name : '-' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Unit of Measure</div>
                <div class="detail-value">
                  {{ item.unitOfMeasure ? `${item.unitOfMeasure.name} (${item.unitOfMeasure.symbol})` : '-' }}
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">HS Code</div>
                <div class="detail-value">{{ item.hs_code || '-' }}</div>
              </div>
            </div>
          </div>
          
          <!-- Description Section -->
          <div class="detail-section" v-if="item.description">
            <h3 class="section-title">Description</h3>
            <div class="description">
              {{ item.description }}
            </div>
          </div>
          
          <!-- Physical Properties Section -->
          <div class="detail-section">
            <h3 class="section-title">Physical Properties</h3>
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Length</div>
                <div class="detail-value">{{ item.length || '-' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Width</div>
                <div class="detail-value">{{ item.width || '-' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Thickness</div>
                <div class="detail-value">{{ item.thickness || '-' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Weight</div>
                <div class="detail-value">{{ item.weight || '-' }}</div>
              </div>
              <div class="detail-item" v-if="item.document_path">
                <div class="detail-label">Technical Document</div>
                <div class="detail-value">
                  <a :href="item.document_url" target="_blank" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-pdf"></i> View Document
                  </a>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Stock Information Section -->
          <div class="detail-section">
            <h3 class="section-title">Stock Information</h3>
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Current Stock</div>
                <div class="detail-value">{{ item.current_stock }} {{ item.unitOfMeasure?.symbol || '' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Minimum Stock</div>
                <div class="detail-value">{{ item.minimum_stock }} {{ item.unitOfMeasure?.symbol || '' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Maximum Stock</div>
                <div class="detail-value">{{ item.maximum_stock }} {{ item.unitOfMeasure?.symbol || '' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Stock Status</div>
                <div class="detail-value">
                  <span class="stock-status" :class="getStockStatusClass(item)">
                    {{ getStockStatus(item) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Pricing Information Section -->
          <div class="detail-section">
            <h3 class="section-title">Pricing Information</h3>
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Cost Price</div>
                <div class="detail-value">{{ item.cost_price || '-' }} {{ item.cost_price_currency || 'USD' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Sale Price</div>
                <div class="detail-value">{{ item.sale_price || '-' }} {{ item.sale_price_currency || 'USD' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Purchasable</div>
                <div class="detail-value">
                  <span :class="item.is_purchasable ? 'badge-success' : 'badge-secondary'" class="badge">
                    {{ item.is_purchasable ? 'Yes' : 'No' }}
                  </span>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Sellable</div>
                <div class="detail-value">
                  <span :class="item.is_sellable ? 'badge-success' : 'badge-secondary'" class="badge">
                    {{ item.is_sellable ? 'Yes' : 'No' }}
                  </span>
                </div>
              </div>
              <div class="detail-item" v-if="showMultiCurrencyPrices">
                <div class="detail-label">View Prices in Other Currencies</div>
                <div class="detail-value">
                  <button @click="fetchPricesInCurrencies" class="btn btn-sm btn-secondary" :disabled="isLoadingCurrencies">
                    <i class="fas fa-money-bill-wave"></i> 
                    {{ isLoadingCurrencies ? 'Loading...' : 'Show Prices' }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Multi-Currency Prices Section -->
          <div class="detail-section" v-if="multiCurrencyPrices">
            <h3 class="section-title">Prices in Other Currencies</h3>
            <div class="multi-currency-prices">
              <div v-for="(price, currency) in multiCurrencyPrices.prices" :key="currency" class="currency-price-card">
                <div class="currency-code">{{ currency }}</div>
                <div class="price-details">
                  <div class="price-row">
                    <span class="price-label">Purchase:</span>
                    <span class="price-value">{{ price.purchase_price }}</span>
                  </div>
                  <div class="price-row">
                    <span class="price-label">Sale:</span>
                    <span class="price-value">{{ price.sale_price }}</span>
                  </div>
                  <div class="base-currency-tag" v-if="price.is_base_currency">Base Currency</div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- BOM Components Section -->
          <div class="detail-section" v-if="bomComponents && bomComponents.length > 0">
            <h3 class="section-title">BOM Components ({{ bomComponents.length }})</h3>
            <div class="components-table">
              <table>
                <thead>
                  <tr>
                    <th>Component Code</th>
                    <th>Component Name</th>
                    <th>Quantity</th>
                    <th>UOM</th>
                    <th>Critical</th>
                    <th>Yield Based</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="component in bomComponents" :key="component.component_id">
                    <td>{{ component.component_code }}</td>
                    <td>{{ component.component_name }}</td>
                    <td>{{ component.quantity }}</td>
                    <td>{{ component.uom || '-' }}</td>
                    <td>
                      <span :class="component.is_critical ? 'badge-warning' : 'badge-secondary'" class="badge">
                        {{ component.is_critical ? 'Yes' : 'No' }}
                      </span>
                    </td>
                    <td>
                      <span :class="component.is_yield_based ? 'badge-info' : 'badge-secondary'" class="badge">
                        {{ component.is_yield_based ? 'Yes' : 'No' }}
                      </span>
                      <div v-if="component.is_yield_based && component.yield_ratio" class="yield-info">
                        <small>Ratio: {{ component.yield_ratio }}</small>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- Batches Section -->
          <div class="detail-section" v-if="item.batches && item.batches.length > 0">
            <h3 class="section-title">Batches</h3>
            <div class="batches-table">
              <table>
                <thead>
                  <tr>
                    <th>Batch Number</th>
                    <th>Expiry Date</th>
                    <th>Manufacturing Date</th>
                    <th>Lot Number</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="batch in item.batches" :key="batch.batch_id">
                    <td>{{ batch.batch_number }}</td>
                    <td>{{ formatDate(batch.expiry_date) }}</td>
                    <td>{{ formatDate(batch.manufacturing_date) }}</td>
                    <td>{{ batch.lot_number || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- Recent Transactions Section -->
          <div class="detail-section" v-if="recentTransactions.length > 0">
            <h3 class="section-title">Recent Transactions</h3>
            <div class="transactions-table">
              <table>
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
                      <span class="transaction-type" :class="getTransactionTypeClass(transaction.transaction_type)">
                        {{ transaction.transaction_type }}
                      </span>
                    </td>
                    <td :class="getQuantityClass(transaction.transaction_type)">
                      {{ transaction.quantity }} {{ item.unitOfMeasure?.symbol || '' }}
                    </td>
                    <td>{{ transaction.warehouse?.name || '-' }}</td>
                    <td>{{ transaction.reference_document || '-' }}</td>
                  </tr>
                </tbody>
              </table>
              <div class="view-all-link" v-if="hasMoreTransactions">
                <router-link :to="`/stock-transactions?item_id=${item.item_id}`">
                  View all transactions
                </router-link>
              </div>
            </div>
          </div>
          
          <div class="form-actions">
            <button class="btn btn-secondary" @click="$emit('close')">Close</button>
            <button class="btn btn-primary" @click="editItem">Edit Item</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import api from '@/services/api';

export default {
  name: 'ItemDetailModal',
  props: {
    item: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'edit'],
  setup(props, { emit }) {
    const recentTransactions = ref([]);
    const hasMoreTransactions = ref(false);
    const multiCurrencyPrices = ref(null);
    const isLoadingCurrencies = ref(false);
    
    // BOM Components dari props item
    const bomComponents = computed(() => {
      console.log('ItemDetailModal - props.item:', props.item);
      return props.item && props.item.bom_components ? props.item.bom_components : [];
    });

    // Show multi-currency button only if costs exist
    const showMultiCurrencyPrices = computed(() => {
      return props.item && (props.item.cost_price > 0 || props.item.sale_price > 0);
    });
    
    const fetchRecentTransactions = async () => {
      try {
        if (props.item && props.item.item_id) {
          const response = await api.get(`/transactions/items/${props.item.item_id}/movement?limit=5`);
          recentTransactions.value = response.data.data || [];
          
          // Check if there are more transactions than what we fetched
          hasMoreTransactions.value = response.data.meta && response.data.meta.total > 5;
        }
      } catch (error) {
        console.error('Error fetching recent transactions:', error);
      }
    };

    const fetchPricesInCurrencies = async () => {
      if (isLoadingCurrencies.value || !props.item?.item_id) return;
      
      isLoadingCurrencies.value = true;
      try {
        const response = await api.get(`/items/${props.item.item_id}/prices-in-currencies`, {
          params: {
            currencies: ['USD', 'IDR', 'EUR', 'SGD', 'JPY']
          }
        });
        
        if (response.data.success) {
          multiCurrencyPrices.value = response.data.data;
        }
      } catch (error) {
        console.error('Error fetching prices in currencies:', error);
      } finally {
        isLoadingCurrencies.value = false;
      }
    };
    
    const formatDate = (dateString) => {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    };
    
    const getStockStatus = (item) => {
      if (item.current_stock <= item.minimum_stock) {
        return 'Low Stock';
      } else if (item.current_stock >= item.maximum_stock) {
        return 'Over Stock';
      } else {
        return 'Normal';
      }
    };
    
    const getStockStatusClass = (item) => {
      const status = getStockStatus(item);
      switch (status) {
        case 'Low Stock': return 'low';
        case 'Over Stock': return 'over';
        default: return 'normal';
      }
    };
    
    const getTransactionTypeClass = (type) => {
      if (['IN', 'RECEIPT', 'RETURN', 'ADJUSTMENT_IN', 'receive', 'return', 'adjustment'].includes(type)) {
        return 'type-in';
      } else if (['OUT', 'ISSUE', 'SALE', 'ADJUSTMENT_OUT', 'issue', 'transfer', 'sale'].includes(type)) {
        return 'type-out';
      }
      return '';
    };
    
    const getQuantityClass = (type) => {
      if (['IN', 'RECEIPT', 'RETURN', 'ADJUSTMENT_IN', 'receive', 'return'].includes(type)) {
        return 'quantity-in';
      } else if (['OUT', 'ISSUE', 'SALE', 'ADJUSTMENT_OUT', 'issue', 'transfer', 'sale'].includes(type)) {
        return 'quantity-out';
      }
      return '';
    };
    
    const editItem = () => {
      emit('close');
      emit('edit', props.item);
    };
    
    onMounted(() => {
      fetchRecentTransactions();
    });
    
    return {
      recentTransactions,
      hasMoreTransactions,
      multiCurrencyPrices,
      isLoadingCurrencies,
      bomComponents,
      showMultiCurrencyPrices,
      formatDate,
      getStockStatus,
      getStockStatusClass,
      getTransactionTypeClass,
      getQuantityClass,
      fetchPricesInCurrencies,
      editItem
    };
  }
};
</script>

<style scoped>
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
  max-width: 900px;
  max-height: 90vh;
  z-index: 60;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
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
  overflow-y: auto;
}

.item-details {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.detail-section {
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  overflow: hidden;
}

.section-title {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
  padding: 0.75rem 1rem;
  background-color: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  color: #1e293b;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  padding: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
}

.detail-value {
  color: #1e293b;
  font-size: 0.875rem;
}

.description {
  padding: 1rem;
  color: #1e293b;
  font-size: 0.875rem;
  line-height: 1.5;
}

.stock-status {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.stock-status.low {
  background-color: #fee2e2;
  color: #dc2626;
}

.stock-status.normal {
  background-color: #d1fae5;
  color: #059669;
}

.stock-status.over {
  background-color: #fef3c7;
  color: #d97706;
}

/* Multi-currency prices section */
.multi-currency-prices {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1rem;
}

.currency-price-card {
  background-color: #f8fafc;
  border-radius: 0.375rem;
  border: 1px solid #e2e8f0;
  padding: 0.75rem;
  min-width: 120px;
  position: relative;
}

.currency-code {
  font-weight: 600;
  font-size: 1rem;
  color: #1e293b;
  margin-bottom: 0.5rem;
  text-align: center;
}

.price-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.price-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
}

.price-label {
  color: #64748b;
}

.price-value {
  font-weight: 500;
  color: #1e293b;
}

.base-currency-tag {
  position: absolute;
  top: -8px;
  right: -8px;
  background-color: #2563eb;
  color: white;
  font-size: 0.625rem;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

/* Component table styles */
.components-table,
.batches-table,
.transactions-table {
  padding: 1rem;
  overflow-x: auto;
}

.components-table table,
.batches-table table,
.transactions-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.components-table th,
.batches-table th,
.transactions-table th {
  text-align: left;
  padding: 0.5rem;
  border-bottom: 1px solid #e2e8f0;
  font-weight: 500;
  color: #64748b;
}

.components-table td,
.batches-table td,
.transactions-table td {
  padding: 0.5rem;
  border-bottom: 1px solid #f1f5f9;
  color: #1e293b;
}

.yield-info {
  margin-top: 0.25rem;
}

.yield-info small {
  font-size: 0.75rem;
  color: #64748b;
}

.transaction-type {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.type-in {
  background-color: #d1fae5;
  color: #059669;
}

.type-out {
  background-color: #fee2e2;
  color: #dc2626;
}

.quantity-in {
  color: #059669;
}

.quantity-out {
  color: #dc2626;
}

.view-all-link {
  margin-top: 0.5rem;
  text-align: right;
}

.view-all-link a {
  font-size: 0.875rem;
  color: #2563eb;
  text-decoration: none;
}

.view-all-link a:hover {
  text-decoration: underline;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1rem;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.btn-primary {
  background-color: #2563eb;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #1d4ed8;
}

.btn-secondary {
  background-color: #f8fafc;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #f1f5f9;
  border-color: #9ca3af;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-success {
  background-color: #d1fae5;
  color: #059669;
}

.badge-secondary {
  background-color: #f1f5f9;
  color: #64748b;
}

.badge-warning {
  background-color: #fef3c7;
  color: #d97706;
}

.badge-info {
  background-color: #dbeafe;
  color: #2563eb;
}

@media (max-width: 640px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
  
  .multi-currency-prices {
    flex-direction: column;
  }
}
</style>