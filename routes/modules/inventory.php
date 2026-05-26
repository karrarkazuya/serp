<?php

use App\Http\Controllers\Inventory\Configuration\LocationController;
use App\Http\Controllers\Inventory\Configuration\OperationTypeController;
use App\Http\Controllers\Inventory\Configuration\ProductCategoryController;
use App\Http\Controllers\Inventory\Configuration\PutawayRuleController;
use App\Http\Controllers\Inventory\Configuration\RouteController;
use App\Http\Controllers\Inventory\Configuration\UomController;
use App\Http\Controllers\Inventory\Configuration\WarehouseController;
use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\LotController;
use App\Http\Controllers\Inventory\PickingController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\QuantController;
use App\Http\Controllers\Inventory\ReorderRuleController;
use App\Http\Controllers\Inventory\ScrapOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory module
|--------------------------------------------------------------------------
| Required from routes/web.php inside the auth middleware group.
*/
Route::prefix('inventory')->name('inventory.')->group(function () {

    // Dashboard
    Route::get('/', [InventoryDashboardController::class, 'index'])
        ->middleware('permission:inventory.read')->name('dashboard');

    // Products
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/',                    [ProductController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create',              [ProductController::class, 'create'])     ->middleware('permission:inventory.create') ->name('create');
        Route::get('/uom-info',            [ProductController::class, 'uomInfo'])    ->middleware('permission:inventory.read')   ->name('uom-info');
        Route::post('/',                   [ProductController::class, 'store'])      ->middleware('permission:inventory.create') ->name('store');
        Route::get('/{product}',           [ProductController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
        Route::get('/{product}/edit',      [ProductController::class, 'edit'])       ->middleware('permission:inventory.write')  ->name('edit');
        Route::put('/{product}',           [ProductController::class, 'write'])      ->middleware('permission:inventory.write')  ->name('update');
        Route::patch('/{product}/archive', [ProductController::class, 'archive'])   ->middleware('permission:inventory.write')  ->name('archive');
        Route::patch('/{product}/unarchive',[ProductController::class, 'unarchive'])->middleware('permission:inventory.write')  ->name('unarchive');
        Route::delete('/{product}',        [ProductController::class, 'unlink'])    ->middleware('permission:inventory.unlink') ->name('delete');
        Route::post('/{product}/comment',  [ProductController::class, 'addComment'])->middleware('permission:inventory.write')  ->name('comment');
    });

    // Transfers (Pickings)
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/',                          [PickingController::class, 'read'])             ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create',                    [PickingController::class, 'create'])           ->middleware('permission:inventory.create') ->name('create');
        Route::get('/new-move-row',              [PickingController::class, 'newMoveRow'])       ->middleware('permission:inventory.create') ->name('new-move-row');
        Route::post('/',                         [PickingController::class, 'store'])            ->middleware('permission:inventory.create') ->name('store');
        Route::get('/{picking}',                 [PickingController::class, 'show'])             ->middleware('permission:inventory.read')   ->name('show');
        Route::get('/{picking}/edit',            [PickingController::class, 'edit'])             ->middleware('permission:inventory.write')  ->name('edit');
        Route::put('/{picking}',                 [PickingController::class, 'write'])            ->middleware('permission:inventory.write')  ->name('update');
        Route::post('/{picking}/confirm',        [PickingController::class, 'confirm'])          ->middleware('permission:inventory.write')  ->name('confirm');
        Route::post('/{picking}/check-availability', [PickingController::class, 'checkAvailability'])->middleware('permission:inventory.write')->name('check-availability');
        Route::post('/{picking}/validate',       [PickingController::class, 'validate'])         ->middleware('permission:inventory.write')  ->name('validate');
        Route::post('/{picking}/cancel',         [PickingController::class, 'cancel'])           ->middleware('permission:inventory.write')  ->name('cancel');
        Route::post('/{picking}/return',         [PickingController::class, 'returnPicking'])    ->middleware('permission:inventory.write')  ->name('return');
        Route::delete('/{picking}',              [PickingController::class, 'unlink'])           ->middleware('permission:inventory.unlink') ->name('delete');
        Route::post('/{picking}/comment',        [PickingController::class, 'addComment'])       ->middleware('permission:inventory.write')  ->name('comment');
    });

    // Receipts (incoming pickings)
    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/',       [PickingController::class, 'readReceipts'])   ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create', [PickingController::class, 'createReceipt'])  ->middleware('permission:inventory.create') ->name('create');
    });

    // Deliveries (outgoing pickings)
    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/',       [PickingController::class, 'readDeliveries'])  ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create', [PickingController::class, 'createDelivery']) ->middleware('permission:inventory.create') ->name('create');
    });

    // Internal Transfers
    Route::prefix('internal-transfers')->name('internal-transfers.')->group(function () {
        Route::get('/',       [PickingController::class, 'readInternal'])   ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create', [PickingController::class, 'createInternal']) ->middleware('permission:inventory.create') ->name('create');
    });

    // Lots / Serial Numbers
    Route::prefix('lots')->name('lots.')->group(function () {
        Route::get('/',               [LotController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create',         [LotController::class, 'create'])     ->middleware('permission:inventory.create') ->name('create');
        Route::post('/',              [LotController::class, 'store'])      ->middleware('permission:inventory.create') ->name('store');
        Route::get('/{lot}',          [LotController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
        Route::get('/{lot}/edit',     [LotController::class, 'edit'])       ->middleware('permission:inventory.write')  ->name('edit');
        Route::put('/{lot}',          [LotController::class, 'write'])      ->middleware('permission:inventory.write')  ->name('update');
        Route::delete('/{lot}',       [LotController::class, 'unlink'])     ->middleware('permission:inventory.unlink') ->name('delete');
        Route::post('/{lot}/comment', [LotController::class, 'addComment']) ->middleware('permission:inventory.write')  ->name('comment');
    });

    // Scrap
    Route::prefix('scrap')->name('scrap.')->group(function () {
        Route::get('/',                    [ScrapOrderController::class, 'read'])         ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create',              [ScrapOrderController::class, 'create'])       ->middleware('permission:inventory.create') ->name('create');
        Route::post('/',                   [ScrapOrderController::class, 'store'])        ->middleware('permission:inventory.create') ->name('store');
        Route::get('/{scrapOrder}',        [ScrapOrderController::class, 'show'])         ->middleware('permission:inventory.read')   ->name('show');
        Route::post('/{scrapOrder}/validate', [ScrapOrderController::class, 'validateScrap'])->middleware('permission:inventory.write')->name('validate');
        Route::delete('/{scrapOrder}',     [ScrapOrderController::class, 'unlink'])       ->middleware('permission:inventory.unlink') ->name('delete');
        Route::post('/{scrapOrder}/comment', [ScrapOrderController::class, 'addComment']) ->middleware('permission:inventory.write')  ->name('comment');
    });

    // Replenishment (Reorder Rules)
    Route::prefix('replenishment')->name('replenishment.')->group(function () {
        Route::get('/',               [ReorderRuleController::class, 'read'])        ->middleware('permission:inventory.read')   ->name('index');
        Route::get('/create',         [ReorderRuleController::class, 'create'])      ->middleware('permission:inventory.create') ->name('create');
        Route::post('/',              [ReorderRuleController::class, 'store'])       ->middleware('permission:inventory.create') ->name('store');
        Route::get('/{reorderRule}/edit',    [ReorderRuleController::class, 'edit'])        ->middleware('permission:inventory.write')  ->name('edit');
        Route::put('/{reorderRule}',         [ReorderRuleController::class, 'write'])       ->middleware('permission:inventory.write')  ->name('update');
        Route::post('/{reorderRule}/replenish', [ReorderRuleController::class, 'replenish'])->middleware('permission:inventory.write')  ->name('replenish');
        Route::delete('/{reorderRule}',      [ReorderRuleController::class, 'unlink'])      ->middleware('permission:inventory.unlink') ->name('delete');
    });

    // Physical Inventory (Adjustments)
    Route::prefix('adjustments')->name('adjustments.')->group(function () {
        Route::get('/',                             [InventoryAdjustmentController::class, 'read'])             ->middleware('permission:inventory.read')  ->name('index');
        Route::get('/create',                       [InventoryAdjustmentController::class, 'create'])           ->middleware('permission:inventory.create') ->name('create');
        Route::post('/',                            [InventoryAdjustmentController::class, 'store'])            ->middleware('permission:inventory.create') ->name('store');
        Route::get('/{inventoryAdjustment}',        [InventoryAdjustmentController::class, 'show'])             ->middleware('permission:inventory.read')  ->name('show');
        Route::post('/{inventoryAdjustment}/start', [InventoryAdjustmentController::class, 'startCount'])       ->middleware('permission:inventory.write') ->name('start');
        Route::post('/{inventoryAdjustment}/lines/{line}', [InventoryAdjustmentController::class, 'updateLine'])->middleware('permission:inventory.write') ->name('update-line');
        Route::post('/{inventoryAdjustment}/validate', [InventoryAdjustmentController::class, 'validateAdjustment'])->middleware('permission:inventory.write')->name('validate');
        Route::delete('/{inventoryAdjustment}',     [InventoryAdjustmentController::class, 'unlink'])           ->middleware('permission:inventory.unlink')->name('delete');
        Route::post('/{inventoryAdjustment}/comment', [InventoryAdjustmentController::class, 'addComment'])     ->middleware('permission:inventory.write') ->name('comment');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/stock', [QuantController::class, 'read'])->middleware('permission:inventory.read')->name('stock');
    });

    // Configuration
    Route::prefix('configuration')->name('config.')->group(function () {

        // Product Categories
        Route::prefix('product-categories')->name('product-categories.')->group(function () {
            Route::get('/',                      [ProductCategoryController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',                [ProductCategoryController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
            Route::post('/',                     [ProductCategoryController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
            Route::get('/{productCategory}',     [ProductCategoryController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
            Route::get('/{productCategory}/edit',[ProductCategoryController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
            Route::put('/{productCategory}',     [ProductCategoryController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
            Route::delete('/{productCategory}',  [ProductCategoryController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
        });

        // Units of Measure
        Route::prefix('uoms')->name('uoms.')->group(function () {
            Route::get('/',          [UomController::class, 'read'])   ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',    [UomController::class, 'create']) ->middleware('permission:inventory.config') ->name('create');
            Route::post('/',         [UomController::class, 'store'])  ->middleware('permission:inventory.config') ->name('store');
            Route::get('/{uom}',     [UomController::class, 'show'])   ->middleware('permission:inventory.read')   ->name('show');
            Route::get('/{uom}/edit',[UomController::class, 'edit'])   ->middleware('permission:inventory.config') ->name('edit');
            Route::put('/{uom}',     [UomController::class, 'write'])  ->middleware('permission:inventory.config') ->name('update');
            Route::delete('/{uom}',  [UomController::class, 'unlink']) ->middleware('permission:inventory.config') ->name('delete');
        });

        // Warehouses
        Route::prefix('warehouses')->name('warehouses.')->group(function () {
            Route::get('/',                       [WarehouseController::class, 'read'])       ->middleware('permission:inventory.config') ->name('index');
            Route::get('/create',                 [WarehouseController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
            Route::post('/',                      [WarehouseController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
            Route::get('/{warehouse}',            [WarehouseController::class, 'show'])       ->middleware('permission:inventory.config') ->name('show');
            Route::get('/{warehouse}/edit',       [WarehouseController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
            Route::put('/{warehouse}',            [WarehouseController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
            Route::patch('/{warehouse}/archive',  [WarehouseController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
            Route::patch('/{warehouse}/unarchive',[WarehouseController::class, 'unarchive'])  ->middleware('permission:inventory.config') ->name('unarchive');
            Route::delete('/{warehouse}',         [WarehouseController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
            Route::post('/{warehouse}/comment',   [WarehouseController::class, 'addComment']) ->middleware('permission:inventory.config') ->name('comment');
        });

        // Locations
        Route::prefix('locations')->name('locations.')->group(function () {
            Route::get('/',                      [LocationController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',                [LocationController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
            Route::post('/',                     [LocationController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
            Route::get('/{location}',            [LocationController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
            Route::get('/{location}/edit',       [LocationController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
            Route::put('/{location}',            [LocationController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
            Route::patch('/{location}/archive',  [LocationController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
            Route::patch('/{location}/unarchive',[LocationController::class, 'unarchive'])  ->middleware('permission:inventory.config') ->name('unarchive');
            Route::delete('/{location}',         [LocationController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
            Route::post('/{location}/comment',   [LocationController::class, 'addComment']) ->middleware('permission:inventory.config') ->name('comment');
        });

        // Operation Types
        Route::prefix('operation-types')->name('operation-types.')->group(function () {
            Route::get('/',                          [OperationTypeController::class, 'read'])       ->middleware('permission:inventory.config') ->name('index');
            Route::get('/create',                    [OperationTypeController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
            Route::post('/',                         [OperationTypeController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
            Route::get('/{operationType}',           [OperationTypeController::class, 'show'])       ->middleware('permission:inventory.config') ->name('show');
            Route::get('/{operationType}/edit',      [OperationTypeController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
            Route::put('/{operationType}',           [OperationTypeController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
            Route::patch('/{operationType}/archive', [OperationTypeController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
            Route::patch('/{operationType}/unarchive',[OperationTypeController::class, 'unarchive']) ->middleware('permission:inventory.config') ->name('unarchive');
            Route::delete('/{operationType}',        [OperationTypeController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
        });

        // Routes
        Route::prefix('routes')->name('routes.')->group(function () {
            Route::get('/',                 [RouteController::class, 'read'])       ->middleware('permission:inventory.config') ->name('index');
            Route::get('/create',           [RouteController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
            Route::post('/',                [RouteController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
            Route::get('/new-rule-row',     [RouteController::class, 'newRuleRow'])->middleware('permission:inventory.config') ->name('new-rule-row');
            Route::get('/{route}',          [RouteController::class, 'show'])       ->middleware('permission:inventory.config') ->name('show');
            Route::get('/{route}/edit',     [RouteController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
            Route::put('/{route}',          [RouteController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
            Route::patch('/{route}/archive',[RouteController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
            Route::patch('/{route}/unarchive',[RouteController::class, 'unarchive'])->middleware('permission:inventory.config')->name('unarchive');
            Route::delete('/{route}',       [RouteController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
        });

        // Putaway Rules
        Route::prefix('putaway-rules')->name('putaway-rules.')->group(function () {
            Route::get('/',               [PutawayRuleController::class, 'read'])   ->middleware('permission:inventory.config') ->name('index');
            Route::get('/create',         [PutawayRuleController::class, 'create']) ->middleware('permission:inventory.config') ->name('create');
            Route::post('/',              [PutawayRuleController::class, 'store'])  ->middleware('permission:inventory.config') ->name('store');
            Route::get('/{putawayRule}/edit', [PutawayRuleController::class, 'edit'])->middleware('permission:inventory.config')->name('edit');
            Route::put('/{putawayRule}',  [PutawayRuleController::class, 'write'])  ->middleware('permission:inventory.config') ->name('update');
            Route::delete('/{putawayRule}',[PutawayRuleController::class, 'unlink'])->middleware('permission:inventory.config') ->name('delete');
        });

    });

});
