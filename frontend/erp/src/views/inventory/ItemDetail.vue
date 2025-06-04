<!-- src/views/inventory/ItemDetail.vue -->
<template>
  <div class="item-detail-view">
    <div class="page-header">
      <div class="header-left">
        <button class="back-button" @click="$router.back()">
          <i class="fas fa-arrow-left"></i> Back
        </button>
        <h1 class="page-title">Item Details</h1>
      </div>
      
      <div class="header-actions">
        <button class="btn btn-secondary" @click="openEditModal">
          <i class="fas fa-edit"></i> Edit
        </button>
        <button class="btn btn-danger" @click="confirmDelete" v-if="canDelete">
          <i class="fas fa-trash"></i> Delete
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="loading-indicator">
      <i class="fas fa-spinner fa-spin"></i> Loading item details...
    </div>

    <div v-else-if="!item" class="error-message">
      <div class="error-icon">
        <i class="fas fa-exclamation-circle"></i>
      </div>
      <h2>Item Not Found</h2>
      <p>The requested item could not be found.</p>
      <button class="btn btn-primary" @click="$router.push('/items')">
        Back to Items List
      </button>
    </div>

    <div v-else class="item-detail-container">
      <!-- Basic Info Card -->
      <div class="detail-card">
        <div class="card-header">
          <h2 class="card-title">Basic Information</h2>
        </div>
        <div class="card-body">
          <div class="detail-grid">
            <div class="detail-row">
              <div class="detail-label">Item Code</div>
              <div class="detail-value">{{ item.item_code }}</div>
            </div>
            <div class="detail-row">
              <div class="detail-label">Name</div>
              <div class="detail-value">{{ item.name }}</div>
            </div>
            <div class="detail-row">
              <div class="detail-label">Category</div>
              <div class="detail-value">{{ item.category ? item.category.name : '-' }}</div>
            </div>
            <div class="detail-row">
              <div class="detail-label">Unit of Measure</div>
              <div class="detail-value">
                {{ item.unitOfMeasure ? `${item.unitOfMeasure.name} (${item.unitOfMeasure.symbol})` : '-' }}
              </div>
            </div>
            <div class="detail-row">
              <div class="detail-label">HS Code</div>
              <div class="detail-value">{{ item.hs_code || '-' }}</div>
            </div>
            <div class="detail-row" v-if="item.description">
              <div class="detail-label">Description</div>
              <div class="detail-value description">{{ item.description }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Physical Properties Card -->
      <div class="detail-card">
        <div class="card-header">
          <h2 class="card-title">Physical Properties</h2>
        </div>
        <div class="card-body">
          <div class="detail-grid">
            <div class="detail-row">
              <div class="detail-label">Length</div>
              <div class="detail-value">{{ item.length || '-' }}</div>
            </div>
            <div class="detail-row">
              <div class="detail-label">Width</div>
              <div class="detail-value">{{ item.width || '-' }}</div>
            </div>
            <div class="detail-row">
              <div class="detail-label">Thickness</div>
              <div class="detail-value">{{ item.thickness || '-' }}</div>
            </div>
            <div class="detail-row">
              <div class="detail-label">Weight</div>
              <div class="detail-value">{{ item.weight || '-' }}</div>
            </div>
            <div class="detail-row" v-if="item.document_path">
              <div class="detail-label">Technical Document</div>
              <div class="detail-value">
                <a :href="item.document_url" target="_blank" class="btn btn-sm btn-primary">
                  <i class="fas fa-file-pdf"></i> View Document
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stock Info Card -->
      <div class="detail-card">
        <div class="card-header">
          <h2 class="card-title">Stock Information</h2>
        </div>
        <div class="card-body">
          <div class="stock-summary">
            <div class="stock-stat">
              <div class="stat-label">Current Stock</div>
              <div class="stat-value">{{ item.current_stock }}</div>
              <div class="stat-unit">{{ item.unitOfMeasure?.symbol || '' }}</div>
            </div>
            <div class="stock-stat">
              <div class="stat-label">Minimum Stock</div>
              <div class="stat-value">{{ item.minimum_stock }}</div>
              <div class="stat-unit">{{ item.unitOfMeasure?.symbol || '' }}</div>
            </div>
            <div class="stock-stat">
              <div class="stat-label">Maximum Stock</div>
              <div class="stat-value">{{ item.maximum_stock }}</div>
              <div class="stat-unit">{{ item.unitOfMeasure?.symbol || '' }}</div>
            </div>
            <div class="stock-stat">
              <div class="stat-label">Status</div>
              <div class="stat-value status">
                <span class="stock-status" :class="getStockStatusClass(item)">
                  {{ getStockStatus(item) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pricing Info Card -->
      <div class="detail-card">
        <div class="card-header">
          <h2 class="card-title">Pricing Information</h2>
          <button 
            v-if="!showMultiCurrencyPrices && (item.cost_price > 0 || item.sale_price > 0)" 
            class="card-action"
            @click="fetchPricesInCurrencies"
          >
            <i class="fas fa-money-bill-wave"></i> Show in Currencies
          </button>
        </div>
        <div class="card-body">
          <div class="stock-summary">
            <div class="stock-stat">
              <div class="stat-label">Cost Price</div>
              <div class="stat-value">{{ item.cost_price || '-' }}</div>
              <div class="stat-unit">{{ item.cost_price_currency || 'USD' }}</div>
            </div>
            <div class="stock-stat">
              <div class="stat-label">Sale Price</div>
              <div class="stat-value">{{ item.sale_price || '-' }}</div>
              <div class="stat-unit">{{ item.sale_price_currency || 'USD' }}</div>
            </div>
            <div class="stock-stat">
              <div class="stat-label">Purchasable</div>
              <div class="stat-value">
                <span :class="item.is_purchasable ? 'badge-success' : 'badge-secondary'" class="badge">
                  {{ item.is_purchasable ? 'Yes' : 'No' }}
                </span>
              </div>
            </div>
            <div class="stock-stat">
              <div class="stat-label">Sellable</div>
              <div class="stat-value">
                <span :class="item.is_sellable ? 'badge-success' : 'badge-secondary'" class="badge">
                  {{ item.is_sellable ? 'Yes' : 'No' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Multi-Currency Prices Section -->
          <div class="currency-prices-container" v-if="showMultiCurrencyPrices">
            <h3 class="currency-prices-title">Prices in Other Currencies</h3>
            <div class="currency-prices-loading" v-if="isLoadingCurrencies">
              <i class="fas fa-spinner fa-spin"></i> Loading prices...
            </div>
            <div class="currency-prices" v-else-if="multiCurrencyPrices">
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
        </div>
      </div>

      <!-- Batches Card -->
      <div class="detail-card" v-if="item.batches && item.batches.length > 0">
        <div class="card-header">
          <h2 class="card-title">Batches</h2>
        </div>
        <div class="card-body">
          <div class="card-table">
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
      </div>

      <!-- Debug: Show BOM Components data -->
      <div class="debug-info" v-if="debugMode">
        <h4>Debug Info:</h4>
        <p>BOM Components from API: {{ JSON.stringify(bomComponents) }}</p>
        <p>BOM Components length: {{ bomComponents.length }}</p>
        <p>Item ID: {{ item.item_id }}</p>
      </div>

      <!-- BOM Components Card -->
      <div class="detail-card" v-if="bomComponents && bomComponents.length > 0">
        <div class="card-header">
          <h2 class="card-title">BOM Components</h2>
          <span class="component-count">{{ bomComponents.length }} component(s)</span>
        </div>
        <div class="card-body">
          <div class="card-table">
            <table>
              <thead>
                <tr>
                  <th>Component Code</th>
                  <th>Component Name</th>
                  <th>Quantity</th>
                  <th>UOM</th>
                  <th>Critical</th>
                  <th>Yield Based</th>
                  <th>Notes</th>
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
                    <div v-if="component.is_yield_based" class="yield-details">
                      <small>Ratio: {{ component.yield_ratio }}</small>
                      <small v-if="component.shrinkage_factor">Shrinkage: {{ component.shrinkage_factor }}%</small>
                    </div>
                  </td>
                  <td>{{ component.notes || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- No BOM Message -->
      <div class="detail-card" v-else>
        <div class="card-header">
          <h2 class="card-title">BOM Components</h2>
        </div>
        <div class="card-body">
          <div class="empty-state">
            <i class="fas fa-cog"></i>
            <p>No BOM components found for this item.</p>
            <p><small>This item either doesn't have an active BOM or is not a manufactured item.</small></p>
          </div>
        </div>
      </div>

      <!-- Transactions Card -->
      <div class="detail-card">
        <div class="card-header">
          <h2 class="card-title">Recent Transactions</h2>
          <router-link :to="`/stock-transactions?item_id=${item.item_id}`" class="card-action">
            View All
          </router-link>
        </div>
        <div class="card-body">
          <div v-if="isLoadingTransactions" class="loading-indicator">
            <i class="fas fa-spinner fa-spin"></i> Loading transactions...
          </div>
          <div v-else-if="transactions.length === 0" class="empty-state">
            <p>No transactions found for this item.</p>
          </div>
          <div v-else class="card-table">
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
                <tr v-for="transaction in transactions" :key="transaction.transaction_id">
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
          </div>
        </div>
      </div>
    </div>

    <!-- Item Form Modal -->
    <ItemFormModal
      v-if="showEditModal"
      :is-edit-mode="true"
      :item-form="itemForm"
      :categories="categories"
      :unit-of-measures="unitOfMeasures"
      @save="saveItem"
      @close="closeEditModal"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmationModal
      v-if="showDeleteModal"
      title="Confirm Delete"
      :message="`Are you sure you want to delete <strong>${item?.name}</strong>?`"
      confirm-button-text="Delete Item"
      confirm-button-class="btn btn-danger"
      @confirm="deleteItem"
      @close="closeDeleteModal"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import ItemService from '@/services/ItemService.js';
import ItemFormModal from '@/components/inventory/ItemFormModal.vue';
import ConfirmationModal from '@/components/common/ConfirmationModal.vue';

export default {
  name: 'ItemDetail',
  components: {
    ItemFormModal,
    ConfirmationModal
  },
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  setup(props) {
    const router = useRouter();
    const item = ref(null);
    const transactions = ref([]);
    const categories = ref([]);
    const unitOfMeasures = ref([]);
    const isLoading = ref(true);
    const isLoadingTransactions = ref(true);
    const isLoadingCurrencies = ref(false);
    const showEditModal = ref(false);
    const showDeleteModal = ref(false);
    const showMultiCurrencyPrices = ref(false);
    const multiCurrencyPrices = ref(null);
    const bomComponents = ref([]);
    const debugMode = ref(false); // Set to true for debugging
    
    const itemForm = ref({
      item_id: null,
      item_code: '',
      name: '',
      description: '',
      category_id: '',
      uom_id: '',
      minimum_stock: 0,
      maximum_stock: 0,
      is_purchasable: false,
      is_sellable: false,
      cost_price: 0,
      sale_price: 0,
      cost_price_currency: 'USD',
      sale_price_currency: 'USD',
      length: '',
      width: '',
      thickness: '',
      weight: '',
      hs_code: '' // Tambahan HS Code
    });

    const canDelete = computed(() => {
      if (!item.value) return false;
      
      // Check if item has transactions or batches
      const hasTransactions = transactions.value.length > 0;
      const hasBatches = item.value.batches && item.value.batches.length > 0;
      
      return !hasTransactions && !hasBatches;
    });

    const fetchItem = async () => {
      isLoading.value = true;
      try {
        console.log('Fetching item with ID:', props.id);
        const response = await ItemService.getItemById(props.id);
        console.log('API Response:', response);
        
        item.value = response.data;
        
        // Set BOM components dari response API
        if (response.bom_components) {
          bomComponents.value = response.bom_components;
          console.log('BOM Components found:', bomComponents.value);
        } else {
          console.log('No BOM components found in API response');
          bomComponents.value = [];
        }
        
        // Populate form for potential edit
        Object.assign(itemForm.value, {
          item_id: item.value.item_id,
          item_code: item.value.item_code,
          name: item.value.name,
          description: item.value.description || '',
          category_id: item.value.category_id || '',
          uom_id: item.value.uom_id || '',
          minimum_stock: item.value.minimum_stock,
          maximum_stock: item.value.maximum_stock,
          is_purchasable: item.value.is_purchasable || false,
          is_sellable: item.value.is_sellable || false,
          cost_price: item.value.cost_price || 0,
          sale_price: item.value.sale_price || 0,
          cost_price_currency: item.value.cost_price_currency || 'USD',
          sale_price_currency: item.value.sale_price_currency || 'USD',
          length: item.value.length || '',
          width: item.value.width || '',
          thickness: item.value.thickness || '',
          weight: item.value.weight || '',
          hs_code: item.value.hs_code || '' // Tambahan HS Code
        });
      } catch (error) {
        console.error('Error fetching item:', error);
        item.value = null;
      } finally {
        isLoading.value = false;
      }
    };

    const fetchTransactions = async () => {
      if (!props.id) return;
      
      isLoadingTransactions.value = true;
      try {
        const response = await ItemService.getItemTransactions(props.id, { limit: 10 });
        transactions.value = response.data || [];
      } catch (error) {
        console.error('Error fetching transactions:', error);
        transactions.value = [];
      } finally {
        isLoadingTransactions.value = false;
      }
    };

    const fetchPricesInCurrencies = async () => {
      if (isLoadingCurrencies.value || !item.value?.item_id) return;
      
      showMultiCurrencyPrices.value = true;
      isLoadingCurrencies.value = true;
      
      try {
        const response = await ItemService.getPricesInCurrencies(
          item.value.item_id, 
          ['USD', 'IDR', 'EUR', 'SGD', 'JPY']
        );
        
        if (response.success) {
          multiCurrencyPrices.value = response.data;
        }
      } catch (error) {
        console.error('Error fetching prices in currencies:', error);
      } finally {
        isLoadingCurrencies.value = false;
      }
    };

    const fetchCategories = async () => {
      try {
        const response = await ItemService.getCategories();
        categories.value = response.data || [];
      } catch (error) {
        console.error('Error fetching categories:', error);
        categories.value = [];
      }
    };

    const fetchUnitOfMeasures = async () => {
      try {
        const response = await ItemService.getUnitsOfMeasure();
        unitOfMeasures.value = response.data || [];
      } catch (error) {
        console.error('Error fetching units of measure:', error);
        unitOfMeasures.value = [];
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

    const openEditModal = () => {
      showEditModal.value = true;
    };

    const closeEditModal = () => {
      showEditModal.value = false;
    };

    const saveItem = async (formData) => {
      try {
        await ItemService.updateItem(formData.get('item_id'), formData);
        
        // Refresh item data
        await fetchItem();
        
        // Close modal
        closeEditModal();
        
        // Show success message
        alert('Item updated successfully!');
      } catch (error) {
        console.error('Error updating item:', error);
        
        if (error.validationErrors) {
          alert('Please check the form for errors: ' + Object.values(error.validationErrors).join(', '));
        } else {
          alert('An error occurred while updating the item. Please try again.');
        }
      }
    };

    const confirmDelete = () => {
      showDeleteModal.value = true;
    };

    const closeDeleteModal = () => {
      showDeleteModal.value = false;
    };

    const deleteItem = async () => {
      try {
        await ItemService.deleteItem(props.id);
        
        // Close modal
        closeDeleteModal();
        
        // Show success message
        alert('Item deleted successfully!');
        
        // Navigate back to items list
        router.push('/items');
      } catch (error) {
        console.error('Error deleting item:', error);
        
        if (error.response && error.response.status === 422) {
          alert('This item cannot be deleted because it has related transactions or batches.');
        } else {
          alert('An error occurred while deleting the item. Please try again.');
        }
      }
    };

    onMounted(() => {
      fetchItem();
      fetchTransactions();
      fetchCategories();
      fetchUnitOfMeasures();
    });

    return {
      item,
      transactions,
      categories,
      unitOfMeasures,
      isLoading,
      isLoadingTransactions,
      isLoadingCurrencies,
      showEditModal,
      showDeleteModal,
      showMultiCurrencyPrices,
      multiCurrencyPrices,
      itemForm,
      canDelete,
      bomComponents,
      debugMode,
      formatDate,
      getStockStatus,
      getStockStatusClass,
      getTransactionTypeClass,
      getQuantityClass,
      openEditModal,
      closeEditModal,
      saveItem,
      confirmDelete,
      closeDeleteModal,
      deleteItem,
      fetchPricesInCurrencies
    };
  }
};
</script>

<style scoped>
.item-detail-view {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.back-button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #64748b;
  background: none;
  border: none;
  padding: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  transition: color 0.2s;
}

.back-button:hover {
  color: #1e293b;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
  color: #1e293b;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.item-detail-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
}

.detail-card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background-color: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.card-title {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0;
  color: #1e293b;
}

.component-count {
  font-size: 0.875rem;
  color: #64748b;
  background-color: #e2e8f0;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.card-action {
  font-size: 0.875rem;
  color: #2563eb;
  text-decoration: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-action:hover {
  text-decoration: underline;
}

.card-body {
  padding: 1.5rem;
}

.detail-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.detail-row {
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

.detail-value.description {
  white-space: pre-line;
  line-height: 1.5;
}

.stock-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
}

.stock-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 1rem;
  border-radius: 0.375rem;
  background-color: #f8fafc;
}

.stat-label {
  font-size: 0.75rem;
  color: #64748b;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
}

.stat-unit {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.stat-value.status {
  font-size: 1rem;
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

/* Multi-currency section */
.currency-prices-container {
  margin-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
  padding-top: 1.5rem;
}

.currency-prices-title {
  font-size: 1rem;
  font-weight: 600;
  margin: 0 0 1rem 0;
  color: #1e293b;
}

.currency-prices {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.currency-price-card {
  background-color: #f8fafc;
  border-radius: 0.375rem;
  border: 1px solid #e2e8f0;
  padding: 0.75rem;
  min-width: 120px;
  position: relative;
  flex: 1;
  min-width: 120px;
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

.currency-prices-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 1rem;
  color: #64748b;
}

.card-table {
  overflow-x: auto;
}

.card-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.card-table th {
  text-align: left;
  padding: 0.75rem 0.5rem;
  border-bottom: 1px solid #e2e8f0;
  font-weight: 500;
  color: #64748b;
}

.card-table td {
  padding: 0.75rem 0.5rem;
  border-bottom: 1px solid #f1f5f9;
  color: #1e293b;
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

.yield-details {
  margin-top: 0.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.yield-details small {
  font-size: 0.75rem;
  color: #64748b;
}

.empty-state {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 2rem 0;
  color: #64748b;
  text-align: center;
}

.empty-state i {
  font-size: 2rem;
  margin-bottom: 1rem;
  color: #94a3b8;
}

.empty-state p {
  margin: 0.25rem 0;
}

.loading-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2rem 0;
  color: #64748b;
}

.loading-indicator i {
  margin-right: 0.5rem;
}

.error-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 4rem 0;
}

.error-icon {
  font-size: 3rem;
  color: #f43f5e;
  margin-bottom: 1rem;
}

.error-message h2 {
  margin: 0 0 0.5rem 0;
  color: #1e293b;
}

.error-message p {
  margin: 0 0 1.5rem 0;
  color: #64748b;
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

.debug-info {
  background-color: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 0.375rem;
  padding: 1rem;
  margin: 1rem 0;
  font-family: monospace;
  font-size: 0.875rem;
}

.debug-info h4 {
  margin: 0 0 0.5rem 0;
  color: #856404;
}

.debug-info p {
  margin: 0.25rem 0;
  word-break: break-all;
}

@media (max-width: 768px) {
  .item-detail-container {
    grid-template-columns: 1fr;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .header-actions {
    align-self: flex-end;
  }
  
  .stock-summary {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .currency-prices {
    flex-direction: column;
  }
}
</style>