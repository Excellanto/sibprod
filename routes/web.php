<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AppConfigurationController;
use App\Http\Controllers\AppDataValuesController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyDetailsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceManagementController;
use App\Http\Controllers\LanguageStringController;
use App\Http\Controllers\ModelTemplateController;
use App\Http\Controllers\PackagingController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProcessOrderController;
use App\Http\Controllers\ProductionDataController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\LoadTranslations;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
});

// Auth routes (Laravel Breeze - provided in routes/auth.php)
if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}

// Authenticated user routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Admin routes
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', LoadTranslations::class, AdminMiddleware::class, 'role_or_permission:admin|Admin|Administrator|admin-panel-access'])
    ->group(function () {
        // Dashboard alias
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Users
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');
        Route::post('/users/{user}/give-permissions', [UserController::class, 'givePermissions'])->name('users.givePermissions');

        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::patch('/roles/{role}/dashboard-view', [RoleController::class, 'updateDashboardView'])->name('roles.dashboardView');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.syncPermissions');

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        // Settings
        Route::get('/settings', [SiteSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SiteSettingController::class, 'update'])->name('settings.update');

        // App Configuration
        Route::get('/app-configuration', [AppConfigurationController::class, 'index'])->name('app-configuration.index');
        Route::match(['post', 'put'], '/app-configuration', [AppConfigurationController::class, 'update'])->name('app-configuration.update');

        // Company Details
        Route::get('/company-details', [CompanyDetailsController::class, 'index'])->name('company-details.index');
        Route::put('/company-details', [CompanyDetailsController::class, 'update'])->name('company-details.update');

        // Invoice Management
        Route::get('/invoice-management', [InvoiceManagementController::class, 'index'])->name('invoice-management.index');
        Route::get('/invoice-management/{invoice}/pdf', [InvoiceManagementController::class, 'pdf'])->name('invoice-management.pdf');
        Route::get('/invoice-management/clients-search', [InvoiceManagementController::class, 'searchClients'])->name('invoice-management.clients-search');
        Route::get('/invoice-management/packages-for-client', [InvoiceManagementController::class, 'packagesForClient'])->name('invoice-management.packages-for-client');
        Route::post('/invoice-management/line-item-desc', [InvoiceManagementController::class, 'storeLineItemDesc'])->name('invoice-management.line-item-desc.store');
        Route::get('/invoice-management/line-groups/{invoice_line_group}/qc-completed-pos', [InvoiceManagementController::class, 'qcCompletedPosForLineGroup'])->name('invoice-management.line-groups.qc-completed-pos');
        Route::post('/invoice-management/line-groups/{invoice_line_group}/line-items', [InvoiceManagementController::class, 'storeLineItem'])->name('invoice-management.line-groups.line-items.store');
        Route::patch('/invoice-management/line-items/{invoice_line_item}', [InvoiceManagementController::class, 'updateLineItem'])->name('invoice-management.line-items.update');
        Route::delete('/invoice-management/line-items/{invoice_line_item}', [InvoiceManagementController::class, 'destroyLineItem'])->name('invoice-management.line-items.destroy');
        Route::patch('/invoice-management/line-groups/{invoice_line_group}/highlighter', [InvoiceManagementController::class, 'updateLineGroupHighlighter'])->name('invoice-management.line-groups.highlighter.update');
        Route::patch('/invoice-management/line-groups/{invoice_line_group}', [InvoiceManagementController::class, 'updateLineGroup'])->name('invoice-management.line-groups.update');
        Route::delete('/invoice-management/line-groups/{invoice_line_group}', [InvoiceManagementController::class, 'destroyLineGroup'])->name('invoice-management.line-groups.destroy');
        Route::post('/invoice-management/{invoice}/foc-groups', [InvoiceManagementController::class, 'storeFocGroup'])->name('invoice-management.foc-groups.store');
        Route::put('/invoice-management/foc-groups/{invoice_foc_group}/items', [InvoiceManagementController::class, 'syncFocGroupItems'])->name('invoice-management.foc-groups.items.sync');
        Route::delete('/invoice-management/foc-groups/{invoice_foc_group}', [InvoiceManagementController::class, 'destroyFocGroup'])->name('invoice-management.foc-groups.destroy');
        Route::post('/invoice-management/{invoice}/line-groups', [InvoiceManagementController::class, 'storeLineGroup'])->name('invoice-management.line-groups.store');
        Route::post('/invoice-management', [InvoiceManagementController::class, 'store'])->name('invoice-management.store');
        Route::patch('/invoice-management/{invoice}/status', [InvoiceManagementController::class, 'updateStatus'])->name('invoice-management.update-status');
        Route::put('/invoice-management/{invoice}', [InvoiceManagementController::class, 'update'])->name('invoice-management.update');
        Route::delete('/invoice-management/{invoice}', [InvoiceManagementController::class, 'destroy'])->name('invoice-management.destroy');

        // Reports
        Route::prefix('reports')
            ->name('reports.')
            ->group(function () {
                Route::get('/client-model', [ReportController::class, 'clientModelIndex'])->name('client-model.index');
                Route::post('/client-model/generate', [ReportController::class, 'clientModelGenerate'])->name('client-model.generate');
                Route::post('/client-model/generate-all', [ReportController::class, 'clientModelGenerateAll'])->name('client-model.generate-all');
                Route::get('/client-model/clients-search', [ReportController::class, 'searchClients'])->name('client-model.clients-search');
                Route::get('/report-generations/status', [ReportController::class, 'reportGenerationStatus'])->name('report-generations.status');
                Route::get('/report-generations/{reportGeneration}/export-excel', [ReportController::class, 'reportGenerationExportExcel'])->name('report-generations.export-excel');
                Route::get('/report-generations/{reportGeneration}/pdf', [ReportController::class, 'reportGenerationPdf'])->name('report-generations.pdf');
                Route::delete('/report-generations/{reportGeneration}', [ReportController::class, 'reportGenerationDestroy'])->name('report-generations.destroy');
                Route::get('/production-order', [ReportController::class, 'productionOrderIndex'])->name('production-order.index');
                Route::post('/production-order/generate', [ReportController::class, 'productionOrderGenerate'])->name('production-order.generate');
                Route::get('/packing-order', [ReportController::class, 'packingOrderIndex'])->name('packing-order.index');
                Route::post('/packing-order/generate', [ReportController::class, 'packingOrderGenerate'])->name('packing-order.generate');
                Route::get('/order-detail', [ReportController::class, 'orderDetailIndex'])->name('order-detail.index');
                Route::post('/order-detail/generate', [ReportController::class, 'orderDetailGenerate'])->name('order-detail.generate');
            });

        // App Data Values
        Route::prefix('app-data-values')
            ->name('app-data-values.')
            ->group(function () {
                Route::get('/orderpos-status', [AppDataValuesController::class, 'orderPosStatusIndex'])->name('orderpos-status.index');
                Route::post('/orderpos-status', [AppDataValuesController::class, 'orderPosStatusStore'])->name('orderpos-status.store');
                Route::put('/orderpos-status/{id}', [AppDataValuesController::class, 'orderPosStatusUpdate'])->name('orderpos-status.update');
                Route::delete('/orderpos-status/{id}', [AppDataValuesController::class, 'orderPosStatusDestroy'])->name('orderpos-status.destroy');

                Route::get('/model-status', [AppDataValuesController::class, 'modelStatusIndex'])->name('model-status.index');
                Route::post('/model-status', [AppDataValuesController::class, 'modelStatusStore'])->name('model-status.store');
                Route::put('/model-status/{id}', [AppDataValuesController::class, 'modelStatusUpdate'])->name('model-status.update');
                Route::delete('/model-status/{id}', [AppDataValuesController::class, 'modelStatusDestroy'])->name('model-status.destroy');

                Route::get('/orders-status', [AppDataValuesController::class, 'ordersStatusIndex'])->name('orders-status.index');
                Route::post('/orders-status', [AppDataValuesController::class, 'ordersStatusStore'])->name('orders-status.store');
                Route::put('/orders-status/{id}', [AppDataValuesController::class, 'ordersStatusUpdate'])->name('orders-status.update');
                Route::delete('/orders-status/{id}', [AppDataValuesController::class, 'ordersStatusDestroy'])->name('orders-status.destroy');

                Route::get('/quality-control-status', [AppDataValuesController::class, 'qualityControlStatusIndex'])->name('quality-control-status.index');
                Route::post('/quality-control-status', [AppDataValuesController::class, 'qualityControlStatusStore'])->name('quality-control-status.store');
                Route::put('/quality-control-status/{id}', [AppDataValuesController::class, 'qualityControlStatusUpdate'])->name('quality-control-status.update');
                Route::delete('/quality-control-status/{id}', [AppDataValuesController::class, 'qualityControlStatusDestroy'])->name('quality-control-status.destroy');

                Route::get('/packaging-status', [AppDataValuesController::class, 'packagingStatusIndex'])->name('packaging-status.index');
                Route::post('/packaging-status', [AppDataValuesController::class, 'packagingStatusStore'])->name('packaging-status.store');
                Route::put('/packaging-status/{id}', [AppDataValuesController::class, 'packagingStatusUpdate'])->name('packaging-status.update');
                Route::delete('/packaging-status/{id}', [AppDataValuesController::class, 'packagingStatusDestroy'])->name('packaging-status.destroy');

                Route::get('/qc-reasons', [AppDataValuesController::class, 'qcReasonsIndex'])->name('qc-reasons.index');
                Route::post('/qc-reasons', [AppDataValuesController::class, 'qcReasonsStore'])->name('qc-reasons.store');
                Route::put('/qc-reasons/{id}', [AppDataValuesController::class, 'qcReasonsUpdate'])->name('qc-reasons.update');
                Route::delete('/qc-reasons/{id}', [AppDataValuesController::class, 'qcReasonsDestroy'])->name('qc-reasons.destroy');

                Route::get('/line-item-desc', [AppDataValuesController::class, 'lineItemDescIndex'])->name('line-item-desc.index');
                Route::post('/line-item-desc', [AppDataValuesController::class, 'lineItemDescStore'])->name('line-item-desc.store');
                Route::put('/line-item-desc/{id}', [AppDataValuesController::class, 'lineItemDescUpdate'])->name('line-item-desc.update');
                Route::delete('/line-item-desc/{id}', [AppDataValuesController::class, 'lineItemDescDestroy'])->name('line-item-desc.destroy');

                Route::get('/po-tags', [AppDataValuesController::class, 'poTagsIndex'])->name('po-tags.index');
                Route::post('/po-tags', [AppDataValuesController::class, 'poTagsStore'])->name('po-tags.store');
                Route::put('/po-tags/{id}', [AppDataValuesController::class, 'poTagsUpdate'])->name('po-tags.update');
                Route::delete('/po-tags/{id}', [AppDataValuesController::class, 'poTagsDestroy'])->name('po-tags.destroy');
            });

        // Languages / Translations
        Route::get('/languages', [LanguageStringController::class, 'index'])->name('languages.index');
        Route::put('/languages', [LanguageStringController::class, 'update'])->name('languages.update');
        Route::post('/languages/sync', [LanguageStringController::class, 'syncLanguageFilesToDB'])->name('languages.sync');

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::delete('/activity-logs/{activityLog}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');

        // Buyers
        Route::get('/buyers', [BuyerController::class, 'index'])->name('buyers.index');
        Route::get('/buyers/create', [BuyerController::class, 'create'])->name('buyers.create');
        Route::get('/buyers/{buyer}/edit', [BuyerController::class, 'edit'])->name('buyers.edit');
        Route::post('/buyers', [BuyerController::class, 'store'])->name('buyers.store');
        Route::put('/buyers/{buyer}', [BuyerController::class, 'update'])->name('buyers.update');
        Route::delete('/buyers/{buyer}', [BuyerController::class, 'destroy'])->name('buyers.destroy');

        // Clients (nested under buyers)
        Route::post('/buyers/{buyer}/clients', [ClientController::class, 'store'])->name('buyers.clients.store');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        // Production Data
        Route::prefix('production-data')
            ->name('production-data.')
            ->group(function () {
                Route::get('/colour-object', [ProductionDataController::class, 'colourObject'])
                    ->name('colour-object');
                Route::post('/colour-object', [ProductionDataController::class, 'storeColourObject'])
                    ->name('colour-object.store');
                Route::put('/colour-object/{colourObject}', [ProductionDataController::class, 'updateColourObject'])
                    ->name('colour-object.update');
                Route::delete('/colour-object/{colourObject}', [ProductionDataController::class, 'destroyColourObject'])
                    ->name('colour-object.destroy');
                Route::get('/colours', [ProductionDataController::class, 'colours'])
                    ->name('colours');
                Route::post('/colours', [ProductionDataController::class, 'storeColour'])
                    ->name('colours.store');
                Route::put('/colours/{colour}', [ProductionDataController::class, 'updateColour'])
                    ->name('colours.update');
                Route::delete('/colours/{colour}', [ProductionDataController::class, 'destroyColour'])
                    ->name('colours.destroy');
                Route::get('/leather', [ProductionDataController::class, 'leather'])
                    ->name('leather');
                Route::post('/leather', [ProductionDataController::class, 'storeLeather'])
                    ->name('leather.store');
                Route::put('/leather/{leather}', [ProductionDataController::class, 'updateLeather'])
                    ->name('leather.update');
                Route::delete('/leather/{leather}', [ProductionDataController::class, 'destroyLeather'])
                    ->name('leather.destroy');
                Route::get('/lining', [ProductionDataController::class, 'lining'])
                    ->name('lining');
                Route::post('/lining', [ProductionDataController::class, 'storeLining'])
                    ->name('lining.store');
                Route::put('/lining/{lining}', [ProductionDataController::class, 'updateLining'])
                    ->name('lining.update');
                Route::delete('/lining/{lining}', [ProductionDataController::class, 'destroyLining'])
                    ->name('lining.destroy');
                Route::get('/loop-band', [ProductionDataController::class, 'loopBand'])
                    ->name('loop-band');
                Route::post('/loop-band', [ProductionDataController::class, 'storeLoopBand'])
                    ->name('loop-band.store');
                Route::put('/loop-band/{loopBand}', [ProductionDataController::class, 'updateLoopBand'])
                    ->name('loop-band.update');
                Route::delete('/loop-band/{loopBand}', [ProductionDataController::class, 'destroyLoopBand'])
                    ->name('loop-band.destroy');
                Route::get('/buckle', [ProductionDataController::class, 'buckle'])
                    ->name('buckle');
                Route::post('/buckle', [ProductionDataController::class, 'storeBuckle'])
                    ->name('buckle.store');
                Route::put('/buckle/{buckle}', [ProductionDataController::class, 'updateBuckle'])
                    ->name('buckle.update');
                Route::delete('/buckle/{buckle}', [ProductionDataController::class, 'destroyBuckle'])
                    ->name('buckle.destroy');
                Route::get('/buckle-width', [ProductionDataController::class, 'buckleWidth'])
                    ->name('buckle-width');
                Route::post('/buckle-width', [ProductionDataController::class, 'storeBuckleWidth'])
                    ->name('buckle-width.store');
                Route::put('/buckle-width/{buckleWidth}', [ProductionDataController::class, 'updateBuckleWidth'])
                    ->name('buckle-width.update');
                Route::delete('/buckle-width/{buckleWidth}', [ProductionDataController::class, 'destroyBuckleWidth'])
                    ->name('buckle-width.destroy');
                Route::get('/thread', [ProductionDataController::class, 'thread'])
                    ->name('thread');
                Route::get('/thread-size', [ProductionDataController::class, 'threadSize'])
                    ->name('thread-size');
                Route::post('/thread-size', [ProductionDataController::class, 'storeThreadSize'])
                    ->name('thread-size.store');
                Route::put('/thread-size/{threadSize}', [ProductionDataController::class, 'updateThreadSize'])
                    ->name('thread-size.update');
                Route::delete('/thread-size/{threadSize}', [ProductionDataController::class, 'destroyThreadSize'])
                    ->name('thread-size.destroy');
                Route::get('/end-width', [ProductionDataController::class, 'endWidth'])
                    ->name('end-width');
                Route::post('/end-width', [ProductionDataController::class, 'storeEndWidth'])
                    ->name('end-width.store');
                Route::put('/end-width/{endWidth}', [ProductionDataController::class, 'updateEndWidth'])
                    ->name('end-width.update');
                Route::delete('/end-width/{endWidth}', [ProductionDataController::class, 'destroyEndWidth'])
                    ->name('end-width.destroy');
                Route::get('/design-name', [ProductionDataController::class, 'designName'])
                    ->name('design-name');
                Route::post('/design-name', [ProductionDataController::class, 'storeDesignName'])
                    ->name('design-name.store');
                Route::put('/design-name/{designName}', [ProductionDataController::class, 'updateDesignName'])
                    ->name('design-name.update');
                Route::delete('/design-name/{designName}', [ProductionDataController::class, 'destroyDesignName'])
                    ->name('design-name.destroy');
                Route::get('/design-description', [ProductionDataController::class, 'designDescription'])
                    ->name('design-description');
                Route::post('/design-description', [ProductionDataController::class, 'storeDesignDescription'])
                    ->name('design-description.store');
                Route::put('/design-description/{designDescription}', [ProductionDataController::class, 'updateDesignDescription'])
                    ->name('design-description.update');
                Route::delete('/design-description/{designDescription}', [ProductionDataController::class, 'destroyDesignDescription'])
                    ->name('design-description.destroy');
                Route::get('/reinforce', [ProductionDataController::class, 'reinforce'])
                    ->name('reinforce');
                Route::post('/reinforce', [ProductionDataController::class, 'storeReinforce'])
                    ->name('reinforce.store');
                Route::put('/reinforce/{reinforce}', [ProductionDataController::class, 'updateReinforce'])
                    ->name('reinforce.update');
                Route::delete('/reinforce/{reinforce}', [ProductionDataController::class, 'destroyReinforce'])
                    ->name('reinforce.destroy');
                Route::get('/strap-length-long-part', [ProductionDataController::class, 'strapLengthLongPart'])
                    ->name('strap-length-long-part');
                Route::post('/strap-length-long-part', [ProductionDataController::class, 'storeStrapLengthLongPart'])
                    ->name('strap-length-long-part.store');
                Route::put('/strap-length-long-part/{strapLengthLongPart}', [ProductionDataController::class, 'updateStrapLengthLongPart'])
                    ->name('strap-length-long-part.update');
                Route::delete('/strap-length-long-part/{strapLengthLongPart}', [ProductionDataController::class, 'destroyStrapLengthLongPart'])
                    ->name('strap-length-long-part.destroy');
                Route::get('/strap-length-short-part', [ProductionDataController::class, 'strapLengthShortPart'])
                    ->name('strap-length-short-part');
                Route::post('/strap-length-short-part', [ProductionDataController::class, 'storeStrapLengthShortPart'])
                    ->name('strap-length-short-part.store');
                Route::put('/strap-length-short-part/{strapLengthShortPart}', [ProductionDataController::class, 'updateStrapLengthShortPart'])
                    ->name('strap-length-short-part.update');
                Route::delete('/strap-length-short-part/{strapLengthShortPart}', [ProductionDataController::class, 'destroyStrapLengthShortPart'])
                    ->name('strap-length-short-part.destroy');
                Route::get('/stamping-long', [ProductionDataController::class, 'stampingLong'])
                    ->name('stamping-long');
                Route::post('/stamping-long', [ProductionDataController::class, 'storeStampingLong'])
                    ->name('stamping-long.store');
                Route::put('/stamping-long/{stampingLong}', [ProductionDataController::class, 'updateStampingLong'])
                    ->name('stamping-long.update');
                Route::delete('/stamping-long/{stampingLong}', [ProductionDataController::class, 'destroyStampingLong'])
                    ->name('stamping-long.destroy');
                Route::get('/stamping-short', [ProductionDataController::class, 'stampingShort'])
                    ->name('stamping-short');
                Route::post('/stamping-short', [ProductionDataController::class, 'storeStampingShort'])
                    ->name('stamping-short.store');
                Route::put('/stamping-short/{stampingShort}', [ProductionDataController::class, 'updateStampingShort'])
                    ->name('stamping-short.update');
                Route::delete('/stamping-short/{stampingShort}', [ProductionDataController::class, 'destroyStampingShort'])
                    ->name('stamping-short.destroy');
                Route::get('/strap-pictures', [ProductionDataController::class, 'strapPictures'])
                    ->name('strap-pictures');
                Route::post('/strap-pictures', [ProductionDataController::class, 'storeStrapPicture'])
                    ->name('strap-pictures.store');
                Route::put('/strap-pictures/{strapPicture}', [ProductionDataController::class, 'updateStrapPicture'])
                    ->name('strap-pictures.update');
                Route::delete('/strap-pictures/{strapPicture}', [ProductionDataController::class, 'destroyStrapPicture'])
                    ->name('strap-pictures.destroy');
                Route::get('/strap-pictures/image/{filename}', [ProductionDataController::class, 'serveStrapPicture'])
                    ->name('strap-pictures.image')
                    ->where('filename', '.*');
                Route::get('/special', [ProductionDataController::class, 'special'])
                    ->name('special');
                Route::post('/special', [ProductionDataController::class, 'storeSpecial'])
                    ->name('special.store');
                Route::put('/special/{special}', [ProductionDataController::class, 'updateSpecial'])
                    ->name('special.update');
                Route::delete('/special/{special}', [ProductionDataController::class, 'destroySpecial'])
                    ->name('special.destroy');
                Route::get('/prod-design', [ProductionDataController::class, 'prodDesign'])
                    ->name('prod-design');
                Route::post('/prod-design', [ProductionDataController::class, 'storeProdDesign'])
                    ->name('prod-design.store');
                Route::put('/prod-design/{prodDesign}', [ProductionDataController::class, 'updateProdDesign'])
                    ->name('prod-design.update');
                Route::delete('/prod-design/{prodDesign}', [ProductionDataController::class, 'destroyProdDesign'])
                    ->name('prod-design.destroy');
                Route::get('/note-info-line1', [ProductionDataController::class, 'noteInfoLine1'])
                    ->name('note-info-line1');
                Route::post('/note-info-line1', [ProductionDataController::class, 'storeNoteInfoLine1'])
                    ->name('note-info-line1.store');
                Route::put('/note-info-line1/{noteInfoLine1}', [ProductionDataController::class, 'updateNoteInfoLine1'])
                    ->name('note-info-line1.update');
                Route::delete('/note-info-line1/{noteInfoLine1}', [ProductionDataController::class, 'destroyNoteInfoLine1'])
                    ->name('note-info-line1.destroy');
                Route::get('/note-info-line2', [ProductionDataController::class, 'noteInfoLine2'])
                    ->name('note-info-line2');
                Route::post('/note-info-line2', [ProductionDataController::class, 'storeNoteInfoLine2'])
                    ->name('note-info-line2.store');
                Route::put('/note-info-line2/{noteInfoLine2}', [ProductionDataController::class, 'updateNoteInfoLine2'])
                    ->name('note-info-line2.update');
                Route::delete('/note-info-line2/{noteInfoLine2}', [ProductionDataController::class, 'destroyNoteInfoLine2'])
                    ->name('note-info-line2.destroy');
                Route::get('/tools', [ProductionDataController::class, 'tools'])
                    ->name('tools');
                Route::post('/tools', [ProductionDataController::class, 'storeTool'])
                    ->name('tools.store');
                Route::put('/tools/{tool}', [ProductionDataController::class, 'updateTool'])
                    ->name('tools.update');
                Route::delete('/tools/{tool}', [ProductionDataController::class, 'destroyTool'])
                    ->name('tools.destroy');
            });

        // Order Management
        Route::prefix('order-management')
            ->name('order-management.')
            ->group(function () {
                Route::get('/process-order', [ProcessOrderController::class, 'index'])
                    ->name('process-order');
                Route::get('/production-orders', [ProcessOrderController::class, 'productionOrders'])
                    ->name('production-orders');
                Route::get('/quality-control', [ProcessOrderController::class, 'qualityControlIndex'])
                    ->name('quality-control.index');
                Route::get('/packaging', [PackagingController::class, 'index'])
                    ->name('packaging.index');
                Route::post('/packaging', [PackagingController::class, 'store'])
                    ->name('packaging.store');
                Route::put('/packaging/{package}', [PackagingController::class, 'update'])
                    ->name('packaging.update');
                Route::delete('/packaging/{package}', [PackagingController::class, 'destroy'])
                    ->name('packaging.destroy');
                Route::get('/packaging/{package}/pdf', [PackagingController::class, 'viewPackagePdf'])
                    ->name('packaging.pdf');
                Route::get('/packaging/{package}/pdf2', [PackagingController::class, 'viewPackagePdf2'])
                    ->name('packaging.pdf2');
                Route::get('/packaging/{package}/pdf3', [PackagingController::class, 'viewPackagePdf3'])
                    ->name('packaging.pdf3');
                Route::get('/packaging/{package}/qc-completed-pos', [PackagingController::class, 'getQcCompletedPos'])
                    ->name('packaging.qc-completed-pos');
                Route::post('/packaging/{package}/boxes', [PackagingController::class, 'storeBox'])
                    ->name('packaging.boxes.store');
                Route::put('/packaging/{package}/boxes/{packageBox}', [PackagingController::class, 'updateBox'])
                    ->name('packaging.boxes.update');
                Route::delete('/packaging/{package}/boxes/{packageBox}', [PackagingController::class, 'destroyBox'])
                    ->name('packaging.boxes.destroy');
                Route::post('/packaging/{package}/boxes/{packageBox}/duplicate', [PackagingController::class, 'duplicateBox'])
                    ->name('packaging.boxes.duplicate');
                Route::post('/packaging/{package}/pallets', [PackagingController::class, 'storePallet'])
                    ->name('packaging.pallets.store');
                Route::put('/packaging/{package}/pallets/{packagePallet}', [PackagingController::class, 'updatePallet'])
                    ->name('packaging.pallets.update');
                Route::delete('/packaging/{package}/pallets/{packagePallet}', [PackagingController::class, 'destroyPallet'])
                    ->name('packaging.pallets.destroy');
                Route::delete('/packaging/{package}/pallets', [PackagingController::class, 'destroyAllPallets'])
                    ->name('packaging.pallets.destroy-all');
                Route::get('/create-order', [ProcessOrderController::class, 'create'])
                    ->name('create-order');
                Route::post('/create-order', [ProcessOrderController::class, 'store'])
                    ->name('create-order.store');
                Route::get('/edit-order/{orderId}', [ProcessOrderController::class, 'edit'])
                    ->name('edit-order');
                Route::put('/update-order/{orderId}', [ProcessOrderController::class, 'update'])
                    ->name('update-order');
                Route::put('/process-order/{orderId}/status', [ProcessOrderController::class, 'updateStatus'])
                    ->name('process-order.update-status');
                Route::get('/client-models', [ProcessOrderController::class, 'getClientModels'])
                    ->name('client-models');
                Route::post('/order-po', [ProcessOrderController::class, 'storeOrderPO'])
                    ->name('order-po.store');
                Route::put('/order-po/{id}', [ProcessOrderController::class, 'updateOrderPO'])
                    ->name('order-po.update');
                Route::put('/order-po/{id}/status', [ProcessOrderController::class, 'updateOrderPOStatus'])
                    ->name('order-po.update-status');
                Route::get('/order-po/{id}/pdf', [ProcessOrderController::class, 'viewPOPDF'])
                    ->name('order-po.pdf');
                Route::get('/order-po/{id}/qc-report', [ProcessOrderController::class, 'qcPoDistributionReport'])
                    ->name('order-po.qc-report');
                Route::delete('/order-po/{id}', [ProcessOrderController::class, 'destroyOrderPO'])
                    ->name('order-po.destroy');
                Route::post('/order-po/{id}/send-for-qc', [ProcessOrderController::class, 'sendForQC'])
                    ->name('order-po.send-for-qc');
                Route::post('/order-po/bulk-send-for-qc', [ProcessOrderController::class, 'bulkSendForQC'])
                    ->name('order-po.bulk-send-for-qc');
                Route::post('/quality-control/bulk-accept', [ProcessOrderController::class, 'bulkAcceptQC'])
                    ->name('quality-control.bulk-accept');
                Route::get('/quality-control/{id}/report', [ProcessOrderController::class, 'qcDistributionReport'])
                    ->name('quality-control.report');
                Route::put('/quality-control/{id}', [ProcessOrderController::class, 'updateQC'])
                    ->name('quality-control.update');
                Route::post('/quality-control/{id}/start-complete', [ProcessOrderController::class, 'startQCComplete'])
                    ->name('quality-control.start-complete');
                Route::post('/quality-control/{id}/save-draft', [ProcessOrderController::class, 'saveQCDraft'])
                    ->name('quality-control.save-draft');
                Route::post('/quality-control/{id}/complete', [ProcessOrderController::class, 'completeQC'])
                    ->name('quality-control.complete');
                Route::get('/quality-control/search-reasons', [ProcessOrderController::class, 'searchQcReasons'])
                    ->name('quality-control.search-reasons');
                Route::delete('/quality-control/{id}', [ProcessOrderController::class, 'destroyQC'])
                    ->name('quality-control.destroy');
                Route::get('/model-template', [ModelTemplateController::class, 'index'])
                    ->name('model-template');
                Route::get('/model-template/create', [ModelTemplateController::class, 'create'])
                    ->name('model-template.create');
                Route::get('/model-template/{clientmodel}', [ModelTemplateController::class, 'show'])
                    ->name('model-template.show');
                Route::get('/model-template/{clientmodel}/edit', [ModelTemplateController::class, 'edit'])
                    ->name('model-template.edit');
                Route::post('/model-template', [ModelTemplateController::class, 'store'])
                    ->name('model-template.store');
                Route::post('/model-template/draft', [ModelTemplateController::class, 'storeDraft'])
                    ->name('model-template.draft');
                Route::post('/model-template/{clientmodel}/duplicate', [ModelTemplateController::class, 'duplicate'])
                    ->name('model-template.duplicate');
                Route::put('/model-template/{clientmodel}', [ModelTemplateController::class, 'update'])
                    ->name('model-template.update');
                Route::delete('/model-template/{clientmodel}', [ModelTemplateController::class, 'destroy'])
                    ->name('model-template.destroy');
                Route::get('/buyers', [ModelTemplateController::class, 'getBuyers'])
                    ->name('buyers');
                Route::get('/clients', [ModelTemplateController::class, 'getClients'])
                    ->name('clients');
                Route::get('/all-clients', [ModelTemplateController::class, 'getAllClients'])
                    ->name('all-clients');
                Route::get('/leathers', [ModelTemplateController::class, 'getLeathers'])
                    ->name('leathers');
                Route::get('/leather-colours', [ModelTemplateController::class, 'getLeatherColours'])
                    ->name('leather-colours');
                Route::get('/linings', [ModelTemplateController::class, 'getLinings'])
                    ->name('linings');
                Route::get('/varnish-colours', [ModelTemplateController::class, 'getVarnishColours'])
                    ->name('varnish-colours');
                Route::get('/design-names', [ModelTemplateController::class, 'getDesignNames'])
                    ->name('design-names');
                Route::get('/design-descriptions', [ModelTemplateController::class, 'getDesignDescriptions'])
                    ->name('design-descriptions');
                Route::get('/specials', [ModelTemplateController::class, 'getSpecials'])
                    ->name('specials');
                Route::post('/leather', [ModelTemplateController::class, 'createLeather'])
                    ->name('leather.create');
                Route::post('/colour', [ModelTemplateController::class, 'createColour'])
                    ->name('colour.create');
                Route::post('/lining', [ModelTemplateController::class, 'createLining'])
                    ->name('lining.create');
                Route::post('/design-name', [ModelTemplateController::class, 'createDesignName'])
                    ->name('design-name.create');
                Route::post('/design-description', [ModelTemplateController::class, 'createDesignDescription'])
                    ->name('design-description.create');
                Route::post('/special', [ModelTemplateController::class, 'createSpecial'])
                    ->name('special.create');
                Route::get('/loop-bands', [ModelTemplateController::class, 'getLoopBands'])
                    ->name('loop-bands');
                Route::get('/end-widths', [ModelTemplateController::class, 'getEndWidths'])
                    ->name('end-widths');
                Route::get('/buckles', [ModelTemplateController::class, 'getBuckles'])
                    ->name('buckles');
                Route::get('/buckle-widths', [ModelTemplateController::class, 'getBuckleWidths'])
                    ->name('buckle-widths');
                Route::post('/loop-band', [ModelTemplateController::class, 'createLoopBand'])
                    ->name('loop-band.create');
                Route::post('/end-width', [ModelTemplateController::class, 'createEndWidth'])
                    ->name('end-width.create');
                Route::post('/buckle', [ModelTemplateController::class, 'createBuckle'])
                    ->name('buckle.create');
                Route::post('/buckle-width', [ModelTemplateController::class, 'createBuckleWidth'])
                    ->name('buckle-width.create');
                Route::get('/strap-length-short-parts', [ModelTemplateController::class, 'getStrapLengthShortParts'])
                    ->name('strap-length-short-parts');
                Route::get('/strap-length-long-parts', [ModelTemplateController::class, 'getStrapLengthLongParts'])
                    ->name('strap-length-long-parts');
                Route::get('/top-thread-colours', [ModelTemplateController::class, 'getTopThreadColours'])
                    ->name('top-thread-colours');
                Route::get('/bottom-thread-colours', [ModelTemplateController::class, 'getBottomThreadColours'])
                    ->name('bottom-thread-colours');
                Route::get('/top-thread-sizes', [ModelTemplateController::class, 'getTopThreadSizes'])
                    ->name('top-thread-sizes');
                Route::get('/bottom-thread-sizes', [ModelTemplateController::class, 'getBottomThreadSizes'])
                    ->name('bottom-thread-sizes');
                Route::get('/stamping-shorts', [ModelTemplateController::class, 'getStampingShorts'])
                    ->name('stamping-shorts');
                Route::get('/stamping-longs', [ModelTemplateController::class, 'getStampingLongs'])
                    ->name('stamping-longs');
                Route::post('/strap-length-short-part', [ModelTemplateController::class, 'createStrapLengthShortPart'])
                    ->name('strap-length-short-part.create');
                Route::post('/strap-length-long-part', [ModelTemplateController::class, 'createStrapLengthLongPart'])
                    ->name('strap-length-long-part.create');
                Route::post('/thread-size', [ModelTemplateController::class, 'createThreadSize'])
                    ->name('thread-size.create');
                Route::post('/stamping-short', [ModelTemplateController::class, 'createStampingShort'])
                    ->name('stamping-short.create');
                Route::post('/stamping-long', [ModelTemplateController::class, 'createStampingLong'])
                    ->name('stamping-long.create');
                Route::get('/note-info-line1s', [ModelTemplateController::class, 'getNoteInfoLine1s'])
                    ->name('note-info-line1s');
                Route::get('/note-info-line2s', [ModelTemplateController::class, 'getNoteInfoLine2s'])
                    ->name('note-info-line2s');
                Route::get('/tools', [ModelTemplateController::class, 'getTools'])
                    ->name('tools');
                Route::get('/strap-pictures', [ModelTemplateController::class, 'getStrapPictures'])
                    ->name('strap-pictures');
                Route::post('/note-info-line1', [ModelTemplateController::class, 'createNoteInfoLine1'])
                    ->name('note-info-line1.create');
                Route::post('/note-info-line2', [ModelTemplateController::class, 'createNoteInfoLine2'])
                    ->name('note-info-line2.create');
                Route::post('/tool', [ModelTemplateController::class, 'createTool'])
                    ->name('tool.create');
                Route::post('/strap-picture', [ModelTemplateController::class, 'uploadStrapPicture'])
                    ->name('strap-picture.create');
            });
    });
