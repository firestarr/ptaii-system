<template>
  <div class="vendor-recommendations">
    <!-- Header with PR Info -->
    <div class="pr-header">
      <h2>Vendor Recommendations - {{ pr.pr_number }}</h2>
      <div class="pr-stats">
        <span>{{ recommendations.length }} Items</span>
        <span>{{ summary.items_with_vendors }} with Vendors</span>
        <span>{{ summary.average_vendors_per_item }} Avg Vendors/Item</span>
      </div>
    </div>

    <!-- Procurement Strategy Recommendation -->
    <div class="strategy-card">
      <h3>Recommended Strategy: {{ summary.recommended_approach }}</h3>
      <div class="strategy-actions">
        <button @click="createDirectPO" v-if="canCreateDirectPO" class="btn-primary">
          Create Direct PO
        </button>
        <button @click="createMultiVendorPO" v-if="hasMultipleVendors" class="btn-secondary">
          Split Between Vendors
        </button>
        <button @click="createMultiRFQ" class="btn-outline">
          Send Multi-Vendor RFQ
        </button>
      </div>
    </div>

    <!-- Items Recommendations Table -->
    <div class="recommendations-table">
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Qty Required</th>
            <th>Best Vendor</th>
            <th>Price</th>
            <th>Lead Time</th>
            <th>Options</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rec in recommendations" :key="rec.pr_line_id">
            <td>
              <div>
                <strong>{{ rec.item_code }}</strong>
                <div>{{ rec.item_name }}</div>
              </div>
            </td>
            <td>{{ rec.required_quantity }} {{ rec.uom }}</td>
            <td v-if="rec.recommended_vendor">
              <div class="vendor-cell">
                <strong>{{ rec.recommended_vendor.vendor_name }}</strong>
                <div class="vendor-rating">
                  ⭐ {{ rec.recommended_vendor.vendor_rating }}/10
                </div>
              </div>
            </td>
            <td v-else>No vendors</td>
            <td v-if="rec.recommended_vendor">
              {{ formatCurrency(rec.recommended_vendor.unit_price) }}
              <small>({{ rec.recommended_vendor.currency }})</small>
            </td>
            <td v-else>-</td>
            <td v-if="rec.recommended_vendor">
              {{ rec.recommended_vendor.estimated_lead_time_days }} days
            </td>
            <td v-else>-</td>
            <td>
              <div class="options-list">
                <span v-for="option in rec.procurement_options" 
                      :key="option.type" 
                      :class="`option-${option.type}`">
                  {{ option.description }}
                </span>
              </div>
            </td>
            <td>
              <div class="action-buttons">
                <button @click="viewVendorOptions(rec)" class="btn-small">
                  {{ rec.available_vendors.length }} Vendors
                </button>
                <button @click="selectCustomVendor(rec)" class="btn-small-outline">
                  Custom
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Vendor Options Modal -->
    <VendorOptionsModal 
      v-if="showVendorModal"
      :item="selectedItem"
      :vendors="selectedItem?.available_vendors || []"
      @close="showVendorModal = false"
      @select="selectVendorForItem"
    />
  </div>
</template>

<script>
import axios from 'axios'
import VendorOptionsModal from '@/components/purchasing/VendorOptionsModal.vue'

export default {
  name: 'PRVendorRecommendations',
  components: {
    VendorOptionsModal
  },
  props: {
    id: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      pr: {},
      recommendations: [],
      summary: {},
      loading: false,
      showVendorModal: false,
      selectedItem: null,
      customSelections: {}
    }
  },
  computed: {
    canCreateDirectPO() {
      return this.recommendations.every(rec => 
        rec.recommended_vendor && rec.recommended_vendor.unit_price > 0
      )
    },
    hasMultipleVendors() {
      return this.recommendations.some(rec => 
        rec.available_vendors.length > 1
      )
    }
  },
  mounted() {
    this.fetchRecommendations()
  },
  methods: {
    async fetchRecommendations() {
      this.loading = true
      try {
        const response = await axios.get(`/purchase-requisitions/${this.id}/vendor-recommendations`)
        this.pr = response.data.data.pr
        this.recommendations = response.data.data.recommendations
        this.summary = response.data.data.summary
      } catch (error) {
        this.$toast.error('Failed to fetch vendor recommendations')
      } finally {
        this.loading = false
      }
    },

    createDirectPO() {
      // Navigate to direct PO creation
      this.$router.push({
        name: 'CreatePOFromPR',
        params: { prId: this.id },
        query: { mode: 'direct' }
      })
    },

    createMultiVendorPO() {
      // Navigate to multi-vendor PO wizard
      this.$router.push({
        name: 'CreateMultiVendorPO', 
        params: { id: this.id }
      })
    },

    createMultiRFQ() {
      // Navigate to multi-vendor RFQ
      this.$router.push({
        name: 'CreateMultiVendorRFQ',
        params: { id: this.id }
      })
    },

    viewVendorOptions(item) {
      this.selectedItem = item
      this.showVendorModal = true
    },

    selectVendorForItem(selection) {
      this.customSelections[selection.pr_line_id] = selection
      this.showVendorModal = false
    },

    selectCustomVendor(item) {
      // Open vendor selection for this specific item
      this.selectedItem = item
      this.showVendorModal = true
    },

    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    }
  }
}
</script>