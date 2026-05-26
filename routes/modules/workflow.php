<?php

use App\Http\Controllers\Workflow\GroupController;
use App\Http\Controllers\Workflow\ManagerController;
use App\Http\Controllers\Workflow\ProcedureController;
use App\Http\Controllers\Workflow\ProcedureTemplateController;
use App\Http\Controllers\Workflow\ShareController;
use App\Http\Controllers\Workflow\TicketController;
use App\Http\Controllers\Workflow\TicketTemplateController;
use App\Http\Controllers\Workflow\WorkflowDashboardController;
use App\Http\Controllers\Workflow\WorkflowReportController;
use App\Http\Controllers\Workflow\WorkflowSettingsController;
use App\Http\Controllers\Workflow\WorkflowUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workflow module
|--------------------------------------------------------------------------
| Required from routes/web.php inside the auth middleware group.
*/
Route::prefix('workflow')->name('workflow.')->group(function () {

    // Dashboard
    Route::get('/', [WorkflowDashboardController::class, 'index'])->middleware('permission:workflow.tickets.read')->name('dashboard');

    // Tickets
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/', [TicketController::class, 'read'])->middleware('permission:workflow.tickets.read')->name('index');
        Route::get('/create', [TicketController::class, 'create'])->middleware('permission:workflow.tickets.create')->name('create');
        Route::post('/', [TicketController::class, 'store'])->middleware('permission:workflow.tickets.create')->name('store');
        Route::get('/{ticket}/inputs/{recordInput}/file', [TicketController::class, 'downloadInputFile'])->middleware('permission:workflow.tickets.read')->name('input-file');
        Route::delete('/{ticket}/inputs/{recordInput}/file', [TicketController::class, 'deleteInputFile'])->middleware('permission:workflow.tickets.write')->name('input-file.delete');
        Route::get('/{ticket}', [TicketController::class, 'show'])->middleware('permission:workflow.tickets.read')->name('show');
        Route::get('/{ticket}/edit', [TicketController::class, 'edit'])->middleware('permission:workflow.tickets.write')->name('edit');
        Route::put('/{ticket}', [TicketController::class, 'write'])->middleware('permission:workflow.tickets.write')->name('update');
        Route::delete('/{ticket}', [TicketController::class, 'unlink'])->middleware('permission:workflow.tickets.unlink')->name('delete');
        Route::patch('/{ticket}/resolve', [TicketController::class, 'resolve'])->middleware('permission:workflow.tickets.write')->name('resolve');
        Route::patch('/{ticket}/close', [TicketController::class, 'close'])->middleware('permission:workflow.tickets.write')->name('close');
        Route::patch('/{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('permission:workflow.tickets.write')->name('reopen');
        Route::patch('/{ticket}/archive', [TicketController::class, 'archive'])->middleware('permission:workflow.tickets.write')->name('archive');
        Route::patch('/{ticket}/unarchive', [TicketController::class, 'unarchive'])->middleware('permission:workflow.tickets.write')->name('unarchive');
        Route::post('/{ticket}/comment', [TicketController::class, 'addComment'])->middleware('permission:workflow.tickets.write')->name('comment');
        Route::patch('/{ticket}/field', [TicketController::class, 'saveField'])->middleware('permission:workflow.tickets.write')->name('save-field');
        Route::patch('/{ticket}/inputs', [TicketController::class, 'saveInputs'])->middleware('permission:workflow.tickets.write')->name('save-inputs');
        Route::post('/{ticket}/viewers', [TicketController::class, 'addViewer'])->middleware('permission:workflow.tickets.write')->name('add-viewer');
        Route::post('/{ticket}/chat', [TicketController::class, 'sendChat'])->middleware('permission:workflow.tickets.write')->name('chat.store');
        Route::get('/{ticket}/chat/files/{file}', [TicketController::class, 'chatFile'])->middleware('permission:workflow.tickets.read')->name('chat.file');
        Route::delete('/{ticket}/viewers/{user}', [TicketController::class, 'removeViewer'])->middleware('permission:workflow.tickets.write')->name('remove-viewer');
        Route::get('/{ticket}/viewers/lookup', [TicketController::class, 'viewersLookup'])->middleware('permission:workflow.tickets.read')->name('viewers-lookup');
        Route::post('/{ticket}/sub-procedures/{line}/start', [TicketController::class, 'startSubProcedure'])->middleware('permission:workflow.tickets.write')->name('sub-procedures.start');
    });

    // Sharing
    Route::prefix('share')->name('share.')->group(function () {
        Route::post('/ticket/{ticket}/toggle', [ShareController::class, 'toggleTicket'])->middleware('permission:workflow.tickets.write')->name('ticket.toggle');
        Route::patch('/ticket/{ticket}/message', [ShareController::class, 'messageTicket'])->middleware('permission:workflow.tickets.write')->name('ticket.message');
        Route::post('/procedure/{procedure}/toggle', [ShareController::class, 'toggleProcedure'])->middleware('permission:workflow.procedures.write')->name('procedure.toggle');
        Route::patch('/procedure/{procedure}/message', [ShareController::class, 'messageProcedure'])->middleware('permission:workflow.procedures.write')->name('procedure.message');
        Route::post('/procedure/{procedure}/ticket/{ticket}/toggle', [ShareController::class, 'toggleProcedureTicket'])->middleware('permission:workflow.procedures.write')->name('procedure-ticket.toggle');
        Route::patch('/procedure/{procedure}/ticket/{ticket}/message', [ShareController::class, 'messageProcedureTicket'])->middleware('permission:workflow.procedures.write')->name('procedure-ticket.message');
    });

    // Procedures
    Route::prefix('procedures')->name('procedures.')->group(function () {
        Route::get('/', [ProcedureController::class, 'read'])->middleware('permission:workflow.procedures.read')->name('index');
        Route::get('/create', [ProcedureController::class, 'create'])->middleware('permission:workflow.procedures.create')->name('create');
        Route::post('/', [ProcedureController::class, 'store'])->middleware('permission:workflow.procedures.create')->name('store');
        Route::get('/{procedure}', [ProcedureController::class, 'show'])->middleware('permission:workflow.procedures.read')->name('show');
        Route::delete('/{procedure}', [ProcedureController::class, 'unlink'])->middleware('permission:workflow.procedures.unlink')->name('delete');
        Route::patch('/{procedure}/close', [ProcedureController::class, 'close'])->middleware('permission:workflow.procedures.write')->name('close');
        Route::patch('/{procedure}/archive', [ProcedureController::class, 'archive'])->middleware('permission:workflow.procedures.write')->name('archive');
        Route::patch('/{procedure}/unarchive', [ProcedureController::class, 'unarchive'])->middleware('permission:workflow.procedures.write')->name('unarchive');
        Route::post('/{procedure}/comment', [ProcedureController::class, 'addComment'])->middleware('permission:workflow.procedures.write')->name('comment');
        Route::patch('/{procedure}/tickets/{ticket}/inputs', [ProcedureController::class, 'saveTicketInputs'])->middleware('permission:workflow.procedures.write')->name('tickets.inputs');
        Route::patch('/{procedure}/tickets/{ticket}/complete', [ProcedureController::class, 'completeTicket'])->middleware('permission:workflow.procedures.write')->name('tickets.complete');
        Route::patch('/{procedure}/tickets/{ticket}/reject', [ProcedureController::class, 'rejectTicket'])->middleware('permission:workflow.procedures.write')->name('tickets.reject');
        Route::patch('/{procedure}/tickets/{ticket}/skip', [ProcedureController::class, 'skipTicket'])->middleware('permission:workflow.procedures.write')->name('tickets.skip');
        Route::post('/{procedure}/tickets/{ticket}/path', [ProcedureController::class, 'choosePath'])->middleware('permission:workflow.procedures.write')->name('tickets.path');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [WorkflowReportController::class, 'index'])->middleware('permission:workflow.tickets.read')->name('index');
        Route::get('/{report}', [WorkflowReportController::class, 'show'])->middleware('permission:workflow.tickets.read')->name('show');
    });

    // Settings
    Route::get('/settings', [WorkflowSettingsController::class, 'index'])->middleware('permission:workflow.config.read')->name('settings.index');

    // Configuration
    Route::prefix('configuration')->name('config.')->group(function () {

        Route::prefix('groups')->name('groups.')->group(function () {
            Route::get('/', [GroupController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
            Route::get('/create', [GroupController::class, 'create'])->middleware('permission:workflow.config.write')->name('create');
            Route::post('/', [GroupController::class, 'store'])->middleware('permission:workflow.config.write')->name('store');
            Route::get('/{group}', [GroupController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
            Route::get('/{group}/edit', [GroupController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
            Route::put('/{group}', [GroupController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
            Route::delete('/{group}', [GroupController::class, 'unlink'])->middleware('permission:workflow.config.unlink')->name('delete');
            Route::post('/{group}/comment', [GroupController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
        });

        Route::prefix('managers')->name('managers.')->group(function () {
            Route::get('/', [ManagerController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
            Route::get('/{manager}', [ManagerController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
            Route::post('/{manager}/comment', [ManagerController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [WorkflowUserController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
            Route::get('/{user}', [WorkflowUserController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
            Route::get('/{user}/edit', [WorkflowUserController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
            Route::put('/{user}', [WorkflowUserController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
            Route::post('/{user}/comment', [WorkflowUserController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
        });

        Route::prefix('ticket-templates')->name('ticket-templates.')->group(function () {
            Route::get('/', [TicketTemplateController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
            Route::get('/create', [TicketTemplateController::class, 'create'])->middleware('permission:workflow.config.write')->name('create');
            Route::post('/', [TicketTemplateController::class, 'store'])->middleware('permission:workflow.config.write')->name('store');
            Route::get('/{ticketTemplate}', [TicketTemplateController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
            Route::get('/{ticketTemplate}/edit', [TicketTemplateController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
            Route::put('/{ticketTemplate}', [TicketTemplateController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
            Route::delete('/{ticketTemplate}', [TicketTemplateController::class, 'unlink'])->middleware('permission:workflow.config.unlink')->name('delete');
            Route::post('/{ticketTemplate}/comment', [TicketTemplateController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
        });

        Route::prefix('procedure-templates')->name('procedure-templates.')->group(function () {
            Route::get('/', [ProcedureTemplateController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
            Route::get('/create', [ProcedureTemplateController::class, 'create'])->middleware('permission:workflow.config.write')->name('create');
            Route::post('/', [ProcedureTemplateController::class, 'store'])->middleware('permission:workflow.config.write')->name('store');
            Route::get('/{procedureTemplate}', [ProcedureTemplateController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
            Route::get('/{procedureTemplate}/edit', [ProcedureTemplateController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
            Route::put('/{procedureTemplate}', [ProcedureTemplateController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
            Route::delete('/{procedureTemplate}', [ProcedureTemplateController::class, 'unlink'])->middleware('permission:workflow.config.unlink')->name('delete');
            Route::post('/{procedureTemplate}/comment', [ProcedureTemplateController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
            Route::post('/{procedureTemplate}/steps', [ProcedureTemplateController::class, 'storeStep'])->middleware('permission:workflow.config.write')->name('steps.store');
            Route::get('/{procedureTemplate}/steps/{step}/edit', [ProcedureTemplateController::class, 'editStep'])->middleware('permission:workflow.config.write')->name('steps.edit');
            Route::put('/{procedureTemplate}/steps/{step}', [ProcedureTemplateController::class, 'updateStep'])->middleware('permission:workflow.config.write')->name('steps.update');
            Route::delete('/{procedureTemplate}/steps/{step}', [ProcedureTemplateController::class, 'destroyStep'])->middleware('permission:workflow.config.unlink')->name('steps.destroy');
            Route::get('/{procedureTemplate}/steps/lookup', [ProcedureTemplateController::class, 'stepsLookup'])->middleware('permission:workflow.config.read')->name('steps.lookup');
            Route::get('/{procedureTemplate}/flowchart', [ProcedureTemplateController::class, 'flowchart'])->middleware('permission:workflow.config.read')->name('flowchart');
            Route::post('/{procedureTemplate}/flowchart/layout', [ProcedureTemplateController::class, 'saveFlowchartLayout'])->middleware('permission:workflow.config.write')->name('flowchart.layout.save');
            Route::post('/{procedureTemplate}/flowchart/layout/reset', [ProcedureTemplateController::class, 'resetFlowchartLayout'])->middleware('permission:workflow.config.write')->name('flowchart.layout.reset');
        });

    });

});
