// src/utils/MultiSODeliveryUtils.js
// Utility functions untuk pengelolaan Multi-SO Delivery

import axios from 'axios';

export class MultiSODeliveryUtils {
  
  /**
   * Validate if items can be combined into a single delivery
   * @param {Array} selectedItems - Array of selected items from different SOs
   * @returns {Object} - Validation result with success status and errors
   */
  static validateItemsForDelivery(selectedItems) {
    const validation = {
      success: true,
      errors: [],
      warnings: []
    };

    // Group items by customer
    const customerGroups = new Map();
    selectedItems.forEach(item => {
      const customerId = item.customer_id;
      if (!customerGroups.has(customerId)) {
        customerGroups.set(customerId, []);
      }
      customerGroups.get(customerId).push(item);
    });

    // Check if all items belong to the same customer
    if (customerGroups.size > 1) {
      validation.success = false;
      validation.errors.push(
        `Items from multiple customers cannot be combined in a single delivery. Found ${customerGroups.size} different customers.`
      );
    }

    // Check for stock availability
    selectedItems.forEach(item => {
      if (item.requested_quantity > item.available_stock) {
        validation.warnings.push(
          `Insufficient stock for ${item.item_name}. Requested: ${item.requested_quantity}, Available: ${item.available_stock}`
        );
      }
    });

    // Check for conflicting warehouses
    const warehouseConflicts = this.checkWarehouseConflicts(selectedItems);
    if (warehouseConflicts.length > 0) {
      validation.warnings.push(...warehouseConflicts);
    }

    return validation;
  }

  /**
   * Check for warehouse conflicts that might affect delivery efficiency
   * @param {Array} items - Array of items
   * @returns {Array} - Array of conflict messages
   */
  static checkWarehouseConflicts(items) {
    const conflicts = [];
    const warehouseGroups = new Map();
    
    items.forEach(item => {
      const warehouseId = item.warehouse_id;
      if (!warehouseGroups.has(warehouseId)) {
        warehouseGroups.set(warehouseId, []);
      }
      warehouseGroups.get(warehouseId).push(item);
    });

    if (warehouseGroups.size > 3) {
      conflicts.push(
        `Items are spread across ${warehouseGroups.size} warehouses. Consider consolidating to improve delivery efficiency.`
      );
    }

    return conflicts;
  }

  /**
   * Calculate delivery consolidation benefits
   * @param {Array} salesOrders - Array of sales orders
   * @returns {Object} - Consolidation metrics
   */
  static calculateConsolidationBenefits(salesOrders) {
    const benefits = {
      estimatedTimeSaving: 0,
      estimatedCostSaving: 0,
      efficiencyGain: 0,
      consolidationScore: 0
    };

    // Calculate time saving (assume 15 minutes saved per additional SO)
    if (salesOrders.length > 1) {
      benefits.estimatedTimeSaving = (salesOrders.length - 1) * 15;
    }

    // Calculate cost saving (assume $10 saved per additional SO)
    if (salesOrders.length > 1) {
      benefits.estimatedCostSaving = (salesOrders.length - 1) * 10;
    }

    // Calculate efficiency gain percentage
    const baseEfficiency = 100;
    const efficiencyBonus = (salesOrders.length - 1) * 12.5;
    benefits.efficiencyGain = Math.min(efficiencyBonus, 50); // Max 50% gain

    // Calculate consolidation score (0-100)
    const itemCount = salesOrders.reduce((sum, so) => sum + so.item_count, 0);
    const warehouseCount = new Set(
      salesOrders.flatMap(so => so.items.map(item => item.warehouse_id))
    ).size;
    
    benefits.consolidationScore = Math.min(
      100,
      ((salesOrders.length * 20) + (itemCount * 2) - (warehouseCount * 5))
    );

    return benefits;
  }

  /**
   * Generate optimal delivery batches from multiple SOs
   * @param {Array} salesOrders - Array of sales orders
   * @param {Object} constraints - Delivery constraints
   * @returns {Array} - Array of optimized delivery batches
   */
  static generateOptimalBatches(salesOrders, constraints = {}) {
    const {
      maxItemsPerDelivery = 50,
      maxWarehousesPerDelivery = 3,
      prioritizeCustomer = true
    } = constraints;

    const batches = [];
    const remainingSOs = [...salesOrders];

    while (remainingSOs.length > 0) {
      const batch = {
        salesOrders: [],
        totalItems: 0,
        warehouses: new Set(),
        customers: new Set()
      };

      // Start with the first remaining SO
      const primarySO = remainingSOs.shift();
      batch.salesOrders.push(primarySO);
      batch.totalItems += primarySO.item_count;
      batch.customers.add(primarySO.customer_id);
      
      primarySO.items.forEach(item => {
        batch.warehouses.add(item.warehouse_id);
      });

      // Try to add compatible SOs to the batch
      let i = 0;
      while (i < remainingSOs.length) {
        const candidateSO = remainingSOs[i];
        
        // Check constraints
        const wouldExceedItems = batch.totalItems + candidateSO.item_count > maxItemsPerDelivery;
        const wouldExceedWarehouses = batch.warehouses.size + 
          candidateSO.items.filter(item => !batch.warehouses.has(item.warehouse_id)).length > maxWarehousesPerDelivery;
        const differentCustomer = prioritizeCustomer && !batch.customers.has(candidateSO.customer_id);

        if (!wouldExceedItems && !wouldExceedWarehouses && !differentCustomer) {
          // Add to batch
          batch.salesOrders.push(candidateSO);
          batch.totalItems += candidateSO.item_count;
          batch.customers.add(candidateSO.customer_id);
          candidateSO.items.forEach(item => {
            batch.warehouses.add(item.warehouse_id);
          });
          
          remainingSOs.splice(i, 1);
        } else {
          i++;
        }
      }

      batches.push({
        ...batch,
        warehouses: Array.from(batch.warehouses),
        customers: Array.from(batch.customers),
        deliveryNumber: this.generateDeliveryNumber(batches.length + 1)
      });
    }

    return batches;
  }

  /**
   * Generate delivery number with auto-increment
   * @param {number} sequence - Sequence number
   * @returns {string} - Generated delivery number
   */
  static generateDeliveryNumber(sequence = 1) {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const seq = String(sequence).padStart(3, '0');
    
    return `DEL-${year}${month}${day}-${seq}`;
  }

  /**
   * Format delivery summary for display
   * @param {Object} delivery - Delivery object
   * @returns {Object} - Formatted summary
   */
  static formatDeliverySummary(delivery) {
    const summary = {
      deliveryNumber: delivery.delivery_number,
      customerName: delivery.customer?.name || 'Unknown Customer',
      totalSalesOrders: 0,
      totalItems: delivery.deliveryLines?.length || 0,
      totalQuantity: 0,
      status: delivery.status,
      deliveryDate: delivery.delivery_date,
      warehouses: new Set(),
      isMultiSO: false
    };

    if (delivery.deliveryLines) {
      // Calculate totals
      summary.totalQuantity = delivery.deliveryLines.reduce(
        (sum, line) => sum + (parseFloat(line.delivered_quantity) || 0), 0
      );

      // Get unique sales orders
      const uniqueSOIds = new Set(
        delivery.deliveryLines
          .map(line => line.salesOrderLine?.so_id)
          .filter(Boolean)
      );
      summary.totalSalesOrders = uniqueSOIds.size;
      summary.isMultiSO = uniqueSOIds.size > 1;

      // Get warehouses
      delivery.deliveryLines.forEach(line => {
        if (line.warehouse?.name) {
          summary.warehouses.add(line.warehouse.name);
        }
      });
    }

    summary.warehouses = Array.from(summary.warehouses);
    
    return summary;
  }

  /**
   * Get delivery performance metrics
   * @param {Array} deliveries - Array of deliveries
   * @returns {Object} - Performance metrics
   */
  static calculatePerformanceMetrics(deliveries) {
    const metrics = {
      totalDeliveries: deliveries.length,
      multiSODeliveries: 0,
      avgItemsPerDelivery: 0,
      avgSOsPerMultiDelivery: 0,
      efficiencyScore: 0,
      onTimeDeliveryRate: 0,
      completionRate: 0
    };

    if (deliveries.length === 0) return metrics;

    let totalItems = 0;
    let totalSOsInMultiDeliveries = 0;
    let onTimeDeliveries = 0;
    let completedDeliveries = 0;

    deliveries.forEach(delivery => {
      const summary = this.formatDeliverySummary(delivery);
      
      totalItems += summary.totalItems;
      
      if (summary.isMultiSO) {
        metrics.multiSODeliveries++;
        totalSOsInMultiDeliveries += summary.totalSalesOrders;
      }

      if (delivery.status === 'Completed') {
        completedDeliveries++;
        
        // Check if delivered on time (assuming delivery_date is the target date)
        const deliveryDate = new Date(delivery.delivery_date);
        const actualDate = new Date(delivery.updated_at || delivery.delivery_date);
        if (actualDate <= deliveryDate) {
          onTimeDeliveries++;
        }
      }
    });

    metrics.avgItemsPerDelivery = totalItems / deliveries.length;
    metrics.avgSOsPerMultiDelivery = metrics.multiSODeliveries > 0 
      ? totalSOsInMultiDeliveries / metrics.multiSODeliveries 
      : 0;
    metrics.onTimeDeliveryRate = completedDeliveries > 0 
      ? (onTimeDeliveries / completedDeliveries) * 100 
      : 0;
    metrics.completionRate = (completedDeliveries / deliveries.length) * 100;
    
    // Calculate efficiency score (weighted combination of metrics)
    const multiSORate = (metrics.multiSODeliveries / deliveries.length) * 100;
    metrics.efficiencyScore = (
      (multiSORate * 0.4) + 
      (metrics.onTimeDeliveryRate * 0.3) + 
      (metrics.completionRate * 0.3)
    );

    return metrics;
  }

  /**
   * Export delivery data to CSV
   * @param {Array} deliveries - Array of deliveries
   * @returns {string} - CSV content
   */
  static exportToCSV(deliveries) {
    const headers = [
      'Delivery Number',
      'Customer',
      'Delivery Date',
      'Status',
      'Sales Orders Count',
      'Total Items',
      'Total Quantity',
      'Warehouses',
      'Is Multi-SO'
    ];

    const rows = deliveries.map(delivery => {
      const summary = this.formatDeliverySummary(delivery);
      return [
        summary.deliveryNumber,
        summary.customerName,
        summary.deliveryDate,
        summary.status,
        summary.totalSalesOrders,
        summary.totalItems,
        summary.totalQuantity,
        summary.warehouses.join('; '),
        summary.isMultiSO ? 'Yes' : 'No'
      ];
    });

    const csvContent = [headers, ...rows]
      .map(row => row.map(field => `"${field}"`).join(','))
      .join('\n');

    return csvContent;
  }

  /**
   * Download CSV file
   * @param {string} csvContent - CSV content string
   * @param {string} filename - File name
   */
  static downloadCSV(csvContent, filename = 'multi-so-deliveries.csv') {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', filename);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  }

  /**
   * Get suggested improvements for delivery consolidation
   * @param {Array} salesOrders - Array of sales orders
   * @returns {Array} - Array of improvement suggestions
   */
  static getSuggestedImprovements(salesOrders) {
    const suggestions = [];
    
    // Group by customer
    const customerGroups = new Map();
    salesOrders.forEach(so => {
      if (!customerGroups.has(so.customer_id)) {
        customerGroups.set(so.customer_id, []);
      }
      customerGroups.get(so.customer_id).push(so);
    });

    // Check for consolidation opportunities
    customerGroups.forEach((orders, customerId) => {
      if (orders.length > 1) {
        const totalItems = orders.reduce((sum, so) => sum + (so.item_count || 0), 0);
        const customerName = orders[0].customer_name;
        
        suggestions.push({
          type: 'consolidation',
          priority: 'high',
          title: `Consolidate ${orders.length} orders for ${customerName}`,
          description: `Combine ${orders.length} sales orders (${totalItems} items) into a single delivery to save time and cost.`,
          estimatedSaving: (orders.length - 1) * 15, // minutes
          impact: 'high'
        });
      }
    });

    // Check for warehouse optimization
    const warehouseDistribution = new Map();
    salesOrders.forEach(so => {
      so.items?.forEach(item => {
        if (!warehouseDistribution.has(item.warehouse_id)) {
          warehouseDistribution.set(item.warehouse_id, 0);
        }
        warehouseDistribution.set(item.warehouse_id, 
          warehouseDistribution.get(item.warehouse_id) + 1
        );
      });
    });

    if (warehouseDistribution.size > 3) {
      suggestions.push({
        type: 'warehouse_optimization',
        priority: 'medium',
        title: 'Optimize warehouse distribution',
        description: `Items are spread across ${warehouseDistribution.size} warehouses. Consider reorganizing inventory for better consolidation.`,
        impact: 'medium'
      });
    }

    return suggestions.sort((a, b) => {
      const priorityOrder = { high: 3, medium: 2, low: 1 };
      return priorityOrder[b.priority] - priorityOrder[a.priority];
    });
  }
}

// Export utility functions as individual exports for convenience
export const {
  validateItemsForDelivery,
  checkWarehouseConflicts,
  calculateConsolidationBenefits,
  generateOptimalBatches,
  generateDeliveryNumber,
  formatDeliverySummary,
  calculatePerformanceMetrics,
  exportToCSV,
  downloadCSV,
  getSuggestedImprovements
} = MultiSODeliveryUtils;

export default MultiSODeliveryUtils;