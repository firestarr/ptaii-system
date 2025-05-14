<!-- src/layouts/AppLayout.vue -->
<template>
    <div class="app-container">
        <aside
            class="sidebar"
            :class="{ 'sidebar-collapsed': sidebarCollapsed }"
        >
            <div class="sidebar-header">
                <div class="brand-logo">
                    <span v-if="!sidebarCollapsed">Inventory ERP</span>
                    <span v-else>ERP</span>
                </div>
                <button class="collapse-btn" @click="toggleSidebar">
                    <i
                        :class="
                            sidebarCollapsed
                                ? 'fas fa-angle-right'
                                : 'fas fa-angle-left'
                        "
                    ></i>
                </button>
            </div>
            <nav class="sidebar-menu">
                <router-link
                    to="/dashboard"
                    class="menu-item"
                    active-class="active"
                >
                    <i class="fas fa-tachometer-alt"></i>
                    <span v-if="!sidebarCollapsed">Dashboard</span>
                </router-link>

                <!-- Inventory Management Section -->
                <div class="menu-section">
                    <div
                        @click="toggleMenuSection('inventory')"
                        class="section-header"
                    >
                        <div class="section-title-container">
                            <i class="fas fa-box"></i>
                            <span v-if="!sidebarCollapsed" class="section-title"
                                >Inventory</span
                            >
                        </div>
                        <i
                            v-if="!sidebarCollapsed"
                            :class="
                                menuSections.inventory
                                    ? 'fas fa-chevron-down'
                                    : 'fas fa-chevron-right'
                            "
                            class="section-icon"
                        ></i>
                    </div>
                </div>

                <div
                    v-show="!sidebarCollapsed && menuSections.inventory"
                    class="submenu"
                >
                    <router-link
                        to="/items"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-cubes"></i>
                        <span v-if="!sidebarCollapsed">Items</span>
                    </router-link>

                    <router-link
                        to="/item-categories"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-tags"></i>
                        <span v-if="!sidebarCollapsed">Categories</span>
                    </router-link>

                    <router-link
                        to="/unit-of-measures"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-ruler"></i>
                        <span v-if="!sidebarCollapsed">Units of Measure</span>
                    </router-link>

                    <router-link
                        to="/warehouses"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-warehouse"></i>
                        <span v-if="!sidebarCollapsed">Warehouses</span>
                    </router-link>
                    <!-- In the manufacturing submenu section -->
                    <router-link
                    to="/materials/plans"
                    class="menu-item"
                    active-class="active"
                    >
                    <i class="fas fa-boxes"></i>
                    <span v-if="!sidebarCollapsed">Material Planning</span>
                    </router-link>
                    <router-link
                    to="/materials/plans/generate"
                    class="menu-item"
                    active-class="active"
                    >
                    <i class="fas fa-plus-circle"></i>
                    <span v-if="!sidebarCollapsed">Generate Material Plans</span>
                    </router-link>

                    <router-link
                    to="/purchasing/requisitions/generate-from-material-plan"
                    class="menu-item"
                    active-class="active"
                    >
                    <i class="fas fa-file-plus"></i>
                    <span v-if="!sidebarCollapsed">Generate PR from Plans</span>
                    </router-link>
                    <router-link
                        to="/item-prices"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-tags"></i>
                        <span v-if="!sidebarCollapsed">Item Prices</span>
                    </router-link>

                    <router-link
                        to="/price-comparison"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-balance-scale"></i>
                        <span v-if="!sidebarCollapsed">Price Comparison</span>
                    </router-link>
                    <router-link to="/item-prices-management" class="menu-item" active-class="active">
                        <i class="fas fa-tags"></i>
                        <span v-if="!sidebarCollapsed">Item Prices Management</span>
                    </router-link>
                </div>

                <!-- Stock Operations Section -->
                <div class="menu-section">
                    <div
                        @click="toggleMenuSection('stockOperations')"
                        class="section-header"
                    >
                        <div class="section-title-container">
                            <i class="fas fa-exchange-alt"></i>
                            <span v-if="!sidebarCollapsed" class="section-title"
                                >Stock Operations</span
                            >
                        </div>
                        <i
                            v-if="!sidebarCollapsed"
                            :class="
                                menuSections.stockOperations
                                    ? 'fas fa-chevron-down'
                                    : 'fas fa-chevron-right'
                            "
                            class="section-icon"
                        ></i>
                    </div>
                </div>

                <div
                    v-show="!sidebarCollapsed && menuSections.stockOperations"
                    class="submenu"
                >
                    <router-link
                        to="/stock-transactions"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-random"></i>
                        <span v-if="!sidebarCollapsed">Transactions</span>
                    </router-link>

                    <router-link
                        to="/stock-adjustments"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-sliders-h"></i>
                        <span v-if="!sidebarCollapsed">Adjustments</span>
                    </router-link>

                    <router-link
                        to="/cycle-counts"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-clipboard-check"></i>
                        <span v-if="!sidebarCollapsed">Cycle Counting</span>
                    </router-link>
                </div>
                <!-- Purchasing Section -->
                <div class="menu-section">
                    <div
                        @click="toggleMenuSection('purchasing')"
                        class="section-header"
                    >
                        <div class="section-title-container">
                            <i class="fas fa-shopping-bag"></i>
                            <span v-if="!sidebarCollapsed" class="section-title"
                                >Purchasing</span
                            >
                        </div>
                        <i
                            v-if="!sidebarCollapsed"
                            :class="
                                menuSections.purchasing
                                    ? 'fas fa-chevron-down'
                                    : 'fas fa-chevron-right'
                            "
                            class="section-icon"
                        ></i>
                    </div>
                </div>

                <div
                    v-show="!sidebarCollapsed && menuSections.purchasing"
                    class="submenu"
                >
                    <router-link
                            to="/purchasing/dashboard"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-tachometer-alt"></i>
                            <span v-if="!sidebarCollapsed">Dashboard</span>
                        </router-link>

                        <router-link
                            to="/purchasing/spend-analysis"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-chart-pie"></i>
                            <span v-if="!sidebarCollapsed">Spend Analysis</span>
                        </router-link>

                        <router-link
                            to="/purchasing/vendor-performance"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-star"></i>
                            <span v-if="!sidebarCollapsed">Vendor Performance</span>
                        </router-link>

                        <router-link
                            to="/purchasing/po-status"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-clipboard-check"></i>
                            <span v-if="!sidebarCollapsed">PO Status</span>
                        </router-link>

                        <router-link
                            to="/purchasing/price-trend"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-chart-line"></i>
                            <span v-if="!sidebarCollapsed">Price Trends</span>
                        </router-link>
                        <router-link
                            to="/purchasing/vendors"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-users"></i>
                            <span v-if="!sidebarCollapsed">Vendors</span>
                        </router-link>

                        <router-link
                            to="/purchasing/requisitions"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-file-alt"></i>
                            <span v-if="!sidebarCollapsed">Requisitions</span>
                        </router-link>

                        <!-- Menu PR Approval baru -->
                        <router-link to="/purchasing/requisitions/approvals" class="menu-item" active-class="active">
                        <i class="fas fa-check-circle"></i>
                        <span v-if="!sidebarCollapsed">PR Approvals</span>
                        </router-link>

                        <!-- Menu PR to RFQ baru -->
                        <router-link to="/purchasing/requisitions/to-rfq" class="menu-item" active-class="active">
                        <i class="fas fa-exchange-alt"></i>
                        <span v-if="!sidebarCollapsed">PR to RFQ</span>
                        </router-link>

                        <!-- After the RFQs menu items and before the Purchase Orders menu item -->
                        <router-link
                            to="/purchasing/quotations"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span v-if="!sidebarCollapsed">Vendor Quotations</span>
                        </router-link>

                        <router-link
                            to="/purchasing/quotations/create"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-plus-circle"></i>
                            <span v-if="!sidebarCollapsed">Create Quotation</span>
                        </router-link>

                        <router-link
                            to="/purchasing/quotations/compare"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-balance-scale"></i>
                            <span v-if="!sidebarCollapsed">Compare Quotations</span>
                        </router-link>

                        <router-link
                            to="/purchasing/rfqs"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span v-if="!sidebarCollapsed">RFQs</span>
                        </router-link>

                        <router-link
                        to="/purchasing/rfqs/create"
                        class="menu-item"
                        active-class="active"
                        >
                        <i class="fas fa-plus-circle"></i>
                        <span v-if="!sidebarCollapsed">Create RFQ</span>
                        </router-link>

                        <router-link
                            to="/purchasing/orders"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-clipboard-list"></i>
                            <span v-if="!sidebarCollapsed">Purchase Orders</span>
                        </router-link>

                        <router-link
                            to="/purchasing/orders/create"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-plus-circle"></i>
                            <span v-if="!sidebarCollapsed">Create Purchase Order</span>
                        </router-link>

                        <router-link
                        to="/purchasing/goods-receipts"
                        class="menu-item"
                        active-class="active"
                        >
                        <i class="fas fa-truck-loading"></i>
                        <span v-if="!sidebarCollapsed">Goods Receipts</span>
                        </router-link>

                        <router-link
                        to="/purchasing/goods-receipts/create"
                        class="menu-item"
                        active-class="active"
                        >
                        <i class="fas fa-plus-circle"></i>
                        <span v-if="!sidebarCollapsed">Create Receipt</span>
                        </router-link>

                        <router-link
                        to="/purchasing/goods-receipts/dashboard"
                        class="menu-item"
                        active-class="active"
                        >
                        <i class="fas fa-tachometer-alt"></i>
                        <span v-if="!sidebarCollapsed">Receipts Dashboard</span>
                        </router-link>

                        <router-link
                            to="/purchasing/vendor-invoices"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-file-invoice"></i>
                            <span v-if="!sidebarCollapsed">Vendor Invoices</span>
                        </router-link>
                        <router-link
                            to="/purchasing/contracts"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-file-contract"></i>
                            <span v-if="!sidebarCollapsed">Vendor Contracts</span>
                        </router-link>

                        <router-link
                            to="/purchasing/contracts/expiry-dashboard"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-chart-line"></i>
                            <span v-if="!sidebarCollapsed"
                                >Contract Expiry Dashboard</span
                            >
                        </router-link>

                        <router-link
                            to="/purchasing/evaluations"
                            class="menu-item"
                            active-class="active"
                        >
                            <i class="fas fa-star"></i>
                            <span v-if="!sidebarCollapsed">Vendor Evaluations</span>
                        </router-link>

                        <router-link
                            to="/purchasing/evaluation-dashboard"
                            class="menu-item"
                            active-class="active"
                        >
                        <i class="fa-solid fa-gauge-high"></i>
                        <span v-if="!sidebarCollapsed">Evaluation Dashboard</span>
                    </router-link>
                </div>

                <!-- Sales Section -->
                <div class="menu-section">
                    <div
                        @click="toggleMenuSection('sales')"
                        class="section-header"
                    >
                        <div class="section-title-container">
                            <i class="fas fa-shopping-cart"></i>
                            <span v-if="!sidebarCollapsed" class="section-title"
                                >Sales</span
                            >
                        </div>
                        <i
                            v-if="!sidebarCollapsed"
                            :class="
                                menuSections.sales
                                    ? 'fas fa-chevron-down'
                                    : 'fas fa-chevron-right'
                            "
                            class="section-icon"
                        ></i>
                    </div>
                </div>

                <div
                    v-show="!sidebarCollapsed && menuSections.sales"
                    class="submenu"
                >
                    <router-link
                        to="/sales/customers"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-users"></i>
                        <span v-if="!sidebarCollapsed">Customers</span>
                    </router-link>

                    <router-link
                        to="/sales/quotations"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span v-if="!sidebarCollapsed">Quotations</span>
                    </router-link>

                    <router-link
                        to="/sales/forecasts"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-chart-line"></i>
                        <span v-if="!sidebarCollapsed">Forecasts</span>
                    </router-link>

                    <router-link
                        to="/sales/orders"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-file-signature"></i>
                        <span v-if="!sidebarCollapsed">Orders</span>
                    </router-link>

                    <router-link
                        to="/sales/deliveries"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-truck"></i>
                        <span v-if="!sidebarCollapsed">Deliveries</span>
                    </router-link>

                    <router-link
                        to="/sales/invoices"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-file-invoice"></i>
                        <span v-if="!sidebarCollapsed">Invoices</span>
                    </router-link>

                    <router-link
                        to="/sales/returns"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-undo"></i>
                        <span v-if="!sidebarCollapsed">Returns</span>
                    </router-link>
                </div>

                <div class="menu-section">
                    <div
                        @click="toggleMenuSection('manufacturing')"
                        class="section-header"
                    >
                        <div class="section-title-container">
                            <i class="fas fa-industry"></i>
                            <span v-if="!sidebarCollapsed" class="section-title"
                                >Manufacturing</span
                            >
                        </div>
                        <i
                            v-if="!sidebarCollapsed"
                            :class="
                                menuSections.manufacturing
                                    ? 'fas fa-chevron-down'
                                    : 'fas fa-chevron-right'
                            "
                            class="section-icon"
                        ></i>
                    </div>
                </div>

                <div
                    v-show="!sidebarCollapsed && menuSections.manufacturing"
                    class="submenu"
                >
                    <router-link
                        to="/manufacturing/boms"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-clipboard-list"></i>
                        <span v-if="!sidebarCollapsed">Bill of Materials</span>
                    </router-link>

                    <router-link
                        to="/manufacturing/routings"
                        class="menu-item"
                        active-class="active"
                        >
                        <i class="fas fa-project-diagram"></i>
                        <span v-if="!sidebarCollapsed">Routing</span>
                    </router-link>
                    <router-link
                        to="/manufacturing/work-centers"
                        class="menu-item"
                        active-class="active"
                        >
                        <i class="fas fa-industry"></i>
                        <span v-if="!sidebarCollapsed">Work Centers</span>
                    </router-link>
                    <router-link
                        to="/manufacturing/work-orders"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-clipboard-list"></i>
                        <span v-if="!sidebarCollapsed">Work Orders</span>
                    </router-link>
                    <router-link
                        to="/manufacturing/production-orders"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-cogs"></i>
                        <span v-if="!sidebarCollapsed">Production Orders</span>
                    </router-link>
                </div>
                <div class="menu-section">
                <div
                    @click="toggleMenuSection('quality')"
                    class="section-header"
                >
                    <div class="section-title-container">
                        <i class="fas fa-check-circle"></i>
                        <span v-if="!sidebarCollapsed" class="section-title">Quality Management</span>
                    </div>
                    <i
                        v-if="!sidebarCollapsed"
                        :class="
                            menuSections.quality
                                ? 'fas fa-chevron-down'
                                : 'fas fa-chevron-right'
                        "
                        class="section-icon"
                    ></i>
                </div>
            </div>

            <div
                v-show="!sidebarCollapsed && menuSections.quality"
                class="submenu"
            >
                <router-link
                    to="/quality-inspections"
                    class="menu-item"
                    active-class="active"
                >
                    <i class="fas fa-clipboard-check"></i>
                    <span v-if="!sidebarCollapsed">Quality Inspections</span>
                </router-link>

                <router-link
                    to="/quality-parameters/create"
                    class="menu-item"
                    active-class="active"
                >
                    <i class="fas fa-sliders-h"></i>
                    <span v-if="!sidebarCollapsed">Quality Parameters</span>
                </router-link>
                
                <router-link
                    to="/dashboard"
                    class="menu-item"
                    active-class="active"
                >
                    <i class="fas fa-chart-line"></i>
                    <span v-if="!sidebarCollapsed">Quality Dashboard</span>
                </router-link>
            </div>

                <div class="menu-section">
                <div
                    @click="toggleMenuSection('accounting')"
                    class="section-header"
                >
                    <div class="section-title-container">
                    <i class="fas fa-calculator"></i>
                    <span v-if="!sidebarCollapsed" class="section-title">Accounting</span>
                    </div>
                    <i
                    v-if="!sidebarCollapsed"
                    :class="
                        menuSections.accounting
                        ? 'fas fa-chevron-down'
                        : 'fas fa-chevron-right'
                    "
                    class="section-icon"
                    ></i>
                </div>
                </div>
                <div
                v-show="!sidebarCollapsed && menuSections.accounting"
                class="submenu"
                >
                <!-- Other accounting menu items -->

                <router-link
                    to="/currency-rates"
                    class="menu-item"
                    active-class="active"
                >
                    <i class="fas fa-money-bill-wave"></i>
                    <span v-if="!sidebarCollapsed">Exchange Rates</span>
                </router-link>

                <router-link
                    to="/currency-converter"
                    class="menu-item"
                    active-class="active"
                >
                    <i class="fas fa-exchange-alt"></i>
                    <span v-if="!sidebarCollapsed">Currency Converter</span>
                </router-link>

                <!-- Add more accounting menu items as needed -->
                </div>
                <!-- Reports Section -->
                <div class="menu-section">
                    <div
                        @click="toggleMenuSection('reports')"
                        class="section-header"
                    >
                        <div class="section-title-container">
                            <i class="fas fa-chart-bar"></i>
                            <span v-if="!sidebarCollapsed" class="section-title"
                                >Reports</span
                            >
                        </div>
                        <i
                            v-if="!sidebarCollapsed"
                            :class="
                                menuSections.reports
                                    ? 'fas fa-chevron-down'
                                    : 'fas fa-chevron-right'
                            "
                            class="section-icon"
                        ></i>
                    </div>
                </div>

                <div
                    v-show="!sidebarCollapsed && menuSections.reports"
                    class="submenu"
                >
                    <router-link
                        to="/reports/stock"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-boxes"></i>
                        <span v-if="!sidebarCollapsed">Stock Report</span>
                    </router-link>

                    <router-link
                        to="/reports/movement"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-chart-line"></i>
                        <span v-if="!sidebarCollapsed">Movement Report</span>
                    </router-link>

                    <router-link
                        to="/reports/sales"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-chart-pie"></i>
                        <span v-if="!sidebarCollapsed">Sales Report</span>
                    </router-link>
                </div>

                <!-- Admin Section -->
                <div class="menu-section">
                    <div
                        @click="toggleMenuSection('admin')"
                        class="section-header"
                    >
                        <div class="section-title-container">
                            <i class="fas fa-user-shield"></i>
                            <span v-if="!sidebarCollapsed" class="section-title"
                                >Admin</span
                            >
                        </div>
                        <i
                            v-if="!sidebarCollapsed"
                            :class="
                                menuSections.admin
                                    ? 'fas fa-chevron-down'
                                    : 'fas fa-chevron-right'
                            "
                            class="section-icon"
                        ></i>
                    </div>
                </div>

                <div
                    v-show="!sidebarCollapsed && menuSections.admin"
                    class="submenu"
                >
                    <router-link
                        to="/admin/users"
                        class="menu-item"
                        active-class="active"
                    >
                        <i class="fas fa-users-cog"></i>
                        <span v-if="!sidebarCollapsed">Users</span>
                    </router-link>
                    <router-link to="/admin/settings" class="nav-link">
                        <i class="fas fa-cogs"></i> System Settings
                    </router-link>
                </div>
            </nav>
        </aside>

        <div class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <h1 class="page-title">{{ pageTitle }}</h1>
                </div>
                <div class="header-right">
                    <div class="user-menu" @click="toggleUserMenu">
                        <img
                            class="avatar"
                            src="/images/user-avatar.png"
                            alt="User avatar"
                        />
                        <span class="username">{{ user.name }}</span>
                        <i class="fas fa-chevron-down"></i>

                        <div v-if="userMenuOpen" class="dropdown-menu">
                            <div class="dropdown-item">
                                <i class="fas fa-user"></i>
                                Profile
                            </div>
                            <div class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                Settings
                            </div>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-item" @click="logout">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, reactive } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "axios";

export default {
    name: "AppLayout",
    setup() {
        const router = useRouter();
        const route = useRoute();
        const sidebarCollapsed = ref(
            localStorage.getItem("sidebarCollapsed") === "true"
        );
        const userMenuOpen = ref(false);
        const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));

        // Menu sections state
        const menuSections = reactive({
            inventory: false,
            stockOperations: false,
            purchasing: false,
            sales: false,
            manufacturing: false,
            reports: false,
            admin: false,
        });

        // Automatically open submenu for active section
        const initializeActiveSection = () => {
            const path = route.path;
            if (
                path.includes("/items") ||
                path.includes("/item-categories") ||
                path.includes("/unit-of-measures") ||
                path.includes("/warehouses")
            ) {
                menuSections.inventory = true;
            } else if (
                path.includes("/stock-transactions") ||
                path.includes("/stock-adjustments") ||
                path.includes("/cycle-counts")
            ) {
                menuSections.stockOperations = true;
            } else if (
                path.includes("/customers") ||
                path.includes("/sales/")
            ) {
                menuSections.sales = true;
            } else if (
                path.includes("/manufacturing/") // Add this condition
            ) {
                menuSections.manufacturing = true;
            } else if (path.includes("/reports/")) {
                menuSections.reports = true;
            } else if (path.includes("/admin/")) {
                menuSections.admin = true;
            }
        };

        const toggleMenuSection = (section) => {
            // If sidebar is collapsed, expand it first
            if (sidebarCollapsed.value) {
                sidebarCollapsed.value = false;
                localStorage.setItem("sidebarCollapsed", "false");

                // Set a short timeout to let the sidebar expand first
                setTimeout(() => {
                    menuSections[section] = !menuSections[section];
                }, 50);
            } else {
                menuSections[section] = !menuSections[section];
            }
        };

        const pageTitle = computed(() => {
            switch (route.name) {
                case "Dashboard":
                    return "Dashboard";
                case "Items":
                    return "Items Management";
                case "ItemCategories":
                    return "Item Categories";
                case "UnitOfMeasures":
                    return "Units of Measure";
                case "Warehouses":
                    return "Warehouses";
                case "ItemPrices":
                    return "Item Prices";
                case "PriceComparison":
                    return "Price Comparison";
                case "ItemPriceManagement":
                    return "Item Prices Management";
                case "StockTransactions":
                    return "Stock Transactions";
                case "StockAdjustmentsList":
                    return "Stock Adjustments";
                case "CycleCounting":
                    return "Cycle Counting";
                case "GoodsReceiptList":
                    return "Goods Receipts";
                case "CreateGoodsReceipt":
                    return "Create Goods Receipt";
                case "EditGoodsReceipt":
                    return "Edit Goods Receipt";
                case "GoodsReceiptDetail":
                    return "Goods Receipt Details";
                case "ConfirmGoodsReceipt":
                    return "Confirm Goods Receipt";
                case "PendingReceiptsDashboard":
                    return "Receipts Dashboard";
                case "Customers":
                    return "Customers";
                case "SalesQuotations":
                    return "Sales Quotations";
                case "SalesOrders":
                    return "Sales Orders";
                case "Deliveries":
                    return "Deliveries";
                case "SalesInvoices":
                    return "Sales Invoices";
                case "SalesReturns":
                    return "Sales Returns";
                case "StockReport":
                    return "Stock Report";
                case "MovementReport":
                    return "Movement Report";
                case "SalesReport":
                    return "Sales Report";
                case 'VendorQuotations':
                    return 'Vendor Quotations';
                case 'CreateVendorQuotation':
                    return 'Create Vendor Quotation';
                case 'VendorQuotationDetail':
                    return 'Vendor Quotation Detail';
                case 'EditVendorQuotation':
                    return 'Edit Vendor Quotation';
                case 'CompareVendorQuotations':
                    return 'Compare Quotations';
                case 'CreatePOFromQuotation':
                    return 'Create PO from Quotation';
                case "Users":
                    return "User Management";
                default:
                    return "Dashboard";
            }
        });

        const toggleSidebar = () => {
            sidebarCollapsed.value = !sidebarCollapsed.value;
            localStorage.setItem("sidebarCollapsed", sidebarCollapsed.value);
        };

        const toggleUserMenu = () => {
            userMenuOpen.value = !userMenuOpen.value;
        };

        const logout = async () => {
            try {
                await axios.post("/api/auth/logout");
            } catch (error) {
                console.error("Logout error:", error);
            } finally {
                localStorage.removeItem("token");
                localStorage.removeItem("user");
                axios.defaults.headers.common["Authorization"] = "";
                router.push("/login");
            }
        };

        // Close dropdown when clicking outside
        onMounted(() => {
            document.addEventListener("click", (event) => {
                const userMenu = document.querySelector(".user-menu");
                if (userMenu && !userMenu.contains(event.target)) {
                    userMenuOpen.value = false;
                }
            });

            initializeActiveSection();
        });

        return {
            sidebarCollapsed,
            userMenuOpen,
            user,
            pageTitle,
            menuSections,
            toggleMenuSection,
            toggleSidebar,
            toggleUserMenu,
            logout,
        };
    },
};
</script>

<style scoped>
.app-container {
    display: flex;
    height: 100vh;
    overflow: hidden;
}

.sidebar {
    width: 250px;
    background-color: #1e293b;
    color: #f8fafc;
    display: flex;
    flex-direction: column;
    transition: width 0.3s ease;
}

.sidebar-collapsed {
    width: 70px;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #334155;
}

.brand-logo {
    font-size: 1.25rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
}

.collapse-btn {
    background: transparent;
    border: none;
    color: #f8fafc;
    cursor: pointer;
    font-size: 1rem;
    padding: 0.25rem;
}

.sidebar-menu {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
}

.menu-section {
    margin-top: 0.5rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #94a3b8;
    cursor: pointer;
    transition: background-color 0.2s;
}

.section-header:hover {
    background-color: #334155;
}

.section-title-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.section-icon {
    font-size: 0.75rem;
}

.submenu {
    padding-left: 0.5rem;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #e2e8f0;
    text-decoration: none;
    transition: background-color 0.2s;
}

.menu-item:hover {
    background-color: #334155;
}

.menu-item.active {
    background-color: #2563eb;
    font-weight: 500;
}

.menu-item i {
    font-size: 1rem;
    margin-right: 1rem;
    width: 20px;
    text-align: center;
}

.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background-color: #f1f5f9;
}

.main-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background-color: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.user-menu {
    display: flex;
    align-items: center;
    cursor: pointer;
    position: relative;
}

.avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    margin-right: 0.75rem;
}

.username {
    font-weight: 500;
    margin-right: 0.5rem;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    background-color: white;
    border-radius: 0.375rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 12rem;
    z-index: 20;
}

.dropdown-item {
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    color: #1e293b;
    transition: background-color 0.2s;
}

.dropdown-item i {
    width: 1.25rem;
    margin-right: 0.75rem;
}

.dropdown-item:hover {
    background-color: #f1f5f9;
}

.dropdown-divider {
    height: 1px;
    background-color: #e2e8f0;
    margin: 0.25rem 0;
}

.content {
    flex: 1;
    padding: 2rem;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        height: 100vh;
        z-index: 30;
        transform: translateX(0);
        transition: transform 0.3s ease;
    }

    .sidebar-collapsed {
        transform: translateX(-100%);
    }

    .main-header {
        padding: 1rem;
    }

    .content {
        padding: 1rem;
    }
}
</style>
