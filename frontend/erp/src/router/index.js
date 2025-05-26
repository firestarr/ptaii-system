// src/router/index.js
import { createRouter, createWebHistory } from "vue-router";
import Login from "../views/Login.vue";
import Register from "../views/Register.vue";
import AppLayout from "../layouts/AppLayout.vue";
import Dashboard from "../views/Dashboard.vue";
// Import components
import ItemsList from "../views/inventory/ItemsList.vue";
import ItemDetail from "../views/inventory/ItemDetail.vue";
import UnitOfMeasureList from "../views/inventory/UnitOfMeasureList.vue";
import UnitOfMeasureDetail from "../views/inventory/UnitOfMeasureDetail.vue";
// import WarehousesList from "../views/inventory/WarehousesList.vue";
// Tambahkan import untuk komponen harga
// import ItemPriceList from "../views/inventory/ItemPriceList.vue";
// import PriceComparison from "../views/inventory/PriceComparison.vue";
import ItemPriceManagement from "../views/inventory/ItemPriceManagement.vue";
//import StockTransactions from "../views/inventory/StockTransactions.vue";
import StockTransactionsList from '../views/inventory/StockTransactionsList.vue';
import StockTransactionForm from '../views/inventory/StockTransactionForm.vue';
import StockTransactionDetail from '../views/inventory/StockTransactionDetail.vue';
import StockAdjustmentList from "../views/inventory/StockAdjustmentList.vue";
import StockAdjustmentForm from "../views/inventory/StockAdjustmentForm.vue";
import StockAdjustmentDetail from "../views/inventory/StockAdjustmentDetail.vue";
import StockAdjustmentApproval from "../views/inventory/StockAdjustmentApproval.vue";
import ItemMovementHistory from '../views/inventory/ItemMovementHistory.vue';
import StockTransferForm from '../views/inventory/StockTransferForm.vue';
import ItemStockList from "../views/inventory/ItemStockList.vue";
import ItemStockDetail from "../views/inventory/ItemStockDetail.vue";
import WarehouseStock from "../views/inventory/WarehouseStock.vue";
import StockTransfer from "../views/inventory/StockTransfer.vue";
import StockAdjustment from "../views/inventory/StockAdjustment.vue";
import StockReservation from "../views/inventory/StockReservation.vue";
import NegativeStocks from "../views/inventory/NegativeStocks.vue";
import ItemCategories from "../views/inventory/ItemCategories.vue";
import ItemCategoriesEnhanced from "../views/inventory/ItemCategoriesEnhanced.vue";
import CycleCountList from "../views/inventory/CycleCountList.vue";
import CycleCountForm from "../views/inventory/CycleCountForm.vue";
import CycleCountDetail from "../views/inventory/CycleCountDetail.vue";
import CycleCountApproval from "../views/inventory/CycleCountApproval.vue";
import GenerateCycleCounts from "../views/inventory/GenerateCycleCounts.vue";
import CustomersList from "@/views/sales/CustomerList.vue";
import CustomerDetails from "@/views/sales/CustomerDetails.vue";
import CustomerCreate from "@/views/sales/CustomerCreate.vue";
import CustomerEdit from "@/views/sales/CustomerEdit.vue";
// Import Sales Quotation components
import SalesQuotationList from "../views/sales/SalesQuotationList.vue";
import SalesQuotationForm from "../views/sales/SalesQuotationForm.vue";
import SalesQuotationDetail from "../views/sales/SalesQuotationDetail.vue";
import SalesQuotationPrint from "../views/sales/SalesQuotationPrint.vue";
//SalesForecast
import SalesForecastList from "../views/sales/SalesForecastList.vue";
import SalesForecastDetail from "../views/sales/SalesForecastDetail.vue";
import SalesForecastAnalytics from "../views/sales/SalesForecastAnalytics.vue";
// Import new Sales Forecast components
import ConsolidatedForecastView from "../views/sales/ConsolidatedForecastView.vue";
import ImportForecastForm from "../views/sales/ImportForecastForm.vue";
import ForecastAccuracyAnalysis from "../views/sales/ForecastAccuracyAnalysis.vue";
import ForecastDashboard from "../views/sales/ForecastDashboard.vue";
import UpdateActualsPage from "../views/sales/UpdateActualsPage.vue";
import ForecastHistoryView from "../views/sales/ForecastHistoryView.vue";
//SalesOrder
import SalesOrderList from "../views/sales/SalesOrderList.vue";
import SalesOrderDetail from "../views/sales/SalesOrderDetail.vue";
import SalesOrderForm from "../views/sales/SalesOrderForm.vue";
import CreateOrderFromQuotation from "../views/sales/CreateOrderFromQuotation.vue";
//Sales Invoice
import SalesInvoiceList from "../views/sales/SalesInvoiceList.vue";
import SalesInvoiceDetail from "../views/sales/SalesInvoiceDetail.vue";
import SalesInvoiceForm from "../views/sales/SalesInvoiceForm.vue";
import SalesInvoicePrint from "../views/sales/SalesInvoicePrint.vue";
import SalesInvoicePayment from "../views/sales/SalesInvoicePayment.vue";
import CreateInvoiceFromDelivery from "../views/sales/CreateInvoiceFromDelivery.vue";
//Sales Delivery
import DeliveryList from "../views/sales/DeliveryList.vue";
import DeliveryDetail from "../views/sales/DeliveryDetail.vue";
import DeliveryForm from "../views/sales/DeliveryForm.vue";
// import DeliveryPrint from "../views/sales/DeliveryPrint.vue";
// Add these imports to the imports section
import VendorList from "../views/purchasing/VendorList.vue";
import VendorDetail from "../views/purchasing/VendorDetail.vue";
import VendorCreate from "../views/purchasing/VendorCreate.vue";
import VendorEdit from "../views/purchasing/VendorEdit.vue";

//Puchase Requisition
// Import components Purchase Requisition
import PurchaseRequisitionList from "../views/purchasing/PurchaseRequisitionList.vue";
import PurchaseRequisitionForm from "../views/purchasing/PurchaseRequisitionForm.vue";
import PurchaseRequisitionDetail from "../views/purchasing/PurchaseRequisitionDetail.vue";
import PurchaseRequisitionApproval from "../views/purchasing/PurchaseRequisitionApproval.vue";
import ConvertToRFQ from "../views/purchasing/ConvertToRFQ.vue";

//RFQ
import RFQList from "../views/purchasing/RFQList.vue";
import RFQDetail from "../views/purchasing/RFQDetail.vue";
import RFQForm from "../views/purchasing/RFQForm.vue";
import RFQSend from "../views/purchasing/RFQSend.vue";
import RFQCompare from "../views/purchasing/RFQCompare.vue";

// Import Purchase Order components
import PurchaseOrderList from '@/views/purchasing/PurchaseOrderList.vue';
import PurchaseOrderDetail from '@/views/purchasing/PurchaseOrderDetail.vue';
import PurchaseOrderFormView from '@/views/purchasing/PurchaseOrderFormView.vue';
import PurchaseOrderTrack from '@/views/purchasing/PurchaseOrderTrack.vue';
import CreatePOFromQuotation from '@/views/purchasing/CreatePOFromQuotation.vue';

//GoodReceipt
import GoodsReceiptList from "../views/purchasing/GoodsReceiptList.vue";
import GoodsReceiptFormView from "../views/purchasing/GoodsReceiptFormView.vue";
import GoodsReceiptDetail from "../views/purchasing/GoodsReceiptDetail.vue";
import ReceiptConfirmation from "../views/purchasing/ReceiptConfirmation.vue";
import PendingReceiptsDashboard from "../views/purchasing/PendingReceiptsDashboard.vue";

// Import components Warehouse
import WarehouseList from "../views/inventory/WarehouseList.vue";
import WarehouseDetail from "../views/inventory/WarehouseDetail.vue";
//import WarehouseZoneDetail from "../views/inventory/WarehouseZoneDetail.vue";
//import WarehouseLocationForm from "../views/inventory/WarehouseLocationForm.vue";
import ZonesList from "../views/inventory/ZonesList.vue";
import LocationsList from "../views/inventory/LocationsList.vue";
import LocationInventory from "../views/inventory/LocationInventory.vue";

// Import the Sales Return components
import SalesReturnList from "@/views/sales/SalesReturnList.vue";
import SalesReturnDetail from "@/views/sales/SalesReturnDetail.vue";
import SalesReturnForm from "@/views/sales/SalesReturnForm.vue";

import RoutingList from "../views/manufacturing/RoutingList.vue";
import RoutingDetail from "@/views/manufacturing/RoutingDetail.vue";
import RoutingForm from "@/views/manufacturing/RoutingForm.vue";
// Add these imports at the top of your router/index.js file
import BOMList from "../views/manufacturing/BOMList.vue";
import BOMDetail from "../views/manufacturing/BOMDetail.vue";
import BOMForm from "../views/manufacturing/BOMForm.vue";
import ProductionOrderList from "../views/manufacturing/ProductionOrderList.vue";
import ProductionOrderForm from "../views/manufacturing/ProductionOrderForm.vue";
import ProductionOrderDetail from "../views/manufacturing/ProductionOrderDetail.vue";
import ProductionConsumptionForm from "../views/manufacturing/ProductionConsumptionForm.vue";
import ProductionCompletionForm from "../views/manufacturing/ProductionCompletionForm.vue";
// Import Quality Inspection components
import ListQualityInspections from '../views/manufacturing/ListQualityInspections.vue'
import QualityInspectionForm from '../views/manufacturing/QualityInspectionForm.vue'
import QualityInspectionDetail from '../views/manufacturing/QualityInspectionDetail.vue'
import QualityParameterForm from '../views/manufacturing/QualityParameterForm.vue'
import QualityAnalysisDashboard from '../views/manufacturing/QualityAnalysisDashboard.vue'
import SalesOrderPrint from "../views/sales/SalesOrderPrint.vue";

// import SalesForecastFormModal from "../views/sales/SalesForecastFormModal.vue";
// Import other components as needed

const routes = [
    {
        path: "/login",
        name: "Login",
        component: Login,
        meta: { requiresAuth: false },
    },
    {
        path: "/register",
        name: "Register",
        component: Register,
        meta: { requiresAuth: false },
    },
    {
        path: "/",
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: "",
                redirect: "/dashboard",
            },
            {
                path: "dashboard",
                name: "Dashboard",
                component: Dashboard,
            },
            // Inventory Module Routes
            {
                path: "items",
                name: "Items",
                component: ItemsList,
            },
            {
                path: "/items/:id",
                name: "ItemDetail",
                component: ItemDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "item-categories",
                name: "ItemCategories",
                component: ItemCategories,
            },
            {
                path: "categories-enhanced",
                name: "ItemCategoriesEnhanced",
                component: ItemCategoriesEnhanced,
            },
            // Add Unit of Measure route
            {
                path: "unit-of-measures",
                name: "UnitOfMeasures",
                component: UnitOfMeasureList,
            },
            {
                path: "unit-of-measures/:id",
                name: "UnitOfMeasureDetail",
                component: UnitOfMeasureDetail,
                props: true,
            },
            // Tambahkan route dalam children array dari layout AppLayout:
            // {
            //     path: "item-prices/:id?",
            //     name: "ItemPrices",
            //     component: ItemPriceList,
            //     props: true,
            //     meta: { requiresAuth: true }
            // },
            // {
            //     path: "price-comparison",
            //     name: "PriceComparison",
            //     component: PriceComparison,
            //     meta: { requiresAuth: true }
            // },
            {
                path: "/item-prices-management",
                name: "ItemPriceManagement",
                component: ItemPriceManagement,
                meta: { requiresAuth: true }
            },
            // {
            //     path: "warehouses",
            //     name: "Warehouses",
            //     component: WarehousesList,
            // },
            // {
            // path: 'warehouses/:id',
            // name: 'WarehouseDetails',
            // component: () => import('../views/inventory/WarehouseDetails.vue'),
            // props: true
            // },
            // Stock Operations Routes
            // {
            //     path: "stock-transactions",
            //     name: "StockTransactions",
            //     component: StockTransactions,
            // },
            {
                path: '/stock-transactions',
                name: 'StockTransactions',
                component: StockTransactionsList
              },
              {
                path: '/stock-transactions/create',
                name: 'CreateStockTransaction',
                component: StockTransactionForm
              },
              {
                path: '/stock-transactions/:id',
                name: 'StockTransactionDetail',
                component: StockTransactionDetail,
                props: true
              },
              {
                path: '/stock-transactions/items/:itemId/movement',
                name: 'ItemMovementHistory',
                component: ItemMovementHistory,
                props: true
              },
              {
                path: '/stock-transactions/transfer',
                name: 'StockTransfer',
                component: StockTransferForm
              },
            {
                path: "/sales/customers",
                name: "customers.index",
                component: CustomersList,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/customers/create",
                name: "customers.create",
                component: CustomerCreate,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/customers/:id",
                name: "customers.show",
                component: CustomerDetails,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/customers/edit/:id",
                name: "customers.edit",
                component: CustomerEdit,
                props: true,
                meta: { requiresAuth: true },
            },
            // Item Stock Management Routes
            {
                path: "/item-stocks",
                name: "ItemStocks",
                component: ItemStockList,
                meta: { requiresAuth: true }
            },
            {
                path: "/item-stocks/item/:itemId",
                name: "ItemStockDetail",
                component: ItemStockDetail,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/item-stocks/warehouse/:warehouseId?",
                name: "WarehouseStock",
                component: WarehouseStock,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/item-stocks/transfer",
                name: "StockTransfer",
                component: StockTransfer,
                meta: { requiresAuth: true }
            },
            {
                path: "/item-stocks/adjust",
                name: "StockAdjustment",
                component: StockAdjustment,
                meta: { requiresAuth: true }
            },
            {
                path: "/item-stocks/reserve",
                name: "StockReservation",
                component: StockReservation,
                meta: { requiresAuth: true }
            },
            {
                path: "/item-stocks/negative",
                name: "NegativeStocks",
                component: NegativeStocks,
                meta: { requiresAuth: true }
            },
            {
                path: "stock-adjustments",
                name: "StockAdjustments",
                component: StockAdjustmentList,
                meta: { requiresAuth: true }
              },
              {
                path: "stock-adjustments/create",
                name: "CreateStockAdjustment",
                component: StockAdjustmentForm,
                meta: { requiresAuth: true }
              },
              {
                path: "stock-adjustments/:id",
                name: "StockAdjustmentDetail",
                component: StockAdjustmentDetail,
                props: true,
                meta: { requiresAuth: true }
              },
              {
                path: "stock-adjustments/:id/edit",
                name: "EditStockAdjustment",
                component: StockAdjustmentForm,
                props: true,
                meta: { requiresAuth: true }
              },
              {
                path: "stock-adjustments/:id/approve",
                name: "ApproveStockAdjustment",
                component: StockAdjustmentApproval,
                props: true,
                meta: { requiresAuth: true }
              },
            
              {
                path: "cycle-counts",
                name: "CycleCountList",
                component: CycleCountList,
                meta: { requiresAuth: true }
              },
              {
                path: "cycle-counts/create",
                name: "CreateCycleCount",
                component: CycleCountForm,
                meta: { requiresAuth: true }
              },
              {
                path: "cycle-counts/generate",
                name: "GenerateCycleCounts",
                component: GenerateCycleCounts,
                meta: { requiresAuth: true }
              },
              {
                path: "cycle-counts/:id",
                name: "CycleCountDetail",
                component: CycleCountDetail,
                props: true,
                meta: { requiresAuth: true }
              },
              {
                path: "cycle-counts/:id/edit",
                name: "EditCycleCount",
                component: CycleCountForm,
                props: true,
                meta: { requiresAuth: true }
              },
              {
                path: "cycle-counts/:id/approve",
                name: "CycleCountApproval",
                component: CycleCountApproval,
                props: true,
                meta: { requiresAuth: true }
              },
            // Item Batch Management Routes
            {
                path: '/items/:itemId/batches',
                name: 'ItemBatches',
                component: () => import('../views/inventory/BatchesList.vue'),
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/items/:itemId/batches/create',
                name: 'CreateBatch',
                component: () => import('../views/inventory/BatchForm.vue'),
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/items/:itemId/batches/:id',
                name: 'BatchDetail',
                component: () => import('../views/inventory/BatchDetail.vue'),
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/items/:itemId/batches/:id/edit',
                name: 'EditBatch',
                component: () => import('../views/inventory/BatchForm.vue'),
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/batches/expiry-dashboard',
                name: 'ExpiryDashboard',
                component: () => import('../views/inventory/ExpiryDashboard.vue'),
                meta: { requiresAuth: true }
            },
            {
                path: "/sales/quotations",
                name: "SalesQuotations",
                component: SalesQuotationList,
            },
            {
                path: "/sales/quotations/create",
                name: "CreateSalesQuotation",
                component: SalesQuotationForm,
            },
            {
                path: "/sales/quotations/:id",
                name: "SalesQuotationDetail",
                component: SalesQuotationDetail,
                props: true,
            },
            {
                path: "/sales/quotations/:id/edit",
                name: "EditSalesQuotation",
                component: SalesQuotationForm,
                props: true,
            },
            {
                path: "/sales/quotations/:id/print",
                name: "PrintSalesQuotation",
                component: SalesQuotationPrint,
                props: true,
            },
            //TambahanIyusyusa
            {
                path: "/sales/forecasts",
                name: "SalesForecastsList",
                component: SalesForecastList,
            },
            {
                path: "/sales/forecasts/:id",
                name: "SalesForecastDetail",
                component: SalesForecastDetail,
                props: true,
            },
            {
                path: "/sales/forecasts/analytics",
                name: "SalesForecastAnalytics",
                component: SalesForecastAnalytics,
            },
            {
                path: "/sales/forecasts/create",
                name: "CreateSalesForecast",
                component: () => import("../views/sales/SalesForecastForm.vue"),
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/forecasts/:id/edit",
                name: "EditSalesForecast",
                component: () => import("../views/sales/SalesForecastForm.vue"),
                props: true,
                meta: { requiresAuth: true },
            },
            // New Sales Forecast Routes
            {
                path: "/sales/forecasts/consolidated",
                name: "ConsolidatedForecastView",
                component: ConsolidatedForecastView,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/forecasts/import",
                name: "ImportForecastForm",
                component: ImportForecastForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/forecasts/accuracy",
                name: "ForecastAccuracyAnalysis",
                component: ForecastAccuracyAnalysis,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/forecasts/dashboard",
                name: "ForecastDashboard",
                component: ForecastDashboard,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/forecasts/update-actuals",
                name: "UpdateActualsPage",
                component: UpdateActualsPage,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/forecasts/history",
                name: "ForecastHistoryView",
                component: ForecastHistoryView,
                meta: { requiresAuth: true },
            },
            // New Forecast Volatility Routes
            {
                path: "/sales/forecasts/volatility-dashboard",
                name: "ForecastVolatilityDashboard",
                component: () => import("../views/sales/ForecastVolatilityDashboard.vue"),
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/forecasts/trend-analysis",
                name: "ForecastTrendAnalysis",
                component: () => import("../views/sales/ForecastTrendAnalysis.vue"),
                meta: { requiresAuth: true },
            },
            //SalesOrder
            {
                path: "/sales/orders",
                name: "SalesOrders",
                component: SalesOrderList,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/orders/create",
                name: "CreateSalesOrder",
                component: SalesOrderForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/orders/:id",
                name: "SalesOrderDetail",
                component: SalesOrderDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/orders/:id/edit",
                name: "EditSalesOrder",
                component: SalesOrderForm,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/orders/create-from-quotation/:id",
                name: "CreateOrderFromQuotation",
                component: CreateOrderFromQuotation,
                props: true,
                meta: { requiresAuth: true },
            },
            // Sales Invoice routes
            {
                path: "/sales/invoices",
                name: "SalesInvoices",
                component: SalesInvoiceList,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/invoices/create",
                name: "CreateSalesInvoice",
                component: SalesInvoiceForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/invoices/create-from-delivery",
                name: "CreateInvoiceFromDelivery",
                component: CreateInvoiceFromDelivery,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/invoices/:id",
                name: "SalesInvoiceDetail",
                component: SalesInvoiceDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/invoices/:id/edit",
                name: "EditSalesInvoice",
                component: SalesInvoiceForm,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/invoices/:id/payment",
                name: "SalesInvoicePayment",
                component: SalesInvoicePayment,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/invoices/:id/print",
                name: "PrintSalesInvoice",
                component: SalesInvoicePrint,
                props: true,
                meta: { requiresAuth: true },
            },
            //SalesDelivery
            {
                path: "/sales/deliveries",
                name: "DeliveryList",
                component: DeliveryList,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/deliveries/create",
                name: "CreateDelivery",
                component: DeliveryForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/deliveries/:id",
                name: "DeliveryDetail",
                component: DeliveryDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/deliveries/:id/edit",
                name: "EditDelivery",
                component: DeliveryForm,
                props: true,
                meta: { requiresAuth: true },
            },
            //SalesReturn
            {
                path: "/sales/returns",
                name: "SalesReturns",
                component: SalesReturnList,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/returns/create",
                name: "CreateSalesReturn",
                component: SalesReturnForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/returns/:id",
                name: "SalesReturnDetail",
                component: SalesReturnDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/returns/:id/edit",
                name: "EditSalesReturn",
                component: SalesReturnForm,
                props: true,
                meta: { requiresAuth: true },
            },
            // {
            //     path: "/sales/deliveries/:id/print",
            //     name: "PrintDelivery",
            //     component: DeliveryPrint,
            //     props: true,
            //     meta: { requiresAuth: true },
            // },
            // {
            //     path: "/sales/forecasts/formmodal",
            //     name: "SalesForecastFormModal",
            //     component: SalesForecastFormModal,
            // },
            //Sampaisini
            // BOM Routes
            //{
            //path: '/manufacturing/boms',
            //name: 'BOMList',
            //component: () => import('../views/manufacturing/BOMList.vue'),
            //meta: { requiresAuth: true }
            //},
            //{
            //path: '/manufacturing/boms/:id',
            //name: 'BOMDetail',
            //component: () => import('../views/manufacturing/BOMDetail.vue'),
            //props: true,
            //meta: { requiresAuth: true }
            //},
            // {
            // path: 'cycle-counts',
            // name: 'CycleCounting',
            // component: () => import('../views/inventory/CycleCounting.vue')
            // },
            // Reports Routes
            // {
            // path: 'reports/stock',
            // name: 'StockReport',
            // component: () => import('../views/reports/StockReport.vue')
            // },
            // {
            // path: 'reports/movement',
            // name: 'MovementReport',
            // component: () => import('../views/reports/MovementReport.vue')
            // },
            // Add these routes within the children array of the AppLayout route
            {
                path: "/manufacturing/boms",
                name: "BOMList",
                component: BOMList,
                meta: { requiresAuth: true }
              },
              {
                path: "/manufacturing/boms/create",
                name: "CreateBOM",
                component: BOMForm,
                meta: { requiresAuth: true }
              },
              {
                path: "/manufacturing/boms/:id",
                name: "BOMDetail",
                component: BOMDetail,
                props: true,
                meta: { requiresAuth: true }
              },
              {
                path: "/manufacturing/boms/:id/edit",
                name: "EditBOM",
                component: BOMForm,
                props: true,
                meta: { requiresAuth: true }
              },
            {
                path: "purchasing/vendors",
                name: "VendorList",
                component: VendorList,
                meta: { requiresAuth: true },
            },
            {
                path: "purchasing/vendors/create",
                name: "VendorCreate",
                component: VendorCreate,
                meta: { requiresAuth: true },
            },
            {
                path: "purchasing/vendors/:id",
                name: "VendorDetail",
                component: VendorDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "purchasing/vendors/:id/edit",
                name: "VendorEdit",
                component: VendorEdit,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/requisitions",
                name: "PurchaseRequisitionList",
                component: PurchaseRequisitionList,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/requisitions/create",
                name: "CreatePurchaseRequisition",
                component: PurchaseRequisitionForm,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/requisitions/:id",
                name: "PurchaseRequisitionDetail",
                component: PurchaseRequisitionDetail,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/requisitions/:id/edit",
                name: "EditPurchaseRequisition",
                component: PurchaseRequisitionForm,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/requisitions/:id/approve",
                name: "ApprovePurchaseRequisition",
                component: PurchaseRequisitionApproval,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/requisitions/:id/convert",
                name: "ConvertToRFQ",
                component: ConvertToRFQ,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/requisitions/approvals",
                name: "PRApprovalList",
                component: () => import("../views/purchasing/PRApprovalList.vue"),
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/requisitions/to-rfq",
                name: "PRToRFQList",
                component: () => import("../views/purchasing/PRToRFQList.vue"),
                meta: { requiresAuth: true },
            },

            //RFQ
            {
                path: "/purchasing/rfqs",
                name: "RFQList",
                component: RFQList,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/rfqs/create",
                name: "CreateRFQ",
                component: RFQForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/rfqs/:id",
                name: "RFQDetail",
                component: RFQDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/rfqs/:id/edit",
                name: "EditRFQ",
                component: RFQForm,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/rfqs/:id/send",
                name: "SendRFQ",
                component: RFQSend,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/rfqs/:id/compare",
                name: "CompareRFQ",
                component: RFQCompare,
                props: true,
                meta: { requiresAuth: true },
            },

            // Add these routes to your router.js file in the purchasing section
            {
                path: "/purchasing/quotations",
                name: "VendorQuotations",
                component: () => import("../views/purchasing/VendorQuotationList.vue"),
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/quotations/create",
                name: "CreateVendorQuotation",
                component: () => import("../views/purchasing/VendorQuotationForm.vue"),
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/quotations/:id",
                name: "VendorQuotationDetail",
                component: () => import("../views/purchasing/VendorQuotationDetail.vue"),
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/quotations/:id/edit",
                name: "EditVendorQuotation",
                component: () => import("../views/purchasing/VendorQuotationForm.vue"),
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/quotations/compare",
                name: "CompareVendorQuotations",
                component: () => import("../views/purchasing/VendorQuotationCompare.vue"),
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/quotations/:id/create-po",
                name: "CreatePOFromQuotation",
                component: () => import("../views/purchasing/CreatePOFromQuotation.vue"),
                props: true,
                meta: { requiresAuth: true }
            },
            

            // Purchase Order routes
            {
                path: '/purchasing/orders',
                name: 'PurchaseOrders',
                component: PurchaseOrderList,
                meta: { requiresAuth: true }
            },
            {
                path: '/purchasing/orders/create',
                name: 'CreatePurchaseOrder',
                component: PurchaseOrderFormView,
                meta: { requiresAuth: true }
            },
            {
                path: '/purchasing/orders/:id',
                name: 'PurchaseOrderDetail',
                component: PurchaseOrderDetail,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/purchasing/orders/:id/edit',
                name: 'EditPurchaseOrder',
                component: PurchaseOrderFormView,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/purchasing/orders/:id/track',
                name: 'PurchaseOrderTrack',
                component: PurchaseOrderTrack,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/purchasing/quotations/:id/create-po',
                name: 'CreatePOFromQuotation',
                component: CreatePOFromQuotation,
                props: true,
                meta: { requiresAuth: true }
            },
            {
                path: '/purchasing/quotations/:id/create-po',
                name: 'CreatePOFromQuotation',
                component: CreatePOFromQuotation,
                props: true,
                meta: { requiresAuth: true }
            },
            // Goods Receipts Routes
            {
                path: "/purchasing/goods-receipts",
                name: "GoodsReceiptList",
                component: GoodsReceiptList,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/goods-receipts/create",
                name: "CreateGoodsReceipt",
                component: GoodsReceiptFormView,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/goods-receipts/dashboard",
                name: "PendingReceiptsDashboard",
                component: PendingReceiptsDashboard,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/goods-receipts/:id",
                name: "GoodsReceiptDetail",
                component: GoodsReceiptDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/goods-receipts/:id/edit",
                name: "EditGoodsReceipt",
                component: GoodsReceiptFormView,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/goods-receipts/:id/confirm",
                name: "ConfirmGoodsReceipt",
                component: ReceiptConfirmation,
                props: route => ({ receiptId: route.params.id }),
                meta: { requiresAuth: true },
            },

            // Vendor Invoice
            {
                path: "/purchasing/vendor-invoices",
                name: "VendorInvoiceList",
                component: () =>
                    import("../views/purchasing/VendorInvoiceList.vue"),
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/vendor-invoices/create",
                name: "VendorInvoiceCreate",
                component: () =>
                    import("../views/purchasing/VendorInvoiceForm.vue"),
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/vendor-invoices/:id",
                name: "VendorInvoiceDetail",
                component: () =>
                    import("../views/purchasing/VendorInvoiceDetail.vue"),
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/vendor-invoices/:id/edit",
                name: "VendorInvoiceEdit",
                component: () =>
                    import("../views/purchasing/VendorInvoiceForm.vue"),
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/vendor-invoices/approve",
                name: "VendorInvoiceApproval",
                component: () =>
                    import("../views/purchasing/VendorInvoiceApproval.vue"),
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/purchasing/vendor-invoices/payment",
                name: "VendorInvoicePayment",
                component: () =>
                    import("../views/purchasing/VendorInvoicePayment.vue"),
                props: true,
                meta: { requiresAuth: true },
            },
            // vendor contract service
            // {
            //     path: "/purchasing/contracts",
            //     name: "ContractList",
            //     component: () => import("../views/purchasing/ContractList.vue"),
            //     meta: { requiresAuth: true },
            // },
            // {
            //     path: "/purchasing/contracts/create",
            //     name: "ContractCreate",
            //     component: () =>
            //         import("../views/purchasing/ContractCreate.vue"),
            //     meta: { requiresAuth: true },
            // },
            // {
            //     path: "/purchasing/contracts/:id",
            //     name: "ContractDetail",
            //     component: () =>
            //         import("../views/purchasing/ContractDetail.vue"),
            //     props: true,
            //     meta: { requiresAuth: true },
            // },
            // {
            //     path: "/purchasing/contracts/:id/edit",
            //     name: "ContractEdit",
            //     component: () => import("../views/purchasing/ContractEdit.vue"),
            //     props: true,
            //     meta: { requiresAuth: true },
            // },
            // Warehouse routes
            {
                path: "/warehouses",
                name: "Warehouses",
                component: WarehouseList,
                meta: { requiresAuth: true },
            },
            {
                path: "/warehouses/:id",
                name: "WarehouseDetail",
                component: WarehouseDetail,
                props: true,
                meta: { requiresAuth: true },
            },

            // Warehouse zone and location management
            {
                path: "/warehouses/:id/zones",
                name: "WarehouseZones",
                component: ZonesList,
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/warehouses/:warehouseId/zones/:zoneId",
                name: "WarehouseLocations",
                component: LocationsList,
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/warehouses/:warehouseId/zones/:zoneId/locations/:locationId/inventory",
                name: "LocationInventory",
                component: LocationInventory,
                props: true,
                meta: { requiresAuth: true },
              },

            // Material Planning routes
            {
                path: "/materials/plans",
                name: "MaterialPlans",
                component: () => import("../views/inventory/MaterialPlanningList.vue"),
                meta: { requiresAuth: true }
            },
            {
                path: "/materials/plans/:id",
                name: "MaterialPlanDetail",
                component: () => import("../views/inventory/MaterialPlanDetails.vue"),
                props: true,
                meta: { requiresAuth: true }
            },
            // Material Planning routes
            {
                path: "/materials/plans/generate",
                name: "MaterialPlanGeneration",
                component: () => import("../views/inventory/MaterialPlanGeneration.vue"),
                meta: { requiresAuth: true }
            },
            {
                path: "/purchasing/requisitions/generate-from-material-plan",
                name: "PRGenerationFromMaterialPlan",
                component: () => import("../views/inventory/PRGenerationFromMaterialPlan.vue"),
                meta: { requiresAuth: true }
            },
            // Routing Management Routes
            {
                path: "/manufacturing/routings",
                name: "RoutingList",
                component: RoutingList,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/routings/create",
                name: "CreateRouting",
                component: RoutingForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/routings/:id",
                name: "RoutingDetail",
                component: RoutingDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/routings/:id/edit",
                name: "EditRouting",
                component: RoutingForm,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/work-centers",
                name: "WorkCentersList",
                component: () => import("../views/manufacturing/WorkCentersList.vue"),
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-centers/create",
                name: "CreateWorkCenter",
                component: () => import("../views/manufacturing/WorkCenterForm.vue"),
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-centers/:id",
                name: "WorkCenterDetail",
                component: () => import("../views/manufacturing/WorkCenterDetail.vue"),
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-centers/:id/edit",
                name: "EditWorkCenter",
                component: () => import("../views/manufacturing/WorkCenterForm.vue"),
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-centers/:id/schedule",
                name: "WorkCenterSchedule",
                component: () => import("../views/manufacturing/WorkCenterSchedule.vue"),
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-orders",
                name: "WorkOrders",
                component: () => import("../views/manufacturing/WorkOrderList.vue"),
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-orders/create",
                name: "CreateWorkOrder",
                component: () => import("../views/manufacturing/WorkOrderForm.vue"),
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-orders/:id",
                name: "WorkOrderDetail",
                component: () => import("../views/manufacturing/WorkOrderDetail.vue"),
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-orders/:id/edit",
                name: "EditWorkOrder",
                component: () => import("../views/manufacturing/WorkOrderForm.vue"),
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/work-orders/:workOrderId/operations/:operationId",
                name: "WorkOrderOperation",
                component: () => import("../views/manufacturing/WorkOrderOperationForm.vue"),
                props: true,
                meta: { requiresAuth: true },
              },
              {
                path: "/manufacturing/dashboard",
                name: "ManufacturingDashboard",
                component: () => import("../views/manufacturing/WorkOrderDashboard.vue"),
                meta: { requiresAuth: true },
              },
            // Currency Rates Module
            {
                path: "/currency-rates",
                name: "CurrencyRates",
                component: () => import("../views/accounting/CurrencyRatesList.vue"),
                meta: { requiresAuth: true },
            },
            {
                path: "/currency-rates/create",
                name: "CreateCurrencyRate",
                component: () => import("../views/accounting/CurrencyRateForm.vue"),
                meta: { requiresAuth: true },
            },
            {
                path: "/currency-rates/:id",
                name: "CurrencyRateDetail",
                component: () => import("../views/accounting/CurrencyRateDetail.vue"),
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/currency-rates/:id/edit",
                name: "EditCurrencyRate",
                component: () => import("../views/accounting/CurrencyRateForm.vue"),
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/currency-converter",
                name: "CurrencyConverter",
                component: () => import("../views/accounting/CurrencyConverter.vue"),
                meta: { requiresAuth: true },
            },
            // Then add these routes within the children array of the AppLayout route
            // You can place them in the manufacturing section
            {
                path: "/manufacturing/production-orders",
                name: "ProductionOrders",
                component: ProductionOrderList,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/production-orders/create",
                name: "CreateProductionOrder",
                component: ProductionOrderForm,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/production-orders/:productionId",
                name: "ProductionOrderDetail",
                component: ProductionOrderDetail,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/production-orders/:id/edit",
                name: "EditProductionOrder",
                component: ProductionOrderForm,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/production-orders/:productionId/consumption/add",
                name: "AddProductionConsumption",
                component: ProductionConsumptionForm,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/production-orders/:productionId/consumption/:consumptionId/edit",
                name: "EditProductionConsumption",
                component: ProductionConsumptionForm,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/manufacturing/production-orders/:productionId/complete",
                name: "CompleteProductionOrder",
                component: ProductionCompletionForm,
                props: true,
                meta: { requiresAuth: true },
            },
            // Quality Inspections routes
            {
                path: 'quality-inspections',
                name: 'quality-inspections',
                component: ListQualityInspections,
                meta: { title: 'Quality Inspections' }
            },
            {
                path: 'quality-inspections/create',
                name: 'quality-inspections-create',
                component: QualityInspectionForm,
                meta: { title: 'Create Quality Inspection' }
            },
            {
                path: 'quality-inspections/:id',
                name: 'quality-inspection-detail',
                component: QualityInspectionDetail,
                props: true,
                meta: { title: 'Inspection Details' }
            },
            {
                path: 'quality-inspections/:id/edit',
                name: 'quality-inspection-edit',
                component: QualityInspectionForm,
                props: true,
                meta: { title: 'Edit Quality Inspection' }
            },
            
            // Quality Parameters routes
            {
                path: 'quality-parameters/create',
                name: 'quality-parameters-create',
                component: QualityParameterForm,
                meta: { title: 'Create Quality Parameter' }
            },
            {
                path: 'quality-parameters/:id/edit',
                name: 'quality-parameters-edit',
                component: QualityParameterForm,
                props: true,
                meta: { title: 'Edit Quality Parameter' }
            },

            {
                path: "/sales/orders/:id/print",
                name: "PrintSalesOrder",
                component: SalesOrderPrint,
                props: true,
                meta: { requiresAuth: true },
            },
            {
                path: "/sales/deliveries/:id/print",
                name: "PrintDeliveryOrder",
                component: () =>
                    import("../views/sales/DeliveryOrderPrint.vue"),
                props: true,
                meta: { requiresAuth: true },
    },
            
            // Dashboard specific route
            {
                path: 'dashboard',
                name: 'quality-dashboard',
                component: QualityAnalysisDashboard,
                meta: { title: 'Quality Analysis Dashboard' }
            },
            // Inside your routes array, add this section:
            {
            path: "/admin",
            component: () => import("../layouts/AdminAppLayout.vue"),
            meta: { requiresAuth: true, adminOnly: true },
            children: [
            {
                path: "",
                redirect: "/admin/dashboard",
            },
            {
                path: "dashboard",
                name: "AdminDashboard",
                component: () => import("../views/admin/AdminDashboard.vue"),
            },
            {
                path: "settings",
                name: "SystemSettings",
                component: () => import("../views/admin/SystemSettings.vue"),
            },
            {
                path: "users",
                name: "UserList",
                component: () => import("../views/admin/UsersList.vue"),
            },
      // Add other admin routes as needed
    ],
  },
        ],
    },
    // Catch-all 404 route
    {
        path: "/:pathMatch(.*)*",
        redirect: "/dashboard",
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard for authentication
router.beforeEach((to, from, next) => {
    const isAuthenticated = !!localStorage.getItem("token");
    const user = JSON.parse(localStorage.getItem("user") || "{}");
    const isAdmin = user.is_admin || false; // Menentukan apakah pengguna adalah admin

    if (to.meta.requiresAuth && !isAuthenticated) {
        // Redirect to login if trying to access a protected route without being authenticated
        next("/login");
    } else if (to.path === "/login" && isAuthenticated) {
        // Redirect to dashboard if already authenticated and trying to access login
        next("/dashboard");
    } else if (to.meta.adminOnly && !isAdmin) {
        // Redirect to dashboard if trying to access admin route without being admin
        next("/dashboard");
    } else {
        // Proceed normally
        next();
    }
});

export default router;
