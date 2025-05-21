<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\MaterialPlan;

// routes/api.php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Inventory\ItemCategoryController;
use App\Http\Controllers\Api\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Api\Inventory\ItemController;
use App\Http\Controllers\Api\Inventory\WarehouseController;
use App\Http\Controllers\Api\Inventory\WarehouseZoneController;
use App\Http\Controllers\Api\Inventory\WarehouseLocationController;
use App\Http\Controllers\Api\Inventory\ItemBatchController;
use App\Http\Controllers\Api\Inventory\StockTransactionController;
use App\Http\Controllers\Api\Inventory\StockAdjustmentController;
use App\Http\Controllers\Api\Inventory\CycleCountingController;
use App\Http\Controllers\Api\Inventory\ItemStockController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MaterialPlanningController;

// purchase order
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\PurchaseRequisitionController;
use App\Http\Controllers\Api\RequestForQuotationController;
use App\Http\Controllers\Api\VendorQuotationController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\VendorInvoiceController;
use App\Http\Controllers\Api\VendorContractController;
use App\Http\Controllers\Api\VendorEvaluationController;

// Sales Order
use App\Http\Controllers\Api\Sales\CustomerController;
use App\Http\Controllers\Api\Sales\SalesQuotationController;
use App\Http\Controllers\Api\Sales\SalesOrderController;
use App\Http\Controllers\Api\Sales\DeliveryController;
use App\Http\Controllers\Api\Sales\SalesInvoiceController;
use App\Http\Controllers\Api\Sales\SalesReturnController;
use App\Http\Controllers\Api\Sales\CustomerInteractionController;
use App\Http\Controllers\Api\Sales\SalesCommissionController;
use App\Http\Controllers\Api\Sales\SalesForecastController;

// Manufacturing
use App\Http\Controllers\Api\Manufacturing\ProductController;
use App\Http\Controllers\Api\Manufacturing\BOMController;
use App\Http\Controllers\Api\Manufacturing\BOMLineController;
use App\Http\Controllers\Api\Manufacturing\RoutingController;
use App\Http\Controllers\Api\Manufacturing\WorkCenterController;
use App\Http\Controllers\Api\Manufacturing\RoutingOperationController;
use App\Http\Controllers\Api\Manufacturing\WorkOrderController;
use App\Http\Controllers\Api\Manufacturing\WorkOrderOperationController;
use App\Http\Controllers\Api\Manufacturing\ProductionOrderController;
use App\Http\Controllers\Api\Manufacturing\ProductionConsumptionController;
use App\Http\Controllers\Api\Manufacturing\QualityInspectionController;
use App\Http\Controllers\Api\Manufacturing\QualityParameterController;
use App\Http\Controllers\Api\Manufacturing\MaintenanceScheduleController;

//Accounting
use App\Http\Controllers\Api\Accounting\ChartOfAccountController;
use App\Http\Controllers\Api\Accounting\AccountingPeriodController;
use App\Http\Controllers\Api\Accounting\JournalEntryController;
use App\Http\Controllers\Api\Accounting\CustomerReceivableController;
use App\Http\Controllers\Api\Accounting\ReceivablePaymentController;
use App\Http\Controllers\Api\Accounting\VendorPayableController;
use App\Http\Controllers\Api\Accounting\PayablePaymentController;
use App\Http\Controllers\Api\Accounting\TaxTransactionController;
use App\Http\Controllers\Api\Accounting\FixedAssetController;
use App\Http\Controllers\Api\Accounting\AssetDepreciationController;
use App\Http\Controllers\Api\Accounting\BudgetController;
use App\Http\Controllers\Api\Accounting\BankAccountController;
use App\Http\Controllers\Api\Accounting\BankReconciliationController;
use App\Http\Controllers\Api\Accounting\FinancialReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth Routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/users', [UserController::class, 'index']);

    Route::prefix('items')->group(function () {
        // Existing item routes...
        // Di dalam grup route prefix 'items'
        Route::get('/{id}/prices-in-currencies', 'App\Http\Controllers\Api\Inventory\ItemController@getPricesInCurrencies');
        // Special item filters
        Route::get('/purchasable', 'App\Http\Controllers\Api\Inventory\ItemController@getPurchasableItems');
        Route::get('/sellable', 'App\Http\Controllers\Api\Inventory\ItemController@getSellableItems');
        
        // Item price routes
        Route::get('/{itemId}/prices', 'App\Http\Controllers\Api\Inventory\ItemPriceController@index');
        Route::post('/{itemId}/prices', 'App\Http\Controllers\Api\Inventory\ItemPriceController@store');
        Route::get('/{itemId}/prices/{id}', 'App\Http\Controllers\Api\Inventory\ItemPriceController@show');
        Route::put('/{itemId}/prices/{id}', 'App\Http\Controllers\Api\Inventory\ItemPriceController@update');
        Route::delete('/{itemId}/prices/{id}', 'App\Http\Controllers\Api\Inventory\ItemPriceController@destroy');
        // Routes for item prices
        Route::get('items/{id}/all-prices', [ItemController::class, 'showAllPrices']);
        Route::get('items/{id}/customer-price-matrix', [ItemController::class, 'customerPriceMatrix']);

        // Price calculation endpoints
        Route::get('/{itemId}/best-purchase-price', 'App\Http\Controllers\Api\Inventory\ItemPriceController@getBestPurchasePrice');
        Route::get('/{itemId}/best-sale-price', 'App\Http\Controllers\Api\Inventory\ItemPriceController@getBestSalePrice');
        Route::get('/{id}/document', 'App\Http\Controllers\Api\Inventory\ItemController@downloadDocument');
        // Update default prices
        Route::put('/{itemId}/default-prices', 'App\Http\Controllers\Api\Inventory\ItemPriceController@updateDefaultPrices');
    });

    // // Item Routes
    // Route::apiResource('items', ItemController::class);
    // Route::get('/items/stock-status', [ItemController::class, 'stockStatus']);

    // // Category Routes
    // Route::apiResource('item-categories', CategoryController::class);

    // // UOM Routes
    // //Route::apiResource('unit-of-measures', UnitOfMeasureController::class);

    // // Warehouse Routes
    // Route::apiResource('warehouses', WarehouseController::class);
    // Route::apiResource('warehouses.zones', WarehouseZoneController::class);
    // Route::apiResource('warehouses.zones.locations', WarehouseLocationController::class);

    // // Transaction Routes
    // Route::apiResource('stock-transactions', StockTransactionController::class);

    // // Adjustment Routes
    // Route::apiResource('stock-adjustments', StockAdjustmentController::class);
    // Route::patch('/stock-adjustments/{stock_adjustment}/approve', [StockAdjustmentController::class, 'approve']);
    // Route::patch('/stock-adjustments/{stock_adjustment}/cancel', [StockAdjustmentController::class, 'cancel']);

    // // Reports
    // Route::get('/reports/stock', [ReportController::class, 'stockReport']);
    // Route::get('/reports/movement', [ReportController::class, 'movementReport']);
    // Route::get('/reports/adjustment', [ReportController::class, 'adjustmentReport']);
    // Route::get('/reports/valuation', [ReportController::class, 'valuationReport']);

    // Item Category Routes
    Route::get('categories/tree', [ItemCategoryController::class, 'tree']);
    Route::resource('categories', ItemCategoryController::class);
    
    // Unit of Measure Routes
    Route::resource('uoms', UnitOfMeasureController::class);
    
    // Item Routes
    Route::get('items/stock-levels', [ItemController::class, 'stockLevelReport']);
    Route::post('items/{id}/update-stock', [ItemController::class, 'updateStock']);
    Route::resource('items', ItemController::class);
    
    // Warehouse Routes
    Route::get('warehouses/{id}/inventory', [WarehouseController::class, 'inventory']);
    Route::resource('warehouses', WarehouseController::class);
    
    // Warehouse Zone Routes
    Route::resource('warehouses/{warehouse_id}/zones', WarehouseZoneController::class);
    
    // Warehouse Location Routes
    Route::get('zones/{zone_id}/locations/{id}/inventory', [WarehouseLocationController::class, 'inventory']);
    Route::resource('zones/{zone_id}/locations', WarehouseLocationController::class);
    
    // Item Batch Routes
    Route::get('batches/near-expiry/{days?}', [ItemBatchController::class, 'nearExpiry']);
    Route::resource('items/{item_id}/batches', ItemBatchController::class);
    
    // Stock Transaction Routes
    Route::get('transactions/items/{item_id}/movement', [StockTransactionController::class, 'itemMovement']);
    Route::post('transactions/transfer', [StockTransactionController::class, 'transfer']);
    Route::resource('transactions', StockTransactionController::class);
    
    // Stock Adjustment Routes
    Route::post('adjustments/{id}/submit', [StockAdjustmentController::class, 'submit']);
    Route::post('adjustments/{id}/approve', [StockAdjustmentController::class, 'approve']);
    Route::post('adjustments/{id}/reject', [StockAdjustmentController::class, 'reject']);
    Route::resource('adjustments', StockAdjustmentController::class);
    
    // Cycle Counting Routes
    Route::post('cycle-counts/generate', [CycleCountingController::class, 'generateTasks']);
    Route::post('cycle-counts/{id}/submit', [CycleCountingController::class, 'submit']);
    Route::post('cycle-counts/{id}/approve', [CycleCountingController::class, 'approve']);
    Route::post('cycle-counts/{id}/reject', [CycleCountingController::class, 'reject']);
    Route::resource('cycle-counts', CycleCountingController::class);

    // Vendors
    Route::apiResource('vendors', VendorController::class);
    Route::get('vendors/{vendor}/evaluations', [VendorController::class, 'evaluations']);
    Route::get('vendors/{vendor}/purchase-orders', [VendorController::class, 'purchaseOrders']);

    // Purchase Requisitions
    Route::apiResource('purchase-requisitions', PurchaseRequisitionController::class);
    Route::patch('purchase-requisitions/{purchaseRequisition}/status', [PurchaseRequisitionController::class, 'updateStatus']);

    // Request For Quotations
    Route::apiResource('request-for-quotations', RequestForQuotationController::class);
    Route::patch('request-for-quotations/{requestForQuotation}/status', [RequestForQuotationController::class, 'updateStatus']);

    // Vendor Quotations
    Route::apiResource('vendor-quotations', VendorQuotationController::class);
    Route::patch('vendor-quotations/{vendorQuotation}/status', [VendorQuotationController::class, 'updateStatus']);

    // Purchase Orders
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::patch('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus']);
    Route::post('purchase-orders/create-from-quotation', [PurchaseOrderController::class, 'createFromQuotation']);
    // Route untuk outstanding PO
Route::get('purchase-orders/{purchaseOrder}/outstanding', [PurchaseOrderController::class, 'showOutstanding']);
Route::get('purchase-orders/outstanding/all', [PurchaseOrderController::class, 'getAllOutstanding']);
Route::get('purchase-orders/reports/outstanding-items', [PurchaseOrderController::class, 'outstandingItemsReport']);
    // New route for currency conversion
    Route::post('purchase-orders/{purchaseOrder}/convert-currency', [PurchaseOrderController::class, 'convertCurrency']);

    // Goods Receipts
    Route::prefix('goods-receipts')->group(function () {
        Route::get('/', 'App\Http\Controllers\API\GoodsReceiptController@index');
        Route::post('/', 'App\Http\Controllers\API\GoodsReceiptController@store');
        Route::get('/available-items', 'App\Http\Controllers\API\GoodsReceiptController@getAvailableItems');
        Route::get('/{goodsReceipt}', 'App\Http\Controllers\API\GoodsReceiptController@show');
        Route::put('/{goodsReceipt}', 'App\Http\Controllers\API\GoodsReceiptController@update');
        Route::delete('/{goodsReceipt}', 'App\Http\Controllers\API\GoodsReceiptController@destroy');
        Route::post('/{goodsReceipt}/confirm', 'App\Http\Controllers\API\GoodsReceiptController@confirm');
    });

    // Vendor Invoices
    Route::get('vendor-invoices/uninvoiced-receipts', [VendorInvoiceController::class, 'getUninvoicedReceipts']);
    Route::apiResource('vendor-invoices', VendorInvoiceController::class);
    Route::post('vendor-invoices/{vendorInvoice}/approve', [VendorInvoiceController::class, 'approve']);
    Route::post('vendor-invoices/{vendorInvoice}/pay', [VendorInvoiceController::class, 'pay']);
    Route::patch('vendor-invoices/{vendorInvoice}/status', [VendorInvoiceController::class, 'updateStatus']);

    // Vendor Contracts
    Route::apiResource('vendor-contracts', VendorContractController::class);
    Route::post('vendor-contracts/{vendorContract}/activate', [VendorContractController::class, 'activate']);
    Route::post('vendor-contracts/{vendorContract}/terminate', [VendorContractController::class, 'terminate']);

    // Vendor Evaluations
    Route::apiResource('vendor-evaluations', VendorEvaluationController::class);
    Route::get('vendor-performance', [VendorEvaluationController::class, 'vendorPerformance']);

    // Customer routes
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'destroy']);
        Route::get('/{id}/quotations', [CustomerController::class, 'quotations']);
        Route::get('/{id}/orders', [CustomerController::class, 'orders']);
        Route::get('/{id}/invoices', [CustomerController::class, 'invoices']);
    });

    // Sales Quotation routes
    Route::prefix('quotations')->group(function () {
        Route::get('/', [SalesQuotationController::class, 'index']);
        Route::post('/', [SalesQuotationController::class, 'store']);
        Route::get('/{id}', [SalesQuotationController::class, 'show']);
        Route::put('/{id}', [SalesQuotationController::class, 'update']);
        Route::delete('/{id}', [SalesQuotationController::class, 'destroy']);
        Route::post('/{id}/lines', [SalesQuotationController::class, 'addLine']);
        Route::put('/{id}/lines/{lineId}', [SalesQuotationController::class, 'updateLine']);
        Route::delete('/{id}/lines/{lineId}', [SalesQuotationController::class, 'removeLine']);
    });

    // Sales Order routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [SalesOrderController::class, 'index']);
        Route::post('/', [SalesOrderController::class, 'store']);
        Route::post('/from-quotation', [SalesOrderController::class, 'createFromQuotation']);
        Route::get('/{id}', [SalesOrderController::class, 'show']);
        Route::put('/{id}', [SalesOrderController::class, 'update']);
        Route::delete('/{id}', [SalesOrderController::class, 'destroy']);
        Route::post('/{id}/lines', [SalesOrderController::class, 'addLine']);
        Route::put('/{id}/lines/{lineId}', [SalesOrderController::class, 'updateLine']);
        Route::delete('/{id}/lines/{lineId}', [SalesOrderController::class, 'removeLine']);
    });

    // Delivery routes
    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']);
        Route::post('/', [DeliveryController::class, 'store']);
        Route::get('/{id}', [DeliveryController::class, 'show']);
        Route::put('/{id}', [DeliveryController::class, 'update']);
        Route::delete('/{id}', [DeliveryController::class, 'destroy']);
        Route::post('/{id}/complete', [DeliveryController::class, 'complete']);
    });

    // Sales Invoice routes
    Route::prefix('invoices')->group(function () {
        Route::get('/', [SalesInvoiceController::class, 'index']);
        Route::post('/', [SalesInvoiceController::class, 'store']);
        Route::post('/from-order', [SalesInvoiceController::class, 'createFromOrder']);
        Route::post('/from-deliveries', [SalesInvoiceController::class, 'createFromDeliveries']);
        Route::delete('/{id}', [SalesInvoiceController::class, 'destroy']);
        Route::get('/{id}/payment-info', [SalesInvoiceController::class, 'paymentInfo']);
        // Add these routes BEFORE the {id} routes to ensure proper route matching
        Route::get('getDeliveriesForInvoicing', [SalesInvoiceController::class, 'getDeliveriesForInvoicing']);
        Route::get('getDeliveryLinesByItem', [SalesInvoiceController::class, 'getDeliveryLinesByItem']);
        // Then the normal {id} routes
        Route::get('/{id}', [SalesInvoiceController::class, 'show']);
        Route::put('/{id}', [SalesInvoiceController::class, 'update']);
    });

    // Sales Return routes
    Route::prefix('returns')->group(function () {
        Route::get('/', [SalesReturnController::class, 'index']);
        Route::post('/', [SalesReturnController::class, 'store']);
        Route::get('/{id}', [SalesReturnController::class, 'show']);
        Route::put('/{id}', [SalesReturnController::class, 'update']);
        Route::delete('/{id}', [SalesReturnController::class, 'destroy']);
        Route::post('/{id}/process', [SalesReturnController::class, 'process']);
    });

    // Customer Interaction routes
    Route::prefix('interactions')->group(function () {
        Route::get('/', [CustomerInteractionController::class, 'index']);
        Route::post('/', [CustomerInteractionController::class, 'store']);
        Route::get('/{id}', [CustomerInteractionController::class, 'show']);
        Route::put('/{id}', [CustomerInteractionController::class, 'update']);
        Route::delete('/{id}', [CustomerInteractionController::class, 'destroy']);
        Route::get('/customer/{customerId}', [CustomerInteractionController::class, 'getCustomerInteractions']);
    });

    // Sales Commission routes
    Route::prefix('commissions')->group(function () {
        Route::get('/', [SalesCommissionController::class, 'index']);
        Route::post('/', [SalesCommissionController::class, 'store']);
        Route::get('/{id}', [SalesCommissionController::class, 'show']);
        Route::put('/{id}', [SalesCommissionController::class, 'update']);
        Route::delete('/{id}', [SalesCommissionController::class, 'destroy']);
        Route::post('/calculate', [SalesCommissionController::class, 'calculateCommissions']);
        Route::get('/sales-person/{salesPersonId}', [SalesCommissionController::class, 'getSalesPersonCommissions']);
        Route::post('/mark-as-paid', [SalesCommissionController::class, 'markAsPaid']);
    });

    // Sales Forecast routes
    Route::prefix('forecasts')->group(function () {
        // First define the specific named routes
        Route::get('/accuracy', [SalesForecastController::class, 'getForecastAccuracy']);
        Route::get('/consolidated', [SalesForecastController::class, 'getConsolidatedForecast']);
        Route::get('/history', [SalesForecastController::class, 'getForecastHistory']);
        Route::post('/import', [SalesForecastController::class, 'importCustomerForecasts']);
        Route::post('/generate', [SalesForecastController::class, 'generateForecasts']);
        Route::post('/update-actuals', [SalesForecastController::class, 'updateActuals']);
        
        // Then define the generic routes
        Route::get('/', [SalesForecastController::class, 'index']);
        Route::post('/', [SalesForecastController::class, 'store']);
        
        // Finally define the parameter routes that will capture anything else
        Route::get('/{id}', [SalesForecastController::class, 'show']);
        Route::put('/{id}', [SalesForecastController::class, 'update']);
        Route::delete('/{id}', [SalesForecastController::class, 'destroy']);
    });

    // Routes untuk Outstanding Sales Order
    Route::get('sales-orders/{id}/outstanding-items', 'Api\Sales\SalesOrderController@getOutstandingItems');
    Route::get('sales-orders/outstanding', 'Api\Sales\SalesOrderController@getAllOutstandingSalesOrders');

    // Routes untuk Delivery dari Outstanding Items
    Route::get('deliveries/outstanding-so', [DeliveryController::class, 'getOutstandingSalesOrders']);
    Route::get('deliveries/outstanding-items/{soId}', [DeliveryController::class, 'getOutstandingItemsForDelivery']);
    Route::post('deliveries/from-outstanding', [DeliveryController::class, 'storeFromOutstanding']);
    


    // Routes untuk ItemStock
    Route::get('item-stocks', [ItemStockController::class, 'index']);
    Route::get('item-stocks/item/{itemId}', [ItemStockController::class, 'getItemStock']);
    Route::get('item-stocks/warehouse/{warehouseId}', [ItemStockController::class, 'getWarehouseStock']);
    Route::get('item-stocks/negative', [ItemStockController::class, 'getNegativeStocks']);
    Route::get('item-stocks/negative-stock-summary', [ItemStockController::class, 'getNegativeStockSummary']);
    Route::post('item-stocks/transfer', [ItemStockController::class, 'transferStock']);
    Route::post('item-stocks/adjust', [ItemStockController::class, 'adjustStock']);
    Route::post('item-stocks/reserve', [ItemStockController::class, 'reserveStock']);
    Route::post('item-stocks/release-reservation', [ItemStockController::class, 'releaseReservation']);
    
    
    // Routes untuk System Settings
    Route::get('settings', 'Api\Admin\SystemSettingController@index');
    Route::get('settings/group/{group}', 'Api\Admin\SystemSettingController@getByGroup');
    Route::get('settings/inventory', 'Api\Admin\SystemSettingController@getInventorySettings');
    Route::put('settings', 'Api\Admin\SystemSettingController@update');
    Route::put('settings/batch', 'Api\Admin\SystemSettingController@updateMultiple');
    Route::put('settings/inventory', 'Api\Admin\SystemSettingController@updateInventorySettings');

    // Item Category Routes
    Route::prefix('item-categories')->group(function () {
        Route::get('/', [ItemCategoryController::class, 'index']);
        Route::post('/', [ItemCategoryController::class, 'store']);
        Route::get('/{id}', [ItemCategoryController::class, 'show']);
        Route::put('/{id}', [ItemCategoryController::class, 'update']);
        Route::delete('/{id}', [ItemCategoryController::class, 'destroy']);
    });

    // Unit of Measure Routes
    Route::prefix('unit-of-measures')->group(function () {
        Route::get('/', [UnitOfMeasureController::class, 'index']);
        Route::post('/', [UnitOfMeasureController::class, 'store']);
        Route::get('/{id}', [UnitOfMeasureController::class, 'show']);
        Route::put('/{id}', [UnitOfMeasureController::class, 'update']);
        Route::delete('/{id}', [UnitOfMeasureController::class, 'destroy']);
    });

    // Item Routes
    Route::prefix('items')->group(function () {
        Route::get('/', [ItemController::class, 'index']);
        Route::post('/', [ItemController::class, 'store']);
        Route::get('/stock-status', [ItemController::class, 'getStockStatus']);
        Route::get('/{id}', [ItemController::class, 'show']);
        Route::put('/{id}', [ItemController::class, 'update']);
        Route::delete('/{id}', [ItemController::class, 'destroy']);
    });

    // Warehouse Routes
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::post('/', [WarehouseController::class, 'store']);
        Route::get('/{id}', [WarehouseController::class, 'show']);
        Route::put('/{id}', [WarehouseController::class, 'update']);
        Route::delete('/{id}', [WarehouseController::class, 'destroy']);
    });

    // Stock Transaction Routes
    Route::prefix('stock-transactions')->group(function () {
        Route::get('/', [StockTransactionController::class, 'index']);
        Route::post('/', [StockTransactionController::class, 'store']);
        Route::get('/{id}', [StockTransactionController::class, 'show']);
    Route::get('/item/{itemId}', [StockTransactionController::class, 'itemMovement']);
        Route::get('/warehouse/{warehouseId}', [StockTransactionController::class, 'getWarehouseTransactions']);
    });

    // Stock Adjustment Routes
    Route::prefix('stock-adjustments')->group(function () {
        Route::get('/', [StockAdjustmentController::class, 'index']);
        Route::post('/', [StockAdjustmentController::class, 'store']);
        Route::get('/{id}', [StockAdjustmentController::class, 'show']);
        Route::patch('/{id}/approve', [StockAdjustmentController::class, 'approve']);
        Route::patch('/{id}/cancel', [StockAdjustmentController::class, 'cancel']);
    });

    // Manufacturing Module Routes
    // Products
    Route::apiResource('products', ProductController::class);

    // BOM
    Route::apiResource('boms', BOMController::class);
    Route::apiResource('boms/{bomId}/lines', BOMLineController::class);
    // Calculate potential yield from a specific material
    Route::post('/{bomId}/lines/{lineId}/calculate-yield', [BOMLineController::class, 'calculateYield']);
    
    // Calculate maximum possible production based on current stock
    Route::get('/{bomId}/maximum-yield', [BOMLineController::class, 'calculateMaximumYield']);
    
    // Create a yield-based BOM
    Route::post('/yield-based', [BOMController::class, 'createYieldBased']);

    // Routing
    Route::apiResource('routings', RoutingController::class);
    Route::apiResource('routings/{routingId}/operations', RoutingOperationController::class);

    // Work Centers
    Route::apiResource('work-centers', WorkCenterController::class);
    Route::get('work-centers/{workCenterId}/maintenance-schedules', [MaintenanceScheduleController::class, 'byWorkCenter']);

    // Work Orders
    Route::apiResource('work-orders', WorkOrderController::class);
    Route::apiResource('work-orders/{workOrderId}/operations', WorkOrderOperationController::class)
        ->except(['store', 'destroy']);

    // Production Orders
    Route::apiResource('production-orders', ProductionOrderController::class);
    Route::post('production-orders/{id}/complete', [ProductionOrderController::class, 'complete']);
    Route::apiResource('production-orders/{productionId}/consumptions', ProductionConsumptionController::class);

    // Maintenance Schedules
    Route::apiResource('maintenance-schedules', MaintenanceScheduleController::class);

    // Quality Control
    Route::apiResource('quality-inspections', QualityInspectionController::class);
    Route::apiResource('quality-inspections/{inspectionId}/parameters', QualityParameterController::class);
    Route::get('quality-parameters/categories', [QualityParameterController::class, 'categories']);
    Route::get('quality-inspections/by-reference/{referenceType}/{referenceId}', [QualityInspectionController::class, 'byReference']);
    Route::get('quality-parameters/items', [QualityParameterController::class, 'getItems']);

    Route::post('/material-planning/generate', [MaterialPlanningController::class, 'generateMaterialPlans']);
    Route::post('/material-planning/purchase-requisition', [MaterialPlanningController::class, 'generatePurchaseRequisitions']);
    Route::post('/material-planning/max-production', [MaterialPlanningController::class, 'calculateMaximumProduction']);
    // Tambahkan route untuk list material plans jika diperlukan
    Route::get('/material-planning', [MaterialPlanningController::class, 'index']);

    Route::delete('/material-planning/{id}', [MaterialPlanningController::class, 'destroy']);

    // Add GET route for single material plan
    Route::get('/material-planning/{id}', [MaterialPlanningController::class, 'show']);

    Route::get('items/{id}/prices-in-currencies', 'App\Http\Controllers\Api\Inventory\ItemController@getPricesInCurrencies');
    Route::get('customers/{id}/transactions-in-currency', 'App\Http\Controllers\Api\Sales\CustomerController@getTransactionsInCurrency');
    Route::get('vendors/{vendor}/transactions-in-currency', 'App\Http\Controllers\Api\VendorController@getTransactionsInCurrency');
    Route::put('vendors/{vendor}/preferred-currency', 'App\Http\Controllers\Api\VendorController@updatePreferredCurrency');
    // Accounting Module Routes
    Route::prefix('accounting')->group(function () {
        // Chart of Accounts
        Route::get('chart-of-accounts/hierarchy', [ChartOfAccountController::class, 'hierarchy']);
        Route::apiResource('chart-of-accounts', ChartOfAccountController::class);

        // Accounting Periods
        Route::get('accounting-periods/current', [AccountingPeriodController::class, 'current']);
        Route::apiResource('accounting-periods', AccountingPeriodController::class);

        // Journal Entries
        Route::post('journal-entries/{id}/post', [JournalEntryController::class, 'post']);
        Route::apiResource('journal-entries', JournalEntryController::class);

        // Customer Receivables
        Route::get('customer-receivables/aging', [CustomerReceivableController::class, 'aging']);
        Route::apiResource('customer-receivables', CustomerReceivableController::class);

        // Receivable Payments
        Route::apiResource('receivable-payments', ReceivablePaymentController::class);

        // Vendor Payables
        Route::get('vendor-payables/aging', [VendorPayableController::class, 'aging']);
        Route::apiResource('vendor-payables', VendorPayableController::class);

        // Payable Payments
        Route::apiResource('payable-payments', PayablePaymentController::class);

        // Tax Transactions
        Route::get('tax-transactions/summary', [TaxTransactionController::class, 'summary']);
        Route::apiResource('tax-transactions', TaxTransactionController::class);

        // Fixed Assets
        Route::apiResource('fixed-assets', FixedAssetController::class);

        // Asset Depreciations
        Route::post('fixed-assets/{id}/calculate-depreciation', [AssetDepreciationController::class, 'calculateDepreciation']);
        Route::apiResource('asset-depreciations', AssetDepreciationController::class);

        // Budgets
        Route::get('budgets/variance-report', [BudgetController::class, 'varianceReport']);
        Route::apiResource('budgets', BudgetController::class);

        // Bank Accounts
        Route::apiResource('bank-accounts', BankAccountController::class);

        // Bank Reconciliations
        Route::post('bank-reconciliations/{id}/finalize', [BankReconciliationController::class, 'finalize']);
        Route::apiResource('bank-reconciliations', BankReconciliationController::class);
        Route::apiResource('bank-reconciliations.lines', BankReconciliationController::class);

        // Financial Reports
        Route::prefix('reports')->group(function () {
            Route::get('trial-balance', [FinancialReportController::class, 'trialBalance']);
            Route::get('income-statement', [FinancialReportController::class, 'incomeStatement']);
            Route::get('balance-sheet', [FinancialReportController::class, 'balanceSheet']);
            Route::get('cash-flow', [FinancialReportController::class, 'cashFlow']);
            Route::get('accounts-receivable', [FinancialReportController::class, 'accountsReceivable']);
            Route::get('accounts-payable', [FinancialReportController::class, 'accountsPayable']);
        });
        // Currency Rates Management
        Route::get('currency-rates', [App\Http\Controllers\Api\CurrencyRateController::class, 'index']);
        Route::post('currency-rates', [App\Http\Controllers\Api\CurrencyRateController::class, 'store']);
        Route::get('currency-rates/{id}', [App\Http\Controllers\Api\CurrencyRateController::class, 'show']);
        Route::put('currency-rates/{id}', [App\Http\Controllers\Api\CurrencyRateController::class, 'update']);
        Route::delete('currency-rates/{id}', [App\Http\Controllers\Api\CurrencyRateController::class, 'destroy']);
        
        // Currency Converter utility
        Route::get('currency-rates/current-rate', [App\Http\Controllers\Api\CurrencyRateController::class, 'getCurrentRate']);
    });
});