<template>
  <div class="pdf-order-capture">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-file-pdf"></i>
            PDF Order Capture
          </h1>
          <p class="page-subtitle">Upload PDF files and automatically create sales orders using AI</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="isLoading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': isLoading }"></i>
            Refresh
          </button>
          <button @click="showUploadModal = true" class="btn btn-primary">
            <i class="fas fa-upload"></i>
            Upload PDF
          </button>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon success">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.completed || 0 }}</h3>
          <p>Completed</p>
          <span class="stat-change success">{{ statistics.success_rate || 0 }}% success rate</span>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon warning">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.processing || 0 }}</h3>
          <p>Processing</p>
          <span class="stat-change neutral">In progress</span>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon danger">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.failed || 0 }}</h3>
          <p>Failed</p>
          <span class="stat-change danger">Need attention</span>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon info">
          <i class="fas fa-brain"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.average_confidence || 0 }}%</h3>
          <p>Avg. Confidence</p>
          <span class="stat-change info">AI accuracy</span>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section">
      <div class="search-filters">
        <div class="filter-group">
          <label>Status</label>
          <select v-model="filters.status" @change="loadCaptureHistory">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="data_extracted">Data Extracted</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Date Range</label>
          <select v-model="filters.days" @change="loadCaptureHistory">
            <option value="7">Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="">All time</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Per Page</label>
          <select v-model="filters.per_page" @change="loadCaptureHistory">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
          </select>
        </div>
        
        <button @click="clearFilters" class="btn btn-outline">
          <i class="fas fa-times"></i>
          Clear Filters
        </button>
      </div>
    </div>

    <!-- Processing History Table -->
    <div class="table-container">
      <div class="table-header">
        <h3>Processing History</h3>
        <div class="table-actions">
          <button @click="toggleSelectAll" class="btn btn-sm btn-outline">
            <i class="fas fa-check-square"></i>
            {{ selectedCaptures.length > 0 ? 'Deselect All' : 'Select All' }}
          </button>
          <button 
            v-if="selectedCaptures.length > 0" 
            @click="bulkRetry" 
            class="btn btn-sm btn-warning"
            :disabled="bulkLoading"
          >
            <i class="fas fa-redo" :class="{ 'fa-spin': bulkLoading }"></i>
            Retry Selected ({{ selectedCaptures.length }})
          </button>
        </div>
      </div>

      <div v-if="isLoading" class="loading-state">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading capture history...</p>
      </div>

      <div v-else-if="captures.length === 0" class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-file-pdf"></i>
        </div>
        <h3>No PDF captures found</h3>
        <p>Upload your first PDF to get started with AI-powered order capture</p>
        <button @click="showUploadModal = true" class="btn btn-primary">
          <i class="fas fa-upload"></i>
          Upload PDF
        </button>
      </div>

      <div v-else class="custom-table">
        <table>
          <thead>
            <tr>
              <th class="checkbox-col">
                <input 
                  type="checkbox" 
                  :checked="selectedCaptures.length === captures.length && captures.length > 0"
                  @change="toggleSelectAll"
                >
              </th>
              <th>File</th>
              <th>Status</th>
              <th>Customer</th>
              <th>Items</th>
              <th>Confidence</th>
              <th>Sales Order</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="capture in captures" :key="capture.id" class="table-row">
              <td class="checkbox-col">
                <input 
                  type="checkbox" 
                  :value="capture.id" 
                  v-model="selectedCaptures"
                >
              </td>
              
              <td class="file-info">
                <div class="file-details">
                  <div class="file-name">
                    <i class="fas fa-file-pdf text-danger"></i>
                    {{ capture.filename }}
                  </div>
                  <div class="file-meta">
                    {{ capture.file_size_human }}
                  </div>
                </div>
              </td>
              
              <td>
                <span 
                  class="status-badge" 
                  :class="getStatusClass(capture.status)"
                >
                  {{ formatStatus(capture.status) }}
                </span>
              </td>
              
              <td class="customer-info">
                <div v-if="capture.extracted_customer">
                  <div class="customer-name">{{ capture.extracted_customer.name }}</div>
                  <div class="customer-meta">{{ capture.extracted_customer.email || 'No email' }}</div>
                </div>
                <span v-else class="text-muted">-</span>
              </td>
              
              <td class="items-info">
                <div v-if="capture.extracted_items && capture.extracted_items.length > 0">
                  <div class="items-count">{{ capture.extracted_items.length }} items</div>
                  <div class="items-preview">
                    {{ capture.extracted_items[0].name }}
                    <span v-if="capture.extracted_items.length > 1">
                      +{{ capture.extracted_items.length - 1 }} more
                    </span>
                  </div>
                </div>
                <span v-else class="text-muted">-</span>
              </td>
              
              <td class="confidence-score">
                <div v-if="capture.confidence_score" class="confidence-display">
                  <div class="confidence-bar">
                    <div 
                      class="confidence-fill" 
                      :style="{ width: capture.confidence_score + '%' }"
                      :class="getConfidenceClass(capture.confidence_score)"
                    ></div>
                  </div>
                  <span class="confidence-text">{{ capture.confidence_score }}%</span>
                </div>
                <span v-else class="text-muted">-</span>
              </td>
              
              <td class="sales-order">
                <div v-if="capture.sales_order">
                  <router-link 
                    :to="`/sales/orders/${capture.sales_order.so_id}`"
                    class="order-link"
                  >
                    {{ capture.sales_order.so_number }}
                  </router-link>
                  <div class="order-amount">${{ formatCurrency(capture.sales_order.total_amount) }}</div>
                </div>
                <span v-else class="text-muted">-</span>
              </td>
              
              <td class="created-date">
                <div class="date-display">
                  <div class="date-main">{{ formatDate(capture.created_at) }}</div>
                  <div class="date-time">{{ formatTime(capture.created_at) }}</div>
                </div>
              </td>
              
              <td class="actions">
                <div class="action-buttons">
                  <button 
                    @click="viewDetails(capture)"
                    class="btn-icon"
                    title="View Details"
                  >
                    <i class="fas fa-eye"></i>
                  </button>
                  
                  <button 
                    v-if="capture.status === 'failed'"
                    @click="retryCapture(capture.id)"
                    class="btn-icon btn-warning"
                    title="Retry Processing"
                    :disabled="retryLoading[capture.id]"
                  >
                    <i class="fas fa-redo" :class="{ 'fa-spin': retryLoading[capture.id] }"></i>
                  </button>
                  
                  <button 
                    @click="downloadFile(capture.id)"
                    class="btn-icon btn-secondary"
                    title="Download PDF"
                  >
                    <i class="fas fa-download"></i>
                  </button>
                  
                  <button 
                    @click="deleteCapture(capture.id)"
                    class="btn-icon btn-danger"
                    title="Delete"
                    :disabled="deleteLoading[capture.id]"
                  >
                    <i class="fas fa-trash" :class="{ 'fa-spin': deleteLoading[capture.id] }"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total > 0" class="pagination-container">
        <div class="pagination-info">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
        </div>
        <div class="pagination-controls">
          <button 
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="btn btn-sm btn-outline"
          >
            <i class="fas fa-chevron-left"></i>
            Previous
          </button>
          
          <span class="page-numbers">
            <button 
              v-for="page in visiblePages" 
              :key="page"
              @click="changePage(page)"
              class="btn btn-sm"
              :class="{ 'btn-primary': page === pagination.current_page, 'btn-outline': page !== pagination.current_page }"
            >
              {{ page }}
            </button>
          </span>
          
          <button 
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="btn btn-sm btn-outline"
          >
            Next
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->
    <div v-if="showUploadModal" class="modal-overlay" @click="closeUploadModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Upload PDF Order</h3>
          <button @click="closeUploadModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="upload-area" :class="{ 'dragover': isDragOver }" 
               @drop="handleDrop" @dragover.prevent="isDragOver = true" 
               @dragleave="isDragOver = false" @dragenter.prevent>
            <input 
              ref="fileInput" 
              type="file" 
              accept=".pdf" 
              @change="handleFileSelect" 
              style="display: none"
            >
            
            <div v-if="!selectedFile" class="upload-placeholder">
              <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
              </div>
              <h4>Drag & Drop PDF File</h4>
              <p>or <button @click="$refs.fileInput.click()" class="link-btn">browse files</button></p>
              <div class="upload-info">
                <small>Supported: PDF files up to 10MB</small>
              </div>
            </div>
            
            <div v-else class="file-selected">
              <div class="file-preview">
                <i class="fas fa-file-pdf text-danger"></i>
                <div class="file-details">
                  <div class="file-name">{{ selectedFile.name }}</div>
                  <div class="file-size">{{ formatFileSize(selectedFile.size) }}</div>
                </div>
                <button @click="clearSelectedFile" class="remove-btn">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </div>
          
          <!-- Processing Options -->
          <div class="processing-options">
            <h4>Processing Options</h4>
            
            <div class="option-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="uploadOptions.auto_create_missing_data">
                <span>Auto-create missing customers and items</span>
              </label>
            </div>
            
            <div class="option-row">
              <div class="option-field">
                <label>Preferred Currency</label>
                <select v-model="uploadOptions.preferred_currency">
                  <option value="USD">USD - US Dollar</option>
                  <option value="EUR">EUR - Euro</option>
                  <option value="GBP">GBP - British Pound</option>
                  <option value="IDR">IDR - Indonesian Rupiah</option>
                </select>
              </div>
              
              <div class="option-field">
                <label>Confidence Threshold</label>
                <select v-model="uploadOptions.processing_options.confidence_threshold">
                  <option value="60">60% - Low</option>
                  <option value="70">70% - Medium</option>
                  <option value="80">80% - High</option>
                  <option value="90">90% - Very High</option>
                </select>
              </div>
            </div>
            
            <div class="option-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="uploadOptions.processing_options.auto_approve">
                <span>Auto-approve if confidence is high enough</span>
              </label>
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button @click="closeUploadModal" class="btn btn-secondary">
            Cancel
          </button>
          <button @click="previewExtraction" class="btn btn-outline" :disabled="!selectedFile || isUploading">
            <i class="fas fa-eye"></i>
            Preview
          </button>
          <button @click="uploadAndProcess" class="btn btn-primary" :disabled="!selectedFile || isUploading">
            <i class="fas fa-upload" :class="{ 'fa-spin': isUploading }"></i>
            {{ isUploading ? 'Processing...' : 'Upload & Process' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Details Modal -->
    <div v-if="showDetailsModal" class="modal-overlay" @click="closeDetailsModal">
      <div class="modal-content modal-large" @click.stop>
        <div class="modal-header">
          <h3>Capture Details</h3>
          <button @click="closeDetailsModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div v-if="selectedCapture" class="details-content">
            <!-- Basic Info -->
            <div class="details-section">
              <h4>File Information</h4>
              <div class="info-grid">
                <div class="info-item">
                  <label>Filename</label>
                  <span>{{ selectedCapture.filename }}</span>
                </div>
                <div class="info-item">
                  <label>File Size</label>
                  <span>{{ selectedCapture.file_size_human }}</span>
                </div>
                <div class="info-item">
                  <label>Status</label>
                  <span class="status-badge" :class="getStatusClass(selectedCapture.status)">
                    {{ formatStatus(selectedCapture.status) }}
                  </span>
                </div>
                <div class="info-item">
                  <label>Confidence Score</label>
                  <span>{{ selectedCapture.confidence_score || 'N/A' }}%</span>
                </div>
              </div>
            </div>
            
            <!-- Extracted Data -->
            <div v-if="selectedCapture.extracted_data" class="details-section">
              <h4>Extracted Data</h4>
              
              <!-- Customer Info -->
              <div v-if="selectedCapture.extracted_customer" class="extracted-section">
                <h5>Customer Information</h5>
                <div class="info-grid">
                  <div class="info-item">
                    <label>Name</label>
                    <span>{{ selectedCapture.extracted_customer.name }}</span>
                  </div>
                  <div class="info-item">
                    <label>Email</label>
                    <span>{{ selectedCapture.extracted_customer.email || 'N/A' }}</span>
                  </div>
                  <div class="info-item">
                    <label>Phone</label>
                    <span>{{ selectedCapture.extracted_customer.phone || 'N/A' }}</span>
                  </div>
                  <div class="info-item">
                    <label>Address</label>
                    <span>{{ selectedCapture.extracted_customer.address || 'N/A' }}</span>
                  </div>
                </div>
              </div>
              
              <!-- Items -->
              <div v-if="selectedCapture.extracted_items" class="extracted-section">
                <h5>Items ({{ selectedCapture.extracted_items.length }})</h5>
                <div class="items-list">
                  <div v-for="(item, index) in selectedCapture.extracted_items" :key="index" class="item-card">
                    <div class="item-header">
                      <span class="item-name">{{ item.name }}</span>
                      <span class="item-qty">Qty: {{ item.quantity }}</span>
                    </div>
                    <div class="item-details">
                      <span v-if="item.unit_price">Price: ${{ item.unit_price }}</span>
                      <span v-if="item.uom">UOM: {{ item.uom }}</span>
                      <span v-if="item.description">{{ item.description }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Error Message -->
            <div v-if="selectedCapture.error_message" class="details-section">
              <h4>Error Details</h4>
              <div class="error-message">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                {{ selectedCapture.error_message }}
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button @click="closeDetailsModal" class="btn btn-secondary">
            Close
          </button>
          <button 
            v-if="selectedCapture && selectedCapture.file_path"
            @click="downloadFile(selectedCapture.id)"
            class="btn btn-outline"
          >
            <i class="fas fa-download"></i>
            Download PDF
          </button>
          <button 
            v-if="selectedCapture && selectedCapture.status === 'failed'"
            @click="retryCapture(selectedCapture.id)"
            class="btn btn-warning"
          >
            <i class="fas fa-redo"></i>
            Retry Processing
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'PdfOrderCapture',
  data() {
    return {
      // State
      isLoading: false,
      isUploading: false,
      bulkLoading: false,
      retryLoading: {},
      deleteLoading: {},
      
      // Data
      captures: [],
      statistics: {},
      
      // Pagination
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
        from: 0,
        to: 0
      },
      
      // Filters
      filters: {
        status: '',
        days: '30',
        per_page: 20
      },
      
      // Selection
      selectedCaptures: [],
      
      // Modals
      showUploadModal: false,
      showDetailsModal: false,
      selectedCapture: null,
      
      // Upload
      selectedFile: null,
      isDragOver: false,
      uploadOptions: {
        auto_create_missing_data: true,
        preferred_currency: 'USD',
        processing_options: {
          confidence_threshold: 80,
          auto_approve: false,
          use_ocr: true
        }
      }
    }
  },
  
  computed: {
    visiblePages() {
      const current = this.pagination.current_page
      const last = this.pagination.last_page
      const pages = []
      
      if (last <= 7) {
        for (let i = 1; i <= last; i++) {
          pages.push(i)
        }
      } else {
        if (current <= 4) {
          for (let i = 1; i <= 5; i++) {
            pages.push(i)
          }
          pages.push('...')
          pages.push(last)
        } else if (current >= last - 3) {
          pages.push(1)
          pages.push('...')
          for (let i = last - 4; i <= last; i++) {
            pages.push(i)
          }
        } else {
          pages.push(1)
          pages.push('...')
          for (let i = current - 1; i <= current + 1; i++) {
            pages.push(i)
          }
          pages.push('...')
          pages.push(last)
        }
      }
      
      return pages
    }
  },
  
  async mounted() {
    await this.loadData()
  },
  
  methods: {
    async loadData() {
      await Promise.all([
        this.loadStatistics(),
        this.loadCaptureHistory()
      ])
    },
    
    async loadStatistics() {
      try {
        const response = await axios.get('/pdf-order-capture/statistics/overview', {
          params: { days: this.filters.days }
        })
        this.statistics = response.data.data
      } catch (error) {
        console.error('Failed to load statistics:', error)
        this.$toast?.error('Failed to load statistics')
      }
    },
    
    async loadCaptureHistory() {
      this.isLoading = true
      try {
        const params = {
          page: this.pagination.current_page,
          per_page: this.filters.per_page
        }
        
        if (this.filters.status) params.status = this.filters.status
        if (this.filters.days) params.days = this.filters.days
        
        const response = await axios.get('/pdf-order-capture', { params })
        const data = response.data.data
        
        this.captures = data.data
        this.pagination = {
          current_page: data.current_page,
          last_page: data.last_page,
          per_page: data.per_page,
          total: data.total,
          from: data.from,
          to: data.to
        }
      } catch (error) {
        console.error('Failed to load capture history:', error)
        this.$toast?.error('Failed to load capture history')
      } finally {
        this.isLoading = false
      }
    },
    
    async refreshData() {
      await this.loadData()
      this.$toast?.success('Data refreshed successfully')
    },
    
    // Upload Functions
    handleFileSelect(event) {
      const file = event.target.files[0]
      if (file && file.type === 'application/pdf') {
        this.selectedFile = file
      } else {
        this.$toast?.error('Please select a valid PDF file')
      }
    },
    
    handleDrop(event) {
      event.preventDefault()
      this.isDragOver = false
      
      const files = event.dataTransfer.files
      if (files.length > 0) {
        const file = files[0]
        if (file.type === 'application/pdf') {
          this.selectedFile = file
        } else {
          this.$toast?.error('Please select a valid PDF file')
        }
      }
    },
    
    clearSelectedFile() {
      this.selectedFile = null
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }
    },
    
    async previewExtraction() {
      if (!this.selectedFile) return
      
      this.isUploading = true
      try {
        const formData = new FormData()
        formData.append('pdf_file', this.selectedFile)
        
        const response = await axios.post('/pdf-order-capture/preview', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        // Show preview in a modal or separate component
        console.log('Preview data:', response.data.data)
        this.$toast?.success('Preview generated successfully')
        // You can implement a preview modal here
        
      } catch (error) {
        console.error('Preview failed:', error)
        this.$toast?.error(error.response?.data?.message || 'Preview failed')
      } finally {
        this.isUploading = false
      }
    },
    
    async uploadAndProcess() {
      if (!this.selectedFile) return
      
      this.isUploading = true
      try {
        const formData = new FormData()
        formData.append('pdf_file', this.selectedFile)
        formData.append('auto_create_missing_data', this.uploadOptions.auto_create_missing_data ? '1' : '0')
        formData.append('preferred_currency', this.uploadOptions.preferred_currency)
        formData.append('processing_options', JSON.stringify(this.uploadOptions.processing_options))
        
        const response = await axios.post('/pdf-order-capture', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        this.$toast?.success('PDF uploaded and processing started')
        this.closeUploadModal()
        await this.loadData()
        
        // Navigate to sales order if created
        if (response.data.data.sales_order) {
          this.$router.push(`/sales/orders/${response.data.data.sales_order.so_id}`)
        }
        
      } catch (error) {
        console.error('Upload failed:', error)
        this.$toast?.error(error.response?.data?.message || 'Upload failed')
      } finally {
        this.isUploading = false
      }
    },
    
    // Action Functions
    async retryCapture(captureId) {
      this.$set(this.retryLoading, captureId, true)
      try {
        await axios.post(`/pdf-order-capture/${captureId}/retry`)
        this.$toast?.success('Processing restarted')
        await this.loadData()
      } catch (error) {
        console.error('Retry failed:', error)
        this.$toast?.error(error.response?.data?.message || 'Retry failed')
      } finally {
        this.$set(this.retryLoading, captureId, false)
      }
    },
    
    async deleteCapture(captureId) {
      if (!confirm('Are you sure you want to delete this capture?')) return
      
      this.$set(this.deleteLoading, captureId, true)
      try {
        await axios.delete(`/pdf-order-capture/${captureId}`)
        this.$toast?.success('Capture deleted successfully')
        await this.loadData()
      } catch (error) {
        console.error('Delete failed:', error)
        this.$toast?.error(error.response?.data?.message || 'Delete failed')
      } finally {
        this.$set(this.deleteLoading, captureId, false)
      }
    },
    
    async downloadFile(captureId) {
      try {
        const response = await axios.get(`/pdf-order-capture/${captureId}/download`, {
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `capture_${captureId}.pdf`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
      } catch (error) {
        console.error('Download failed:', error)
        this.$toast?.error('Download failed')
      }
    },
    
    async viewDetails(capture) {
      try {
        const response = await axios.get(`/pdf-order-capture/${capture.id}`)
        this.selectedCapture = response.data.data
        this.showDetailsModal = true
      } catch (error) {
        console.error('Failed to load details:', error)
        this.$toast?.error('Failed to load capture details')
      }
    },
    
    // Bulk Actions
    toggleSelectAll() {
      if (this.selectedCaptures.length === this.captures.length) {
        this.selectedCaptures = []
      } else {
        this.selectedCaptures = this.captures.map(c => c.id)
      }
    },
    
    async bulkRetry() {
      if (this.selectedCaptures.length === 0) return
      
      this.bulkLoading = true
      try {
        await axios.post('/pdf-order-capture/bulk/retry', {
          capture_ids: this.selectedCaptures
        })
        this.$toast?.success(`Retrying ${this.selectedCaptures.length} captures`)
        this.selectedCaptures = []
        await this.loadData()
      } catch (error) {
        console.error('Bulk retry failed:', error)
        this.$toast?.error('Bulk retry failed')
      } finally {
        this.bulkLoading = false
      }
    },
    
    // Pagination
    changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page && page !== this.pagination.current_page) {
        this.pagination.current_page = page
        this.loadCaptureHistory()
      }
    },
    
    // Filters
    clearFilters() {
      this.filters = {
        status: '',
        days: '30',
        per_page: 20
      }
      this.pagination.current_page = 1
      this.loadCaptureHistory()
    },
    
    // Modal Functions
    closeUploadModal() {
      this.showUploadModal = false
      this.clearSelectedFile()
      this.isDragOver = false
    },
    
    closeDetailsModal() {
      this.showDetailsModal = false
      this.selectedCapture = null
    },
    
    // Helper Functions
    getStatusClass(status) {
      const statusClasses = {
        pending: 'status-secondary',
        processing: 'status-warning',
        data_extracted: 'status-info',
        validating: 'status-info',
        creating_order: 'status-warning',
        completed: 'status-success',
        failed: 'status-danger',
        cancelled: 'status-secondary'
      }
      return statusClasses[status] || 'status-secondary'
    },
    
    formatStatus(status) {
      const statusLabels = {
        pending: 'Pending',
        processing: 'Processing',
        data_extracted: 'Data Extracted',
        validating: 'Validating',
        creating_order: 'Creating Order',
        completed: 'Completed',
        failed: 'Failed',
        cancelled: 'Cancelled'
      }
      return statusLabels[status] || status
    },
    
    getConfidenceClass(score) {
      if (score >= 80) return 'confidence-high'
      if (score >= 60) return 'confidence-medium'
      return 'confidence-low'
    },
    
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString()
    },
    
    formatTime(dateString) {
      return new Date(dateString).toLocaleTimeString()
    },
    
    formatCurrency(amount) {
      return parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },
    
    formatFileSize(bytes) {
      const units = ['B', 'KB', 'MB', 'GB']
      let size = bytes
      let unitIndex = 0
      
      while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024
        unitIndex++
      }
      
      return `${size.toFixed(1)} ${units[unitIndex]}`
    }
  }
}
</script>

<style scoped>
.pdf-order-capture {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.title-section {
  flex: 1;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title i {
  color: #dc3545;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 1.1rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Statistics */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: white;
}

.stat-icon.success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon.warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.stat-icon.danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
.stat-icon.info { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }

.stat-content h3 {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.stat-content p {
  color: var(--text-secondary);
  margin: 0 0 0.5rem 0;
  font-weight: 500;
}

.stat-change {
  font-size: 0.8rem;
  font-weight: 500;
}

.stat-change.success { color: #10b981; }
.stat-change.danger { color: #ef4444; }
.stat-change.neutral { color: var(--text-muted); }
.stat-change.info { color: #3b82f6; }

/* Filters */
.filters-section {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.search-filters {
  display: flex;
  gap: 1rem;
  align-items: end;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 150px;
}

.filter-group label {
  font-weight: 500;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.filter-group select {
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: white;
  color: var(--text-primary);
  font-size: 0.9rem;
}

/* Table */
.table-container {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  overflow: hidden;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-secondary);
}

.table-header h3 {
  margin: 0;
  color: var(--text-primary);
}

.table-actions {
  display: flex;
  gap: 1rem;
}

.custom-table {
  overflow-x: auto;
}

.custom-table table {
  width: 100%;
  border-collapse: collapse;
}

.custom-table th {
  text-align: left;
  padding: 1rem;
  background: var(--bg-secondary);
  border-bottom: 2px solid var(--border-color);
  font-weight: 600;
  color: var(--text-secondary);
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.custom-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.table-row:hover {
  background: var(--bg-secondary);
}

.checkbox-col {
  width: 40px;
  text-align: center;
}

/* Table Cell Styles */
.file-info {
  min-width: 200px;
}

.file-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.file-name {
  font-weight: 500;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.file-meta {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.status-badge {
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-success { background: #d1fae5; color: #065f46; }
.status-warning { background: #fef3c7; color: #92400e; }
.status-danger { background: #fee2e2; color: #991b1b; }
.status-info { background: #dbeafe; color: #1e40af; }
.status-secondary { background: var(--bg-secondary); color: var(--text-muted); }

.customer-info, .items-info {
  min-width: 150px;
}

.customer-name, .items-count {
  font-weight: 500;
  color: var(--text-primary);
}

.customer-meta, .items-preview {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.confidence-display {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 100px;
}

.confidence-bar {
  flex: 1;
  height: 8px;
  background: var(--bg-secondary);
  border-radius: 4px;
  overflow: hidden;
}

.confidence-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.confidence-high { background: #10b981; }
.confidence-medium { background: #f59e0b; }
.confidence-low { background: #ef4444; }

.confidence-text {
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-primary);
}

.order-link {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 500;
}

.order-link:hover {
  text-decoration: underline;
}

.order-amount {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.date-display {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.date-main {
  font-weight: 500;
  color: var(--text-primary);
}

.date-time {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
  background: white;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.9rem;
}

.btn-icon:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-icon.btn-warning { border-color: #f59e0b; color: #f59e0b; }
.btn-icon.btn-secondary { border-color: var(--gray-400); color: var(--gray-600); }
.btn-icon.btn-danger { border-color: #ef4444; color: #ef4444; }

.btn-icon.btn-warning:hover { background: #fef3c7; }
.btn-icon.btn-secondary:hover { background: var(--gray-100); }
.btn-icon.btn-danger:hover { background: #fee2e2; }

/* Loading and Empty States */
.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.loading-spinner, .empty-icon {
  font-size: 3rem;
  color: var(--text-muted);
  margin-bottom: 1rem;
}

.empty-state h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
}

.empty-state p {
  color: var(--text-muted);
  margin-bottom: 1.5rem;
}

/* Pagination */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

.pagination-info {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-numbers {
  display: flex;
  gap: 0.25rem;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  border: none;
  font-weight: 500;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
  background: var(--card-bg);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--bg-secondary);
  border-color: var(--gray-400);
}

.btn-outline {
  background: transparent;
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}

.btn-outline:hover:not(:disabled) {
  background: var(--bg-secondary);
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.btn-warning {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
}

.btn-warning:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

/* Modals */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 2rem;
}

.modal-content {
  background: var(--card-bg);
  border-radius: 12px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-large {
  max-width: 800px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  color: var(--text-primary);
}

.close-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: var(--bg-secondary);
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.close-btn:hover {
  background: var(--border-color);
  color: var(--text-primary);
}

.modal-body {
  flex: 1;
  padding: 1.5rem;
  overflow-y: auto;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Upload Area */
.upload-area {
  border: 2px dashed var(--border-color);
  border-radius: 12px;
  padding: 3rem 2rem;
  text-align: center;
  transition: all 0.2s ease;
  margin-bottom: 2rem;
}

.upload-area.dragover {
  border-color: var(--primary-color);
  background: rgba(59, 130, 246, 0.05);
}

.upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.upload-icon {
  font-size: 3rem;
  color: var(--text-muted);
}

.upload-placeholder h4 {
  margin: 0;
  color: var(--text-primary);
}

.upload-placeholder p {
  margin: 0;
  color: var(--text-muted);
}

.link-btn {
  background: none;
  border: none;
  color: var(--primary-color);
  cursor: pointer;
  text-decoration: underline;
}

.upload-info {
  margin-top: 1rem;
}

.file-selected {
  display: flex;
  justify-content: center;
}

.file-preview {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: var(--bg-secondary);
  border-radius: 8px;
  max-width: 400px;
}

.file-preview i {
  font-size: 2rem;
}

.remove-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: var(--border-color);
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-btn:hover {
  background: #ef4444;
  color: white;
}

/* Processing Options */
.processing-options {
  border-top: 1px solid var(--border-color);
  padding-top: 1.5rem;
}

.processing-options h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.option-group {
  margin-bottom: 1rem;
}

.option-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.option-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.option-field label {
  font-weight: 500;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.option-field select {
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: white;
  color: var(--text-primary);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  color: var(--text-primary);
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
}

/* Details Modal */
.details-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.details-section {
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 1.5rem;
}

.details-section:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.details-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  font-size: 1.1rem;
}

.details-section h5 {
  margin: 0 0 1rem 0;
  color: var(--text-secondary);
  font-size: 1rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-item label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-item span {
  color: var(--text-primary);
  font-weight: 500;
}

.extracted-section {
  margin-bottom: 1.5rem;
}

.items-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.item-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1rem;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.item-name {
  font-weight: 500;
  color: var(--text-primary);
}

.item-qty {
  font-size: 0.9rem;
  color: var(--text-muted);
  background: var(--card-bg);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.item-details {
  display: flex;
  gap: 1rem;
  font-size: 0.9rem;
  color: var(--text-muted);
}

.error-message {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: #fee2e2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #991b1b;
}

.text-muted {
  color: var(--text-muted);
}

.text-danger {
  color: #ef4444;
}

/* Responsive */
@media (max-width: 768px) {
  .pdf-order-capture {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .header-actions {
    align-self: stretch;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .search-filters {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filter-group {
    min-width: auto;
  }
  
  .pagination-container {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .table-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .custom-table {
    font-size: 0.8rem;
  }
  
  .custom-table th,
  .custom-table td {
    padding: 0.5rem;
  }
  
  .action-buttons {
    flex-direction: column;
  }
  
  .option-row {
    grid-template-columns: 1fr;
  }
  
  .modal-overlay {
    padding: 1rem;
  }
  
  .modal-content {
    max-height: 95vh;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>