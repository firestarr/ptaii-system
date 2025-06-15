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
          <p class="page-subtitle">Upload PDF files and automatically create sales orders using AI with smart page-based processing</p>
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
              <th>Item Validation</th>
              <th>Confidence</th>
              <th>Sales Order</th>
              <th>Pages/Processing</th>
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
                    <span v-if="isLargeFile(capture.file_size)" class="file-size-indicator large-file">
                      <i class="fas fa-layer-group" title="Processed with chunking"></i>
                      Large
                    </span>
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

              <!-- Item Validation Column -->
              <td class="item-validation">
                <div v-if="capture.item_validation" class="validation-summary">
                  <div v-if="capture.item_validation.existing_items && capture.item_validation.existing_items.length > 0" 
                       class="validation-item success">
                    <i class="fas fa-check-circle"></i>
                    {{ capture.item_validation.existing_items.length }} found
                  </div>
                  <div v-if="capture.item_validation.missing_items && capture.item_validation.missing_items.length > 0" 
                       class="validation-item danger">
                    <i class="fas fa-times-circle"></i>
                    {{ capture.item_validation.missing_items.length }} missing
                  </div>
                  <div v-if="capture.item_validation.fuzzy_matches && capture.item_validation.fuzzy_matches.length > 0" 
                       class="validation-item warning">
                    <i class="fas fa-question-circle"></i>
                    {{ capture.item_validation.fuzzy_matches.length }} fuzzy
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
              
              <td class="processing-info">
                <div class="processing-details">
                  <div v-if="isPageBasedProcessing(capture)" class="processing-method page-based">
                    <i class="fas fa-file-alt" title="Processed page by page"></i>
                    {{ getTotalPages(capture) }} pages
                  </div>
                  <div v-else-if="isChunkedProcessing(capture)" class="processing-method chunked">
                    <i class="fas fa-layer-group" title="Processed with chunking"></i>
                    Chunked
                  </div>
                  <div v-else class="processing-method single">
                    <i class="fas fa-file-alt" title="Single request processing"></i>
                    Standard
                  </div>
                  <div v-if="capture.extracted_data && capture.extracted_data.processing_notes" class="processing-notes">
                    {{ getProcessingNotesSummary(capture.extracted_data.processing_notes) }}
                  </div>
                </div>
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

                  <!-- Create SO Button -->
                  <button 
                    v-if="capture.status === 'data_extracted' && !capture.created_so_id && canCreateSalesOrder(capture)"
                    @click="createSalesOrder(capture.id)"
                    class="btn-icon btn-success"
                    title="Create Sales Order"
                    :disabled="createSoLoading[capture.id]"
                  >
                    <i class="fas fa-plus" :class="{ 'fa-spin': createSoLoading[capture.id] }"></i>
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
                <small>Large files will be automatically processed page-by-page for better accuracy</small>
              </div>
            </div>
            
            <div v-else class="file-selected">
              <div class="file-preview">
                <i class="fas fa-file-pdf text-danger"></i>
                <div class="file-details">
                  <div class="file-name">{{ selectedFile.name }}</div>
                  <div class="file-size">
                    {{ formatFileSize(selectedFile.size) }}
                    <span v-if="willUseChunking(selectedFile.size)" class="chunking-indicator">
                      <i class="fas fa-file-alt"></i>
                      Will use page-based processing
                    </span>
                  </div>
                </div>
                <button @click="clearSelectedFile" class="remove-btn">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              
              <!-- Processing Time Estimate -->
              <div v-if="willUseChunking(selectedFile.size)" class="processing-estimate">
                <div class="estimate-info">
                  <i class="fas fa-info-circle"></i>
                  <span>Large file detected. Will be processed page-by-page for optimal accuracy.</span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Processing Options -->
          <div class="processing-options">
            <h4>Processing Options</h4>
            
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

            <!-- Page-based Processing Options for Large Files -->
            <div v-if="selectedFile && willUseChunking(selectedFile.size)" class="chunking-options">
              <h5>
                <i class="fas fa-file-alt"></i>
                Page-based Processing Settings
              </h5>
              <div class="chunking-info">
                <p>This file will be processed page-by-page to ensure accurate extraction. Each page is analyzed separately and results are intelligently merged.</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button @click="closeUploadModal" class="btn btn-secondary">
            Cancel
          </button>
          <button @click="uploadAndExtract" class="btn btn-primary" :disabled="!selectedFile || isUploading">
            <i class="fas fa-upload" :class="{ 'fa-spin': isUploading }"></i>
            {{ isUploading ? getProcessingText() : 'Upload & Extract Data' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Details Modal -->
    <div v-if="showDetailsModal" class="modal-overlay" @click="closeDetailsModal">
      <div class="modal-content modal-large" @click.stop>
        <div class="modal-header">
          <h3>
            <i class="fas fa-file-pdf"></i>
            Capture Details & Preview
          </h3>
          <button @click="closeDetailsModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div v-if="selectedCapture" class="details-content">
            
            <!-- PREVIEW SUMMARY (NEW SECTION) -->
            <div class="details-section" v-if="selectedCapture.status === 'data_extracted'">
              <h4>📋 Extraction Preview</h4>
              
              <!-- Overall Status -->
              <div class="preview-status">
                <div v-if="canCreateSalesOrder(selectedCapture)" class="status-card success">
                  <div class="status-icon">✅</div>
                  <div class="status-content">
                    <h5>Ready to Create Sales Order</h5>
                    <p>All items found in database. No issues detected.</p>
                  </div>
                </div>
                
                <div v-else class="status-card warning">
                  <div class="status-icon">⚠️</div>
                  <div class="status-content">
                    <h5>Action Required</h5>
                    <p>Some items are missing from database. Please review below.</p>
                  </div>
                </div>
              </div>
              
              <!-- Quick Stats -->
              <div class="preview-stats">
                <div class="stat-item">
                  <span class="stat-label">Customer:</span>
                  <span class="stat-value">{{ selectedCapture.extracted_customer?.name || 'Not detected' }}</span>
                </div>
                <div class="stat-item">
                  <span class="stat-label">Total Items:</span>
                  <span class="stat-value">{{ selectedCapture.extracted_items?.length || 0 }}</span>
                </div>
                <div class="stat-item" v-if="selectedCapture.item_validation">
                  <span class="stat-label">Found in DB:</span>
                  <span class="stat-value success">{{ selectedCapture.item_validation.existing_items?.length || 0 }}</span>
                </div>
                <div class="stat-item" v-if="selectedCapture.item_validation && selectedCapture.item_validation.missing_items?.length > 0">
                  <span class="stat-label">Missing:</span>
                  <span class="stat-value danger">{{ selectedCapture.item_validation.missing_items?.length || 0 }}</span>
                </div>
              </div>
            </div>
            
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
                <div v-if="isPageBasedProcessing(selectedCapture)" class="info-item">
                  <label>Processing Method</label>
                  <span class="processing-method page-based">
                    <i class="fas fa-file-alt"></i>
                    Page-based Processing ({{ getTotalPages(selectedCapture) }} pages)
                  </span>
                </div>
                <div v-else-if="isChunkedProcessing(selectedCapture)" class="info-item">
                  <label>Processing Method</label>
                  <span class="processing-method chunked">
                    <i class="fas fa-layer-group"></i>
                    Chunked Processing
                  </span>
                </div>
              </div>
            </div>

            <!-- Item Validation Section -->
            <div v-if="selectedCapture.item_validation" class="details-section">
              <h4>Item Validation Results</h4>
              
              <!-- Missing Items -->
              <div v-if="selectedCapture.item_validation.missing_items && selectedCapture.item_validation.missing_items.length > 0" 
                   class="validation-section missing-items">
                <h5 class="validation-title danger">
                  <i class="fas fa-times-circle"></i>
                  Missing Items ({{ selectedCapture.item_validation.missing_items.length }})
                </h5>
                <div class="missing-items-list">
                  <div v-for="(item, index) in selectedCapture.item_validation.missing_items" 
                       :key="index" 
                       class="missing-item-card">
                    <div class="item-header">
                      <span class="item-code">{{ item.item_code || 'No Code' }}</span>
                      <span class="item-name">{{ item.item_name || 'No Name' }}</span>
                      <span v-if="item.source_page" class="page-info">
                        <i class="fas fa-file-alt"></i>
                        Page {{ item.source_page }}
                      </span>
                    </div>
                    <div class="item-details">
                      <span v-if="item.description">{{ item.description }}</span>
                      <span v-if="item.quantity">Qty: {{ item.quantity }}</span>
                      <span v-if="item.unit_price">Price: ${{ item.unit_price }}</span>
                    </div>
                  </div>
                </div>
                <div class="validation-warning">
                  <i class="fas fa-exclamation-triangle"></i>
                  These items must be created in the system before a sales order can be generated.
                </div>
              </div>

              <!-- Existing Items -->
              <div v-if="selectedCapture.item_validation.existing_items && selectedCapture.item_validation.existing_items.length > 0" 
                   class="validation-section existing-items">
                <h5 class="validation-title success">
                  <i class="fas fa-check-circle"></i>
                  Found Items ({{ selectedCapture.item_validation.existing_items.length }})
                </h5>
                <div class="existing-items-list">
                  <div v-for="(item, index) in selectedCapture.item_validation.existing_items" 
                       :key="index" 
                       class="existing-item-card">
                    <div class="item-header">
                      <span class="item-code">{{ item.matched_item.item_code }}</span>
                      <span class="item-name">{{ item.matched_item.name }}</span>
                    </div>
                    <div class="item-match-info">
                      <span class="extracted-data">Extracted: {{ item.extracted_data.name }}</span>
                      <span class="match-indicator">✓ Matched</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Fuzzy Matches -->
              <div v-if="selectedCapture.item_validation.fuzzy_matches && selectedCapture.item_validation.fuzzy_matches.length > 0" 
                   class="validation-section fuzzy-matches">
                <h5 class="validation-title warning">
                  <i class="fas fa-question-circle"></i>
                  Fuzzy Matches ({{ selectedCapture.item_validation.fuzzy_matches.length }})
                </h5>
                <div class="fuzzy-matches-list">
                  <div v-for="(match, index) in selectedCapture.item_validation.fuzzy_matches" 
                       :key="index" 
                       class="fuzzy-match-card">
                    <div class="match-header">
                      <span class="extracted-name">{{ match.extracted_name }}</span>
                      <span class="similarity-score">{{ Math.round(match.similarity_score) }}% match</span>
                    </div>
                    <div class="matched-item">
                      <span class="matched-code">{{ match.matched_item.item_code }}</span>
                      <span class="matched-name">{{ match.matched_item.name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Processing Information -->
            <div v-if="selectedCapture.extracted_data && selectedCapture.extracted_data.processing_notes" class="details-section">
              <h4>Processing Information</h4>
              <div class="processing-details-full">
                <div class="processing-note">
                  <strong>Method:</strong> 
                  {{ isPageBasedProcessing(selectedCapture) ? 'Page-based Processing' : 
                      isChunkedProcessing(selectedCapture) ? 'Chunked Processing' : 'Standard Processing' }}
                </div>
                <div class="processing-note">
                  <strong>Details:</strong> {{ selectedCapture.extracted_data.processing_notes }}
                </div>
                <div v-if="selectedCapture.extracted_data.table_structure_notes" class="processing-note">
                  <strong>Table Structure:</strong> {{ selectedCapture.extracted_data.table_structure_notes }}
                </div>
                <div v-if="selectedCapture.extracted_data.number_format_notes" class="processing-note">
                  <strong>Number Format:</strong> {{ selectedCapture.extracted_data.number_format_notes }}
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
                      <span v-if="item.source_page" class="page-info">
                        <i class="fas fa-file-alt"></i>
                        Page {{ item.source_page }}
                      </span>
                      <span v-else-if="item.source_chunk" class="chunk-info">
                        <i class="fas fa-layer-group"></i>
                        Chunk {{ item.source_chunk }}
                      </span>
                    </div>
                    <div class="item-details">
                      <span v-if="item.unit_price">Price: ${{ item.unit_price }}</span>
                      <span v-if="item.uom">UOM: {{ item.uom }}</span>
                      <span v-if="item.description">{{ item.description }}</span>
                    </div>
                    <div v-if="item.validation_check" class="validation-info">
                      <small>Validation: {{ item.validation_check }}</small>
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
          <!-- Always show Close button -->
          <button @click="closeDetailsModal" class="btn btn-secondary">
            <i class="fas fa-times"></i>
            Close
          </button>
          
          <!-- Download PDF button -->
          <button 
            v-if="selectedCapture && selectedCapture.file_path"
            @click="downloadFile(selectedCapture.id)"
            class="btn btn-outline"
          >
            <i class="fas fa-download"></i>
            Download PDF
          </button>
          
          <!-- Create Sales Order button (PRIMARY ACTION) -->
          <button 
            v-if="selectedCapture && selectedCapture.status === 'data_extracted' && !selectedCapture.created_so_id && canCreateSalesOrder(selectedCapture)"
            @click="createSalesOrder(selectedCapture.id)"
            class="btn btn-success btn-lg"
            :disabled="createSoLoading[selectedCapture.id]"
          >
            <i class="fas fa-plus" :class="{ 'fa-spin': createSoLoading[selectedCapture.id] }"></i>
            {{ createSoLoading[selectedCapture.id] ? 'Creating Sales Order...' : 'Create Sales Order' }}
          </button>
          
          <!-- Warning message if cannot create SO -->
          <div 
            v-else-if="selectedCapture && selectedCapture.status === 'data_extracted' && !selectedCapture.created_so_id && !canCreateSalesOrder(selectedCapture)"
            class="cannot-create-warning"
          >
            <i class="fas fa-exclamation-triangle"></i>
            <span>Sales Order cannot be created: 
              <strong v-if="selectedCapture.item_validation?.missing_items?.length > 0">
                {{ selectedCapture.item_validation.missing_items.length }} items missing from database
              </strong>
              <strong v-else>Items validation required</strong>
            </span>
          </div>
          
          <!-- Already has Sales Order -->
          <div 
            v-else-if="selectedCapture && selectedCapture.created_so_id"
            class="has-so-info"
          >
            <i class="fas fa-check-circle text-success"></i>
            <span>Sales Order already created</span>
            <router-link 
              v-if="selectedCapture.sales_order"
              :to="`/sales/orders/${selectedCapture.sales_order.so_id}`"
              class="btn btn-primary btn-sm"
            >
              <i class="fas fa-external-link-alt"></i>
              View SO #{{ selectedCapture.sales_order.so_number }}
            </router-link>
          </div>
          
          <!-- Retry button for failed captures -->
          <button 
            v-if="selectedCapture && selectedCapture.status === 'failed'"
            @click="retryCapture(selectedCapture.id)"
            class="btn btn-warning"
            :disabled="retryLoading[selectedCapture.id]"
          >
            <i class="fas fa-redo" :class="{ 'fa-spin': retryLoading[selectedCapture.id] }"></i>
            Retry Processing
          </button>
          
          <!-- Reprocess with validation button -->
          <button 
            v-if="selectedCapture && ['data_extracted', 'failed'].includes(selectedCapture.status)"
            @click="reprocessWithValidation(selectedCapture.id)"
            class="btn btn-info"
            :disabled="retryLoading[selectedCapture.id]"
          >
            <i class="fas fa-file-alt" :class="{ 'fa-spin': retryLoading[selectedCapture.id] }"></i>
            Reprocess with Validation
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
      createSoLoading: {},
      
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
        preferred_currency: 'USD',
        processing_options: {
          confidence_threshold: 80,
          auto_approve: false,
          use_ocr: true
        }
      },
      
      // Chunking constants
      CHUNKING_THRESHOLD: 5 * 1024 * 1024, // 5MB
      LARGE_FILE_THRESHOLD: 2 * 1024 * 1024 // 2MB
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
    
    // FIXED: Upload and Extract (auto-open preview modal)
    async uploadAndExtract() {
      if (!this.selectedFile) return
      
      this.isUploading = true
      try {
        const formData = new FormData()
        formData.append('pdf_file', this.selectedFile)
        formData.append('preferred_currency', this.uploadOptions.preferred_currency)
        formData.append('processing_options', JSON.stringify(this.uploadOptions.processing_options))
        
        const response = await axios.post('/pdf-order-capture', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        this.$toast?.success('PDF uploaded and data extracted successfully')
        this.closeUploadModal()
        await this.loadData()
        
        // Get the extracted data directly from response (no need for additional API call)
        const captureData = response.data.data.pdf_capture
        const itemValidation = response.data.data.item_validation
        
        // Show appropriate message based on validation results
        if (itemValidation && itemValidation.missing_items && itemValidation.missing_items.length > 0) {
          this.$toast?.warning(`${itemValidation.missing_items.length} items not found in database. Please review the details.`)
        } else {
          this.$toast?.info('All items found! You can now create a sales order.')
        }
        
        // Set the capture data directly and show modal (no API call needed)
        this.selectedCapture = {
          ...captureData,
          item_validation: itemValidation,
          extracted_customer: captureData.extracted_data?.customer || null,
          extracted_items: captureData.extracted_data?.items || []
        }
        this.showDetailsModal = true
        
      } catch (error) {
        console.error('Upload failed:', error)
        this.$toast?.error(error.response?.data?.message || 'Upload failed')
      } finally {
        this.isUploading = false
      }
    },
    
    // FIXED: Create Sales Order (separate function)
    async createSalesOrder(captureId) {
      // Confirm action
      const capture = this.captures.find(c => c.id === captureId) || this.selectedCapture
      if (!capture) {
        this.$toast?.error('Capture not found')
        return
      }
      
      // Double-check if can create
      if (!this.canCreateSalesOrder(capture)) {
        let reason = 'Unknown reason'
        if (capture.status !== 'data_extracted') {
          reason = 'Data must be extracted first'
        } else if (capture.created_so_id) {
          reason = 'Sales order already exists'
        } else if (capture.item_validation?.missing_items?.length > 0) {
          reason = `${capture.item_validation.missing_items.length} items missing from database`
        }
        
        this.$toast?.error(`Cannot create sales order: ${reason}`)
        return
      }
      
      const confirmed = confirm(`Create sales order from this PDF?\n\nCustomer: ${capture.extracted_customer?.name || 'Unknown'}\nItems: ${capture.extracted_items?.length || 0}`)
      if (!confirmed) return
      
      this.$set(this.createSoLoading, captureId, true)
      try {
        const response = await axios.post(`/pdf-order-capture/${captureId}/create-sales-order`)
        
        this.$toast?.success('Sales order created successfully!')
        await this.loadData()
        
        // Navigate to sales order if created
        if (response.data.data.sales_order) {
          const soId = response.data.data.sales_order.so_id
          this.$toast?.info(`Redirecting to Sales Order #${response.data.data.sales_order.so_number}...`)
          
          // Close modal if open
          if (this.showDetailsModal) {
            this.closeDetailsModal()
          }
          
          // Redirect after short delay
          setTimeout(() => {
            this.$router.push(`/sales/orders/${soId}`)
          }, 1500)
        }
        
      } catch (error) {
        console.error('Create sales order failed:', error)
        const errorMessage = error.response?.data?.message || 'Failed to create sales order'
        this.$toast?.error(errorMessage)
        
        // Show specific error details if available
        if (error.response?.data?.data?.missing_items) {
          const missingItems = error.response.data.data.missing_items
          const itemNames = missingItems.map(i => i.item_code || i.item_name).slice(0, 3).join(', ')
          const extraCount = missingItems.length > 3 ? ` and ${missingItems.length - 3} more` : ''
          this.$toast?.warning(`Missing items: ${itemNames}${extraCount}`)
        }
      } finally {
        this.$set(this.createSoLoading, captureId, false)
      }
    },

    // FIXED: Check if sales order can be created
    canCreateSalesOrder(capture) {
      // Must be in data_extracted status
      if (capture.status !== 'data_extracted') return false
      
      // Must not already have a sales order created
      if (capture.created_so_id || capture.sales_order) return false
      
      // Check item validation - no missing items allowed
      if (!capture.item_validation) return false
      
      const missingItems = capture.item_validation.missing_items || []
      return missingItems.length === 0
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

    async reprocessWithValidation(captureId) {
      this.$set(this.retryLoading, captureId, true)
      try {
        const response = await axios.post(`/pdf-order-capture/${captureId}/reprocess-with-validation`)
        this.$toast?.success('Reprocessing completed with enhanced validation')
        await this.loadData()
        
        // Show success info about page-based processing if used
        if (response.data.data.page_chunking_used) {
          this.$toast?.info('Large file was processed page-by-page for better accuracy')
        }
        
        // Close modal and show new details
        if (this.showDetailsModal) {
          this.closeDetailsModal()
          setTimeout(() => {
            this.viewDetails(response.data.data.pdf_capture)
          }, 500)
        }
      } catch (error) {
        console.error('Reprocess failed:', error)
        this.$toast?.error(error.response?.data?.message || 'Reprocess failed')
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
    
    // FIXED: View Details method
    async viewDetails(capture) {
      try {
        // If capture is already a full object with data, use it directly
        if (capture && capture.extracted_data && capture.item_validation) {
          this.selectedCapture = {
            ...capture,
            extracted_customer: capture.extracted_data?.customer || null,
            extracted_items: capture.extracted_data?.items || []
          }
          this.showDetailsModal = true
          return
        }
        
        // Otherwise, fetch from API (for existing captures from table)
        const captureId = capture.id || capture
        const response = await axios.get(`/pdf-order-capture/${captureId}`)
        
        this.selectedCapture = {
          ...response.data.data,
          extracted_customer: response.data.data.extracted_data?.customer || null,
          extracted_items: response.data.data.extracted_data?.items || []
        }
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
    
    // Chunking-related helper functions
    isLargeFile(fileSize) {
      return fileSize > this.LARGE_FILE_THRESHOLD
    },
    
    willUseChunking(fileSize) {
      return fileSize > this.CHUNKING_THRESHOLD
    },
    
    isPageBasedProcessing(capture) {
      if (!capture.extracted_data) return false
      
      const processingNotes = capture.extracted_data.processing_notes || ''
      const tableNotes = capture.extracted_data.table_structure_notes || ''
      
      return processingNotes.includes('pages') || 
             tableNotes.includes('pages') ||
             (capture.extracted_data.items && 
              capture.extracted_data.items.some(item => item.source_page))
    },
    
    isChunkedProcessing(capture) {
      if (this.isPageBasedProcessing(capture)) return false // Page-based takes precedence
      
      if (!capture.extracted_data) return false
      
      const processingNotes = capture.extracted_data.processing_notes || ''
      const tableNotes = capture.extracted_data.table_structure_notes || ''
      
      return processingNotes.includes('chunks') || 
             tableNotes.includes('chunks') ||
             (capture.extracted_data.items && 
              capture.extracted_data.items.some(item => item.source_chunk))
    },
    
    getTotalPages(capture) {
      if (!capture.extracted_data || !capture.extracted_data.processing_notes) return '?'
      
      const pageMatch = capture.extracted_data.processing_notes.match(/(\d+)\s+pages?/)
      if (pageMatch) {
        return pageMatch[1]
      }
      
      // Count unique source pages from items
      if (capture.extracted_data.items) {
        const pages = new Set()
        capture.extracted_data.items.forEach(item => {
          if (item.source_page) {
            pages.add(item.source_page)
          }
        })
        return pages.size > 0 ? pages.size : '?'
      }
      
      return '?'
    },
    
    getProcessingNotesSummary(notes) {
      if (!notes) return ''
      
      // Extract key information from processing notes
      const pageMatch = notes.match(/(\d+)\s+pages?/)
      if (pageMatch) {
        return `${pageMatch[1]} pages`
      }
      
      const chunkMatch = notes.match(/(\d+)\s+chunks?/)
      if (chunkMatch) {
        return `${chunkMatch[1]} chunks`
      }
      
      return notes.length > 30 ? notes.substring(0, 27) + '...' : notes
    },
    
    getProcessingText() {
      if (!this.selectedFile) return 'Processing...'
      
      if (this.willUseChunking(this.selectedFile.size)) {
        return 'Extracting from pages...'
      }
      
      return 'Extracting data...'
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
        so_created: 'status-success',
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
        so_created: 'Completed',
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

/* Item Validation Styles */
.item-validation {
  min-width: 120px;
}

.validation-summary {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.validation-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
}

.validation-item.success { color: #059669; }
.validation-item.danger { color: #dc2626; }
.validation-item.warning { color: #d97706; }

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

.btn-icon.btn-success { border-color: #10b981; color: #10b981; }
.btn-icon.btn-warning { border-color: #f59e0b; color: #f59e0b; }
.btn-icon.btn-secondary { border-color: var(--gray-400); color: var(--gray-600); }
.btn-icon.btn-danger { border-color: #ef4444; color: #ef4444; }

.btn-icon.btn-success:hover { background: #d1fae5; }
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

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.btn-success:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
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

.btn-info {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
}

.btn-info:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

.btn-lg {
  padding: 0.875rem 2rem;
  font-size: 1rem;
  font-weight: 600;
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
  background: #ffffff
}

.modal-large {
  max-width: 900px;
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
  display: flex;
  align-items: center;
  gap: 0.5rem;
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
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
  flex-wrap: wrap;
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

/* NEW: Preview Section Styles */
.preview-status {
  margin-bottom: 1.5rem;
}

.status-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid;
}

.status-card.success {
  background: #ecfdf5; /* Solid green background instead of transparent */
  border-color: #10b981;
  color: #065f46;
}

.status-card.warning {
  background: #fffbeb; /* Solid yellow background instead of transparent */
  border-color: #f59e0b;
  color: #92400e;
}

.status-icon {
  font-size: 2rem;
}

.status-content h5 {
  margin: 0 0 0.25rem 0;
  font-weight: 600;
}

.status-content p {
  margin: 0;
  font-size: 0.9rem;
}

.preview-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  padding: 1rem;
  background: var(--bg-secondary);
  border-radius: 8px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-label {
  font-weight: 500;
  color: var(--text-secondary);
}

.stat-value {
  font-weight: 600;
  color: var(--text-primary);
}

.stat-value.success {
  color: #059669;
}

.stat-value.danger {
  color: #dc2626;
}

/* Also update the cannot-create-warning and has-so-info styles for consistency */
.cannot-create-warning {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #fffbeb; /* Solid yellow background */
  color: #92400e;
  padding: 0.75rem 1rem;
  border-radius: 6px;
  border: 1px solid #f59e0b;
  font-size: 0.9rem;
  flex: 1;
  min-width: 300px;
}

.has-so-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: #ecfdf5; /* Solid green background */
  color: #065f46;
  padding: 0.75rem 1rem;
  border-radius: 6px;
  border: 1px solid #10b981;
  font-size: 0.9rem;
  flex: 1;
  min-width: 300px;
}

.text-success {
  color: #059669;
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

/* Validation Section Styles */
.validation-section {
  margin-bottom: 1.5rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  overflow: hidden;
}

.validation-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.validation-title.success {
  background: #d1fae5;
  color: #065f46;
  border-bottom: 1px solid #10b981;
}

.validation-title.danger {
  background: #fee2e2;
  color: #991b1b;
  border-bottom: 1px solid #ef4444;
}

.validation-title.warning {
  background: #fef3c7;
  color: #92400e;
  border-bottom: 1px solid #f59e0b;
}

.missing-items-list, .existing-items-list, .fuzzy-matches-list {
  padding: 1rem;
}

.missing-item-card, .existing-item-card, .fuzzy-match-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 1rem;
  margin-bottom: 0.5rem;
}

.missing-item-card:last-child, .existing-item-card:last-child, .fuzzy-match-card:last-child {
  margin-bottom: 0;
}

.item-header, .match-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.item-code {
  font-weight: 600;
  color: var(--text-primary);
  background: var(--card-bg);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8rem;
}

.item-name {
  font-weight: 500;
  color: var(--text-primary);
}

.page-info, .chunk-info {
  font-size: 0.8rem;
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

.item-match-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
}

.extracted-data {
  color: var(--text-muted);
}

.match-indicator {
  color: #059669;
  font-weight: 500;
}

.similarity-score {
  background: #fef3c7;
  color: #92400e;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 500;
}

.matched-item {
  display: flex;
  gap: 1rem;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.matched-code {
  font-weight: 600;
  color: var(--text-primary);
}

.matched-name {
  color: var(--text-primary);
}

.validation-warning {
  background: #fffbeb; /* Solid yellow background */
  border: 1px solid #f59e0b;
  border-radius: 6px;
  padding: 1rem;
  margin-top: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #92400e;
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

.item-qty {
  font-size: 0.9rem;
  color: var(--text-muted);
  background: var(--card-bg);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.validation-info {
  margin-top: 0.5rem;
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

/* Processing Methods */
.processing-method {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 500;
}

.processing-method.page-based { color: #3b82f6; }
.processing-method.chunked { color: #f59e0b; }
.processing-method.single { color: var(--text-muted); }

.processing-notes {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 0.25rem;
}

.processing-details-full {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.processing-note {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.chunking-options {
  background: #f0f9ff;
  border: 1px solid #3b82f6;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.chunking-options h5 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 0.5rem 0;
  color: #1e40af;
}

.chunking-info p {
  margin: 0;
  color: #1e40af;
  font-size: 0.9rem;
}

.processing-estimate {
  background: #f0f9ff;
  border: 1px solid #3b82f6;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.estimate-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #1e40af;
  font-size: 0.9rem;
}

.chunking-indicator {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.7rem;
  color: #3b82f6;
  margin-left: 0.5rem;
}

.file-size-indicator {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.7rem;
  margin-left: 0.5rem;
}

.file-size-indicator.large-file {
  color: #f59e0b;
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
  
  .modal-footer {
    flex-direction: column;
    align-items: stretch;
  }
  
  .cannot-create-warning,
  .has-so-info {
    min-width: auto;
  }
}
</style>