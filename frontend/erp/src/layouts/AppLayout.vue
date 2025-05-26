<!-- src/layouts/AppLayout.vue -->
<template>
    <div class="app-container" :class="{ 'dark': isDarkMode }">
        <!-- Animated Background -->
        <div class="background-animation">
            <div class="floating-orbs">
                <div class="orb orb-1"></div>
                <div class="orb orb-2"></div>
                <div class="orb orb-3"></div>
                <div class="orb orb-4"></div>
                <div class="orb orb-5"></div>
            </div>
        </div>

        <!-- Modern Top Navigation -->
        <nav class="top-navigation">
            <div class="nav-container">
                <!-- Brand Section -->
                <div class="brand-section">
                    <div class="brand-logo">
                        <div class="logo-icon">
                            <i class="fas fa-cube"></i>
                        </div>
                        <div class="brand-text">
                            <h2>Inventory ERP</h2>
                            <p>Modern Business Suite</p>
                        </div>
                    </div>
                </div>

                <!-- Main Navigation -->
                <div class="main-nav">
                    <div class="nav-item" @mouseenter="showMegaMenu('dashboard')" @mouseleave="hideMegaMenu">
                        <router-link to="/dashboard" class="nav-link" :class="{ active: $route.path === '/dashboard' }">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </router-link>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('inventory')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-box"></i>
                            <span>Inventory</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('stockManagement')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-boxes"></i>
                            <span>Stock</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('purchasing')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-shopping-bag"></i>
                            <span>Purchasing</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('sales')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Sales</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('manufacturing')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-industry"></i>
                            <span>Manufacturing</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('quality')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-check-circle"></i>
                            <span>Quality</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('accounting')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-calculator"></i>
                            <span>Accounting</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('reports')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>

                    <div class="nav-item" @mouseenter="showMegaMenu('admin')" @mouseleave="hideMegaMenu">
                        <div class="nav-link">
                            <i class="fas fa-user-shield"></i>
                            <span>Admin</span>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                    </div>
                </div>

                <!-- Right Section -->
                <div class="nav-right">
                    <!-- Search -->
                    <div class="search-container">
                        <div class="search-box" :class="{ active: searchFocused }">
                            <i class="fas fa-search search-icon"></i>
                            <input 
                                type="text" 
                                class="search-input" 
                                placeholder="Search anything..."
                                v-model="searchQuery"
                                @focus="searchFocused = true"
                                @blur="handleSearchBlur"
                            />
                            <Transition name="fade">
                                <div v-if="searchFocused && searchQuery" class="search-results">
                                    <div class="search-category">
                                        <h4>Quick Actions</h4>
                                        <div class="search-item">
                                            <i class="fas fa-plus"></i>
                                            <span>Add New Item</span>
                                        </div>
                                        <div class="search-item">
                                            <i class="fas fa-shopping-cart"></i>
                                            <span>Create Order</span>
                                        </div>
                                    </div>
                                    <div class="search-category">
                                        <h4>Navigation</h4>
                                        <div class="search-item">
                                            <i class="fas fa-box"></i>
                                            <span>Items Management</span>
                                        </div>
                                        <div class="search-item">
                                            <i class="fas fa-users"></i>
                                            <span>Customer List</span>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <button class="action-btn" @click="quickCreateItem" title="Add Item">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="action-btn" @click="showNotifications = !showNotifications" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <span v-if="notificationCount > 0" class="notification-badge">{{ notificationCount }}</span>
                        </button>
                        <button class="action-btn" @click="toggleTheme" title="Toggle Theme">
                            <i :class="isDarkMode ? 'fas fa-sun' : 'fas fa-moon'"></i>
                        </button>
                    </div>

                    <!-- User Menu -->
                    <div class="user-section" @click="toggleUserMenu">
                        <div class="user-avatar">
                            <span>{{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}</span>
                        </div>
                        <div class="user-info">
                            <span class="username">{{ user.name || 'John Doe' }}</span>
                            <span class="user-role">{{ user.role || 'Administrator' }}</span>
                        </div>
                        <i class="fas fa-chevron-down user-arrow" :class="{ rotated: userMenuOpen }"></i>
                    </div>
                </div>
            </div>

            <!-- Mega Menu -->
            <Transition name="slide-down">
                <div v-if="activeMegaMenu" class="mega-menu" @mouseenter="keepMegaMenuOpen" @mouseleave="hideMegaMenu">
                    <!-- Inventory Mega Menu -->
                    <div v-if="activeMegaMenu === 'inventory'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-box"></i> Items & Categories</h3>
                            <div class="menu-links">
                                <router-link to="/items" class="menu-link">
                                    <i class="fas fa-cubes"></i>
                                    <div>
                                        <span>Items</span>
                                        <small>Manage inventory items</small>
                                    </div>
                                </router-link>
                                <router-link to="/item-categories" class="menu-link">
                                    <i class="fas fa-tags"></i>
                                    <div>
                                        <span>Categories</span>
                                        <small>Organize by categories</small>
                                    </div>
                                </router-link>
                                <router-link to="/unit-of-measures" class="menu-link">
                                    <i class="fas fa-ruler"></i>
                                    <div>
                                        <span>Units</span>
                                        <small>Measurement units</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-warehouse"></i> Storage & Planning</h3>
                            <div class="menu-links">
                                <router-link to="/warehouses" class="menu-link">
                                    <i class="fas fa-warehouse"></i>
                                    <div>
                                        <span>Warehouses</span>
                                        <small>Storage locations</small>
                                    </div>
                                </router-link>
                                <router-link to="/materials/plans" class="menu-link">
                                    <i class="fas fa-clipboard-list"></i>
                                    <div>
                                        <span>Material Planning</span>
                                        <small>Plan material needs</small>
                                    </div>
                                </router-link>
                                <router-link to="/materials/plans/generate" class="menu-link">
                                    <i class="fas fa-plus-circle"></i>
                                    <div>
                                        <span>Generate Material Plans</span>
                                        <small>Create planning schedules</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/requisitions/generate-from-material-plan" class="menu-link">
                                    <i class="fas fa-file-plus"></i>
                                    <div>
                                        <span>Generate PR from Plans</span>
                                        <small>Create purchase requests</small>
                                    </div>
                                </router-link>
                                <router-link to="/batches/expiry-dashboard" class="menu-link">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <span>Expiry Management</span>
                                        <small>Track expiration dates</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-dollar-sign"></i> Pricing</h3>
                            <div class="menu-links">
                                <router-link to="/item-prices" class="menu-link">
                                    <i class="fas fa-tags"></i>
                                    <div>
                                        <span>Item Prices</span>
                                        <small>Manage pricing</small>
                                    </div>
                                </router-link>
                                <router-link to="/price-comparison" class="menu-link">
                                    <i class="fas fa-balance-scale"></i>
                                    <div>
                                        <span>Price Comparison</span>
                                        <small>Compare prices</small>
                                    </div>
                                </router-link>
                                <router-link to="/item-prices-management" class="menu-link">
                                    <i class="fas fa-tags"></i>
                                    <div>
                                        <span>Item Prices Management</span>
                                        <small>Advanced price management</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Management Mega Menu -->
                    <div v-if="activeMegaMenu === 'stockManagement'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-boxes"></i> Stock Overview</h3>
                            <div class="menu-links">
                                <router-link to="/item-stocks" class="menu-link">
                                    <i class="fas fa-box"></i>
                                    <div>
                                        <span>Inventory Stock</span>
                                        <small>Current stock levels</small>
                                    </div>
                                </router-link>
                                <router-link to="/item-stocks/warehouse" class="menu-link">
                                    <i class="fas fa-warehouse"></i>
                                    <div>
                                        <span>Warehouse Stock</span>
                                        <small>Stock by warehouse</small>
                                    </div>
                                </router-link>
                                <router-link to="/item-stocks/negative" class="menu-link">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <span>Negative Stocks</span>
                                        <small>Items with negative balance</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-exchange-alt"></i> Stock Operations</h3>
                            <div class="menu-links">
                                <router-link to="/item-stocks/transfer" class="menu-link">
                                    <i class="fas fa-exchange-alt"></i>
                                    <div>
                                        <span>Stock Transfer</span>
                                        <small>Move stock between locations</small>
                                    </div>
                                </router-link>
                                <router-link to="/item-stocks/adjust" class="menu-link">
                                    <i class="fas fa-sliders-h"></i>
                                    <div>
                                        <span>Stock Adjustment</span>
                                        <small>Adjust inventory levels</small>
                                    </div>
                                </router-link>
                                <router-link to="/item-stocks/reserve" class="menu-link">
                                    <i class="fas fa-lock"></i>
                                    <div>
                                        <span>Stock Reservation</span>
                                        <small>Reserve items for orders</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-clipboard-check"></i> Transactions & Counting</h3>
                            <div class="menu-links">
                                <router-link to="/stock-transactions" class="menu-link">
                                    <i class="fas fa-random"></i>
                                    <div>
                                        <span>Transactions</span>
                                        <small>Stock movement history</small>
                                    </div>
                                </router-link>
                                <router-link to="/stock-adjustments" class="menu-link">
                                    <i class="fas fa-sliders-h"></i>
                                    <div>
                                        <span>Adjustments</span>
                                        <small>Adjustment records</small>
                                    </div>
                                </router-link>
                                <router-link to="/cycle-counts" class="menu-link">
                                    <i class="fas fa-clipboard-check"></i>
                                    <div>
                                        <span>Cycle Counting</span>
                                        <small>Physical count schedules</small>
                                    </div>
                                </router-link>
                                <router-link to="/cycle-counts/generate" class="menu-link">
                                    <i class="fas fa-tasks"></i>
                                    <div>
                                        <span>Generate Count Tasks</span>
                                        <small>Create counting schedules</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Purchasing Mega Menu -->
                    <div v-if="activeMegaMenu === 'purchasing'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-users"></i> Vendors</h3>
                            <div class="menu-links">
                                <router-link to="/purchasing/vendors" class="menu-link">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <span>Vendor Management</span>
                                        <small>Manage suppliers</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/vendor-performance" class="menu-link">
                                    <i class="fas fa-star"></i>
                                    <div>
                                        <span>Performance</span>
                                        <small>Vendor evaluation</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/evaluations" class="menu-link">
                                    <i class="fas fa-star"></i>
                                    <div>
                                        <span>Vendor Evaluations</span>
                                        <small>Detailed assessments</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/evaluation-dashboard" class="menu-link">
                                    <i class="fa-solid fa-gauge-high"></i>
                                    <div>
                                        <span>Evaluation Dashboard</span>
                                        <small>Performance overview</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-file-alt"></i> Requisitions & RFQ</h3>
                            <div class="menu-links">
                                <router-link to="/purchasing/requisitions" class="menu-link">
                                    <i class="fas fa-file-alt"></i>
                                    <div>
                                        <span>Requisitions</span>
                                        <small>Purchase requests</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/requisitions/approvals" class="menu-link">
                                    <i class="fas fa-check-circle"></i>
                                    <div>
                                        <span>PR Approvals</span>
                                        <small>Approve purchase requests</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/requisitions/to-rfq" class="menu-link">
                                    <i class="fas fa-exchange-alt"></i>
                                    <div>
                                        <span>PR to RFQ</span>
                                        <small>Convert to RFQ</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/rfqs" class="menu-link">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <div>
                                        <span>RFQs</span>
                                        <small>Request for quotations</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-file-invoice-dollar"></i> Quotations & Orders</h3>
                            <div class="menu-links">
                                <router-link to="/purchasing/quotations" class="menu-link">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <div>
                                        <span>Vendor Quotations</span>
                                        <small>Supplier quotes</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/quotations/compare" class="menu-link">
                                    <i class="fas fa-balance-scale"></i>
                                    <div>
                                        <span>Compare Quotations</span>
                                        <small>Side-by-side comparison</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/orders" class="menu-link">
                                    <i class="fas fa-clipboard-list"></i>
                                    <div>
                                        <span>Purchase Orders</span>
                                        <small>Manage POs</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/po-status" class="menu-link">
                                    <i class="fas fa-clipboard-check"></i>
                                    <div>
                                        <span>PO Status</span>
                                        <small>Track order status</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-truck-loading"></i> Receipts & Analytics</h3>
                            <div class="menu-links">
                                <router-link to="/purchasing/goods-receipts" class="menu-link">
                                    <i class="fas fa-truck-loading"></i>
                                    <div>
                                        <span>Goods Receipts</span>
                                        <small>Receive goods</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/goods-receipts/dashboard" class="menu-link">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <div>
                                        <span>Receipts Dashboard</span>
                                        <small>Receipt tracking</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/vendor-invoices" class="menu-link">
                                    <i class="fas fa-file-invoice"></i>
                                    <div>
                                        <span>Vendor Invoices</span>
                                        <small>Supplier billing</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/contracts" class="menu-link">
                                    <i class="fas fa-file-contract"></i>
                                    <div>
                                        <span>Vendor Contracts</span>
                                        <small>Contract management</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/contracts/expiry-dashboard" class="menu-link">
                                    <i class="fas fa-chart-line"></i>
                                    <div>
                                        <span>Contract Expiry Dashboard</span>
                                        <small>Contract renewals</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-chart-pie"></i> Analytics</h3>
                            <div class="menu-links">
                                <router-link to="/purchasing/dashboard" class="menu-link">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <div>
                                        <span>Dashboard</span>
                                        <small>Overview metrics</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/spend-analysis" class="menu-link">
                                    <i class="fas fa-chart-pie"></i>
                                    <div>
                                        <span>Spend Analysis</span>
                                        <small>Analyze spending</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/price-trend" class="menu-link">
                                    <i class="fas fa-chart-line"></i>
                                    <div>
                                        <span>Price Trends</span>
                                        <small>Historical pricing</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Sales Mega Menu -->
                    <div v-if="activeMegaMenu === 'sales'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-users"></i> Customers</h3>
                            <div class="menu-links">
                                <router-link to="/sales/customers" class="menu-link">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <span>Customer Management</span>
                                        <small>Manage customers</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/quotations" class="menu-link">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <div>
                                        <span>Quotations</span>
                                        <small>Sales quotes</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-shopping-cart"></i> Orders & Delivery</h3>
                            <div class="menu-links">
                                <router-link to="/sales/orders" class="menu-link">
                                    <i class="fas fa-file-signature"></i>
                                    <div>
                                        <span>Sales Orders</span>
                                        <small>Manage orders</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/deliveries" class="menu-link">
                                    <i class="fas fa-truck"></i>
                                    <div>
                                        <span>Deliveries</span>
                                        <small>Track deliveries</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/invoices" class="menu-link">
                                    <i class="fas fa-file-invoice"></i>
                                    <div>
                                        <span>Invoices</span>
                                        <small>Billing & invoices</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/returns" class="menu-link">
                                    <i class="fas fa-undo"></i>
                                    <div>
                                        <span>Returns</span>
                                        <small>Handle returns</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-chart-line"></i> Forecasting</h3>
                            <div class="menu-links">
                                <router-link to="/sales/forecasts" class="menu-link">
                                    <i class="fas fa-chart-line"></i>
                                    <div>
                                        <span>Sales Forecasts</span>
                                        <small>Predict sales</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/forecasts/dashboard" class="menu-link">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <div>
                                        <span>Forecast Dashboard</span>
                                        <small>Forecast analytics</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/forecasts/volatility-dashboard" class="menu-link">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <span>Volatility Monitor</span>
                                        <small>Monitor forecast changes</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/forecasts/trend-analysis" class="menu-link">
                                    <i class="fas fa-chart-area"></i>
                                    <div>
                                        <span>Trend Analysis</span>
                                        <small>Detailed trend analysis</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/forecasts/consolidated" class="menu-link">
                                    <i class="fas fa-table"></i>
                                    <div>
                                        <span>Consolidated View</span>
                                        <small>Overall forecasting</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/forecasts/import" class="menu-link">
                                    <i class="fas fa-file-import"></i>
                                    <div>
                                        <span>Import Forecast</span>
                                        <small>Bulk import</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-bullseye"></i> Analysis</h3>
                            <div class="menu-links">
                                <router-link to="/sales/forecasts/accuracy" class="menu-link">
                                    <i class="fas fa-bullseye"></i>
                                    <div>
                                        <span>Accuracy Analysis</span>
                                        <small>Forecast accuracy</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/forecasts/update-actuals" class="menu-link">
                                    <i class="fas fa-sync-alt"></i>
                                    <div>
                                        <span>Update Actuals</span>
                                        <small>Update actual data</small>
                                    </div>
                                </router-link>
                                <router-link to="/sales/forecasts/history" class="menu-link">
                                    <i class="fas fa-history"></i>
                                    <div>
                                        <span>Version History</span>
                                        <small>Historical versions</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Manufacturing Mega Menu -->
                    <div v-if="activeMegaMenu === 'manufacturing'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-clipboard-list"></i> Planning</h3>
                            <div class="menu-links">
                                <router-link to="/manufacturing/boms" class="menu-link">
                                    <i class="fas fa-clipboard-list"></i>
                                    <div>
                                        <span>Bill of Materials</span>
                                        <small>Product recipes</small>
                                    </div>
                                </router-link>
                                <router-link to="/manufacturing/routings" class="menu-link">
                                    <i class="fas fa-project-diagram"></i>
                                    <div>
                                        <span>Routing</span>
                                        <small>Production flow</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-cogs"></i> Production</h3>
                            <div class="menu-links">
                                <router-link to="/manufacturing/work-centers" class="menu-link">
                                    <i class="fas fa-industry"></i>
                                    <div>
                                        <span>Work Centers</span>
                                        <small>Production areas</small>
                                    </div>
                                </router-link>
                                <router-link to="/manufacturing/work-orders" class="menu-link">
                                    <i class="fas fa-clipboard-list"></i>
                                    <div>
                                        <span>Work Orders</span>
                                        <small>Production tasks</small>
                                    </div>
                                </router-link>
                                <router-link to="/manufacturing/production-orders" class="menu-link">
                                    <i class="fas fa-cogs"></i>
                                    <div>
                                        <span>Production Orders</span>
                                        <small>Manufacturing jobs</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Quality Management Mega Menu -->
                    <div v-if="activeMegaMenu === 'quality'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-check-circle"></i> Quality Control</h3>
                            <div class="menu-links">
                                <router-link to="/quality-inspections" class="menu-link">
                                    <i class="fas fa-clipboard-check"></i>
                                    <div>
                                        <span>Inspections</span>
                                        <small>Quality control</small>
                                    </div>
                                </router-link>
                                <router-link to="/quality-parameters/create" class="menu-link">
                                    <i class="fas fa-sliders-h"></i>
                                    <div>
                                        <span>Parameters</span>
                                        <small>Quality standards</small>
                                    </div>
                                </router-link>
                                <router-link to="/dashboard" class="menu-link">
                                    <i class="fas fa-chart-line"></i>
                                    <div>
                                        <span>Quality Dashboard</span>
                                        <small>Quality metrics</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Accounting Mega Menu -->
                    <div v-if="activeMegaMenu === 'accounting'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-calculator"></i> Financial Management</h3>
                            <div class="menu-links">
                                <router-link to="/currency-rates" class="menu-link">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>
                                        <span>Exchange Rates</span>
                                        <small>Currency exchange rates</small>
                                    </div>
                                </router-link>
                                <router-link to="/currency-converter" class="menu-link">
                                    <i class="fas fa-exchange-alt"></i>
                                    <div>
                                        <span>Currency Converter</span>
                                        <small>Convert currencies</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Reports Mega Menu -->
                    <div v-if="activeMegaMenu === 'reports'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-boxes"></i> Inventory Reports</h3>
                            <div class="menu-links">
                                <router-link to="/reports/stock" class="menu-link">
                                    <i class="fas fa-boxes"></i>
                                    <div>
                                        <span>Stock Report</span>
                                        <small>Current inventory</small>
                                    </div>
                                </router-link>
                                <router-link to="/reports/movement" class="menu-link">
                                    <i class="fas fa-chart-line"></i>
                                    <div>
                                        <span>Movement Report</span>
                                        <small>Stock movements</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                        <div class="menu-section">
                            <h3><i class="fas fa-chart-pie"></i> Business Reports</h3>
                            <div class="menu-links">
                                <router-link to="/reports/sales" class="menu-link">
                                    <i class="fas fa-chart-pie"></i>
                                    <div>
                                        <span>Sales Report</span>
                                        <small>Sales analytics</small>
                                    </div>
                                </router-link>
                                <router-link to="/purchasing/spend-analysis" class="menu-link">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>
                                        <span>Purchase Analysis</span>
                                        <small>Spending reports</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Mega Menu -->
                    <div v-if="activeMegaMenu === 'admin'" class="mega-menu-content">
                        <div class="menu-section">
                            <h3><i class="fas fa-user-shield"></i> System Administration</h3>
                            <div class="menu-links">
                                <router-link to="/admin/users" class="menu-link">
                                    <i class="fas fa-users-cog"></i>
                                    <div>
                                        <span>User Management</span>
                                        <small>Manage system users</small>
                                    </div>
                                </router-link>
                                <router-link to="/admin/settings" class="menu-link">
                                    <i class="fas fa-cogs"></i>
                                    <div>
                                        <span>System Settings</span>
                                        <small>Configuration management</small>
                                    </div>
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Notifications Dropdown -->
            <Transition name="slide-down">
                <div v-if="showNotifications" class="notifications-dropdown">
                    <div class="notifications-header">
                        <h3>Notifications</h3>
                        <button @click="markAllRead" class="mark-all-btn">Mark all read</button>
                    </div>
                    <div class="notifications-list">
                        <div v-for="notification in notifications" :key="notification.id" class="notification-item">
                            <div class="notification-icon" :class="notification.type">
                                <i :class="notification.icon"></i>
                            </div>
                            <div class="notification-content">
                                <p class="notification-title">{{ notification.title }}</p>
                                <p class="notification-time">{{ notification.time }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- User Dropdown -->
            <Transition name="slide-down">
                <div v-if="userMenuOpen" class="user-dropdown">
                    <div class="user-profile">
                        <div class="user-avatar-large">
                            <span>{{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}</span>
                        </div>
                        <div class="user-details">
                            <h4>{{ user.name || 'John Doe' }}</h4>
                            <p>{{ user.email || 'john@company.com' }}</p>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="user-menu-items">
                        <div class="dropdown-item" @click="navigateToProfile">
                            <i class="fas fa-user"></i>
                            <span>Profile Settings</span>
                        </div>
                        <div class="dropdown-item" @click="navigateToSettings">
                            <i class="fas fa-cog"></i>
                            <span>System Settings</span>
                        </div>
                        <div class="dropdown-item">
                            <i class="fas fa-bell"></i>
                            <span>Notification Settings</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-item logout-item" @click="logout">
                            <div class="logout-icon">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <span>Logout</span>
                            <i class="fas fa-arrow-right logout-arrow"></i>
                        </div>
                    </div>
                </div>
            </Transition>
        </nav>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb-section">
                <div class="breadcrumb">
                    <router-link to="/dashboard" class="breadcrumb-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </router-link>
                    <i class="fas fa-chevron-right breadcrumb-separator"></i>
                    <span class="breadcrumb-current">{{ pageTitle }}</span>
                </div>
                <div class="page-actions">
                    <button class="action-button primary" @click="quickCreateItem">
                        <i class="fas fa-plus"></i>
                        <span>Quick Add</span>
                    </button>
                    <button class="action-button secondary" @click="showQuickActions = !showQuickActions">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">{{ pageTitle }}</h1>
                    <p class="page-subtitle">Manage your {{ pageTitle.toLowerCase() }} efficiently</p>
                </div>
                
                <!-- Quick Stats Cards -->
                <div class="quick-stats" v-if="$route.name === 'Dashboard'">
                    <div class="stat-card" v-for="(stat, index) in quickStats" :key="index">
                        <div class="stat-icon" :style="{ background: stat.gradient }">
                            <i :class="stat.icon"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ stat.value }}</h3>
                            <p>{{ stat.label }}</p>
                            <div class="stat-trend" :class="stat.trend">
                                <i :class="stat.trend === 'up' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
                                <span>{{ stat.change }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <router-view />
            </div>
        </main>

        <!-- Floating Action Menu -->
        <div class="floating-actions" :class="{ active: showQuickActions }">
            <button class="fab-main" @click="showQuickActions = !showQuickActions">
                <i class="fas fa-plus" :class="{ rotated: showQuickActions }"></i>
            </button>
            
            <Transition name="scale-stagger">
                <div v-if="showQuickActions" class="fab-menu">
                    <button class="fab-item" @click="quickCreateItem" title="Add Item">
                        <i class="fas fa-box"></i>
                        <span>Add Item</span>
                    </button>
                    <button class="fab-item" @click="quickCreateOrder" title="New Order">
                        <i class="fas fa-shopping-cart"></i>
                        <span>New Order</span>
                    </button>
                    <button class="fab-item" @click="quickCreateCustomer" title="Add Customer">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Customer</span>
                    </button>
                    <button class="fab-item" @click="viewAnalytics" title="Analytics">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
                    </button>
                </div>
            </Transition>
        </div>

        <!-- Loading Overlay -->
        <Transition name="fade">
            <div v-if="isLoading" class="loading-overlay">
                <div class="loading-content">
                    <div class="loading-spinner"></div>
                    <p>Processing your request...</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "axios";

export default {
    name: "AppLayout",
    setup() {
        const router = useRouter();
        const route = useRoute();
        
        // Reactive states
        const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));
        const searchQuery = ref("");
        const searchFocused = ref(false);
        const showNotifications = ref(false);
        const showQuickActions = ref(false);
        const isDarkMode = ref(localStorage.getItem("darkMode") === "true");
        const isLoading = ref(false);
        const userMenuOpen = ref(false);
        const activeMegaMenu = ref(null);
        const megaMenuTimeout = ref(null);
        
        // Sample data
        const notificationCount = ref(3);
        const notifications = ref([
            {
                id: 1,
                title: "New order received from ABC Corp",
                time: "5 minutes ago",
                icon: "fas fa-shopping-cart",
                type: "success"
            },
            {
                id: 2,
                title: "Low stock alert for Product X",
                time: "1 hour ago",
                icon: "fas fa-exclamation-triangle",
                type: "warning"
            },
            {
                id: 3,
                title: "Monthly report is ready",
                time: "2 hours ago",
                icon: "fas fa-file-alt",
                type: "info"
            }
        ]);

        const quickStats = ref([
            {
                value: "1,234",
                label: "Total Items",
                icon: "fas fa-box",
                gradient: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                trend: "up",
                change: "+12%"
            },
            {
                value: "$45,678",
                label: "Monthly Sales",
                icon: "fas fa-chart-line",
                gradient: "linear-gradient(135deg, #f093fb 0%, #f5576c 100%)",
                trend: "up",
                change: "+8.5%"
            },
            {
                value: "89",
                label: "Pending Orders",
                icon: "fas fa-clock",
                gradient: "linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)",
                trend: "down",
                change: "-5%"
            },
            {
                value: "96%",
                label: "System Uptime",
                icon: "fas fa-server",
                gradient: "linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)",
                trend: "up",
                change: "+2%"
            }
        ]);

        const pageTitle = computed(() => {
        const titleMap = {
            // Dashboard
            "Dashboard": "Dashboard",
            
            // Inventory Management
            "Items": "Items Management",
            "ItemDetail": "Item Details",
            "ItemCategories": "Item Categories",
            "ItemCategoriesEnhanced": "Enhanced Item Categories",
            "UnitOfMeasures": "Units of Measure",
            "UnitOfMeasureDetail": "Unit of Measure Details",
            "ItemPriceManagement": "Item Prices Management",
            
            // Stock Management
            "StockTransactions": "Stock Transactions",
            "CreateStockTransaction": "Create Stock Transaction",
            "StockTransactionDetail": "Stock Transaction Details",
            "ItemMovementHistory": "Item Movement History",
            "StockTransfer": "Stock Transfer",
            "ItemStocks": "Inventory Stock",
            "ItemStockDetail": "Item Stock Details",
            "WarehouseStock": "Warehouse Stock",
            "StockAdjustment": "Stock Adjustment",
            "StockReservation": "Stock Reservation",
            "NegativeStocks": "Negative Stocks",
            "StockAdjustments": "Stock Adjustments",
            "CreateStockAdjustment": "Create Stock Adjustment",
            "StockAdjustmentDetail": "Stock Adjustment Details",
            "EditStockAdjustment": "Edit Stock Adjustment",
            "ApproveStockAdjustment": "Approve Stock Adjustment",
            
            // Cycle Counting
            "CycleCountList": "Cycle Counting",
            "CreateCycleCount": "Create Cycle Count",
            "GenerateCycleCounts": "Generate Count Tasks",
            "CycleCountDetail": "Cycle Count Details",
            "EditCycleCount": "Edit Cycle Count",
            "CycleCountApproval": "Cycle Count Approval",
            
            // Batch Management
            "ItemBatches": "Item Batches",
            "CreateBatch": "Create Batch",
            "BatchDetail": "Batch Details",
            "EditBatch": "Edit Batch",
            "ExpiryDashboard": "Expiry Management",
            
            // Customers
            "customers.index": "Customer Management",
            "customers.create": "Add New Customer",
            "customers.show": "Customer Details",
            "customers.edit": "Edit Customer",
            
            // Sales Quotations
            "SalesQuotations": "Sales Quotations",
            "CreateSalesQuotation": "Create Sales Quotation",
            "SalesQuotationDetail": "Sales Quotation Details",
            "EditSalesQuotation": "Edit Sales Quotation",
            "PrintSalesQuotation": "Print Sales Quotation",
            
            // Sales Forecasts
            "SalesForecastsList": "Sales Forecasts",
            "SalesForecastDetail": "Sales Forecast Details",
            "SalesForecastAnalytics": "Sales Forecast Analytics",
            "CreateSalesForecast": "Create Sales Forecast",
            "EditSalesForecast": "Edit Sales Forecast",
            "ConsolidatedForecastView": "Consolidated Forecast View",
            "ImportForecastForm": "Import Forecast",
            "ForecastAccuracyAnalysis": "Forecast Accuracy Analysis",
            "ForecastDashboard": "Forecast Dashboard",
            "UpdateActualsPage": "Update Actuals",
            "ForecastHistoryView": "Forecast Version History",
            "ForecastVolatilityDashboard": "Forecast Volatility Monitor",
            "ForecastTrendAnalysis": "Forecast Trend Analysis",
            
            // Sales Orders
            "SalesOrders": "Sales Orders",
            "CreateSalesOrder": "Create Sales Order",
            "SalesOrderDetail": "Sales Order Details",
            "EditSalesOrder": "Edit Sales Order",
            "CreateOrderFromQuotation": "Create Order from Quotation",
            "PrintSalesOrder": "Print Sales Order",
            
            // Sales Invoices
            "SalesInvoices": "Sales Invoices",
            "CreateSalesInvoice": "Create Sales Invoice",
            "CreateInvoiceFromDelivery": "Create Invoice from Delivery",
            "SalesInvoiceDetail": "Sales Invoice Details",
            "EditSalesInvoice": "Edit Sales Invoice",
            "SalesInvoicePayment": "Sales Invoice Payment",
            "PrintSalesInvoice": "Print Sales Invoice",
            
            // Sales Deliveries
            "DeliveryList": "Deliveries",
            "CreateDelivery": "Create Delivery",
            "DeliveryDetail": "Delivery Details",
            "EditDelivery": "Edit Delivery",
            "PrintDeliveryOrder": "Print Delivery Order",
            
            // Sales Returns
            "SalesReturns": "Sales Returns",
            "CreateSalesReturn": "Create Sales Return",
            "SalesReturnDetail": "Sales Return Details",
            "EditSalesReturn": "Edit Sales Return",
            
            // Manufacturing - BOM
            "BOMList": "Bill of Materials",
            "CreateBOM": "Create BOM",
            "BOMDetail": "BOM Details",
            "EditBOM": "Edit BOM",
            
            // Manufacturing - Routing
            "RoutingList": "Routing",
            "CreateRouting": "Create Routing",
            "RoutingDetail": "Routing Details",
            "EditRouting": "Edit Routing",
            
            // Manufacturing - Work Centers
            "WorkCentersList": "Work Centers",
            "CreateWorkCenter": "Create Work Center",
            "WorkCenterDetail": "Work Center Details",
            "EditWorkCenter": "Edit Work Center",
            "WorkCenterSchedule": "Work Center Schedule",
            
            // Manufacturing - Work Orders
            "WorkOrders": "Work Orders",
            "CreateWorkOrder": "Create Work Order",
            "WorkOrderDetail": "Work Order Details",
            "EditWorkOrder": "Edit Work Order",
            "WorkOrderOperation": "Work Order Operation",
            "ManufacturingDashboard": "Manufacturing Dashboard",
            
            // Manufacturing - Production Orders
            "ProductionOrders": "Production Orders",
            "CreateProductionOrder": "Create Production Order",
            "ProductionOrderDetail": "Production Order Details",
            "EditProductionOrder": "Edit Production Order",
            "AddProductionConsumption": "Add Production Consumption",
            "EditProductionConsumption": "Edit Production Consumption",
            "CompleteProductionOrder": "Complete Production Order",
            
            // Purchasing - Vendors
            "VendorList": "Vendor Management",
            "VendorCreate": "Add New Vendor",
            "VendorDetail": "Vendor Details",
            "VendorEdit": "Edit Vendor",
            
            // Purchasing - Requisitions
            "PurchaseRequisitionList": "Purchase Requisitions",
            "CreatePurchaseRequisition": "Create Purchase Requisition",
            "PurchaseRequisitionDetail": "Purchase Requisition Details",
            "EditPurchaseRequisition": "Edit Purchase Requisition",
            "ApprovePurchaseRequisition": "Approve Purchase Requisition",
            "ConvertToRFQ": "Convert to RFQ",
            "PRApprovalList": "PR Approvals",
            "PRToRFQList": "PR to RFQ",
            
            // Purchasing - RFQ
            "RFQList": "Request for Quotations",
            "CreateRFQ": "Create RFQ",
            "RFQDetail": "RFQ Details",
            "EditRFQ": "Edit RFQ",
            "SendRFQ": "Send RFQ",
            "CompareRFQ": "Compare RFQ",
            
            // Purchasing - Vendor Quotations
            "VendorQuotations": "Vendor Quotations",
            "CreateVendorQuotation": "Create Vendor Quotation",
            "VendorQuotationDetail": "Vendor Quotation Details",
            "EditVendorQuotation": "Edit Vendor Quotation",
            "CompareVendorQuotations": "Compare Quotations",
            "CreatePOFromQuotation": "Create PO from Quotation",
            
            // Purchasing - Purchase Orders
            "PurchaseOrders": "Purchase Orders",
            "CreatePurchaseOrder": "Create Purchase Order",
            "PurchaseOrderDetail": "Purchase Order Details",
            "EditPurchaseOrder": "Edit Purchase Order",
            "PurchaseOrderTrack": "Track Purchase Order",
            
            // Purchasing - Goods Receipts
            "GoodsReceiptList": "Goods Receipts",
            "CreateGoodsReceipt": "Create Goods Receipt",
            "PendingReceiptsDashboard": "Receipts Dashboard",
            "GoodsReceiptDetail": "Goods Receipt Details",
            "EditGoodsReceipt": "Edit Goods Receipt",
            "ConfirmGoodsReceipt": "Confirm Goods Receipt",
            
            // Purchasing - Vendor Invoices
            "VendorInvoiceList": "Vendor Invoices",
            "VendorInvoiceCreate": "Create Vendor Invoice",
            "VendorInvoiceDetail": "Vendor Invoice Details",
            "VendorInvoiceEdit": "Edit Vendor Invoice",
            "VendorInvoiceApproval": "Vendor Invoice Approval",
            "VendorInvoicePayment": "Vendor Invoice Payment",
            
            // Warehouses
            "Warehouses": "Warehouses",
            "WarehouseDetail": "Warehouse Details",
            "WarehouseZones": "Warehouse Zones",
            "WarehouseLocations": "Warehouse Locations",
            "LocationInventory": "Location Inventory",
            
            // Material Planning
            "MaterialPlans": "Material Planning",
            "MaterialPlanDetail": "Material Plan Details",
            "MaterialPlanGeneration": "Generate Material Plans",
            "PRGenerationFromMaterialPlan": "Generate PR from Material Plan",
            
            // Accounting
            "CurrencyRates": "Exchange Rates",
            "CreateCurrencyRate": "Create Exchange Rate",
            "CurrencyRateDetail": "Exchange Rate Details",
            "EditCurrencyRate": "Edit Exchange Rate",
            "CurrencyConverter": "Currency Converter",
            
            // Quality Management
            "quality-inspections": "Quality Inspections",
            "quality-inspections-create": "Create Quality Inspection",
            "quality-inspection-detail": "Quality Inspection Details",
            "quality-inspection-edit": "Edit Quality Inspection",
            "quality-parameters-create": "Create Quality Parameters",
            "quality-parameters-edit": "Edit Quality Parameters",
            "quality-dashboard": "Quality Dashboard",
            
            // Administration
            "AdminDashboard": "Admin Dashboard",
            "SystemSettings": "System Settings",
            "UserList": "User Management",
            
            // Legacy routes yang sudah ada sebelumnya
            "Customers": "Customers",
            "Users": "User Management"
        };
        
        return titleMap[route.name] || "Dashboard";
    });

        // Methods
        const showMegaMenu = (menu) => {
            clearTimeout(megaMenuTimeout.value);
            activeMegaMenu.value = menu;
        };

        const hideMegaMenu = () => {
            megaMenuTimeout.value = setTimeout(() => {
                activeMegaMenu.value = null;
            }, 100);
        };

        const keepMegaMenuOpen = () => {
            clearTimeout(megaMenuTimeout.value);
        };

        const handleSearchBlur = () => {
            setTimeout(() => {
                searchFocused.value = false;
            }, 200);
        };

        const toggleUserMenu = () => {
            userMenuOpen.value = !userMenuOpen.value;
        };

        const toggleTheme = () => {
            isDarkMode.value = !isDarkMode.value;
            localStorage.setItem("darkMode", isDarkMode.value);
        };

        const markAllRead = () => {
            notificationCount.value = 0;
            showNotifications.value = false;
        };

        const logout = async () => {
            isLoading.value = true;
            try {
                await axios.post("/api/auth/logout");
            } catch (error) {
                console.error("Logout error:", error);
            } finally {
                localStorage.removeItem("token");
                localStorage.removeItem("user");
                axios.defaults.headers.common["Authorization"] = "";
                isLoading.value = false;
                router.push("/login");
            }
        };

        // Quick actions
        const quickCreateItem = () => {
            router.push("/items/create");
            showQuickActions.value = false;
        };

        const quickCreateOrder = () => {
            router.push("/sales/orders/create");
            showQuickActions.value = false;
        };

        const quickCreateCustomer = () => {
            router.push("/sales/customers/create");
            showQuickActions.value = false;
        };

        const viewAnalytics = () => {
            router.push("/analytics");
            showQuickActions.value = false;
        };

        const navigateToProfile = () => {
            router.push("/profile");
            userMenuOpen.value = false;
        };

        const navigateToSettings = () => {
            router.push("/settings");
            userMenuOpen.value = false;
        };

        // Close dropdowns when clicking outside
        onMounted(() => {
            document.addEventListener("click", (event) => {
                const navContainer = document.querySelector(".nav-container");
                const megaMenu = document.querySelector(".mega-menu");
                const notifications = document.querySelector(".notifications-dropdown");
                const userDropdown = document.querySelector(".user-dropdown");
                
                if (navContainer && !navContainer.contains(event.target) && 
                    megaMenu && !megaMenu.contains(event.target)) {
                    activeMegaMenu.value = null;
                }
                
                if (notifications && !notifications.contains(event.target) &&
                    !event.target.closest('.action-btn')) {
                    showNotifications.value = false;
                }
                
                if (userDropdown && !userDropdown.contains(event.target) &&
                    !event.target.closest('.user-section')) {
                    userMenuOpen.value = false;
                }
            });

            // Apply dark mode if saved
            if (isDarkMode.value) {
                document.documentElement.classList.add("dark");
            }
        });

        return {
            user,
            searchQuery,
            searchFocused,
            showNotifications,
            showQuickActions,
            isDarkMode,
            isLoading,
            userMenuOpen,
            activeMegaMenu,
            notificationCount,
            notifications,
            quickStats,
            pageTitle,
            showMegaMenu,
            hideMegaMenu,
            keepMegaMenuOpen,
            handleSearchBlur,
            toggleUserMenu,
            toggleTheme,
            markAllRead,
            logout,
            quickCreateItem,
            quickCreateOrder,
            quickCreateCustomer,
            viewAnalytics,
            navigateToProfile,
            navigateToSettings,
        };
    },
};
</script>

<style scoped>
/* CSS Variables */
:root {
    --primary-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    --secondary-gradient: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
    --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --nav-height: 80px;
    --content-padding: 2rem;
    --border-radius: 16px;
    --border-radius-lg: 24px;
    --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    --box-shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.15);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    /* Light theme colors */
    --bg-primary: #ffffff;
    --bg-secondary: #f8fafc;
    --bg-tertiary: #f1f5f9;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --border-color: #e2e8f0;
    --nav-bg: #ffffff;
    --card-bg: #ffffff;
}

.dark {
    /* Dark theme colors */
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --border-color: #334155;
    --nav-bg: rgba(15, 23, 42, 0.95);
    --card-bg: #1e293b;
}

/* Global Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
}

/* App Container */
.app-container {
    min-height: 100vh;
    background: var(--bg-secondary);
    position: relative;
    transition: var(--transition);
}

/* Animated Background */
.background-animation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%);
    overflow: hidden;
}

.dark .background-animation {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
}

.floating-orbs {
    position: absolute;
    width: 100%;
    height: 100%;
}

.orb {
    position: absolute;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.05);
    animation: float 20s infinite ease-in-out;
}

.dark .orb {
    background: rgba(99, 102, 241, 0.1);
}

.orb-1 {
    width: 200px;
    height: 200px;
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}

.orb-2 {
    width: 300px;
    height: 300px;
    top: 50%;
    right: 10%;
    animation-delay: 5s;
}

.orb-3 {
    width: 150px;
    height: 150px;
    bottom: 20%;
    left: 60%;
    animation-delay: 10s;
}

.orb-4 {
    width: 250px;
    height: 250px;
    top: 30%;
    left: 70%;
    animation-delay: 15s;
}

.orb-5 {
    width: 180px;
    height: 180px;
    bottom: 10%;
    right: 30%;
    animation-delay: 20s;
}

@keyframes float {
    0%, 100% { 
        transform: translateY(0px) translateX(0px) rotate(0deg); 
        opacity: 0.3; 
    }
    25% { 
        transform: translateY(-30px) translateX(20px) rotate(90deg); 
        opacity: 0.1; 
    }
    50% { 
        transform: translateY(-20px) translateX(-20px) rotate(180deg); 
        opacity: 0.2; 
    }
    75% { 
        transform: translateY(20px) translateX(30px) rotate(270deg); 
        opacity: 0.15; 
    }
}

/* Top Navigation */
.top-navigation {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: var(--nav-bg);
    backdrop-filter: blur(20px);
    border-bottom: 2px solid var(--border-color);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.dark .top-navigation {
    background: rgba(15, 23, 42, 0.95);
    border-bottom: 2px solid var(--border-color);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.nav-container {
    max-width: none;
    margin: 0;
    padding: 0 1rem;
    height: var(--nav-height);
    display: grid;
    grid-template-columns: 280px 1fr auto;
    align-items: center;
    gap: 2rem;
    width: 100%;
}

/* Brand Section */
.brand-section {
    flex-shrink: 0;
    min-width: 200px;
}

.brand-logo {
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
}

.logo-icon {
    width: 48px;
    height: 48px;
    background: var(--primary-gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.brand-text h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.brand-text p {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin: 0;
}

/* Main Navigation */
.main-nav {
    display: flex;
    gap: 0.25rem;
    flex: 1;
    justify-content: flex-start;
    max-width: 800px;
    overflow-x: auto;
    padding: 0 1rem;
}

.nav-item {
    position: relative;
    flex-shrink: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.85rem;
    transition: var(--transition);
    cursor: pointer;
    white-space: nowrap;
}

.nav-link:hover {
    background: rgba(99, 102, 241, 0.1);
    color: var(--text-primary);
    transform: translateY(-2px);
}

.nav-link.active {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.nav-arrow {
    font-size: 0.7rem;
    transition: transform 0.3s ease;
}

.nav-item:hover .nav-arrow {
    transform: rotate(180deg);
}

/* Navigation Right Section */
.nav-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
    min-width: 280px;
}

/* Enhanced Search */
.search-container {
    position: relative;
}

.search-box {
    position: relative;
    transition: var(--transition);
}

.search-box.active {
    transform: scale(1.02);
}

.search-input {
    background: #ffffff;
    border: 2px solid var(--border-color);
    border-radius: 25px;
    padding: 0.75rem 1rem 0.75rem 3rem;
    width: 240px;
    transition: var(--transition);
    color: var(--text-primary);
    font-size: 0.9rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.dark .search-input {
    background: var(--card-bg);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.search-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    width: 280px;
}

.search-input::placeholder {
    color: var(--text-muted);
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.9rem;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #ffffff;
    border-radius: var(--border-radius);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    margin-top: 0.5rem;
    z-index: 200;
    border: 2px solid var(--border-color);
    max-height: 400px;
    overflow-y: auto;
}

.dark .search-results {
    background: #1e293b;
    border-color: #334155;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.search-category {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.search-category:last-child {
    border-bottom: none;
}

.search-category h4 {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.search-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-primary);
    margin: 0.25rem 0;
}

.search-item:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.action-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid var(--border-color);
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    font-size: 1rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.dark .action-btn {
    background: var(--card-bg);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.action-btn:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.notification-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: var(--secondary-gradient);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-3px); }
    60% { transform: translateY(-2px); }
}

/* User Section */
.user-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: #ffffff;
    border: 2px solid var(--border-color);
    border-radius: 25px;
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.dark .user-section {
    background: var(--card-bg);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.user-section:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    border: 3px solid white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dark .user-avatar {
    border: 3px solid var(--bg-secondary);
}

.user-info {
    display: flex;
    flex-direction: column;
    text-align: left;
}

.username {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
    line-height: 1.2;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.user-role {
    font-size: 0.75rem;
    color: var(--text-muted);
    line-height: 1.2;
    font-weight: 500;
}

.user-arrow {
    color: var(--text-muted);
    font-size: 0.75rem;
    transition: transform 0.3s ease;
}

.user-arrow.rotated {
    transform: rotate(180deg);
}

/* Mega Menu */
.mega-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #ffffff;
    border: 2px solid var(--border-color);
    border-radius: 0 0 var(--border-radius-lg) var(--border-radius-lg);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    z-index: 999;
}

.dark .mega-menu {
    background: #1e293b;
    border-color: #334155;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.mega-menu-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.menu-section h3 {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.menu-section h3 i {
    color: #6366f1;
}

.menu-links {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.menu-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    color: var(--text-primary);
    text-decoration: none;
    transition: var(--transition);
    border: 1px solid transparent;
}

.menu-link:hover {
    background: rgba(99, 102, 241, 0.05);
    border-color: rgba(99, 102, 241, 0.2);
    transform: translateX(8px);
}

.menu-link i {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 0.9rem;
}

.menu-link div span {
    font-weight: 500;
    display: block;
    margin-bottom: 0.25rem;
}

.menu-link div small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

/* Dropdowns */
.notifications-dropdown,
.user-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    background: #ffffff;
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    z-index: 200;
    min-width: 320px;
    max-height: 400px;
    overflow: hidden;
}

.dark .notifications-dropdown,
.dark .user-dropdown {
    background: #1e293b;
    border-color: #334155;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.notifications-header {
    padding: 1.5rem;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
}

.dark .notifications-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
}

.notifications-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.mark-all-btn {
    background: none;
    border: none;
    color: #6366f1;
    font-size: 0.85rem;
    cursor: pointer;
    font-weight: 500;
}

.mark-all-btn:hover {
    text-decoration: underline;
}

.notifications-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    transition: var(--transition);
    cursor: pointer;
    border-radius: 8px;
    margin: 0.25rem 0.5rem;
}

.notification-item:last-child {
    border-bottom: none;
    margin-bottom: 0.5rem;
}

.notification-item:hover {
    background: rgba(99, 102, 241, 0.05);
    transform: translateX(3px);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.9rem;
}

.notification-icon.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.notification-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.notification-icon.info {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.notification-time {
    color: var(--text-muted);
    font-size: 0.8rem;
}

/* User Dropdown */
.user-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow-lg);
    z-index: 200;
    min-width: 320px;
    max-height: 400px;
    overflow: hidden;
}

.user-profile {
    padding: 1.5rem;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
}

.dark .user-profile {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
}

.user-avatar-large {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.3rem;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    border: 4px solid white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dark .user-avatar-large {
    border: 4px solid var(--bg-secondary);
}

.user-details h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.user-details p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0;
}

.user-menu-items {
    padding: 1rem 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    color: var(--text-primary);
    transition: var(--transition);
    cursor: pointer;
    font-size: 0.9rem;
}

.dropdown-item.logout-item {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #fca5a5;
    border-radius: 8px;
    margin: 0.5rem;
    font-weight: 600;
    color: #dc2626;
    position: relative;
    transition: all 0.3s ease;
}

.dark .dropdown-item.logout-item {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}

.dropdown-item.logout-item:hover {
    background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);
    color: white;
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
}

.dark .dropdown-item.logout-item:hover {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border-color: #ef4444;
}

.logout-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(220, 38, 38, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    transition: all 0.3s ease;
}

.dropdown-item.logout-item:hover .logout-icon {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: scale(1.1);
}

.logout-arrow {
    margin-left: auto;
    opacity: 0.6;
    transition: all 0.3s ease;
}

.dropdown-item.logout-item:hover .logout-arrow {
    opacity: 1;
    transform: translateX(5px);
}

.dropdown-item:hover {
    background: rgba(99, 102, 241, 0.05);
    color: #6366f1;
}

.dropdown-divider {
    height: 1px;
    background: var(--border-color);
    margin: 0.5rem 0;
}

/* Main Content */
.main-content {
    padding-top: 2rem;
    min-height: calc(100vh - var(--nav-height));
}

/* Breadcrumb */
.breadcrumb-section {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 var(--content-padding);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.9rem;
    font-weight: 500;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    text-decoration: none;
    transition: var(--transition);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}

.breadcrumb-item:hover {
    color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
}

.breadcrumb-separator {
    font-size: 0.7rem;
    color: var(--text-muted);
    opacity: 0.6;
}

.breadcrumb-current {
    color: var(--text-primary);
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 6px;
}

.page-actions {
    display: flex;
    gap: 0.5rem;
}

.action-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.85rem;
    transition: var(--transition);
}

.action-button.primary {
    background: var(--primary-gradient);
    color: white;
}

.action-button.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.action-button.secondary {
    background: var(--card-bg);
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.action-button.secondary:hover {
    border-color: #6366f1;
    color: #6366f1;
}

/* Page Header */
.page-header {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 var(--content-padding);
    margin-bottom: 2rem;
}

.page-title-section {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    color: var(--text-muted);
    font-size: 1.1rem;
    margin: 0;
}

/* Quick Stats */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--box-shadow-lg);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.stat-content p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0 0 0.5rem 0;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.stat-trend.up {
    color: #10b981;
}

.stat-trend.down {
    color: #ef4444;
}

/* Content Area */
.content-area {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 var(--content-padding);
    min-height: 400px;
}

/* Floating Actions */
.floating-actions {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 100;
}

.fab-main {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--primary-gradient);
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 2;
}

.fab-main:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 15px 35px rgba(99, 102, 241, 0.6);
}

.fab-main i.rotated {
    transform: rotate(45deg);
}

.fab-menu {
    position: absolute;
    bottom: 80px;
    right: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    z-index: 1;
}

.fab-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    color: var(--text-primary);
    cursor: pointer;
    transition: var(--transition);
    box-shadow: var(--box-shadow);
    min-width: 180px;
    text-align: left;
    font-size: 0.9rem;
    font-weight: 500;
}

.fab-item:hover {
    transform: translateX(-8px);
    box-shadow: var(--box-shadow-lg);
    border-color: #6366f1;
    color: #6366f1;
}

.fab-item i {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(248, 250, 252, 0.95);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.dark .loading-overlay {
    background: rgba(15, 23, 42, 0.95);
}

.loading-content {
    text-align: center;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(99, 102, 241, 0.2);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s ease-in-out infinite;
    margin: 0 auto 1rem;
}

.loading-content p {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 1.1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Vue Transitions */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

.slide-down-enter-active, .slide-down-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: top;
}

.slide-down-enter-from, .slide-down-leave-to {
    opacity: 0;
    transform: translateY(-20px);
}

.scale-enter-active, .scale-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.scale-enter-from, .scale-leave-to {
    opacity: 0;
    transform: scale(0.8);
}

.scale-stagger-enter-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.scale-stagger-enter-from {
    opacity: 0;
    transform: scale(0.8) translateY(20px);
}

/* Responsive Design */
@media (max-width: 1400px) {
    .nav-container {
        grid-template-columns: 260px 1fr auto;
        padding: 0 1rem;
        gap: 1.5rem;
    }
    
    .main-nav {
        margin-left: 0.5rem;
    }
    
    .brand-text h2 {
        font-size: 1rem;
    }
    
    .brand-text p {
        font-size: 0.65rem;
    }
    
    .search-input {
        width: 200px;
    }
    
    .search-input:focus {
        width: 240px;
    }
}

@media (max-width: 1200px) {
    .nav-container {
        grid-template-columns: 240px 1fr auto;
        padding: 0 1rem;
        gap: 1rem;
    }
    
    .main-nav {
        margin-left: 0;
        gap: 0.25rem;
    }
    
    .logo-icon {
        width: 38px;
        height: 38px;
        font-size: 1rem;
    }
    
    .breadcrumb-section {
        padding: 1.5rem 1.5rem 1rem 1.5rem;
    }
    
    .page-header,
    .content-area {
        padding: 0 1.5rem 2rem 1.5rem;
    }
    
    .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .search-input {
        width: 180px;
    }
    
    .search-input:focus {
        width: 220px;
    }
    
    .nav-right {
        gap: 0.5rem;
    }
}

@media (max-width: 1024px) {
    .nav-container {
        grid-template-columns: 200px 1fr auto;
        gap: 1rem;
        justify-content: space-between;
    }
    
    .main-nav {
        display: none;
    }
    
    .brand-text p {
        display: none;
    }
    
    .search-input {
        width: 160px;
    }
    
    .search-input:focus {
        width: 200px;
    }
    
    .breadcrumb-section {
        padding: 1.5rem 1rem 1rem 1rem;
        margin-top: 0.5rem;
    }
    
    .mega-menu-content {
        grid-template-columns: 1fr;
        padding: 1.5rem;
    }
}

@media (max-width: 768px) {
    .nav-container {
        grid-template-columns: auto 1fr auto;
        padding: 0 0.75rem;
        gap: 0.5rem;
    }
    
    .brand-text {
        display: none;
    }
    
    .logo-icon {
        width: 36px;
        height: 36px;
        font-size: 0.95rem;
    }
    
    .user-info {
        display: none;
    }
    
    .search-input {
        width: 120px;
    }
    
    .search-input:focus {
        width: 160px;
    }
    
    .quick-actions {
        gap: 0.25rem;
    }
    
    .action-btn {
        width: 38px;
        height: 38px;
        font-size: 0.9rem;
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .breadcrumb-section {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
        padding: 1.5rem 1rem 1rem 1rem;
        margin-top: 0.5rem;
    }
    
    .page-header,
    .content-area {
        padding: 0 1rem 2rem 1rem;
    }
    
    .floating-actions {
        bottom: 1rem;
        right: 1rem;
    }
    
    .fab-main {
        width: 56px;
        height: 56px;
        font-size: 1.3rem;
    }
    
    .notifications-dropdown,
    .user-dropdown {
        min-width: 280px;
        right: -50px;
    }
}

@media (max-width: 480px) {
    .nav-container {
        grid-template-columns: auto 1fr auto;
        padding: 0 0.5rem;
        gap: 0.25rem;
    }
    
    .search-input {
        width: 100px;
    }
    
    .search-input:focus {
        width: 140px;
    }
    
    .user-section {
        padding: 0.375rem 0.75rem;
        gap: 0.5rem;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        font-size: 0.85rem;
    }
    
    .breadcrumb-section {
        padding: 1rem 0.5rem;
        margin-top: 0.5rem;
    }
    
    .page-header,
    .content-area {
        padding: 0 1rem 2rem 1rem;
    }
    
    .fab-item {
        min-width: 150px;
        padding: 0.75rem 1rem;
    }
    
    .notifications-dropdown,
    .user-dropdown {
        min-width: 260px;
        right: -80px;
    }
}

.dropdown-item:not(.logout-item) {
    border-radius: 8px;
    margin: 0.25rem 0.5rem;
}

.dropdown-item:not(.logout-item):hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    color: #6366f1;
    transform: translateX(3px);
}

.dropdown-item:not(.logout-item) i {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.dropdown-item:not(.logout-item):hover i {
    background: rgba(99, 102, 241, 0.2);
    transform: scale(1.1);
}
</style>