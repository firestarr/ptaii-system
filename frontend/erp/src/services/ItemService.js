// src/services/ItemService.js
import api from './api';

/**
 * Service for item management operations
 */
const ItemService = {
  /**
   * Get all items
   * @param {Object} params - Query parameters
   * @returns {Promise} Promise with items response
   */
  getItems: async (params = {}) => {
    const response = await api.get('/items', { params });
    return response.data;
  },
  
  /**
   * Get item by ID with detailed information including BOM components
   * @param {Number} id - Item ID
   * @returns {Promise} Promise with item response
   */
  getItemById: async (id) => {
    try {
      console.log('ItemService: Fetching item with ID:', id);
      const response = await api.get(`/items/${id}`);
      console.log('ItemService: API response:', response.data);
      
      // Ensure the response structure is correct
      if (response.data && response.data.success) {
        return {
          success: true,
          data: response.data.data,
          bom_components: response.data.bom_components || []
        };
      } else {
        console.warn('ItemService: Unexpected response structure:', response.data);
        return response.data;
      }
    } catch (error) {
      console.error('ItemService: Error fetching item:', error);
      throw error;
    }
  },
  
  /**
   * Create a new item
   * @param {Object|FormData} itemData - Item data or FormData for file uploads
   * @param {Boolean} hasFile - Whether itemData includes file uploads
   * @returns {Promise} Promise with create item response
   */
  createItem: async (itemData, hasFile = false) => {
    const config = hasFile ? {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    } : {};
    
    const response = await api.post('/items', itemData, config);
    return response.data;
  },
  
  /**
   * Update item
   * @param {Number} id - Item ID
   * @param {Object|FormData} itemData - Item data to update
   * @param {Boolean} hasFile - Whether itemData includes file uploads
   * @returns {Promise} Promise with update item response
   */
  updateItem: async (id, itemData, hasFile = false) => {
    if (hasFile) {
      // Use post with _method=PUT for FormData
      const response = await api.post(`/items/${id}?_method=PUT`, itemData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      return response.data;
    } else {
      const response = await api.put(`/items/${id}`, itemData);
      return response.data;
    }
  },
  
  /**
   * Delete item
   * @param {Number} id - Item ID
   * @returns {Promise} Promise with delete item response
   */
  deleteItem: async (id) => {
    const response = await api.delete(`/items/${id}`);
    return response.data;
  },
  
  /**
   * Get stock status for all items
   * @returns {Promise} Promise with stock status response
   */
  getStockStatus: async () => {
    const response = await api.get('/items/stock-status');
    return response.data;
  },
  
  /**
   * Get item categories
   * @returns {Promise} Promise with categories response
   */
  getCategories: async () => {
    const response = await api.get('/item-categories');
    return response.data;
  },
  
  /**
   * Get units of measure
   * @returns {Promise} Promise with UOM response
   */
  getUnitsOfMeasure: async () => {
    const response = await api.get('/unit-of-measures');
    return response.data;
  },
  
  /**
   * Get transactions for a specific item
   * @param {Number} itemId - Item ID
   * @param {Object} params - Query parameters
   * @returns {Promise} Promise with transactions response
   */
  getItemTransactions: async (itemId, params = {}) => {
    const response = await api.get(`/transactions/items/${itemId}/movement`, { params });
    return response.data;
  },
  
  /**
   * Get prices in multiple currencies
   * @param {Number} id - Item ID
   * @param {Array} currencies - List of currency codes
   * @param {String} date - Optional date for exchange rates (YYYY-MM-DD)
   * @returns {Promise} Promise with prices in currencies response
   */
  getPricesInCurrencies: async (id, currencies, date = null) => {
    const params = { currencies };
    if (date) params.date = date;
    
    const response = await api.get(`/items/${id}/prices-in-currencies`, { params });
    return response.data;
  },
  
  /**
   * Get BOM components for an item
   * @param {Number} itemId - Item ID
   * @returns {Promise} Promise with BOM components response
   */
  getBOMComponents: async (itemId) => {
    try {
      // This data should come with the item details, but if needed separately:
      const response = await api.get(`/boms?item_id=${itemId}&status=Active`);
      
      if (response.data && response.data.data && response.data.data.length > 0) {
        const bom = response.data.data[0];
        const bomLinesResponse = await api.get(`/boms/${bom.bom_id}/lines`);
        return bomLinesResponse.data;
      }
      
      return { data: [] };
    } catch (error) {
      console.error('Error fetching BOM components:', error);
      return { data: [] };
    }
  },
  
  /**
   * Get item with all prices including customer-specific prices
   * @param {Number} id - Item ID
   * @returns {Promise} Promise with detailed prices response
   */
  getItemWithAllPrices: async (id) => {
    const response = await api.get(`/items/${id}/all-prices`);
    return response.data;
  },
  
  /**
   * Get customer price matrix for an item
   * @param {Number} id - Item ID
   * @returns {Promise} Promise with customer price matrix response
   */
  getCustomerPriceMatrix: async (id) => {
    const response = await api.get(`/items/${id}/customer-price-matrix`);
    return response.data;
  },
  
  /**
   * Get purchasable items
   * @param {Object} params - Query parameters
   * @returns {Promise} Promise with purchasable items response
   */
  getPurchasableItems: async (params = {}) => {
    const response = await api.get('/items/purchasable', { params });
    return response.data;
  },
  
  /**
   * Get sellable items
   * @param {Object} params - Query parameters
   * @returns {Promise} Promise with sellable items response
   */
  getSellableItems: async (params = {}) => {
    const response = await api.get('/items/sellable', { params });
    return response.data;
  },
  
  /**
   * Download item document
   * @param {Number} id - Item ID
   * @returns {Promise} Promise with document blob
   */
  downloadDocument: async (id) => {
    const response = await api.get(`/items/${id}/document`, {
      responseType: 'blob'
    });
    return response;
  },
  
  /**
   * Get stock level report
   * @param {Object} params - Optional query parameters
   * @returns {Promise} Promise with stock levels response
   */
  getStockLevelReport: async (params = {}) => {
    const response = await api.get('/items/stock-levels', { params });
    return response.data;
  },
  
  /**
   * Update item stock
   * @param {Number} id - Item ID
   * @param {Object} data - Adjustment data
   * @returns {Promise} Promise with update stock response
   */
  updateStock: async (id, data) => {
    const response = await api.post(`/items/${id}/update-stock`, data);
    return response.data;
  },

  /**
   * Debug helper to log item structure
   * @param {Object} item - Item object to debug
   */
  debugItem: (item) => {
    console.group('🔍 Item Debug Info');
    console.log('Item Object:', item);
    console.log('Has BOM Components:', !!(item && item.bom_components));
    console.log('BOM Components Count:', item?.bom_components?.length || 0);
    if (item?.bom_components?.length > 0) {
      console.log('First Component:', item.bom_components[0]);
    }
    console.groupEnd();
  }
};

export default ItemService;