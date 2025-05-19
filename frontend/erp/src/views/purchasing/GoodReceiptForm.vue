<template>
    <div class="receipt-form">
        <form @submit.prevent="handleSubmit">
            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="receipt_number">Receipt Number</label>
                        <input
                            type="text"
                            id="receipt_number"
                            v-model="formData.receipt_number"
                            :disabled="isEditMode"
                            placeholder="Auto-generated"
                        />
                        <div v-if="errors.receipt_number" class="error-message">
                            {{ errors.receipt_number }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="receipt_date">Receipt Date*</label>
                        <input
                            type="date"
                            id="receipt_date"
                            v-model="formData.receipt_date"
                            required
                        />
                        <div v-if="errors.receipt_date" class="error-message">
                            {{ errors.receipt_date }}
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="po_id">Purchase Order*</label>
                        <select
                            id="po_id"
                            v-model="formData.po_id"
                            required
                            @change="loadPurchaseOrderDetails"
                        >
                            <option value="">Select Purchase Order</option>
                            <option
                                v-for="po in purchaseOrders"
                                :key="po.po_id"
                                :value="po.po_id"
                            >
                                {{ po.po_number }} -
                                {{ po.vendor ? po.vendor.name : "N/A" }}
                            </option>
                        </select>
                        <div v-if="errors.po_id" class="error-message">
                            {{ errors.po_id }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="vendor_id">Vendor</label>
                        <input
                            type="text"
                            id="vendor_id"
                            v-model="vendorName"
                            disabled
                            placeholder="Automatically filled from PO"
                        />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="reference">Reference / Delivery Note</label>
                        <input
                            type="text"
                            id="reference"
                            v-model="formData.reference"
                            placeholder="Vendor's delivery note number"
                        />
                    </div>

                    <div class="form-group">
                        <label for="received_by">Received By</label>
                        <input
                            type="text"
                            id="received_by"
                            v-model="formData.received_by"
                            placeholder="Name of receiving person"
                        />
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header-with-actions">
                    <h3 class="section-title">Receipt Items</h3>
                </div>

                <div v-if="errors.lines" class="error-message mb-3">
                    {{ errors.lines }}
                </div>

                <div v-if="poLoading" class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Loading purchase
                    order items...
                </div>

                <div
                    v-else-if="formData.lines.length === 0"
                    class="empty-lines"
                >
                    <p>Please select a purchase order to see the items.</p>
                </div>

                <div v-else class="lines-table-container">
                    <table class="lines-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Ordered Qty</th>
                                <th>Remaining Qty</th>
                                <th>Received Qty*</th>
                                <th>UoM</th>
                                <th>Warehouse*</th>
                                <th>Location</th>
                                <th>Batch/Lot No.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(line, index) in formData.lines"
                                :key="index"
                                :class="{
                                    'disabled-row':
                                        line.remaining_quantity <= 0,
                                }"
                            >
                                <td>
                                    <div class="item-info">
                                        <div class="item-name">
                                            {{ line.item_name }}
                                        </div>
                                        <div class="item-code">
                                            {{ line.item_code }}
                                        </div>
                                    </div>
                                </td>
                                <td>{{ line.ordered_quantity }}</td>
                                <td>{{ line.remaining_quantity }}</td>
                                <td>
                                    <input
                                        type="number"
                                        v-model.number="line.received_quantity"
                                        min="0"
                                        :max="line.remaining_quantity"
                                        required
                                        class="full-width"
                                        :disabled="line.remaining_quantity <= 0"
                                    />
                                    <div
                                        v-if="
                                            getLineError(
                                                index,
                                                'received_quantity'
                                            )
                                        "
                                        class="error-message"
                                    >
                                        {{
                                            getLineError(
                                                index,
                                                "received_quantity"
                                            )
                                        }}
                                    </div>
                                </td>
                                <td>{{ line.uom_name }}</td>
                                <td>
                                    <select
                                        v-model="line.warehouse_id"
                                        required
                                        class="full-width"
                                        @change="loadLocations(line)"
                                        :disabled="line.remaining_quantity <= 0"
                                    >
                                        <option value="">
                                            Select Warehouse
                                        </option>
                                        <option
                                            v-for="warehouse in warehouses"
                                            :key="warehouse.warehouse_id"
                                            :value="warehouse.warehouse_id"
                                        >
                                            {{ warehouse.name }}
                                        </option>
                                    </select>
                                    <div
                                        v-if="
                                            getLineError(index, 'warehouse_id')
                                        "
                                        class="error-message"
                                    >
                                        {{
                                            getLineError(index, "warehouse_id")
                                        }}
                                    </div>
                                </td>
                                <td>
                                    <select
                                        v-model="line.location_id"
                                        class="full-width"
                                        :disabled="line.remaining_quantity <= 0"
                                    >
                                        <option value="">
                                            Select Location
                                        </option>
                                        <option
                                            v-for="location in getLocationOptions(
                                                line
                                            )"
                                            :key="location.location_id"
                                            :value="location.location_id"
                                        >
                                            {{ location.name }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        v-model="line.batch_number"
                                        class="full-width"
                                        placeholder="Optional"
                                        :disabled="line.remaining_quantity <= 0"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea
                    id="notes"
                    v-model="formData.notes"
                    rows="3"
                    placeholder="Additional notes about this receipt"
                ></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" @click="cancel">
                    Cancel
                </button>
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="isSubmitting"
                >
                    {{
                        isSubmitting
                            ? "Saving..."
                            : isEditMode
                            ? "Update Receipt"
                            : "Create Receipt"
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
<script>
import { ref, watch, onMounted } from "vue";

export default {
    name: "GoodsReceiptForm",
    props: {
        receipt: {
            type: Object,
            default: null,
        },
        isEditMode: {
            type: Boolean,
            default: false,
        },
        isSubmitting: {
            type: Boolean,
            default: false,
        },
        purchaseOrders: {
            type: Array,
            default: () => [],
        },
        warehouses: {
            type: Array,
            default: () => [],
        },
        serverErrors: {
            type: Object,
            default: () => ({}),
        },
    },
    emits: ["submit", "cancel"],
    setup(props, { emit }) {
        // Form data
        const formData = ref({
            receipt_number: "",
            receipt_date: new Date().toISOString().slice(0, 10), // Today's date as default
            po_id: "",
            vendor_id: "",
            reference: "",
            received_by: "",
            notes: "",
            lines: [],
        });

        // Form state
        const errors = ref({});
        const vendorName = ref("");
        const poLoading = ref(false);
        const warehouseLocations = ref({});

        // Initialize form with receipt data if provided
        const initForm = () => {
            if (props.receipt) {
                formData.value = {
                    receipt_number: props.receipt.receipt_number || "",
                    receipt_date:
                        props.receipt.receipt_date ||
                        new Date().toISOString().slice(0, 10),
                    po_id: props.receipt.po_id || "",
                    vendor_id: props.receipt.vendor_id || "",
                    reference: props.receipt.reference || "",
                    received_by: props.receipt.received_by || "",
                    notes: props.receipt.notes || "",
                    lines: [],
                };

                if (props.receipt.vendor) {
                    vendorName.value = props.receipt.vendor.name;
                }

                // Load purchase order details if PO is set
                if (formData.value.po_id) {
                    loadPurchaseOrderDetails();
                }
            }
        };

        // Watch for receipt prop changes
        watch(
            () => props.receipt,
            () => {
                initForm();
            },
            { deep: true }
        );

        // Watch for server errors
        watch(
            () => props.serverErrors,
            (newErrors) => {
                errors.value = { ...newErrors };
            },
            { deep: true }
        );

        // Load purchase order details
        const loadPurchaseOrderDetails = async () => {
            if (!formData.value.po_id) {
                formData.value.lines = [];
                vendorName.value = "";
                return;
            }

            poLoading.value = true;

            try {
                // Fetch PO details from API
                const response = await fetch(
                    `/purchase-orders/${formData.value.po_id}`,
                    {
                        headers: {
                            Authorization: `Bearer ${localStorage.getItem(
                                "token"
                            )}`,
                            "Content-Type": "application/json",
                        },
                    }
                );

                const data = await response.json();

                if (data && data.data) {
                    const po = data.data;

                    // Set vendor ID and name
                    formData.value.vendor_id = po.vendor_id;
                    vendorName.value = po.vendor ? po.vendor.name : "";

                    // Initialize lines from PO lines
                    if (po.lines && po.lines.length > 0) {
                        formData.value.lines = po.lines.map((line) => {
                            // If we're editing an existing receipt, find the matching line
                            let receivedQuantity = 0;
                            let warehouseId = "";
                            let locationId = "";
                            let batchNumber = "";

                            if (props.receipt && props.receipt.lines) {
                                const receiptLine = props.receipt.lines.find(
                                    (rl) => rl.po_line_id === line.line_id
                                );
                                if (receiptLine) {
                                    receivedQuantity =
                                        receiptLine.received_quantity;
                                    warehouseId = receiptLine.warehouse_id;
                                    locationId = receiptLine.location_id;
                                    batchNumber = receiptLine.batch_number;
                                }
                            }

                            // Calculate remaining quantity
                            const receivedQty = po.goodsReceipts
                                ? po.goodsReceipts.reduce((total, gr) => {
                                      const grLine = gr.lines
                                          ? gr.lines.find(
                                                (grl) =>
                                                    grl.po_line_id ===
                                                    line.line_id
                                            )
                                          : null;
                                      return (
                                          total +
                                          (grLine
                                              ? grLine.received_quantity
                                              : 0)
                                      );
                                  }, 0)
                                : 0;

                            const remainingQuantity =
                                line.quantity - receivedQty;

                            return {
                                po_line_id: line.line_id,
                                item_id: line.item_id,
                                item_name: line.item ? line.item.name : "",
                                item_code: line.item ? line.item.item_code : "",
                                ordered_quantity: line.quantity,
                                remaining_quantity: remainingQuantity,
                                received_quantity:
                                    receivedQuantity ||
                                    (remainingQuantity > 0
                                        ? remainingQuantity
                                        : 0),
                                uom_id: line.uom_id,
                                uom_name: line.unitOfMeasure
                                    ? line.unitOfMeasure.name
                                    : "",
                                warehouse_id: warehouseId || "",
                                location_id: locationId || "",
                                batch_number: batchNumber || "",
                            };
                        });
                    } else {
                        formData.value.lines = [];
                    }
                }
            } catch (error) {
                console.error("Error loading purchase order details:", error);
                formData.value.lines = [];
            } finally {
                poLoading.value = false;
            }
        };

        // Load warehouse locations
        const loadLocations = async (line) => {
            if (!line.warehouse_id) {
                return;
            }

            if (warehouseLocations.value[line.warehouse_id]) {
                return; // Already loaded
            }

            try {
                const response = await fetch(
                    `/warehouses/${line.warehouse_id}/inventory`,
                    {
                        headers: {
                            Authorization: `Bearer ${localStorage.getItem(
                                "token"
                            )}`,
                            "Content-Type": "application/json",
                        },
                    }
                );

                const data = await response.json();

                if (data && data.data && data.data.locations) {
                    warehouseLocations.value[line.warehouse_id] =
                        data.data.locations;
                } else {
                    warehouseLocations.value[line.warehouse_id] = [];
                }
            } catch (error) {
                console.error(
                    `Error loading locations for warehouse ${line.warehouse_id}:`,
                    error
                );
                warehouseLocations.value[line.warehouse_id] = [];
            }
        };

        // Get locations for a warehouse
        const getLocationOptions = (line) => {
            if (!line.warehouse_id) return [];
            return warehouseLocations.value[line.warehouse_id] || [];
        };

        // Get error for a specific line field
        const getLineError = (index, field) => {
            const key = `lines.${index}.${field}`;
            return errors.value[key];
        };

        // Form submission
        const handleSubmit = () => {
            // Basic validation
            const validationErrors = {};

            if (!formData.value.receipt_date) {
                validationErrors.receipt_date = "Receipt date is required";
            }

            if (!formData.value.po_id) {
                validationErrors.po_id = "Purchase order is required";
            }

            // Validate lines
            if (formData.value.lines.length === 0) {
                validationErrors.lines =
                    "Receipt must have at least one line item";
            } else {
                formData.value.lines.forEach((line, index) => {
                    if (line.remaining_quantity <= 0) {
                        // Skip validation for fully received lines
                        return;
                    }

                    if (line.received_quantity <= 0) {
                        validationErrors[`lines.${index}.received_quantity`] =
                            "Received quantity must be greater than 0";
                    } else if (
                        line.received_quantity > line.remaining_quantity
                    ) {
                        validationErrors[`lines.${index}.received_quantity`] =
                            "Received quantity cannot exceed remaining quantity";
                    }

                    if (!line.warehouse_id) {
                        validationErrors[`lines.${index}.warehouse_id`] =
                            "Warehouse is required";
                    }
                });
            }

            // If validation errors exist, update errors and stop submission
            if (Object.keys(validationErrors).length > 0) {
                errors.value = validationErrors;
                return;
            }

            // Clear validation errors
            errors.value = {};

            // Prepare data for submission
            const submitData = {
                receipt_number: formData.value.receipt_number,
                receipt_date: formData.value.receipt_date,
                po_id: formData.value.po_id,
                vendor_id: formData.value.vendor_id,
                reference: formData.value.reference,
                received_by: formData.value.received_by,
                notes: formData.value.notes,
                lines: formData.value.lines
                    .filter(
                        (line) =>
                            line.remaining_quantity > 0 &&
                            line.received_quantity > 0
                    )
                    .map((line) => ({
                        po_line_id: line.po_line_id,
                        item_id: line.item_id,
                        received_quantity: line.received_quantity,
                        warehouse_id: line.warehouse_id,
                        location_id: line.location_id || null,
                        batch_number: line.batch_number || null,
                    })),
            };

            // Emit submit event with form data
            emit("submit", submitData);
        };

        // Cancel form
        const cancel = () => {
            emit("cancel");
        };

        // Initialize form on component mount
        onMounted(() => {
            initForm();
        });

        return {
            formData,
            errors,
            vendorName,
            poLoading,
            warehouseLocations,
            handleSubmit,
            cancel,
            loadPurchaseOrderDetails,
            loadLocations,
            getLocationOptions,
            getLineError,
        };
    },
};
</script>
<style scoped>
.receipt-form {
    max-width: 1000px;
    margin: 0 auto;
}

.form-section {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 1.25rem 0;
    color: #1e293b;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.section-header-with-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.section-header-with-actions .section-title {
    margin: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #334155;
    font-size: 0.875rem;
}

input[type="text"],
input[type="date"],
input[type="number"],
textarea,
select {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background-color: white;
    transition: border-color 0.2s;
}

input[type="text"]:focus,
input[type="date"]:focus,
input[type="number"]:focus,
textarea:focus,
select:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

input[type="text"]:disabled,
input[type="date"]:disabled,
input[type="number"]:disabled,
textarea:disabled,
select:disabled {
    background-color: #f8fafc;
    cursor: not-allowed;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

.error-message {
    margin-top: 0.375rem;
    font-size: 0.75rem;
    color: #dc2626;
}

.mb-3 {
    margin-bottom: 0.75rem;
}

.loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    color: var(--gray-500);
    font-size: 0.875rem;
}

.loading-indicator i {
    margin-right: 0.5rem;
}

.empty-lines {
    text-align: center;
    padding: 2rem 1rem;
    background-color: #f8fafc;
    border-radius: 0.375rem;
    border: 1px dashed #cbd5e1;
}

.lines-table-container {
    overflow-x: auto;
}

.lines-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.lines-table th {
    text-align: left;
    padding: 0.75rem;
    background-color: #f8fafc;
    font-weight: 500;
    color: #334155;
    border-bottom: 1px solid #e2e8f0;
}

.lines-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.lines-table tbody tr:last-child td {
    border-bottom: 2px solid #e2e8f0;
}

.disabled-row {
    background-color: #f1f5f9;
    color: #64748b;
}

.disabled-row input,
.disabled-row select {
    background-color: #f1f5f9;
    color: #64748b;
    cursor: not-allowed;
}

.item-info {
    display: flex;
    flex-direction: column;
}

.item-name {
    font-weight: 500;
}

.item-code {
    font-size: 0.75rem;
    color: var(--gray-500);
}

.full-width {
    width: 100%;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.625rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
    transition: background-color 0.2s, color 0.2s;
}

.btn-primary {
    background-color: #2563eb;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background-color: #1d4ed8;
}

.btn-primary:disabled {
    background-color: #93c5fd;
    cursor: not-allowed;
}

.btn-secondary {
    background-color: #e2e8f0;
    color: #1e293b;
}

.btn-secondary:hover {
    background-color: #cbd5e1;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>
