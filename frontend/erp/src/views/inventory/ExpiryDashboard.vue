<!-- src/views/inventory/ExpiryDashboard.vue -->
<template>
  <div class="expiry-dashboard">
    <div class="dashboard-header">
      <h2 class="page-title">Expiry Management Dashboard</h2>
      <div class="filter-controls">
        <div class="days-filter">
          <label for="days-filter">Show items expiring within:</label>
          <select id="days-filter" v-model="daysFilter" @change="fetchNearExpiryBatches">
            <option value="30">30 days</option>
            <option value="60">60 days</option>
            <option value="90">90 days</option>
            <option value="180">6 months</option>
            <option value="365">1 year</option>
          </select>
        </div>
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Search items or batches..." 
            @input="filterBatches"
          />
          <button v-if="searchQuery" class="clear-btn" @click="clearSearch">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    </div>

    <div class="dashboard-stats">
      <div class="stat-card critical">
        <div class="stat-icon">
          <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ criticalCount }}</div>
          <div class="stat-label">Critical (&lt; 7 days)</div>
        </div>
      </div>
      <div class="stat-card warning">
        <div class="stat-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ warningCount }}</div>
          <div class="stat-label">Warning (&lt; 30 days)</div>
        </div>
      </div>
      <div class="stat-card attention">
        <div class="stat-icon">
          <i class="fas fa-bell"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ attentionCount }}</div>
          <div class="stat-label">Attention (&lt; {{ daysFilter }} days)</div>
        </div>
      </div>
      <div class="stat-card expired">
        <div class="stat-icon">
          <i class="fas fa-calendar-times"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ expiredCount }}</div>
          <div class="stat-label">Expired</div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading-section">
      <div class="spinner">
        <i class="fas fa-spinner fa-spin"></i>
      </div>
      <p>Loading expiry data...</p>
    </div>

    <div v-else-if="filteredBatches.length === 0" class="empty-state">
      <div class="empty-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <h3>No Near-Expiry Items Found</h3>
      <p v-if="searchQuery">No items match your search criteria.</p>
      <p v-else>There are no items expiring within {{ daysFilter }} days.</p>
    </div>

    <div v-else class="expiry-batches-wrapper">
      <div class="table-actions">
        <div class="table-tabs">
          <button 
            :class="['tab-btn', { active: activeTab === 'all' }]" 
            @click="activeTab = 'all'">
            All Items
          </button>
          <button 
            :class="['tab-btn', { active: activeTab === 'critical' }]" 
            @click="activeTab = 'critical'">
            Critical
          </button>
          <button 
            :class="['tab-btn', { active: activeTab === 'warning' }]" 
            @click="activeTab = 'warning'">
            Warning
          </button>
          <button 
            :class="['tab-btn', { active: activeTab === 'expired' }]" 
            @click="activeTab = 'expired'">
            Expired
          </button>
        </div>
        <div class="table-tools">
          <button class="btn btn-outline" @click="exportToCsv">
            <i class="fas fa-download"></i> Export to CSV
          </button>
          <button class="btn btn-outline" @click="printReport">
            <i class="fas fa-print"></i> Print Report
          </button>
        </div>
      </div>

      <div class="expiry-table-container">
        <table class="expiry-table">
          <thead>
            <tr>
              <th @click="sortBy('item_code')" class="sortable">
                Item Code
                <i v-if="sortColumn === 'item_code'" :class="getSortIcon()"></i>
              </th>
              <th @click="sortBy('item_name')" class="sortable">
                Item Name
                <i v-if="sortColumn === 'item_name'" :class="getSortIcon()"></i>
              </th>
              <th @click="sortBy('batch_number')" class="sortable">
                Batch Number
                <i v-if="sortColumn === 'batch_number'" :class="getSortIcon()"></i>
              </th>
              <th @click="sortBy('expiry_date')" class="sortable">
                Expiry Date
                <i v-if="sortColumn === 'expiry_date'" :class="getSortIcon()"></i>
              </th>
              <th @click="sortBy('days_until_expiry')" class="sortable">
                Days Until Expiry
                <i v-if="sortColumn === 'days_until_expiry'" :class="getSortIcon()"></i>
              </th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="batch in paginatedBatches" 
              :key="batch.batch_id"
              :class="getRowClass(batch)"
            >
              <td class="code-cell">{{ batch.item_code }}</td>
              <td>{{ batch.item_name }}</td>
              <td class="code-cell">{{ batch.batch_number }}</td>
              <td>{{ formatDate(batch.expiry_date) }}</td>
              <td :class="getDaysClass(batch)">
                <div class="days-badge" :class="getDaysBadgeClass(batch)">
                  {{ batch.days_until_expiry >= 0 ? batch.days_until_expiry : 'Expired' }}
                </div>
              </td>
              <td>
                <div class="status-badge" :class="getStatusBadgeClass(batch)">
                  {{ getStatusText(batch) }}
                </div>
              </td>
              <td class="actions-cell">
                <a 
                  :href="`/items/${batch.item_id}/batches/${batch.batch_id}`" 
                  class="btn btn-sm btn-outline-info"
                  title="View Batch Details"
                >
                  <i class="fas fa-eye"></i>
                </a>
                <a 
                  :href="`/items/${batch.item_id}`" 
                  class="btn btn-sm btn-outline-primary"
                  title="View Item Details"
                >
                  <i class="fas fa-box"></i>
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination-controls">
        <div class="pagination-info">
          Showing {{ paginationFrom }} to {{ paginationTo }} of {{ filteredBatches.length }} items
        </div>
        <div class="pagination-buttons">
          <button 
            class="pagination-btn" 
            :disabled="currentPage === 1"
            @click="currentPage--">
            <i class="fas fa-chevron-left"></i>
          </button>
          
          <template v-for="(page, index) in pageNumbers" :key="index">
            <button 
              v-if="page !== '...'" 
              :class="['pagination-btn', { active: page === currentPage }]"
              @click="currentPage = page">
              {{ page }}
            </button>
            <span v-else class="pagination-ellipsis">...</span>
          </template>
          
          <button 
            class="pagination-btn" 
            :disabled="currentPage === totalPages"
            @click="currentPage++">
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ExpiryDashboard',
  data() {
    return {
      batches: [],
      filteredBatches: [],
      loading: true,
      daysFilter: 30,
      searchQuery: '',
      sortColumn: 'days_until_expiry',
      sortDirection: 'asc',
      activeTab: 'all',
      currentPage: 1,
      itemsPerPage: 10
    };
  },
  computed: {
    criticalCount() {
      return this.batches.filter(batch => 
        batch.days_until_expiry >= 0 && batch.days_until_expiry <= 7
      ).length;
    },
    warningCount() {
      return this.batches.filter(batch => 
        batch.days_until_expiry > 7 && batch.days_until_expiry <= 30
      ).length;
    },
    attentionCount() {
      return this.batches.filter(batch => 
        batch.days_until_expiry > 30 && batch.days_until_expiry <= this.daysFilter
      ).length;
    },
    expiredCount() {
      return this.batches.filter(batch => batch.days_until_expiry < 0).length;
    },
    paginatedBatches() {
      const start = (this.currentPage - 1) * this.itemsPerPage;
      const end = start + this.itemsPerPage;
      return this.filteredBatches.slice(start, end);
    },
    totalPages() {
      return Math.ceil(this.filteredBatches.length / this.itemsPerPage);
    },
    paginationFrom() {
      return this.filteredBatches.length === 0 ? 0 : (this.currentPage - 1) * this.itemsPerPage + 1;
    },
    paginationTo() {
      return Math.min(this.currentPage * this.itemsPerPage, this.filteredBatches.length);
    },
    pageNumbers() {
      if (this.totalPages <= 7) {
        return Array.from({ length: this.totalPages }, (_, i) => i + 1);
      }
      
      const current = this.currentPage;
      const pages = [];
      
      // Always include first page
      pages.push(1);
      
      // Show ellipsis if current page is more than 3
      if (current > 3) {
        pages.push('...');
      }
      
      // Add pages around current page
      const startPage = Math.max(2, current - 1);
      const endPage = Math.min(this.totalPages - 1, current + 1);
      
      for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
      }
      
      // Show ellipsis if current page is less than total - 2
      if (current < this.totalPages - 2) {
        pages.push('...');
      }
      
      // Always include last page
      if (this.totalPages > 1) {
        pages.push(this.totalPages);
      }
      
      return pages;
    }
  },
  watch: {
    activeTab() {
      this.filterBatches();
      this.currentPage = 1;
    }
  },
  created() {
    this.fetchNearExpiryBatches();
  },
  methods: {
    async fetchNearExpiryBatches() {
      this.loading = true;
      
      try {
        const response = await axios.get(`/batches/near-expiry/${this.daysFilter}`);
        if (response.data.success) {
          this.batches = response.data.data;
          this.filterBatches();
        }
      } catch (error) {
        console.error('Error fetching near-expiry batches:', error);
      } finally {
        this.loading = false;
      }
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
    getRowClass(batch) {
      if (batch.days_until_expiry < 0) return 'row-expired';
      if (batch.days_until_expiry <= 7) return 'row-critical';
      if (batch.days_until_expiry <= 30) return 'row-warning';
      return '';
    },
    getDaysClass(batch) {
      if (batch.days_until_expiry < 0) return 'days-expired';
      if (batch.days_until_expiry <= 7) return 'days-critical';
      if (batch.days_until_expiry <= 30) return 'days-warning';
      return '';
    },
    getDaysBadgeClass(batch) {
      if (batch.days_until_expiry < 0) return 'badge-expired';
      if (batch.days_until_expiry <= 7) return 'badge-critical';
      if (batch.days_until_expiry <= 30) return 'badge-warning';
      return 'badge-attention';
    },
    getStatusText(batch) {
      if (batch.days_until_expiry < 0) return 'Expired';
      if (batch.days_until_expiry <= 7) return 'Critical';
      if (batch.days_until_expiry <= 30) return 'Warning';
      return 'Attention';
    },
    getStatusBadgeClass(batch) {
      if (batch.days_until_expiry < 0) return 'badge-expired';
      if (batch.days_until_expiry <= 7) return 'badge-critical';
      if (batch.days_until_expiry <= 30) return 'badge-warning';
      return 'badge-attention';
    },
    clearSearch() {
      this.searchQuery = '';
      this.filterBatches();
    },
    filterBatches() {
      let filtered = [...this.batches];
      
      // Apply search filter
      if (this.searchQuery) {
        const query = this.searchQuery.toLowerCase();
        filtered = filtered.filter(batch => 
          batch.item_code.toLowerCase().includes(query) ||
          batch.item_name.toLowerCase().includes(query) ||
          batch.batch_number.toLowerCase().includes(query)
        );
      }
      
      // Apply tab filter
      switch (this.activeTab) {
        case 'critical':
          filtered = filtered.filter(batch => batch.days_until_expiry >= 0 && batch.days_until_expiry <= 7);
          break;
        case 'warning':
          filtered = filtered.filter(batch => batch.days_until_expiry > 7 && batch.days_until_expiry <= 30);
          break;
        case 'expired':
          filtered = filtered.filter(batch => batch.days_until_expiry < 0);
          break;
      }
      
      // Apply sorting
      filtered.sort((a, b) => {
        let valueA = a[this.sortColumn];
        let valueB = b[this.sortColumn];
        
        if (this.sortColumn === 'expiry_date') {
          valueA = new Date(valueA).getTime();
          valueB = new Date(valueB).getTime();
        }
        
        if (valueA < valueB) return this.sortDirection === 'asc' ? -1 : 1;
        if (valueA > valueB) return this.sortDirection === 'asc' ? 1 : -1;
        return 0;
      });
      
      this.filteredBatches = filtered;
      this.currentPage = 1;
    },
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortColumn = column;
        this.sortDirection = 'asc';
      }
      
      this.filterBatches();
    },
    getSortIcon() {
      return this.sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
    },
    exportToCsv() {
      // Prepare CSV content
      const headers = ['Item Code', 'Item Name', 'Batch Number', 'Expiry Date', 'Days Until Expiry', 'Status'];
      
      const rows = this.filteredBatches.map(batch => [
        batch.item_code,
        batch.item_name,
        batch.batch_number,
        batch.expiry_date,
        batch.days_until_expiry,
        this.getStatusText(batch)
      ]);
      
      const csvContent = [
        headers.join(','),
        ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
      ].join('\n');
      
      // Create download link
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `expiry-report-${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    },
    printReport() {
      // Create a printable version of the table
      const printContent = document.createElement('div');
      
      // Add title
      const title = document.createElement('h1');
      title.textContent = 'Expiry Management Report';
      printContent.appendChild(title);
      
      // Add date
      const date = document.createElement('p');
      date.textContent = `Generated on: ${new Date().toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })}`;
      printContent.appendChild(date);
      
      // Add filter info
      const filter = document.createElement('p');
      filter.textContent = `Showing items expiring within ${this.daysFilter} days`;
      printContent.appendChild(filter);
      
      // Create table
      const table = document.createElement('table');
      table.style.width = '100%';
      table.style.borderCollapse = 'collapse';
      table.style.marginTop = '20px';
      
      // Add table headers
      const thead = document.createElement('thead');
      const headerRow = document.createElement('tr');
      
      ['Item Code', 'Item Name', 'Batch Number', 'Expiry Date', 'Days Until Expiry', 'Status'].forEach(text => {
        const th = document.createElement('th');
        th.textContent = text;
        th.style.border = '1px solid #ddd';
        th.style.padding = '8px';
        th.style.backgroundColor = '#f2f2f2';
        th.style.textAlign = 'left';
        headerRow.appendChild(th);
      });
      
      thead.appendChild(headerRow);
      table.appendChild(thead);
      
      // Add table body
      const tbody = document.createElement('tbody');
      
      this.filteredBatches.forEach(batch => {
        const row = document.createElement('tr');
        
        [
          batch.item_code,
          batch.item_name,
          batch.batch_number,
          this.formatDate(batch.expiry_date),
          batch.days_until_expiry,
          this.getStatusText(batch)
        ].forEach(text => {
          const td = document.createElement('td');
          td.textContent = text;
          td.style.border = '1px solid #ddd';
          td.style.padding = '8px';
          row.appendChild(td);
        });
        
        // Add background color based on status
        if (batch.days_until_expiry < 0) {
          row.style.backgroundColor = '#FFEBEE';
        } else if (batch.days_until_expiry <= 7) {
          row.style.backgroundColor = '#FFF3E0';
        } else if (batch.days_until_expiry <= 30) {
          row.style.backgroundColor = '#FFFDE7';
        }
        
        tbody.appendChild(row);
      });
      
      table.appendChild(tbody);
      printContent.appendChild(table);
      
      // Create print window
      const printWindow = window.open('', '_blank');
      printWindow.document.write(`
        <html>
          <head>
            <title>Expiry Management Report</title>
            <style>
              body {
                font-family: Arial, sans-serif;
                padding: 20px;
              }
              h1 {
                color: #333;
              }
              p {
                color: #666;
              }
              @media print {
                body {
                  padding: 0;
                }
              }
            </style>
          </head>
          <body>
            ${printContent.innerHTML}
          </body>
        </html>
      `);
      
      printWindow.document.close();
      setTimeout(() => {
        printWindow.print();
      }, 500);
    }
  }
};
</script>

<style scoped>
.expiry-dashboard {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
}

.filter-controls {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.days-filter {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.days-filter label {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.days-filter select {
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background-color: white;
}

.search-box {
  position: relative;
  width: 250px;
}

.search-box i {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-500);
}

.search-box input {
  width: 100%;
  padding: 0.375rem 0.75rem 0.375rem 2rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.clear-btn {
  position: absolute;
  right: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--gray-500);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem;
}

.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  display: flex;
  align-items: center;
  padding: 1.25rem;
  border-radius: 0.5rem;
  border: 1px solid var(--gray-200);
  background-color: white;
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.stat-card.critical {
  border-left: 4px solid #ef4444;
}

.stat-card.warning {
  border-left: 4px solid #f59e0b;
}

.stat-card.attention {
  border-left: 4px solid #3b82f6;
}

.stat-card.expired {
  border-left: 4px solid #6b7280;
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  border-radius: 0.5rem;
  margin-right: 1rem;
  font-size: 1.5rem;
}

.stat-card.critical .stat-icon {
  color: #ef4444;
  background-color: rgba(239, 68, 68, 0.1);
}

.stat-card.warning .stat-icon {
  color: #f59e0b;
  background-color: rgba(245, 158, 11, 0.1);
}

.stat-card.attention .stat-icon {
  color: #3b82f6;
  background-color: rgba(59, 130, 246, 0.1);
}

.stat-card.expired .stat-icon {
  color: #6b7280;
  background-color: rgba(107, 114, 128, 0.1);
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.875rem;
  color: var(--gray-500);
}

.loading-section {
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

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 0;
  text-align: center;
}

.empty-icon {
  font-size: 3rem;
  color: #10b981;
  margin-bottom: 1rem;
}

.empty-state h3 {
  font-size: 1.25rem;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: var(--gray-500);
  max-width: 24rem;
}

.expiry-batches-wrapper {
  border: 1px solid var(--gray-200);
  border-radius: 0.5rem;
  overflow: hidden;
}

.table-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.table-tabs {
  display: flex;
}

.tab-btn {
  background: none;
  border: none;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  color: var(--gray-600);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: var(--gray-900);
}

.tab-btn.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
  font-weight: 500;
}

.table-tools {
  display: flex;
  gap: 0.5rem;
}

.expiry-table-container {
  overflow-x: auto;
}

.expiry-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.expiry-table th {
  text-align: left;
  padding: 0.75rem 1rem;
  background-color: var(--gray-50);
  color: var(--gray-600);
  font-weight: 500;
  border-bottom: 1px solid var(--gray-200);
}

.expiry-table th.sortable {
  cursor: pointer;
  user-select: none;
}

.expiry-table th.sortable:hover {
  background-color: var(--gray-100);
}

.expiry-table th i {
  margin-left: 0.25rem;
}

.expiry-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--gray-100);
  color: var(--gray-800);
}

.expiry-table tr:hover td {
  background-color: var(--gray-50);
}

.row-expired {
  background-color: rgba(239, 68, 68, 0.05);
}

.row-critical {
  background-color: rgba(245, 158, 11, 0.05);
}

.row-warning {
  background-color: rgba(245, 158, 11, 0.03);
}

.code-cell {
  font-family: monospace;
  font-size: 0.8125rem;
}

.days-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2rem;
  height: 1.5rem;
  padding: 0 0.5rem;
  border-radius: 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-expired {
  background-color: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.badge-critical {
  background-color: rgba(245, 158, 11, 0.1);
  color: #d97706;
}

.badge-warning {
  background-color: rgba(234, 179, 8, 0.1);
  color: #ca8a04;
}

.badge-attention {
  background-color: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 1.5rem;
  padding: 0 0.75rem;
  border-radius: 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.actions-cell {
  display: flex;
  gap: 0.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

.btn-outline {
  background-color: transparent;
  border: 1px solid var(--gray-200);
  color: var(--gray-700);
}

.btn-outline:hover {
  background-color: var(--gray-100);
}

.btn-outline-info {
  border: 1px solid #3b82f6;
  color: #3b82f6;
}

.btn-outline-info:hover {
  background-color: rgba(59, 130, 246, 0.1);
}

.btn-outline-primary {
  border: 1px solid var(--primary-color);
  color: var(--primary-color);
}

.btn-outline-primary:hover {
  background-color: rgba(37, 99, 235, 0.1);
}

.btn i {
  margin-right: 0.375rem;
}

.btn-sm i {
  margin-right: 0;
}

.pagination-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: var(--gray-50);
  border-top: 1px solid var(--gray-200);
}

.pagination-info {
  font-size: 0.875rem;
  color: var(--gray-500);
}

.pagination-buttons {
  display: flex;
  gap: 0.25rem;
}

.pagination-btn {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.375rem;
  background: none;
  border: 1px solid var(--gray-200);
  color: var(--gray-500);
  cursor: pointer;
}

.pagination-btn:hover:not(:disabled) {
  background-color: var(--gray-100);
  color: var(--gray-800);
}

.pagination-btn.active {
  background-color: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-ellipsis {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 2rem;
  height: 2rem;
  color: var(--gray-500);
}

@media (max-width: 768px) {
  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .filter-controls {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }
  
  .search-box {
    width: 100%;
  }
  
  .dashboard-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .table-actions {
    flex-direction: column;
    gap: 1rem;
  }
  
  .table-tabs {
    width: 100%;
    overflow-x: auto;
  }
  
  .pagination-controls {
    flex-direction: column;
    gap: 1rem;
  }
}

@media (max-width: 640px) {
  .dashboard-stats {
    grid-template-columns: 1fr;
  }
}
</style>