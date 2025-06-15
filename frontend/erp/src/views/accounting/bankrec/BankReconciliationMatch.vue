<template>
  <div class="bank-reconciliation-match">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <button @click="goBack" class="btn-back">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="page-title">
              <i class="fas fa-link"></i>
              Match Transactions
            </h1>
            <p class="page-subtitle">
              Reconciliation #{{ reconciliation.reconciliation_id }} - {{ reconciliation.bank_account?.account_name }}
            </p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="autoMatch" class="btn-secondary" :disabled="processing">
            <i class="fas fa-magic" :class="{ 'fa-spin': processing }"></i>
            Auto Match
          </button>
          <button @click="saveProgress" class="btn-outline" :disabled="saving">
            <i class="fas fa-save" :class="{ 'fa-spin': saving }"></i>
            Save Progress
          </button>
          <button @click="completeMatching" class="btn-primary" :disabled="!canComplete">
            <i class="fas fa-check"></i>
            Complete Matching
          </button>
        </div>
      </div>
    </div>

    <!-- Progress and Summary -->
    <div class="progress-summary">
      <div class="summary-cards">
        <div class="summary-card">
          <div class="card-icon matched">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="card-content">
            <h3>{{ matchedCount }}</h3>
            <p>Matched Transactions</p>
          </div>
        </div>
        <div class="summary-card">
          <div class="card-icon pending">
            <i class="fas fa-clock"></i>
          </div>
          <div class="card-content">
            <h3>{{ unmatchedCount }}</h3>
            <p>Pending Matches</p>
          </div>
        </div>
        <div class="summary-card">
          <div class="card-icon variance">
            <i class="fas fa-calculator"></i>
          </div>
          <div class="card-content">
            <h3>{{ formatCurrency(remainingVariance) }}</h3>
            <p>Remaining Variance</p>
          </div>
        </div>
        <div class="summary-card progress">
          <div class="card-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="card-content">
            <div class="progress-ring">
              <svg class="progress-circle" width="60" height="60">
                <circle cx="30" cy="30" r="25" stroke="var(--gray-200)" stroke-width="4" fill="none"/>
                <circle 
                  cx="30" cy="30" r="25" 
                  stroke="var(--primary-color)" 
                  stroke-width="4" 
                  fill="none"
                  :stroke-dasharray="progressCircumference"
                  :stroke-dashoffset="progressOffset"
                  class="progress-arc"
                />
              </svg>
              <div class="progress-text">{{ matchingProgress }}%</div>
            </div>
            <p>Completion</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Matching Interface -->
    <div class="matching-interface">
      <div class="matching-grid">
        <!-- Bank Statement Transactions -->
        <div class="statement-panel">
          <div class="panel-header">
            <h3>
              <i class="fas fa-university"></i>
              Bank Statement Transactions
            </h3>
            <div class="panel-controls">
              <div class="search-box">
                <i class="fas fa-search"></i>
                <input 
                  v-model="statementSearch" 
                  type="text" 
                  placeholder="Search statement transactions..."
                  class="search-input"
                />
              </div>
              <select v-model="statementFilter" class="filter-select">
                <option value="all">All Transactions</option>
                <option value="unmatched">Unmatched Only</option>
                <option value="matched">Matched Only</option>
              </select>
            </div>
          </div>
          <div class="panel-body">
            <div class="transactions-list statement-transactions">
              <div 
                v-for="transaction in filteredStatementTransactions" 
                :key="'stmt-' + transaction.id"
                class="transaction-item"
                :class="{ 
                  'matched': transaction.matched, 
                  'selected': selectedStatement?.id === transaction.id,
                  'dragging': draggedTransaction?.id === transaction.id 
                }"
                @click="selectStatementTransaction(transaction)"
                draggable="true"
                @dragstart="onDragStart(transaction, 'statement')"
                @dragend="onDragEnd"
              >
                <div class="transaction-header">
                  <div class="transaction-type">
                    <i class="fas" :class="getTransactionIcon(transaction.type)"></i>
                    <span class="type-label">{{ transaction.type }}</span>
                  </div>
                  <div class="transaction-amount" :class="getAmountClass(transaction.amount)">
                    {{ formatCurrency(transaction.amount) }}
                  </div>
                </div>
                <div class="transaction-details">
                  <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{{ formatDate(transaction.date) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Reference:</span>
                    <span class="detail-value">{{ transaction.reference || 'N/A' }}</span>
                  </div>
                  <div v-if="transaction.description" class="transaction-description">
                    {{ transaction.description }}
                  </div>
                </div>
                <div v-if="transaction.matched" class="match-indicator">
                  <i class="fas fa-link"></i>
                  <span>Matched with Book Entry #{{ transaction.matchedWith }}</span>
                </div>
              </div>
            </div>
            
            <div v-if="filteredStatementTransactions.length === 0" class="empty-state">
              <i class="fas fa-file-invoice"></i>
              <p>No statement transactions found</p>
            </div>
          </div>
        </div>

        <!-- Matching Controls -->
        <div class="matching-controls">
          <div class="match-actions">
            <button 
              @click="matchSelected" 
              class="btn-match"
              :disabled="!canMatchSelected"
            >
              <i class="fas fa-arrows-alt-h"></i>
              Match Selected
            </button>
            <button 
              @click="unmatchSelected" 
              class="btn-unmatch"
              :disabled="!selectedStatement?.matched && !selectedBook?.matched"
            >
              <i class="fas fa-unlink"></i>
              Unmatch
            </button>
            <button 
              @click="createAdjustment" 
              class="btn-adjustment"
            >
              <i class="fas fa-plus"></i>
              Create Adjustment
            </button>
          </div>
          
          <div class="tolerance-settings">
            <h4><i class="fas fa-cog"></i> Matching Settings</h4>
            <div class="setting-group">
              <label>Date Tolerance (days):</label>
              <input 
                v-model.number="dateTolerance" 
                type="number" 
                min="0" 
                max="30" 
                class="tolerance-input"
              />
            </div>
            <div class="setting-group">
              <label>Amount Tolerance:</label>
              <input 
                v-model.number="amountTolerance" 
                type="number" 
                step="0.01" 
                min="0" 
                class="tolerance-input"
              />
            </div>
            <div class="setting-group">
              <label class="checkbox-label">
                <input 
                  v-model="fuzzyMatching" 
                  type="checkbox" 
                  class="tolerance-checkbox"
                />
                Enable fuzzy description matching
              </label>
            </div>
          </div>

          <div class="match-suggestions">
            <h4><i class="fas fa-lightbulb"></i> Suggested Matches</h4>
            <div v-if="suggestions.length === 0" class="no-suggestions">
              <p>No automatic suggestions available</p>
              <small>Select transactions to see potential matches</small>
            </div>
            <div v-else class="suggestions-list">
              <div 
                v-for="suggestion in suggestions" 
                :key="suggestion.id"
                class="suggestion-item"
                @click="applySuggestion(suggestion)"
              >
                <div class="suggestion-score">
                  <div class="score-badge" :class="getScoreClass(suggestion.score)">
                    {{ suggestion.score }}%
                  </div>
                </div>
                <div class="suggestion-details">
                  <div class="suggestion-match">
                    Statement #{{ suggestion.statementId }} ↔ Book #{{ suggestion.bookId }}
                  </div>
                  <div class="suggestion-reason">{{ suggestion.reason }}</div>
                </div>
                <button class="btn-apply-suggestion">
                  <i class="fas fa-check"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Book Transactions -->
        <div class="book-panel">
          <div class="panel-header">
            <h3>
              <i class="fas fa-book"></i>
              Book Transactions
            </h3>
            <div class="panel-controls">
              <div class="search-box">
                <i class="fas fa-search"></i>
                <input 
                  v-model="bookSearch" 
                  type="text" 
                  placeholder="Search book transactions..."
                  class="search-input"
                />
              </div>
              <select v-model="bookFilter" class="filter-select">
                <option value="all">All Transactions</option>
                <option value="unmatched">Unmatched Only</option>
                <option value="matched">Matched Only</option>
              </select>
            </div>
          </div>
          <div class="panel-body">
            <div 
              class="transactions-list book-transactions"
              @drop="onDrop"
              @dragover.prevent
              @dragenter.prevent
            >
              <div 
                v-for="transaction in filteredBookTransactions" 
                :key="'book-' + transaction.id"
                class="transaction-item"
                :class="{ 
                  'matched': transaction.matched, 
                  'selected': selectedBook?.id === transaction.id,
                  'drop-target': isDragOver 
                }"
                @click="selectBookTransaction(transaction)"
                draggable="true"
                @dragstart="onDragStart(transaction, 'book')"
                @dragend="onDragEnd"
              >
                <div class="transaction-header">
                  <div class="transaction-type">
                    <i class="fas" :class="getTransactionIcon(transaction.type)"></i>
                    <span class="type-label">{{ transaction.type }}</span>
                  </div>
                  <div class="transaction-amount" :class="getAmountClass(transaction.amount)">
                    {{ formatCurrency(transaction.amount) }}
                  </div>
                </div>
                <div class="transaction-details">
                  <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{{ formatDate(transaction.date) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Account:</span>
                    <span class="detail-value">{{ transaction.account || 'N/A' }}</span>
                  </div>
                  <div v-if="transaction.description" class="transaction-description">
                    {{ transaction.description }}
                  </div>
                </div>
                <div v-if="transaction.matched" class="match-indicator">
                  <i class="fas fa-link"></i>
                  <span>Matched with Statement #{{ transaction.matchedWith }}</span>
                </div>
              </div>
            </div>
            
            <div v-if="filteredBookTransactions.length === 0" class="empty-state">
              <i class="fas fa-book"></i>
              <p>No book transactions found</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Adjustment Modal -->
    <div v-if="showAdjustmentModal" class="modal-overlay" @click="closeAdjustmentModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3><i class="fas fa-plus"></i> Create Reconciliation Adjustment</h3>
          <button @click="closeAdjustmentModal" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="saveAdjustment" class="adjustment-form">
            <div class="form-group">
              <label class="form-label required">Transaction Type</label>
              <select v-model="adjustmentForm.type" class="form-input" required>
                <option value="">Select type</option>
                <option value="Bank Fee">Bank Fee</option>
                <option value="Interest">Interest</option>
                <option value="NSF Fee">NSF Fee</option>
                <option value="Transfer">Transfer</option>
                <option value="Adjustment">Adjustment</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label required">Amount</label>
              <input 
                v-model.number="adjustmentForm.amount" 
                type="number" 
                step="0.01" 
                class="form-input" 
                placeholder="0.00"
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label required">Date</label>
              <input 
                v-model="adjustmentForm.date" 
                type="date" 
                class="form-input" 
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea 
                v-model="adjustmentForm.description" 
                class="form-textarea" 
                rows="3"
                placeholder="Enter adjustment description..."
              ></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button @click="closeAdjustmentModal" class="btn-secondary">Cancel</button>
          <button @click="saveAdjustment" class="btn-primary" :disabled="!isAdjustmentValid">
            <i class="fas fa-save"></i>
            Create Adjustment
          </button>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>{{ loadingMessage }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BankReconciliationMatch',
  data() {
    return {
      loading: false,
      processing: false,
      saving: false,
      loadingMessage: '',
      reconciliation: {
        bank_account: {}
      },
      statementTransactions: [],
      bookTransactions: [],
      selectedStatement: null,
      selectedBook: null,
      draggedTransaction: null,
      isDragOver: false,
      statementSearch: '',
      bookSearch: '',
      statementFilter: 'unmatched',
      bookFilter: 'unmatched',
      dateTolerance: 3,
      amountTolerance: 0.01,
      fuzzyMatching: true,
      suggestions: [],
      showAdjustmentModal: false,
      adjustmentForm: {
        type: '',
        amount: null,
        date: '',
        description: ''
      }
    }
  },
  computed: {
    matchedCount() {
      return this.statementTransactions.filter(t => t.matched).length
    },
    unmatchedCount() {
      return this.statementTransactions.filter(t => !t.matched).length + 
             this.bookTransactions.filter(t => !t.matched).length
    },
    remainingVariance() {
      const unmatchedStatementTotal = this.statementTransactions
        .filter(t => !t.matched)
        .reduce((sum, t) => sum + t.amount, 0)
      const unmatchedBookTotal = this.bookTransactions
        .filter(t => !t.matched)
        .reduce((sum, t) => sum + t.amount, 0)
      return unmatchedStatementTotal - unmatchedBookTotal
    },
    matchingProgress() {
      const total = this.statementTransactions.length
      if (total === 0) return 100
      return Math.round((this.matchedCount / total) * 100)
    },
    progressCircumference() {
      return 2 * Math.PI * 25 // radius = 25
    },
    progressOffset() {
      const progress = this.matchingProgress / 100
      return this.progressCircumference * (1 - progress)
    },
    filteredStatementTransactions() {
      let transactions = this.statementTransactions

      // Apply search filter
      if (this.statementSearch) {
        const search = this.statementSearch.toLowerCase()
        transactions = transactions.filter(t => 
          t.description?.toLowerCase().includes(search) ||
          t.reference?.toLowerCase().includes(search) ||
          t.type.toLowerCase().includes(search)
        )
      }

      // Apply status filter
      if (this.statementFilter === 'matched') {
        transactions = transactions.filter(t => t.matched)
      } else if (this.statementFilter === 'unmatched') {
        transactions = transactions.filter(t => !t.matched)
      }

      return transactions
    },
    filteredBookTransactions() {
      let transactions = this.bookTransactions

      // Apply search filter
      if (this.bookSearch) {
        const search = this.bookSearch.toLowerCase()
        transactions = transactions.filter(t => 
          t.description?.toLowerCase().includes(search) ||
          t.account?.toLowerCase().includes(search) ||
          t.type.toLowerCase().includes(search)
        )
      }

      // Apply status filter
      if (this.bookFilter === 'matched') {
        transactions = transactions.filter(t => t.matched)
      } else if (this.bookFilter === 'unmatched') {
        transactions = transactions.filter(t => !t.matched)
      }

      return transactions
    },
    canMatchSelected() {
      return this.selectedStatement && this.selectedBook && 
             !this.selectedStatement.matched && !this.selectedBook.matched
    },
    canComplete() {
      return this.unmatchedCount === 0 && Math.abs(this.remainingVariance) < 0.01
    },
    isAdjustmentValid() {
      return this.adjustmentForm.type && 
             this.adjustmentForm.amount !== null && 
             this.adjustmentForm.date
    }
  },
  async mounted() {
    await this.initializeData()
  },
  methods: {
    async initializeData() {
      this.loading = true
      this.loadingMessage = 'Loading reconciliation data...'
      
      try {
        const reconciliationId = this.$route.params.id
        
        // Load reconciliation details
        const reconciliationResponse = await axios.get(`/accounting/bank-reconciliations/${reconciliationId}`)
        this.reconciliation = reconciliationResponse.data.data
        
        // Load transactions
        await this.loadTransactions()
        await this.generateSuggestions()
        
        // Set default adjustment date
        this.adjustmentForm.date = new Date().toISOString().split('T')[0]
      } catch (error) {
        console.error('Error initializing data:', error)
        this.$toast?.error('Failed to load reconciliation data')
        this.goBack()
      } finally {
        this.loading = false
      }
    },

    async loadTransactions() {
      try {
        // Load statement transactions (mock data for demo)
        this.statementTransactions = [
          {
            id: 1,
            type: 'Deposit',
            amount: 1500.00,
            date: '2025-01-15',
            reference: 'DEP001',
            description: 'Customer payment - Invoice #1234',
            matched: false,
            matchedWith: null
          },
          {
            id: 2,
            type: 'Withdrawal',
            amount: -250.00,
            date: '2025-01-14',
            reference: 'CHK001',
            description: 'Check #1001 - Office supplies',
            matched: false,
            matchedWith: null
          },
          {
            id: 3,
            type: 'Fee',
            amount: -15.00,
            date: '2025-01-13',
            reference: 'FEE001',
            description: 'Monthly service fee',
            matched: false,
            matchedWith: null
          }
        ]

        // Load book transactions (mock data for demo)
        this.bookTransactions = [
          {
            id: 101,
            type: 'Deposit',
            amount: 1500.00,
            date: '2025-01-15',
            account: 'Accounts Receivable',
            description: 'Customer payment received',
            matched: false,
            matchedWith: null
          },
          {
            id: 102,
            type: 'Withdrawal',
            amount: -250.00,
            date: '2025-01-14',
            account: 'Office Expenses',
            description: 'Office supplies purchase',
            matched: false,
            matchedWith: null
          }
        ]
      } catch (error) {
        console.error('Error loading transactions:', error)
        this.$toast?.error('Failed to load transactions')
      }
    },

    async generateSuggestions() {
      this.suggestions = []
      
      // Simple matching algorithm for demo
      const unmatchedStatement = this.statementTransactions.filter(t => !t.matched)
      const unmatchedBook = this.bookTransactions.filter(t => !t.matched)
      
      for (const stmt of unmatchedStatement) {
        for (const book of unmatchedBook) {
          const score = this.calculateMatchScore(stmt, book)
          if (score > 50) {
            this.suggestions.push({
              id: `${stmt.id}-${book.id}`,
              statementId: stmt.id,
              bookId: book.id,
              score: score,
              reason: this.getMatchReason(stmt, book, score)
            })
          }
        }
      }
      
      // Sort by score descending
      this.suggestions.sort((a, b) => b.score - a.score)
    },

    calculateMatchScore(stmt, book) {
      let score = 0
      
      // Exact amount match
      if (Math.abs(stmt.amount - book.amount) <= this.amountTolerance) {
        score += 50
      }
      
      // Date proximity
      const stmtDate = new Date(stmt.date)
      const bookDate = new Date(book.date)
      const daysDiff = Math.abs((stmtDate - bookDate) / (1000 * 60 * 60 * 24))
      
      if (daysDiff <= this.dateTolerance) {
        score += Math.max(0, 30 - (daysDiff * 5))
      }
      
      // Description similarity (simplified)
      if (this.fuzzyMatching && stmt.description && book.description) {
        const similarity = this.getStringSimilarity(stmt.description, book.description)
        score += similarity * 20
      }
      
      return Math.round(score)
    },

    getStringSimilarity(str1, str2) {
      const words1 = str1.toLowerCase().split(' ')
      const words2 = str2.toLowerCase().split(' ')
      const commonWords = words1.filter(word => words2.includes(word))
      return commonWords.length / Math.max(words1.length, words2.length)
    },

    getMatchReason(stmt, book, score) {
      const reasons = []
      
      if (Math.abs(stmt.amount - book.amount) <= this.amountTolerance) {
        reasons.push('Exact amount match')
      }
      
      const daysDiff = Math.abs((new Date(stmt.date) - new Date(book.date)) / (1000 * 60 * 60 * 24))
      if (daysDiff <= this.dateTolerance) {
        reasons.push(`Date within ${daysDiff} day(s)`)
      }
      
      if (reasons.length === 0) {
        reasons.push('Partial match based on criteria')
      }
      
      return reasons.join(', ')
    },

    selectStatementTransaction(transaction) {
      this.selectedStatement = this.selectedStatement?.id === transaction.id ? null : transaction
      this.updateSuggestionsForSelection()
    },

    selectBookTransaction(transaction) {
      this.selectedBook = this.selectedBook?.id === transaction.id ? null : transaction
      this.updateSuggestionsForSelection()
    },

    updateSuggestionsForSelection() {
      if (this.selectedStatement && !this.selectedStatement.matched) {
        const unmatchedBook = this.bookTransactions.filter(t => !t.matched)
        this.suggestions = unmatchedBook.map(book => {
          const score = this.calculateMatchScore(this.selectedStatement, book)
          return {
            id: `${this.selectedStatement.id}-${book.id}`,
            statementId: this.selectedStatement.id,
            bookId: book.id,
            score: score,
            reason: this.getMatchReason(this.selectedStatement, book, score)
          }
        }).filter(s => s.score > 30).sort((a, b) => b.score - a.score)
      } else if (this.selectedBook && !this.selectedBook.matched) {
        const unmatchedStatement = this.statementTransactions.filter(t => !t.matched)
        this.suggestions = unmatchedStatement.map(stmt => {
          const score = this.calculateMatchScore(stmt, this.selectedBook)
          return {
            id: `${stmt.id}-${this.selectedBook.id}`,
            statementId: stmt.id,
            bookId: this.selectedBook.id,
            score: score,
            reason: this.getMatchReason(stmt, this.selectedBook, score)
          }
        }).filter(s => s.score > 30).sort((a, b) => b.score - a.score)
      } else {
        this.generateSuggestions()
      }
    },

    onDragStart(transaction, type) {
      this.draggedTransaction = { ...transaction, sourceType: type }
    },

    onDragEnd() {
      this.draggedTransaction = null
      this.isDragOver = false
    },

    onDrop(event) {
      event.preventDefault()
      this.isDragOver = false
      
      if (this.draggedTransaction) {
        const targetTransaction = event.target.closest('.transaction-item')
        if (targetTransaction) {
          // Get the target transaction data
          // This would need to be implemented based on how you store transaction data
          // For demo purposes, we'll just show the concept
          console.log('Drop detected', this.draggedTransaction, targetTransaction)
        }
      }
    },

    async matchSelected() {
      if (!this.canMatchSelected) return
      
      try {
        // Create the match
        const matchData = {
          statement_transaction_id: this.selectedStatement.id,
          book_transaction_id: this.selectedBook.id,
          reconciliation_id: this.reconciliation.reconciliation_id
        }
        
        // API call would go here
        await axios.post(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/matches`, matchData)
        
        // Update local state
        this.selectedStatement.matched = true
        this.selectedStatement.matchedWith = this.selectedBook.id
        this.selectedBook.matched = true
        this.selectedBook.matchedWith = this.selectedStatement.id
        
        // Clear selections
        this.selectedStatement = null
        this.selectedBook = null
        
        // Update suggestions
        await this.generateSuggestions()
        
        this.$toast?.success('Transactions matched successfully')
      } catch (error) {
        console.error('Error matching transactions:', error)
        this.$toast?.error('Failed to match transactions')
      }
    },

    async unmatchSelected() {
      const transaction = this.selectedStatement || this.selectedBook
      if (!transaction || !transaction.matched) return
      
      try {
        // API call would go here
        await axios.delete(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/matches/${transaction.matchId}`)
        
        // Find and unmatch both transactions
        const stmtTransaction = this.statementTransactions.find(t => t.id === transaction.id || t.matchedWith === transaction.id)
        const bookTransaction = this.bookTransactions.find(t => t.id === transaction.id || t.matchedWith === transaction.id)
        
        if (stmtTransaction) {
          stmtTransaction.matched = false
          stmtTransaction.matchedWith = null
        }
        
        if (bookTransaction) {
          bookTransaction.matched = false
          bookTransaction.matchedWith = null
        }
        
        // Clear selections
        this.selectedStatement = null
        this.selectedBook = null
        
        // Update suggestions
        await this.generateSuggestions()
        
        this.$toast?.success('Transactions unmatched successfully')
      } catch (error) {
        console.error('Error unmatching transactions:', error)
        this.$toast?.error('Failed to unmatch transactions')
      }
    },

    async applySuggestion(suggestion) {
      const stmtTransaction = this.statementTransactions.find(t => t.id === suggestion.statementId)
      const bookTransaction = this.bookTransactions.find(t => t.id === suggestion.bookId)
      
      if (stmtTransaction && bookTransaction) {
        this.selectedStatement = stmtTransaction
        this.selectedBook = bookTransaction
        await this.matchSelected()
      }
    },

    async autoMatch() {
      this.processing = true
      
      try {
        // Auto-match high-confidence suggestions
        const highConfidenceMatches = this.suggestions.filter(s => s.score >= 90)
        
        for (const match of highConfidenceMatches) {
          await this.applySuggestion(match)
        }
        
        this.$toast?.success(`Auto-matched ${highConfidenceMatches.length} transactions`)
      } catch (error) {
        console.error('Error in auto-match:', error)
        this.$toast?.error('Auto-match failed')
      } finally {
        this.processing = false
      }
    },

    createAdjustment() {
      this.showAdjustmentModal = true
    },

    closeAdjustmentModal() {
      this.showAdjustmentModal = false
      this.adjustmentForm = {
        type: '',
        amount: null,
        date: new Date().toISOString().split('T')[0],
        description: ''
      }
    },

    async saveAdjustment() {
      if (!this.isAdjustmentValid) return
      
      try {
        // API call would go here
        await axios.post(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/adjustments`, this.adjustmentForm)

        // Add adjustment to transactions list
        const newTransaction = {
          id: Date.now(),
          type: this.adjustmentForm.type,
          amount: this.adjustmentForm.amount,
          date: this.adjustmentForm.date,
          description: this.adjustmentForm.description,
          matched: false,
          matchedWith: null,
          isAdjustment: true
        }
        
        // Add to appropriate list based on amount
        if (this.adjustmentForm.amount > 0) {
          this.bookTransactions.push(newTransaction)
        } else {
          this.statementTransactions.push(newTransaction)
        }
        
        this.closeAdjustmentModal()
        this.$toast?.success('Adjustment created successfully')
      } catch (error) {
        console.error('Error creating adjustment:', error)
        this.$toast?.error('Failed to create adjustment')
      }
    },

    async saveProgress() {
      this.saving = true
      
      try {
        // Save current matching state
        // API call would go here
        
        this.$toast?.success('Progress saved successfully')
      } catch (error) {
        console.error('Error saving progress:', error)
        this.$toast?.error('Failed to save progress')
      } finally {
        this.saving = false
      }
    },

    async completeMatching() {
      if (!this.canComplete) return
      
      try {
        // Update reconciliation status
        await axios.put(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}`, {
          status: 'In Progress'
        })
        
        this.$toast?.success('Matching completed successfully')
        this.$router.push(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}`)
      } catch (error) {
        console.error('Error completing matching:', error)
        this.$toast?.error('Failed to complete matching')
      }
    },

    goBack() {
      this.$router.go(-1)
    },

    formatDate(date) {
      if (!date) return 'N/A'
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },

    formatCurrency(amount) {
      if (amount === null || amount === undefined || isNaN(amount)) return '$0.00'
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    },

    getTransactionIcon(type) {
      const iconMap = {
        'Deposit': 'fa-arrow-down',
        'Withdrawal': 'fa-arrow-up',
        'Transfer': 'fa-exchange-alt',
        'Fee': 'fa-minus-circle',
        'Interest': 'fa-percentage',
        'Check': 'fa-money-check'
      }
      return iconMap[type] || 'fa-file-invoice-dollar'
    },

    getAmountClass(amount) {
      if (amount === 0) return 'amount-zero'
      return amount > 0 ? 'amount-positive' : 'amount-negative'
    },

    getScoreClass(score) {
      if (score >= 90) return 'score-high'
      if (score >= 70) return 'score-medium'
      return 'score-low'
    }
  }
}
</script>

<style scoped>
/* CSS Variables */
:root {
  --primary-color: #2563eb;
  --primary-dark: #1d4ed8;
  --success-color: #059669;
  --warning-color: #d97706;
  --danger-color: #dc2626;
  --gray-50: #f8fafc;
  --gray-100: #f1f5f9;
  --gray-200: #e2e8f0;
  --gray-300: #cbd5e1;
  --gray-400: #94a3b8;
  --gray-500: #64748b;
  --gray-600: #475569;
  --gray-700: #334155;
  --gray-800: #1e293b;
  --gray-900: #0f172a;
  --white: #ffffff;
  --border-radius: 12px;
  --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  --box-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.1);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.bank-reconciliation-match {
  min-height: 100vh;
  background: var(--gray-50);
  padding: 2rem;
}

/* Page Header */
.page-header {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
  position: sticky;
  top: 2rem;
  z-index: 100;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.title-section {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.btn-back {
  width: 3rem;
  height: 3rem;
  border: 2px solid var(--gray-200);
  background: var(--white);
  color: var(--gray-600);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: var(--transition);
  font-size: 1rem;
}

.btn-back:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
  color: var(--gray-800);
  transform: translateX(-2px);
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: var(--primary-color);
}

.page-subtitle {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Buttons */
.btn-primary, .btn-secondary, .btn-outline {
  padding: 0.75rem 1.5rem;
  border-radius: var(--border-radius);
  font-weight: 600;
  font-size: 0.875rem;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn-primary {
  background: var(--primary-color);
  color: var(--white);
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.btn-primary:disabled {
  background: var(--gray-300);
  color: var(--gray-500);
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-secondary {
  background: var(--gray-200);
  color: var(--gray-700);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--gray-300);
}

.btn-outline {
  background: transparent;
  color: var(--gray-700);
  border: 2px solid var(--gray-300);
}

.btn-outline:hover:not(:disabled) {
  background: var(--gray-100);
  border-color: var(--gray-400);
}

/* Progress Summary */
.progress-summary {
  margin-bottom: 2rem;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
}

.summary-card {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 1.5rem;
  box-shadow: var(--box-shadow);
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: var(--transition);
}

.summary-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--box-shadow-lg);
}

.card-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: var(--white);
  flex-shrink: 0;
}

.card-icon.matched {
  background: var(--success-color);
}

.card-icon.pending {
  background: var(--warning-color);
}

.card-icon.variance {
  background: var(--danger-color);
}

.card-content h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0;
}

.card-content p {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin: 0;
}

.progress {
  background: var(--primary-color);
}

.progress-ring {
  position: relative;
  width: 60px;
  height: 60px;
}

.progress-circle {
  transform: rotate(-90deg);
}

.progress-arc {
  transition: stroke-dashoffset 0.5s ease;
}

.progress-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--gray-900);
}

/* Matching Interface */
.matching-interface {
  margin-bottom: 2rem;
}

.matching-grid {
  display: grid;
  grid-template-columns: 1fr 300px 1fr;
  gap: 1.5rem;
  min-height: 600px;
}

.statement-panel, .book-panel, .matching-controls {
  background: var(--white);
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  display: flex;
  flex-direction: column;
}

.matching-controls {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
  background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
}

.panel-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  background: var(--gray-50);
}

.panel-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.panel-header h3 i {
  color: var(--primary-color);
}

.panel-controls {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.search-box {
  position: relative;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  transition: var(--transition);
}

.search-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-select {
  padding: 0.75rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  background: var(--white);
}

.panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.transaction-item {
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  padding: 1rem;
  cursor: pointer;
  transition: var(--transition);
  background: var(--white);
}

.transaction-item:hover {
  border-color: var(--gray-300);
  background: var(--gray-50);
}

.transaction-item.selected {
  border-color: var(--primary-color);
  background: rgba(37, 99, 235, 0.05);
}

.transaction-item.matched {
  border-color: var(--success-color);
  background: rgba(5, 150, 105, 0.05);
}

.transaction-item.dragging {
  opacity: 0.5;
  transform: rotate(2deg);
}

.transaction-item.drop-target {
  border-color: var(--primary-color);
  background: rgba(37, 99, 235, 0.1);
  border-style: dashed;
}

.transaction-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.transaction-type {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.transaction-type i {
  color: var(--primary-color);
}

.type-label {
  font-weight: 600;
  color: var(--gray-900);
}

.transaction-amount {
  font-weight: 700;
  font-size: 1rem;
}

.amount-positive {
  color: var(--success-color);
}

.amount-negative {
  color: var(--danger-color);
}

.amount-zero {
  color: var(--gray-600);
}

.transaction-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.875rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
}

.detail-label {
  color: var(--gray-600);
  font-weight: 500;
}

.detail-value {
  color: var(--gray-900);
  font-weight: 600;
}

.transaction-description {
  color: var(--gray-700);
  font-style: italic;
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid var(--gray-100);
}

.match-indicator {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid var(--success-color);
  color: var(--success-color);
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--gray-500);
}

.empty-state i {
  font-size: 3rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

/* Matching Controls */
.match-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.btn-match, .btn-unmatch, .btn-adjustment {
  padding: 0.75rem 1rem;
  border-radius: var(--border-radius);
  font-weight: 600;
  font-size: 0.875rem;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.btn-match {
  background: var(--success-color);
  color: var(--white);
}

.btn-match:hover:not(:disabled) {
  background: #047857;
}

.btn-match:disabled {
  background: var(--gray-300);
  color: var(--gray-500);
  cursor: not-allowed;
}

.btn-unmatch {
  background: var(--warning-color);
  color: var(--white);
}

.btn-unmatch:hover:not(:disabled) {
  background: #b45309;
}

.btn-unmatch:disabled {
  background: var(--gray-300);
  color: var(--gray-500);
  cursor: not-allowed;
}

.btn-adjustment {
  background: var(--primary-color);
  color: var(--white);
}

.btn-adjustment:hover {
  background: var(--primary-dark);
}

.tolerance-settings, .match-suggestions {
  border-top: 1px solid var(--gray-200);
  padding-top: 1rem;
}

.tolerance-settings h4, .match-suggestions h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tolerance-settings h4 i, .match-suggestions h4 i {
  color: var(--primary-color);
}

.setting-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.setting-group label {
  font-weight: 600;
  color: var(--gray-700);
  font-size: 0.875rem;
}

.tolerance-input {
  padding: 0.5rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
}

.tolerance-input:focus {
  outline: none;
  border-color: var(--primary-color);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.tolerance-checkbox {
  width: 1rem;
  height: 1rem;
}

.no-suggestions {
  text-align: center;
  color: var(--gray-500);
  font-size: 0.875rem;
}

.suggestions-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.suggestion-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid var(--gray-200);
  border-radius: var(--border-radius);
  cursor: pointer;
  transition: var(--transition);
}

.suggestion-item:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
}

.suggestion-score {
  flex-shrink: 0;
}

.score-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--white);
}

.score-high {
  background: var(--success-color);
}

.score-medium {
  background: var(--warning-color);
}

.score-low {
  background: var(--danger-color);
}

.suggestion-details {
  flex: 1;
}

.suggestion-match {
  font-weight: 600;
  color: var(--gray-900);
  font-size: 0.875rem;
}

.suggestion-reason {
  color: var(--gray-600);
  font-size: 0.75rem;
}

.btn-apply-suggestion {
  width: 2rem;
  height: 2rem;
  border: none;
  background: var(--primary-color);
  color: var(--white);
  border-radius: 50%;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-apply-suggestion:hover {
  background: var(--primary-dark);
  transform: scale(1.1);
}

/* Modal */
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
  padding: 1rem;
}

.modal-content {
  background: var(--white);
  border-radius: var(--border-radius);
  max-width: 500px;
  width: 100%;
  box-shadow: var(--box-shadow-lg);
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: var(--gray-900);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-header h3 i {
  color: var(--primary-color);
}

.btn-close {
  width: 2rem;
  height: 2rem;
  border: none;
  background: transparent;
  color: var(--gray-400);
  border-radius: 6px;
  cursor: pointer;
  transition: var(--transition);
}

.btn-close:hover {
  background: var(--gray-100);
  color: var(--gray-600);
}

.modal-body {
  padding: 1.5rem;
}

.adjustment-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-label {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--gray-700);
}

.form-label.required::after {
  content: '*';
  color: var(--danger-color);
  margin-left: 0.25rem;
}

.form-input, .form-textarea {
  padding: 0.75rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  transition: var(--transition);
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.modal-footer {
  padding: 1.5rem;
  border-top: 1px solid var(--gray-200);
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Loading Overlay */
.loading-overlay {
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
}

.loading-content {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  text-align: center;
  box-shadow: var(--box-shadow-lg);
}

.loading-spinner {
  font-size: 2rem;
  color: var(--primary-color);
  margin-bottom: 1rem;
}

.loading-content p {
  color: var(--gray-600);
  margin: 0;
}

/* Responsive Design */
@media (max-width: 1400px) {
  .matching-grid {
    grid-template-columns: 1fr 280px 1fr;
  }

  .summary-cards {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1024px) {
  .bank-reconciliation-match {
    padding: 1rem;
  }

  .matching-grid {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
    gap: 1rem;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .title-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .page-title {
    font-size: 1.5rem;
  }

  .panel-controls {
    flex-direction: column;
  }

  .transaction-details {
    grid-template-columns: 1fr;
  }
}
</style>